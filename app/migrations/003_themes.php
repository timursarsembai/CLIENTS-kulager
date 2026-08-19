<?php
declare(strict_types=1);

/**
 * Цветовые темы переезжают в базу.
 *
 * До сих пор темы жили только в app/themes.php: чтобы поправить цвет или
 * добавить свою, приходилось лезть в код. Файл остаётся набором тем «из
 * коробки» — из него делается первое наполнение таблицы и запасной вариант,
 * если база недоступна.
 */
return static function (Db $db): void {
    $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $db->run("CREATE TABLE themes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        theme_key VARCHAR(64) NOT NULL,
        name VARCHAR(191) NOT NULL,
        swatch_bg VARCHAR(32) NOT NULL DEFAULT '',
        swatch_accent VARCHAR(32) NOT NULL DEFAULT '',
        vars_json TEXT NOT NULL,
        sort INT NOT NULL DEFAULT 0,
        is_builtin TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY theme_key (theme_key)
    ) $charset");
};
