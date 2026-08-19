<?php
declare(strict_types=1);

/**
 * Строит форму редактирования блока по его описанию из реестра.
 *
 * Имена полей повторяют структуру данных: data[kicker],
 * data[items][0][title], data[offer][price]. PHP соберёт их обратно
 * в массив, а Blocks::sanitize() отбросит всё, чего нет в схеме.
 *
 * Повторяющиеся группы (list) редактируются на клиенте: в разметку
 * кладётся <template> с индексом-заполнителем, JS клонирует его.
 */
final class FormBuilder
{
    private const INDEX_PLACEHOLDER = '__INDEX__';

    /** Каталог страниц для полей-ссылок. @var list<array> */
    private static array $pages = [];

    /**
     * Список страниц, из которого выбирают ссылку.
     * Заполняется админкой перед выводом формы; без него поле «страница»
     * работает как обычная строка — сайт от этого не ломается.
     */
    public static function usePages(array $catalog): void
    {
        self::$pages = $catalog;
    }

    /** @param array<string,array> $fields */
    public static function fields(array $fields, array $values, string $prefix = 'data'): string
    {
        $html = '';

        foreach ($fields as $name => $field) {
            $html .= self::field($field, $prefix . '[' . $name . ']', $values[$name] ?? null);
        }

        return $html;
    }

    private static function field(array $field, string $name, mixed $value): string
    {
        return match ($field['type'] ?? 'string') {
            'group' => self::group($field, $name, is_array($value) ? $value : []),
            'list'  => self::list($field, $name, is_array($value) ? $value : []),
            default => self::input($field, $name, $value),
        };
    }

    /* ------------------------------------------------------- простые поля */

    private static function input(array $field, string $name, mixed $value): string
    {
        $label = (string) ($field['label'] ?? '');
        $hint = isset($field['hint']) ? '<span class="field__hint">' . e((string) $field['hint']) . '</span>' : '';
        $required = !empty($field['required']) ? ' required' : '';
        $type = $field['type'] ?? 'string';

        // Переключатель ставим перед подписью — так его смысл читается сразу
        if ($type === 'bool') {
            return '<label class="field field--check">'
                . '<input type="hidden" name="' . e($name) . '" value="0">'
                . '<input type="checkbox" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . '>'
                . '<span class="field__label">' . e($label) . '</span>'
                . $hint
                . '</label>';
        }

        $control = match ($type) {
            'text' => '<textarea name="' . e($name) . '" rows="' . (int) ($field['rows'] ?? 3) . '"'
                . $required . '>' . e((string) $value) . '</textarea>',

            'richtext' => self::richtext($name, (string) $value),

            'number' => '<input type="number" name="' . e($name) . '" value="'
                . e($value === null || $value === '' ? '' : (string) $value) . '"' . $required . '>',

            'select' => self::select($field, $name, (string) $value),

            'page' => self::page($field, $name, (string) $value),

            'section' => self::section($name, (string) $value),

            'image' => self::image($name, (string) $value),

            default => '<input type="text" name="' . e($name) . '" value="' . e((string) $value) . '"' . $required . '>',
        };

        return '<label class="field">'
            . '<span class="field__label">' . e($label) . ($required !== '' ? ' <i>обязательно</i>' : '') . '</span>'
            . $control
            . $hint
            . '</label>';
    }

    private static function select(array $field, string $name, string $value): string
    {
        $html = '<select name="' . e($name) . '">';

        foreach ($field['options'] ?? [] as $key => $title) {
            $selected = (string) $key === $value ? ' selected' : '';
            $html .= '<option value="' . e((string) $key) . '"' . $selected . '>' . e((string) $title) . '</option>';
        }

        return $html . '</select>';
    }

    /**
     * Раздел страниц, из которого блок берёт ссылки.
     *
     * Список берётся из справочника `app/sections.php`, поэтому новый раздел
     * появляется в блоках сам. Пустое значение — «все страницы».
     */
    private static function section(string $name, string $value): string
    {
        $html = '<select name="' . e($name) . '" data-page-source>'
            . '<option value="">Все страницы</option>';

        foreach (PageRepository::sections() as $code => $title) {
            if ($code === '') {
                continue;
            }

            $html .= '<option value="' . e($code) . '"' . ($code === $value ? ' selected' : '') . '>'
                . e($title) . '</option>';
        }

        return $html . '</select>';
    }

    /**
     * Ссылка на страницу сайта: выпадающий список вместо ручного ввода адреса.
     *
     * Страницы всех разделов выводятся сразу, у каждой проставлен свой раздел —
     * выбор категории в блоке скрывает лишние на клиенте, без запросов к серверу.
     * Адрес, которого нет в каталоге (якорь, внешняя ссылка, страница из файла),
     * не теряется: для него включается пункт «Другой адрес» с обычным полем.
     */
    private static function page(array $field, string $name, string $value): string
    {
        if (self::$pages === []) {
            return '<input type="text" name="' . e($name) . '" value="' . e($value) . '">';
        }

        $sections = PageRepository::sections();
        $known = false;
        $grouped = [];

        foreach (self::$pages as $page) {
            $grouped[$page['section']][] = $page;
            $known = $known || $page['url'] === $value;
        }

        // Пустой адрес — это «карточка без ссылки», а не «другой адрес»
        $custom = $value !== '' && !$known;

        $html = '<span class="page-field" data-page-field>'
            . '<select data-page-select' . ($custom ? '' : ' name="' . e($name) . '"') . '>'
            . '<option value="">— без ссылки —</option>';

        foreach ($grouped as $section => $pages) {
            $html .= '<optgroup label="' . e($sections[$section] ?? $section) . '">';

            foreach ($pages as $page) {
                $selected = !$custom && $page['url'] === $value ? ' selected' : '';
                $title = $page['title'] . ($page['published'] ? '' : ' (черновик)');

                $html .= '<option value="' . e($page['url']) . '" data-section="' . e($section) . '"'
                    . $selected . '>' . e($title) . ' — /' . e($page['url']) . '</option>';
            }

            $html .= '</optgroup>';
        }

        $html .= '<option value="__custom__" data-page-custom' . ($custom ? ' selected' : '') . '>'
            . 'Другой адрес…</option>'
            . '</select>'
            /*
             * Имя поля всегда стоит ровно на одном из двух элементов — на том,
             * который сейчас редактируют. Скрипт переносит его при переключении,
             * а пересчёт индексов в списках правит имя там же, где оно есть.
             */
            . '<input type="text" class="page-field__custom" data-page-input'
            . ($custom ? ' name="' . e($name) . '"' : '')
            . ' value="' . e($custom ? $value : '') . '"'
            . ' placeholder="#anchor или https://…"' . ($custom ? '' : ' hidden') . '>'
            . '</span>';

        return $html;
    }

