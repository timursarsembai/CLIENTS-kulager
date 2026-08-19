<?php
declare(strict_types=1);

/**
 * Цветовые темы сайта.
 *
 * Отдаёт их в том же виде, в каком лежал app/themes.php, поэтому шаблоны
 * не знают, откуда пришла тема — из базы или из файла. Файл остаётся набором
 * «из коробки»: из него делается первое наполнение таблицы и запасной вариант,
 * если база недоступна.
 */
final class ThemeRepository
{
    /**
     * Переменные темы: ключ => [подпись, вид поля].
     * Порядок задаёт порядок полей в форме, поэтому группы идут подряд.
     */
    /**
     * Переменные темы, сгруппированные по смыслу.
     *
     * Раньше здесь был плоский список из трёх десятков строк вида «Линии,
     * ярче» — по названию невозможно понять, где это увидишь. Теперь у каждой
     * группы есть пояснение, а у поля — где оно проявляется на странице.
     *
     * Вид поля: color — пипетка, alpha — цвет с прозрачностью, text — как есть.
     */
    public const GROUPS = [
        'main' => [
            'title' => 'Основные цвета',
            'hint'  => 'Их видно на каждой странице. Обычно правят только их.',
            'vars'  => [
                '--bg'           => ['Фон страницы', 'color', 'Основной цвет позади всего содержимого.'],
                '--text'         => ['Текст', 'color', 'Обычные абзацы.'],
                '--text-strong'  => ['Заголовки', 'color', 'Крупные заголовки блоков.'],
                '--accent'       => ['Акцент', 'color', 'Ссылки, кнопка «Получить предложение», надзаголовки.'],
                '--accent-hover' => ['Акцент при наведении', 'color', 'Тот же акцент, когда на него навели мышь.'],
                '--accent-ink'   => ['Текст на акценте', 'color', 'Надпись внутри акцентной кнопки.'],
                '--logo'         => ['Логотип', 'color', 'Логотип перекрашивается в этот цвет.'],
            ],
        ],

        'surfaces' => [
            'title' => 'Подложки и карточки',
            'hint'  => 'Фоны, на которых лежит содержимое: карточки отраслей, секции с заливкой.',
            'vars'  => [
                '--surface'   => ['Карточка', 'color', 'Фон карточек и таблиц.'],
                '--surface-2' => ['Карточка, второй вариант', 'color', 'Для чередования карточек в сетке.'],
                '--surface-3' => ['Выделенная плашка', 'color', 'Блок «Приезжайте на тест-драйв», призыв внизу.'],
                '--panel'     => ['Секция на подложке', 'color', 'Полосы во всю ширину: «Задачи», «Сезоны».'],
                '--hover-bg'  => ['Фон при наведении', 'color', 'Подсветка пунктов меню и карточек-ссылок.'],
                '--hover-bg-2'=> ['Фон при наведении, второй', 'color', 'Там же, где нужен контраст посильнее.'],
            ],
        ],

        'text' => [
            'title' => 'Оттенки текста',
            'hint'  => 'Второстепенные надписи: подписи под значениями, примечания, пункты меню. '
                . 'Чем дальше по списку, тем тише текст.',
            'vars'  => [
                '--text-2' => ['Подзаголовки и вводные абзацы', 'color', 'Текст под заголовком блока.'],
                '--text-3' => ['Подписи в карточках', 'color', 'Мелкие строки рядом со значениями.'],
                '--text-4' => ['Примечания', 'color', 'Строки вроде «Данные демонстрационные».'],
                '--nav'    => ['Пункты меню', 'color', 'Ссылки в шапке и боковом меню.'],
                '--text-5' => ['Текст на плашках', 'color', 'Абзацы внутри выделенных блоков.'],
                '--text-6' => ['Текст на плашках, тише', 'color', 'Там же, но менее заметный.'],
                '--text-7' => ['Служебные подписи', 'color', 'Мелочи вроде подписи под фотографией.'],
            ],
        ],

        'lines' => [
            'title' => 'Линии и рамки',
            'hint'  => 'Границы карточек, разделители таблиц, сетка блоков. '
                . 'Первая — самая незаметная, последняя — самая контрастная.',
            'vars'  => [
                '--line'   => ['Рамки карточек', 'color', 'Обычные границы: карточки, таблицы, поля формы.'],
                '--line-2' => ['Разделители', 'color', 'Линии внутри карточек и между строками таблицы.'],
                '--line-3' => ['Рамки полей', 'color', 'Границы полей формы заявки.'],
                '--line-4' => ['Контурные кнопки', 'color', 'Обводка кнопок без заливки.'],
                '--line-5' => ['Самые заметные линии', 'color', 'Акцентные разделители.'],
            ],
        ],

        'overlays' => [
            'title' => 'Полупрозрачные слои',
            'hint'  => 'Шапка и панель внизу лежат поверх содержимого, поэтому у них '
                . 'полупрозрачный фон: цвет плюс прозрачность.',
            'vars'  => [
                '--header-bg'    => ['Фон шапки', 'alpha', 'Шапка при прокрутке.'],
                '--bar-bg'       => ['Фон панели внизу', 'alpha', 'Плавающая полоса с кнопкой WhatsApp.'],
                '--overlay-card' => ['Карточка поверх фото', 'alpha', 'Блок с текстом на фотографии.'],
                '--hero-scrim'   => ['Затемнение обложки', 'alpha', 'Слой между фотографией и заголовком.'],
            ],
        ],

        'advanced' => [
            'title' => 'Дополнительно',
            'hint'  => 'Меняют редко: градиент поверх обложки и прозрачность фотографий. '
                . 'Градиент пересобирается сам, когда меняете фон страницы.',
            'vars'  => [
                '--hero-overlay' => ['Градиент обложки', 'text', 'Плавный переход от фона к фотографии.'],
                '--photo-op'     => ['Прозрачность фотографий', 'text', 'От 0 до 1: чем меньше, тем бледнее снимки.'],
            ],
        ],
    ];

