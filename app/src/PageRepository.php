<?php
declare(strict_types=1);

/**
 * Доступ к страницам. Основной источник — база; пока контент не переехал,
 * работает резерв на файлах content/*.php.
 *
 * Публичная часть не должна зависеть от админки: если база недоступна,
 * сайт продолжает отдавать страницы из файлов.
 */
final class PageRepository
{
    private Db $db;
    private array $config;
    private ?bool $ready = null;

    public function __construct(Db $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /** Готова ли база принимать запросы контента. */
    public function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        return $this->ready = $this->db->isAvailable() && $this->db->tableExists('page_locales');
    }

    /* ------------------------------------------------------- публичная часть */

    /**
     * Страница по адресу. $includeDrafts — для предпросмотра из админки.
     *
     * @return array{id:int,template:string,meta:array,blocks:list<array>,alternates:array}|null
     */
    public function findByPath(string $path, string $locale, bool $includeDrafts = false): ?array
    {
        if (!$this->isReady()) {
            return null;
        }

        $slug = trim($path, '/');
        $row = $this->localeRowBySlug($slug, $locale, $includeDrafts);
        $contentLocale = $locale;

        /*
         * Перевода ещё нет — показываем версию на основном языке по тому же
         * адресу. Так же ведут себя файлы контента: страница не должна
         * пропадать из-за незаконченного перевода.
         */
        if ($row === null && $locale !== $this->defaultLocale()) {
            $row = $this->localeRowBySlug($slug, $this->defaultLocale(), $includeDrafts);
            $contentLocale = $this->defaultLocale();
        }

        if ($row === null) {
            return null;
        }

        $pageId = (int) $row['page_id'];
        $blocks = $this->blocks($pageId, $contentLocale, $includeDrafts);

        // Языковая версия заведена, но блоки ещё не переведены
        if ($blocks === [] && $contentLocale !== $this->defaultLocale()) {
            $blocks = $this->blocks($pageId, $this->defaultLocale(), $includeDrafts);
        }

        return [
            'id'         => $pageId,
            'template'   => (string) $row['template'],
            'meta'       => $this->metaFromRow($row),
            'blocks'     => $blocks,
            'alternates' => $this->alternates($pageId),
        ];
    }

    private function localeRowBySlug(string $slug, string $locale, bool $includeDrafts): ?array
    {
        /*
         * page_id выбираем под своим именем: `l.*` содержит собственный id
         * языковой версии и затирает id страницы. Пока записей было мало,
         * они совпадали, и ошибка не проявлялась.
         */
        $sql = 'SELECT p.id AS page_id, p.template, l.*
                  FROM page_locales l
                  JOIN pages p ON p.id = l.page_id
                 WHERE l.locale = :locale AND l.slug = :slug';

        if (!$includeDrafts) {
            $sql .= ' AND l.is_published = 1';
        }

        return $this->db->first($sql, ['locale' => $locale, 'slug' => $slug]);
    }

    private function defaultLocale(): string
    {
        return (string) array_key_first($this->config['locales'] ?? ['ru' => []]);
    }

