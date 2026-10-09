<?php
/**
 * Read-only connection example. Usage: php examples/connect.php
 * Requires PHP >= 7.2.34, PDO_ODBC and a registered official Unicode ODBC driver.
 * Connection settings come exclusively from the environment; see deploy/variables.md.
 */
declare(strict_types=1);

use GaussDb\Compat\ConnectionConfig;
use GaussDb\Compat\Driver;

$installation = getenv('GAUSS_COMPAT_INSTALL_DIR');
require ($installation ? rtrim($installation, '/\\') : dirname(__DIR__)) . '/src/autoload.php';

/** Read a mandatory, nonempty environment value without displaying its contents. */
function connectionSetting(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') { throw new RuntimeException($name . ' is required'); }
    return $value;
}

try {
    $port = connectionSetting('GAUSS_PORT');
    if (!ctype_digit($port)) { throw new RuntimeException('GAUSS_PORT must be an integer'); }
    $sslMode = getenv('GAUSS_SSLMODE') ?: 'verify-full';
    if (!in_array($sslMode, ['verify-full', 'verify-ca', 'require', 'prefer', 'disable'], true)) {
        throw new RuntimeException('Unsupported GAUSS_SSLMODE');
    }
    $db = Driver::connect(new ConnectionConfig(
        connectionSetting('GAUSS_HOST'), (int) $port, connectionSetting('GAUSS_DATABASE'),
        connectionSetting('GAUSS_USER'), connectionSetting('GAUSS_PASSWORD'),
        connectionSetting('GAUSS_MODE'), getenv('GAUSS_ODBC_DRIVER') ?: 'GaussDB Unicode', $sslMode
    ));
    $value = $db->query('SELECT 1 AS connected')->fetchColumn();
    if ((string) $value !== '1') { throw new RuntimeException('Unexpected connection result'); }
    echo json_encode(['connected' => true, 'mode' => $db->mode]), PHP_EOL;
} catch (Throwable $error) {
    // Do not echo driver messages, which may reveal connection information.
    fwrite(STDERR, 'Connection verification failed (' . get_class($error) . '). Check environment and docs/troubleshooting.md.' . PHP_EOL);
    exit(1);
}
