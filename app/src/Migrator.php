<?php
declare(strict_types=1);

/**
 * Миграции схемы. Консоли на shared-хостинге нет, поэтому применяются
 * из админки: каждая миграция — файл app/migrations/NNN_name.php,
 * возвращающий замыкание function (Db $db): void.
 *
 * Применённые записываются в таблицу migrations и повторно не запускаются.
 */
final class Migrator
{
    private Db $db;
    private string $dir;

    public function __construct(Db $db, ?string $dir = null)
    {
        $this->db = $db;
        $this->dir = $dir ?? APP_DIR . '/migrations';
    }

    /** @return list<string> имена файлов, ещё не применённых */
    public function pending(): array
    {
        $applied = $this->applied();

        return array_values(array_filter(
            $this->available(),
            static fn (string $name): bool => !in_array($name, $applied, true)
        ));
    }

    /**
     * Применяет все неприменённые миграции по порядку.
     *
     * @return list<string> что было применено
     */
    public function migrate(): array
    {
        $this->ensureTable();

        $done = [];
        foreach ($this->pending() as $name) {
            $migration = require $this->dir . '/' . $name;

            if (!is_callable($migration)) {
                throw new RuntimeException('Миграция ' . $name . ' не вернула функцию.');
            }

            // Схему меняем вне транзакции: MySQL всё равно не откатывает DDL
            $migration($this->db);

            $this->db->insert('migrations', [
                'name'       => $name,
                'applied_at' => date('Y-m-d H:i:s'),
            ]);

            $done[] = $name;
        }

        return $done;
    }

    /** @return list<string> */
    public function applied(): array
    {
        if (!$this->db->tableExists('migrations')) {
            return [];
        }

        return array_column($this->db->all('SELECT name FROM migrations ORDER BY name'), 'name');
    }

    /** @return list<string> */
    public function available(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }

        $files = array_values(array_filter(
            scandir($this->dir) ?: [],
            static fn (string $f): bool => (bool) preg_match('~^\d+_.+\.php$~', $f)
        ));

        sort($files, SORT_STRING);

        return $files;
    }

    private function ensureTable(): void
    {
        $this->db->run(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                applied_at DATETIME NOT NULL,
                UNIQUE KEY name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
