<?php
declare(strict_types=1);

/**
 * robots.txt и sitemap.xml. Отдаём их из PHP, а не файлами: карта сайта
 * должна отражать то, что опубликовано прямо сейчас, а на shared-хостинге
 * нет планировщика, чтобы перегенерировать её по расписанию.
 */
final class Seo
{
    private Site $site;
    private PageRepository $pages;
    private array $routes;

    public function __construct(Site $site, PageRepository $pages)
    {
        $this->site = $site;
        $this->pages = $pages;
        $this->routes = require APP_DIR . '/routes.php';
    }

    public function robots(): string
    {
        // Сайт закрыт на время доработки — обход запрещаем целиком
        if ($this->site->siteNoindex()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /assets/uploads/*.svg',
        ];

        foreach (preg_split('~\R~', $this->site->robotsExtra()) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $this->site->baseUrl() . '/sitemap.xml';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function sitemap(): string
    {
        // Пока сайт закрыт от индексации, карта сайта пустая, но валидная
        $urls = $this->site->siteNoindex()
            ? []
            : ($this->pages->isReady() ? $this->fromDatabase() : $this->fromFiles());

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                . '    <loc>' . e($url['loc']) . "</loc>\n";

            if (($url['lastmod'] ?? '') !== '') {
                $xml .= '    <lastmod>' . e($url['lastmod']) . "</lastmod>\n";
            }

            // Поисковику нужно знать, что у страницы есть версия на другом языке
            foreach ($url['alternates'] ?? [] as $locale => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . e($locale)
                    . '" href="' . e($href) . "\" />\n";
            }

            $xml .= "  </url>\n";
        }

        return $xml . '</urlset>' . "\n";
    }

    /* ---------------------------------------------------------- источники */

    /** @return list<array{loc:string,lastmod:string,alternates:array<string,string>}> */
    private function fromDatabase(): array
    {
        $out = [];

        foreach ($this->pages->publishedForSitemap() as $row) {
            $locale = (string) $row['locale'];
            $slug = (string) $row['slug'];

            $alternates = [];
            foreach ($this->pages->alternates((int) $row['page_id']) as $altLocale => $altSlug) {
                $alternates[$this->site->localeMeta($altLocale)['html']] =
                    $this->site->absoluteUrl($altSlug, $altLocale);
            }

            $out[] = [
                'loc'        => $this->site->absoluteUrl($slug, $locale),
                'lastmod'    => substr((string) ($row['published_at'] ?? $row['updated_at'] ?? ''), 0, 10),
                'alternates' => $alternates,
            ];
        }

        return $out;
    }

    /** Резерв, пока контент не переехал в базу: адреса из карты маршрутов. */
    private function fromFiles(): array
    {
        $out = [];

        foreach ($this->routes as $key => $definition) {
            foreach ($definition['slug'] as $locale => $pattern) {
                if (str_contains($pattern, '{slug}')) {
                    $dir = APP_DIR . '/content/' . trim((string) ($definition['content_dir'] ?? $key), '/');

                    foreach (glob($dir . '/*.' . $locale . '.php') ?: [] as $file) {
                        $name = basename($file, '.' . $locale . '.php');
                        $out[] = ['loc' => $this->site->absoluteUrl(str_replace('{slug}', $name, $pattern), $locale)];
                    }

                    continue;
                }

                $file = APP_DIR . '/content/' . ($definition['content'] ?? $key) . '.' . $locale . '.php';

                if (is_file($file)) {
                    $out[] = ['loc' => $this->site->absoluteUrl($pattern, $locale)];
                }
            }
        }

        return $out;
    }
}
