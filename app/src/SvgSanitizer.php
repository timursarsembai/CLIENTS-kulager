<?php
declare(strict_types=1);

/**
 * Очистка SVG перед сохранением в медиатеку.
 *
 * SVG — это разметка, а не картинка: внутри бывает и скрипт, и ссылка
 * на чужой сервер. Файл лежит на нашем же домене, поэтому исполнись
 * такой скрипт — он получит доступ ко всему, что доступно вошедшему.
 *
 * Чистим по белому списку: что не перечислено — выбрасываем. Список
 * запрещённого для этого не годится: обходов у него не счесть —
 * `onmouseover`, `onbegin` у `<animate>`, пробел перед `=`, адрес,
 * записанный сущностями. Тот же подход, что и у Blocks::cleanHtml.
 */
final class SvgSanitizer
{
    /*
     * Списки заданы в каноническом написании: в SVG регистр значим, и
     * `viewbox` вместо `viewBox` браузер уже не понимает — картинка теряет
     * пропорции. Сравниваем по нижнему регистру, а в файл возвращаем то
     * написание, что перечислено здесь.
     */

    /** Теги, из которых состоит настоящая картинка. */
    private const TAGS = [
        'svg', 'g', 'defs', 'title', 'desc', 'symbol', 'use', 'path', 'rect', 'circle',
        'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'clipPath', 'mask',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'filter', 'feGaussianBlur',
        'feOffset', 'feBlend', 'feMerge', 'feMergeNode', 'feColorMatrix', 'feDropShadow',
    ];

    /** Атрибуты, за которыми не спрячешь поведение. */
    private const ATTRS = [
        'viewBox', 'xmlns', 'xmlns:xlink', 'version', 'width', 'height', 'x', 'y', 'x1', 'y1',
        'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform', 'fill',
        'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-opacity', 'opacity', 'offset',
        'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'patternUnits',
        'clip-path', 'clip-rule', 'mask', 'filter', 'id', 'class', 'font-family',
        'font-size', 'font-weight', 'text-anchor', 'dx', 'dy', 'preserveAspectRatio',
        'stdDeviation', 'result', 'in', 'in2', 'mode', 'type', 'values', 'flood-color',
        'flood-opacity', 'maskUnits', 'clipPathUnits', 'href', 'xlink:href',
    ];

    /** @return string|null очищенная разметка, либо null если это не картинка */
    public static function clean(string $svg): ?string
    {
        // Внешние сущности и подключения разбираются до всякой разметки
        if (preg_match('~<!(doctype|entity)~i', $svg) === 1) {
            return null;
        }

        // Скрипты и вставная разметка удаляются вместе с содержимым
        $svg = (string) preg_replace(
            '~<\s*(script|style|foreignobject|iframe|object|embed|animate|set|handler)\b.*?(</\s*\1\s*>|$)~is',
            '',
            $svg
        );

        // Комментарии и инструкции обработки не нужны и мешают разбору
        $svg = (string) preg_replace('~<!--.*?-->|<\?.*?\?>~s', '', $svg);

        // Ключ — нижний регистр для сравнения, значение — как писать в файл
        $tags = array_combine(array_map('strtolower', self::TAGS), self::TAGS);
        $attrs = array_combine(array_map('strtolower', self::ATTRS), self::ATTRS);

        $svg = (string) preg_replace_callback(
            '~<\s*(/?)\s*([a-z0-9:_-]+)([^>]*)>~i',
            static function (array $m) use ($tags, $attrs): string {
                $closing = $m[1];
                $key = strtolower($m[2]);

                if (!isset($tags[$key])) {
                    return '';
                }

                $tag = $tags[$key];

                if ($closing !== '') {
                    return '</' . $tag . '>';
                }

                $kept = '';

                preg_match_all(
                    '~([a-z0-9:_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')~i',
                    $m[3],
                    $found,
                    PREG_SET_ORDER
                );

                foreach ($found as $pair) {
                    $key = strtolower($pair[1]);
                    $value = $pair[3] ?? $pair[4] ?? '';

                    if (!isset($attrs[$key])) {
                        continue;
                    }

                    $name = $attrs[$key];

                    // Ссылка допустима только внутрь самого файла: #id
                    if (($key === 'href' || $key === 'xlink:href')
                        && !str_starts_with(trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')), '#')
                    ) {
                        continue;
                    }

                    /*
                     * url(#id) — это ссылка на градиент или маску внутри
                     * того же файла, без неё картинка развалится. Всё
                     * остальное в url() ведёт наружу — такое выбрасываем.
                     */
                    if (preg_match('~url\\(\\s*[\'"]?\\s*(?!#)~i', $value) === 1
                        || stripos($value, 'expression') !== false
                    ) {
                        continue;
                    }

                    $kept .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                }

                $selfClosing = str_contains($m[3], '/') && preg_match('~/\s*$~', $m[3]) === 1;

                return '<' . $tag . $kept . ($selfClosing ? ' />' : '>');
            },
            $svg
        );

        // После чистки это всё ещё должна быть картинка
        return stripos($svg, '<svg') === false ? null : $svg;
    }
}
