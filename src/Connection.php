<?php

declare(strict_types=1);

namespace GaussDb\Compat;

use PDO;
use RuntimeException;

/** PDO-backed connection with GaussDB mode and encoding checks. */
final class Connection
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    public $mode;

    /** Initialize the value object or adapter without changing database state. */
    public function __construct(PDO $pdo, string $mode)
    {
        $this->pdo = $pdo;
        $this->mode = CompatibilityMode::fromName($mode);
    }

    /** Prepare SQL with explicit optional result type mappings. */
    public function prepare(string $sql, array $resultTypes = []): Statement
    {
        $pdo = $this->pdo;
        return new Statement($pdo->prepare($sql), $this->mode, $resultTypes, $sql,
            static function (string $query) use ($pdo): \PDOStatement { return $pdo->prepare($query); },
            (int) $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    /** Execute SQL directly and wrap the returned PDO statement. */
    public function query(string $sql, array $resultTypes = []): Statement
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new RuntimeException('PDO returned false while executing a query');
        }
        return new Statement($statement, $this->mode, $resultTypes, null, null,
            (int) $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    /** Bind parameters and execute SQL using this adapter. */
    public function execute(string $sql, array $parameters = [], array $resultTypes = []): Statement
    {
        $statement = $this->prepare($sql, $resultTypes);
        $statement->execute($parameters);
        return $statement;
    }

    /** Execute SQL without a result set and return affected rows. */
    public function exec(string $sql): int
    {
        $count = $this->pdo->exec($sql);
        if ($count === false) {
            throw new RuntimeException('PDO returned false while executing SQL');
        }
        return $count;
    }

    /** Begin an explicit transaction on the underlying connection. */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** Commit the current transaction. */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /** Roll back the current transaction. */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /** Report whether PDO currently has an active transaction. */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /** Expose PDO for capabilities not wrapped by the compatibility layer. */
    public function nativePdo(): PDO
    {
        return $this->pdo;
    }

    /** Reject connections whose server-reported mode does not match. */
    public function assertCompatibilityMode(): void
    {
        $statement = $this->pdo->query(
            'SELECT datcompatibility FROM pg_database WHERE datname = current_database()'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to query GaussDB compatibility mode');
        }
        $actual = $statement->fetchColumn();
        if (!is_string($actual) || !CompatibilityMode::matchesDatabaseValue($this->mode, $actual)) {
            $shown = is_scalar($actual) ? (string) $actual : gettype($actual);
            throw new RuntimeException(
                "Connected database compatibility mode is {$shown}; expected {$this->mode}"
            );
        }
    }

    /** Reject connections not using UTF-8 client encoding. */
    public function assertUtf8ClientEncoding(): void
    {
        $statement = $this->pdo->query('SHOW client_encoding');
        if ($statement === false) {
            throw new RuntimeException('Unable to query GaussDB client encoding');
        }
        $encoding = $statement->fetchColumn();
        $normalized = strtoupper(str_replace(['-', '_'], '', (string) $encoding));
        if ($normalized !== 'UTF8') {
            throw new RuntimeException("GaussDB ODBC client encoding is not UTF8: {$encoding}");
        }
    }
}
