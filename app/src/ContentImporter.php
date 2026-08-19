<?php
declare(strict_types=1);

/**
 * Перенос контента из файлов app/content в базу.
 *
 * Файлы после переноса остаются на месте и продолжают работать резервом —
 * переезд можно делать постранично и в любой момент откатить, просто очистив
 * таблицы. Повторный запуск обновляет уже перенесённые страницы.
 *
 * Данные блоков сохраняются как есть, без приведения к схеме: реестр блоков
 * мог описать не все поля, и терять контент из-за этого нельзя. Вместо этого
 * импортёр возвращает список полей, которых нет в схеме, — их видно в отчёте.
 */
final class ContentImporter
{
    private Db $db;
    private Site $site;
    private PageRepository $pages;
    private array $routes;

    /** @var array<string,true> поля, не описанные в реестре блоков */
    private array $unknownFields = [];

    public function __construct(Db $db, Site $site, PageRepository $pages)
    {
        $this->db = $db;
        $this->site = $site;
        $this->pages = $pages;
        $this->routes = require APP_DIR . '/routes.php';
    }

    /** @return array{pages:int,blocks:int,unknown:list<string>,skipped:list<string>} */
    public function run(): array
    {
        $stats = ['pages' => 0, 'blocks' => 0, 'skipped' => []];

        foreach ($this->routes as $key => $definition) {
            foreach (array_keys($this->site->locales()) as $locale) {
                $pattern = $definition['slug'][$locale] ?? null;

                if ($pattern === null) {
                    continue;
                }

                foreach ($this->targets($key, $definition, $pattern, $locale) as $target) {
                    $content = $this->load($target['file']);

                    if ($content === null) {
                        $stats['skipped'][] = $target['file'];
                        continue;
                    }

                    $blocks = $this->importPage($target, $definition, $locale, $content);

                    $stats['pages']++;
                    $stats['blocks'] += $blocks;
                }
            }
        }

        $stats['unknown'] = array_keys($this->unknownFields);
        sort($stats['unknown']);

        return $stats;
    }

    /**
     * Какие страницы соответствуют маршруту: одна для обычного,
     * по файлу на каждую — для шаблонного.
     *
     * @return list<array{key:string,slug:string,file:string,section:string}>
     */
    private function targets(string $key, array $definition, string $pattern, string $locale): array
    {
        $pattern = trim($pattern, '/');

        if (!str_contains($pattern, '{slug}')) {
            $name = (string) ($definition['content'] ?? $key);

            return [[
                'key'     => $key,
                'slug'    => $pattern,
                'file'    => APP_DIR . '/content/' . $name . '.' . $locale . '.php',
                'section' => '',
            ]];
        }

        $dir = APP_DIR . '/content/' . trim((string) ($definition['content_dir'] ?? $key), '/');

        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (!preg_match('~^([a-z0-9][a-z0-9-]*)\.' . preg_quote($locale, '~') . '\.php$~', $file, $m)) {
                continue;
            }

            $out[] = [
                'key'     => $key . '/' . $m[1],
                'slug'    => str_replace('{slug}', $m[1], $pattern),
                'file'    => $dir . '/' . $file,
                'section' => $key,
            ];
        }

        return $out;
    }

    /** Выполняет файл контента в изолированной области видимости. */
    private function load(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $site = $this->site;
        $content = require $file;

        return is_array($content) ? $content : null;
    }

    /** @return int сколько блоков перенесено */
    private function importPage(array $target, array $definition, string $locale, array $content): int
    {
        return $this->db->transaction(function () use ($target, $definition, $locale, $content): int {
            $page = $this->pages->findByKey($target['key']);

            $pageId = $page === null
                ? $this->pages->createPage(
                    $target['key'],
                    (string) ($definition['template'] ?? 'home'),
                    $target['section'],
                    true
                )
                : (int) $page['id'];

            $meta = $content['meta'] ?? [];

            $this->pages->saveLocale($pageId, $locale, [
                'slug'         => $target['slug'],
                'title'        => (string) ($meta['title'] ?? ''),
                'description'  => (string) ($meta['description'] ?? ''),
                'og_image'     => (string) ($meta['og_image'] ?? ''),
                'bar'          => $meta['bar'] ?? null,
                'is_published' => true,
            ]);

            // Переносим начисто: иначе повторный импорт удвоит блоки
            $this->db->delete(
                'page_blocks',
                'page_id = :id AND locale = :locale',
                ['id' => $pageId, 'locale' => $locale]
            );

            $count = 0;
            $sort = 10;

            foreach ($content['blocks'] ?? [] as $block) {
                $type = (string) ($block['type'] ?? '');

                if ($type === '' || !Blocks::exists($type)) {
                    continue;
                }

                $data = $block;
                unset($data['type']);

                $this->collectUnknownFields($type, $data);

                $this->pages->addBlock($pageId, $locale, $type, $data, $sort);

                $sort += 10;
                $count++;
            }

            return $count;
        });
    }

    /** Отмечает поля, которых нет в схеме блока: подсказка, что дополнить в реестре. */
    private function collectUnknownFields(string $type, array $data, string $prefix = ''): void
    {
        $definition = Blocks::definition($type);

        if ($definition === null) {
            return;
        }

        $fields = $prefix === '' ? $definition['fields'] : $this->fieldsAt($definition['fields'], $prefix);

        foreach ($data as $name => $value) {
            if (!is_string($name) || str_starts_with($name, '_')) {
                continue;
            }

            if (!isset($fields[$name])) {
                $this->unknownFields[$type . '.' . ltrim($prefix . '.' . $name, '.')] = true;
                continue;
            }

            $field = $fields[$name];

            if (($field['type'] ?? '') === 'group' && is_array($value)) {
                $this->collectUnknownFields($type, $value, ltrim($prefix . '.' . $name, '.'));
            }

            if (($field['type'] ?? '') === 'list' && is_array($value) && is_array($field['of'] ?? null)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $this->collectUnknownFields($type, $item, ltrim($prefix . '.' . $name, '.') . '.*');
                    }
                }
            }
        }
    }

    /** Находит описание вложенных полей по пути вида «offer» или «cards.*». */
    private function fieldsAt(array $fields, string $path): array
    {
        foreach (explode('.', $path) as $segment) {
            if ($segment === '*') {
                continue;
            }

            $field = $fields[$segment] ?? null;

            if ($field === null) {
                return [];
            }

            if (($field['type'] ?? '') === 'group') {
                $fields = $field['fields'];
                continue;
            }

            if (($field['type'] ?? '') === 'list' && is_array($field['of'] ?? null)) {
                $fields = $field['of'];
                continue;
            }

            return [];
        }

        return $fields;
    }
}
