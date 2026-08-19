<?php
declare(strict_types=1);

/**
 * Поля страницы, которые нужны только поисковикам.
 *
 * - noindex   — закрыть отдельную страницу от индексации, не снимая с публикации;
 * - canonical — свой канонический адрес, когда содержимое дублирует другую страницу.
 *
 * Общие настройки (название сайта, приписка к заголовку, коды подтверждения прав)
 * живут в таблице settings — отдельная схема им не нужна.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE page_locales
        ADD COLUMN noindex TINYINT(1) NOT NULL DEFAULT 0 AFTER og_image,
        ADD COLUMN canonical VARCHAR(255) NOT NULL DEFAULT '' AFTER noindex");
};
