<?php
declare(strict_types=1);

/**
 * Разбор адреса: язык из префикса пути, страница — из карты маршрутов.
 * Основной язык живёт в корне (/), остальные — под своим префиксом (/kk/...).
 *
 * Маршрут может быть шаблонным: `otrasli/{slug}` подходит любому адресу,
 * для которого найден файл контента. Так 22 страницы отраслей описываются
 * одной строкой карты.
 */
final class Router
{
    private Site $site;
    private array $routes;
    private ?PageRepository $pages;
    private ?Leads $leads;

    public function __construct(Site $site, ?PageRepository $pages = null, ?Leads $leads = null)
    {
        $this->site = $site;
        $this->pages = $pages;
        $this->leads = $leads;
        $this->routes = require APP_DIR . '/routes.php';
    }

    public function dispatch(string $requestUri): void
    {
        $path = trim(parse_url($requestUri, PHP_URL_PATH) ?? '/', '/');

        // Служебные файлы отдаём до разбора языка: они одни на весь сайт
        if ($path === 'robots.txt' || $path === 'sitemap.xml') {
            $this->serveSeo($path);

            return;
        }

        $locale = $this->extractLocale($path);
        $this->site->setLocale($locale);

        // Приём заявки: отдельный адрес, чтобы форма работала с любой страницы
        if ($path === 'zayavka' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->acceptLead($locale);

            return;
        }

        // Основной источник — база. Файлы остаются резервом, пока контент
        // не переехал целиком, и страховкой на случай недоступной базы.
        if ($this->dispatchFromDatabase($path, $locale)) {
            return;
        }

        $match = $this->match($path, $locale);

        if ($match === null) {
            $this->renderNotFound();

            return;
        }

        [$definition, $param, $contentFile] = $match;

        $this->site->setPath($path);
        $this->site->setAlternates($this->alternates($definition, $param));

        $content = $this->loadContent($contentFile);

        $view = new View($this->site);

        echo $view->page($definition['template'], [
            'page'   => $content,
            'blocks' => $content['blocks'] ?? [],
            'meta'   => $content['meta'] ?? [],
            'slug'   => $param,
        ]);
    }

    /**
     * Приём заявки. Отвечаем по-разному: скрипту — JSON, обычной форме —
     * переадресация обратно на страницу с пометкой в адресе. Без JavaScript
     * форма тоже должна работать.
     */
    private function acceptLead(string $locale): void
    {
        $page = trim((string) ($_POST['page'] ?? ''), '/');
        $result = $this->leads?->accept($_POST, $page, $locale)
            ?? ['ok' => false, 'error' => 'Форма временно не работает.'];

        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

            return;
        }

        $back = $this->site->url($page) . '?' . http_build_query(
            $result['ok'] ? ['zayavka' => 'ok'] : ['oshibka' => $result['error'] ?? '']
        ) . '#zayavka';

