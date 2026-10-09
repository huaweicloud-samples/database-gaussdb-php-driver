# API 与模式差异

`GaussDb\Compat` 是 PHP 兼容层而非新的 PDO 扩展。
以下连接配置来自环境变量；完整只读入口见 [examples/connect.php](../examples/connect.php)。

## 连接与模式

```php
use GaussDb\Compat\ConnectionConfig;
use GaussDb\Compat\Driver;

require getenv('GAUSS_COMPAT_INSTALL_DIR') . '/src/autoload.php';

$values = [];
foreach (['HOST', 'PORT', 'DATABASE', 'USER', 'PASSWORD', 'MODE'] as $key) {
    $value = getenv('GAUSS_' . $key);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing GAUSS_' . $key);
    }
    $values[$key] = $value;
}
$db = Driver::connect(new ConnectionConfig(
    $values['HOST'], (int) $values['PORT'], $values['DATABASE'],
    $values['USER'], $values['PASSWORD'], $values['MODE'],
    getenv('GAUSS_ODBC_DRIVER') ?: 'GaussDB Unicode',
    getenv('GAUSS_SSLMODE') ?: 'verify-full'
));
```

`CompatibilityMode` 是字符串常量类，不是 PHP enum。
`CompatibilityMode::M` 的值为 `M`，接受 `M/MYSQL`；`CompatibilityMode::ORACLE` 的值为 `ORA`，接受 `A/O/ORA/ORACLE`。
构造配置时自动归一化别名，连接后检查实际模式；原生配置类的 TLS 默认值为 `prefer`，生产调用应像示例一样显式指定策略。

## 参数化查询

以下业务表示意需由业务自行建模和授权，不会由部署脚本创建：

```php
$row = $db->execute('SELECT id, name FROM users WHERE id = ?', [1])->fetch();
$stmt = $db->prepare('UPDATE users SET name = :name WHERE id = :id');
$stmt->execute(['name' => '示例用户', 'id' => 1]);
```

`bindValue()` 不传类型时与 `execute()` 一致：整数绑定 INT、NULL 绑定 NULL、bool 绑定整数 0/1。
金额等精确数值建议以字符串传递，避免 PHP float 精度损失。不要拼接不可信 SQL 标识符或参数。
空字符串参数以固定 SQL 常量 `''` 绕过部分驱动的零长度绑定问题；其他参数仍绑定。
ORA 库可将空串视为 NULL，兼容层不伪造不同的数据库语义；空 stream 不在这一处理范围。
参数在 `execute()` 时实际绑定；不要混用兼容层和 `nativeStatement()->bindValue()/bindParam()`。
空串占位符改变时可能重新准备原生语句，原生对象及其设置不会自动迁移。

## 布尔与二进制结果

```php
use GaussDb\Compat\BinaryValue;
use GaussDb\Compat\ResultType;

$flags = $db->execute(
    'SELECT id, enabled FROM feature_flags WHERE id >= :id',
    ['id' => 1], ['enabled' => ResultType::BOOLEAN]
)->fetchAll();

$db->execute('INSERT INTO files (id, payload) VALUES (?, ?)',
    [1, new BinaryValue("A\x00B\xFF")]);
$bytes = $db->execute('SELECT payload FROM files WHERE id = ?',
    [1], [0 => ResultType::BINARY_HEX])->fetchColumn();
```

布尔结果接受 true/false、整数 0/1 及不区分大小写的 `0/1/t/f/true/false` 字符串，NULL 保持 NULL，未知值明确报错。
不传 `ResultType` 时不保证原生类型一致。字符传输是兼容处理，不是所有驱动组合的正确性保证。
二进制输入使用 `BinaryValue`，M 的 BLOB 使用原始字节，ORA 路径转换为十六进制。
`BINARY_HEX` 只标注明确的二进制列，不能用于任意普通字符串；字段类型按目标模式设计。

结果映射支持 `fetch/fetchAll/fetchColumn` 的 ASSOC、NUM、BOTH、OBJ、COLUMN 形式。
映射键可用列名或从 0 开始的列索引；查询需使用唯一、非纯数字的列别名。
`fetchAll(PDO::FETCH_COLUMN, 1)` 读取第二列；布尔单列 false 不能作为可靠的游标结束标记，建议整行读取或 fetchAll。
带映射的 CLASS、GROUP、UNIQUE 等其他模式被拒绝；不带映射时可使用原生 PDO 行为。

## 事务与原生访问

```php
$db->beginTransaction();
try {
    $db->execute('UPDATE accounts SET balance = balance - ? WHERE id = ?', ['10.00', 1]);
    $db->execute('UPDATE accounts SET balance = balance + ? WHERE id = ?', ['10.00', 2]);
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) { $db->rollBack(); }
    throw $error;
}
```

通过 `nativePdo()`、`nativeStatement()` 获取原生对象时，由调用方承担类型差异与状态管理。
兼容层不自动重连、不重试写入，不掩盖 PDOException，也不能撤销已提交事务。
