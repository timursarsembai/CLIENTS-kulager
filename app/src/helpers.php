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
