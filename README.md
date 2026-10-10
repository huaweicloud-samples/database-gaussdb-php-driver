# GaussDB PHP M/ORA 兼容接入套件

[![License: Apache-2.0](https://img.shields.io/badge/License-Apache--2.0-blue.svg)](LICENSE)
[![HUAWEI CLOUD Samples](https://img.shields.io/badge/HUAWEI%20CLOUD-Samples-red)](https://github.com/huaweicloud-samples)
[![CI](https://github.com/huaweicloud-samples/database-gaussdb-php-driver/actions/workflows/ci.yml/badge.svg)](https://github.com/huaweicloud-samples/database-gaussdb-php-driver/actions/workflows/ci.yml)
[![Status: incubating](https://img.shields.io/badge/status-incubating-orange)](https://github.com/huaweicloud-samples/database-gaussdb-php-driver)

本套件面向中文 PHP 开发者，演示如何通过官方 Unicode ODBC 驱动访问 GaussDB M 模式和 A/ORA 模式数据库，处理模式校验、UTF-8、布尔及二进制差异，不修改数据库内核。

这是教学与集成参考代码，不是厂商二进制驱动或生产支持承诺。生产使用前必须在实际运行时、数据库和驱动组合中完成充分验证、加固与优化。

## 目录

- [架构与能力](#架构与能力)
- [云服务与费用](#云服务与费用)
- [前置条件](#前置条件)
- [快速开始](#快速开始)
- [使用与验证](#使用与验证)
- [清理资源](#清理资源)
- [详细文档](#详细文档)
- [依赖与致谢](#依赖与致谢)
- [贡献与安全](#贡献与安全)
- [许可证](#许可证)
- [维护者与联系方式](#维护者与联系方式)

## 架构与能力

```text
PHP 业务代码 → GaussDb\Compat → PHP PDO_ODBC → 官方 Unicode ODBC → GaussDB
```

- `src/` 是实际交付的 PHP 兼容层，Windows/Linux 使用同一 API。
- M 模式接受数据库标识 `M/MYSQL`；A/ORA 模式也接受输入别名 `O`。
- 连接后核对数据库模式及 UTF-8，避免连错库或出现编码差异。
- 布尔结果使用显式 `ResultType::BOOLEAN`；二进制使用 `BinaryValue` 和 `BINARY_HEX`。
- 保留参数化 SQL、事务、保存点、SQLSTATE 与原生 PDO 访问能力。

不提供 `pdo_gaussdb.so`、`pdo_gaussdb.dll` 或 `gaussdb:` DSN。
本仓库不含厂商安装包、历史 PoC、内部验收脚本、测试报告或本地虚拟机材料。

## 云服务与费用

本示例使用客户已有的 GaussDB 实例，不自动创建 ECS、数据库或网络资源。
如自行购买 GaussDB/ECS，其计算、存储、备份和网络费用按所在区域实际计费。
本项目卸载命令不会停止云服务计费；请在确认数据已备份、无其他应用依赖后，通过云控制台释放自行创建的资源。

## 前置条件

1. 已有 M 或 A/ORA 数据库及最小权限账号；只读示例无需建表权限、管理员权限或云 AK/SK。
2. 运行机器可通过受控网络访问数据库；安全组只放行必要来源和端口，不向公网开放数据库。
3. PHP CLI、PDO、PDO_ODBC、ctype、JSON、hash。最低代码语法为 PHP 7.2.34；建议使用仍获安全维护且已在业务环境验证的运行时。
4. Linux 使用 unixODBC；Windows 使用系统 ODBC 管理器。PHP、扩展和厂商驱动位数/架构一致。
5. 自行从有授权的官方渠道获取与服务端、操作系统、部署形态匹配的 Unicode ODBC 驱动，注册名称默认为 `GaussDB Unicode`。
6. 本套件不限定云区域，实际以目标区域的服务可用性及网络策略为准。不需要 Huawei Cloud CLI；克隆源码时需要 Git。

PHP 7.2 已停止官方安全维护，语法兼容不等于安全支持。
客户端安装与证书准备见 [Linux/Windows 环境准备](docs/installation.md)。

## 快速开始

可从 [v0.1.0 发行页](https://github.com/huaweicloud-samples/database-gaussdb-php-driver/releases/tag/v0.1.0) 下载 ZIP 或 tar.gz 源码发行包。Windows/Linux 使用相同的 PHP 源码；包内不含 PHP、PDO_ODBC 扩展或厂商 ODBC 驱动。下载校验和解压步骤见 [发行包使用](docs/releases.md)，解压后可直接从下方 PHP 环境检查和安装步骤开始，无需克隆仓库。

以下以 Linux Bash 为例。仅使用已审核合并的源码提交，生产部署固定提交或发布标签；尚未合并的 PR 不是正式发行版。

```bash
git clone https://github.com/huaweicloud-samples/database-gaussdb-php-driver.git
cd database-gaussdb-php-driver
php -r 'var_export(PDO::getAvailableDrivers());'
# 预期包含 odbc
export GAUSS_COMPAT_INSTALL_DIR='/opt/gaussdb-php-compat'
php deploy/manage.php install
# 预期 Installed: /opt/gaussdb-php-compat
```

目标父目录须已存在且当前用户有写权限；目标目录必须是新的独立目录，不能是业务目录。
安装器只复制本项目源码和许可文件，不覆盖已有目录、不安装系统组件。Windows 命令见 [环境准备](docs/installation.md)。

## 使用与验证

设置配置后运行只读示例；密码交互输入，不写入命令历史或仓库：

```bash
export GAUSS_HOST='gaussdb.example.com'
export GAUSS_PORT='5432'
export GAUSS_DATABASE='app_m'
export GAUSS_MODE='M'
export GAUSS_USER='app_user'
export GAUSS_SSLMODE='verify-full'
read -r -s -p 'GaussDB password: ' GAUSS_PASSWORD
export GAUSS_PASSWORD
php examples/connect.php
unset GAUSS_PASSWORD
```

M 库预期输出 `{"connected":true,"mode":"M"}`，退出码为 0；ORA 库设置对应库名和 `GAUSS_MODE=O`，输出模式为 `ORA`。
示例只检查模式、编码并读取常量，不创建或删除任何表。
这仅证明基础连接可用，不代表业务功能、性能或所有兼容场景验收通过。

业务代码通过 `require '/opt/gaussdb-php-compat/src/autoload.php';` 加载。
连接配置、参数化查询和结果类型的完整示例见 [API 使用](docs/usage.md)，变量列表见 [部署变量](deploy/variables.md)。

## 清理资源

先停止应用对该安装目录的引用，再执行：

```bash
export GAUSS_COMPAT_INSTALL_DIR='/opt/gaussdb-php-compat'
php deploy/manage.php uninstall
# 按提示输入 REMOVE 加目标完整路径；未确认不会删除
```

卸载前校验安装清单、文件哈希及额外文件；发现修改、软链接或业务文件会拒绝清理。
不会删除数据库、表、ODBC 驱动、PHP、证书或任何云资源。保留业务依赖的共享组件。
升级请安装到新的版本目录，再按应用发布流程切换引用并重启长驻 PHP 进程。

## 详细文档

- [发行包下载、校验和解压](docs/releases.md)
- [Linux/Windows 安装与 Composer 接入](docs/installation.md)
- [API、参数绑定、事务和结果类型](docs/usage.md)
- [全部部署及运行变量](deploy/variables.md)
- [兼容边界与故障排除](docs/troubleshooting.md)

## 依赖与致谢

感谢 PHP/PDO、unixODBC、GaussDB 官方驱动及 Contributor Covenant 社区。
运行时 Composer 依赖图不引入第三方 PHP 包；`composer.lock` 固定该依赖图，PHP/扩展/厂商包仍需由部署方锁定并留存清单。
厂商 ODBC 与系统组件由使用方单独安装，许可证不因使用本示例而改变。详见 [NOTICE](NOTICE)。

## 贡献与安全

遵循 [贡献指南](CONTRIBUTING.md)和[行为准则](CODE_OF_CONDUCT.md)。所有变更经 DCO 签名、CI 检查和 PR 审核后合并。
普通问题使用仓库 Issue 模板；漏洞和敏感信息请按 [SECURITY.md](SECURITY.md) 私下报告。

## 许可证

本项目代码采用 [Apache License 2.0](LICENSE)，按原样提供，不作任何保证。
第三方组件及行为准则文本的归属和许可见 [NOTICE](NOTICE)。

## 维护者与联系方式

项目维护者：[@jarrenL](https://github.com/jarrenL)，责任人以 [.github/CODEOWNERS](.github/CODEOWNERS) 为准。
维护团队：HUAWEI CLOUD Samples 仓库维护者。
公共交流：[GitHub Issues](https://github.com/huaweicloud-samples/database-gaussdb-php-driver/issues)；社区邮箱：<huaweiCloudSamples@huawei.com>。
维护者将在 5 个工作日内首次响应；Incubating 阶段不构成商业支持 SLA。
