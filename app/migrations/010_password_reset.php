<?php
declare(strict_types=1);

/**
 * Восстановление пароля через телеграм.
 *
 * Код одноразовый и живёт четверть часа. Храним не сам код, а его хеш:
 * заглянувший в базу не сможет войти чужим кодом, как не может и по хешу
 * пароля. Заявка привязана к пользователю, поэтому чужой код не подойдёт
 * даже при совпадении цифр.
 */
return static function (Db $db): void {
    $db->run(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT \'\',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
