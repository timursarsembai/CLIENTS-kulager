<?php
declare(strict_types=1);

/**
 * Раздел админки: страницы, меню, заявки, пользователи и прочее.
 *
 * Всё общее — база, репозитории, текущий пользователь, способы ответить —
 * лежит в AdminContext, а здесь только короткие проводники к нему. Так тело
 * раздела занимается своим делом и не повторяет одно и то же в каждом файле.
 *
 * Раздел ничего не знает ни о соседних разделах, ни о том, как его нашли:
 * маршруты разбирает Admin и вызывает нужный метод.
 */
abstract class AdminSection
{
    protected AdminContext $app;

    protected array $config;
    protected Db $db;
    protected Site $site;
    protected PageRepository $pages;
    protected Auth $auth;
    protected Migrator $migrator;
    protected ?PageCache $cache;

    public function __construct(AdminContext $app)
    {
        $this->app = $app;
        $this->config = $app->config;
        $this->db = $app->db;
        $this->site = $app->site;
        $this->pages = $app->pages;
        $this->auth = $app->auth;
        $this->migrator = $app->migrator;
        $this->cache = $app->cache;
    }

    /* ------------------------------------------------------- ответы разделу */

    protected function isPost(): bool
    {
        return $this->app->isPost();
    }

    protected function redirect(string $path = ''): void
    {
        $this->app->redirect($path);
    }

    protected function url(string $path = ''): string
    {
        return $this->app->url($path);
    }

    protected function flash(string $message): void
    {
        $this->app->flash($message);
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->app->json($data, $status);
    }

    protected function notFound(): void
    {
        $this->app->notFound();
    }

    protected function adminOnly(): bool
    {
        return $this->app->adminOnly();
    }

    protected function render(string $template, array $data, string $title = ''): void
    {
        $this->app->render($template, $data, $title);
    }
}
