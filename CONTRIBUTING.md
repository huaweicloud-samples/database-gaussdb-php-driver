# 贡献指南

参与前请阅读并遵守 [行为准则](CODE_OF_CONDUCT.md)。安全问题请走 [私下报告渠道](SECURITY.md)，不要在公开 Issue 中附带漏洞利用细节或凭据。

## 贡献流程

1. 先通过 Bug Report 或 Feature Request 模板创建 Issue，或认领已有 Issue，与维护者确认范围。
2. Fork 本仓库，从最新 main 创建含义明确的英文分支。
3. 完成代码、使用文档和必要验证；不要提交厂商二进制、真实账号、客户数据或内部验收材料。
4. 使用真实贡献者身份执行 `git commit -s`，为每一个提交添加 DCO 签名。
5. 从 Fork 分支发起 PR，填写 PR 模板，关联 Issue，描述变更与验证结果。
6. 等待 CI 和维护者评审，按要求修改并重新签名提交。
7. 合并必须满足仓库分支保护：CI 通过、3 次批准且包含 CODEOWNERS 审核；维护者执行 Squash and Merge。

维护者将在 5 个工作日内首次响应，可能请求补充信息或修改。不绕过分支保护，不替其他人签名或审批自己的 PR。
Squash 合并后的提交也应保留有效的 Signed-off-by 记录。

## DCO

所有提交必须包含 `Signed-off-by: 姓名 <邮箱>`，例如通过以下命令自动添加：

```bash
git config user.name 'Your Name'
git config user.email 'your-verified-email@example.com'
git commit -s -m 'docs: clarify connection configuration'
```

签名前必须确认有权提交相关内容，并阅读 [Developer Certificate of Origin 1.1 全文](https://developercertificate.org/)。
DCO 是来源声明，不是 GPG 提交签名；CI 会核对签名与提交作者。

## 代码、文档与安全要求

- PHP 语法兼容 7.2.34；运行时和扩展选择必须同时考虑安全维护状态。
- 类、函数和关键逻辑应有说明；保持现有命名空间/API，不随意改写数据库语义。
- 使用参数绑定；连接信息只取自环境，日志必须脱敏；禁止新增 GPL/AGPL 代码或依赖。
- 部署脚本必须注明用途、用法、依赖；删除操作必须要求明确确认，禁止清理不属于本项目的资源。
- Markdown 文档维护相对链接、命令前置条件、预期输出和清理指引。
- CI 执行 DCO、Markdown lint、密钥扫描、PHP 语法和部署生命周期检查；它不代表真实数据库业务验收通过。
- 功能变更需提供脱敏的验证说明；不把历史报告中的通过率用于新的未验证组合。

## 许可证与评审

贡献将按仓库的 [Apache License 2.0](LICENSE) 分发；提交贡献即表示同意该许可，并确认拥有相应权利。
维护者核对代码来源、许可、安全、部署/清理步骤和文档一致性；责任人见 [CODEOWNERS](.github/CODEOWNERS)。
Issue 模板在 [.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE)，PR 模板在 [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md)。
