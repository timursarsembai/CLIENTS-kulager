<?php
declare(strict_types=1);

/**
 * Адрес страницы из заголовка: кириллица в латиницу, пробелы в дефисы.
 *
 * Заголовки пишутся по-русски и по-казахски, а адрес должен читаться
 * и в браузере, и в выдаче поисковика. Правила транслитерации те же, что
 * в остальном проекте, поэтому таблица здесь одна на всех.
 *
 * Слэш сохраняется: адреса вложенных страниц (`otrasli/teplicy`) — обычное
 * дело, и разбивать их на части ради транслитерации незачем.
 */
final class Slug
{
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

        // Казахские буквы: передаём так же, как принято в адресах на сайте
        'ә' => 'a', 'ғ' => 'g', 'қ' => 'q', 'ң' => 'n', 'ө' => 'o', 'ұ' => 'u',
        'ү' => 'u', 'һ' => 'h', 'і' => 'i',
    ];

    /** Приводит строку к виду, пригодному для адреса. */
    public static function make(string $text, bool $keepSlashes = true): string
    {
        $text = strtr(mb_strtolower(trim($text)), self::TRANSLIT);

        $allowed = $keepSlashes ? '~[^a-z0-9/]+~' : '~[^a-z0-9]+~';
        $text = preg_replace($allowed, '-', $text) ?? '';

        // Схлопываем повторы и убираем мусор по краям каждой части адреса
        $parts = array_filter(
            array_map(
                static fn (string $part): string => trim(preg_replace('~-+~', '-', $part) ?? '', '-'),
                explode('/', $text)
            ),
            static fn (string $part): bool => $part !== ''
        );

        return implode('/', $parts);
    }

    /**
     * Адрес для страницы, у которой он ещё не задан.
     *
     * Вложенность сохраняем: у отрасли адрес начинается с `otrasli/`, и при
     * переименовании заголовка страница не должна уезжать в корень сайта.
     */
    public static function fromTitle(string $title, string $currentSlug = ''): string
    {
        $slug = self::make($title, false);

        if ($slug === '') {
            return $currentSlug;
        }

        $prefix = '';

        if (str_contains($currentSlug, '/')) {
            $prefix = substr($currentSlug, 0, (int) strrpos($currentSlug, '/') + 1);
        }

        return $prefix . $slug;
    }
}
