# 订阅设备管理开发与变更记录

本文记录 2026-09-18 新增的订阅设备管理功能，供后续部署、节点适配、问题定位和回滚使用。该功能与 Sanctum 账号登录会话完全分离：账号登录会话管理浏览器登录令牌，订阅设备管理代理客户端及节点连接。

对应 Rocket 主题版本：`1.1.0`。

## 1. 功能范围

- 客户端首次拉取订阅时记录 IP、地区、客户端、版本、系统、设备型号和首次加入时间。
- 节点上报在线 IP 时建立订阅设备在线状态，并保留现有 Redis 设备数量限制。
- 增强节点可上报稳定设备 ID、设备型号和客户端信息。
- 增强节点可上报每台设备的实际上行、实际下行和按节点倍率计算的计费流量。
- 用户可在 Rocket 主题查看订阅设备并发起临时下线。
- 下线设备默认阻止 10 分钟，防止客户端自动重连。
- 原有用户总流量上报仍是唯一计费来源，单设备流量只用于分析展示，不会重复扣费。

## 2. 兼容性边界

### 2.1 无需升级节点即可获得

- 首次拉取订阅的时间。
- 订阅请求 IP 及 IPv4 地区。
- 能从 User-Agent 识别到的客户端、系统和粗略设备型号。
- 原节点 `alive` 上报提供的在线 IP、首次/最后在线时间和节点名称。

旧节点只上报 IP。同一公网出口下的多个设备可能被合并，设备更换公网 IP 后也可能产生新记录，这是 IP 识别本身的限制。

### 2.2 必须升级节点才能获得

- 跨 IP 稳定的设备身份。
- 节点侧识别的准确设备型号。
- 每台设备的精确流量。
- 真正关闭某台设备的现有连接。

面板会向已连接 WebSocket 的节点发送 `device.kick`。旧节点不处理该事件时，面板仍会将设备标为下线并从设备计数缓存中移除，但不能阻止共享 UUID 的客户端继续产生代理流量。

## 3. 数据库变更

迁移文件：

```text
database/migrations/2026_09_18_000001_create_subscription_device_tables.php
```

新增表：

| 表 | 作用 |
| --- | --- |
| `v2_user_device` | 设备主档、首次/最后出现、客户端信息、累计流量和临时封锁 |
| `v2_user_device_presence` | 设备在各节点的在线状态，避免单设备多节点状态互相覆盖 |
| `v2_stat_device` | 按设备、节点、倍率和日期汇总流量 |
| `v2_device_traffic_report` | 保存节点流量上报批次 ID，防止网络重试重复统计 |

设备功能未修改 `v2_user` 和 `v2_stat_user` 的计费口径。

## 4. 用户接口

接口均使用现有 `user` 中间件，只允许访问当前用户的数据。

```text
GET  /api/v1/user/device/fetch
GET  /api/v1/user/device/traffic?days=30
POST /api/v1/user/device/offline
```

下线请求：

```json
{
  "device_id": 12
}
```

下线响应中的 `enforcement` 有两个值：

- `node_push`：下线事件已发送到当前 WebSocket 节点。
- `panel_only`：只完成面板侧下线，节点未连接控制通道或需要升级。

## 5. 节点上报协议

### 5.1 兼容的在线设备上报

旧格式继续支持：

```json
{
  "alive": {
    "1001": ["1.2.3.4", "5.6.7.8"]
  }
}
```

增强格式：

```json
{
  "alive": {
    "1001": [
      {
        "device_id": "stable-device-id",
        "ip": "1.2.3.4",
        "name": "My iPhone",
        "model": "iPhone 16 Pro",
        "client_name": "Shadowrocket",
        "client_version": "2.2.60",
        "platform": "iOS",
        "user_agent": "Shadowrocket/2.2.60"
      }
    ]
  }
}
```

`device_id` 应由节点产生稳定标识，不应直接上传 IMEI、MAC 或其他敏感硬件编号。面板只保存 `SHA-256("id:" + device_id)`。

### 5.2 单设备流量上报

V2 合并上报字段：

```json
{
  "traffic": {
    "1001": [4096, 8192]
  },
  "device_traffic": {
    "1001": [
      {
        "device_id": "stable-device-id",
        "ip": "1.2.3.4",
        "u": 4096,
        "d": 8192
      }
    ]
  },
  "device_traffic_report_id": "node-12-1789720000-42"
}
```

`u` 和 `d` 必须是本次上报周期内的增量字节，不是累计计数。增强节点应为每批数据发送稳定且唯一的 `device_traffic_report_id`，相同批次重试时必须复用该值。也可以单独调用：

```text
POST /api/v1/server/UniProxy/deviceTraffic
POST /api/v2/server/deviceTraffic
```

WebSocket 事件：

```json
{
  "event": "report.device_traffic",
  "data": {
    "report_id": "node-12-1789720000-42",
    "data": {
      "1001": [
        {"device_id": "stable-device-id", "ip": "1.2.3.4", "u": 4096, "d": 8192}
      ]
    }
  }
}
```

设备流量不能替代原 `traffic` 字段。原字段继续负责用户流量扣减，`device_traffic` 仅负责设备维度拆分。未提供批次 ID 的旧节点仍兼容，但无法防止超时重试造成的设备统计重复。