    /** Плоский список переменных: ключ => [подпись, вид поля]. */
    public const VARS = self::FLAT;

    /** @var array<string,array{0:string,1:string}> собирается из групп */
    private const FLAT = [
        '--bg' => ['Фон страницы', 'color'],
        '--text' => ['Текст', 'color'],
        '--text-strong' => ['Заголовки', 'color'],
        '--accent' => ['Акцент', 'color'],
        '--accent-hover' => ['Акцент при наведении', 'color'],
        '--accent-ink' => ['Текст на акценте', 'color'],
        '--logo' => ['Логотип', 'color'],
        '--surface' => ['Карточка', 'color'],
        '--surface-2' => ['Карточка, второй вариант', 'color'],
        '--surface-3' => ['Выделенная плашка', 'color'],
        '--panel' => ['Секция на подложке', 'color'],
        '--hover-bg' => ['Фон при наведении', 'color'],
        '--hover-bg-2' => ['Фон при наведении, второй', 'color'],
        '--text-2' => ['Подзаголовки и вводные абзацы', 'color'],
        '--text-3' => ['Подписи в карточках', 'color'],
        '--text-4' => ['Примечания', 'color'],
        '--nav' => ['Пункты меню', 'color'],
        '--text-5' => ['Текст на плашках', 'color'],
        '--text-6' => ['Текст на плашках, тише', 'color'],
        '--text-7' => ['Служебные подписи', 'color'],
        '--line' => ['Рамки карточек', 'color'],
        '--line-2' => ['Разделители', 'color'],
        '--line-3' => ['Рамки полей', 'color'],
        '--line-4' => ['Контурные кнопки', 'color'],
        '--line-5' => ['Самые заметные линии', 'color'],
        '--header-bg' => ['Фон шапки', 'alpha'],
        '--bar-bg' => ['Фон панели внизу', 'alpha'],
        '--overlay-card' => ['Карточка поверх фото', 'alpha'],
        '--hero-scrim' => ['Затемнение обложки', 'alpha'],
        '--hero-overlay' => ['Градиент обложки', 'text'],
        '--photo-op' => ['Прозрачность фотографий', 'text'],
    ];

    public function __construct(private Db $db, private ?Settings $settings = null)
    {
    }

    public function isReady(): bool
    {
        return $this->db->isAvailable() && $this->db->tableExists('themes');
    }

    /** Темы из файла — набор «из коробки». @return array<string,array> */
    public static function builtin(): array
    {
        return (array) require APP_DIR . '/themes.php';
    }

    /**
     * Все темы для сайта: ключ => ['name' =>, 'swatch' =>, 'vars' =>].
     *
     * @return array<string,array>
     */
    public function all(): array
    {
        if (!$this->isReady()) {
            return self::builtin();
        }

        $rows = $this->db->all('SELECT * FROM themes ORDER BY sort, id');

        if ($rows === []) {
            return self::builtin();
        }

        $out = [];

        foreach ($rows as $row) {
            $vars = json_decode((string) $row['vars_json'], true);

            $out[(string) $row['theme_key']] = [
                'name'   => (string) $row['name'],
                'swatch' => [(string) $row['swatch_bg'], (string) $row['swatch_accent']],
                'vars'   => is_array($vars) ? $vars : [],
            ];
        }

        return $out;
    }

