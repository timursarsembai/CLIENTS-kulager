<?php
declare(strict_types=1);

/**
 * Общее состояние сайта: конфигурация, текущий язык, ссылки, контакты.
 * Шаблоны обращаются только сюда, а не к глобальным переменным.
 */
final class Site
{
    private array $config;
    private string $locale;
    private string $baseUrl;
    /** slug текущей страницы без языкового префикса */
    private string $path = '';
    /** @var array<string,string> slug текущей страницы для каждого языка */
    private array $alternates = [];

    private ?Settings $settings;
    private ?NavigationRepository $navigation = null;
    private ?ThemeRepository $themes = null;
    private ?Counters $counters = null;
    private bool $editMode = false;
    private int $editPageId = 0;
    private bool $adminSession = false;

    public function __construct(
        array $config,
        ?Settings $settings = null,
        ?NavigationRepository $navigation = null,
        ?ThemeRepository $themes = null,
        ?Counters $counters = null
    )
    {
        $this->config = $config;
        $this->settings = $settings;
        $this->navigation = $navigation;
        $this->themes = $themes;
        $this->counters = $counters;
        $this->locale = $this->defaultLocale();
        $this->baseUrl = $config['base_url'] !== '' ? rtrim($config['base_url'], '/') : $this->detectBaseUrl();
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /* ---------------------------------------------------------- языки */

    public function defaultLocale(): string
    {
        return (string) array_key_first($this->config['locales']);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        if (isset($this->config['locales'][$locale])) {
            $this->locale = $locale;
        }
    }

    public function isDefaultLocale(): bool
    {
        return $this->locale === $this->defaultLocale();
    }

    /** @return array<string,array{name:string,short:string,html:string,og:string}> */
    public function locales(): array
    {
        return $this->config['locales'];
    }

    public function localeMeta(?string $locale = null): array
    {
        return $this->config['locales'][$locale ?? $this->locale];
    }

    /* ---------------------------------------------------------- ссылки */

    /** Абсолютный путь от корня сайта с языковым префиксом: url('otrasli') → /otrasli или /kk/otrasli */
    public function url(string $path = '', ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $path = trim($path, '/');
        $prefix = $locale === $this->defaultLocale() ? '' : '/' . $locale;

        return ($prefix . '/' . $path) === '/' ? '/' : rtrim($prefix . '/' . $path, '/');
    }

    /** Абсолютный URL — для canonical, og:url и микроразметки. */
    public function absoluteUrl(string $path = '', ?string $locale = null): string
    {
        return $this->baseUrl . $this->url($path, $locale);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Путь к статике с меткой версии, чтобы не ловить старый кэш после правок. */
    public function asset(string $path): string
    {
        $path = '/assets/' . ltrim($path, '/');
        $file = (defined('PUBLIC_DIR') ? PUBLIC_DIR : dirname(APP_DIR) . '/public_html') . $path;
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return $path . '?v=' . $version;
    }

    /** Абсолютный адрес файла статики — для og:image и микроразметки. */
    public function assetUrl(string $path): string
    {
        return $this->baseUrl . $this->asset($path);
    }

    public function setPath(string $path): void
    {
        $this->path = trim($path, '/');
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Канонический адрес текущей страницы. */
    public function canonical(): string
    {
        return $this->absoluteUrl($this->path);
    }

    /** @param array<string,string> $map язык => slug */
    public function setAlternates(array $map): void
    {
        $this->alternates = $map;
    }

    /** @return array<string,string> язык => абсолютный URL */
    public function alternates(): array
    {
        $out = [];
        foreach ($this->alternates as $locale => $slug) {
            $out[$locale] = $this->absoluteUrl($slug, $locale);
        }

        return $out;
    }

    /* ------------------------------------------------- строки интерфейса */

    /** @var array<string,array<string,string>> кэш словарей по языкам */
    private array $langCache = [];

    /**
     * Строка интерфейса: шапка, подвал, меню — то, чего нет в контенте страниц.
     * Если перевода нет, возвращается строка основного языка, а не пустота.
     */
    public function t(string $key): string
    {
        // Правка через админку перекрывает файл: строки интерфейса
        // редактируются прямо на странице, а файлы остаются образцом
        $override = $this->settings?->text($key, $this->locale);

        if ($override !== null && $override !== '') {
            return $override;
        }

        $strings = $this->lang($this->locale);

        if (isset($strings[$key])) {
            return $strings[$key];
        }

        return $this->lang($this->defaultLocale())[$key] ?? $key;
    }

    /** @return array<string,string> */
    private function lang(string $locale): array
    {
        if (isset($this->langCache[$locale])) {
            return $this->langCache[$locale];
        }

        $file = APP_DIR . '/lang/' . $locale . '.php';

        return $this->langCache[$locale] = is_file($file) ? (array) require $file : [];
    }

    /* --------------------------------------------------------- счётчики */

    public function countersHead(): string
    {
        return $this->counters?->head() ?? '';
    }

    public function countersBody(): string
    {
        return $this->counters?->body() ?? '';
    }

    /* -------------------------------------------------------------- SEO */

    /** Название сайта для соцсетей. */
    public function siteName(): string
    {
        return $this->setting('seo_site_name', 'KULAGER');
    }

    /**
     * Заголовок вкладки: к заголовку страницы добавляется приписка,
     * если её там ещё нет — дважды название сайта никому не нужно.
     */
    public function pageTitle(string $title): string
    {
        $suffix = trim($this->setting('seo_title_suffix', ''));

        if ($title === '') {
            return $suffix !== '' ? trim($suffix, " -—|") : $this->siteName();
        }

        if ($suffix === '' || mb_stripos($title, trim($suffix, " -—|")) !== false) {
            return $title;
        }

        return $title . ' ' . $suffix;
    }

    /** Описание страницы или общее, если у страницы своего нет. */
    public function pageDescription(string $description): string
    {
        return $description !== '' ? $description : $this->setting('seo_description', '');
    }

    /** Картинка для соцсетей: своя у страницы или общая. */
    public function pageImage(string $image): string
    {
        return $image !== '' ? $image : $this->setting('seo_og_image', '');
    }

    /** Закрыт ли от индексации весь сайт — на время доработки. */
    public function siteNoindex(): bool
    {
        return $this->setting('seo_noindex', '') !== '';
    }

    /** Дополнительные строки robots.txt из настроек. */
    public function robotsExtra(): string
    {
        return $this->setting('seo_robots_extra', '');
    }

    /** Коды подтверждения прав в панелях вебмастеров. @return array<string,string> */
    public function verifications(): array
    {
        return array_filter([
            'yandex-verification'      => $this->setting('seo_verify_yandex', ''),
            'google-site-verification' => $this->setting('seo_verify_google', ''),
        ]);
    }

    private function setting(string $key, string $default): string
    {
        return $this->settings?->get($key, $default) ?? $default;
    }

    /* ------------------------------------------------------------ заявки */

    /**
     * Подпись формы заявки. Живёт в Leads, но шаблону нужен доступ —
     * а тащить в него ещё одну зависимость незачем.
     */
    public function formSignature(): string
    {
        return Leads::signatureFor($this->formSecret());
    }

    private function formSecret(): string
    {
        $secret = $this->settings?->get('form_secret', '') ?? '';

        if ($secret === '' && $this->settings !== null) {
            $secret = bin2hex(random_bytes(16));
            $this->settings->set('form_secret', $secret);
        }

        return $secret;
    }

    /* ------------------------------------------------- правка на странице */

    /**
     * Режим правки: редактор открыл сайт с ?edit=1 и вошёл в админку.
     * В этом режиме шаблоны помечают текстовые узлы, а скрипт делает их
     * редактируемыми. Гость такой страницы никогда не увидит.
     */
    public function setEditMode(bool $on): void
    {
        $this->editMode = $on;
    }

    public function editMode(): bool
    {
        return $this->editMode;
    }

    /**
     * Вошёл ли посетитель в админку. Отличается от режима правки: панель
     * администратора видна всегда, а правка включается по кнопке.
     */
    public function setAdminSession(bool $on): void
    {
        $this->adminSession = $on;
    }

    public function adminSession(): bool
    {
        return $this->adminSession;
    }

    /** Какую страницу правим: нужно полям, которые живут не в блоках. */
    public function setEditPageId(int $id): void
    {
        $this->editPageId = $id;
    }

    public function editPageId(): int
    {
        return $this->editPageId;
    }

    /* ------------------------------------------------------------- темы */

    /** Цветовые темы: из базы, если правились в админке, иначе из файла. */
    public function themes(): array
    {
        return $this->themes?->all() ?? (array) require APP_DIR . '/themes.php';
    }

    /** Тема, с которой открывается сайт. */
    public function defaultTheme(): string
    {
        $fallback = (string) $this->config('default_theme', 'dark');

        return $this->themes?->defaultKey($fallback) ?? $fallback;
    }

    /* -------------------------------------------------------- навигация */

    /** @var array<string,array> кэш навигации по языкам */
    private array $navCache = [];

    /**
     * Навигация текущего языка. Если перевода ещё нет — берём основной язык,
     * чтобы страница не осталась без меню.
     *
     * @return array<string,mixed>
     */
    public function nav(?string $section = null, string $area = 'drawer'): array
    {
        $key = $this->locale . '|' . $area;

        if (!isset($this->navCache[$key])) {
            // Меню правится в админке; файлы остаются запасным вариантом,
            // пока перенос в базу не сделан
            $data = $this->navigation?->tree($this->locale, $area) ?? [];

            if ($data === []) {
                $data = $this->navigation?->tree($this->defaultLocale(), $area) ?? [];
            }

            if ($data === []) {
                $candidates = [
                    APP_DIR . '/content/navigation.' . $this->locale . '.php',
                    APP_DIR . '/content/navigation.' . $this->defaultLocale() . '.php',
                ];

                foreach ($candidates as $file) {
                    if (is_file($file)) {
                        $data = (array) require $file;
                        break;
                    }
                }
            }

            $this->navCache[$key] = $data;
        }

        $nav = $this->navCache[$key];

        return $section === null ? $nav : (array) ($nav[$section] ?? []);
    }

    /* ---------------------------------------------------------- контакты */

    /** Контакты: сначала то, что задано в админке, затем значения из конфига. */
    public function contact(string $key): string
    {
        if ($this->settings !== null) {
            return $this->settings->get($key, (string) ($this->config['contacts'][$key] ?? ''));
        }

        return (string) ($this->config['contacts'][$key] ?? '');
    }

    /** Ссылка на WhatsApp с заранее подставленным текстом обращения. */
    public function whatsapp(string $message = ''): string
    {
        $url = 'https://wa.me/' . $this->contact('whatsapp');

        return $message === '' ? $url : $url . '?text=' . rawurlencode($message);
    }

    public function phoneHref(): string
    {
        return 'tel:' . $this->contact('phone_href');
    }

    public function mailHref(): string
    {
        return 'mailto:' . $this->contact('email');
    }

    /* ---------------------------------------------------------- прочее */

    private function detectBaseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
        $scheme = $https || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
