<?php
declare(strict_types=1);

/**
 * Страницы и блоки: список, редактор, публикация.
 *
 * Самый большой раздел: страница состоит из блоков, поэтому здесь и
 * перестановка блоков, и форма отдельного блока, и настройки самой
 * страницы. Снимок перед правкой (keepUndo) живёт тут же — отменять
 * приходится именно эти действия.
 */
final class AdminPages extends AdminSection
{
    /** Снимки страницы: то, к чему возвращает кнопка «Отменить». */
    private function revisions(): PageRevisions
    {
        return new PageRevisions($this->db);
    }

    public function pagesList(): void
    {
        $this->render('pages', [
            'pages'   => $this->pages->listPages(),
            'locales' => $this->site->locales(),
        ], 'Страницы');
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
            $this->blockRoutes($page, $locale, array_slice($segments, 3));

            return;
        }

        match ($action) {
            ''          => $this->pageEditor($page, $locale),
            'settings'  => $this->pageSettings($page, $locale),
            'add'       => $this->blockAdd($page, $locale),
            'reorder'   => $this->blocksReorder($page, $locale),
            'publish'   => $this->pagePublish($page, $locale),
            'unpublish' => $this->pageUnpublish($page, $locale),
            'copy'      => $this->pageCopyBlocks($page, $locale),
            'undo'      => $this->pageUndo($page, $locale),
            default     => $this->notFound(),
        };
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

    /**
     * Снимок языковой версии перед изменением: к нему вернёт «Отменить».
     * Снимки чистятся сами, храним последние пятнадцать на версию.
     */
    private function keepUndo(array $page, string $locale, string $comment): void
    {
        $this->revisions()->snapshot((int) $page['id'], $locale, $this->auth->user()['id'] ?? null, $comment);
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

        $this->pages->publish((int) $page['id'], $locale, (int) ($this->auth->user()['id'] ?? 0) ?: null);
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

        $this->pages->unpublish((int) $page['id'], $locale);
        $this->auth->log('unpublish', $page['page_key'] . ' / ' . $locale);

        $this->flash(at('Страница снята с публикации.'));
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function blockRoutes(array $page, string $locale, array $segments): void
    {
        $blockId = (int) ($segments[0] ?? 0);
        $block = $this->pages->findBlock($blockId);

        if ($block === null || (int) $block['page_id'] !== (int) $page['id'] || $block['locale'] !== $locale) {
            $this->notFound();

            return;
        }

        match ($segments[1] ?? '') {
            ''          => $this->blockForm($page, $locale, $block),
            'delete'    => $this->blockDelete($page, $locale, $block),
            'toggle'    => $this->blockToggle($page, $locale, $block),
            'duplicate' => $this->blockDuplicate($page, $locale, $block),
            default     => $this->notFound(),
        };
    }

    /* ------------------------------------------------------------ действия */

    private function blockForm(array $page, string $locale, array $block): void
    {
        $definition = Blocks::definition($block['type']);

        if ($definition === null) {
            $this->notFound();

            return;
        }

        $errors = [];
        $data = $block['data'];

        if ($this->isPost()) {
            [$clean, $errors] = Blocks::sanitize($block['type'], (array) ($_POST['data'] ?? []));

            if ($errors === []) {
                $this->keepUndo($page, $locale, 'правка блока «' . Blocks::title($block['type']) . '»');
                $this->pages->updateBlock((int) $block['id'], $clean);
                $this->flash(at('Блок «%s» сохранён.', at(Blocks::title($block['type']))));
                $this->redirect('page/' . $page['id'] . '/' . $locale . '#block-' . $block['id']);

                return;
            }

            // При ошибке показываем то, что ввёл редактор, а не старое значение
            $data = $clean;
        }

        // Ссылки в блоке выбираются из страниц того же языка
        FormBuilder::usePages($this->pages->catalog($locale));

        $this->render('block', [
            'page'       => $page,
            'locale'     => $locale,
            'block'      => $block,
            'definition' => $definition,
            'data'       => $data,
            'errors'     => $errors,
        ], Blocks::title($block['type']));
    }

    private function blockAdd(array $page, string $locale): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $type = (string) ($_POST['type'] ?? '');

        if (!Blocks::exists($type)) {
            $this->flash(at('Неизвестный тип блока.'));
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $id = $this->pages->addBlock((int) $page['id'], $locale, $type, Blocks::defaults($type));

        $this->redirect('page/' . $page['id'] . '/' . $locale . '/block/' . $id);
    }

    private function blockDelete(array $page, string $locale, array $block): void
    {
        // Только POST: по GET токен не проверяется, и картинка с таким
        // адресом на чужой странице удалила бы блок руками вошедшего
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->keepUndo($page, $locale, 'удаление блока «' . Blocks::title($block['type']) . '»');
        $this->pages->deleteBlock((int) $block['id']);
        $this->flash(at('Блок «%s» удалён.', at(Blocks::title($block['type']))));
        $this->redirect('page/' . $page['id'] . '/' . $locale);
    }

    private function blockToggle(array $page, string $locale, array $block): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $this->pages->setBlockVisible((int) $block['id'], !$block['is_visible']);
        $this->redirect('page/' . $page['id'] . '/' . $locale . '#block-' . $block['id']);
    }

    private function blockDuplicate(array $page, string $locale, array $block): void
    {
        if (!$this->isPost()) {
            $this->redirect('page/' . $page['id'] . '/' . $locale);

            return;
        }

        $id = $this->pages->addBlock(
            (int) $page['id'],
            $locale,
            $block['type'],
            $block['data'],
            (int) $block['sort'] + 5
        );

        $this->flash(at('Блок скопирован.'));
        $this->redirect('page/' . $page['id'] . '/' . $locale . '#block-' . $id);
    }

    /** Новый порядок блоков. Отвечает JSON: вызывается перетаскиванием. */
    private function blocksReorder(array $page, string $locale): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isPost()) {
            echo json_encode(['ok' => false]);

            return;
        }

        $order = array_map('intval', (array) ($_POST['order'] ?? []));

        // Переставляем только блоки этой страницы и этого языка
        $allowed = array_column(
            $this->db->all(
                'SELECT id FROM page_blocks WHERE page_id = :id AND locale = :locale',
                ['id' => $page['id'], 'locale' => $locale]
            ),
            'id'
        );

        $order = array_values(array_intersect($order, array_map('intval', $allowed)));

        $this->pages->reorderBlocks($order);

        echo json_encode(['ok' => true, 'count' => count($order)]);
    }
}
