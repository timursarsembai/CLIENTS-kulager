<?php
declare(strict_types=1);

/**
 * Настройки сайта, которые редактор меняет сам: контакты, адреса, режим работы.
 *
 * Значения по умолчанию берутся из app/config.php — база лишь переопределяет
 * их. Поэтому сайт продолжает работать и до переноса настроек, и при
 * недоступной базе.
 */
final class Settings
{
    /** Что можно править через админку: ключ => подпись и подсказка */
    public const FIELDS = [
        'company'    => ['label' => 'Название организации', 'group' => 'contacts'],
        'phone'      => ['label' => 'Телефон', 'hint' => 'Как показывать людям: +7 701 441 4848', 'group' => 'contacts'],
        'phone_href' => ['label' => 'Телефон для ссылки', 'hint' => 'Только цифры: 77014414848', 'group' => 'contacts'],
        'whatsapp'   => ['label' => 'Номер WhatsApp', 'hint' => 'Только цифры, без плюса', 'group' => 'contacts'],
        'email'      => ['label' => 'Почта', 'group' => 'contacts'],
        'schedule'   => ['label' => 'Режим работы', 'group' => 'contacts'],
        'office'     => ['label' => 'Адрес офиса', 'group' => 'contacts'],
        'production' => ['label' => 'Адрес производства', 'group' => 'contacts'],

        'logo' => [
            'label' => 'Логотип',
            'hint'  => 'Одноцветный силуэт на прозрачном фоне: он перекрашивается под тему. '
                . 'Подойдёт PNG или SVG, примерно 153×26.',
            'group' => 'contacts',
            'type'  => 'image',
        ],

        /* --------------------------------------------------------------- SEO */

        'seo_site_name' => [
            'label' => 'Название сайта',
            'hint'  => 'Показывается в соцсетях рядом с заголовком страницы (og:site_name).',
            'group' => 'seo',
        ],
        'seo_title_suffix' => [
            'label' => 'Приписка к заголовку',
            'hint'  => 'Добавляется в конец заголовка вкладки, если его там ещё нет: « — KULAGER».',
            'group' => 'seo',
        ],
        'seo_description' => [
            'label' => 'Описание по умолчанию',
            'hint'  => 'Берётся, когда у страницы своё описание не заполнено.',
            'group' => 'seo',
            'type'  => 'text',
        ],
        'seo_og_image' => [
            'label' => 'Картинка для соцсетей по умолчанию',
            'hint'  => 'Подставляется страницам без своей картинки. 1200×630.',
            'group' => 'seo',
            'type'  => 'image',
        ],
        'seo_robots_extra' => [
            'label' => 'Дополнительные строки robots.txt',
            'hint'  => 'Пишутся после стандартных правил, по строке на правило.',
            'group' => 'seo',
            'type'  => 'text',
        ],
        'seo_noindex' => [
            'label' => 'Закрыть весь сайт от индексации',
            'hint'  => 'На время доработки: во все страницы добавится noindex, а robots.txt запретит обход.',
            'group' => 'seo',
            'type'  => 'bool',
        ],
        'seo_verify_yandex' => [
            'label' => 'Код подтверждения Яндекса',
            'hint'  => 'Только значение content из тега yandex-verification.',
            'group' => 'seo',
        ],
        'seo_verify_google' => [
            'label' => 'Код подтверждения Google',
            'hint'  => 'Только значение content из тега google-site-verification.',
            'group' => 'seo',
        ],

        /* ------------------------------------------------------- уведомления */

        'telegram_token' => [
            'label' => 'Токен телеграм-бота',
            'hint'  => 'Выдаёт @BotFather при создании бота. Хранится только в базе.',
            'group' => 'notify',
            'secret' => true,
        ],
        'telegram_chat' => [
            'label' => 'Куда слать заявки',
            'hint'  => 'Идентификатор чата или канала. Проще всего определить кнопкой ниже.',
            'group' => 'notify',
        ],

        /* --------------------------------------------------------- счётчики */

        'counter_metrika' => [
            'label' => 'Номер Яндекс.Метрики',
            'hint'  => 'Только номер: 12345678. Код счётчика соберём сами.',
            'group' => 'counters',
        ],
        'counter_ga' => [
            'label' => 'Идентификатор Google Analytics',
            'hint'  => 'Вида G-XXXXXXXXXX. Код тоже соберём сами.',
            'group' => 'counters',
        ],
        'counter_head' => [
            'label' => 'Свой код в <head>',
            'hint'  => 'Для счётчиков, которых нет выше. Вставьте код целиком, как дал сервис.',
            'group' => 'counters',
            'type'  => 'text',
        ],
        'counter_body' => [
            'label' => 'Свой код перед </body>',
            'hint'  => 'Сюда обычно просят класть код чатов и пикселей.',
            'group' => 'counters',
            'type'  => 'text',
        ],
        'counter_hosts' => [
            'label' => 'Разрешить домены',
            'hint'  => 'Через пробел, если свой счётчик грузится со стороннего домена: '
                . 'example.com cdn.example.com. Для Метрики и Google это не нужно.',
            'group' => 'counters',
        ],
    ];

