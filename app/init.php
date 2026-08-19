<?php
declare(strict_types=1);

/**
 * Общая инициализация для публичной части и админки.
 * Возвращает контейнер сервисов; ничего не выводит и не маршрутизирует.
 *
 * Ожидает, что вызывающий уже определил APP_DIR.
 */

$config = require APP_DIR . '/config.php';

// Ошибки всегда пишем в журнал; на экран выводим только в режиме отладки.
// Дублируем настройку php.ini — на хостинге свой php.ini нам недоступен.
ini_set('log_errors', '1');
if (is_dir(APP_DIR . '/logs')) {
    ini_set('error_log', APP_DIR . '/logs/php-error.log');
}

if (!empty($config['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

mb_internal_encoding('UTF-8');

/*
 * Корень сайта. Точки входа задают PUBLIC_DIR сами; при запуске из командной
 * строки его нет, поэтому ищем каталог со статикой рядом с приложением —
 * имя у него на разных хостингах разное.
 */
if (!defined('PUBLIC_DIR')) {
    $publicDir = dirname(APP_DIR) . '/public_html';

    foreach (['public_html', 'html', 'www', 'public'] as $candidate) {
        if (is_dir(dirname(APP_DIR) . '/' . $candidate . '/assets')) {
            $publicDir = dirname(APP_DIR) . '/' . $candidate;
            break;
        }
    }

    define('PUBLIC_DIR', $publicDir);
}

require APP_DIR . '/src/helpers.php';
require APP_DIR . '/src/Db.php';
require APP_DIR . '/src/Migrator.php';
require APP_DIR . '/src/Auth.php';
require APP_DIR . '/src/Csrf.php';
require APP_DIR . '/src/Site.php';
require APP_DIR . '/src/View.php';
require APP_DIR . '/src/Blocks.php';
require APP_DIR . '/src/FormBuilder.php';
require APP_DIR . '/src/MediaLibrary.php';
require APP_DIR . '/src/Settings.php';
require APP_DIR . '/src/PageRepository.php';
require APP_DIR . '/src/NavigationRepository.php';
require APP_DIR . '/src/PageCache.php';
require APP_DIR . '/src/ThemeRepository.php';
require APP_DIR . '/src/Slug.php';
require APP_DIR . '/src/Totp.php';
require APP_DIR . '/src/AdminLang.php';
require APP_DIR . '/src/Leads.php';
require APP_DIR . '/src/Counters.php';
require APP_DIR . '/src/Router.php';

$db = new Db($config['db'] ?? []);
$settings = new Settings($db, $config['contacts'] ?? []);
$navigation = new NavigationRepository($db);
$themes = new ThemeRepository($db, $settings);
$counters = new Counters($settings);
$site = new Site($config, $settings, $navigation, $themes, $counters);
$leads = new Leads($db, $settings);
$pages = new PageRepository($db, $config);

// Кэш готовых страниц: при включённой отладке отключён, иначе правки
// не видно до сброса
$cache = new PageCache(
    $settings,
    (string) ($config['cache_dir'] ?? APP_DIR . '/cache'),
    empty($config['debug'])
);

return [
    'config'     => $config,
    'db'         => $db,
    'site'       => $site,
    'pages'      => $pages,
    'settings'   => $settings,
    'navigation' => $navigation,
    'cache'      => $cache,
    'themes'     => $themes,
    'leads'      => $leads,
    'counters'   => $counters,
];
