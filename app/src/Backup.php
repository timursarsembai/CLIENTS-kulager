<?php
declare(strict_types=1);

/**
 * Резервная копия: дамп базы и, если получится, загруженные файлы одним архивом.
 *
 * На shared-хостинге нет ни консоли, ни mysqldump, поэтому дамп собираем
 * запросами и сразу отдаём в браузер потоком — так копия любого размера
 * не упирается в memory_limit.
 */
final class Backup
{
    private Db $db;
    private string $uploads;

    public function __construct(Db $db, ?string $uploads = null)
    {
        $this->db = $db;
        $this->uploads = $uploads ?? (defined('PUBLIC_DIR') ? PUBLIC_DIR : dirname(APP_DIR) . '/public_html')
            . '/assets/uploads';
    }

    public function canZip(): bool
    {
        return class_exists('ZipArchive');
    }

    /** Отдаёт архив (или один SQL-файл, если ZipArchive недоступен). */
    public function send(): void
    {
        $stamp = date('Y-m-d_H-i');

        if (!$this->canZip()) {
            $this->sendSql('kulager-' . $stamp . '.sql');

            return;
        }

        $file = tempnam(sys_get_temp_dir(), 'kulager-backup');

        if ($file === false) {
            $this->sendSql('kulager-' . $stamp . '.sql');

            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            @unlink($file);
            $this->sendSql('kulager-' . $stamp . '.sql');

            return;
        }

        $zip->addFromString('database.sql', $this->dump());

        foreach ($this->uploadFiles() as $path => $name) {
            $zip->addFile($path, 'uploads/' . $name);
        }

        $zip->addFromString('README.txt', $this->readme($stamp));
        $zip->close();

        $name = 'kulager-' . $stamp . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($file));

        readfile($file);
        @unlink($file);
    }

    /* ------------------------------------------------------------ внутреннее */

    private function sendSql(string $name): void
    {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');

        echo $this->dump();
    }

    /** @return array<string,string> путь на диске => имя в архиве */
    private function uploadFiles(): array
    {
        $out = [];

        foreach (glob($this->uploads . '/*') ?: [] as $path) {
            if (is_file($path) && basename($path) !== '.htaccess') {
                $out[$path] = basename($path);
            }
        }

        return $out;
    }

    private function dump(): string
    {
        $sql = "-- Резервная копия KULAGER, " . date('d.m.Y H:i') . "\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($this->tables() as $table) {
            $create = $this->db->first('SHOW CREATE TABLE `' . $table . '`');
            $sql .= "DROP TABLE IF EXISTS `$table`;\n"
                . ($create['Create Table'] ?? '') . ";\n\n";

            $sql .= $this->rows($table);
        }

        return $sql . "SET FOREIGN_KEY_CHECKS = 1;\n";
    }

    /** @return list<string> */
    private function tables(): array
    {
        $out = [];

        foreach ($this->db->all('SHOW TABLES') as $row) {
            $out[] = (string) reset($row);
        }

        return $out;
    }

    private function rows(string $table): string
    {
        $rows = $this->db->all('SELECT * FROM `' . $table . '`');

        if ($rows === []) {
            return '';
        }

        $sql = '';
        $pdo = $this->db->pdo();

        foreach ($rows as $row) {
            $values = array_map(
                static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                $row
            );

            $sql .= 'INSERT INTO `' . $table . '` (`' . implode('`, `', array_keys($row)) . '`) VALUES ('
                . implode(', ', $values) . ");\n";
        }

        return $sql . "\n";
    }

    private function readme(string $stamp): string
    {
        return "Резервная копия сайта KULAGER от $stamp\n\n"
            . "database.sql — дамп базы. Восстановление: создать пустую базу\n"
            . "и импортировать файл через phpMyAdmin или mysql < database.sql\n\n"
            . "uploads/ — файлы из assets/uploads. Положить обратно в тот же каталог.\n\n"
            . "Тексты страниц лежат в базе. Шаблоны, стили и файлы app/ в копию\n"
            . "не входят — они есть в исходниках проекта.\n";
    }
}
