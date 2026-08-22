<?php
declare(strict_types=1);

/**
 * Согласие на обработку персональных данных — вместе с самой заявкой.
 *
 * Одной галочки на странице мало: доказательством служит запись о том, кто
 * и когда согласился и с каким именно текстом. Текст поэтому сохраняется
 * целиком — формулировка на сайте со временем меняется, а подтверждать
 * придётся ту, что человек видел в день обращения.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE leads ADD COLUMN consent TINYINT(1) NOT NULL DEFAULT 0 AFTER message");
    $db->run("ALTER TABLE leads ADD COLUMN consent_text VARCHAR(500) NOT NULL DEFAULT '' AFTER consent");
};
