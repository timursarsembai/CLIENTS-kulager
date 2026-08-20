<?php
declare(strict_types=1);

/**
 * Общая часть админки: службы и способы ответить.
 *
 * Раздел админки (страницы, меню, заявки…) не собирает ответ сам: он берёт
 * отсюда базу, репозитории и текущего пользователя, а отвечает через render,
 * redirect или json. Так разделы не знают друг о друге и не тянут за собой
 * ни разметку макета, ни правила доступа.
 *
 * В шаблоны этот же объект приходит под именем `$admin`: им они строят
 * адреса и подсвечивают открытый раздел.
 */
final class AdminContext
{
    public array $config;
    public Db $db;
    public Site $site;
    public PageRepository $pages;
    public Auth $auth;
    public Migrator $migrator;
    public ?PageCache $cache;

    private string $base;

    /** Одноразовый ключ для inline-скриптов админки. */
    private string $nonce = '';

    /** Текущий раздел — по нему подсвечивается пункт в навигации. */
    private string $section = '';

    public function __construct(array $services)
    {
        $this->config = $services['config'];
        $this->db = $services['db'];
        $this->site = $services['site'];
        $this->pages = $services['pages'];
        $this->auth = new Auth($this->db, $this->config);
        $this->cache = $services['cache'] ?? null;
        $this->migrator = new Migrator($this->db);
        $this->base = '/' . trim((string) ($this->config['admin_path'] ?? 'admin'), '/');
    }

    /* ------------------------------------------------------------ состояние */

    public function section(): string
    {
        return $this->section;
    }

    public function setSection(string $section): void
    {
        $this->section = $section;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function setNonce(string $nonce): void
    {
        $this->nonce = $nonce;
    }

    public function base(): string
    {
        return $this->base;
    }

    /* --------------------------------------------------------------- ответы */

    public function url(string $path = ''): string
    {
        return rtrim($this->base . '/' . ltrim($path, '/'), '/') ?: $this->base;
    }

    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function redirect(string $path = ''): void
    {
        header('Location: ' . $this->url($path));
        exit;
    }

    /** Сообщение показывается один раз — на следующей странице. */
    public function flash(string $message): void
    {
        $_SESSION['flash'][] = $message;
    }

    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->render('not-found', [], 'Не найдено');
    }

    /** Раздел только для администратора: редактору отвечаем отказом. */
    public function adminOnly(): bool
    {
        if ($this->auth->isAdmin()) {
            return true;
        }

        $this->flash(at('Раздел доступен только администратору.'));
        $this->redirect('');

        return false;
    }

    public function render(string $template, array $data, string $title = ''): void
    {
        $view = new View($this->site);

        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $content = $view->render('admin/' . $template, $data + [
            'admin' => $this,
            'auth'  => $this->auth,
        ]);

        echo $view->render('admin/layout', [
            'admin'    => $this,
            'auth'     => $this->auth,
            'content'  => $content,
            'title'    => at($title),
            'messages' => $messages,
        ]);
    }
}