### 5.3 节点下线事件

面板通过 WebSocket 向节点发送：

```json
{
  "event": "device.kick",
  "data": {
    "user_id": 1001,
    "device_key": "sha256-value",
    "ip": "1.2.3.4",
    "blocked_until": 1789720000
  }
}
```

节点收到后应立即关闭该用户与 IP/设备标识匹配的连接，并在 `blocked_until` 前拒绝重新连接。节点校验 `device_key` 时使用 `SHA-256("id:" + 原始 device_id)`。

## 6. 文件级变更清单

### 新增文件

| 文件 | 内容 |
| --- | --- |
| `database/migrations/2026_09_18_000001_create_subscription_device_tables.php` | 三张设备表及索引 |
| `app/Models/UserDevice.php` | 设备主档模型 |
| `app/Models/UserDevicePresence.php` | 节点在线状态模型 |
| `app/Models/StatDevice.php` | 单设备流量模型 |
| `app/Models/DeviceTrafficReport.php` | 流量上报批次去重模型 |
| `app/Console/Commands/CleanupSubscriptionDevices.php` | 设备历史和流量保留策略命令 |
| `app/Services/IpLocationService.php` | IP2Region 地区解析，结果缓存 30 天 |
| `app/Services/SubscriptionDeviceService.php` | 设备识别、在线、流量和下线核心逻辑 |
| `app/Http/Controllers/V1/User/DeviceController.php` | 用户设备接口 |
| `docs/zh-CN/subscription-device-management.md` | 本变更记录 |

### 修改文件

| 文件 | 修改内容 |
| --- | --- |
| `app/Services/DeviceStateService.php` | 在旧 Redis 在线逻辑旁路写入设备台账，异常时自动回退旧逻辑 |
| `app/Http/Controllers/V1/Client/ClientController.php` | 拉取订阅时记录设备；记录失败不影响订阅响应 |
| `app/Http/Controllers/V1/Server/UniProxyController.php` | 新增可选设备流量入口 |
| `app/Http/Controllers/V2/Server/ServerController.php` | 合并上报支持 `device_traffic` |
| `app/Http/Routes/V1/UserRoute.php` | 注册三条用户设备接口 |
| `app/Http/Routes/V1/ServerRoute.php` | 注册 V1 节点设备流量接口 |
| `app/Http/Routes/V2/ServerRoute.php` | 注册 V2 节点设备流量接口 |
| `app/WebSocket/NodeEventHandlers.php` | 处理 `report.device_traffic` |
| `app/WebSocket/NodeWorker.php` | 注册设备流量 WebSocket 事件 |
| `app/Models/User.php` | 增加 `subscriptionDevices` 关系 |
| `theme/Rocket/src/api/user.js` | 新增设备列表、流量和下线 API |
| `theme/Rocket/src/views/profile/Profile.vue` | 新增订阅设备表，登录会话保持独立 |
| `theme/Rocket/src/views/traffic/Traffic.vue` | 新增近 30 天设备流量汇总 |
| `theme/Rocket/package.json`、`package-lock.json`、`config.json` | 主题版本升级为 `1.1.0` |
| `theme/Rocket/dist`、`theme/Rocket/assets` | Vite 生产构建产物 |
| `public/theme/Rocket` | 当前环境实际加载的 Rocket 运行资源 |
| `docs/zh-CN/README.md` | 增加本文入口 |

设备在线索引现使用 `node_device_users:{nodeId}` Redis 集合，不再执行阻塞 Redis 的 `KEYS user_devices:*`。`online_count` 最多每 10 秒写库一次，强制下线除外。

默认每天 `03:30` 执行 `device:cleanup`，保留 180 天设备与设备流量数据。可手工调整：

```bash
php artisan device:cleanup --device-days=365 --traffic-days=365
```

## 7. 部署步骤

```bash
docker exec <web-container> php artisan migrate --force
docker exec <web-container> php artisan optimize:clear
docker exec <web-container> php artisan octane:reload
docker compose restart ws-server
```

Rocket 主题需要重新构建并将 `theme/Rocket/dist` 发布到 `public/theme/Rocket`，或上传新的 Rocket 主题包。

## 8. 排障顺序

1. 检查四张设备相关表是否存在。
2. 检查订阅拉取后 `v2_user_device` 是否新增记录。
3. 检查节点 `alive` 中是否带用户 UID 与 IP。
4. 精确型号缺失时检查节点是否上报扩展描述。
5. 单设备流量为空时检查 `device_traffic` 是否按增量上报。
6. 下线只显示 `panel_only` 时检查节点 WebSocket 和节点端 `device.kick` 实现。
7. 检查 `storage/logs/laravel.log` 中的 `Subscription device` 或 `Device traffic report` 日志。

## 9. 回滚

代码回滚时优先恢复本节“修改文件”，移除三条用户接口和三个节点扩展入口。新逻辑属于旁路功能，移除后原 Redis 在线计数及用户总流量统计仍可继续工作。

确认不再需要历史设备数据后，才执行迁移回滚删除三张新增表：

```bash
docker exec <web-container> php artisan migrate:rollback --force --step=1
```

执行回滚前必须确认该批次最后一个迁移确实是本功能迁移；生产环境建议先备份三张设备表，不要直接假设 `--step=1` 的目标。
