<?php
declare(strict_types=1);

/**
 * Кэш готовых страниц в файлах.
 *
 * На shared-хостинге нет ни Redis, ни возможности держать процесс, поэтому
 * страница просто сохраняется в файл, а следующий посетитель получает его
 * без обращения к базе и без сборки блоков.
 *
 * Сбрасывается не удалением файлов, а сменой версии: каждая версия — свой
 * подкаталог, поэтому после правки в админке кэш перестаёт находиться сразу,
 * а прошлые каталоги подчищаются при следующих записях.
 */
final class PageCache
{
    private const LIFETIME = 86400;

    /** Сколько устаревших версий убираем за один заход, чтобы не подвесить запрос. */
    private const SWEEP_LIMIT = 5;

    private string $dir;
    private bool $enabled;

    public function __construct(private ?Settings $settings, string $dir, bool $enabled = true)
    {
        $this->dir = rtrim($dir, '/');
        $this->enabled = $enabled;
    }

    /**
     * Кэшируем только обычные показы страниц: у авторизованного редактора
     * должно быть видно правки сразу, а POST кэшировать нельзя вовсе.
     */
    public function usable(): bool
    {
        if (!$this->enabled || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }

        if (($_SERVER['QUERY_STRING'] ?? '') !== '') {
            return false;
        }

        // Редактор ходит по сайту со своей сессионной кукой — ему отдаём живое
        return !isset($_COOKIE[session_name()]);
    }

    public function get(string $key): ?string
    {
        if (!$this->usable()) {
            return null;
        }

        $file = $this->file($key);

        if (!is_file($file) || filemtime($file) + self::LIFETIME < time()) {
            return null;
        }

        $html = file_get_contents($file);

        return $html === false ? null : $html;
    }

    public function put(string $key, string $html): void
    {
        if (!$this->usable() || trim($html) === '') {
            return;
        }

        $dir = $this->versionDir();

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $file = $this->file($key);

        // Пишем через временный файл: иначе посетитель может получить половину
        $tmp = $file . '.' . getmypid() . '.tmp';

        if (file_put_contents($tmp, $html) === false) {
            return;
        }

        @rename($tmp, $file);
        $this->sweep();
    }

    /**
     * Сбрасывает весь кэш: следующая страница соберётся заново.
     *
     * Метка обязана меняться при каждом вызове. Секунд для этого мало:
     * две правки подряд укладываются в одну секунду, и кэш, записанный между
     * ними, остался бы действительным.
     */
    public function flush(): void
    {
        $this->settings?->set('cache_version', uniqid('', true));
    }

    /** Версия кэша: меняется при каждой правке в админке. */
    private function version(): string
    {
        return (string) ($this->settings?->get('cache_version', '0') ?? '0');
    }

    /**
     * Страницы одной версии лежат в своём подкаталоге. Так устаревшая версия
     * убирается одной командой, а не обходом тысячи файлов.
     */
    private function versionDir(): string
    {
        // В имя каталога пускаем только безопасные символы: версия приходит
        // из базы, а там могло оказаться что угодно
        $version = preg_replace('~[^a-z0-9.]~i', '', $this->version()) ?: '0';

        return $this->dir . '/v' . $version;
    }

    private function file(string $key): string
    {
        return $this->versionDir() . '/' . hash('xxh128', $key) . '.html';
    }

    /** Убирает каталоги прошлых версий — по нескольку за раз. */
    private function sweep(): void
    {
        $current = $this->versionDir();
        $removed = 0;

        foreach (glob($this->dir . '/v*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ($dir === $current) {
                continue;
            }

            foreach (glob($dir . '/*.html') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);

            if (++$removed >= self::SWEEP_LIMIT) {
                return;
            }
        }
    }
}
