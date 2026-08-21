<?php
declare(strict_types=1);

/**
 * Общее для страницы и её блоков: снимок перед правкой.
 *
 * Правки приходят с двух сторон — из настроек самой страницы и из форм
 * отдельных блоков, — а отменять их приходится одинаково. Поэтому снимок
 * делается в одном месте, а не повторяется в каждом действии.
 */
abstract class AdminPageSection extends AdminSection
{
    /** Снимки страницы: то, к чему возвращает кнопка «Отменить». */
    protected function revisions(): PageRevisions
    {
        return new PageRevisions($this->db);
    }

    /**
     * Снимок языковой версии перед изменением: к нему вернёт «Отменить».
     * Снимки чистятся сами, храним последние пятнадцать на версию.
     */
    protected function keepUndo(array $page, string $locale, string $comment): void
    {
        $this->revisions()->snapshot((int) $page['id'], $locale, $this->auth->user()['id'] ?? null, $comment);
    }
}
