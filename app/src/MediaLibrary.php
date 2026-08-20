<?php
declare(strict_types=1);

/**
 * Медиатека: загрузка изображений через админку.
 *
 * Что здесь важно с точки зрения безопасности:
 * - тип файла определяется по содержимому (finfo и getimagesize), а не по
 *   расширению — присланное имя вообще не участвует в решении;
 * - имя файла собирается заново из безопасных символов;
 * - каталог загрузок закрыт от выполнения кода своим .htaccess.
 *
 * Изображение пересохраняется через GD: это заодно срезает всё лишнее,
 * что могло быть спрятано внутри файла.
 */
final class MediaLibrary
{
    /** Форматы, которые принимаем: тип из finfo => расширение */
    private const ALLOWED = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];

    /** Ширины, под которые делаем копии для srcset */
    private const WIDTHS = [480, 960, 1600];

    private Db $db;
    private string $dir;

    public function __construct(Db $db, ?string $dir = null)
    {
        $this->db = $db;

        // PUBLIC_DIR определяют точки входа; при вызове из командной строки
        // её нет, поэтому держим запасной путь рядом с каталогом приложения
        $this->dir = $dir ?? (defined('PUBLIC_DIR') ? PUBLIC_DIR : dirname(APP_DIR) . '/public_html')
            . '/assets/uploads';
    }

    public function isWritable(): bool
    {
        return is_dir($this->dir) ? is_writable($this->dir) : is_writable(dirname($this->dir));
    }

    /** Есть ли обработка изображений. Без неё работаем, но без уменьшенных копий. */
    public function hasGd(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
    }

    /** @return list<array> файлы в порядке от новых к старым */
    public function all(int $limit = 200): array
    {
        return $this->db->all('SELECT * FROM media ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM media WHERE id = :id', ['id' => $id]);
    }

    /**
     * Принимает файл из $_FILES.
     *
     * @return array{0:?array,1:?string} запись медиатеки либо текст ошибки
     */
    public function upload(array $file): array
    {
        $error = $this->checkUploadError($file);

        if ($error !== null) {
            return [null, $error];
        }

        $tmp = (string) $file['tmp_name'];

        if (!is_uploaded_file($tmp)) {
            return [null, at('Файл получен не через форму загрузки.')];
        }

        $mime = $this->detectMime($tmp);

        if ($mime === null) {
            return [null, at('Такой тип файла не поддерживается. Нужен JPEG, PNG, WebP или SVG.')];
        }

        if (!$this->ensureDir()) {
            return [null, at('Каталог assets/uploads недоступен для записи.')];
        }

        $name = $this->uniqueName((string) $file['name'], self::ALLOWED[$mime]);
        $path = $this->dir . '/' . $name;

        // SVG не пропускаем через GD — он не растровый; вместо этого чистим разметку
        if ($mime === 'image/svg+xml') {
            $svg = $this->cleanSvg((string) file_get_contents($tmp));

            if ($svg === null) {
                return [null, at('В SVG нашлись скрипты или внешние ссылки — файл не принят.')];
            }

            file_put_contents($path, $svg);
            [$width, $height] = $this->svgSize($svg);
        } else {
            $size = getimagesize($tmp);

            if ($size === false) {
                return [null, at('Файл не похож на изображение.')];
            }

            [$width, $height] = $size;

            /*
             * Без GD пересобрать картинку нечем — сохраняем как есть.
             * Хостинг без GD встречается, и загрузка на нём должна работать,
             * пусть и без уменьшенных копий.
             */
            if (!$this->hasGd()) {
                if (!move_uploaded_file($tmp, $path) && !copy($tmp, $path)) {
                    return [null, at('Не удалось сохранить файл.')];
                }
            } else {
                if (!$this->rewrite($tmp, $path, $mime)) {
                    return [null, at('Не удалось обработать изображение.')];
                }

                $this->makeVariants($path, $mime, $width);
            }
        }

        @chmod($path, 0644);

        $id = $this->db->insert('media', [
            'path'       => 'uploads/' . $name,
            'mime'       => $mime,
            'width'      => (int) $width,
            'height'     => (int) $height,
            'size'       => (int) filesize($path),
            'alt_ru'     => '',
            'alt_kk'     => '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [$this->find($id), null];
    }

    public function updateAlt(int $id, string $altRu, string $altKk): void
    {
        $this->db->update('media', [
            'alt_ru' => mb_substr($altRu, 0, 255),
            'alt_kk' => mb_substr($altKk, 0, 255),
        ], 'id = :id', ['id' => $id]);
    }

    /** Удаляет файл вместе с производными размерами. */
    public function delete(int $id): void
    {
        $item = $this->find($id);

        if ($item === null) {
            return;
        }

        $name = basename((string) $item['path']);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        /*
         * Удаляем сам файл и ровно те копии, которые сделали сами:
         * имя-480.jpg, имя-960.jpg, имя-1600.jpg. Раньше сюда попадало
         * всё, что начинается так же, и `logo.svg` уносил с собой
         * `logo-2.png` — постороннюю картинку с похожим именем.
         */
        $files = [$this->dir . '/' . $name];

        foreach (self::WIDTHS as $width) {
            $files[] = $this->dir . '/' . $base . '-' . $width . ($ext === '' ? '' : '.' . $ext);
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->db->delete('media', 'id = :id', ['id' => $id]);
    }

    /* ------------------------------------------------------------ служебное */

    private function checkUploadError(array $file): ?string
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        return match ($code) {
            UPLOAD_ERR_OK        => null,
            UPLOAD_ERR_NO_FILE   => at('Файл не выбран.'),
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => at(
                'Файл больше, чем разрешает хостинг (%s). Уменьшите размер и попробуйте снова.',
                (string) ini_get('upload_max_filesize')
            ),
            UPLOAD_ERR_PARTIAL   => at('Файл передан не полностью.'),
            default              => at('Не удалось загрузить файл (код %d).', $code),
        };
    }

    /** Тип определяем по содержимому: расширению из браузера не доверяем. */
    private function detectMime(string $tmp): ?string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);

        // finfo отдаёт SVG как обычный XML или текст — проверяем содержимое
        if (in_array($mime, ['image/svg', 'text/xml', 'application/xml', 'text/plain'], true)) {
            $head = (string) file_get_contents($tmp, false, null, 0, 1024);
            $mime = str_contains($head, '<svg') ? 'image/svg+xml' : $mime;
        }

        return isset(self::ALLOWED[$mime]) ? $mime : null;
    }

    private function ensureDir(): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            return false;
        }

        return is_writable($this->dir);
    }

    /** Имя собираем заново: от исходного берём только читаемую основу. */
    private function uniqueName(string $original, string $extension): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = $this->transliterate($base);
        $base = strtolower(preg_replace('~[^a-zA-Z0-9]+~', '-', $base) ?? '');
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'image';
        }

        $base = substr($base, 0, 60);
        $name = $base . '.' . $extension;
        $counter = 2;

        /*
         * Проверяем и диск, и базу: они могут разойтись, если файлы чистили
         * вручную. Совпадение только по одному источнику приводило к отказу
         * записи по уникальному индексу.
         */
        while (file_exists($this->dir . '/' . $name) || $this->pathTaken('uploads/' . $name)) {
            $name = $base . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $name;
    }

    private function pathTaken(string $path): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM media WHERE path = :path',
            ['path' => $path],
            0
        ) > 0;
    }

    private function transliterate(string $value): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'ә'=>'a','ғ'=>'g','қ'=>'q','ң'=>'n','ө'=>'o','ұ'=>'u','ү'=>'u','һ'=>'h','і'=>'i',
        ];

        return strtr(mb_strtolower($value), $map);
    }

    /** Пересохраняет картинку средствами GD, отбрасывая всё постороннее. */
    private function rewrite(string $tmp, string $path, string $mime): bool
    {
        $image = $this->read($tmp, $mime);

        if ($image === null) {
            return false;
        }

        $ok = $this->write($image, $path, $mime);
        imagedestroy($image);

        return $ok;
    }

    /** Готовит уменьшенные копии для srcset — только меньше оригинала. */
    private function makeVariants(string $path, string $mime, int $width): void
    {
        if (!$this->hasGd()) {
            return;
        }

        $image = $this->read($path, $mime);

        if ($image === null) {
            return;
        }

        $height = imagesy($image);
        $info = pathinfo($path);

        foreach (self::WIDTHS as $target) {
            if ($target >= $width) {
                continue;
            }

            $targetHeight = (int) round($height * $target / $width);
            $copy = imagecreatetruecolor($target, $targetHeight);

            // Прозрачность PNG и WebP не должна превратиться в чёрный фон
            imagealphablending($copy, false);
            imagesavealpha($copy, true);

            imagecopyresampled($copy, $image, 0, 0, 0, 0, $target, $targetHeight, $width, $height);

            $this->write($copy, $info['dirname'] . '/' . $info['filename'] . '-' . $target . '.' . $info['extension'], $mime);
            imagedestroy($copy);
        }

        imagedestroy($image);
    }

    private function read(string $file, string $mime): ?GdImage
    {
        // Форматы бывают собраны в GD выборочно: webp может отсутствовать
        $reader = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            default      => null,
        };

        if ($reader === null || !function_exists($reader)) {
            return null;
        }

        $image = @$reader($file);

        return $image === false ? null : $image;
    }

    private function write(GdImage $image, string $path, string $mime): bool
    {
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $writer = match ($mime) {
            'image/jpeg' => 'imagejpeg',
            'image/png'  => 'imagepng',
            'image/webp' => 'imagewebp',
            default      => null,
        };

        if ($writer === null || !function_exists($writer)) {
            return false;
        }

        return $writer === 'imagepng'
            ? imagepng($image, $path, 8)
            : $writer($image, $path, 86);
    }

    /*
     * SVG — это разметка, а не картинка: внутри бывает и скрипт, и ссылка
     * на чужой сервер. Файл лежит на нашем же домене, поэтому исполнись
     * такой скрипт — он получит доступ ко всему, что доступно вошедшему.
     *
     * Чистим по белому списку: что не перечислено — выбрасываем. Список
     * запрещённого для этого не годится: обходов у него не счесть —
     * `onmouseover`, `onbegin` у `<animate>`, пробел перед `=`, адрес,
     * записанный сущностями. Тот же подход, что и у Blocks::cleanHtml.
     */

    /*
     * Списки заданы в каноническом написании: в SVG регистр значим, и
     * `viewbox` вместо `viewBox` браузер уже не понимает — картинка теряет
     * пропорции. Сравниваем по нижнему регистру, а в файл возвращаем то
     * написание, что перечислено здесь.
     */

    /** Теги, из которых состоит настоящая картинка. */
    private const SVG_TAGS = [
        'svg', 'g', 'defs', 'title', 'desc', 'symbol', 'use', 'path', 'rect', 'circle',
        'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'clipPath', 'mask',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'filter', 'feGaussianBlur',
        'feOffset', 'feBlend', 'feMerge', 'feMergeNode', 'feColorMatrix', 'feDropShadow',
    ];

    /** Атрибуты, за которыми не спрячешь поведение. */
    private const SVG_ATTRS = [
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

    private function cleanSvg(string $svg): ?string
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
        $tags = array_combine(array_map('strtolower', self::SVG_TAGS), self::SVG_TAGS);
        $attrs = array_combine(array_map('strtolower', self::SVG_ATTRS), self::SVG_ATTRS);

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

    /** @return array{0:int,1:int} */
    private function svgSize(string $svg): array
    {
        if (preg_match('~viewBox\s*=\s*"[\d.\s-]*?([\d.]+)\s+([\d.]+)"~i', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }

        $width = preg_match('~\bwidth\s*=\s*"(\d+)~i', $svg, $w) ? (int) $w[1] : 0;
        $height = preg_match('~\bheight\s*=\s*"(\d+)~i', $svg, $h) ? (int) $h[1] : 0;

        return [$width, $height];
    }
}
