<?php
declare(strict_types=1);

/**
 * Меню сайта в базе.
 *
 * Пункты хранятся одной таблицей: menu_key — какое это меню, parent_id —
 * вложенность (нужна только группам отраслей). Наружу отдаётся ровно та же
 * структура, что лежала в content/navigation.{язык}.php, поэтому шаблоны
 * и Site::nav() не знают, откуда пришло меню — из базы или из файла.
 */
final class NavigationRepository
{
    /** Меню, которые умеет показывать вёрстка: ключ => название для админки. */
    public const MENUS = [
        'main'           => 'Главное меню',
        'models'         => 'Модели',
        'service'        => 'Обслуживание',
        'company'        => 'Компания',
        'industries'     => 'Отрасли (с группами)',
        'industries_all' => 'Ссылка «Все отрасли»',
    ];

    /** Меню, где пункты объединены в группы. */
    private const GROUPED = ['industries'];

    /** Меню из одной ссылки. */
    private const SINGLE = ['industries_all'];

    public function __construct(private Db $db)
    {
    }

    public function isReady(): bool
    {
        return $this->db->isAvailable() && $this->db->tableExists('navigation');
    }

    /** Заполнено ли меню хоть для одного языка. */
    public function hasItems(): bool
    {
        return $this->isReady() && (int) $this->db->value('SELECT COUNT(*) FROM navigation', [], 0) > 0;
    }

    /**
     * Всё меню языка в том же виде, в каком его отдавал файл навигации.
     *
     * $area — где меню показывается: 'drawer' (шапка, боковое меню, блок
     * отраслей) или 'footer'. Пункт один, но в подвале у него может быть
     * своё название и его можно оттуда убрать, не трогая остальное.
     *
     * @return array<string,mixed>
     */
    public function tree(string $locale, string $area = 'drawer'): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $rows = $this->db->all(
            'SELECT * FROM navigation WHERE locale = :locale ORDER BY menu_key, sort, id',
            ['locale' => $locale]
        );

        if ($rows === []) {
            return [];
        }

        $out = [];
        $groups = [];

        foreach ($rows as $row) {
            $menu = (string) $row['menu_key'];

            // Пункт, снятый с этой части сайта, сюда не попадает
            if ($area === 'footer' && array_key_exists('in_footer', $row) && !$row['in_footer']) {
                continue;
            }

            $item = $this->asItem($row, $area);

            if (in_array($menu, self::SINGLE, true)) {
                $out[$menu] = $item;
                continue;
            }

            if (!in_array($menu, self::GROUPED, true)) {
                $out[$menu][] = $item;
                continue;
            }

            // Группы отраслей: сначала запоминаем сами группы, потом их пункты
            if ($row['parent_id'] === null) {
                $groups[(int) $row['id']] = [
                    'title'      => (string) $row['title'],
                    'full_title' => (string) ($row['full_title'] ?: $row['title']),
                    '_id'        => (int) $row['id'],
                    '_field'     => 'title',
                    'items'      => [],
                ];
                continue;
            }

            $parent = (int) $row['parent_id'];

            if (isset($groups[$parent])) {
                $groups[$parent]['items'][] = $item;
            }
        }

        if ($groups !== []) {
            $out['industries'] = array_values($groups);
        }

        // Пустые меню тоже нужны: иначе шаблон возьмёт их из файла
        foreach (array_keys(self::MENUS) as $menu) {
            if (!isset($out[$menu]) && !in_array($menu, self::SINGLE, true)) {
                $out[$menu] = [];
            }
        }

