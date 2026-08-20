<?php
declare(strict_types=1);

/**
 * Вход, восстановление пароля, профиль и второй шаг.
 *
 * Здесь всё, что происходит до входа и вокруг учётной записи: первичная
 * установка, форма входа, восстановление пароля через телеграм, смена
 * собственного пароля и одноразовые коды.
 */
final class AdminAccess extends AdminSection
{
    /** Восстановление пароля — отдельная забота, но начинается отсюда. */
    private function reset(): PasswordReset
    {
        return new PasswordReset($this->db, $this->auth);
    }

    public function setup(): void
    {
        $errors = [];

        if ($this->isPost()) {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $name = trim((string) ($_POST['name'] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = at('Укажите корректный адрес почты.');
            }

            if (mb_strlen($password) < 10) {
                $errors['password'] = at('Пароль должен быть не короче 10 символов.');
            }

            if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
                $errors['password_confirm'] = at('Пароли не совпадают.');
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

    public function login(): void
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

    /**
     * Восстановление пароля через телеграм.
     *
     * Код уходит в тот же чат, куда падают заявки: почтой на shared-хостинге
     * пользоваться ненадёжно — письма уходят в спам или не уходят вовсе,
     * а телеграм у владельца сайта уже настроен и проверен.
     *
     * Форма одна, в два шага: сначала почта, потом код и новый пароль.
     * О том, заведена ли такая почта, форма не рассказывает.
     */
    public function passwordReset(): void
    {
        if ($this->auth->check()) {
            $this->redirect('');

            return;
        }

        $step = ($_POST['step'] ?? $_GET['step'] ?? 'email') === 'code' ? 'code' : 'email';
        $email = trim((string) ($_POST['email'] ?? ''));
        $error = null;
        $sent = false;

        if ($this->isPost() && $step === 'email') {
            if ($this->reset()->requestedTooOften()) {
                $error = at('Код уже запрашивали несколько раз. Попробуйте через час.');
            } else {
                $started = $this->reset()->start($email);

                /*
                 * Ответ одинаковый и когда почта есть, и когда её нет:
                 * иначе форма превращается в способ узнать, кто здесь заведён.
                 */
                if ($started !== null) {
                    $this->sendResetCode((string) $started['code'], (array) $started['user']);
                }

                $sent = true;
                $step = 'code';
            }
        }

        if ($this->isPost() && $step === 'code' && ($_POST['step'] ?? '') === 'code') {
            $error = $this->reset()->finish(
                $email,
                (string) ($_POST['code'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirm'] ?? '')
            );

            if ($error === null) {
                $this->flash(at('Пароль изменён — войдите с новым.'));
                $this->redirect('login');

                return;
            }
        }

        $this->render('password-reset', [
            'step'  => $step,
            'email' => $email,
            'error' => $error,
            'sent'  => $sent,
        ], 'Восстановление пароля');
    }

    /** Код восстановления уходит в телеграм — тем же путём, что и заявки. */
    private function sendResetCode(string $code, array $user): void
    {
        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $leads = new Leads($this->db, $settings, (array) ($this->config['trusted_proxies'] ?? []));

        $text = "<b>Восстановление пароля KULAGER</b>\n\n"
            . 'Кому: ' . htmlspecialchars((string) ($user['email'] ?? ''), ENT_NOQUOTES, 'UTF-8') . "\n"
            . 'Код: <code>' . $code . "</code>\n"
            . "Годен 15 минут.\n\n"
            . 'Если пароль никто не восстанавливал — просто не вводите код '
            . 'и смените его сами в разделе «Профиль».';

        $leads->send($text);
    }

    public function logout(): void
    {
        $this->auth->log('logout');
        $this->auth->logout();
        $this->redirect('login');
    }

    /** Профиль: пока только смена собственного пароля. */
    public function profile(): void
    {
        $errors = [];

        if ($this->isPost()) {
            $current = (string) ($_POST['current'] ?? '');
            $next = (string) ($_POST['password'] ?? '');

            if (!password_verify($current, (string) $this->auth->user()['password_hash'])) {
                $errors['current'] = at('Текущий пароль указан неверно.');
            }

            if (mb_strlen($next) < 10) {
                $errors['password'] = at('Новый пароль должен быть не короче 10 символов.');
            }

            if ($next !== (string) ($_POST['password_confirm'] ?? '')) {
                $errors['password_confirm'] = at('Пароли не совпадают.');
            }

            if ($errors === []) {
                $this->db->update(
                    'users',
                    ['password_hash' => password_hash($next, PASSWORD_DEFAULT)],
                    'id = :id',
                    ['id' => $this->auth->user()['id']]
                );

                $this->auth->log('password_change');
                $this->flash(at('Пароль изменён.'));
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
    public function twoFactor(): void
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
            $this->flash(at('Добавьте ключ в приложение и введите код, чтобы включить.'));
            $this->redirect('profile');

            return;
        }

        if ($action === 'enable') {
            $secret = (string) ($user['totp_secret'] ?? '');

            if ($secret === '' || !Totp::verify($secret, (string) ($_POST['code'] ?? ''))) {
                $this->flash(at('Код не подошёл — вход по коду не включён.'));
                $this->redirect('profile');

                return;
            }

            $this->db->update('users', ['totp_enabled' => 1], 'id = :id', ['id' => $id]);
            $_SESSION['totp_new_codes'] = $this->auth->makeBackupCodes($id);

            $this->auth->log('twofa_enabled');
            $this->flash(at('Вход по одноразовому коду включён. Сохраните запасные коды.'));
            $this->redirect('profile');

            return;
        }

        if ($action === 'disable') {
            // Отключение — тоже действие с последствиями, просим пароль
            if (!password_verify((string) ($_POST['current'] ?? ''), (string) $user['password_hash'])) {
                $this->flash(at('Текущий пароль указан неверно — ничего не изменилось.'));
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
            $this->flash(at('Вход по одноразовому коду отключён.'));
            $this->redirect('profile');

            return;
        }

        if ($action === 'codes') {
            $_SESSION['totp_new_codes'] = $this->auth->makeBackupCodes($id);
            $this->auth->log('twofa_codes');
            $this->flash(at('Запасные коды перевыпущены — прежние больше не действуют.'));
            $this->redirect('profile');

            return;
        }

        $this->redirect('profile');
    }

    /** Переключение языка админки — хранится у пользователя. */
    public function switchLanguage(): void
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
}
