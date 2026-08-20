<?php
declare(strict_types=1);

/**
 * Восстановление пароля через телеграм.
 *
 * Код из шести цифр уходит в тот же чат, куда падают заявки: почтой на
 * shared-хостинге пользоваться ненадёжно — письма уходят в спам или
 * не уходят вовсе, а телеграм у владельца сайта уже настроен и проверен.
 *
 * Само сообщение отправляет админка: этот класс только заводит код,
 * проверяет его и ставит новый пароль.
 */
final class PasswordReset
{
    /** Сколько живёт код восстановления. */
    private const RESET_TTL = 900;

    /** Сколько раз можно ошибиться в коде, прежде чем он сгорит. */
    private const RESET_TRIES = 5;

    public function __construct(private Db $db, private Auth $auth)
    {
    }

    /**
     * Заводит код восстановления и возвращает его — отправлять код наружу
     * будет вызывающий, через телеграм.
     *
     * Возвращает null, если восстановление этому человеку не положено:
     * почты нет, доступ закрыт. Наружу разница не показывается — иначе
     * форма превращается в способ узнать, кто здесь заведён.
     */
    public function start(string $email): ?array
    {
        $user = $this->db->first(
            'SELECT * FROM users WHERE email = :email',
            ['email' => mb_substr(trim($email), 0, 191)]
        );

        if ($user === null || (array_key_exists('is_active', $user) && !$user['is_active'])) {
            return null;
        }

        // Прежние коды этого человека гасим: действует только последний
        $this->db->delete('password_resets', 'user_id = :id AND used_at IS NULL', ['id' => (int) $user['id']]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->db->insert('password_resets', [
            'user_id'    => (int) $user['id'],
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'ip'         => $this->auth->clientIp(),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + self::RESET_TTL),
        ]);

        $this->auth->log('password_reset_start', (string) $user['email'], $this->auth->clientIp());
        $this->db->delete('password_resets', 'expires_at < :old', ['old' => date('Y-m-d H:i:s', time() - 86400)]);

        return ['code' => $code, 'user' => $user];
    }

    /**
     * Проверяет код и ставит новый пароль.
     *
     * @return string|null текст ошибки, либо null при успехе
     */
    public function finish(string $email, string $code, string $password, string $confirm): ?string
    {
        if (mb_strlen($password) < 10) {
            return at('Пароль должен быть не короче 10 символов.');
        }

        if ($password !== $confirm) {
            return at('Пароли не совпадают.');
        }

        $user = $this->db->first(
            'SELECT * FROM users WHERE email = :email',
            ['email' => mb_substr(trim($email), 0, 191)]
        );

        $row = $user === null ? null : $this->db->first(
            'SELECT * FROM password_resets
             WHERE user_id = :id AND used_at IS NULL AND expires_at > :now
             ORDER BY id DESC LIMIT 1',
            ['id' => (int) $user['id'], 'now' => date('Y-m-d H:i:s')]
        );

        if ($row === null) {
            return at('Код не подошёл или устарел. Запросите новый.');
        }

        // Перебор шестизначного кода: пять попыток, потом код сгорает
        if ((int) $row['attempts'] >= self::RESET_TRIES) {
            $this->db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);

            return at('Код не подошёл или устарел. Запросите новый.');
        }

        if (!password_verify(trim($code), (string) $row['code_hash'])) {
            $this->db->update(
                'password_resets',
                ['attempts' => (int) $row['attempts'] + 1],
                'id = :id',
                ['id' => (int) $row['id']]
            );

            return at('Код не подошёл или устарел. Запросите новый.');
        }

        $this->db->update('users', [
            'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
            'password_set_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $user['id']]);

        $this->db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);

        /*
         * Снимаем и блокировку по попыткам: обычно пароль восстанавливают
         * как раз после того, как заперли себя неудачными входами.
         */
        $this->db->delete(
            'login_attempts',
            'email = :email OR ip = :ip',
            ['email' => mb_substr((string) $user['email'], 0, 191), 'ip' => $this->auth->clientIp()]
        );

        $this->auth->log('password_reset_done', (string) $user['email'], $this->auth->clientIp());

        return null;
    }

    /**
     * Не слишком ли часто просят код. Считаем по адресу: иначе формой
     * можно засыпать телеграм владельца сообщениями.
     */
    public function requestedTooOften(): bool
    {
        $count = (int) $this->db->value(
            'SELECT COUNT(*) FROM password_resets WHERE ip = :ip AND created_at > :since',
            ['ip' => $this->auth->clientIp(), 'since' => date('Y-m-d H:i:s', time() - 3600)],
            0
        );

        return $count >= 5;
    }

    /** Запасные коды: десять одноразовых строк, храним только их хеши. */
}