        return $out;
    }

    /** @return array{title:string,url:string,_id:int,_field:string,in_drawer?:bool} */
    private function asItem(array $row, string $area = 'drawer'): array
    {
        $footerTitle = (string) ($row['footer_title'] ?? '');
        $inFooter = $area === 'footer' && $footerTitle !== '';

        $item = [
            'title' => $inFooter ? $footerTitle : (string) $row['title'],
            'url'   => (string) $row['url'],
            // Идентификатор и поле нужны правке на странице: в подвале
            // и в боковом меню правятся разные названия одного пункта
            '_id'    => (int) $row['id'],
            '_field' => $area === 'footer' ? 'footer_title' : 'title',
        ];

        // Ключ пишем, только когда пункт спрятан: так проще сравнивать с файлами
        if (!$row['in_drawer']) {
            $item['in_drawer'] = false;
        }

        return $item;
    }

    /* ------------------------------------------------------------ правка */

    /** Пункты одного меню для формы в админке. @return list<array> */
    public function items(string $menu, string $locale): array
    {
        return $this->db->all(
            'SELECT * FROM navigation WHERE menu_key = :menu AND locale = :locale ORDER BY sort, id',
            ['menu' => $menu, 'locale' => $locale]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM navigation WHERE id = :id', ['id' => $id]);
    }

    public function add(string $menu, string $locale, array $data): int
    {
        $sort = (int) $this->db->value(
            'SELECT COALESCE(MAX(sort), 0) + 1 FROM navigation WHERE menu_key = :menu AND locale = :locale',
            ['menu' => $menu, 'locale' => $locale],
            1
        );

        return $this->db->insert('navigation', [
            'menu_key'     => $menu,
            'locale'       => $locale,
            'parent_id'    => $data['parent_id'] ?? null,
            'sort'         => $sort,
            'title'        => (string) $data['title'],
            'full_title'   => (string) ($data['full_title'] ?? ''),
            'footer_title' => (string) ($data['footer_title'] ?? ''),
            'url'          => (string) ($data['url'] ?? ''),
            'in_drawer'    => !empty($data['in_drawer']) ? 1 : 0,
            'in_footer'    => 1,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('navigation', [
            'parent_id'    => $data['parent_id'] ?? null,
            'title'        => (string) $data['title'],
            'full_title'   => (string) ($data['full_title'] ?? ''),
            'footer_title' => (string) ($data['footer_title'] ?? ''),
            'url'          => (string) ($data['url'] ?? ''),
            'in_drawer'    => !empty($data['in_drawer']) ? 1 : 0,
            'in_footer'    => !empty($data['in_footer']) ? 1 : 0,
        ], 'id = :id', ['id' => $id]);
    }

    /** Удаляет пункт вместе с вложенными: группа без своих отраслей не нужна. */
    public function delete(int $id): void
    {
        $this->db->delete('navigation', 'parent_id = :id', ['id' => $id]);
        $this->db->delete('navigation', 'id = :id', ['id' => $id]);
    }

    /** @param list<int> $order идентификаторы в новом порядке */
    public function reorder(array $order): void
    {
        foreach (array_values($order) as $index => $id) {
            $this->db->update('navigation', ['sort' => $index], 'id = :id', ['id' => (int) $id]);
        }
    }

    /* ---------------------------------------------------------- перенос */

    /**
     * Переносит меню из файлов content/navigation.{язык}.php в базу.
     * Существующие пункты языка заменяются целиком — перенос повторяем
     * столько раз, сколько нужно, дублей не будет.
     *
     * @return int сколько пунктов записано
     */
    public function importFromFiles(array $locales): int
    {
        $written = 0;

        foreach ($locales as $locale) {
            $file = APP_DIR . '/content/navigation.' . $locale . '.php';

            if (!is_file($file)) {
                continue;
            }

            $data = (array) require $file;

            $this->db->delete('navigation', 'locale = :locale', ['locale' => $locale]);

            foreach ($data as $menu => $value) {
                if (!isset(self::MENUS[$menu])) {
                    continue;
                }

                $written += $this->importMenu($menu, $locale, $value);
            }
        }

        return $written;
    }

    private function importMenu(string $menu, string $locale, mixed $value): int
    {
        if (in_array($menu, self::SINGLE, true)) {
            $this->add($menu, $locale, [
                'title'     => (string) ($value['title'] ?? ''),
                'url'       => (string) ($value['url'] ?? ''),
                'in_drawer' => true,
            ]);

            return 1;
        }

        $written = 0;

        foreach ((array) $value as $item) {
            // Группа отраслей: сначала сама группа, потом её пункты
            if (isset($item['items'])) {
                $parent = $this->add($menu, $locale, [
                    'title'      => (string) $item['title'],
                    'full_title' => (string) ($item['full_title'] ?? ''),
                    'in_drawer'  => true,
                ]);
                $written++;

                foreach ((array) $item['items'] as $child) {
                    $this->add($menu, $locale, [
                        'parent_id' => $parent,
                        'title'     => (string) $child['title'],
                        'url'       => (string) $child['url'],
                        'in_drawer' => $child['in_drawer'] ?? true,
                    ]);
                    $written++;
                }

                continue;
            }

            $this->add($menu, $locale, [
                'title'     => (string) $item['title'],
                'url'       => (string) ($item['url'] ?? ''),
                'in_drawer' => $item['in_drawer'] ?? true,
            ]);
            $written++;
        }

        return $written;
    }
}
