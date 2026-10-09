<?php
/**
 * Install or remove only this PHP library, never a database or system ODBC driver.
 * Usage: GAUSS_COMPAT_INSTALL_DIR=/absolute/new/path php deploy/manage.php install|uninstall
 * Requires PHP >= 7.2.34 with JSON/hash and filesystem access. No Composer is required.
 * Removal requires an exact confirmation; see deploy/variables.md.
 */
declare(strict_types=1);

/** Return the closed allowlist of files owned by this installer. */
function deploymentFiles(): array
{
    $files = ['composer.json', 'LICENSE', 'NOTICE'];
    foreach (glob(dirname(__DIR__) . '/src/*.php') as $file) { $files[] = 'src/' . basename($file); }
    return $files;
}

/** Resolve an absolute target under an existing parent and reject broad/symlink targets. */
function deploymentTarget(): string
{
    $input = getenv('GAUSS_COMPAT_INSTALL_DIR');
    if (!$input || !preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $input)) {
        throw new RuntimeException('GAUSS_COMPAT_INSTALL_DIR must be an absolute path');
    }
    $input = rtrim(str_replace('\\', '/', $input), '/');
    $leaf = basename($input);
    $parent = realpath(dirname($input));
    if ($input === '' || preg_match('/^[A-Za-z]:$/', $input) || $parent === false || in_array($leaf, ['', '.', '..'], true)) {
        throw new RuntimeException('Use a dedicated child directory under an existing parent');
    }
    $target = $parent . DIRECTORY_SEPARATOR . $leaf;
    $root = realpath(dirname(__DIR__));
    $home = getenv('HOME') ?: getenv('USERPROFILE');
    if (is_link($input) || is_link($target) || $target === $parent || $target === $root ||
        ($home && realpath($target) === realpath($home))) {
        throw new RuntimeException('Refusing a root, home, source or symlink target');
    }
    return $target;
}

/** Install to a new directory, without overwriting or removing any existing content. */
function installLibrary(string $target): void
{
    if (file_exists($target)) { throw new RuntimeException('Target already exists; use a new release directory'); }
    $root = dirname(__DIR__);
    foreach (deploymentFiles() as $file) {
        if (!is_file($root . '/' . $file)) { throw new RuntimeException('Incomplete source package: ' . $file); }
    }
    if (!mkdir($target, 0755) || !mkdir($target . '/src', 0755)) { throw new RuntimeException('Cannot create install directory'); }
    $manifest = ['package' => 'huaweicloud-samples/database-gaussdb-php-driver', 'files' => []];
    foreach (deploymentFiles() as $file) {
        if (!copy($root . '/' . $file, $target . '/' . $file)) { throw new RuntimeException('Copy failed: ' . $file); }
        $manifest['files'][$file] = hash_file('sha256', $target . '/' . $file);
    }
    if (file_put_contents($target . '/.gaussdb-php-install.json', json_encode($manifest, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Cannot write installation manifest');
    }
    echo 'Installed: ', $target, PHP_EOL;
}

/** Remove only verified installer-owned files, after explicit target-specific consent. */
function uninstallLibrary(string $target): void
{
    if (!file_exists($target)) { echo "Nothing to remove\n"; return; }
    $marker = $target . '/.gaussdb-php-install.json';
    if (!is_file($marker) || is_link($marker) || !is_dir($target . '/src') || is_link($target . '/src')) {
        throw new RuntimeException('Not an installer-owned directory');
    }
    $manifest = json_decode(file_get_contents($marker), true);
    if (!is_array($manifest) || ($manifest['package'] ?? '') !== 'huaweicloud-samples/database-gaussdb-php-driver' ||
        !isset($manifest['files']) || !is_array($manifest['files']) || !$manifest['files']) {
        throw new RuntimeException('Invalid installation manifest');
    }
    $allowed = deploymentFiles();
    foreach ($manifest['files'] as $file => $hash) {
        if (!in_array($file, $allowed, true) || is_link($target . '/' . $file) || !is_file($target . '/' . $file) ||
            !is_string($hash) || hash_file('sha256', $target . '/' . $file) !== $hash) {
            throw new RuntimeException('Unknown, missing or modified file; nothing removed');
        }
    }
    foreach (['' => ['src', '.gaussdb-php-install.json'], 'src/' => []] as $prefix => $extra) {
        foreach (scandir($target . '/' . $prefix) as $entry) {
            if (in_array($entry, ['.', '..'], true) || in_array($entry, $extra, true)) { continue; }
            if (!isset($manifest['files'][$prefix . $entry])) { throw new RuntimeException('Unmanaged files present; nothing removed'); }
        }
    }
    $expected = 'REMOVE ' . $target;
    $confirmation = getenv('GAUSS_COMPAT_CONFIRM');
    if ($confirmation === false) {
        fwrite(STDOUT, 'Type exactly "' . $expected . '" to remove this library: ');
        $confirmation = rtrim((string) fgets(STDIN), "\r\n");
    }
    if ($confirmation !== $expected) { throw new RuntimeException('Removal not confirmed; nothing removed'); }
    foreach ($manifest['files'] as $file => $hash) {
        if (!unlink($target . '/' . $file)) { throw new RuntimeException('Cannot remove an owned file'); }
    }
    if (!rmdir($target . '/src') || !unlink($marker) || !rmdir($target)) { throw new RuntimeException('Cannot finish cleanup'); }
    echo 'Removed library only: ', $target, PHP_EOL;
}

try {
    if (PHP_VERSION_ID < 70234) { throw new RuntimeException('PHP >= 7.2.34 is required'); }
    $action = $argv[1] ?? '';
    if (!in_array($action, ['install', 'uninstall'], true)) { throw new RuntimeException('Usage: php deploy/manage.php install|uninstall'); }
    $target = deploymentTarget();
    if ($action === 'install') { installLibrary($target); } else { uninstallLibrary($target); }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
