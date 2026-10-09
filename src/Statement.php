<?php

declare(strict_types=1);

namespace GaussDb\Compat;

use PDO;
use PDOStatement;
use UnexpectedValueException;

/** Parameterized execution and explicit result normalization. */
final class Statement
{
    /** @var PDOStatement */
    private $statement;

    /** @var string */
    private $mode;

    /** @var array<int|string, string> */
    private $resultTypes;

    /** @var string|null */
    private $sql;
    /** @var string|null */
    private $activeSql;
    /** @var callable|null */
    private $prepare;
    /** @var array<int|string, array> */
    private $bindings = [];
    /** @var int */
    private $defaultFetchMode;

    /** @param array<int|string, string> $resultTypes */
    public function __construct(PDOStatement $statement, string $mode, array $resultTypes = array(), ?string $sql = null, ?callable $prepare = null, int $defaultFetchMode = PDO::FETCH_ASSOC)
    {
        $this->statement = $statement;
        $this->mode = CompatibilityMode::fromName($mode);
        $this->resultTypes = $resultTypes;
        $this->sql = $this->activeSql = $sql;
        $this->prepare = $prepare;
        $this->defaultFetchMode = $defaultFetchMode;
    }

    /** Bind parameters and execute SQL using this adapter. */
    public function execute(array $parameters = []): bool
    {
        foreach ($parameters as $key => $value) {
            $parameter = is_int($key)
                ? $key + 1
                : (strncmp((string) $key, ':', 1) === 0 ? (string) $key : ':' . $key);
            $this->bindValue($parameter, $value);
        }
        if ($this->prepare !== null) {
            $sql = $this->sql;
            $bindings = $this->bindings;
            foreach ($bindings as [$value, $type]) {
                if (EmptyStringParameters::isEmpty($value, $type)) {
                    [$sql, $bindings] = EmptyStringParameters::compile($this->sql, $bindings);
                    break;
                }
            }
            if ($sql !== $this->activeSql) {
                $this->statement->closeCursor();
                $this->statement = ($this->prepare)($sql);
                $this->activeSql = $sql;
            }
            foreach ($bindings as $parameter => [$value, $type]) {
                $this->statement->bindValue($parameter, $value, $type);
            }
        }
        return $this->statement->execute();
    }

    /** @param int|string $parameter @param mixed $value */
    public function bindValue($parameter, $value, ?int $type = null): bool
    {
        if ($type === null) {
            [$value, $type] = $this->normalizeParameter($value);
        } elseif ($value instanceof BinaryValue) {
            [$value] = $this->normalizeParameter($value);
        }
        if ($this->prepare !== null) {
            $key = is_int($parameter) ? $parameter : ':' . ltrim((string) $parameter, ':');
            $this->bindings[$key] = [$value, $type];
            return true;
        }
        return $this->statement->bindValue($parameter, $value, $type);
    }

    /** @param int|null $mode @return mixed */
    public function fetch($mode = null)
    {
        if (!$this->resultTypes) {
            return $mode === null ? $this->statement->fetch() : $this->statement->fetch($mode);
        }
        // PDO::FETCH_DEFAULT is named only in newer PHP; 0 has the same meaning in 7.2.
        return $this->fetchMapped($mode === null || $mode === 0 ? $this->defaultFetchMode : $mode, 0);
    }

    /** @param int|null $mode */
    public function fetchAll($mode = null, ...$arguments): array
    {
        if (!$this->resultTypes) {
            return $mode === null ? $this->statement->fetchAll() : $this->statement->fetchAll($mode, ...$arguments);
        }
        $mode = $mode === null || $mode === 0 ? $this->defaultFetchMode : $mode;
        if (count($arguments) > ($mode === PDO::FETCH_COLUMN ? 1 : 0)) {
            throw new \InvalidArgumentException('Unsupported arguments for mapped fetchAll');
        }
        $column = $mode === PDO::FETCH_COLUMN && isset($arguments[0]) ? (int) $arguments[0] : 0;
        $this->validateMappedMode($mode, $column);
        $rows = [];
        // Fetch rows first: a mapped BOOLEAN false is data, not end-of-cursor.
        while (($row = $this->statement->fetch(PDO::FETCH_BOTH)) !== false) {
            $rows[] = $this->projectRow($row, $mode, $column);
        }
        return $rows;
    }

    /** @return mixed */
    public function fetchColumn(int $column = 0)
    {
        return !$this->resultTypes ? $this->statement->fetchColumn($column) : $this->fetchMapped(PDO::FETCH_COLUMN, $column);
    }

    /** Return the affected-row count reported by PDO. */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    /** Return the number of columns reported by PDO. */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /** Release the current result cursor. */
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /** Expose the current native statement; rewrites may replace this object. */
    public function nativeStatement(): PDOStatement
    {
        return $this->statement;
    }

