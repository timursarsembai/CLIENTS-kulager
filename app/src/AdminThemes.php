<?php
declare(strict_types=1);

/**
 * Оформление: список тем и правка цветов.
 *
 * Тема — это набор переменных CSS. Значения уходят в разметку каждой
 * страницы, поэтому чистятся при сохранении.
 */
final class AdminThemes extends AdminSection
{
    /** Разбор адресов раздела тем. */
    public function themeRoutes(array $segments): void
    {
        $themes = new ThemeRepository($this->db, new Settings($this->db, (array) $this->config['contacts']));

        // При первом заходе переносим темы из файла: иначе править нечего
        $themes->importBuiltin();

        $head = (string) ($segments[0] ?? '');

        if ($head === '') {
            $this->themeList($themes);

            return;
        }

        if ($head === 'add') {
            $this->themeAdd($themes);

            return;
        }

        if ($head === 'default') {
            $this->themeDefault($themes);

            return;
        }

        $theme = $themes->find((int) $head);

        if ($theme === null) {
            $this->notFound();

            return;
        }

        match ($segments[1] ?? '') {
            ''       => $this->themeForm($themes, $theme),
            'delete' => $this->themeDelete($themes, $theme),
            default  => $this->notFound(),
        };
    }

    private function themeList(ThemeRepository $themes): void
    {
        $this->render('themes', [
            'themes'  => $themes->rows(),
            'current' => $themes->defaultKey((string) $this->site->config('default_theme', 'dark')),
        ], 'Оформление');
    }

    private function themeForm(ThemeRepository $themes, array $theme): void
    {
        if ($this->isPost()) {
            $vars = [];

            foreach (array_keys(ThemeRepository::VARS) as $key) {
                // Значение уходит в <style> на каждой странице — чистим на входе
                $value = ThemeRepository::cleanValue((string) ($_POST['vars'][$key] ?? ''));

                if ($value !== '') {
                    $vars[$key] = $value;
                }
            }

            $themes->save(
                (int) $theme['id'],
                mb_substr(trim(strip_tags((string) ($_POST['name'] ?? $theme['name']))), 0, 60),
                $vars,
                [
                    trim((string) ($_POST['swatch_bg'] ?? '')),
                    trim((string) ($_POST['swatch_accent'] ?? '')),
                ]
            );

            $this->auth->log('theme_save', (string) $theme['theme_key']);
            $this->flash(at('Тема сохранена.'));
            $this->redirect('themes/' . $theme['id']);

            return;
        }

        $vars = json_decode((string) $theme['vars_json'], true);

        $this->render('theme-edit', [
            'theme'  => $theme,
            'vars'   => is_array($vars) ? $vars : [],
            'fields' => ThemeRepository::VARS,
        ], (string) $theme['name']);
    }

    private function themeAdd(ThemeRepository $themes): void
    {
        if (!$this->isPost()) {
            $this->redirect('themes');

            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $source = trim((string) ($_POST['source'] ?? 'dark'));

        if ($name === '') {
            $this->flash(at('Укажите название темы.'));
            $this->redirect('themes');

            return;
        }

        $id = $themes->duplicate($source, $name);

        if ($id === null) {
            $this->flash(at('Не нашли тему, с которой копировать.'));
            $this->redirect('themes');

            return;
        }

        $this->auth->log('theme_add', $name);
        $this->flash(at('Тема создана — поправьте цвета.'));
        $this->redirect('themes/' . $id);
    }

    private function themeDefault(ThemeRepository $themes): void
    {
        if ($this->isPost()) {
            $key = trim((string) ($_POST['theme_key'] ?? ''));

            if ($themes->exists($key)) {
                $themes->setDefault($key);
                $this->auth->log('theme_default', $key);
                $this->flash(at('Тема по умолчанию изменена.'));
            }
        }

        $this->redirect('themes');
    }

    private function themeDelete(ThemeRepository $themes, array $theme): void
    {
        if ($this->isPost()) {
            $themes->delete((int) $theme['id'])
                ? $this->flash(at('Тема удалена.'))
                : $this->flash(at('Встроенную тему удалить нельзя.'));

            $this->auth->log('theme_delete', (string) $theme['theme_key']);
        }

        $this->redirect('themes');
    }
}
