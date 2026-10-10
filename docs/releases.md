# 源码发行包

## 下载与内容

首版 [v0.1.0](https://github.com/huaweicloud-samples/database-gaussdb-php-driver/releases/tag/v0.1.0) 提供：

- `gaussdb-php-compat-v0.1.0.zip`：便于 Windows 解压，Linux 也可使用。
- `gaussdb-php-compat-v0.1.0.tar.gz`：便于 Linux 解压，与 ZIP 的文件内容一致。
- `SHA256SUMS`：两个发行包的 SHA-256 校验值。

两种包都包含 PHP 兼容层、安装/卸载工具、只读连接示例、客户文档和许可文件；不含内部验收脚本、测试报告、厂商安装包或开发工具依赖。
这是源码发行包，不是 `.exe`、`.dll` 或 `.so` 驱动安装包。PHP 源码不区分 Windows/Linux 或 CPU 架构，运行时及官方 ODBC 驱动必须与部署环境匹配。

最低语法兼容 PHP 7.2.34，但该版本已停止官方安全维护。部署前按 [安装指引](installation.md) 准备 PHP、PDO_ODBC 和官方 Unicode ODBC，Linux 还需 unixODBC。

## Linux 校验和解压

从同一发行页将 tar.gz 和 `SHA256SUMS` 下载到一个新目录，确认下列检查输出 `OK` 后再解压：

```bash
sha256sum --check --ignore-missing SHA256SUMS
tar -xzf gaussdb-php-compat-v0.1.0.tar.gz
cd gaussdb-php-compat-v0.1.0
```

macOS 使用 `shasum -a 256` 计算文件哈希，并与 `SHA256SUMS` 中对应文件的值逐字比较。

## Windows 校验和解压

从同一发行页将 ZIP 和 `SHA256SUMS` 下载到一个新目录。在 PowerShell 中执行，校验失败会停止：

```powershell
$archive = 'gaussdb-php-compat-v0.1.0.zip'
$entries = @(Get-Content .\SHA256SUMS | Where-Object { $_ -match ('^[0-9a-f]{64}  ' + [regex]::Escape($archive) + '$') })
if ($entries.Count -ne 1) { throw 'Missing or ambiguous checksum entry' }
$expected = ($entries[0] -split '\s+')[0]
$actual = (Get-FileHash -Algorithm SHA256 $archive).Hash
if ($actual -ine $expected) { throw 'Checksum mismatch; do not extract' }
Expand-Archive -Path $archive -DestinationPath .\unpacked
Set-Location .\unpacked\gaussdb-php-compat-v0.1.0
```

## 安装与使用

解压后按 [README 快速开始](../README.md#快速开始)检查 PDO_ODBC 并运行 `php deploy/manage.php install`，不用执行 Git 克隆步骤。
也可以在应用中直接加载解压目录的 `src/autoload.php`；路径应固定到版本目录。
配置环境变量后执行 `php examples/connect.php` 做只读连接检查，再参考 [API 使用](usage.md)接入业务。
升级请安装到新的独立目录；卸载沿用 README 中带明确确认的流程，不删除共享系统组件。

`v0.1.0` 是首个参考实现发行版，不是所有平台及数据库组合的生产认证。真实业务需要在目标环境完成验证。
SHA-256 用于核对下载文件完整性，不替代可信下载来源或数字签名。许可证为 [Apache-2.0](../LICENSE)。
