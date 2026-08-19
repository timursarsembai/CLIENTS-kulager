<?php
declare(strict_types=1);

/**
 * Меню переезжает в базу: до сих пор навигация правилась только в файлах
 * content/navigation.{язык}.php, а редактор должен менять её из админки.
 *
 * Таблица navigation уже создана начальной миграцией, здесь добавляются поля,
 * без которых не собрать нынешнее меню:
 * - full_title — длинное название группы отраслей (в шапке оно сокращено);
 * - in_drawer  — прятать пункт в боковом меню, не убирая из подвала.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE navigation
        ADD COLUMN full_title VARCHAR(191) NOT NULL DEFAULT '' AFTER title,
        ADD COLUMN in_drawer TINYINT(1) NOT NULL DEFAULT 1 AFTER url");
};
