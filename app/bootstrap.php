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
/*
 * Разовый номер для встроенных скриптов. Свой на каждый запрос, поэтому
 * в разметке стоит метка Site::NONCE_MARK, а подстановка идёт перед самой
 * отдачей — и для собранной страницы, и для взятой из кэша.
 */
$nonce = base64_encode(random_bytes(12));

if (!headers_sent()) {
    $scriptHosts = implode(' ', $services['counters']->hosts());

    header(
        "Content-Security-Policy: default-src 'self'; "
        /*
         * Скрипты — только свои файлы, помеченные разовым номером вставки
         * и домены счётчиков. Без номера чужой скрипт, пробравшийся в текст
         * блока, браузер выполнять откажется.
         */
        . "script-src 'self' 'nonce-" . $nonce . "' " . $scriptHosts . '; '
        /*
         * Со стилями иначе: вёрстка сайта пользуется атрибутом style в сотне
         * мест (ширина колонки, доля показателя, фоновая картинка блока),
         * а номер на атрибуты не распространяется. Оставляем как есть:
         * подмена стиля не даёт выполнить код, а значения тем чистятся
         * при сохранении.
         */
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: " . $scriptHosts . '; '
        . "connect-src 'self' " . $scriptHosts . '; '
        . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
    );
}

$cache = $services['cache'];

/*
 * В ключ кэша входит и домен: страница содержит абсолютные адреса —
 * canonical, hreflang, og:image. Без домена в ключе страница, собранная
 * при обращении по одному адресу, отдавалась бы по другому вместе
 * с чужими ссылками внутри.
 */
$key = ($_SERVER['HTTP_HOST'] ?? '') . '|' . ($_SERVER['REQUEST_URI'] ?? '/');

// Готовая страница из кэша: ни базы, ни сборки блоков
$cached = $cache->get($key);

if ($cached !== null) {
    echo str_replace(Site::NONCE_MARK, $nonce, $cached);

    return;
}

$router = new Router($services['site'], $services['pages'], $services['leads']);

ob_start();
$router->dispatch($_SERVER['REQUEST_URI'] ?? '/');
$html = (string) ob_get_clean();

// В кэш идёт разметка с меткой, посетителю — с подставленным номером
echo str_replace(Site::NONCE_MARK, $nonce, $html);

// В кэш кладём только успешные ответы: 404 и редиректы запоминать нельзя
if (http_response_code() === 200) {
    $cache->put($key, $html);
}
