<?php
declare(strict_types=1);

/** Экранирование для вывода в HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Экранирование для подстановки в атрибут style/аналогичные (значения из контента). */
function attr(?string $value): string
{
    return e($value);
}

/**
 * Собирает список CSS-классов, отбрасывая пустые значения.
 * Условные классы передаются как ['класс' => bool].
 */
function classes(array $list): string
{
    $out = [];
    foreach ($list as $key => $value) {
        if (is_int($key)) {
            if ($value !== null && $value !== '') {
                $out[] = (string) $value;
            }
        } elseif ($value) {
            $out[] = $key;
        }
    }

    return implode(' ', $out);
}

/**
 * Строка интерфейса админки на языке пользователя.
 * Ключ — русский текст, поэтому шаблон читается и без словаря.
 */
function at(string $text, mixed ...$args): string
{
    return $args === [] ? AdminLang::get($text) : AdminLang::format($text, ...$args);
}

/** То же, но сразу экранированное — для вывода в разметку. */
function ate(string $text, mixed ...$args): string
{
    return e(at($text, ...$args));
}

/**
 * Адрес посетителя с учётом обратного прокси.
 *
 * За прокси REMOTE_ADDR — это адрес самого прокси, один для всех: тогда
 * лимит попыток входа и лимит заявок считаются на весь сайт разом, а любой
 * посетитель может закрыть вход остальным. Настоящий адрес прокси кладёт
 * в заголовок, но заголовок подделывается кем угодно, поэтому верим ему
 * только когда запрос действительно пришёл от известного нам прокси.
 *
 * Список доверенных адресов — `trusted_proxies` в app/config.php. Пусто
 * (обычный хостинг без прокси) — берём REMOTE_ADDR и ничему не верим.
 */
function client_ip(array $trusted = []): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($trusted === [] || !in_array($remote, $trusted, true)) {
        return $remote;
    }

    // В цепочке «клиент, прокси1, прокси2» нужен самый левый — исходный
    $chain = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '');

    foreach (explode(',', $chain) as $candidate) {
        $candidate = trim($candidate);

        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }
    }

    return $remote;
}
