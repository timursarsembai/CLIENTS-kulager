<?php
declare(strict_types=1);

/**
 * Язык интерфейса админки у каждого пользователя свой: один редактор
 * работает по-русски, другой по-казахски. Пусто — язык сайта по умолчанию.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE users ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT '' AFTER role");
};
