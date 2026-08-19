<?php
declare(strict_types=1);

/**
 * Подвал и боковое меню перестают быть одним и тем же списком.
 *
 * Пункт по-прежнему один — иначе отрасль пришлось бы переименовывать дважды,
 * — но у него появляется отдельное название для подвала и признак показа.
 * Пока footer_title пуст, подвал берёт общее название: ничего не меняется,
 * пока редактор сам не решит их развести.
 */
return static function (Db $db): void {
    $db->run("ALTER TABLE navigation
        ADD COLUMN footer_title VARCHAR(191) NOT NULL DEFAULT '' AFTER full_title,
        ADD COLUMN in_footer TINYINT(1) NOT NULL DEFAULT 1 AFTER in_drawer");
};
