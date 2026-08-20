<?php
declare(strict_types=1);

/**
 * Правка прямо на странице сайта.
 *
 * Принимает точечные правки из режима «править на странице»: текст блока,
 * пункт меню, строку интерфейса, поле самой страницы. Значение не пишется
 * как есть — блок собирается заново и проходит ту же проверку, что и форма.
 */
final class AdminInline extends AdminSection
{
    /** Снимки страницы: то, к чему возвращает кнопка «Отменить». */
    private function revisions(): PageRevisions
    {
        return new PageRevisions($this->db);
    }

    /**
     * Правка текста прямо на странице сайта.
     *
     * Принимает идентификатор блока, путь к полю (`items.0.title`) и новое
     * значение. Значение не записывается как есть: данные блока собираются
     * заново и проходят через ту же проверку, что и форма в админке, —
     * правил на два входа не бывает.
     */
    public function inlineSave(): void
    {
        if (!$this->isPost()) {
            $this->json(['error' => at('Ожидался POST.')], 405);

            return;
        }

        $path = trim((string) ($_POST['path'] ?? ''));
        $value = (string) ($_POST['value'] ?? '');

        // Правка на странице приходит из четырёх мест сразу: блок, пункт
        // меню, строка интерфейса и поле самой страницы
        if (!empty($_POST['nav'])) {
            $this->inlineSaveNav((int) $_POST['nav'], $path, $value);

            return;
        }

        if (!empty($_POST['text'])) {
            $this->inlineSaveText((string) $_POST['text'], $path, $value);

            return;
        }

        if (!empty($_POST['setting'])) {
            $this->inlineSaveSetting((string) $_POST['setting'], $value);

            return;
        }

        if (!empty($_POST['page'])) {
            $this->inlineSavePage((int) $_POST['page'], $path, $value);

            return;
        }

        $block = $this->pages->findBlock((int) ($_POST['block'] ?? 0));

        if ($block === null || $path === '') {
            $this->json(['error' => at('Блок не найден.')], 404);

            return;
        }

        $data = $block['data'];

        if (!self::setByPath($data, explode('.', $path), $value)) {
            $this->json(['error' => at('Такого поля в блоке нет.')], 422);

            return;
        }

        [$clean, $errors] = Blocks::sanitize((string) $block['type'], $data);

        if ($errors !== []) {
            $this->json(['error' => reset($errors)], 422);

            return;
        }

        // Снимок и здесь: правка на странице отменяется той же кнопкой
        $this->revisions()->snapshot(
            (int) $block['page_id'],
            (string) $block['locale'],
            $this->auth->user()['id'] ?? null,
            'правка на странице: ' . Blocks::title((string) $block['type'])
        );

        $this->pages->updateBlock((int) $block['id'], $clean);
        $this->auth->log('inline_edit', $block['type'] . '#' . $block['id'], $path);

        // Правка идёт мимо dispatch-обработчика POST, кэш сбрасываем сами
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => self::byPath($clean, explode('.', $path))]);
    }

    /** Правка пункта меню прямо на странице. */
    private function inlineSaveNav(int $id, string $field, string $value): void
    {
        $nav = new NavigationRepository($this->db);
        $item = $nav->find($id);

        if ($item === null || !in_array($field, ['title', 'full_title', 'footer_title'], true)) {
            $this->json(['error' => at('Пункт меню не найден.')], 404);

            return;
        }

        $value = trim(strip_tags($value));

        if ($value === '') {
            $this->json(['error' => at('Название не может быть пустым.')], 422);

            return;
        }

        $nav->update($id, [
            'parent_id'    => $item['parent_id'],
            'title'        => $field === 'title' ? $value : (string) $item['title'],
            'full_title'   => $field === 'full_title' ? $value : (string) $item['full_title'],
            'footer_title' => $field === 'footer_title' ? $value : (string) ($item['footer_title'] ?? ''),
            'url'          => (string) $item['url'],
            'in_drawer'    => (bool) $item['in_drawer'],
            'in_footer'    => (bool) ($item['in_footer'] ?? 1),
        ]);

        $this->auth->log('inline_edit_menu', (string) $item['menu_key'], $field);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $value]);
    }

    /** Правка строки интерфейса: подписи в подвале, шапке, боковом меню. */
    private function inlineSaveText(string $key, string $locale, string $value): void
    {
        if (!isset($this->site->locales()[$locale])) {
            $this->json(['error' => at('Неизвестный язык.')], 422);

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $settings->setText($key, $locale, Blocks::cleanHtml($value));

        $this->auth->log('inline_edit_text', $key, $locale);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $value]);
    }

    /** Правка настройки сайта: телефон, почта, режим работы. */
    private function inlineSaveSetting(string $key, string $value): void
    {
        if (!isset(Settings::FIELDS[$key])) {
            $this->json(['error' => at('Такой настройки нет.')], 422);

            return;
        }

        /*
         * На странице правятся только контакты — то, что там и видно.
         * Остальные настройки (токен бота, счётчики, SEO) в админке закрыты
         * администратором, и эта дверь не должна их открывать: иначе редактор
         * одним запросом уводит уведомления о заявках в свой чат.
         */
        $group = (string) (Settings::FIELDS[$key]['group'] ?? '');

        if ($group !== 'contacts' && !$this->auth->isAdmin()) {
            $this->json(['error' => at('Эту настройку меняет только администратор.')], 403);

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $settings->save([$key => trim(strip_tags($value))]);

        $this->auth->log('inline_edit_setting', $key);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $value]);
    }

    /**
     * Правка полей самой страницы — сейчас это тексты плавающей панели внизу.
     * Они лежат в языковой версии, а не в блоках.
     */
    private function inlineSavePage(int $pageId, string $field, string $value): void
    {
        $locale = $this->site->locale();
        $row = $this->pages->locale($pageId, $locale);

        if ($row === null || !str_starts_with($field, 'bar.')) {
            $this->json(['error' => at('Такого поля у страницы нет.')], 422);

            return;
        }

        $key = substr($field, 4);

        if (!in_array($key, ['title', 'subtitle', 'label', 'message'], true)) {
            $this->json(['error' => at('Такого поля у панели нет.')], 422);

            return;
        }

        $bar = json_decode((string) ($row['bar_json'] ?? ''), true);
        $bar = is_array($bar) ? $bar : [];
        $bar[$key] = trim(strip_tags($value));

        $this->revisions()->snapshot($pageId, $locale, $this->auth->user()['id'] ?? null, 'правка панели внизу');

        $this->pages->saveLocale($pageId, $locale, [
            'slug'        => (string) $row['slug'],
            'title'       => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'og_image'    => (string) ($row['og_image'] ?? ''),
            'noindex'     => !empty($row['noindex']),
            'canonical'   => (string) ($row['canonical'] ?? ''),
            'bar'         => array_filter($bar, static fn (string $v): bool => $v !== ''),
        ]);

        $this->auth->log('inline_edit_bar', (string) $row['slug'], $key);
        $this->cache?->flush();

        $this->json(['ok' => true, 'value' => $bar[$key]]);
    }

    /**
     * Записывает значение по пути внутри данных блока.
     * Новых ключей не создаёт: править можно только то, что уже выводится.
     */
    private static function setByPath(array &$data, array $path, string $value): bool
    {
        $key = array_shift($path);

        if ($key === null || !array_key_exists($key, $data)) {
            return false;
        }

        if ($path === []) {
            if (is_array($data[$key])) {
                return false;
            }

            $data[$key] = $value;

            return true;
        }

        if (!is_array($data[$key])) {
            return false;
        }

        return self::setByPath($data[$key], $path, $value);
    }

    private static function byPath(array $data, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }
}
