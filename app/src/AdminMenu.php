<?php
declare(strict_types=1);

/**
 * Меню сайта: шапка, боковое меню, подвал.
 *
 * Меню правится списком и сохраняется разом; порядок пунктов меняется
 * перетаскиванием, поэтому есть отдельный приём для перестановки.
 */
final class AdminMenu extends AdminSection
{
    /** Разбор адресов редактора меню: menu/{язык}/{меню}/{действие}. */
    public function menuRoutes(array $segments): void
    {
        $nav = new NavigationRepository($this->db);
        $locale = (string) ($segments[0] ?? $this->site->defaultLocale());

        if (!isset($this->site->locales()[$locale])) {
            $this->notFound();

            return;
        }

        $menu = (string) ($segments[1] ?? '');

        if ($menu === '') {
            $this->menuList($nav, $locale);

            return;
        }

        if (!isset(NavigationRepository::MENUS[$menu])) {
            $this->notFound();

            return;
        }

        match ($segments[2] ?? '') {
            ''        => $this->menuEditor($nav, $locale, $menu),
            'add'     => $this->menuAdd($nav, $locale, $menu),
            'save'    => $this->menuSave($nav, $locale, $menu),
            'delete'  => $this->menuDelete($nav, $locale, $menu),
            'reorder' => $this->menuReorder($nav, $locale, $menu),
            default   => $this->notFound(),
        };
    }

    private function menuList(NavigationRepository $nav, string $locale): void
    {
        $counts = [];

        foreach (array_keys(NavigationRepository::MENUS) as $menu) {
            $counts[$menu] = count($nav->items($menu, $locale));
        }

        $this->render('menu', [
            'locale'   => $locale,
            'locales'  => $this->site->locales(),
            'menus'    => NavigationRepository::MENUS,
            'counts'   => $counts,
            'imported' => $nav->hasItems(),
        ], 'Меню');
    }

    private function menuEditor(NavigationRepository $nav, string $locale, string $menu): void
    {
        FormBuilder::usePages($this->pages->catalog($locale));

        $this->render('menu-edit', [
            'locale'  => $locale,
            'locales' => $this->site->locales(),
            'menu'    => $menu,
            'title'   => NavigationRepository::MENUS[$menu],
            'items'   => $nav->items($menu, $locale),
            'grouped' => $menu === 'industries',
        ], NavigationRepository::MENUS[$menu]);
    }

    private function menuAdd(NavigationRepository $nav, string $locale, string $menu): void
    {
        if (!$this->isPost()) {
            $this->redirect('menu/' . $locale . '/' . $menu);

            return;
        }

        $parent = (int) ($_POST['parent_id'] ?? 0);

        $nav->add($menu, $locale, [
            'parent_id' => $parent > 0 ? $parent : null,
            'title'     => trim((string) ($_POST['title'] ?? 'Новый пункт')),
            'url'       => trim((string) ($_POST['url'] ?? '')),
            'in_drawer' => true,
        ]);

        $this->auth->log('menu_add', $menu . '/' . $locale);
        $this->redirect('menu/' . $locale . '/' . $menu);
    }

    private function menuSave(NavigationRepository $nav, string $locale, string $menu): void
    {
        if (!$this->isPost()) {
            $this->redirect('menu/' . $locale . '/' . $menu);

            return;
        }

        foreach ((array) ($_POST['items'] ?? []) as $id => $values) {
            $item = $nav->find((int) $id);

            // Правим только пункты этого меню и языка: id приходит из формы
            if ($item === null || $item['menu_key'] !== $menu || $item['locale'] !== $locale) {
                continue;
            }

            $nav->update((int) $id, [
                'parent_id'    => $item['parent_id'],
                'title'        => trim((string) ($values['title'] ?? '')),
                'full_title'   => trim((string) ($values['full_title'] ?? '')),
                'footer_title' => trim((string) ($values['footer_title'] ?? '')),
                'url'          => trim((string) ($values['url'] ?? '')),
                'in_drawer'    => !empty($values['in_drawer']),
                'in_footer'    => !empty($values['in_footer']),
            ]);
        }

        $this->auth->log('menu_save', $menu . '/' . $locale);
        $this->flash(at('Меню сохранено.'));
        $this->redirect('menu/' . $locale . '/' . $menu);
    }

    private function menuDelete(NavigationRepository $nav, string $locale, string $menu): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $item = $nav->find($id);

        if ($this->isPost() && $item !== null && $item['menu_key'] === $menu && $item['locale'] === $locale) {
            $nav->delete($id);
            $this->auth->log('menu_delete', $menu . '/' . $locale);
            $this->flash(at('Пункт удалён.'));
        }

        $this->redirect('menu/' . $locale . '/' . $menu);
    }

    private function menuReorder(NavigationRepository $nav, string $locale, string $menu): void
    {
        $order = array_map('intval', (array) ($_POST['order'] ?? []));
        $allowed = array_column($nav->items($menu, $locale), 'id');

        // Порядок принимаем только для пунктов этого меню
        $order = array_values(array_filter($order, static fn (int $id): bool => in_array($id, array_map('intval', $allowed), true)));

        if ($order !== []) {
            $nav->reorder($order);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
    }
}
