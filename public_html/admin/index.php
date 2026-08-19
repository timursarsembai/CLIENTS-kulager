<?php
declare(strict_types=1);

/**
 * Точка входа админки. Отделена от публичной части: если админку
 * удалить или закрыть, сайт продолжит работать.
 */

$appDir = is_dir(__DIR__ . '/../../app') ? __DIR__ . '/../../app' : __DIR__ . '/../app';

define('APP_DIR', rtrim(str_replace('\\', '/', $appDir), '/'));
define('PUBLIC_DIR', dirname(__DIR__));

$services = require APP_DIR . '/init.php';

require APP_DIR . '/src/Admin.php';

$admin = new Admin($services);
$admin->dispatch($_SERVER['REQUEST_URI'] ?? '/');
