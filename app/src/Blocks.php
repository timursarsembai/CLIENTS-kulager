<?php
declare(strict_types=1);

/**
 * Работа с реестром блоков: схемы полей, значения по умолчанию,
 * приведение присланных из формы данных к ожидаемому виду.
 *
 * Данным из браузера не доверяем: всё, чего нет в схеме, отбрасывается.
 */
final class Blocks
{
    private static ?array $registry = null;

    /** @return array<string,array> */
    public static function all(): array
    {
        if (self::$registry === null) {
            self::$registry = require APP_DIR . '/blocks.php';
        }

        return self::$registry;
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    public static function definition(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function title(string $type): string
    {
        return self::all()[$type]['title'] ?? $type;
    }

    /** Блоки, сгруппированные для библиотеки в админке. @return array<string,array<string,array>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::all() as $type => $definition) {
            $groups[$definition['group'] ?? 'Прочее'][$type] = $definition;
        }

        return $groups;
    }

    /** Заготовка нового блока: только значения по умолчанию из схемы. */
    public static function defaults(string $type): array
    {
        $definition = self::definition($type);

        return $definition === null ? [] : self::defaultsFor($definition['fields']);
    }

    /**
     * Приводит присланные данные к схеме блока.
     * Возвращает [очищенные данные, список ошибок].
     *
     * @return array{0:array,1:array<string,string>}
     */
    public static function sanitize(string $type, array $input): array
    {
        $definition = self::definition($type);

        if ($definition === null) {
            return [[], ['type' => at('Неизвестный тип блока.')]];
        }

        $errors = [];
        $data = self::sanitizeFields($definition['fields'], $input, '', $errors);

        return [$data, $errors];
    }

    /* ------------------------------------------------------------ внутреннее */

    private static function defaultsFor(array $fields): array
    {
        $out = [];

        foreach ($fields as $name => $field) {
            $type = $field['type'] ?? 'string';

            if ($type === 'group') {
                $nested = self::defaultsFor($field['fields']);
                if ($nested !== []) {
                    $out[$name] = $nested;
                }
                continue;
            }

            if ($type === 'list') {
                continue;
            }

            if (array_key_exists('default', $field)) {
                $out[$name] = $field['default'];
            }
        }

        return $out;
    }

    private static function sanitizeFields(array $fields, array $input, string $path, array &$errors): array
    {
        $out = [];

        foreach ($fields as $name => $field) {
            $key = $path === '' ? $name : $path . '.' . $name;
            $value = $input[$name] ?? null;

            $clean = self::sanitizeValue($field, $value, $key, $errors);

            if (!empty($field['required']) && self::isBlank($clean)) {
                $errors[$key] = at('Поле «%s» обязательно.', at((string) ($field['label'] ?? $name)));
            }

            // Пустые значения в хранилище не пишем: блок остаётся компактным,
            // а шаблон и так подставит своё поведение по умолчанию
            if (!self::isBlank($clean) || $clean === false || $clean === 0) {
                $out[$name] = $clean;
            }
        }

        return $out;
    }

    private static function sanitizeValue(array $field, mixed $value, string $key, array &$errors): mixed
    {
        switch ($field['type'] ?? 'string') {
            case 'bool':
                return (bool) $value;

            case 'number':
                if ($value === null || $value === '') {
                    return null;
                }

                return (int) $value;

            case 'select':
                $options = $field['options'] ?? [];
                $value = is_string($value) ? $value : '';

                return array_key_exists($value, $options) ? $value : (string) ($field['default'] ?? '');

            case 'section':
                $sections = PageRepository::sections();
                $value = is_string($value) ? $value : '';

                return isset($sections[$value]) ? $value : '';

            case 'page':
                $value = is_string($value) ? $value : '';

                /*
                 * «Другой адрес» — служебное значение выпадающего списка:
                 * оно попадёт в форму, только если не отработал скрипт,
                 * который заменяет список на поле ввода.
                 */
                return $value === '__custom__' ? '' : self::cleanUrl($value);

            case 'richtext':
                return self::cleanHtml(is_string($value) ? $value : '');

            case 'image':
                return self::cleanPath(is_string($value) ? $value : '');

            case 'text':
            case 'string':
                return self::cleanText(is_string($value) ? $value : '');

            case 'group':
                return is_array($value)
                    ? self::sanitizeFields($field['fields'], $value, $key, $errors)
                    : [];

            case 'list':
                return self::sanitizeList($field, $value, $key, $errors);
        }

        return null;
    }

    private static function sanitizeList(array $field, mixed $value, string $key, array &$errors): array
    {
        if (!is_array($value)) {
            return [];
        }

        $of = $field['of'];
        $out = [];

        foreach (array_values($value) as $index => $item) {
            // Простой список: строки или пути к картинкам
            if (is_string($of)) {
                $clean = $of === 'image'
                    ? self::cleanPath(is_string($item) ? $item : '')
                    : self::cleanText(is_string($item) ? $item : '');

                if ($clean !== '') {
                    $out[] = $clean;
                }
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            /*
             * Ошибки собираем отдельно: пустую строку списка (редактор нажал
             * «Добавить» и передумал) выбрасываем вместе с её претензиями,
             * иначе форма не сохранится из-за незаполненного лишнего элемента.
             */
            $itemErrors = [];
            $clean = self::sanitizeFields($of, $item, $key . '[' . $index . ']', $itemErrors);

            if (!self::hasContent($clean, $of)) {
                continue;
            }

            $errors += $itemErrors;
            $out[] = $clean;
        }

        if (isset($field['max'])) {
            $out = array_slice($out, 0, (int) $field['max']);
        }

        return $out;
    }

    /** Обычный текст: убираем управляющие символы и лишние пробелы по краям. */
    private static function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~u', '', $value) ?? '';

        return trim($value);
    }

    /**
     * Ограниченный HTML: только оформление текста и ссылки.
     * Всё остальное — включая скрипты и обработчики событий — вырезается.
     *
     * Применяется и при сохранении, и при выводе в админку: в базу могли
     * попасть данные в обход формы (импорт, правка через SQL).
     */
    public static function cleanHtml(string $value): string
    {
        $value = self::cleanText($value);

        // Скрипты и стили вырезаем вместе с содержимым: strip_tags убирает
        // только теги, а текст внутри оставляет прямо в тексте страницы
        $value = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $value) ?? $value;
        $value = preg_replace('~<(script|style)\b[^>]*>.*~is', '', $value) ?? $value;

        $value = strip_tags($value, '<b><strong><i><em><br><a><ul><ol><li><p>');

        // Атрибуты оставляем только у ссылок, и только href
        return preg_replace_callback(
            '~<([a-z][a-z0-9]*)\b([^>]*)>~i',
            static function (array $m): string {
                $tag = strtolower($m[1]);

                if ($tag !== 'a') {
                    return '<' . $tag . '>';
                }

                if (!preg_match('~href\s*=\s*"([^"]*)"~i', $m[2], $href)) {
                    return '<a>';
                }

                $url = self::cleanUrl(html_entity_decode($href[1], ENT_QUOTES, 'UTF-8'));

                return $url === '' ? '<a>' : '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
            },
            $value
        ) ?? '';
    }

    /**
     * Разрешаем только заведомо безопасные адреса.
     *
     * Проверять запрещённые схемы по списку недостаточно: браузер игнорирует
     * пробелы и управляющие символы внутри схемы, поэтому «jav\tascript:»
     * выполнится как javascript:. Поэтому список разрешённого, а не запрещённого.
     */
    private static function cleanUrl(string $url): string
    {
        // Убираем всё, что браузер при разборе схемы всё равно отбросит
        $probe = strtolower(preg_replace('~[\s\x00-\x20]+~', '', $url) ?? '');

        // Относительные адреса и якоря — разрешены
        if ($probe !== '' && !preg_match('~^[a-z][a-z0-9+.-]*:~', $probe)) {
            return trim($url);
        }

        if (preg_match('~^(https?|mailto|tel):~', $probe)) {
            return trim($url);
        }

        return '';
    }

    /** Путь к файлу внутри assets: без выхода вверх и без протоколов. */
    private static function cleanPath(string $value): string
    {
        $value = trim($value);

        if ($value === '' || str_contains($value, '..') || preg_match('~^[a-z]+:~i', $value)) {
            return '';
        }

        return ltrim($value, '/');
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Заполнил ли редактор хоть что-то осмысленное.
     *
     * Не считаем содержимым: снятый флажок, ноль и значения, которые поле
     * получило само (выбранный по умолчанию пункт списка). Иначе строка,
     * где редактор ничего не ввёл, никогда не будет выглядеть пустой.
     *
     * @param array<string,array> $fields описание полей строки
     */
    private static function hasContent(array $values, array $fields = []): bool
    {
        foreach ($values as $name => $value) {
            $field = $fields[$name] ?? [];

            if (array_key_exists('default', $field) && $value === $field['default']) {
                continue;
            }

            if (is_array($value)) {
                $nested = ($field['type'] ?? '') === 'group'
                    ? ($field['fields'] ?? [])
                    : (is_array($field['of'] ?? null) ? $field['of'] : []);

                if (self::hasContent($value, $nested)) {
                    return true;
                }

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                return true;
            }

            if ($value === true || (is_int($value) && $value !== 0)) {
                return true;
            }
        }

        return false;
    }
}