    /** Поля одной группы: контакты и SEO редактируются в разных разделах. */
    public static function group(string $group): array
    {
        return array_filter(
            self::FIELDS,
            static fn (array $field): bool => ($field['group'] ?? 'contacts') === $group
        );
    }

    private Db $db;
    private array $defaults;
    private ?array $values = null;

    public function __construct(Db $db, array $defaults)
    {
        $this->db = $db;
        $this->defaults = $defaults;
    }

    /** @return array<string,string> текущие значения с учётом значений по умолчанию */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = $this->defaults;

        try {
            if ($this->db->isConfigured() && $this->db->tableExists('settings')) {
                foreach ($this->db->all("SELECT setting_key, value_json FROM settings WHERE locale = ''") as $row) {
                    $decoded = json_decode((string) $row['value_json'], true);

                    if (is_string($decoded) && $decoded !== '') {
                        $values[$row['setting_key']] = $decoded;
                    }
                }
            }
        } catch (Throwable $e) {
            // Настройки — не повод ломать страницу: остаёмся на значениях из конфига
        }

        return $this->values = $values;
    }

    public function get(string $key, string $default = ''): string
    {
        return (string) ($this->all()[$key] ?? $default);
    }

    /**
     * Служебное значение — не из формы настроек, а из работы CMS
     * (например версия кэша). Проверку по списку полей не проходит.
     */
    public function set(string $key, string $value): void
    {
        if (!$this->db->isConfigured() || !$this->db->tableExists('settings')) {
            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        $exists = (int) $this->db->value(
            "SELECT COUNT(*) FROM settings WHERE setting_key = :key AND locale = ''",
            ['key' => $key],
            0
        );

        if ($exists > 0) {
            $this->db->update('settings', ['value_json' => $encoded], "setting_key = :key AND locale = ''", ['key' => $key]);
        } else {
            $this->db->insert('settings', ['setting_key' => $key, 'locale' => '', 'value_json' => $encoded]);
        }

        // Значения уже прочитаны в этом запросе — обновляем и их
        if ($this->values !== null) {
            $this->values[$key] = $value;
        }
    }

    /* --------------------------------------------------- строки интерфейса */

    /** @var array<string,array<string,string>> кэш строк по языкам */
    private array $texts = [];

    /**
     * Переопределение строки интерфейса, если её правили в админке.
     * Строки живут в тех же настройках, но со своим языком.
     */
    public function text(string $key, string $locale): ?string
    {
        if (!isset($this->texts[$locale])) {
            $this->texts[$locale] = [];

            try {
                if ($this->db->isConfigured() && $this->db->tableExists('settings')) {
                    $rows = $this->db->all(
                        'SELECT setting_key, value_json FROM settings WHERE locale = :locale',
                        ['locale' => $locale]
                    );

                    foreach ($rows as $row) {
                        $decoded = json_decode((string) $row['value_json'], true);

                        if (is_string($decoded)) {
                            $this->texts[$locale][(string) $row['setting_key']] = $decoded;
                        }
                    }
                }
            } catch (Throwable $e) {
                // Строки интерфейса — не повод ломать страницу
            }
        }

        return $this->texts[$locale][$key] ?? null;
    }

    /** Записывает правку строки интерфейса для одного языка. */
    public function setText(string $key, string $locale, string $value): void
    {
        if (!$this->db->isConfigured() || !$this->db->tableExists('settings')) {
            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        $exists = (int) $this->db->value(
            'SELECT COUNT(*) FROM settings WHERE setting_key = :key AND locale = :locale',
            ['key' => $key, 'locale' => $locale],
            0
        );

        if ($exists > 0) {
            $this->db->update(
                'settings',
                ['value_json' => $encoded],
                'setting_key = :key AND locale = :locale',
                ['key' => $key, 'locale' => $locale]
            );
        } else {
            $this->db->insert('settings', ['setting_key' => $key, 'locale' => $locale, 'value_json' => $encoded]);
        }

        unset($this->texts[$locale]);
    }

    /** @param array<string,string> $values */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!isset(self::FIELDS[$key])) {
                continue;
            }

            $value = trim($value);
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

            // Пустое значение означает «вернуться к тому, что в конфиге»
            if ($value === '') {
                $this->db->delete('settings', "setting_key = :key AND locale = ''", ['key' => $key]);
                continue;
            }

            $exists = $this->db->value(
                "SELECT COUNT(*) FROM settings WHERE setting_key = :key AND locale = ''",
                ['key' => $key],
                0
            );

            if ((int) $exists > 0) {
                $this->db->update('settings', ['value_json' => $encoded], "setting_key = :key AND locale = ''", ['key' => $key]);
                continue;
            }

            $this->db->insert('settings', [
                'setting_key' => $key,
                'locale'      => '',
                'value_json'  => $encoded,
            ]);
        }

        $this->values = null;
    }
}