    /**
     * Заведена ли такая страница в базе — в любом состоянии.
     *
     * Нужно, чтобы снятие с публикации действительно убирало страницу:
     * иначе роутер откатился бы на файл и она осталась бы доступной.
     */
    public function pathExists(string $path, string $locale): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        return (int) $this->db->value(
            'SELECT COUNT(*) FROM page_locales WHERE locale = :locale AND slug = :slug',
            ['locale' => $locale, 'slug' => trim($path, '/')],
            0
        ) > 0;
    }

    /** Разделы страниц: код => название. @return array<string,string> */
    public static function sections(): array
    {
        static $sections = null;

        return $sections ??= require APP_DIR . '/sections.php';
    }

    /**
     * Каталог страниц для выпадающих списков в админке.
     *
     * Возвращает адрес, короткое название и раздел каждой страницы на нужном
     * языке. Адрес отдаём без префикса языка — ссылки в блоках хранятся
     * именно так, префикс подставляет Site::url() при выводе.
     *
     * @return list<array{url:string,title:string,section:string,published:bool}>
     */
    public function catalog(string $locale): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $rows = $this->db->all(
            'SELECT l.slug, l.title, l.is_published, p.section, p.page_key
               FROM page_locales l
               JOIN pages p ON p.id = l.page_id
              WHERE l.locale = :locale
              ORDER BY p.section, p.sort, l.slug',
            ['locale' => $locale]
        );

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'url'       => (string) $row['slug'],
                'title'     => self::shortTitle((string) $row['title'], (string) $row['page_key']),
                'section'   => (string) $row['section'],
                'published' => (bool) $row['is_published'],
            ];
        }

        return $out;
    }

    /**
     * Короткое имя для списка: заголовки страниц написаны под поиск
     * («Складам сельхозпредприятий KULAGER | Подвоз мешков…»), в выпадающем
     * списке нужна только первая часть.
     */
    private static function shortTitle(string $title, string $fallback): string
    {
        $title = trim(preg_split('~\s+[|—]\s+~u', $title, 2)[0] ?? '');

        if ($title === '') {
            return $fallback;
        }

        return mb_strlen($title) > 70 ? mb_substr($title, 0, 69) . '…' : $title;
    }

    /** Опубликованные языковые версии для карты сайта. @return list<array> */
    public function publishedForSitemap(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        return $this->db->all(
            'SELECT page_id, locale, slug, published_at, updated_at
               FROM page_locales
              WHERE is_published = 1 AND noindex = 0
              ORDER BY locale, slug'
        );
    }

    /** @return array<string,string> язык => slug */
    public function alternates(int $pageId): array
    {
        $rows = $this->db->all(
            'SELECT locale, slug FROM page_locales WHERE page_id = :id',
            ['id' => $pageId]
        );

        return array_column($rows, 'slug', 'locale');
    }

    /**
     * Блоки страницы в порядке вывода.
     *
     * @return list<array>
     */
    public function blocks(int $pageId, string $locale, bool $includeHidden = false): array
    {
        $sql = 'SELECT id, type, data_json, is_visible, sort
                  FROM page_blocks
                 WHERE page_id = :id AND locale = :locale';

        if (!$includeHidden) {
            $sql .= ' AND is_visible = 1';
        }

        $sql .= ' ORDER BY sort, id';

        $out = [];
        foreach ($this->db->all($sql, ['id' => $pageId, 'locale' => $locale]) as $row) {
            $data = json_decode((string) $row['data_json'], true);
            $data = is_array($data) ? $data : [];

            $data['type'] = $row['type'];
            $data['_id'] = (int) $row['id'];
            $data['_visible'] = (bool) $row['is_visible'];

            $out[] = $data;
        }

        return $out;
    }

    /* ------------------------------------------------------------- админка */

    /** @return list<array> страницы со сводкой по языкам */
    public function listPages(): array
    {
        $pages = $this->db->all('SELECT * FROM pages ORDER BY section, sort, page_key');

        if ($pages === []) {
            return [];
        }

        $locales = $this->db->all(
            'SELECT l.*, (SELECT COUNT(*) FROM page_blocks b
                            WHERE b.page_id = l.page_id AND b.locale = l.locale) AS blocks_count
               FROM page_locales l'
        );

        $byPage = [];
        foreach ($locales as $row) {
            $byPage[(int) $row['page_id']][$row['locale']] = $row;
        }

        foreach ($pages as &$page) {
            $page['locales'] = $byPage[(int) $page['id']] ?? [];
        }

        return $pages;
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM pages WHERE id = :id', ['id' => $id]);
    }

    public function findByKey(string $key): ?array
    {
        return $this->db->first('SELECT * FROM pages WHERE page_key = :key', ['key' => $key]);
    }

    public function locale(int $pageId, string $locale): ?array
    {
        return $this->db->first(
            'SELECT * FROM page_locales WHERE page_id = :id AND locale = :locale',
            ['id' => $pageId, 'locale' => $locale]
        );
    }

    public function createPage(string $key, string $template, string $section = '', bool $isSystem = false): int
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('pages', [
            'page_key'   => $key,
            'template'   => $template,
            'section'    => $section,
            'sort'       => (int) $this->db->value('SELECT COALESCE(MAX(sort), 0) + 10 FROM pages', [], 10),
            'is_system'  => $isSystem ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Занят ли адрес другой страницей. Проверяем до записи: уникальный
     * индекс иначе уронит запрос, а редактор увидит пустой экран.
     */
    public function slugTaken(string $slug, string $locale, int $exceptPageId = 0): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM page_locales WHERE locale = :locale AND slug = :slug AND page_id <> :id',
            ['locale' => $locale, 'slug' => trim($slug, '/'), 'id' => $exceptPageId],
            0
        ) > 0;
    }

    public function saveLocale(int $pageId, string $locale, array $data): void
    {
        $existing = $this->locale($pageId, $locale);

        $payload = [
            'slug'        => $data['slug'] ?? '',
            'title'       => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'og_image'    => $data['og_image'] ?? '',
            'noindex'     => !empty($data['noindex']) ? 1 : 0,
            'canonical'   => $data['canonical'] ?? '',
            'bar_json'    => isset($data['bar']) ? json_encode($data['bar'], JSON_UNESCAPED_UNICODE) : null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if (array_key_exists('is_published', $data)) {
            $payload['is_published'] = $data['is_published'] ? 1 : 0;
        }

        if ($existing === null) {
            $this->db->insert('page_locales', $payload + ['page_id' => $pageId, 'locale' => $locale]);

            return;
        }

        $this->db->update('page_locales', $payload, 'id = :id', ['id' => $existing['id']]);
    }

    public function addBlock(int $pageId, string $locale, string $type, array $data = [], ?int $sort = null): int
    {
        $sort ??= (int) $this->db->value(
            'SELECT COALESCE(MAX(sort), 0) + 10 FROM page_blocks WHERE page_id = :id AND locale = :locale',
            ['id' => $pageId, 'locale' => $locale],
            10
        );

        return $this->db->insert('page_blocks', [
            'page_id'    => $pageId,
            'locale'     => $locale,
            'sort'       => $sort,
            'type'       => $type,
            'data_json'  => json_encode($data, JSON_UNESCAPED_UNICODE),
            'is_visible' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateBlock(int $blockId, array $data): void
    {
        $this->db->update('page_blocks', [
            'data_json'  => json_encode($data, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $blockId]);
    }

    public function setBlockVisible(int $blockId, bool $visible): void
    {
        $this->db->update('page_blocks', [
            'is_visible' => $visible ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $blockId]);
    }

    public function deleteBlock(int $blockId): void
    {
        $this->db->delete('page_blocks', 'id = :id', ['id' => $blockId]);
    }

    /**
     * @param list<int> $order идентификаторы блоков в новом порядке
     *
     * В транзакции: иначе оборванный запрос оставит половину блоков
     * со старым порядком, а половину с новым.
     */
    public function reorderBlocks(array $order): void
    {
        $this->db->transaction(function () use ($order): void {
            $sort = 10;

            foreach ($order as $blockId) {
                $this->db->update('page_blocks', ['sort' => $sort], 'id = :id', ['id' => (int) $blockId]);
                $sort += 10;
            }
        });
    }

    public function findBlock(int $blockId): ?array
    {
        $row = $this->db->first('SELECT * FROM page_blocks WHERE id = :id', ['id' => $blockId]);

        if ($row === null) {
            return null;
        }

        $row['data'] = json_decode((string) $row['data_json'], true) ?: [];

        return $row;
    }

    /**
     * Копирует блоки одной языковой версии в другую — как заготовку
     * для перевода. Существующие блоки получателя заменяются.
     *
     * @return int сколько блоков скопировано
     */
    public function copyBlocks(int $pageId, string $from, string $to): int
    {
        return $this->db->transaction(function () use ($pageId, $from, $to): int {
            $source = $this->db->all(
                'SELECT sort, type, data_json, is_visible FROM page_blocks
                  WHERE page_id = :id AND locale = :locale ORDER BY sort, id',
                ['id' => $pageId, 'locale' => $from]
            );

            if ($source === []) {
                return 0;
            }

            $this->db->delete(
                'page_blocks',
                'page_id = :id AND locale = :locale',
                ['id' => $pageId, 'locale' => $to]
            );

            $now = date('Y-m-d H:i:s');

            foreach ($source as $block) {
                $this->db->insert('page_blocks', [
                    'page_id'    => $pageId,
                    'locale'     => $to,
                    'sort'       => (int) $block['sort'],
                    'type'       => $block['type'],
                    'data_json'  => $block['data_json'],
                    'is_visible' => (int) $block['is_visible'],
                    'updated_at' => $now,
                ]);
            }

            return count($source);
        });
    }

    /**
     * Сколько блоков ещё не переведено: содержимое совпадает с основным
     * языком дословно. Это подсказка, а не строгий учёт — часть строк
     * (цифры, адреса) совпадает и в переведённой версии.
     *
     * @return array{total:int,same:int}
     */
    public function translationStats(int $pageId, string $locale): array
    {
        $base = $this->defaultLocale();

        if ($locale === $base) {
            return ['total' => 0, 'same' => 0];
        }

        $rows = $this->db->all(
            'SELECT locale, sort, type, data_json FROM page_blocks
              WHERE page_id = :id AND locale IN (:a, :b) ORDER BY sort, id',
            ['id' => $pageId, 'a' => $base, 'b' => $locale]
        );

        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[$row['locale']][] = $row;
        }

        $target = $byLocale[$locale] ?? [];
        $source = $byLocale[$base] ?? [];
        $same = 0;

        foreach ($target as $index => $block) {
            $origin = $source[$index] ?? null;

            if ($origin !== null && $origin['type'] === $block['type'] && $origin['data_json'] === $block['data_json']) {
                $same++;
            }
        }

        return ['total' => count($target), 'same' => $same];
    }

    /**
     * Публикация языковой версии.
     *
     * Снимок перед этим делает тот, кто правит: снимки живут в PageRevisions,
     * и репозиторию страниц незачем знать про отмену правок.
     */
    public function publish(int $pageId, string $locale): void
    {
        $this->db->update('page_locales', [
            'is_published' => 1,
            'published_at' => date('Y-m-d H:i:s'),
        ], 'page_id = :id AND locale = :locale', ['id' => $pageId, 'locale' => $locale]);
    }

    public function unpublish(int $pageId, string $locale): void
    {
        $this->db->update(
            'page_locales',
            ['is_published' => 0],
            'page_id = :id AND locale = :locale',
            ['id' => $pageId, 'locale' => $locale]
        );
    }

    /* ------------------------------------------------------------ служебное */

    private function metaFromRow(array $row): array
    {
        $meta = [
            'title'       => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'noindex'     => !empty($row['noindex']),
        ];

        if (($row['canonical'] ?? '') !== '') {
            $meta['canonical'] = (string) $row['canonical'];
        }

        if (($row['og_image'] ?? '') !== '') {
            $meta['og_image'] = $row['og_image'];
        }

        $bar = json_decode((string) ($row['bar_json'] ?? ''), true);
        if (is_array($bar) && $bar !== []) {
            $meta['bar'] = $bar;
        }

        return $meta;
    }
}
