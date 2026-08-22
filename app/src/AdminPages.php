<?php
declare(strict_types=1);

/**
 * Страницы: список, редактор, настройки, публикация и отмена правок.
 *
 * Действия над отдельными блоками живут в AdminBlocks: правил там своих
 * хватает, а здесь остаётся сама страница и её языковые версии.
 */
final class AdminPages extends AdminPageSection
{
    /** Действия над блоками адресуются сюда же, а выполняет их соседний раздел. */
    private function blocks(): AdminBlocks
    {
        return new AdminBlocks($this->app);
    }

    /** Разбор адресов внутри редактора страницы. */
    public function pageRoutes(array $segments): void
    {
        $pageId = (int) ($segments[0] ?? 0);
        $locale = (string) ($segments[1] ?? $this->site->defaultLocale());

        $page = $this->pages->find($pageId);

        if ($page === null || !isset($this->site->locales()[$locale])) {
            $this->notFound();

            return;
        }

        $this->site->setLocale($locale);

        $action = $segments[2] ?? '';

        // Действия над отдельным блоком: block/{id}/{что делаем}
        if ($action === 'block') {
            $this->blocks()->blockRoutes($page, $locale, array_slice($segments, 3));

            return;
        }

        match ($action) {
            ''          => $this->pageEditor($page, $locale),
            'settings'  => $this->pageSettings($page, $locale),
            'add'       => $this->blocks()->blockAdd($page, $locale),
            'reorder'   => $this->blocks()->blocksReorder($page, $locale),
            'publish'   => $this->pagePublish($page, $locale),
            'unpublish' => $this->pageUnpublish($page, $locale),
            'copy'      => $this->pageCopyBlocks($page, $locale),
            'undo'      => $this->pageUndo($page, $locale),
            default     => $this->notFound(),
        };
    }

    public function pagesList(): void
    {
        $this->render('pages', [
            'pages'   => $this->pages->listPages(),
            'locales' => $this->site->locales(),
        ], 'Страницы');
    }

    private function pageEditor(array $page, string $locale): void
    {
        $localeRow = $this->pages->locale((int) $page['id'], $locale);
        $isDefault = $locale === $this->site->defaultLocale();

        $this->render('page', [
            'page'         => $page,
            'locale'       => $locale,
            'localeRow'    => $localeRow,
            'blocks'       => $this->pages->blocks((int) $page['id'], $locale, true),
            'library'      => Blocks::grouped(),
            'otherLocales' => $this->otherLocales($locale),
            'isDefault'    => $isDefault,
            'baseLocale'   => $this->site->defaultLocale(),
            'baseBlocks'   => $isDefault
                ? 0
                : count($this->pages->blocks((int) $page['id'], $this->site->defaultLocale(), true)),
            'translation'  => $this->pages->translationStats((int) $page['id'], $locale),
            'undo'         => $this->revisions()->lastRevision((int) $page['id'], $locale),
        ], $localeRow['title'] ?? $page['page_key']);
    }

    /** @return array<string,array> языки, кроме текущего */
    private function otherLocales(string $current): array
    {
        $out = $this->site->locales();
        unset($out[$current]);

        return $out;
    }

    /* ------------------------------------------------------------ служебное */

    /** Копирование блоков основного языка в текущий — заготовка для перевода. */
    private function pageCopyBlocks(array $page, string $locale): void
    {
        if (!$this->isPost() || $locale === $this->site->defaultLocale()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $base = $this->site->defaultLocale();

        // Копирование затирает перевод целиком — снимок обязателен
        $this->keepUndo($page, $locale, 'копирование блоков из основного языка');

        $copied = $this->pages->copyBlocks((int) $page['id'], $base, $locale);

        // Языковой версии могло не быть вовсе — создаём её вместе с блоками
        if ($this->pages->locale((int) $page['id'], $locale) === null) {
            $baseRow = $this->pages->locale((int) $page['id'], $base) ?? [];

            $this->pages->saveLocale((int) $page['id'], $locale, [
                'slug'        => (string) ($baseRow['slug'] ?? ''),
                'title'       => (string) ($baseRow['title'] ?? ''),
                'description' => (string) ($baseRow['description'] ?? ''),
                'og_image'    => (string) ($baseRow['og_image'] ?? ''),
                'is_published' => false,
            ]);
        }

        $this->auth->log('copy_blocks', $page['page_key'] . ' ' . $base . '→' . $locale);
        $this->flash($copied > 0
            ? at('Скопировано блоков: %d. Теперь замените текст на перевод.', $copied)
            : at('В основной версии нет блоков для копирования.'));

        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function pageSettings(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $bar = array_filter([
            'title'    => trim((string) ($_POST['bar_title'] ?? '')),
            'subtitle' => trim((string) ($_POST['bar_subtitle'] ?? '')),
            'label'    => trim((string) ($_POST['bar_label'] ?? '')),
            'message'  => trim((string) ($_POST['bar_message'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $title = trim((string) ($_POST['title'] ?? ''));
        $current = (string) ($this->pages->locale((int) $page['id'], $locale)['slug'] ?? '');
        $slug = Slug::make((string) ($_POST['slug'] ?? ''));

        /*
         * Адрес пустой — либо это главная (у неё он пустой и должен таким
         * остаться), либо поле просто не заполнили: тогда собираем его
         * из заголовка, сохраняя вложенность.
         */
        if ($slug === '' && $current !== '') {
            $slug = Slug::fromTitle($title, $current);
        }

        if ($this->pages->slugTaken($slug, $locale, (int) $page['id'])) {
            $this->flash(at('Адрес «/%s» уже занят другой страницей — настройки не сохранены.', $slug));
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->keepUndo($page, $locale, 'настройки страницы');

        $this->pages->saveLocale((int) $page['id'], $locale, [
            'slug'        => $slug,
            'title'       => $title,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'og_image'    => trim((string) ($_POST['og_image'] ?? '')),
            'noindex'     => !empty($_POST['noindex']),
            'canonical'   => Slug::make((string) ($_POST['canonical'] ?? '')),
            'bar'         => $bar !== [] ? $bar : null,
        ]);

        $this->flash(at('Настройки страницы сохранены.'));
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    /** Возврат языковой версии к состоянию до последнего изменения. */
    private function pageUndo(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $revision = $this->revisions()->lastRevision((int) $page['id'], $locale);

        if ($revision === null) {
            $this->flash(at('Отменять нечего: правок пока не было.'));
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->revisions()->restoreRevision((int) $revision['id'])
            ? $this->flash(at('Отменено: %s.', $revision['comment'] ?: at('последнее изменение')))
            : $this->flash(at('Не удалось отменить — снимок повреждён.'));

        $this->auth->log('page_undo', $page['page_key'] . ' ' . $locale, (string) $revision['comment']);
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    /* -------------------------------------------------------------- меню */

    private function pagePublish(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        // Снимок перед публикацией: к прежнему состоянию должно быть куда вернуться
        $this->keepUndo($page, $locale, 'публикация');
        $this->pages->publish((int) $page['id'], $locale);
        $this->auth->log('publish', $page['page_key'] . ' / ' . $locale);

        $this->flash(at('Страница опубликована.'));
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function pageUnpublish(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->keepUndo($page, $locale, 'снятие с публикации');
        $this->pages->unpublish((int) $page['id'], $locale);
        $this->auth->log('unpublish', $page['page_key'] . ' / ' . $locale);

        $this->flash(at('Страница снята с публикации.'));
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }
}