    /** @return list<array> строки таблицы — для списка в админке */
    public function rows(): array
    {
        return $this->isReady() ? $this->db->all('SELECT * FROM themes ORDER BY sort, id') : [];
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM themes WHERE id = :id', ['id' => $id]);
    }

    public function findByKey(string $key): ?array
    {
        return $this->db->first('SELECT * FROM themes WHERE theme_key = :key', ['key' => $key]);
    }

    /** Тема по умолчанию: сначала выбранная в админке, затем из конфига. */
    public function defaultKey(string $fallback): string
    {
        $key = (string) ($this->settings?->get('default_theme', '') ?? '');

        return $key !== '' && $this->exists($key) ? $key : $fallback;
    }

    public function setDefault(string $key): void
    {
        $this->settings?->set('default_theme', $key);
    }

    public function exists(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /* ------------------------------------------------------------ правка */

    /** @param array<string,string> $vars */
    public function save(int $id, string $name, array $vars, array $swatch): void
    {
        $this->db->update('themes', [
            'name'          => mb_substr($name, 0, 191),
            'swatch_bg'     => (string) ($swatch[0] ?? ''),
            'swatch_accent' => (string) ($swatch[1] ?? ''),
            'vars_json'     => json_encode($vars, JSON_UNESCAPED_UNICODE),
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /**
     * Новая тема — всегда копия существующей: полсотни переменных подряд
     * никто не заполнит, а копию достаточно подправить.
     */
    public function duplicate(string $sourceKey, string $name): ?int
    {
        $themes = $this->all();
        $source = $themes[$sourceKey] ?? null;

        if ($source === null) {
            return null;
        }

        $key = $this->uniqueKey($name);

        return $this->db->insert('themes', [
            'theme_key'     => $key,
            'name'          => mb_substr($name, 0, 191),
            'swatch_bg'     => (string) ($source['swatch'][0] ?? ''),
            'swatch_accent' => (string) ($source['swatch'][1] ?? ''),
            'vars_json'     => json_encode($source['vars'], JSON_UNESCAPED_UNICODE),
            'sort'          => (int) $this->db->value('SELECT COALESCE(MAX(sort), 0) + 1 FROM themes', [], 1),
            'is_builtin'    => 0,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /** Темы «из коробки» не удаляем: на них ссылается конфиг и вёрстка. */
    public function delete(int $id): bool
    {
        $theme = $this->find($id);

        if ($theme === null || $theme['is_builtin']) {
            return false;
        }

        $this->db->delete('themes', 'id = :id', ['id' => $id]);

        // Удалили тему, выбранную по умолчанию — возвращаемся к первой доступной
        if ((string) ($this->settings?->get('default_theme', '') ?? '') === $theme['theme_key']) {
            $this->settings?->set('default_theme', (string) array_key_first($this->all()));
        }

        return true;
    }

    /** Переносит темы из файла в базу. Уже заведённые не трогает. */
    public function importBuiltin(): int
    {
        if (!$this->isReady()) {
            return 0;
        }

        $written = 0;
        $sort = 0;

        foreach (self::builtin() as $key => $theme) {
            $sort++;

            if ($this->findByKey((string) $key) !== null) {
                continue;
            }

            $this->db->insert('themes', [
                'theme_key'     => (string) $key,
                'name'          => (string) $theme['name'],
                'swatch_bg'     => (string) ($theme['swatch'][0] ?? ''),
                'swatch_accent' => (string) ($theme['swatch'][1] ?? ''),
                'vars_json'     => json_encode($theme['vars'], JSON_UNESCAPED_UNICODE),
                'sort'          => $sort,
                'is_builtin'    => 1,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $written++;
        }

        return $written;
    }

    /** Ключ темы из названия: латиницей, без совпадений с существующими. */
    private function uniqueKey(string $name): string
    {
        $base = Slug::make($name, false);

        if ($base === '') {
            $base = 'theme';
        }

        $key = $base;
        $n = 1;

        while ($this->findByKey($key) !== null) {
            $key = $base . '-' . (++$n);
        }

        return $key;
    }
}
