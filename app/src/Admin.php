<?php
declare(strict_types=1);

require_once APP_DIR . '/src/AdminContext.php';
require_once APP_DIR . '/src/AdminSection.php';

foreach ([
    'AdminAccess', 'AdminPages', 'AdminInline', 'AdminMenu', 'AdminMedia',
    'AdminLeads', 'AdminUsers', 'AdminThemes', 'AdminSeo', 'AdminSystem',
] as $section) {
    require_once APP_DIR . '/src/' . $section . '.php';
}

/**
 * Админка: кто допущен и какому разделу передать запрос.
 *
 * Сами действия живут в разделах (AdminPages, AdminMenu, AdminLeads…),
 * а здесь — общий для всех порядок допуска: политика безопасности, сессия,
 * проверка CSRF-токена, миграции, первичная установка, вход. Раздел
 * получает запрос уже после того, как всё это пройдено.
 */
final class Admin
{
    private AdminContext $app;

    /** @var array<string,AdminSection> разделы создаются по мере надобности */
    private array $sections = [];

    public function __construct(array $services)
    {
        $this->app = new AdminContext($services);
    }

    /* --------------------------------- то, чем пользуются шаблоны админки */

    public function section(): string
    {
        return $this->app->section();
    }

    public function nonce(): string
    {
        return $this->app->nonce();
    }

    public function url(string $path = ''): string
    {
        return $this->app->url($path);
    }

    /* ------------------------------------------------------------ маршруты */

    public function dispatch(string $requestUri): void
    {
        /*
         * Политику задаём из PHP, а не из .htaccess: в админке правится
         * контент, поэтому inline-скрипты разрешаем не все подряд, а только
         * свои — по одноразовому ключу. Чужой скрипт, вставленный в текст,
         * ключа не знает и не выполнится.
         */
        $nonce = base64_encode(random_bytes(12));
        $this->app->setNonce($nonce);

        if (!headers_sent()) {
            header(
                "Content-Security-Policy: default-src 'self'; "
                . "script-src 'self' 'nonce-" . $nonce . "'; "
                . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; "
                . "font-src 'self' https://fonts.gstatic.com; connect-src 'self'; form-action 'self'; "
                . "frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
            );
        }

        $path = trim((string) parse_url($requestUri, PHP_URL_PATH), '/');
        $path = trim(substr($path, strlen(trim($this->app->base(), '/'))), '/');

        // Первый сегмент: pages, menu, leads… — им и подсвечиваем навигацию
        $this->app->setSection(explode('/', $path)[0] ?? '');

        // Без базы админка бессмысленна — показываем, что именно не так
        if (!$this->app->db->isAvailable()) {
            $this->app->render('connection', ['configured' => $this->app->db->isConfigured()], 'Нет связи с базой');

            return;
        }

        // Сессию открываем до любого вывода: без неё не выдать CSRF-токен формам
        Auth::startSession();
        Csrf::verify();

        /*
         * Любая правка в админке делает кэш страниц устаревшим.
         *
         * Сбрасываем дважды. Сразу — чтобы посетитель, пришедший сразу после
         * ответа, уже не получил старую страницу. И в конце запроса — на случай,
         * если между сбросом и записью правки кто-то успел положить в кэш
         * ещё не изменённую страницу.
         */
        if ($this->app->isPost() && $this->app->cache !== null) {
            $cache = $this->app->cache;
            $cache->flush();
            register_shutdown_function(static fn () => $cache->flush());
        }

        // Схема ещё не создана или обновилась — предлагаем применить миграции
        if ($this->app->migrator->pending() !== [] && $path !== 'migrate') {
            $this->app->render('migrate', ['pending' => $this->app->migrator->pending()], 'Обновление базы');

            return;
        }

        // Применение миграций идёт раньше всех обращений к таблицам:
        // при первом запуске их ещё не существует
        if ($path === 'migrate') {
            $this->system()->migrate();

            return;
        }

        /*
         * Язык интерфейса берём у пользователя. До входа — язык сайта:
         * страницу входа тоже надо кому-то читать.
         */
        AdminLang::use($this->app->auth->user()['locale'] ?? '' ?: $this->app->site->defaultLocale());

        // Первый запуск: заводим администратора
        if (!$this->app->auth->hasUsers()) {
            $path === 'setup' ? $this->access()->setup() : $this->app->redirect('setup');

            return;
        }

        if ($path === 'login') {
            $this->access()->login();

            return;
        }

        // Восстановление пароля открыто без входа — за тем и заведено
        if ($path === 'vosstanovlenie') {
            $this->access()->passwordReset();

            return;
        }

        if (!$this->app->auth->check()) {
            $this->app->redirect('login');

            return;
        }

        $this->route($path);
    }

