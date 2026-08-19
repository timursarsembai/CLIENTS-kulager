<?php
declare(strict_types=1);

/**
 * Язык интерфейса админки.
 *
 * Ключом служит сам русский текст: `at('Страницы')`. Это избавляет от таблицы
 * выдуманных идентификаторов — русский шаблон остаётся читаемым, а перевода
 * может не быть вовсе: тогда показывается ключ, то есть исходная строка.
 *
 * Язык хранится у пользователя, поэтому один редактор может работать
 * по-казахски, а другой по-русски.
 */
final class AdminLang
{
    /** Язык по умолчанию — на нём написаны сами шаблоны. */
    private const FALLBACK = 'ru';

    private static string $locale = self::FALLBACK;

    /** @var array<string,string> */
    private static array $strings = [];

    public static function use(string $locale): void
    {
        self::$locale = $locale;
        self::$strings = [];

        if ($locale === self::FALLBACK) {
            return;
        }

        $file = APP_DIR . '/lang/admin.' . $locale . '.php';

        if (is_file($file)) {
            self::$strings = (array) require $file;
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** Перевод строки. Нет перевода — возвращаем как есть. */
    public static function get(string $text): string
    {
        return self::$strings[$text] ?? $text;
    }

    /**
     * Строка с подстановкой: at('Загружено файлов: %d', 3).
     * Числа и имена внутри перевода стоят там, где нужно казахскому порядку слов.
     */
    public static function format(string $text, mixed ...$args): string
    {
        return sprintf(self::get($text), ...$args);
    }
}