        header('Location: ' . $back, true, 303);
    }

    private function serveSeo(string $path): void
    {
        require APP_DIR . '/src/Seo.php';

        $seo = new Seo($this->site, $this->pages ?? new PageRepository(new Db([]), []));

        if ($path === 'robots.txt') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $seo->robots();

            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo $seo->sitemap();
    }

    /** Пытается отдать страницу из базы. @return bool удалось ли */
    private function dispatchFromDatabase(string $path, string $locale): bool
    {
        if ($this->pages === null || !$this->pages->isReady()) {
            return false;
        }

        $draft = $this->isPreview();
        $page = $this->pages->findByPath($path, $locale, $draft);

        if ($page === null) {
            // Страница заведена, но снята с публикации — это осознанное решение
            // редактора, и подменять её файловой версией нельзя
            if ($this->pages->pathExists($path, $locale)) {
                $this->renderNotFound();

                return true;
            }

            return false;
        }

        $this->site->setPath($path);
        $this->site->setAlternates($page['alternates']);

        // Править на странице можно только то, что пришло из базы: у блоков
        // из файлов нет идентификаторов, сохранять правку было бы некуда
        $this->site->setEditMode($this->isEditMode());
        $this->site->setEditPageId($page['id']);
        $this->site->setAdminSession($this->hasAdminSession());

        $view = new View($this->site);

        echo $view->page($page['template'], [
            'page'    => $page,
            'blocks'  => $page['blocks'],
            'meta'    => $page['meta'],
            'preview' => $draft,
        ]);

        return true;
    }

    /** Правка прямо на странице — тот же вход, что и у предпросмотра. */
    private function isEditMode(): bool
    {
        return isset($_GET['edit']) && $this->hasAdminSession();
    }

    /**
     * Предпросмотр черновика. Сессию трогаем только если её об этом попросили —
     * иначе публичная часть перестала бы работать без cookies.
     */
    private function isPreview(): bool
    {
        return isset($_GET['preview']) && $this->hasAdminSession();
    }

    /** Вошёл ли посетитель в админку. */
    private function hasAdminSession(): bool
    {
        if (!isset($_COOKIE['kulager_admin'])) {
            return false;
        }

        // Каталог и срок сессии заданы в Auth — здесь тот же вход, что в админке
        Auth::startSession();

        return !empty($_SESSION['user_id']);
    }

    /** Снимает языковой префикс с пути и возвращает язык. */
    private function extractLocale(string &$path): string
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? '';

        if ($first !== '' && $first !== $this->site->defaultLocale() && isset($this->site->locales()[$first])) {
            array_shift($segments);
            $path = implode('/', $segments);

            return $first;
        }

        return $this->site->defaultLocale();
    }

    /**
     * Ищет подходящий маршрут. Шаблонный маршрут считается совпавшим только
     * если для него есть файл контента — иначе получим страницу-пустышку.
     *
     * @return array{0:array,1:string,2:string}|null definition, параметр, файл контента
     */
    private function match(string $path, string $locale): ?array
    {
        foreach ($this->routes as $key => $definition) {
            $pattern = $definition['slug'][$locale] ?? null;
            if ($pattern === null) {
                continue;
            }
            $pattern = trim($pattern, '/');

            if (!str_contains($pattern, '{slug}')) {
                if ($pattern === $path) {
                    return [$definition, '', $definition['content'] ?? $key];
                }
                continue;
            }

            $prefix = rtrim(substr($pattern, 0, strpos($pattern, '{slug}')), '/');
            if ($prefix !== '' && !str_starts_with($path, $prefix . '/')) {
                continue;
            }

            $param = substr($path, strlen($prefix) + 1);
            if ($param === '' || !$this->isSafeSlug($param)) {
                continue;
            }

            $file = trim((string) ($definition['content_dir'] ?? $key), '/') . '/' . $param;
            if ($this->contentFile($file, $locale) !== null) {
                return [$definition, $param, $file];
            }
        }

        return null;
    }

    /** Пускаем только простые имена: адрес участвует в пути к файлу контента. */
    private function isSafeSlug(string $slug): bool
    {
        return (bool) preg_match('~^[a-z0-9][a-z0-9-]*$~', $slug);
    }

    /** @return array<string,string> язык => адрес этой же страницы */
    private function alternates(array $definition, string $param): array
    {
        $out = [];
        foreach ($definition['slug'] as $locale => $pattern) {
            $out[$locale] = str_replace('{slug}', $param, $pattern);
        }

        return $out;
    }

    /** Контент страницы: сначала запрошенный язык, при отсутствии — основной. */
    private function loadContent(string $name): array
    {
        $file = $this->contentFile($name, $this->site->locale());

        if ($file === null) {
            return [];
        }

        $site = $this->site;

        return (array) require $file;
    }

    /** Путь к файлу контента с откатом на основной язык, или null. */
    private function contentFile(string $name, string $locale): ?string
    {
        $candidates = [
            APP_DIR . '/content/' . $name . '.' . $locale . '.php',
            APP_DIR . '/content/' . $name . '.' . $this->site->defaultLocale() . '.php',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private function renderNotFound(): void
    {
        http_response_code(404);

        $view = new View($this->site);

        if (is_file(APP_DIR . '/views/pages/404.php')) {
            echo $view->page('404', ['meta' => ['title' => 'Страница не найдена']]);

            return;
        }

        echo '<!doctype html><meta charset="utf-8"><title>404</title><h1>Страница не найдена</h1>';
    }
}