    /**
     * Поле с ограниченным оформлением. Редактируется как обычный текст,
     * значение уходит в скрытое поле; лишние теги всё равно срежет сервер.
     */
    private static function richtext(string $name, string $value): string
    {
        // Значение вставляется в разметку как HTML, поэтому чистим и на выводе:
        // в базу могло попасть что угодно в обход формы
        $value = Blocks::cleanHtml($value);

        return '<div class="richtext" data-richtext>'
            . '<div class="richtext__tools">'
            . '<button type="button" data-cmd="bold" title="Жирный"><b>Ж</b></button>'
            . '<button type="button" data-cmd="italic" title="Курсив"><i>К</i></button>'
            . '<button type="button" data-cmd="insertUnorderedList" title="Список">•</button>'
            . '<button type="button" data-cmd="createLink" title="Ссылка">🔗</button>'
            . '<button type="button" data-cmd="removeFormat" title="Убрать оформление">✕</button>'
            . '</div>'
            . '<div class="richtext__area" contenteditable="true" data-richtext-area>' . $value . '</div>'
            . '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '" data-richtext-value>'
            . '</div>';
    }

    /** Поле выбора картинки для форм вне реестра блоков — например og:image страницы. */
    public static function imageField(string $name, string $value): string
    {
        return self::image($name, $value);
    }

    /** Поле выбора страницы для форм вне реестра блоков — например пункта меню. */
    public static function pageField(string $name, string $value): string
    {
        return self::page([], $name, $value);
    }

    /** Выбор картинки: путь, кнопка выбора из медиатеки и предпросмотр. */
    private static function image(string $name, string $value): string
    {
        return '<span class="image-field" data-image-field>'
            . '<input type="text" name="' . e($name) . '" value="' . e($value) . '" data-image-input'
            . ' placeholder="uploads/foto.jpg">'
            . '<button type="button" class="btn btn--small" data-image-pick>Выбрать</button>'
            . '<span class="image-field__preview">'
            . ($value !== '' ? '<img src="/assets/' . e($value) . '" alt="" data-image-preview>' : '<img alt="" hidden data-image-preview>')
            . '</span>'
            . '</span>';
    }

    /* ------------------------------------------------------ вложенные поля */

    private static function group(array $field, string $name, array $values): string
    {
        return '<fieldset class="group">'
            . '<legend>' . e((string) ($field['label'] ?? '')) . '</legend>'
            . (isset($field['hint']) ? '<p class="group__hint">' . e((string) $field['hint']) . '</p>' : '')
            . self::fieldsRaw($field['fields'], $values, $name)
            . '</fieldset>';
    }

    private static function list(array $field, string $name, array $values): string
    {
        $of = $field['of'];
        $label = (string) ($field['label'] ?? '');
        $max = isset($field['max']) ? ' data-list-max="' . (int) $field['max'] . '"' : '';

        $items = '';
        foreach (array_values($values) as $index => $item) {
            $items .= self::listItem($of, $name, (string) $index, $item);
        }

        return '<div class="list-field" data-list' . $max . '>'
            . '<div class="list-field__head">'
            . '<span class="field__label">' . e($label) . '</span>'
            . (isset($field['hint']) ? '<span class="field__hint">' . e((string) $field['hint']) . '</span>' : '')
            . '</div>'
            . '<div class="list-field__items" data-list-items>' . $items . '</div>'
            . '<template data-list-template>' . self::listItem($of, $name, self::INDEX_PLACEHOLDER, null) . '</template>'
            . '<button type="button" class="btn btn--small" data-list-add>Добавить</button>'
            . '</div>';
    }

    private static function listItem(array|string $of, string $name, string $index, mixed $value): string
    {
        $itemName = $name . '[' . $index . ']';

        $body = is_string($of)
            ? self::input(
                ['type' => $of === 'image' ? 'image' : 'string', 'label' => ''],
                $itemName,
                is_string($value) ? $value : ''
            )
            : self::fieldsRaw($of, is_array($value) ? $value : [], $itemName);

        return '<div class="list-item" data-list-item>'
            . '<div class="list-item__handle" data-list-handle title="Перетащите, чтобы поменять порядок">⋮⋮</div>'
            . '<div class="list-item__body">' . $body . '</div>'
            . '<button type="button" class="list-item__remove" data-list-remove title="Удалить">✕</button>'
            . '</div>';
    }

    /** @param array<string,array> $fields */
    private static function fieldsRaw(array $fields, array $values, string $prefix): string
    {
        $html = '';

        foreach ($fields as $name => $field) {
            $html .= self::field($field, $prefix . '[' . $name . ']', $values[$name] ?? null);
        }

        return $html;
    }
}
