<?php
declare(strict_types=1);

/**
 * Состояние, обслуживание и настройки сайта.
 *
 * Всё, что нужно, когда на хостинге нет консоли: миграции, резервная
 * копия, журнал действий, сброс кэша и проверка окружения.
 */
final class AdminSystem extends AdminSection
{
    public function dashboard(): void
    {
        $stats = [
            'pages'     => (int) $this->db->value('SELECT COUNT(*) FROM pages', [], 0),
            'published' => (int) $this->db->value('SELECT COUNT(*) FROM page_locales WHERE is_published = 1', [], 0),
            'blocks'    => (int) $this->db->value('SELECT COUNT(*) FROM page_blocks', [], 0),
            'media'     => (int) $this->db->value('SELECT COUNT(*) FROM media', [], 0),
        ];

        [$recentHide, $recentParams] = $this->hideOwners('u.email');

        $this->render('dashboard', [
            'stats'  => $stats,
            'recent' => $this->db->all(
                'SELECT a.*, u.name AS user_name FROM activity_log a
                   LEFT JOIN users u ON u.id = a.user_id'
                   . ($recentHide !== '' ? ' WHERE ' . $recentHide : '') .
                 ' ORDER BY a.created_at DESC LIMIT 10',
                $recentParams
            ),
        ], 'Панель');
    }

    /** Контакты и адреса — то, что меняется без разработчика. */
    public function siteSettings(): void
    {
        $settings = new Settings($this->db, (array) $this->config['contacts']);

        if ($this->isPost()) {
            $values = [];

            foreach (array_keys(Settings::group('contacts')) as $key) {
                $values[$key] = (string) ($_POST[$key] ?? '');
            }

            $settings->save($values);
            $this->auth->log('settings');
            $this->flash(at('Настройки сохранены.'));
            $this->redirect('settings');

            return;
        }

        $this->render('settings', [
            'fields'   => Settings::group('contacts'),
            'values'   => $settings->all(),
            'defaults' => (array) $this->config['contacts'],
        ], 'Настройки');
    }

    public function system(): void
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

    public function import(): void
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

        $this->flash(at(
            'Перенесено страниц: %d, блоков: %d, пунктов меню: %d.',
            $result['pages'],
            $result['blocks'],
            $menuItems
        ));

        // Поля, которых нет в реестре блоков, — их не покажет редактор,
        // хотя на сайте они выводятся. Сигнал дополнить реестр.
        if ($result['unknown'] !== []) {
            $this->flash(at('Не описаны в реестре блоков: %s', implode(', ', $result['unknown'])));
        }

        $this->redirect('pages');
    }

    /** Выгрузка копии: база и загруженные файлы. */
    public function backup(): void
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

    public function activityLog(): void
    {
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $perPage = 50;

        // Действия владельца в журнале не показываем — кроме как самому владельцу
        [$hide, $params] = $this->hideOwners('u.email');
        $filter = $hide !== '' ? ' WHERE ' . $hide : '';

        $this->render('log', [
            'rows' => $this->db->all(
                'SELECT a.*, u.name AS user_name, u.email AS user_email
                   FROM activity_log a
                   LEFT JOIN users u ON u.id = a.user_id'
                   . $filter .
                 ' ORDER BY a.created_at DESC, a.id DESC
                  LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
                $params
            ),
            'page'  => $page,
            'total' => (int) $this->db->value(
                'SELECT COUNT(*) FROM activity_log a LEFT JOIN users u ON u.id = a.user_id' . $filter,
                $params
            ),
            'perPage' => $perPage,
        ], 'Журнал действий');
    }

    public function migrate(): void
    {
        // При первом запуске пользователей ещё нет — тогда миграции открыты,
        // иначе схему меняет только администратор
        if ($this->auth->hasUsers() && !$this->auth->isAdmin()) {
            $this->flash(at('Обновление базы доступно только администратору.'));
            $this->redirect('');

            return;
        }

        if ($this->isPost()) {
            $applied = $this->migrator->migrate();
            $this->flash(at('Применено миграций: %d.', count($applied)));
            $this->redirect('');

            return;
        }

        $this->render('migrate', ['pending' => $this->migrator->pending()], 'Обновление базы');
    }

    /** Ручной сброс кэша: на случай правки в базе мимо админки. */
    public function cacheFlush(): void
    {
        if ($this->isPost()) {
            // Кэш уже сброшен общим обработчиком POST, здесь только сообщение
            $this->flash(at('Кэш страниц сброшен.'));
        }

        $this->redirect('system');
    }
}
