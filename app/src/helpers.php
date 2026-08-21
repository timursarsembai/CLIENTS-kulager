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

    if ($trusted === [] || !ip_in_list($remote, $trusted)) {
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

/**
 * Входит ли адрес в список доверенных.
 *
 * Список принимает и отдельные адреса, и диапазоны вида 172.18.0.0/16:
 * у контейнера обратного прокси адрес меняется при пересоздании, а сеть,
 * в которой он живёт, остаётся прежней.
 *
 * @param list<string> $list
 */
function ip_in_list(string $ip, array $list): bool
{
    $packed = @inet_pton($ip);

    if ($packed === false) {
        return false;
    }

    foreach ($list as $entry) {
        $entry = trim($entry);

        if ($entry === '') {
            continue;
        }

        if (!str_contains($entry, '/')) {
            if ($entry === $ip) {
                return true;
            }

            continue;
        }

        [$net, $bits] = explode('/', $entry, 2);
        $netPacked = @inet_pton(trim($net));
        $bits = (int) $bits;

        // Адреса разной длины (IPv4 против IPv6) не сравниваем
        if ($netPacked === false || strlen($netPacked) !== strlen($packed)) {
            continue;
        }

        $whole = intdiv($bits, 8);
        $rest = $bits % 8;

        if ($whole > 0 && strncmp($packed, $netPacked, $whole) !== 0) {
            continue;
        }

        if ($rest === 0) {
            return true;
        }

        // Хвост короче байта: сравниваем только старшие биты
        $mask = chr((0xFF << (8 - $rest)) & 0xFF);

        if ((($packed[$whole] ?? "\0") & $mask) === (($netPacked[$whole] ?? "\0") & $mask)) {
            return true;
        }
    }

    return false;
}
