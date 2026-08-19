<?php
declare(strict_types=1);

/**
 * Рендеринг шаблонов. Шаблон — обычный PHP-файл, переменные приходят
 * массивом и распаковываются в область видимости. Никаких «умных» движков:
 * на shared-хостинге важнее предсказуемость и отсутствие зависимостей.
 */
final class View
{
    private Site $site;
    private string $dir;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->dir = APP_DIR . '/views';
    }

    /**
     * Метка редактируемого текста для правки прямо на странице.
     *
     * Ставится в тег, внутри которого выводится значение поля:
     *   <h2 <?= $view->editable($block, 'title') ?>><?= e($block['title']) ?></h2>
     *
     * Путь совпадает с расположением поля в данных блока, включая списки:
     * `items.0.title`. Вне режима правки возвращает пустую строку, поэтому
     * на обычной странице разметка не меняется вовсе.
     *
     * $mode = 'html' — для полей с разметкой: сохраняется содержимое как есть,
     * лишние теги всё равно срежет Blocks::cleanHtml() на сервере.
     */
    public function editable(array $block, string $path, string $mode = 'text'): string
    {
        if (!$this->site->editMode() || !isset($block['_id'])) {
            return '';
        }

        return ' data-edit-block="' . (int) $block['_id'] . '"'
            . ' data-edit-path="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"'
            // Поля с разметкой (заголовок обложки, сноска таблицы) правятся
            // как HTML: в них живут переносы строк и выделение
            . ($mode === 'html' ? ' data-edit-html="1"' : '');
    }

    /**
     * Метка редактируемого пункта меню.
     *
     * Блок «Отрасли применения» и подвал берут названия из меню, а не из
     * своих данных, — значит и править их надо в меню. Снаружи это
     * незаметно: щёлкаешь по названию отрасли на странице и меняешь его.
     */
    public function editableNav(array $item, string $field = 'title'): string
    {
        if (!$this->site->editMode() || empty($item['_id'])) {
            return '';
        }

        return ' data-edit-nav="' . (int) $item['_id'] . '"'
            . ' data-edit-path="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Метка редактируемой строки интерфейса — подписи в подвале, шапке,
     * боковом меню. Они одни на весь сайт и лежат в app/lang, а правка
     * сохраняется поверх файла, отдельно для каждого языка.
     */
    public function editableText(string $key, string $mode = 'text'): string
    {
        if (!$this->site->editMode()) {
            return '';
        }

        return ' data-edit-text="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-edit-path="' . htmlspecialchars($this->site->locale(), ENT_QUOTES, 'UTF-8') . '"'
            . ($mode === 'html' ? ' data-edit-html="1"' : '');
    }

    /** Метка редактируемой настройки сайта — телефон, почта, режим работы. */
    public function editableSetting(string $key): string
    {
        if (!$this->site->editMode()) {
            return '';
        }

        return ' data-edit-setting="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-edit-path="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Метка поля самой страницы: заголовок и подписи плавающей панели внизу.
     * Они хранятся не в блоках, а в языковой версии страницы.
     */
    public function editablePage(string $field): string
    {
        $id = $this->site->editPageId();

        if (!$this->site->editMode() || $id === 0) {
            return '';
        }

        return ' data-edit-page="' . $id . '"'
            . ' data-edit-path="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '"';
    }

    /** Страница внутри общего каркаса. */
    public function page(string $template, array $data = []): string
    {
        $content = $this->render('pages/' . $template, $data);

        return $this->render('layout', $data + ['content' => $content]);
    }

    /**
     * Рендер списка блоков контента. Каждый блок — ['type' => ..., ...данные].
     * Именно этот механизм станет основой конструктора страниц в CMS.
     */
    public function blocks(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if ($type === '' || !is_file($this->dir . '/blocks/' . $type . '.php')) {
                continue;
            }
            $out .= $this->render('blocks/' . $type, ['block' => $block]);
        }

        return $out;
    }

    public function partial(string $name, array $data = []): string
    {
        return $this->render('partials/' . $name, $data);
    }

    /**
     * Внутренние переменные названы с префиксом, чтобы данные шаблона
     * могли использовать любые имена — включая $data, $view или $file.
     */
    public function render(string $__template, array $__vars = []): string
    {
        $__file = $this->dir . '/' . $__template . '.php';

        if (!is_file($__file)) {
            throw new RuntimeException('Не найден шаблон: ' . $__template);
        }

        $site = $this->site;
        $view = $this;

        extract($__vars, EXTR_SKIP);

        ob_start();
        try {
            require $__file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