    /**
     * Разбор адреса для вошедшего.
     *
     * Разделы с продолжением в адресе (page/12/ru/block/5) разбирают хвост
     * сами: правила там свои, и знать их снаружи незачем.
     */
    private function route(string $path): void
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $head = $segments[0] ?? '';
        $tail = array_slice($segments, 1);

        switch ($head) {
            case 'page':
                $this->pages()->pageRoutes($tail);

                return;

            case 'media':
                $this->media()->mediaRoutes($tail);

                return;

            case 'menu':
                $this->menu()->menuRoutes($tail);

                return;

            case 'leads':
                $this->leads()->leadRoutes($tail);

                return;

            case 'users':
                $this->app->adminOnly() ? $this->users()->userRoutes($tail) : null;

                return;

            case 'themes':
                $this->app->adminOnly() ? $this->themes()->themeRoutes($tail) : null;

                return;
        }

        match ($path) {
            ''         => $this->system()->dashboard(),
            'pages'    => $this->pages()->pagesList(),
            'profile'  => $this->access()->profile(),
            'twofa'    => $this->access()->twoFactor(),
            'lang'     => $this->access()->switchLanguage(),
            'settings' => $this->system()->siteSettings(),
            'seo'      => $this->app->adminOnly() ? $this->seo()->seoSettings() : null,
            'system'   => $this->app->adminOnly() ? $this->system()->system() : null,
            'import'   => $this->app->adminOnly() ? $this->system()->import() : null,
            'backup'   => $this->app->adminOnly() ? $this->system()->backup() : null,
            'cache'    => $this->app->adminOnly() ? $this->system()->cacheFlush() : null,
            'log'      => $this->app->adminOnly() ? $this->system()->activityLog() : null,
            'inline'   => $this->inline()->inlineSave(),
            'logout'   => $this->access()->logout(),
            'setup'    => $this->app->redirect(''),
            default    => $this->app->notFound(),
        };
    }

    /* ------------------------------------------------------------- разделы */

    /**
     * Раздел создаётся при первом обращении: за запрос работает один,
     * остальным незачем даже появляться.
     *
     * @template T of AdminSection
     * @param class-string<T> $class
     * @return T
     */
    private function part(string $class): AdminSection
    {
        return $this->sections[$class] ??= new $class($this->app);
    }

    private function access(): AdminAccess
    {
        return $this->part(AdminAccess::class);
    }

    private function pages(): AdminPages
    {
        return $this->part(AdminPages::class);
    }

    private function inline(): AdminInline
    {
        return $this->part(AdminInline::class);
    }

    private function menu(): AdminMenu
    {
        return $this->part(AdminMenu::class);
    }

    private function media(): AdminMedia
    {
        return $this->part(AdminMedia::class);
    }

    private function leads(): AdminLeads
    {
        return $this->part(AdminLeads::class);
    }

    private function users(): AdminUsers
    {
        return $this->part(AdminUsers::class);
    }

    private function themes(): AdminThemes
    {
        return $this->part(AdminThemes::class);
    }

    private function seo(): AdminSeo
    {
        return $this->part(AdminSeo::class);
    }

    private function system(): AdminSystem
    {
        return $this->part(AdminSystem::class);
    }
}
