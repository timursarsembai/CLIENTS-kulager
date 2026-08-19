<?php
declare(strict_types=1);

/**
 * Двухшаговый вход: пароль плюс одноразовый код из приложения.
 *
 * - totp_secret     — ключ, общий у сервера и телефона;
 * - totp_enabled    — включён ли второй шаг (секрет заводится раньше, чем
 *                     подтверждается, поэтому это отдельный флаг);
 * - totp_backup     — запасные коды на случай потери телефона, хешированные;
 * - password_set_at — когда пароль меняли последний раз.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE users
        ADD COLUMN totp_secret VARCHAR(64) NOT NULL DEFAULT '',
        ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN totp_backup TEXT NULL,
        ADD COLUMN password_set_at DATETIME NULL");
};
