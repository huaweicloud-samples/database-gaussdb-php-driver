<?php

declare(strict_types=1);

namespace GaussDb\Compat;

use InvalidArgumentException;

/** Validated connection settings for the official Unicode ODBC driver. */
final class ConnectionConfig
{
    /** @var string */ public $host;
    /** @var int */ public $port;
    /** @var string */ public $database;
    /** @var string */ public $user;
    /** @var string */ public $password;
    /** @var string */ public $mode;
    /** @var string */ public $driver;
    /** @var string */ public $sslMode;
    /** @var string|null */ public $dsn;
    /** @var array */ public $pdoOptions;

    /**
     * @param string $mode CompatibilityMode::M/ORACLE or an alias accepted by CompatibilityMode::fromName().
     */
    public function __construct(
        string $host,
        int $port,
        string $database,
        string $user,
        string $password,
        string $mode,
        string $driver = 'GaussDB Unicode',
        string $sslMode = 'prefer',
        ?string $dsn = null,
        array $pdoOptions = array()
    ) {
        if ($host === '' || $database === '' || $user === '') {
            throw new InvalidArgumentException('Host, database, and user must not be empty');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Invalid port: {$port}");
        }
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->mode = CompatibilityMode::fromName($mode);
        $this->driver = $driver;
        $this->sslMode = $sslMode;
        $this->dsn = $dsn;
        $this->pdoOptions = $pdoOptions;
    }

    /** Build a safe DSN-less string or preserve a caller-managed DSN. */
    public function pdoDsn(): string
    {
        if ($this->dsn !== null && $this->dsn !== '') {
            return strncmp($this->dsn, 'odbc:', 5) === 0 ? $this->dsn : 'odbc:' . $this->dsn;
        }

        $parts = [
            'Driver=' . self::odbcDriver($this->driver),
            'Servername=' . self::odbcScalar($this->host),
            'Port=' . $this->port,
            'Database=' . self::odbcScalar($this->database),
            'SSLmode=' . self::odbcScalar($this->sslMode),
            'ConnSettings=set client_encoding=UTF8',
            // Request character metadata instead of the driver's SQL_BIT path.
            // ResultType::BOOLEAN performs the strict PHP conversion afterwards.
            'BoolsAsChar=1',
            'ByteaAsLongVarBinary=1',
        ];

        return 'odbc:' . implode(';', $parts);
    }

    /** Reject characters that could inject or ambiguously parse ODBC properties. */
    private static function odbcScalar(string $value): string
    {
        if (
            strpos($value, ';') !== false
            || strpos($value, '{') !== false
            || strpos($value, '}') !== false
            || strpos($value, "\0") !== false
        ) {
            throw new InvalidArgumentException('ODBC connection values must not contain semicolons, braces, or NUL bytes');
        }
        return $value;
    }

    /** Escape a driver name using ODBC brace syntax. */
    private static function odbcDriver(string $value): string
    {
        if ($value === '' || strpos($value, "\0") !== false) {
            throw new InvalidArgumentException('ODBC driver name must not be empty or contain NUL bytes');
        }
        return '{' . str_replace('}', '}}', $value) . '}';
    }
}
