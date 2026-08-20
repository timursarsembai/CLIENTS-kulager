<?php
declare(strict_types=1);

/**
 * Настройки для поисковиков и счётчики.
 *
 * Общие значения подставляются страницам, у которых своё не заполнено;
 * сводка ниже показывает, где как раз не заполнено.
 */
final class AdminSeo extends AdminSection
{
    /**
     * Общие настройки для поисковиков: название сайта, приписка к заголовку,
     * описание и картинка по умолчанию, robots.txt, подтверждение прав.
     */
    public function seoSettings(): void
    {
        $settings = new Settings($this->db, (array) $this->config['contacts']);

        // Счётчики правятся на этой же странице: они про ту же аналитику
        $fields = Settings::group('seo') + Settings::group('counters');

        if ($this->isPost()) {
            $values = [];

            /*
             * На странице две формы — общие настройки и счётчики. Сохраняем
             * только то, что пришло: иначе одна форма затирала бы поля другой.
             */
            foreach ($fields as $key => $field) {
                if (($field['type'] ?? '') === 'bool') {
                    // Флажок не приходит вовсе, когда снят, — смотрим на соседнее скрытое поле
                    if (array_key_exists($key, $_POST)) {
                        $values[$key] = empty($_POST[$key]) ? '' : '1';
                    }

                    continue;
                }

                if (array_key_exists($key, $_POST)) {
                    $values[$key] = (string) $_POST[$key];
                }
            }

            $settings->save($values);
            $this->auth->log('seo_settings');
            $this->flash(at('Настройки SEO сохранены.'));
            $this->redirect('seo');

            return;
        }

        FormBuilder::usePages($this->pages->catalog($this->site->defaultLocale()));

        $this->render('seo', [
            'fields'   => Settings::group('seo'),
            'counters' => Settings::group('counters'),
            'values'  => $settings->all(),
            'pages'   => $this->seoOverview(),
            'locales' => $this->site->locales(),
        ], 'SEO');
    }

    /**
     * Сводка по страницам: где не заполнено описание, где слишком длинный
     * заголовок, что закрыто от индексации. Проверять руками шесть десятков
     * страниц никто не станет.
     */
    private function seoOverview(): array
    {
        $rows = $this->db->all(
            'SELECT l.*, p.page_key
               FROM page_locales l
               JOIN pages p ON p.id = l.page_id
              ORDER BY l.locale, l.slug'
        );

        $out = [];

        foreach ($rows as $row) {
            $title = (string) $row['title'];
            $description = (string) ($row['description'] ?? '');
            $issues = [];

            if (trim($title) === '') {
                $issues[] = 'нет заголовка';
            } elseif (mb_strlen($title) > 70) {
                $issues[] = 'заголовок длиннее 70 знаков';
            }

            if (trim($description) === '') {
                $issues[] = 'нет описания';
            } elseif (mb_strlen($description) > 200) {
                $issues[] = 'описание длиннее 200 знаков';
            }

            if (!empty($row['noindex'])) {
                $issues[] = 'закрыта от индексации';
            }

            if (!$row['is_published']) {
                $issues[] = 'черновик';
            }

            $out[] = [
                'page_id'     => (int) $row['page_id'],
                'locale'      => (string) $row['locale'],
                'slug'        => (string) $row['slug'],
                'title'       => $title,
                'title_len'   => mb_strlen($title),
                'description' => $description,
                'desc_len'    => mb_strlen($description),
                'issues'      => $issues,
            ];
        }

        return $out;
    }

    /* --------------------------------------------------------- оформление */
}
