<?php
declare(strict_types=1);

/**
 * Админка: маршрутизация и действия.
 *
 * Порядок допуска: первичная установка (пока нет пользователей) → вход →
 * всё остальное. Изменяющие запросы всегда проверяются на CSRF-токен.
 */
final class Admin
{
    private array $config;
    private Db $db;
    private Site $site;
    private PageRepository $pages;
    private Auth $auth;
    private Migrator $migrator;
    private ?PageCache $cache;
    private string $base;

    public function __construct(array $services)
    {
        $this->config = $services['config'];
        $this->db = $services['db'];
        $this->site = $services['site'];
        $this->pages = $services['pages'];
        $this->auth = new Auth($this->db, $this->config);
        $this->cache = $services['cache'] ?? null;
        $this->migrator = new Migrator($this->db);
        $this->base = '/' . trim((string) ($this->config['admin_path'] ?? 'admin'), '/');
    }

    /** Одноразовый ключ для inline-скриптов админки. */
    private string $nonce = '';

    /** Текущий раздел — по нему подсвечивается пункт в навигации. */
    private string $section = '';

    public function section(): string
    {
        return $this->section;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function dispatch(string $requestUri): void
    {
        /*
         * Политику задаём из PHP, а не из .htaccess: в админке правится
         * контент, поэтому inline-скрипты разрешаем не все подряд, а только
         * свои — по одноразовому ключу. Чужой скрипт, вставленный в текст,
         * ключа не знает и не выполнится.
         */
        $this->nonce = base64_encode(random_bytes(12));

        if (!headers_sent()) {
            header(
                "Content-Security-Policy: default-src 'self'; "
                . "script-src 'self' 'nonce-" . $this->nonce . "'; "
                . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; "
                . "font-src 'self' https://fonts.gstatic.com; connect-src 'self'; form-action 'self'; "
                . "frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
            );
        }

        $path = trim((string) parse_url($requestUri, PHP_URL_PATH), '/');
        $path = trim(substr($path, strlen(trim($this->base, '/'))), '/');

        // Первый сегмент: pages, menu, leads… — им и подсвечиваем навигацию
        $this->section = explode('/', $path)[0] ?? '';

        // Без базы админка бессмысленна — показываем, что именно не так
        if (!$this->db->isAvailable()) {
            $this->render('connection', ['configured' => $this->db->isConfigured()], 'Нет связи с базой');

            return;
        }

        // Сессию открываем до любого вывода: без неё не выдать CSRF-токен формам
        $this->auth->startSession();
        Csrf::verify();

        /*
         * Любая правка в админке делает кэш страниц устаревшим.
         *
         * Сбрасываем дважды. Сразу — чтобы посетитель, пришедший сразу после
         * ответа, уже не получил старую страницу. И в конце запроса — на случай,
         * если между сбросом и записью правки кто-то успел положить в кэш
         * ещё не изменённую страницу.
         */
        if ($this->isPost() && $this->cache !== null) {
            $cache = $this->cache;
            $cache->flush();
            register_shutdown_function(static fn () => $cache->flush());
        }

        // Схема ещё не создана или обновилась — предлагаем применить миграции
        if ($this->migrator->pending() !== [] && $path !== 'migrate') {
            $this->render('migrate', ['pending' => $this->migrator->pending()], 'Обновление базы');

            return;
        }

        // Применение миграций идёт раньше всех обращений к таблицам:
        // при первом запуске их ещё не существует
        if ($path === 'migrate') {
            $this->migrate();

            return;
        }

        /*
         * Язык интерфейса берём у пользователя. До входа — язык сайта:
         * страницу входа тоже надо кому-то читать.
         */
        AdminLang::use($this->auth->user()['locale'] ?? '' ?: $this->site->defaultLocale());

        // Первый запуск: заводим администратора
        if (!$this->auth->hasUsers()) {
            $path === 'setup' ? $this->setup() : $this->redirect('setup');

            return;
        }

        if ($path === 'login') {
            $this->login();

            return;
        }

        if (!$this->auth->check()) {
            $this->redirect('login');

            return;
        }

        $segments = $path === '' ? [] : explode('/', $path);

        // Редактор страницы: page/{id}/{locale}/...
        if (($segments[0] ?? '') === 'page') {
            $this->pageRoutes(array_slice($segments, 1));

            return;
        }

        // Медиатека: media, media/upload, media/{id}/delete
        if (($segments[0] ?? '') === 'media') {
            $this->mediaRoutes(array_slice($segments, 1));

            return;
        }

        // Редактор меню: menu, menu/{язык}/{меню}/...
        if (($segments[0] ?? '') === 'menu') {
            $this->menuRoutes(array_slice($segments, 1));

            return;
        }

        // Заявки: leads, leads/{id}/{действие}, leads/telegram
        if (($segments[0] ?? '') === 'leads') {
            $this->leadRoutes(array_slice($segments, 1));

            return;
        }

        // Пользователи: users, users/{id}/{действие}
        if (($segments[0] ?? '') === 'users') {
            $this->adminOnly() ? $this->userRoutes(array_slice($segments, 1)) : null;

            return;
        }

        // Оформление: themes, themes/{id}, themes/add, themes/{id}/delete
        if (($segments[0] ?? '') === 'themes') {
            $this->adminOnly() ? $this->themeRoutes(array_slice($segments, 1)) : null;

            return;
        }

        match ($path) {
            ''         => $this->dashboard(),
            'pages'    => $this->pagesList(),
            'profile'  => $this->profile(),
            'twofa'    => $this->twoFactor(),
            'lang'     => $this->switchLanguage(),
            'settings' => $this->siteSettings(),
            'seo'      => $this->adminOnly() ? $this->seoSettings() : null,
            'system'   => $this->adminOnly() ? $this->system() : null,
            'import'   => $this->adminOnly() ? $this->import() : null,
            'backup'   => $this->adminOnly() ? $this->backup() : null,
            'cache'    => $this->adminOnly() ? $this->cacheFlush() : null,
            'log'      => $this->adminOnly() ? $this->activityLog() : null,
            'inline'   => $this->inlineSave(),
            'logout'   => $this->logout(),
            'setup'    => $this->redirect(''),
            default    => $this->notFound(),
        };
    }

    /**
     * Правка текста прямо на странице сайта.
     *
     * Принимает идентификатор блока, путь к полю (`items.0.title`) и новое
     * значение. Значение не записывается как есть: данные блока собираются
     * заново и проходят через ту же проверку, что и форма в админке, —
     * правил на два входа не бывает.
     */
    private function inlineSave(): void
    {
        if (!$this->isPost()) {
            $this->json(['error' => 'Ожидался POST.'], 405);

            return;
        }

        $path = trim((string) ($_POST['path'] ?? ''));
        $value = (string) ($_POST['value'] ?? '');

        // Правка на странице приходит из четырёх мест сразу: блок, пункт
        // меню, строка интерфейса и поле самой страницы
        if (!empty($_POST['nav'])) {
            $this->inlineSaveNav((int) $_POST['nav'], $path, $value);

            return;
        }

        if (!empty($_POST['text'])) {
            $this->inlineSaveText((string) $_POST['text'], $path, $value);

            return;
        }

        if (!empty($_POST['setting'])) {
            $this->inlineSaveSetting((string) $_POST['setting'], $value);

            return;
        }

        if (!empty($_POST['page'])) {
            $this->inlineSavePage((int) $_POST['page'], $path, $value);

            return;
        }

        $block = $this->pages->findBlock((int) ($_POST['block'] ?? 0));

        if ($block === null || $path === '') {
            $this->json(['error' => 'Блок не найден.'], 404);

            return;
        }

        $data = $block['data'];

        if (!self::setByPath($data, explode('.', $path), $value)) {
            $this->json(['error' => 'Такого поля в блоке нет.'], 422);

            return;
        }

        [$clean, $errors] = Blocks::sanitize((string) $block['type'], $data);

        if ($errors !== []) {
            $this->json(['error' => reset($errors)], 422);

            return;
        }

        // Снимок и здесь: правка на странице отменяется той же кнопкой
        $this->pages->snapshot(
            (int) $block['page_id'],
            (string) $block['locale'],
            $this->auth->user()['id'] ?? null,
            'правка на странице: ' . Blocks::title((string) $block['type'])
        );

        $this->pages->updateBlock((int) $block['id'], $clean);
        $this->auth->log('inline_edit', $block['type'] . '#' . $block['id'], $path);

        // Правка идёт мимо dispatch-обработчика POST, кэш сбрасываем сами
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => self::byPath($clean, explode('.', $path))]);
    }

    /** Правка пункта меню прямо на странице. */
    private function inlineSaveNav(int $id, string $field, string $value): void
    {
        $nav = new NavigationRepository($this->db);
        $item = $nav->find($id);

        if ($item === null || !in_array($field, ['title', 'full_title', 'footer_title'], true)) {
            $this->json(['error' => 'Пункт меню не найден.'], 404);

            return;
        }

        $value = trim(strip_tags($value));

        if ($value === '') {
            $this->json(['error' => 'Название не может быть пустым.'], 422);

            return;
        }

        $nav->update($id, [
            'parent_id'    => $item['parent_id'],
            'title'        => $field === 'title' ? $value : (string) $item['title'],
            'full_title'   => $field === 'full_title' ? $value : (string) $item['full_title'],
            'footer_title' => $field === 'footer_title' ? $value : (string) ($item['footer_title'] ?? ''),
            'url'          => (string) $item['url'],
            'in_drawer'    => (bool) $item['in_drawer'],
            'in_footer'    => (bool) ($item['in_footer'] ?? 1),
        ]);

        $this->auth->log('inline_edit_menu', (string) $item['menu_key'], $field);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $value]);
    }

    /** Правка строки интерфейса: подписи в подвале, шапке, боковом меню. */
    private function inlineSaveText(string $key, string $locale, string $value): void
    {
        if (!isset($this->site->locales()[$locale])) {
            $this->json(['error' => 'Неизвестный язык.'], 422);

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $settings->setText($key, $locale, Blocks::cleanHtml($value));

        $this->auth->log('inline_edit_text', $key, $locale);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $value]);
    }

    /** Правка настройки сайта: телефон, почта, режим работы. */
    private function inlineSaveSetting(string $key, string $value): void
    {
        if (!isset(Settings::FIELDS[$key])) {
            $this->json(['error' => 'Такой настройки нет.'], 422);

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $settings->save([$key => trim(strip_tags($value))]);

        $this->auth->log('inline_edit_setting', $key);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $value]);
    }

    /**
     * Правка полей самой страницы — сейчас это тексты плавающей панели внизу.
     * Они лежат в языковой версии, а не в блоках.
     */
    private function inlineSavePage(int $pageId, string $field, string $value): void
    {
        $locale = $this->site->locale();
        $row = $this->pages->locale($pageId, $locale);

        if ($row === null || !str_starts_with($field, 'bar.')) {
            $this->json(['error' => 'Такого поля у страницы нет.'], 422);

            return;
        }

        $key = substr($field, 4);

        if (!in_array($key, ['title', 'subtitle', 'label', 'message'], true)) {
            $this->json(['error' => 'Такого поля у панели нет.'], 422);

            return;
        }

        $bar = json_decode((string) ($row['bar_json'] ?? ''), true);
        $bar = is_array($bar) ? $bar : [];
        $bar[$key] = trim(strip_tags($value));

        $this->pages->snapshot($pageId, $locale, $this->auth->user()['id'] ?? null, 'правка панели внизу');

        $this->pages->saveLocale($pageId, $locale, [
            'slug'        => (string) $row['slug'],
            'title'       => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'og_image'    => (string) ($row['og_image'] ?? ''),
            'noindex'     => !empty($row['noindex']),
            'canonical'   => (string) ($row['canonical'] ?? ''),
            'bar'         => array_filter($bar, static fn (string $v): bool => $v !== ''),
        ]);

        $this->auth->log('inline_edit_bar', (string) $row['slug'], $key);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $bar[$key]]);
    }

    /**
     * Записывает значение по пути внутри данных блока.
     * Новых ключей не создаёт: править можно только то, что уже выводится.
     */
    private static function setByPath(array &$data, array $path, string $value): bool
    {
        $key = array_shift($path);

        if ($key === null || !array_key_exists($key, $data)) {
            return false;
        }

        if ($path === []) {
            if (is_array($data[$key])) {
                return false;
            }

            $data[$key] = $value;

            return true;
        }

        if (!is_array($data[$key])) {
            return false;
        }

        return self::setByPath($data[$key], $path, $value);
    }

    private static function byPath(array $data, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }

    /**
     * Разделы, меняющие устройство сайта, доступны только администратору.
     * Редактор работает с контентом, но не переносит данные и не трогает схему.
     */
    private function adminOnly(): bool
    {
        if ($this->auth->isAdmin()) {
            return true;
        }

        $this->flash('Раздел доступен только администратору.');
        $this->redirect('');

        return false;
    }

    /** Разбор адресов внутри редактора страницы. */
    private function pageRoutes(array $segments): void
    {
        $pageId = (int) ($segments[0] ?? 0);
        $locale = (string) ($segments[1] ?? $this->site->defaultLocale());

        $page = $this->pages->find($pageId);

        if ($page === null || !isset($this->site->locales()[$locale])) {
            $this->notFound();

            return;
        }

        $this->site->setLocale($locale);

        $action = $segments[2] ?? '';

        // Действия над отдельным блоком: block/{id}/{что делаем}
        if ($action === 'block') {
            $this->blockRoutes($page, $locale, array_slice($segments, 3));

            return;
        }

        match ($action) {
            ''          => $this->pageEditor($page, $locale),
            'settings'  => $this->pageSettings($page, $locale),
            'add'       => $this->blockAdd($page, $locale),
            'reorder'   => $this->blocksReorder($page, $locale),
            'publish'   => $this->pagePublish($page, $locale),
            'unpublish' => $this->pageUnpublish($page, $locale),
            'copy'      => $this->pageCopyBlocks($page, $locale),
            'undo'      => $this->pageUndo($page, $locale),
            default     => $this->notFound(),
        };
    }

    private function blockRoutes(array $page, string $locale, array $segments): void
    {
        $blockId = (int) ($segments[0] ?? 0);
        $block = $this->pages->findBlock($blockId);

        if ($block === null || (int) $block['page_id'] !== (int) $page['id'] || $block['locale'] !== $locale) {
            $this->notFound();

            return;
        }

        match ($segments[1] ?? '') {
            ''          => $this->blockForm($page, $locale, $block),
            'delete'    => $this->blockDelete($page, $locale, $block),
            'toggle'    => $this->blockToggle($page, $locale, $block),
            'duplicate' => $this->blockDuplicate($page, $locale, $block),
            default     => $this->notFound(),
        };
    }

    /* ------------------------------------------------------------ действия */

    private function setup(): void
    {
        $errors = [];

        if ($this->isPost()) {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $name = trim((string) ($_POST['name'] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Укажите корректный адрес почты.';
            }

            if (mb_strlen($password) < 10) {
                $errors['password'] = 'Пароль должен быть не короче 10 символов.';
            }

            if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
                $errors['password_confirm'] = 'Пароли не совпадают.';
            }

            if ($errors === []) {
                $id = $this->auth->createUser($email, $password, $name !== '' ? $name : 'Администратор', 'admin');
                $this->auth->login($this->db->first('SELECT * FROM users WHERE id = :id', ['id' => $id]));
                $this->auth->log('setup', $email);
                $this->redirect('');

                return;
            }
        }

        $this->render('setup', ['errors' => $errors, 'input' => $_POST], 'Первый запуск');
    }

    private function login(): void
    {
        if ($this->auth->check()) {
            $this->redirect('');

            return;
        }

        $error = null;

        // Пароль уже принят — остался одноразовый код
        if ($this->auth->awaitingCode()) {
            if ($this->isPost()) {
                $error = $this->auth->attemptCode((string) ($_POST['code'] ?? ''));

                if ($error === null) {
                    $this->redirect('');

                    return;
                }
            }

            $this->render('login-code', ['error' => $error], 'Подтверждение входа');

            return;
        }

        if ($this->isPost()) {
            $error = $this->auth->attempt(
                trim((string) ($_POST['email'] ?? '')),
                (string) ($_POST['password'] ?? '')
            );

            if ($error === null) {
                // Со включённым вторым шагом сессии ещё нет — покажем форму кода
                $this->redirect($this->auth->awaitingCode() ? 'login' : '');

                return;
            }
        }

        $this->render('login', ['error' => $error, 'email' => $_POST['email'] ?? ''], 'Вход');
    }

    private function logout(): void
    {
        $this->auth->log('logout');
        $this->auth->logout();
        $this->redirect('login');
    }

    private function migrate(): void
    {
        // При первом запуске пользователей ещё нет — тогда миграции открыты,
        // иначе схему меняет только администратор
        if ($this->auth->hasUsers() && !$this->auth->isAdmin()) {
            $this->flash('Обновление базы доступно только администратору.');
            $this->redirect('');

            return;
        }

        if ($this->isPost()) {
            $applied = $this->migrator->migrate();
            $this->flash(count($applied) . ' миграций применено.');
            $this->redirect('');

            return;
        }

        $this->render('migrate', ['pending' => $this->migrator->pending()], 'Обновление базы');
    }

    /** Контакты и адреса — то, что меняется без разработчика. */
    private function siteSettings(): void
    {
        $settings = new Settings($this->db, (array) $this->config['contacts']);

        if ($this->isPost()) {
            $values = [];

            foreach (array_keys(Settings::group('contacts')) as $key) {
                $values[$key] = (string) ($_POST[$key] ?? '');
            }

            $settings->save($values);
            $this->auth->log('settings');
            $this->flash('Настройки сохранены.');
            $this->redirect('settings');

            return;
        }

        $this->render('settings', [
            'fields'   => Settings::group('contacts'),
            'values'   => $settings->all(),
            'defaults' => (array) $this->config['contacts'],
        ], 'Настройки');
    }

    /** Профиль: пока только смена собственного пароля. */
    private function profile(): void
    {
        $errors = [];

        if ($this->isPost()) {
            $current = (string) ($_POST['current'] ?? '');
            $next = (string) ($_POST['password'] ?? '');

            if (!password_verify($current, (string) $this->auth->user()['password_hash'])) {
                $errors['current'] = 'Текущий пароль указан неверно.';
            }

            if (mb_strlen($next) < 10) {
                $errors['password'] = 'Новый пароль должен быть не короче 10 символов.';
            }

            if ($next !== (string) ($_POST['password_confirm'] ?? '')) {
                $errors['password_confirm'] = 'Пароли не совпадают.';
            }

            if ($errors === []) {
                $this->db->update(
                    'users',
                    ['password_hash' => password_hash($next, PASSWORD_DEFAULT)],
                    'id = :id',
                    ['id' => $this->auth->user()['id']]
                );

                $this->auth->log('password_change');
                $this->flash('Пароль изменён.');
                $this->redirect('profile');

                return;
            }
        }

        $user = $this->auth->user() ?? [];

        $this->render('profile', [
            'errors' => $errors,
            'secret' => (string) ($_SESSION['totp_new_secret'] ?? ''),
            'codes'  => (array) ($_SESSION['totp_new_codes'] ?? []),
        ], 'Профиль');

        // Секрет и запасные коды показываем ровно один раз
        unset($_SESSION['totp_new_secret'], $_SESSION['totp_new_codes']);
    }

    /**
     * Включение и отключение входа по одноразовому коду.
     *
     * Секрет заводится сразу, но второй шаг включается только после того,
     * как редактор введёт код из приложения: иначе легко запереть себя,
     * сохранив секрет, который никуда не переписан.
     */
    private function twoFactor(): void
    {
        if (!$this->isPost()) {
            $this->redirect('profile');

            return;
        }

        $user = $this->auth->user() ?? [];
        $id = (int) ($user['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'start') {
            $secret = Totp::secret();
            $this->db->update('users', ['totp_secret' => $secret, 'totp_enabled' => 0], 'id = :id', ['id' => $id]);

            $_SESSION['totp_new_secret'] = $secret;
            $this->flash('Добавьте ключ в приложение и введите код, чтобы включить.');
            $this->redirect('profile');

            return;
        }

        if ($action === 'enable') {
            $secret = (string) ($user['totp_secret'] ?? '');

            if ($secret === '' || !Totp::verify($secret, (string) ($_POST['code'] ?? ''))) {
                $this->flash('Код не подошёл — вход по коду не включён.');
                $this->redirect('profile');

                return;
            }

            $this->db->update('users', ['totp_enabled' => 1], 'id = :id', ['id' => $id]);
            $_SESSION['totp_new_codes'] = $this->auth->makeBackupCodes($id);

            $this->auth->log('twofa_enabled');
            $this->flash('Вход по одноразовому коду включён. Сохраните запасные коды.');
            $this->redirect('profile');

            return;
        }

        if ($action === 'disable') {
            // Отключение — тоже действие с последствиями, просим пароль
            if (!password_verify((string) ($_POST['current'] ?? ''), (string) $user['password_hash'])) {
                $this->flash('Текущий пароль указан неверно — ничего не изменилось.');
                $this->redirect('profile');

                return;
            }

            $this->db->update(
                'users',
                ['totp_enabled' => 0, 'totp_secret' => '', 'totp_backup' => null],
                'id = :id',
                ['id' => $id]
            );

            $this->auth->log('twofa_disabled');
            $this->flash('Вход по одноразовому коду отключён.');
            $this->redirect('profile');

            return;
        }

        if ($action === 'codes') {
            $_SESSION['totp_new_codes'] = $this->auth->makeBackupCodes($id);
            $this->auth->log('twofa_codes');
            $this->flash('Запасные коды перевыпущены — прежние больше не действуют.');
            $this->redirect('profile');

            return;
        }

        $this->redirect('profile');
    }

    private function dashboard(): void
    {
        $stats = [
            'pages'     => (int) $this->db->value('SELECT COUNT(*) FROM pages', [], 0),
            'published' => (int) $this->db->value('SELECT COUNT(*) FROM page_locales WHERE is_published = 1', [], 0),
            'blocks'    => (int) $this->db->value('SELECT COUNT(*) FROM page_blocks', [], 0),
            'media'     => (int) $this->db->value('SELECT COUNT(*) FROM media', [], 0),
        ];

        $this->render('dashboard', [
            'stats'  => $stats,
            'recent' => $this->db->all(
                'SELECT a.*, u.name AS user_name FROM activity_log a
                   LEFT JOIN users u ON u.id = a.user_id
                  ORDER BY a.created_at DESC LIMIT 10'
            ),
        ], 'Панель');
    }

    private function pagesList(): void
    {
        $this->render('pages', [
            'pages'   => $this->pages->listPages(),
            'locales' => $this->site->locales(),
        ], 'Страницы');
    }

    private function import(): void
    {
        if (!$this->isPost()) {
            $this->redirect('system');

            return;
        }

        require APP_DIR . '/src/ContentImporter.php';

        $importer = new ContentImporter($this->db, $this->site, $this->pages);
        $result = $importer->run();

        // Меню переносим тем же действием: иначе после переноса контента
        // редактор увидит страницы в базе, а меню — по-прежнему из файлов
        $nav = new NavigationRepository($this->db);
        $menuItems = $nav->importFromFiles(array_keys($this->site->locales()));

        $this->auth->log('import', '', json_encode($result, JSON_UNESCAPED_UNICODE));

        $this->flash(sprintf(
            'Перенесено страниц: %d, блоков: %d, пунктов меню: %d.',
            $result['pages'],
            $result['blocks'],
            $menuItems
        ));

        // Поля, которых нет в реестре блоков, — их не покажет редактор,
        // хотя на сайте они выводятся. Сигнал дополнить реестр.
        if ($result['unknown'] !== []) {
            $this->flash('Не описаны в реестре блоков: ' . implode(', ', $result['unknown']));
        }

        $this->redirect('pages');
    }

    /** Выгрузка копии: база и загруженные файлы. */
    private function backup(): void
    {
        require APP_DIR . '/src/Backup.php';

        if (!$this->isPost()) {
            $this->redirect('system');

            return;
        }

        $this->auth->log('backup');

        // Никакого вывода до файла: заголовки уже уйдут вместе с архивом
        (new Backup($this->db))->send();
        exit;
    }

    private function activityLog(): void
    {
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $perPage = 50;

        $this->render('log', [
            'rows' => $this->db->all(
                'SELECT a.*, u.name AS user_name, u.email AS user_email
                   FROM activity_log a
                   LEFT JOIN users u ON u.id = a.user_id
                  ORDER BY a.created_at DESC, a.id DESC
                  LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
            ),
            'page'  => $page,
            'total' => (int) $this->db->value('SELECT COUNT(*) FROM activity_log', [], 0),
            'perPage' => $perPage,
        ], 'Журнал действий');
    }

    private function system(): void
    {
        $uploads = PUBLIC_DIR . '/assets/uploads';

        $this->render('system', [
            'php'        => PHP_VERSION,
            'extensions' => [
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'gd'        => extension_loaded('gd'),
                'fileinfo'  => extension_loaded('fileinfo'),
                'mbstring'  => extension_loaded('mbstring'),
            ],
            'limits' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
                'memory_limit'        => ini_get('memory_limit'),
                'max_execution_time'  => ini_get('max_execution_time'),
            ],
            'writable' => [
                'app/logs'       => is_writable(APP_DIR . '/logs'),
                'app/cache'      => is_dir(APP_DIR . '/cache') ? is_writable(APP_DIR . '/cache') : null,
                'assets/uploads' => is_dir($uploads) ? is_writable($uploads) : null,
            ],
            'cachedPages' => count(glob(APP_DIR . '/cache/v*/*.html') ?: []),
            'migrations' => [
                'applied' => $this->migrator->applied(),
                'pending' => $this->migrator->pending(),
            ],
            'contentFiles' => $this->contentFilesCount(),
            'canZip'       => class_exists('ZipArchive'),
            'security'     => $this->securityOverview(),
        ], 'Состояние');
    }

    /* ------------------------------------------------------------ медиатека */

    /* -------------------------------------------------------------- заявки */

    private function leadRoutes(array $segments): void
    {
        $leads = new Leads($this->db, new Settings($this->db, (array) $this->config['contacts']));
        $head = (string) ($segments[0] ?? '');

        if ($head === '') {
            $this->leadList($leads);

            return;
        }

        // Настройка уведомлений — дело администратора
        if (in_array($head, ['telegram', 'detect', 'test'], true)) {
            if (!$this->adminOnly()) {
                return;
            }

            match ($head) {
                'telegram' => $this->leadTelegram(),
                'detect'   => $this->leadDetectChat(),
                'test'     => $this->leadTestMessage($leads),
            };

            return;
        }

        $id = (int) $head;

        match ($segments[1] ?? '') {
            'status' => $this->leadStatus($leads, $id),
            'delete' => $this->leadDelete($leads, $id),
            'resend' => $this->leadResend($leads, $id),
            default  => $this->notFound(),
        };
    }

    private function leadList(Leads $leads): void
    {
        $status = (string) ($_GET['status'] ?? '');

        $this->render('leads', [
            'leads'    => $leads->recent($status),
            'status'   => $status,
            'counts'   => [
                'new'     => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'new'", [], 0),
                'in_work' => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'in_work'", [], 0),
                'done'    => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'done'", [], 0),
                'spam'    => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'spam'", [], 0),
            ],
            'telegram' => [
                'token' => trim((string) $this->settingsValue('telegram_token')) !== '',
                'chat'  => trim((string) $this->settingsValue('telegram_chat')),
            ],
            'isAdmin' => $this->auth->isAdmin(),
        ], 'Заявки');
    }

    private function settingsValue(string $key): string
    {
        return (new Settings($this->db, (array) $this->config['contacts']))->get($key, '');
    }

    private function leadStatus(Leads $leads, int $id): void
    {
        if ($this->isPost()) {
            $leads->setStatus($id, (string) ($_POST['status'] ?? ''));
            $this->auth->log('lead_status', (string) $id, (string) ($_POST['status'] ?? ''));
        }

        $this->redirect('leads');
    }

    private function leadDelete(Leads $leads, int $id): void
    {
        if ($this->isPost()) {
            $leads->delete($id);
            $this->auth->log('lead_delete', (string) $id);
            $this->flash('Заявка удалена.');
        }

        $this->redirect('leads');
    }

    /** Повторная отправка в телеграм — когда бот был не настроен или лежал. */
    private function leadResend(Leads $leads, int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $lead = $this->db->first('SELECT * FROM leads WHERE id = :id', ['id' => $id]);

        if ($lead === null) {
            $this->redirect('leads');

            return;
        }

        $text = "<b>Заявка с сайта KULAGER</b>\n\n"
            . 'Имя: ' . htmlspecialchars((string) $lead['name'], ENT_NOQUOTES, 'UTF-8') . "\n"
            . ($lead['phone'] !== '' ? 'Телефон: ' . htmlspecialchars((string) $lead['phone'], ENT_NOQUOTES, 'UTF-8') . "\n" : '')
            . ($lead['email'] !== '' ? 'Почта: ' . htmlspecialchars((string) $lead['email'], ENT_NOQUOTES, 'UTF-8') . "\n" : '')
            . ($lead['message'] !== '' ? "\n" . htmlspecialchars((string) $lead['message'], ENT_NOQUOTES, 'UTF-8') . "\n" : '')
            . "\nСтраница: /" . htmlspecialchars((string) $lead['page'], ENT_NOQUOTES, 'UTF-8');

        $error = $leads->send($text);

        $this->db->update('leads', [
            'notified'     => $error === null ? 1 : 0,
            'notify_error' => mb_substr((string) $error, 0, 255),
        ], 'id = :id', ['id' => $id]);

        $this->flash($error === null ? 'Отправлено в телеграм.' : 'Не отправилось: ' . $error);
        $this->redirect('leads');
    }

    private function leadTelegram(): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);

        $settings->save([
            'telegram_token' => (string) ($_POST['telegram_token'] ?? ''),
            'telegram_chat'  => (string) ($_POST['telegram_chat'] ?? ''),
        ]);

        $this->auth->log('lead_telegram_settings');
        $this->flash('Настройки бота сохранены.');
        $this->redirect('leads');
    }

    /**
     * Определяет чат по последнему сообщению боту: так не приходится искать
     * идентификатор вручную — достаточно написать боту «/start».
     */
    private function leadDetectChat(): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $token = trim($settings->get('telegram_token', ''));

        if ($token === '') {
            $this->flash('Сначала сохраните токен бота.');
            $this->redirect('leads');

            return;
        }

        $response = @file_get_contents(
            'https://api.telegram.org/bot' . $token . '/getUpdates',
            false,
            stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]])
        );

        $data = is_string($response) ? json_decode($response, true) : null;

        if (!is_array($data) || empty($data['ok'])) {
            $this->flash('Телеграм не ответил: ' . (string) ($data['description'] ?? 'нет связи'));
            $this->redirect('leads');

            return;
        }

        $chat = null;

        foreach (array_reverse((array) $data['result']) as $update) {
            $message = $update['message'] ?? $update['channel_post'] ?? null;

            if (is_array($message) && isset($message['chat']['id'])) {
                $chat = (string) $message['chat']['id'];
                break;
            }
        }

        if ($chat === null) {
            $this->flash('Не нашли ни одного сообщения. Напишите боту «/start» и повторите.');
            $this->redirect('leads');

            return;
        }

        $settings->save(['telegram_chat' => $chat]);
        $this->auth->log('lead_telegram_chat', $chat);
        $this->flash('Чат определён: ' . $chat);
        $this->redirect('leads');
    }

    private function leadTestMessage(Leads $leads): void
    {
        if ($this->isPost()) {
            $error = $leads->send("<b>Проверка связи</b>\nЕсли вы видите это сообщение, заявки будут приходить сюда.");

            $this->flash($error === null ? 'Сообщение ушло — проверьте телеграм.' : 'Не отправилось: ' . $error);
        }

        $this->redirect('leads');
    }

    /* -------------------------------------------------------- пользователи */

    /** Разбор адресов раздела пользователей. */
    private function userRoutes(array $segments): void
    {
        $head = (string) ($segments[0] ?? '');

        if ($head === '') {
            $this->userList();

            return;
        }

        if ($head === 'add') {
            $this->userAdd();

            return;
        }

        $user = $this->db->first('SELECT * FROM users WHERE id = :id', ['id' => (int) $head]);

        if ($user === null) {
            $this->notFound();

            return;
        }

        match ($segments[1] ?? '') {
            'role'     => $this->userRole($user),
            'password' => $this->userPassword($user),
            'toggle'   => $this->userToggle($user),
            'twofa'    => $this->userDropTwoFactor($user),
            'delete'   => $this->userDelete($user),
            default    => $this->notFound(),
        };
    }

    private function userList(): void
    {
        $this->render('users', [
            'users'   => $this->db->all('SELECT * FROM users ORDER BY role, email'),
            'me'      => (int) ($this->auth->user()['id'] ?? 0),
            'created' => (array) ($_SESSION['new_user'] ?? []),
        ], 'Пользователи');

        // Пароль нового пользователя показываем ровно один раз
        unset($_SESSION['new_user']);
    }

    private function userAdd(): void
    {
        if (!$this->isPost()) {
            $this->redirect('users');

            return;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('Укажите корректный адрес почты.');
            $this->redirect('users');

            return;
        }

        if ($this->db->first('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
            $this->flash('Пользователь с такой почтой уже заведён.');
            $this->redirect('users');

            return;
        }

        // Пароль придумываем сами: так он заведомо стойкий, а передать его
        // человеку всё равно придётся отдельным каналом
        $password = $this->newPassword();

        $id = $this->auth->createUser($email, $password, $name !== '' ? $name : $email, $role);

        $this->db->update(
            'users',
            ['created_by' => (int) ($this->auth->user()['id'] ?? 0), 'password_set_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );

        $_SESSION['new_user'] = ['email' => $email, 'password' => $password];

        $this->auth->log('user_add', $email, $role);
        $this->flash('Пользователь заведён. Передайте пароль лично — второй раз он не покажется.');
        $this->redirect('users');
    }

    private function userRole(array $user): void
    {
        if (!$this->isPost()) {
            $this->redirect('users');

            return;
        }

        $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

        // Последнего администратора не понижаем: иначе управлять станет некому
        if ($role !== 'admin' && $user['role'] === 'admin' && $this->adminCount() <= 1) {
            $this->flash('Это единственный администратор — роль оставлена прежней.');
            $this->redirect('users');

            return;
        }

        $this->db->update('users', ['role' => $role], 'id = :id', ['id' => $user['id']]);
        $this->auth->log('user_role', (string) $user['email'], $role);
        $this->flash('Роль изменена.');
        $this->redirect('users');
    }

    private function userPassword(array $user): void
    {
        if (!$this->isPost()) {
            $this->redirect('users');

            return;
        }

        $password = $this->newPassword();

        $this->db->update(
            'users',
            [
                'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
                'password_set_at' => date('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $user['id']]
        );

        $_SESSION['new_user'] = ['email' => $user['email'], 'password' => $password];

        $this->auth->log('user_password_reset', (string) $user['email']);
        $this->flash('Пароль сброшен. Передайте новый лично — второй раз он не покажется.');
        $this->redirect('users');
    }

    private function userToggle(array $user): void
    {
        if (!$this->isPost()) {
            $this->redirect('users');

            return;
        }

        $active = empty($user['is_active']);

        if (!$active && $user['role'] === 'admin' && $this->adminCount() <= 1) {
            $this->flash('Это единственный администратор — доступ оставлен.');
            $this->redirect('users');

            return;
        }

        if (!$active && (int) $user['id'] === (int) ($this->auth->user()['id'] ?? 0)) {
            $this->flash('Себе доступ закрыть нельзя.');
            $this->redirect('users');

            return;
        }

        $this->db->update('users', ['is_active' => $active ? 1 : 0], 'id = :id', ['id' => $user['id']]);
        $this->auth->log($active ? 'user_enable' : 'user_disable', (string) $user['email']);
        $this->flash($active ? 'Доступ открыт.' : 'Доступ закрыт.');
        $this->redirect('users');
    }

    /** Сброс второго шага: человек потерял телефон и не может войти сам. */
    private function userDropTwoFactor(array $user): void
    {
        if ($this->isPost()) {
            $this->db->update(
                'users',
                ['totp_enabled' => 0, 'totp_secret' => '', 'totp_backup' => null],
                'id = :id',
                ['id' => $user['id']]
            );

            $this->auth->log('user_twofa_reset', (string) $user['email']);
            $this->flash('Вход по коду отключён — пусть настроит заново в своём профиле.');
        }

        $this->redirect('users');
    }

    private function userDelete(array $user): void
    {
        if (!$this->isPost()) {
            $this->redirect('users');

            return;
        }

        if ((int) $user['id'] === (int) ($this->auth->user()['id'] ?? 0)) {
            $this->flash('Себя удалить нельзя.');
            $this->redirect('users');

            return;
        }

        if ($user['role'] === 'admin' && $this->adminCount() <= 1) {
            $this->flash('Это единственный администратор — удалять его нельзя.');
            $this->redirect('users');

            return;
        }

        $this->db->delete('users', 'id = :id', ['id' => $user['id']]);
        $this->auth->log('user_delete', (string) $user['email']);
        $this->flash('Пользователь удалён. Его записи в журнале остались.');
        $this->redirect('users');
    }

    private function adminCount(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1", [], 0);
    }

    /** Пароль, который не придётся придумывать: четыре слова через дефис. */
    private function newPassword(): string
    {
        $words = [
            'платформа', 'шасси', 'кузов', 'мотор', 'ремень', 'колесо', 'тормоз', 'подвеска',
            'ферма', 'теплица', 'склад', 'элеватор', 'карьер', 'рынок', 'питомник', 'парк',
        ];

        $parts = [];

        for ($i = 0; $i < 3; $i++) {
            $parts[] = $words[random_int(0, count($words) - 1)];
        }

        $parts[] = (string) random_int(100, 999);

        return implode('-', $parts);
    }

    /* ---------------------------------------------------------------- SEO */

    /**
     * Общие настройки для поисковиков: название сайта, приписка к заголовку,
     * описание и картинка по умолчанию, robots.txt, подтверждение прав.
     */
    private function seoSettings(): void
    {
        $settings = new Settings($this->db, (array) $this->config['contacts']);

        // Счётчики правятся на этой же странице: они про ту же аналитику
        $fields = Settings::group('seo') + Settings::group('counters');

        if ($this->isPost()) {
            $values = [];

            /*
             * На странице две формы — общие настройки и счётчики. Сохраняем
             * только то, что пришло: иначе одна форма затирала бы поля другой.
             */
            foreach ($fields as $key => $field) {
                if (($field['type'] ?? '') === 'bool') {
                    // Флажок не приходит вовсе, когда снят, — смотрим на соседнее скрытое поле
                    if (array_key_exists($key, $_POST)) {
                        $values[$key] = empty($_POST[$key]) ? '' : '1';
                    }

                    continue;
                }

                if (array_key_exists($key, $_POST)) {
                    $values[$key] = (string) $_POST[$key];
                }
            }

            $settings->save($values);
            $this->auth->log('seo_settings');
            $this->flash('Настройки SEO сохранены.');
            $this->redirect('seo');

            return;
        }

        FormBuilder::usePages($this->pages->catalog($this->site->defaultLocale()));

        $this->render('seo', [
            'fields'   => Settings::group('seo'),
            'counters' => Settings::group('counters'),
            'values'  => $settings->all(),
            'pages'   => $this->seoOverview(),
            'locales' => $this->site->locales(),
        ], 'SEO');
    }

    /**
     * Сводка по страницам: где не заполнено описание, где слишком длинный
     * заголовок, что закрыто от индексации. Проверять руками шесть десятков
     * страниц никто не станет.
     */
    private function seoOverview(): array
    {
        $rows = $this->db->all(
            'SELECT l.*, p.page_key
               FROM page_locales l
               JOIN pages p ON p.id = l.page_id
              ORDER BY l.locale, l.slug'
        );

        $out = [];

        foreach ($rows as $row) {
            $title = (string) $row['title'];
            $description = (string) ($row['description'] ?? '');
            $issues = [];

            if (trim($title) === '') {
                $issues[] = 'нет заголовка';
            } elseif (mb_strlen($title) > 70) {
                $issues[] = 'заголовок длиннее 70 знаков';
            }

            if (trim($description) === '') {
                $issues[] = 'нет описания';
            } elseif (mb_strlen($description) > 200) {
                $issues[] = 'описание длиннее 200 знаков';
            }

            if (!empty($row['noindex'])) {
                $issues[] = 'закрыта от индексации';
            }

            if (!$row['is_published']) {
                $issues[] = 'черновик';
            }

            $out[] = [
                'page_id'     => (int) $row['page_id'],
                'locale'      => (string) $row['locale'],
                'slug'        => (string) $row['slug'],
                'title'       => $title,
                'title_len'   => mb_strlen($title),
                'description' => $description,
                'desc_len'    => mb_strlen($description),
                'issues'      => $issues,
            ];
        }

        return $out;
    }

    /* --------------------------------------------------------- оформление */

    /** Разбор адресов раздела тем. */
    private function themeRoutes(array $segments): void
    {
        $themes = new ThemeRepository($this->db, new Settings($this->db, (array) $this->config['contacts']));

        // При первом заходе переносим темы из файла: иначе править нечего
        $themes->importBuiltin();

        $head = (string) ($segments[0] ?? '');

        if ($head === '') {
            $this->themeList($themes);

            return;
        }

        if ($head === 'add') {
            $this->themeAdd($themes);

            return;
        }

        if ($head === 'default') {
            $this->themeDefault($themes);

            return;
        }

        $theme = $themes->find((int) $head);

        if ($theme === null) {
            $this->notFound();

            return;
        }

        match ($segments[1] ?? '') {
            ''       => $this->themeForm($themes, $theme),
            'delete' => $this->themeDelete($themes, $theme),
            default  => $this->notFound(),
        };
    }

    private function themeList(ThemeRepository $themes): void
    {
        $this->render('themes', [
            'themes'  => $themes->rows(),
            'current' => $themes->defaultKey((string) $this->site->config('default_theme', 'dark')),
        ], 'Оформление');
    }

    private function themeForm(ThemeRepository $themes, array $theme): void
    {
        if ($this->isPost()) {
            $vars = [];

            foreach (array_keys(ThemeRepository::VARS) as $key) {
                $value = trim((string) ($_POST['vars'][$key] ?? ''));

                if ($value !== '') {
                    $vars[$key] = $value;
                }
            }

            $themes->save(
                (int) $theme['id'],
                trim((string) ($_POST['name'] ?? $theme['name'])),
                $vars,
                [
                    trim((string) ($_POST['swatch_bg'] ?? '')),
                    trim((string) ($_POST['swatch_accent'] ?? '')),
                ]
            );

            $this->auth->log('theme_save', (string) $theme['theme_key']);
            $this->flash('Тема сохранена.');
            $this->redirect('themes/' . $theme['id']);

            return;
        }

        $vars = json_decode((string) $theme['vars_json'], true);

        $this->render('theme-edit', [
            'theme'  => $theme,
            'vars'   => is_array($vars) ? $vars : [],
            'fields' => ThemeRepository::VARS,
        ], (string) $theme['name']);
    }

    private function themeAdd(ThemeRepository $themes): void
    {
        if (!$this->isPost()) {
            $this->redirect('themes');

            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $source = trim((string) ($_POST['source'] ?? 'dark'));

        if ($name === '') {
            $this->flash('Укажите название темы.');
            $this->redirect('themes');

            return;
        }

        $id = $themes->duplicate($source, $name);

        if ($id === null) {
            $this->flash('Не нашли тему, с которой копировать.');
            $this->redirect('themes');

            return;
        }

        $this->auth->log('theme_add', $name);
        $this->flash('Тема создана — поправьте цвета.');
        $this->redirect('themes/' . $id);
    }

    private function themeDefault(ThemeRepository $themes): void
    {
        if ($this->isPost()) {
            $key = trim((string) ($_POST['theme_key'] ?? ''));

            if ($themes->exists($key)) {
                $themes->setDefault($key);
                $this->auth->log('theme_default', $key);
                $this->flash('Тема по умолчанию изменена.');
            }
        }

        $this->redirect('themes');
    }

    private function themeDelete(ThemeRepository $themes, array $theme): void
    {
        if ($this->isPost()) {
            $themes->delete((int) $theme['id'])
                ? $this->flash('Тема удалена.')
                : $this->flash('Встроенную тему удалить нельзя.');

            $this->auth->log('theme_delete', (string) $theme['theme_key']);
        }

        $this->redirect('themes');
    }

    /**
     * Снимок языковой версии перед изменением: к нему вернёт «Отменить».
     * Снимки чистятся сами, храним последние пятнадцать на версию.
     */
    private function keepUndo(array $page, string $locale, string $comment): void
    {
        $this->pages->snapshot((int) $page['id'], $locale, $this->auth->user()['id'] ?? null, $comment);
    }

    /** Переключение языка админки — хранится у пользователя. */
    private function switchLanguage(): void
    {
        if ($this->isPost()) {
            $locale = (string) ($_POST['locale'] ?? '');

            if (isset($this->site->locales()[$locale])) {
                $this->db->update(
                    'users',
                    ['locale' => $locale],
                    'id = :id',
                    ['id' => $this->auth->user()['id'] ?? 0]
                );
            }
        }

        $this->redirect((string) ($_POST['back'] ?? ''));
    }

    /** Возврат языковой версии к состоянию до последнего изменения. */
    private function pageUndo(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $revision = $this->pages->lastRevision((int) $page['id'], $locale);

        if ($revision === null) {
            $this->flash('Отменять нечего: правок пока не было.');
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->pages->restoreRevision((int) $revision['id'])
            ? $this->flash('Отменено: ' . ($revision['comment'] ?: 'последнее изменение') . '.')
            : $this->flash('Не удалось отменить — снимок повреждён.');

        $this->auth->log('page_undo', $page['page_key'] . ' ' . $locale, (string) $revision['comment']);
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    /* -------------------------------------------------------------- меню */

    /** Разбор адресов редактора меню: menu/{язык}/{меню}/{действие}. */
    private function menuRoutes(array $segments): void
    {
        $nav = new NavigationRepository($this->db);
        $locale = (string) ($segments[0] ?? $this->site->defaultLocale());

        if (!isset($this->site->locales()[$locale])) {
            $this->notFound();

            return;
        }

        $menu = (string) ($segments[1] ?? '');

        if ($menu === '') {
            $this->menuList($nav, $locale);

            return;
        }

        if (!isset(NavigationRepository::MENUS[$menu])) {
            $this->notFound();

            return;
        }

        match ($segments[2] ?? '') {
            ''        => $this->menuEditor($nav, $locale, $menu),
            'add'     => $this->menuAdd($nav, $locale, $menu),
            'save'    => $this->menuSave($nav, $locale, $menu),
            'delete'  => $this->menuDelete($nav, $locale, $menu),
            'reorder' => $this->menuReorder($nav, $locale, $menu),
            default   => $this->notFound(),
        };
    }

    private function menuList(NavigationRepository $nav, string $locale): void
    {
        $counts = [];

        foreach (array_keys(NavigationRepository::MENUS) as $menu) {
            $counts[$menu] = count($nav->items($menu, $locale));
        }

        $this->render('menu', [
            'locale'   => $locale,
            'locales'  => $this->site->locales(),
            'menus'    => NavigationRepository::MENUS,
            'counts'   => $counts,
            'imported' => $nav->hasItems(),
        ], 'Меню');
    }

    private function menuEditor(NavigationRepository $nav, string $locale, string $menu): void
    {
        FormBuilder::usePages($this->pages->catalog($locale));

        $this->render('menu-edit', [
            'locale'  => $locale,
            'locales' => $this->site->locales(),
            'menu'    => $menu,
            'title'   => NavigationRepository::MENUS[$menu],
            'items'   => $nav->items($menu, $locale),
            'grouped' => $menu === 'industries',
        ], NavigationRepository::MENUS[$menu]);
    }

    private function menuAdd(NavigationRepository $nav, string $locale, string $menu): void
    {
        if (!$this->isPost()) {
            $this->redirect('menu/' . $locale . '/' . $menu);

            return;
        }

        $parent = (int) ($_POST['parent_id'] ?? 0);

        $nav->add($menu, $locale, [
            'parent_id' => $parent > 0 ? $parent : null,
            'title'     => trim((string) ($_POST['title'] ?? 'Новый пункт')),
            'url'       => trim((string) ($_POST['url'] ?? '')),
            'in_drawer' => true,
        ]);

        $this->auth->log('menu_add', $menu . '/' . $locale);
        $this->redirect('menu/' . $locale . '/' . $menu);
    }

    private function menuSave(NavigationRepository $nav, string $locale, string $menu): void
    {
        if (!$this->isPost()) {
            $this->redirect('menu/' . $locale . '/' . $menu);

            return;
        }

        foreach ((array) ($_POST['items'] ?? []) as $id => $values) {
            $item = $nav->find((int) $id);

            // Правим только пункты этого меню и языка: id приходит из формы
            if ($item === null || $item['menu_key'] !== $menu || $item['locale'] !== $locale) {
                continue;
            }

            $nav->update((int) $id, [
                'parent_id'    => $item['parent_id'],
                'title'        => trim((string) ($values['title'] ?? '')),
                'full_title'   => trim((string) ($values['full_title'] ?? '')),
                'footer_title' => trim((string) ($values['footer_title'] ?? '')),
                'url'          => trim((string) ($values['url'] ?? '')),
                'in_drawer'    => !empty($values['in_drawer']),
                'in_footer'    => !empty($values['in_footer']),
            ]);
        }

        $this->auth->log('menu_save', $menu . '/' . $locale);
        $this->flash('Меню сохранено.');
        $this->redirect('menu/' . $locale . '/' . $menu);
    }

    private function menuDelete(NavigationRepository $nav, string $locale, string $menu): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $item = $nav->find($id);

        if ($this->isPost() && $item !== null && $item['menu_key'] === $menu && $item['locale'] === $locale) {
            $nav->delete($id);
            $this->auth->log('menu_delete', $menu . '/' . $locale);
            $this->flash('Пункт удалён.');
        }

        $this->redirect('menu/' . $locale . '/' . $menu);
    }

    private function menuReorder(NavigationRepository $nav, string $locale, string $menu): void
    {
        $order = array_map('intval', (array) ($_POST['order'] ?? []));
        $allowed = array_column($nav->items($menu, $locale), 'id');

        // Порядок принимаем только для пунктов этого меню
        $order = array_values(array_filter($order, static fn (int $id): bool => in_array($id, array_map('intval', $allowed), true)));

        if ($order !== []) {
            $nav->reorder($order);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
    }

    /** Ручной сброс кэша: на случай правки в базе мимо админки. */
    private function cacheFlush(): void
    {
        if ($this->isPost()) {
            // Кэш уже сброшен общим обработчиком POST, здесь только сообщение
            $this->flash('Кэш страниц сброшен.');
        }

        $this->redirect('system');
    }

    private function mediaRoutes(array $segments): void
    {
        $library = new MediaLibrary($this->db);

        // media/{id}/delete и media/{id}/alt
        if (ctype_digit((string) ($segments[0] ?? ''))) {
            $item = $library->find((int) $segments[0]);

            if ($item === null) {
                $this->notFound();

                return;
            }

            match ($segments[1] ?? '') {
                'delete' => $this->mediaDelete($library, $item),
                'alt'    => $this->mediaAlt($library, $item),
                default  => $this->notFound(),
            };

            return;
        }

        match ($segments[0] ?? '') {
            ''       => $this->mediaList($library),
            'upload' => $this->mediaUpload($library),
            'json'   => $this->mediaJson($library),
            default  => $this->notFound(),
        };
    }

    /** Список файлов для окна выбора картинки. */
    /**
     * Что сейчас со входом в админку: по этим строкам видно, где тонко.
     * Проверки читают состояние и ничего не меняют.
     */
    private function securityOverview(): array
    {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        $users = $this->db->all('SELECT email, role, totp_enabled, last_login_at FROM users ORDER BY id');
        $withTwoFa = count(array_filter($users, static fn (array $u): bool => !empty($u['totp_enabled'])));

        $failed = (int) $this->db->value(
            'SELECT COUNT(*) FROM activity_log WHERE action IN (:failed, :blocked) AND created_at > :since',
            [
                'failed'  => 'login_failed',
                'blocked' => 'login_blocked',
                'since'   => date('Y-m-d H:i:s', time() - 86400),
            ],
            0
        );

        return [
            'https'      => $https,
            'users'      => $users,
            'two_factor' => $withTwoFa,
            'failed'     => $failed,
            'debug'      => !empty($this->config['debug']),
        ];
    }

    private function mediaJson(MediaLibrary $library): void
    {
        $items = [];

        foreach ($library->all(500) as $item) {
            $items[] = [
                'id'     => (int) $item['id'],
                'path'   => (string) $item['path'],
                'name'   => basename((string) $item['path']),
                'alt'    => (string) $item['alt_ru'],
                'width'  => (int) $item['width'],
                'height' => (int) $item['height'],
            ];
        }

        $this->json(['items' => $items, 'writable' => $library->isWritable()]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function mediaList(MediaLibrary $library): void
    {
        $this->render('media', [
            'items'    => $library->all(),
            'writable' => $library->isWritable(),
            'gd'       => extension_loaded('gd'),
            'limit'    => ini_get('upload_max_filesize'),
        ], 'Медиатека');
    }

    private function mediaUpload(MediaLibrary $library): void
    {
        if (!$this->isPost()) {
            $this->redirect('media');

            return;
        }

        $uploaded = 0;
        $errors = [];
        $added = [];

        // Файлы приходят пачкой: раскладываем массив $_FILES по одному
        $files = $_FILES['files'] ?? null;

        foreach ((array) ($files['name'] ?? []) as $index => $name) {
            [$item, $error] = $library->upload([
                'name'     => $name,
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$index] ?? 0,
            ]);

            if ($item !== null) {
                $uploaded++;
                $added[] = [
                    'path' => (string) $item['path'],
                    'name' => basename((string) $item['path']),
                ];
                continue;
            }

            if ($error !== null) {
                $errors[] = $name . ': ' . $error;
            }
        }

        if ($uploaded > 0) {
            $this->auth->log('media_upload', (string) $uploaded);
        }

        // Загрузка из окна выбора картинки: страница не перезагружается,
        // поэтому отвечаем списком добавленных файлов
        if (($_POST['json'] ?? '') === '1') {
            $this->json(['uploaded' => $added, 'errors' => $errors]);

            return;
        }

        if ($uploaded > 0) {
            $this->flash('Загружено файлов: ' . $uploaded . '.');
        }

        foreach (array_slice($errors, 0, 5) as $message) {
            $this->flash($message);
        }

        $this->redirect('media');
    }

    private function mediaAlt(MediaLibrary $library, array $item): void
    {
        if ($this->isPost()) {
            $library->updateAlt(
                (int) $item['id'],
                (string) ($_POST['alt_ru'] ?? ''),
                (string) ($_POST['alt_kk'] ?? '')
            );
            $this->flash('Описание сохранено.');
        }

        $this->redirect('media');
    }

    private function mediaDelete(MediaLibrary $library, array $item): void
    {
        if (!$this->isPost()) {
            $this->redirect('media');

            return;
        }

        // Файл может использоваться на страницах — предупреждаем, но не запрещаем
        $used = (int) $this->db->value(
            'SELECT COUNT(*) FROM page_blocks WHERE data_json LIKE :needle',
            ['needle' => '%' . $item['path'] . '%'],
            0
        );

        $library->delete((int) $item['id']);
        $this->auth->log('media_delete', (string) $item['path']);

        $this->flash($used > 0
            ? 'Файл удалён, но он использовался в блоках (' . $used . ') — проверьте страницы.'
            : 'Файл удалён.');

        $this->redirect('media');
    }

    /* ----------------------------------------------------- редактор страницы */

    private function pageEditor(array $page, string $locale): void
    {
        $localeRow = $this->pages->locale((int) $page['id'], $locale);
        $isDefault = $locale === $this->site->defaultLocale();

        $this->render('page', [
            'page'         => $page,
            'locale'       => $locale,
            'localeRow'    => $localeRow,
            'blocks'       => $this->pages->blocks((int) $page['id'], $locale, true),
            'library'      => Blocks::grouped(),
            'otherLocales' => $this->otherLocales($locale),
            'isDefault'    => $isDefault,
            'baseLocale'   => $this->site->defaultLocale(),
            'baseBlocks'   => $isDefault
                ? 0
                : count($this->pages->blocks((int) $page['id'], $this->site->defaultLocale(), true)),
            'translation'  => $this->pages->translationStats((int) $page['id'], $locale),
            'undo'         => $this->pages->lastRevision((int) $page['id'], $locale),
        ], $localeRow['title'] ?? $page['page_key']);
    }

    /** Копирование блоков основного языка в текущий — заготовка для перевода. */
    private function pageCopyBlocks(array $page, string $locale): void
    {
        if (!$this->isPost() || $locale === $this->site->defaultLocale()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $base = $this->site->defaultLocale();

        // Копирование затирает перевод целиком — снимок обязателен
        $this->keepUndo($page, $locale, 'копирование блоков из основного языка');

        $copied = $this->pages->copyBlocks((int) $page['id'], $base, $locale);

        // Языковой версии могло не быть вовсе — создаём её вместе с блоками
        if ($this->pages->locale((int) $page['id'], $locale) === null) {
            $baseRow = $this->pages->locale((int) $page['id'], $base) ?? [];

            $this->pages->saveLocale((int) $page['id'], $locale, [
                'slug'        => (string) ($baseRow['slug'] ?? ''),
                'title'       => (string) ($baseRow['title'] ?? ''),
                'description' => (string) ($baseRow['description'] ?? ''),
                'og_image'    => (string) ($baseRow['og_image'] ?? ''),
                'is_published' => false,
            ]);
        }

        $this->auth->log('copy_blocks', $page['page_key'] . ' ' . $base . '→' . $locale);
        $this->flash($copied > 0
            ? 'Скопировано блоков: ' . $copied . '. Теперь замените текст на перевод.'
            : 'В основной версии нет блоков для копирования.');

        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function pageSettings(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $bar = array_filter([
            'title'    => trim((string) ($_POST['bar_title'] ?? '')),
            'subtitle' => trim((string) ($_POST['bar_subtitle'] ?? '')),
            'label'    => trim((string) ($_POST['bar_label'] ?? '')),
            'message'  => trim((string) ($_POST['bar_message'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $title = trim((string) ($_POST['title'] ?? ''));
        $current = (string) ($this->pages->locale((int) $page['id'], $locale)['slug'] ?? '');
        $slug = Slug::make((string) ($_POST['slug'] ?? ''));

        /*
         * Адрес пустой — либо это главная (у неё он пустой и должен таким
         * остаться), либо поле просто не заполнили: тогда собираем его
         * из заголовка, сохраняя вложенность.
         */
        if ($slug === '' && $current !== '') {
            $slug = Slug::fromTitle($title, $current);
        }

        if ($this->pages->slugTaken($slug, $locale, (int) $page['id'])) {
            $this->flash('Адрес «/' . $slug . '» уже занят другой страницей — настройки не сохранены.');
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->keepUndo($page, $locale, 'настройки страницы');

        $this->pages->saveLocale((int) $page['id'], $locale, [
            'slug'        => $slug,
            'title'       => $title,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'og_image'    => trim((string) ($_POST['og_image'] ?? '')),
            'noindex'     => !empty($_POST['noindex']),
            'canonical'   => Slug::make((string) ($_POST['canonical'] ?? '')),
            'bar'         => $bar !== [] ? $bar : null,
        ]);

        $this->flash('Настройки страницы сохранены.');
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function blockForm(array $page, string $locale, array $block): void
    {
        $definition = Blocks::definition($block['type']);

        if ($definition === null) {
            $this->notFound();

            return;
        }

        $errors = [];
        $data = $block['data'];

        if ($this->isPost()) {
            [$clean, $errors] = Blocks::sanitize($block['type'], (array) ($_POST['data'] ?? []));

            if ($errors === []) {
                $this->keepUndo($page, $locale, 'правка блока «' . Blocks::title($block['type']) . '»');
                $this->pages->updateBlock((int) $block['id'], $clean);
                $this->flash('Блок «' . Blocks::title($block['type']) . '» сохранён.');
                $this->redirect('page/' . $page['id'] . '/' . $locale . '#block-' . $block['id']);

                return;
            }

            // При ошибке показываем то, что ввёл редактор, а не старое значение
            $data = $clean;
        }

        // Ссылки в блоке выбираются из страниц того же языка
        FormBuilder::usePages($this->pages->catalog($locale));

        $this->render('block', [
            'page'       => $page,
            'locale'     => $locale,
            'block'      => $block,
            'definition' => $definition,
            'data'       => $data,
            'errors'     => $errors,
        ], Blocks::title($block['type']));
    }

    private function blockAdd(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $type = (string) ($_POST['type'] ?? '');

        if (!Blocks::exists($type)) {
            $this->flash('Неизвестный тип блока.');
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $id = $this->pages->addBlock((int) $page['id'], $locale, $type, Blocks::defaults($type));

        $this->redirect('page/' . $page['id'] . '/' . $locale . '/block/' . $id);
    }

    private function blockDelete(array $page, string $locale, array $block): void
    {
        $this->keepUndo($page, $locale, 'удаление блока «' . Blocks::title($block['type']) . '»');
        $this->pages->deleteBlock((int) $block['id']);
        $this->flash('Блок «' . Blocks::title($block['type']) . '» удалён.');
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function blockToggle(array $page, string $locale, array $block): void
    {
        $this->pages->setBlockVisible((int) $block['id'], !$block['is_visible']);
        $this->redirect('page/' . $page['id'] . '/' . $locale . '#block-' . $block['id']);
    }

    private function blockDuplicate(array $page, string $locale, array $block): void
    {
        $id = $this->pages->addBlock(
            (int) $page['id'],
            $locale,
            $block['type'],
            $block['data'],
            (int) $block['sort'] + 5
        );

        $this->flash('Блок скопирован.');
        $this->redirect('page/' . $page['id'] . '/' . $locale . '#block-' . $id);
    }

    /** Новый порядок блоков. Отвечает JSON: вызывается перетаскиванием. */
    private function blocksReorder(array $page, string $locale): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isPost()) {
            echo json_encode(['ok' => false]);

            return;
        }

        $order = array_map('intval', (array) ($_POST['order'] ?? []));

        // Переставляем только блоки этой страницы и этого языка
        $allowed = array_column(
            $this->db->all(
                'SELECT id FROM page_blocks WHERE page_id = :id AND locale = :locale',
                ['id' => $page['id'], 'locale' => $locale]
            ),
            'id'
        );

        $order = array_values(array_intersect($order, array_map('intval', $allowed)));

        $this->pages->reorderBlocks($order);

        echo json_encode(['ok' => true, 'count' => count($order)]);
    }

    private function pagePublish(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->pages->publish((int) $page['id'], $locale, (int) ($this->auth->user()['id'] ?? 0) ?: null);
        $this->auth->log('publish', $page['page_key'] . ' / ' . $locale);

        $this->flash('Страница опубликована.');
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function pageUnpublish(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->pages->unpublish((int) $page['id'], $locale);
        $this->auth->log('unpublish', $page['page_key'] . ' / ' . $locale);

        $this->flash('Страница снята с публикации.');
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    /** @return array<string,array> языки, кроме текущего */
    private function otherLocales(string $current): array
    {
        $out = $this->site->locales();
        unset($out[$current]);

        return $out;
    }

    /* ------------------------------------------------------------ служебное */

    private function contentFilesCount(): int
    {
        $count = 0;
        $dir = APP_DIR . '/content';

        if (!is_dir($dir)) {
            return 0;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('~\.(ru|kk)\.php$~', $file->getFilename())) {
                $count++;
            }
        }

        return $count;
    }

    private function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function redirect(string $path = ''): void
    {
        header('Location: ' . $this->url($path));
        exit;
    }

    public function url(string $path = ''): string
    {
        return rtrim($this->base . '/' . ltrim($path, '/'), '/') ?: $this->base;
    }

    private function flash(string $message): void
    {
        $_SESSION['flash'][] = $message;
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->render('not-found', [], 'Не найдено');
    }

    private function render(string $template, array $data, string $title = ''): void
    {
        $view = new View($this->site);

        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $content = $view->render('admin/' . $template, $data + [
            'admin' => $this,
            'auth'  => $this->auth,
        ]);

        echo $view->render('admin/layout', [
            'admin'    => $this,
            'auth'     => $this->auth,
            'content'  => $content,
            'title'    => $title,
            'messages' => $messages,
        ]);
    }
}
