<?php
declare(strict_types=1);

/**
 * Пользователи админки: роли, доступ, сброс пароля.
 *
 * Последнего администратора нельзя ни разжаловать, ни отключить, ни
 * удалить — иначе в админку станет некому войти.
 */
final class AdminUsers extends AdminSection
{
    /** Разбор адресов раздела пользователей. */
    public function userRoutes(array $segments): void
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
            $this->flash(at('Укажите корректный адрес почты.'));
            $this->redirect('users');

            return;
        }

        if ($this->db->first('SELECT id FROM users WHERE email = :email', ['email' => $email]) !== null) {
            $this->flash(at('Пользователь с такой почтой уже заведён.'));
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
        $this->flash(at('Пользователь заведён. Передайте пароль лично — второй раз он не покажется.'));
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
            $this->flash(at('Это единственный администратор — роль оставлена прежней.'));
            $this->redirect('users');

            return;
        }

        $this->db->update('users', ['role' => $role], 'id = :id', ['id' => $user['id']]);
        $this->auth->log('user_role', (string) $user['email'], $role);
        $this->flash(at('Роль изменена.'));
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
        $this->flash(at('Пароль сброшен. Передайте новый лично — второй раз он не покажется.'));
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
            $this->flash(at('Это единственный администратор — доступ оставлен.'));
            $this->redirect('users');

            return;
        }

        if (!$active && (int) $user['id'] === (int) ($this->auth->user()['id'] ?? 0)) {
            $this->flash(at('Себе доступ закрыть нельзя.'));
            $this->redirect('users');

            return;
        }

        $this->db->update('users', ['is_active' => $active ? 1 : 0], 'id = :id', ['id' => $user['id']]);
        $this->auth->log($active ? 'user_enable' : 'user_disable', (string) $user['email']);
        $this->flash($active ? at('Доступ открыт.') : at('Доступ закрыт.'));
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
            $this->flash(at('Вход по коду отключён — пусть настроит заново в своём профиле.'));
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
            $this->flash(at('Себя удалить нельзя.'));
            $this->redirect('users');

            return;
        }

        if ($user['role'] === 'admin' && $this->adminCount() <= 1) {
            $this->flash(at('Это единственный администратор — удалять его нельзя.'));
            $this->redirect('users');

            return;
        }

        $this->db->delete('users', 'id = :id', ['id' => $user['id']]);
        $this->auth->log('user_delete', (string) $user['email']);
        $this->flash(at('Пользователь удалён. Его записи в журнале остались.'));
        $this->redirect('users');
    }

    private function adminCount(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1", [], 0);
    }

    /**
     * Пароль, который не придётся придумывать: три группы по пять знаков.
     *
     * Раньше он собирался из словаря на шестнадцать слов — читался легко,
     * но и перебирался за четыре миллиона вариантов, а словарь лежит в этом
     * же файле на виду. Здесь набор из 32 знаков на 15 позиций: столько
     * вариантов не переберёт никто, а списать с листка всё ещё можно —
     * похожие друг на друга знаки (0, O, 1, l, I) из набора убраны.
     */
    private function newPassword(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $parts = [];

        for ($group = 0; $group < 3; $group++) {
            $chunk = '';

            for ($i = 0; $i < 5; $i++) {
                $chunk .= $alphabet[random_int(0, $max)];
            }

            $parts[] = $chunk;
        }

        return implode('-', $parts);
    }

    /* ---------------------------------------------------------------- SEO */
}
