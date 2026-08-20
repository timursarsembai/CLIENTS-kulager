<?php
declare(strict_types=1);

/**
 * Вход в админку. Пароли — только через password_hash(), попытки входа
 * ограничены по адресу, сессия привязана к строке браузера.
 *
 * Сессия стартует исключительно в админке: публичная часть работает
 * без cookies, иначе её нельзя будет кэшировать.
 */
final class Auth
{
    /** Сколько сессия живёт без действий редактора. */
    private const IDLE_LIMIT = 28800;

    /** Сколько живёт вообще — даже если работать не переставая. */
    private const ABSOLUTE_LIMIT = 604800;

    private Db $db;
    private array $config;
    private ?array $user = null;

    public function __construct(Db $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Открывает сессию админки. Метод статический: та же сессия читается
     * и на публичной части — по ней сайт узнаёт вошедшего и показывает ему
     * панель администратора.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        /*
         * PHP сам убирает файлы сессий, к которым не обращались
         * session.gc_maxlifetime — по умолчанию 24 минуты. Пока это меньше
         * нашего лимита простоя, вошедшего выбрасывает намного раньше срока,
         * который админка обещает. Поэтому срок уборки равен нашему.
         */
        ini_set('session.gc_maxlifetime', (string) self::IDLE_LIMIT);

        // Сессию с придуманным номером не поднимаем — только со своей
        ini_set('session.use_strict_mode', '1');

        /*
         * На общем хостинге сессии всех сайтов лежат в одном каталоге, и чужая
         * уборка с коротким сроком чистит наши файлы заодно. Свой каталог —
         * вне веб-корня, поэтому снаружи он недоступен.
         */
        $store = APP_DIR . '/sessions';

        if ((is_dir($store) || @mkdir($store, 0700, true)) && is_writable($store)) {
            session_save_path($store);
        }

        session_name('kulager_admin');
        session_set_cookie_params([
            'lifetime' => self::IDLE_LIMIT,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Strict',
        ]);
        session_start();

        /*
         * Срок куки браузер отсчитывает от её выдачи, а не от последнего
         * действия. Вошедшему выдаём её заново на каждой странице: пока
         * человек работает, вход не обрывается на ровном месте.
         */
        if (($_SESSION['user_id'] ?? 0) > 0 && !headers_sent()) {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + self::IDLE_LIMIT,
                'path'     => '/',
                'httponly' => true,
                'secure'   => $https,
                'samesite' => 'Strict',
            ]);
        }
    }

    /** Есть ли вообще хоть один пользователь — от этого зависит первичная установка. */
    public function hasUsers(): bool
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users') > 0;
    }

    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        // Отпечаток браузера: угнанная cookie в другом окружении не сработает
        if (($_SESSION['fingerprint'] ?? '') !== $this->fingerprint()) {
            $this->logout();

            return null;
        }

        // Забытая открытая вкладка не должна оставаться входом навсегда
        if ($this->sessionExpired()) {
            $this->logout();

            return null;
        }

        $user = $this->db->first('SELECT * FROM users WHERE id = :id', ['id' => $id]);

        // Пользователя удалили или закрыли ему доступ, пока он работал
        if ($user === null || (array_key_exists('is_active', $user) && !$user['is_active'])) {
            $this->logout();

            return null;
        }

        $_SESSION['seen_at'] = time();

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? '') === 'admin';
    }

    /**
     * Проверяет пару email/пароль и открывает сессию.
     *
     * @return string|null текст ошибки, либо null при успехе
     */
    public function attempt(string $email, string $password): ?string
    {
        $ip = $this->ip();

        if ($this->tooManyAttempts($ip, $email)) {
            $this->log('login_blocked', $email);

            return at('Слишком много попыток входа. Повторите через 15 минут.');
        }

        $user = $this->db->first('SELECT * FROM users WHERE email = :email', ['email' => $email]);

        // Считаем хеш даже для несуществующего пользователя, чтобы время
        // ответа не выдавало, заведён такой email или нет
        $hash = $user['password_hash'] ?? '$2y$12$usqLoDeeSybFYNXi/aBcAO0GEmuHFtHmMk1M5PfrKYPtIvHmwZAqi';

        if (!password_verify($password, $hash) || $user === null) {
            $this->recordAttempt($ip, $email);
            $this->log('login_failed', $email, $ip);

            return at('Неверная почта или пароль.');
        }

        // Доступ закрыт администратором — пароль правильный, но входа нет
        if (array_key_exists('is_active', $user) && !$user['is_active']) {
            $this->log('login_disabled', $email, $ip);

            return at('Доступ к админке закрыт. Обратитесь к администратору.');
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        $this->db->delete('login_attempts', 'ip = :ip OR email = :email', ['ip' => $ip, 'email' => mb_substr($email, 0, 191)]);

        /*
         * Второй шаг: пароль принят, но сессию открываем только после кода.
         * До этого в сессии лежит лишь номер пользователя, ожидающего код, —
         * этого недостаточно, чтобы что-то сделать в админке.
         */
        if (!empty($user['totp_enabled'])) {
            self::startSession();
            session_regenerate_id(true);

            $_SESSION['pending_user'] = (int) $user['id'];
            $_SESSION['pending_since'] = time();
            $this->log('login_password_ok', $email, $ip);

            return null;
        }

        $this->login($user);
        $this->log('login', $email, $ip);

        return null;
    }

    public function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['fingerprint'] = $this->fingerprint();
        $_SESSION['started_at'] = time();
        $_SESSION['seen_at'] = time();

        $this->user = $user;

        $this->db->update(
            'users',
            ['last_login_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $user['id']]
        );
    }

    public function logout(): void
    {
        $this->user = null;
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Ждёт ли вход подтверждения одноразовым кодом. */
    public function awaitingCode(): bool
    {
        self::startSession();

        // Пять минут на ввод кода: дольше держать полупройденный вход незачем
        if (time() - (int) ($_SESSION['pending_since'] ?? 0) > 300) {
            unset($_SESSION['pending_user'], $_SESSION['pending_since']);
        }

        return !empty($_SESSION['pending_user']);
    }

    /**
     * Второй шаг входа. Принимает и одноразовый код, и запасной —
     * запасной после использования сгорает.
     *
     * @return string|null текст ошибки, либо null при успехе
     */
    public function attemptCode(string $code): ?string
    {
        self::startSession();

        $id = (int) ($_SESSION['pending_user'] ?? 0);

        if ($id === 0) {
            return at('Сессия входа истекла. Введите пароль заново.');
        }

        $ip = $this->ip();

        if ($this->tooManyAttempts($ip, '')) {
            return at('Слишком много попыток. Повторите через 15 минут.');
        }

        $user = $this->db->first('SELECT * FROM users WHERE id = :id', ['id' => $id]);

        if ($user === null) {
            unset($_SESSION['pending_user']);

            return at('Пользователь не найден.');
        }

        if (Totp::verify((string) $user['totp_secret'], $code)) {
            unset($_SESSION['pending_user'], $_SESSION['pending_since']);
            $this->login($user);
            $this->log('login', (string) $user['email'], $ip);

            return null;
        }

        if ($this->useBackupCode($user, $code)) {
            unset($_SESSION['pending_user'], $_SESSION['pending_since']);
            $this->login($user);
            $this->log('login_backup_code', (string) $user['email'], $ip);

            return null;
        }

        $this->recordAttempt($ip, (string) $user['email']);
        $this->log('login_code_failed', (string) $user['email'], $ip);

        return at('Код не подошёл. Проверьте время на телефоне.');
    }

    /** Запасные коды: десять одноразовых строк, храним только их хеши. */
    public function makeBackupCodes(int $userId): array
    {
        $codes = [];
        $hashes = [];

        for ($i = 0; $i < 10; $i++) {
            $code = strtolower(bin2hex(random_bytes(4)));
            $codes[] = $code;
            $hashes[] = password_hash($code, PASSWORD_DEFAULT);
        }

        $this->db->update('users', ['totp_backup' => json_encode($hashes)], 'id = :id', ['id' => $userId]);

        return $codes;
    }

    private function useBackupCode(array $user, string $code): bool
    {
        $hashes = json_decode((string) ($user['totp_backup'] ?? ''), true);

        if (!is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (!password_verify(trim($code), (string) $hash)) {
                continue;
            }

            unset($hashes[$index]);

            $this->db->update(
                'users',
                ['totp_backup' => json_encode(array_values($hashes))],
                'id = :id',
                ['id' => $user['id']]
            );

            return true;
        }

        return false;
    }

    /** Создание пользователя. Пустой пароль не допускаем. */
    public function createUser(string $email, string $password, string $name, string $role = 'editor'): int
    {
        return $this->db->insert('users', [
            'email'         => $email,
            'name'          => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function log(string $action, string $target = '', string $details = ''): void
    {
        try {
            $this->db->insert('activity_log', [
                'user_id'    => $this->user()['id'] ?? null,
                'action'     => $action,
                'target'     => $target,
                'details'    => $details,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Журнал действий не должен ломать работу админки
        }
    }

    private function sessionExpired(): bool
    {
        $now = time();
        $seen = (int) ($_SESSION['seen_at'] ?? $now);
        $started = (int) ($_SESSION['started_at'] ?? $now);

        return $now - $seen > self::IDLE_LIMIT || $now - $started > self::ABSOLUTE_LIMIT;
    }

    /**
     * Перебор считаем и по адресу, и по почте.
     *
     * Только по адресу мало: подбор с сотни адресов по одному аккаунту
     * лимит не превысит, а до пароля доберётся.
     */
    private function tooManyAttempts(string $ip, string $email): bool
    {
        $limit = (int) ($this->config['login_attempts_limit'] ?? 10);
        $since = date('Y-m-d H:i:s', time() - 900);

        $byIp = (int) $this->db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > :since',
            ['ip' => $ip, 'since' => $since]
        );

        if ($byIp >= $limit) {
            return true;
        }

        $byEmail = (int) $this->db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :email AND attempted_at > :since',
            ['email' => mb_substr($email, 0, 191), 'since' => $since]
        );

        return $email !== '' && $byEmail >= $limit;
    }

    private function recordAttempt(string $ip, string $email): void
    {
        $this->db->insert('login_attempts', [
            'ip'           => $ip,
            'email'        => mb_substr($email, 0, 191),
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);

        // Чистим старые записи здесь же: планировщика на хостинге нет
        $this->db->delete('login_attempts', 'attempted_at < :old', ['old' => date('Y-m-d H:i:s', time() - 86400)]);
    }

    private function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
