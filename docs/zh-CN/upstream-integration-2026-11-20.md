# 官方版本与本地定制整合记录

整合分支 `integration/xboard-custom` 基于 GitHub `master` 的 `d826a3b`。本地定制源自归档 `82d3496`，其父提交 `1375695` 与官方历史中的 `a0f6b32` 文件树一致。通过三方比较逐批移植定制改动。

| 冲突或差异 | 处理结果 |
| --- | --- |
| `OrderService.php`、`GiftCardService.php` | 保留官方行锁、邀请奖励和余额抵扣后的返佣计算。沿用 `surplus_credit`，同事务返还一次优惠券。重复取消保持官方的返回约定。 |
| `UniProxyController.php`、`ServerController.php`、`DeviceStateService.php` | 保留官方节点上报及机器鉴权，接入设备在线和增量明细。旧 IP 列表支持带端口的 IPv4/IPv6。用户总流量仍由汇总上报计入。 |
| `ClashMeta.php`、`SingBox.php` | 保留官方 XHTTP/ECH 与 Sing-box 策略组筛选，增加本地 Mihomo 识别、DNS 与 Reality 指纹、模板回退和格式转换。 |
| `Dockerfile`、`supervisord.conf`、`compose.sample.yaml`、`.gitignore` | 镜像使用检出的源码和管理后台子模块，保留官方 Caddy、Redis 与入口脚本。默认由 Web 角色运行调度。 |
| `composer.lock` | 保留项目的主版本约束，针对 Composer 审计结果更新锁定依赖，去除不再使用的库。 |
| `tests/TestCase.php`、`.github/workflows/ci.yml` | 采用官方 Laravel 测试基类。PHPUnit 使用内存 SQLite，财务测试夹具使用独立连接。CI 提供独立 Redis 服务用于设备与节点相关测试。 |

Flavor 和 Rocket 的源码与运行资源随镜像交付，套餐、邀请码、会话和设备接口与两套主题一并移植。

## 发布检查

1. 从整合提交检出代码并初始化 `public/assets/admin` 子模块。构建镜像后用 `XBOARD_IMAGE` 指定该镜像的标签或摘要。
2. 使用生产库的脱敏副本核对已执行的迁移。重点验证旧订单的 `refund_amount` 转为 `surplus_credit`，以及余额、优惠券、登录设备和历史订阅模板。
3. 确认只有一个调度实例。使用外部调度时，在 Web 容器设置 `ENABLE_SCHEDULER=false`。
4. 在预发布环境检查登录、套餐下单、支付通知、设备页面及实际客户端导入。使用旧版本回滚时，按对应代码和数据库结构一起恢复。