    /** @param mixed $value */
    private function normalizeParameter($value): array
    {
        if ($value instanceof BinaryValue && $this->mode === CompatibilityMode::ORACLE) {
            return array(
                strtoupper(bin2hex($value->bytes)),
                PDO::PARAM_STR
            );
        }
        if ($value instanceof BinaryValue) {
            return array($value->bytes, PDO::PARAM_LOB);
        }
        if (is_bool($value)) {
            return array($value ? 1 : 0, PDO::PARAM_INT);
        }
        if (is_int($value)) {
            return array($value, PDO::PARAM_INT);
        }
        if ($value === null) {
            return array(null, PDO::PARAM_NULL);
        }
        return array($value, PDO::PARAM_STR);
    }

    /** Limit mapped reads to the supported fetch modes and column indices. */
    private function validateMappedMode(int $mode, int $column): void
    {
        if (!in_array($mode, [PDO::FETCH_ASSOC, PDO::FETCH_NUM, PDO::FETCH_BOTH, PDO::FETCH_OBJ, PDO::FETCH_COLUMN], true)) {
            throw new \InvalidArgumentException('ResultType mapping supports ASSOC, NUM, BOTH, OBJ and COLUMN; use nativeStatement() for other modes');
        }
        if ($column < 0) { throw new \InvalidArgumentException('Column index must not be negative'); }
    }

    /** @return mixed */
    private function fetchMapped(int $mode, int $column)
    {
        $this->validateMappedMode($mode, $column);
        $row = $this->statement->fetch(PDO::FETCH_BOTH);
        return $row === false ? false : $this->projectRow($row, $mode, $column);
    }

    /** @return mixed */
    private function projectRow(array $row, int $mode, int $column)
    {
        $names = array_values(array_filter(array_keys($row), 'is_string'));
        $values = array_filter($row, 'is_int', ARRAY_FILTER_USE_KEY);
        if (count($names) !== count($values)) {
            throw new UnexpectedValueException('ResultType mapping requires unique, non-numeric column aliases');
        }
        $types = [];
        foreach ($this->resultTypes as $key => $type) {
            if (is_int($key)) {
                $index = $key;
            } else {
                $index = array_search($key, $names, true);
                if ($index === false) {
                    $matches = array_keys(array_filter($names, static function (string $name) use ($key): bool {
                        return strcasecmp($key, $name) === 0;
                    }));
                    $index = count($matches) === 1 ? $matches[0] : false;
                }
            }
            if ($index === false || !array_key_exists($index, $values)) {
                throw new UnexpectedValueException('Mapped result column is missing or ambiguous: ' . $key);
            }
            if (isset($types[$index]) && $types[$index] !== $type) {
                throw new UnexpectedValueException('Conflicting result types for column: ' . $key);
            }
            $types[$index] = ResultType::validate($type);
        }
        foreach ($types as $index => $type) {
            if ($values[$index] !== null) { $values[$index] = self::normalizeResult($values[$index], $type); }
        }
        if ($mode === PDO::FETCH_COLUMN) {
            if (!array_key_exists($column, $values)) { throw new \InvalidArgumentException('Column index is out of range'); }
            return $values[$column];
        }
        if ($mode === PDO::FETCH_NUM) { return array_values($values); }
        $assoc = array_combine($names, array_values($values));
        if ($mode === PDO::FETCH_OBJ) { return (object) $assoc; }
        if ($mode === PDO::FETCH_BOTH) {
            foreach ($names as $index => $name) { $row[$name] = $row[$index] = $values[$index]; }
            return $row;
        }
        return $assoc;
    }

    /** @param mixed $value @return mixed */
    private static function normalizeResult($value, string $type)
    {
        $type = ResultType::validate($type);
        if ($type === ResultType::BOOLEAN) {
            return self::toBoolean($value);
        }
        return self::decodeHexBinary($value);
    }

    /** @param mixed $value */
    private static function toBoolean($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if ($value === false || $value === 0) {
            return false;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, array('1', 't', 'true'), true)) {
                return true;
            }
            if (in_array($normalized, array('0', 'f', 'false'), true)) {
                return false;
            }
        }
        throw new UnexpectedValueException('GaussDB boolean result is not a recognized 0/1 value');
    }

    /** @param mixed $value */
    private static function decodeHexBinary($value): string
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException('GaussDB binary result is not string or stream data');
        }
        if (strncmp($value, '\\x', 2) === 0) {
            $value = substr($value, 2);
        }
        if ($value === '') {
            return '';
        }
        if (strlen($value) % 2 !== 0 || !ctype_xdigit($value)) {
            throw new UnexpectedValueException('GaussDB ODBC binary result is not hexadecimal text');
        }
        $decoded = hex2bin($value);
        if ($decoded === false) {
            throw new UnexpectedValueException('Unable to decode GaussDB ODBC binary result');
        }
        return $decoded;
    }
}
