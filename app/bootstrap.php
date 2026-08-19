<?php
declare(strict_types=1);

/**
 * Точка запуска публичной части. Вызывается из public_html/index.php,
 * переменная $appDir приходит оттуда.
 */

if (!isset($appDir)) {
    $appDir = __DIR__;
}

if (!defined('APP_DIR')) {
    define('APP_DIR', rtrim(str_replace('\\', '/', $appDir), '/'));
}

$services = require APP_DIR . '/init.php';

/*
 * Политика безопасности. Домены счётчиков берутся из настроек: без них
 * браузер заблокирует скрипт Метрики или Google, и счётчик не заработает.
 */
if (!headers_sent()) {
    $scriptHosts = implode(' ', $services['counters']->hosts());

    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' " . $scriptHosts . '; '
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: " . $scriptHosts . '; '
        . "connect-src 'self' " . $scriptHosts . '; '
        . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
    );
}

$cache = $services['cache'];
$key = ($_SERVER['REQUEST_URI'] ?? '/');

// Готовая страница из кэша: ни базы, ни сборки блоков
$cached = $cache->get($key);

if ($cached !== null) {
    echo $cached;

    return;
}

$router = new Router($services['site'], $services['pages'], $services['leads']);

ob_start();
$router->dispatch($_SERVER['REQUEST_URI'] ?? '/');
$html = (string) ob_get_clean();

echo $html;

// В кэш кладём только успешные ответы: 404 и редиректы запоминать нельзя
if (http_response_code() === 200) {
    $cache->put($key, $html);
}
