# 2026-11-18 代码归档记录

本次将当前工作区已完成的开发内容归档到 GitHub 仓库 `liusu2100-sys/Xboard`。

## 归档范围

- Flavor、Rocket 两套主题源码、依赖锁文件和现有运行资源。
- 活动会话、订阅设备识别、设备下线、设备流量记录及相关数据迁移。
- 支付、卡密、返佣、优惠券幂等处理。
- Mihomo、Sing-box、Hiddify、Shadowrocket、Stash 等订阅兼容改进。
- Docker scheduler、PHP 命令包装器、构建流程及依赖锁定。
- 中文项目介绍、开发指南、功能迁移方案、变更记录和自动化测试。

## Git 跟踪规则

此前 `theme/.gitignore` 只允许跟踪原始主题，导致 Flavor 和 Rocket 一直留在本机。
本次开放两套主题的源码与运行资源，同时忽略 `node_modules`、`dist`、环境文件和日志。

以下本机资料不进入归档：

- `.env`、服务密钥、数据库、Redis 数据、缓存和日志。
- `.agents`、`.codex`、`.aws` 本地工具目录。
- `20260607` 截图目录。
- `theme/Rocket/rocket_assets` 原站参考资料。

## 分支策略

当前本地代码基于 `13756956a6f34ea0793ef935402a9ec5ac2179f5`。
GitHub 仓库已有的 `master` 分支与本地代码没有共同祖先，且已有后续提交。
本次使用独立分支 `codex/project-snapshot-2026-11-18` 保存完整本地开发状态，保留远端 `master`。

该分支是当前开发成果的归档，不代表已经合并远端 `master` 的后续功能或修复。
后续恢复开发时，可基于本分支逐项评估升级和合并。
