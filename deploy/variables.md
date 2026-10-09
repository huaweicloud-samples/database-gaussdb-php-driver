# 部署与运行变量

连接参数只从进程环境读取，不把 `.env`、密码文件或客户连接串提交到仓库。
部署脚本只安装本项目 PHP 源码，不创建云资源、不安装厂商驱动、不连接数据库。

| 变量 | 用途 | 必填/默认值 |
| --- | --- | --- |
| `GAUSS_COMPAT_INSTALL_DIR` | 独立安装目录；必须是绝对路径，父目录须已存在 | 安装/卸载必填；示例未设时从当前源码加载 |
| `GAUSS_COMPAT_CONFIRM` | 非交互卸载确认，必须为 `REMOVE` 加一个空格及脚本显示的规范化目标路径 | 未设时交互确认 |
| `GAUSS_HOST` | 数据库地址；应优先使用可校验证书的主机名 | 连接必填 |
| `GAUSS_PORT` | 数据库监听端口 | 连接必填 |
| `GAUSS_DATABASE` | 已创建的目标数据库 | 连接必填 |
| `GAUSS_USER` | 最小权限业务账号；示例只读取模式、编码和常量 | 连接必填 |
| `GAUSS_PASSWORD` | 数据库密码，由密钥系统注入或交互输入 | 连接必填，无默认值 |
| `GAUSS_MODE` | `M/MYSQL` 或 `A/O/ORA/ORACLE` | 连接必填 |
| `GAUSS_ODBC_DRIVER` | 操作系统中注册的 Unicode ODBC 驱动名 | `GaussDB Unicode` |
| `GAUSS_SSLMODE` | TLS 策略，支持 `verify-full/verify-ca/require/prefer/disable` | 示例默认 `verify-full` |

生产环境应按厂商说明配置 CA 信任、证书与主机名匹配。
`require` 不能替代完整的服务器身份校验；`prefer/disable` 仅用于经授权的隔离环境。
卸载只移除清单中哈希仍一致的本项目文件；文件改动或混入业务文件时会拒绝删除。
安装过程失败时会保留现场，不会自动递归删除目录；由管理员核对后处理。
