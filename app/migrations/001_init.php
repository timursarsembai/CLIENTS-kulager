<?php
declare(strict_types=1);

/**
 * Начальная схема CMS.
 *
 * Заметки по решениям:
 * - JSON храним в TEXT: версия MariaDB на хостинге заранее не известна,
 *   а выборки внутри JSON нам не нужны.
 * - Контент разделён по языкам на уровне строк, а не колонок: набор блоков
 *   у русской и казахской версии может отличаться.
 * - VARCHAR(191) для ключей — чтобы индексы влезали в utf8mb4 на старых MySQL.
 */
return static function (Db $db): void {
    $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $db->run("CREATE TABLE users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL,
        name VARCHAR(191) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
        created_at DATETIME NOT NULL,
        last_login_at DATETIME NULL,
        UNIQUE KEY email (email)
    ) $charset");

    /* Страница: общая часть, не зависящая от языка */
    $db->run("CREATE TABLE pages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_key VARCHAR(191) NOT NULL,
        template VARCHAR(64) NOT NULL DEFAULT 'home',
        section VARCHAR(64) NOT NULL DEFAULT '',
        sort INT NOT NULL DEFAULT 0,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY page_key (page_key),
        KEY section_sort (section, sort)
    ) $charset");

    /* Языковая версия страницы: адрес, мета-теги, признак публикации */
    $db->run("CREATE TABLE page_locales (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_id INT UNSIGNED NOT NULL,
        locale VARCHAR(5) NOT NULL,
        slug VARCHAR(191) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NULL,
        og_image VARCHAR(255) NOT NULL DEFAULT '',
        bar_json TEXT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        published_at DATETIME NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY page_locale (page_id, locale),
        UNIQUE KEY locale_slug (locale, slug),
        CONSTRAINT fk_page_locales_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
    ) $charset");

    /* Блоки страницы. data_json — поля блока, структура задана реестром блоков */
    $db->run("CREATE TABLE page_blocks (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_id INT UNSIGNED NOT NULL,
        locale VARCHAR(5) NOT NULL,
        sort INT NOT NULL DEFAULT 0,
        type VARCHAR(64) NOT NULL,
        data_json MEDIUMTEXT NULL,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL,
        KEY page_locale_sort (page_id, locale, sort),
        CONSTRAINT fk_page_blocks_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
    ) $charset");

    /* Снимок языковой версии целиком — для отката к предыдущей публикации */
    $db->run("CREATE TABLE page_revisions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_id INT UNSIGNED NOT NULL,
        locale VARCHAR(5) NOT NULL,
        author_id INT UNSIGNED NULL,
        snapshot_json MEDIUMTEXT NOT NULL,
        comment VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        KEY page_locale_created (page_id, locale, created_at),
        CONSTRAINT fk_page_revisions_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
    ) $charset");

    $db->run("CREATE TABLE media (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        path VARCHAR(255) NOT NULL,
        mime VARCHAR(100) NOT NULL DEFAULT '',
        width INT UNSIGNED NOT NULL DEFAULT 0,
        height INT UNSIGNED NOT NULL DEFAULT 0,
        size INT UNSIGNED NOT NULL DEFAULT 0,
        alt_ru VARCHAR(255) NOT NULL DEFAULT '',
        alt_kk VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        UNIQUE KEY path (path)
    ) $charset");

    $db->run("CREATE TABLE navigation (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        menu_key VARCHAR(64) NOT NULL,
        locale VARCHAR(5) NOT NULL,
        parent_id INT UNSIGNED NULL,
        sort INT NOT NULL DEFAULT 0,
        title VARCHAR(191) NOT NULL,
        url VARCHAR(255) NOT NULL DEFAULT '',
        page_id INT UNSIGNED NULL,
        KEY menu_locale_sort (menu_key, locale, sort)
    ) $charset");

    $db->run("CREATE TABLE settings (
        setting_key VARCHAR(191) NOT NULL,
        locale VARCHAR(5) NOT NULL DEFAULT '',
        value_json MEDIUMTEXT NULL,
        PRIMARY KEY (setting_key, locale)
    ) $charset");

    /* Защита от подбора пароля: считаем неудачные попытки по адресу */
    $db->run("CREATE TABLE login_attempts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        email VARCHAR(191) NOT NULL DEFAULT '',
        attempted_at DATETIME NOT NULL,
        KEY ip_time (ip, attempted_at)
    ) $charset");

    /* Кто что опубликовал — чтобы разбирать спорные правки */
    $db->run("CREATE TABLE activity_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(64) NOT NULL,
        target VARCHAR(191) NOT NULL DEFAULT '',
        details TEXT NULL,
        created_at DATETIME NOT NULL,
        KEY created (created_at)
    ) $charset");
};
