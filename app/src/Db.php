<?php
declare(strict_types=1);

/**
 * Тонкая обёртка над PDO. Никакой ORM: на shared-хостинге важнее, чтобы
 * всё было очевидно и без зависимостей. Запросы — только подготовленные.
 */
final class Db
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** Настроено ли подключение (на этапе переезда база может отсутствовать). */
    public function isConfigured(): bool
    {
        return ($this->config['dsn'] ?? '') !== '';
    }

    /** Доступна ли база прямо сейчас. Используется страницей «Состояние». */
    public function isAvailable(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->pdo();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException('Подключение к базе данных не настроено (app/config.php).');
        }

        $this->pdo = new PDO(
            $this->config['dsn'],
            $this->config['user'] ?? '',
            $this->config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Если база лежит, публичная часть должна быстро перейти
                // на файловый резерв, а не ждать соединения
                PDO::ATTR_TIMEOUT            => 2,
            ]
        );

        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /** @return array<string,mixed>|null */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? $default : $value;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );

        $this->run($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(
            static fn (string $c): string => sprintf('`%s` = :set_%s', $c, $c),
            array_keys($data)
        ));

        $params = $whereParams;
        foreach ($data as $column => $value) {
            $params['set_' . $column] = $value;
        }

        return $this->run(sprintf('UPDATE `%s` SET %s WHERE %s', $table, $set, $where), $params)->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $params)->rowCount();
    }

    /** Выполняет замыкание в транзакции, откатывая её при любой ошибке. */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $work($this);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        try {
            $this->run('SELECT 1 FROM `' . $table . '` LIMIT 1');

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
