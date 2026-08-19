<?php
declare(strict_types=1);

/**
 * Заявки с сайта.
 *
 * Заявка сначала сохраняется, и только потом уходит в телеграм: если бот
 * недоступен или токен неверный, обращение всё равно не потеряется, а в
 * админке будет видно, что уведомление не дошло.
 *
 * Храним и служебное — адрес страницы, откуда пришли, и IP: без этого
 * не разобрать поток спама, когда он появится.
 */
return static function (Db $db): void {
    $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $db->run("CREATE TABLE leads (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL DEFAULT '',
        phone VARCHAR(64) NOT NULL DEFAULT '',
        email VARCHAR(191) NOT NULL DEFAULT '',
        message TEXT NULL,
        page VARCHAR(255) NOT NULL DEFAULT '',
        locale VARCHAR(5) NOT NULL DEFAULT '',
        status ENUM('new','in_work','done','spam') NOT NULL DEFAULT 'new',
        notified TINYINT(1) NOT NULL DEFAULT 0,
        notify_error VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        KEY status_created (status, created_at)
    ) $charset");
};
