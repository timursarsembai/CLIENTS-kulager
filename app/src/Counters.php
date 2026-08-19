<?php
declare(strict_types=1);

/**
 * Счётчики посещаемости.
 *
 * Метрику и Google Analytics собираем сами из номера счётчика: вставлять
 * простыню кода руками незачем, а ошибиться в ней легко. Для остальных
 * сервисов есть поля со свободным кодом.
 *
 * Счётчики не подключаются редактору: считать собственные заходы в админку
 * и правку страниц не нужно.
 */
final class Counters
{
    /** Домены, с которых грузятся скрипты счётчиков — для политики безопасности. */
    private const HOSTS = [
        'metrika' => ['https://mc.yandex.ru'],
        'ga'      => ['https://www.googletagmanager.com', 'https://www.google-analytics.com'],
    ];

    public function __construct(private Settings $settings)
    {
    }

    /** Код для <head>: сами счётчики. */
    public function head(): string
    {
        $out = '';

        $metrika = $this->digits($this->settings->get('counter_metrika', ''));

        if ($metrika !== '') {
            $out .= $this->metrika($metrika);
        }

        $ga = $this->gaId($this->settings->get('counter_ga', ''));

        if ($ga !== '') {
            $out .= $this->googleAnalytics($ga);
        }

        $out .= trim($this->settings->get('counter_head', ''));

        return $out;
    }

    /** Код перед </body>: чаты, пиксели и прочее, что просят ставить внизу. */
    public function body(): string
    {
        return trim($this->settings->get('counter_body', ''));
    }

    public function used(): bool
    {
        return $this->head() !== '' || $this->body() !== '';
    }

    /**
     * Домены для политики безопасности: без них браузер не даст загрузить
     * скрипт счётчика, и он просто не заработает.
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        $hosts = [];

        if ($this->digits($this->settings->get('counter_metrika', '')) !== '') {
            $hosts = array_merge($hosts, self::HOSTS['metrika']);
        }

        if ($this->gaId($this->settings->get('counter_ga', '')) !== '') {
            $hosts = array_merge($hosts, self::HOSTS['ga']);
        }

        foreach (preg_split('~[\s,]+~', $this->settings->get('counter_hosts', '')) ?: [] as $host) {
            $host = trim($host);

            if ($host === '') {
                continue;
            }

            // Пускаем только имя домена: строка попадёт в заголовок ответа
            if (preg_match('~^(https?://)?[a-z0-9.-]+$~i', $host)) {
                $hosts[] = str_starts_with($host, 'http') ? $host : 'https://' . $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    /* ------------------------------------------------------------ сборка */

    private function metrika(string $id): string
    {
        return <<<HTML
        <script>
        (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
        (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
        ym({$id}, "init", { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true });
        </script>
        <noscript><div><img src="https://mc.yandex.ru/watch/{$id}" style="position:absolute; left:-9999px" alt=""></div></noscript>

        HTML;
    }

    private function googleAnalytics(string $id): string
    {
        return <<<HTML
        <script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{$id}');
        </script>

        HTML;
    }

    private function digits(string $value): string
    {
        return preg_replace('~\D~', '', $value) ?? '';
    }

    private function gaId(string $value): string
    {
        $value = trim($value);

        return preg_match('~^(G|UA|AW|GT)-[A-Z0-9-]+$~i', $value) ? strtoupper($value) : '';
    }
}
