# 客户端安装与部署

本套件不修改数据库内核，不捆绑厂商二进制，也不需要 Docker。
先由管理员准备运行时及 ODBC，再部署 PHP 源码。不要用 PostgreSQL ODBC 驱动替代 GaussDB 官方驱动。

## Linux

使用发行版支持的 PHP CLI、PDO_ODBC 和 unixODBC 软件包，包名和版本以实际发行版为准。
如果发行版缺少 PDO_ODBC，使用与已安装 PHP 完全相同的源码和 `phpize/php-config` 编译 `ext/pdo_odbc`，链接系统 unixODBC。
不需要编译 PDO_PGSQL，也不需要修改本项目 PHP 源码。

从授权渠道获取匹配服务端与 CPU 架构的官方 ODBC 包，按包内安装手册部署驱动及私有依赖。
不要覆盖系统 OpenSSL、libstdc++ 或 Driver Manager，不要为所有进程设置全局 `LD_LIBRARY_PATH`。
管理员按实际路径在 `odbcinst.ini` 注册 Unicode 驱动，例如：

```ini
[GaussDB Unicode]
Description=GaussDB Unicode ODBC Driver
Driver=/opt/gaussdb-odbc/odbc/lib/gsqlodbcw.so
FileUsage=1
```

路径仅为示例；以授权包布局为准。检查：

```bash
odbcinst -j
odbcinst -q -d -n 'GaussDB Unicode'
php -r 'var_export(PDO::getAvailableDrivers());'
```

预期可找到 Unicode 驱动且 PHP 列表包含 `odbc`。使用 `ldd` 检查可信厂商库，不能有 `not found`。
PHP-FPM 与 CLI 可能使用不同的 ini、扩展及环境变量；实际业务进程也要单独确认。
随后按 [README](../README.md#快速开始)部署兼容层。

## Windows

1. 安装官方 PHP，并启用与运行时版本、位数、TS/NTS 类型一致的 PDO_ODBC；确认当前进程读取了正确的 php.ini。
2. 安装匹配位数的官方 GaussDB ODBC：64 位 PHP 配 64 位 ODBC，32 位 PHP 配 32 位 ODBC。
3. 用对应位数的 ODBC 数据源管理器确认 `GaussDB Unicode` 已注册。
4. 打开 PowerShell，在源码目录执行以下步骤；安装目标父目录须已存在。

```powershell
php -r 'var_export(PDO::getAvailableDrivers());'
$env:GAUSS_COMPAT_INSTALL_DIR = 'C:\apps\gaussdb-php-compat'
php deploy/manage.php install
$env:GAUSS_HOST = 'gaussdb.example.com'
$env:GAUSS_PORT = '5432'
$env:GAUSS_DATABASE = 'app_m'
$env:GAUSS_MODE = 'M'
$env:GAUSS_USER = 'app_user'
$env:GAUSS_SSLMODE = 'verify-full'
$secret = Read-Host 'GaussDB password' -AsSecureString
$credential = New-Object System.Management.Automation.PSCredential('unused', $secret)
try {
    $env:GAUSS_PASSWORD = $credential.GetNetworkCredential().Password
    php examples/connect.php
    if ($LASTEXITCODE -ne 0) { throw 'Connection verification failed' }
} finally {
    Remove-Item Env:GAUSS_PASSWORD -ErrorAction SilentlyContinue
    $credential = $null
    $secret = $null
}
```

环境变量在子进程运行期间仍是明文，生产环境应采用受控凭据注入并限制进程访问。
卸载执行 `php deploy/manage.php uninstall` 并按提示确认，不会卸载共享 ODBC 驱动。

## TLS 与系统 DSN

示例默认 `verify-full`。按官方 ODBC 文档安装可信 CA、配置证书发现路径，并使用与证书匹配的主机名。
没有 TLS 成功验证的环境不能认定生产加密连接已就绪。
默认无 DSN 连接由兼容层加入 `ConnSettings=set client_encoding=UTF8`、`BoolsAsChar=1` 和 `ByteaAsLongVarBinary=1`。
若使用自定义 DSN/连接串，兼容层按原样使用，必须在其配置中设置这些属性及适当 TLS 策略，修改后重新连接。

## Composer

本项目未发布到 Packagist，不能直接从默认源 `composer require`。
已有应用可配置此公开 VCS 仓库，再固定已审核的提交；将 `COMMIT_SHA` 替换为实际完整提交：

```bash
composer config repositories.gaussdb-php-compat vcs https://github.com/huaweicloud-samples/database-gaussdb-php-driver.git
composer require 'huaweicloud-samples/gaussdb-php-compat:dev-main#COMMIT_SHA'
```

在应用的 composer.json 所在目录执行，并提交应用的 composer.lock。
仅使用已经包含兼容层的审核后提交；不要引用仍只有模板的分支状态。
运行时加载应用的 `vendor/autoload.php`。不使用 Composer 时直接使用本项目 `src/autoload.php`。
PHP 7.2 的 Composer 工具须选择兼容该 PHP 的工具分支，不把开发机 Composer 二进制直接搬到旧运行时。
