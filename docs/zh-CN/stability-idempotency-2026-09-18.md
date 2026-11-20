# 稳定性与幂等优化变更记录

本文记录 2026-09-18 完成的订单、优惠券、卡密、返佣、Docker 调度、设备上报、依赖安全和自动化测试优化。部署或回滚时应以本文为准，不要只替换单个控制器。

## 1. 资金链路

### 1.1 支付与订单

- `OrderService::paid()` 在事务中锁定最新订单，只允许一个请求完成 `pending -> processing`。
- `OrderService::open()` 再次锁定订单，只允许 `processing` 状态执行套餐、余额和流量变更。
- 重复支付回调直接返回成功，不会重复派发开通任务。
- `OrderHandleJob` 不再使用事务外无效的 `lockForUpdate()`。
- 创建订单时锁定用户并再次检查未完成订单，降低并发创建重复订单的风险。

### 1.2 优惠券

- 优惠券仍在创建订单时预占库存，保持已展示价格可兑现。
- 待支付订单取消或超时取消时归还库存。
- `coupon_released_at` 保证重复取消不会重复归还。

### 1.3 礼品卡

- 兑换事务内重新锁定卡密和用户。
- 所有有效性、用户条件和使用次数判断均基于锁定后的最新数据。
- 奖励发放、卡密使用次数和兑换流水在同一事务内提交。

### 1.4 邀请返佣

- 每笔待发放订单在事务中重新查询并锁定。
- 发放前再次确认 `commission_status=1`。
- 每个订单、受益人和返佣层级生成 `idempotency_key`，数据库唯一索引提供第二层防重复保护。

## 2. 数据库迁移

```text
2026_09_18_000002_harden_financial_idempotency.php
2026_09_18_000003_create_device_traffic_reports_table.php
```

变更内容：

| 对象 | 变更 |
| --- | --- |
| `v2_order.coupon_released_at` | 标记优惠券库存已经归还 |
| `v2_commission_log.idempotency_key` | 唯一返佣发放键 |
| `v2_device_traffic_report` | 节点设备流量批次去重表 |

迁移已在当前开发数据库执行成功。生产部署必须先进入维护窗口，再执行迁移并重启常驻进程。

## 3. Docker 与 PHP

- `compose.yaml` 和示例编排新增 `scheduler` 服务，运行 `php artisan schedule:work`。
- Supervisor 镜像新增 scheduler 进程，可用 `ENABLE_SCHEDULER` 控制。
- Dockerfile 直接复制 CI 检出的源码，不再在构建过程中重新克隆可变分支。
- `composer.lock` 纳入版本控制，生产镜像使用锁定版本安装。
- `bin/xboard-artisan` 自动优先选择 `php82` 并拒绝 PHP 8.2 以下环境。
- 调度心跳缓存写入失败时不再阻断普通 Artisan 命令；实际运行 scheduler 仍必须配置可用的 Redis 缓存。
- 插件调度注册失败不再阻断订单、返佣、流量重置等核心任务；数据库恢复后应重启 scheduler 以重新加载插件任务。

宿主机当前默认 `php` 是 7.2，手工维护命令应使用：

```bash
./bin/xboard-artisan migrate --force
./bin/xboard-artisan optimize:clear
```

Docker 容器内的基础镜像已经是 PHP 8.2，可继续使用 `php artisan`。
宿主机若直接运行 scheduler，还必须给对应的 PHP 8.2 CLI 安装 `redis` 扩展；`fileinfo` 也属于 Composer 锁定依赖所需扩展。Dockerfile 已安装这两个扩展。

## 4. 设备链路

- 节点到用户的 Redis 集合索引替代全库 `KEYS` 扫描。
- 心跳更新时不再先将数据库在线状态全部写成离线。
- `v2_user.online_count` 写库恢复 10 秒节流。
- 设备和节点在线关系使用并发安全的 `firstOrCreate`/`upsert`。
- 订阅拉取设备写入按用户和设备节流 60 秒。
- 增强节点可发送 `report_id`，同一批设备流量重试不会重复累计。
- 单批最多接收 10000 条设备流量记录。
- 每日清理 180 天前的设备流量、上报批次和长期离线设备。

强制下线仍有兼容边界：旧节点不实现 `device.kick` 时只能完成面板侧下线。节点执行 ACK 需要节点项目同步升级，当前面板不会把“事件已发送”当成“节点已确认断开”。

## 5. 依赖与测试

- Laravel 从 `12.56.0` 更新至 `12.69.2`。
- Guzzle、CommonMark、Symfony、sodium_compat 等依赖更新至安全修复版本。
- Composer 在线审计由 38 条安全公告降为 0。
- 新增 PHPUnit 配置与资金幂等回归测试。
- 新增 GitHub Actions，自动运行后端 PHPUnit 与 Rocket `npm run build`。
- Rocket 生产构建已通过；主 JS 约 163 KB，CSS 约 116 KB。

本次验证结果：5 个测试、17 个断言通过；本次修改的资金、调度和设备核心 PHP 文件经 PHPStan 定向检查无错误。全项目仍有 20 余项既有 PHPStan 类型问题，未在本次业务修复中扩大修改范围。

## 6. 文件追踪

核心修改文件：

```text
app/Services/OrderService.php
app/Services/GiftCardService.php
app/Console/Commands/CheckCommission.php
app/Jobs/OrderHandleJob.php
app/Services/DeviceStateService.php
app/Services/SubscriptionDeviceService.php
app/Http/Controllers/V1/Server/UniProxyController.php
app/Http/Controllers/V2/Server/ServerController.php
app/WebSocket/NodeEventHandlers.php
app/Console/Kernel.php
Dockerfile
compose.yaml
compose.sample.yaml
.docker/supervisor/supervisord.conf
composer.json
composer.lock
phpunit.xml
.github/workflows/ci.yml
```

新增测试：

```text
tests/TestCase.php
tests/Feature/FinancialIdempotencyTest.php
tests/Unit/RuntimeRequirementsTest.php
```

已删除会输出首个用户 Token 的 `test_api.php` 和临时浏览器调试脚本 `test-console.js`。

## 7. 生产部署

```bash
docker compose pull
docker compose up -d web horizon scheduler ws-server redis
docker compose exec web php artisan migrate --force
docker compose exec web php artisan optimize:clear
docker compose exec web php artisan octane:reload
docker compose restart horizon scheduler ws-server
```

若线上使用单容器 Supervisor 镜像，确认环境变量 `ENABLE_SCHEDULER=true`。不要同时运行宿主机 cron 和容器 scheduler，否则虽然幂等保护能阻止重复资产变更，仍会产生无意义的重复任务竞争。

## 8. 回滚说明

1. 先恢复旧代码并停止 scheduler、Horizon 和 WebSocket 常驻进程。
2. 回滚 `000003` 会删除设备流量批次去重表，不影响用户总计费流量。
3. 回滚 `000002` 会删除优惠券释放时间和返佣唯一键。
4. 回滚后再启动旧进程。

生产数据库回滚前必须备份。不要在新代码仍运行时删除上述字段，否则取消订单和返佣任务会报错。
