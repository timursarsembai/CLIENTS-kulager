<?php
declare(strict_types=1);

/**
 * Управление пользователями из админки.
 *
 * is_active — временно закрыть доступ, не удаляя человека: у уволившегося
 * редактора остаются его правки в журнале, и терять эту связь незачем.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE users
        ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
        ADD COLUMN created_by INT UNSIGNED NULL AFTER created_at");
};
