<?php
declare(strict_types=1);

/**
 * Блоки страницы: библиотека, форма блока, порядок и видимость.
 *
 * Из чего собрана страница, описано в реестре app/blocks.php — здесь только
 * действия над уже описанными блоками. Поля формы строятся по тому же
 * описанию, поэтому новый тип блока не требует правок в этом файле.
 */
final class AdminBlocks extends AdminPageSection
{
    public function blockRoutes(array $page, string $locale, array $segments): void
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

    public function blockAdd(array $page, string $locale): void
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
    public function blocksReorder(array $page, string $locale): void
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
