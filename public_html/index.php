<?php
declare(strict_types=1);

/**
 * Единая точка входа. Всё, что лежит в public_html, заливается в корень сайта.
 * Каталог app/ ищем сначала рядом с public_html (предпочтительно, вне DocumentRoot),
 * затем внутри — не все shared-хостинги дают подняться выше корня сайта.
 */
$appDir = is_dir(__DIR__ . '/../app') ? __DIR__ . '/../app' : __DIR__ . '/app';

// Корень сайта: отсюда считаются пути к статике
define('PUBLIC_DIR', __DIR__);

require $appDir . '/bootstrap.php';
