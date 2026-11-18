# Xboard 项目介绍文档

本文档面向接手 Xboard 项目的产品、运维、后端、前端与主题开发人员，目标是说明这个项目“是什么、由哪些部分组成、核心业务如何流转、线上如何运行、二次开发应该先理解哪些边界”。

本文基于当前仓库代码整理，重点覆盖当前项目中已经存在并正在使用的模块，包括 Laravel 12 后端、Octane 常驻进程、Horizon 队列、Workerman WebSocket、V1/V2 API、订阅协议生成、插件系统、主题系统，以及当前正在开发的 `Flavor` 与 `Rocket` 主题。

## 1. 项目定位

Xboard 是一个代理订阅和节点管理面板。它的核心职责不是单纯展示节点列表，而是围绕“用户、套餐、订单、支付、节点、订阅、流量统计、工单、邀请返佣、后台管理”形成一套完整的商业闭环。

项目主要解决这些问题：

- 用户注册、登录、找回密码、邮箱验证、登录设备管理。
- 套餐展示、购买、续费、升级、余额抵扣、优惠码、试用套餐。
- 订单创建、支付回调、订单开通、订阅状态更新。
- 节点配置管理、节点权限分组、节点在线状态、节点上报流量。
- 根据客户端类型动态生成订阅内容，例如 Clash Meta、Stash、Surge、Surfboard、Sing-box、Hiddify、NekoBox、V2rayNG、Loon 等。
- 用户侧前端主题渲染与后台主题上传、切换、配置。
- 后台管理用户、套餐、节点、订单、支付、公告、知识库、礼品卡、优惠码、工单、系统状态。
- 插件化扩展支付、通知、功能钩子和自定义业务。

## 2. 技术栈

后端：

- PHP `^8.2`
- Laravel `^12.0`
- Laravel Octane `2.11.*`
- Laravel Horizon `^5.30`
- Laravel Sanctum `^4.0`
- Workerman `^5.1`
- Redis
- MySQL 或 SQLite
- Guzzle、Symfony HTTP Client、Symfony YAML
- Bacon QR Code，用于二维码生成
- Spatie DB Dumper，用于数据库备份

前端与主题：

- 用户前端主题以 Vue 3 为主。
- Rocket 主题使用 Vue 3、Vue Router、Pinia、Axios、Vite。
- 原主题 `Xboard` 为系统默认主题。
- 当前新增主题包括 `Flavor` 与 `Rocket`。
- 后台前端由 Laravel Blade 加载 `resources/views/admin.blade.php`，README 中描述为 React + Shadcn UI 风格。

运行方式：

- Docker Compose 管理服务。
- `web` 服务运行 Laravel Octane，默认监听 `7001`。
- `horizon` 服务运行队列。
- `ws-server` 服务运行 WebSocket。
- `redis` 服务通过 Unix Socket 提供 Redis。

## 3. 运行拓扑

当前 `compose.yaml` 定义了四个服务。

| 服务 | 作用 | 关键命令 |
| --- | --- | --- |
| `web` | Laravel Octane HTTP 服务 | `php artisan octane:start --port=7001 --host=0.0.0.0` |
| `horizon` | 队列消费者 | `php artisan horizon` |
| `ws-server` | WebSocket 服务 | `php artisan ws-server start` |
| `redis` | Redis 数据与锁 | `redis-server --unixsocket /data/redis.sock --unixsocketperm 777` |

线上通常由 Nginx、aaPanel 或 1Panel 把域名流量反向代理到 `127.0.0.1:7001`。

Octane 是常驻进程，和传统 PHP-FPM 最大区别是：代码和部分对象会驻留内存。因此修改 PHP 路由、控制器、服务、协议生成器、配置读取逻辑后，通常需要执行：

```bash
docker compose exec web php artisan octane:reload
```

当前环境使用容器名时，也可以执行：

```bash
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

## 4. 目录结构

### 4.1 根目录

| 路径 | 说明 |
| --- | --- |
| `app/` | Laravel 后端核心代码 |
| `app/Http/Routes/` | V1/V2 API 路由模块 |
| `app/Http/Controllers/` | 控制器，分 V1、V2、用户、后台、节点、客户端 |
| `app/Services/` | 业务服务层，订单、用户、套餐、节点、插件、主题等核心逻辑 |
| `app/Models/` | Eloquent 模型 |
| `app/Protocols/` | 订阅协议生成器 |
| `app/Console/Commands/` | Artisan 命令和计划任务命令 |
| `app/Providers/` | 服务提供者，路由、Octane、插件、协议、设置等 |
| `database/migrations/` | 数据库结构迁移 |
| `plugins/` | 插件目录 |
| `resources/rules/` | Clash、Clash Meta、Surge、Surfboard、Sing-box 等订阅模板 |
| `resources/lang/` | 后端多语言 JSON |
| `resources/views/` | Blade 视图，包含后台入口 |
| `theme/` | 用户前端主题源码 |
| `public/theme/` | 主题运行目录，线上浏览器实际加载这里的文件 |
| `docs/` | 项目文档 |
| `compose.yaml` | Docker Compose 服务定义 |
| `Dockerfile` | 镜像构建定义 |

### 4.2 后端分层

Xboard 后端大体分为：

- Controller：处理 HTTP 请求、参数验证、调用服务、返回标准响应。
- Service：承载业务规则和事务，例如订单开通、套餐校验、节点配置、用户流量。
- Model：数据库实体和字段转换。
- Middleware：认证、节点 token 校验、语言、JSON 强制响应、审计日志、Octane 事务状态清理。
- Provider：系统启动时注册路由、插件、协议、Octane tick 等机制。
- Job/Command：异步任务和周期性检查任务。

## 5. 路由体系

### 5.1 Web 路由

`routes/web.php` 主要负责三件事：

1. 渲染用户前台主题。
2. 渲染后台入口。
3. 提供短订阅路径。

根路径 `/` 的主题渲染流程：

1. 读取 `admin_setting('frontend_theme', 'Xboard')`。
2. 使用 `ThemeService` 检查主题是否存在。
3. 如果 `public/theme/{theme}` 不存在，则从主题源码目录复制一份到公开目录。
4. 读取主题配置。
5. 渲染 `theme::{theme}.dashboard`。

后台入口：

```php
/{secure_path}
```

`secure_path` 来自：

```php
admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
```

订阅短路径：

```text
/{subscribe_path}/{token}
```

默认 `subscribe_path` 为 `s`。

### 5.2 API 路由

API 由 `App\Providers\RouteServiceProvider` 注册：

```text
/api/v1
/api/v2
```

每个版本都会扫描：

```text
app/Http/Routes/V1/*.php
app/Http/Routes/V2/*.php
```

这种设计的好处是路由按业务模块分散，避免单个 `api.php` 无限膨胀。

### 5.3 V1 API

V1 主要承载用户端、认证端、客户端订阅和节点兼容接口。

| 路由类 | 前缀 | 说明 |
| --- | --- | --- |
| `PassportRoute` | `/api/v1/passport` | 注册、登录、找回密码、邮件验证码、快速登录 |
| `GuestRoute` | `/api/v1/guest` | 游客配置、游客套餐、支付回调、Telegram webhook |
| `UserRoute` | `/api/v1/user` | 用户中心、套餐、订单、邀请、工单、节点、礼品卡、知识库、流量统计 |
| `ClientRoute` | `/api/v1/client` | 客户端订阅兼容接口、App 配置 |
| `ServerRoute` | `/api/v1/server` | 节点拉配置、拉用户、推流量、在线状态 |

用户端常见接口：

| 接口 | 说明 |
| --- | --- |
| `GET /api/v1/user/info` | 获取用户基础信息 |
| `GET /api/v1/user/getSubscribe` | 获取订阅信息、订阅链接、套餐信息 |
| `GET /api/v1/user/subscribe/qrcode` | 生成订阅二维码 |
| `GET /api/v1/user/getActiveSession` | 获取登录设备 |
| `POST /api/v1/user/removeActiveSession` | 移除登录设备 |
| `GET /api/v1/user/plan/fetch` | 获取可购买套餐 |
| `POST /api/v1/user/order/save` | 创建订单 |
| `POST /api/v1/user/order/checkout` | 订单支付 |
| `GET /api/v1/user/order/fetch` | 获取订单列表 |
| `POST /api/v1/user/ticket/save` | 新建工单 |
| `POST /api/v1/user/ticket/reply` | 回复工单 |
| `GET /api/v1/user/invite/save` | 生成邀请码 |
| `POST /api/v1/user/invite/delete` | 删除邀请码 |

### 5.4 V2 API

V2 主要承载新版后台、节点握手和少量客户端接口。

| 路由类 | 前缀 | 说明 |
| --- | --- | --- |
| `V2\AdminRoute` | `/api/v2/{secure_path}` | 后台管理接口 |
| `V2\ServerRoute` | `/api/v2/server` | 节点握手、上报、UniProxy 兼容接口 |
| `V2\ClientRoute` | `/api/v2/client` | 客户端 App 配置 |
| `V2\PassportRoute` | `/api/v2/passport` | 复用 V1 登录注册逻辑 |
| `V2\UserRoute` | `/api/v2/user` | 精简用户接口 |

后台接口受这些中间件保护：

```php
['admin', 'log']
```

其中 `log` 对后台 POST 操作写入 `AdminAuditLog`，并过滤敏感字段。

## 6. 认证和会话

### 6.1 用户认证

当前项目使用 Laravel Sanctum Token 作为用户 API 认证基础。

登录流程：

1. 前端调用 `/api/v1/passport/auth/login`。
2. `AuthController` 调用 `LoginService` 校验账号密码。
3. 成功后由 `AuthService::generateAuthData()` 创建 Sanctum token。
4. 返回格式中包含 `auth_data`，前端保存到 `localStorage`。
5. 后续请求通过 `Authorization: Bearer xxx` 访问用户接口。

### 6.2 密码校验

`LoginService` 使用 `Helper::multiPasswordVerify()` 兼容旧密码算法和新密码哈希。

失败次数限制由后台配置控制：

- `password_limit_enable`
- `password_limit_count`
- `password_limit_expire`

### 6.3 活动会话

项目扩展了 `personal_access_tokens`，用于记录登录设备信息：

- `ip_address`
- `user_agent`
- `device_name`
- `browser`
- `platform`

相关服务：

```text
app/Services/AuthService.php
```

相关接口：

```text
GET  /api/v1/user/getActiveSession
POST /api/v1/user/removeActiveSession
```

如果历史 token 没有这些字段，系统会尽量通过 token 名称回填或标记为 legacy session。

## 7. 核心业务模块

### 7.1 用户模块

主要模型：

```text
app/Models/User.php
```

主要服务：

```text
app/Services/UserService.php
app/Services/AuthService.php
app/Services/Auth/LoginService.php
app/Services/Auth/RegisterService.php
```

用户关键字段：

| 字段 | 说明 |
| --- | --- |
| `email` | 登录邮箱 |
| `password` | 哈希密码 |
| `uuid` | 节点使用的用户 UUID |
| `token` | 订阅 token |
| `plan_id` | 当前套餐 |
| `group_id` | 节点权限组 |
| `u` / `d` | 上传/下载流量 |
| `transfer_enable` | 可用总流量 |
| `expired_at` | 到期时间，`NULL` 通常代表长期或按量套餐 |
| `balance` | 余额 |
| `commission_balance` | 佣金余额 |
| `speed_limit` | 用户速度限制 |
| `device_limit` | 设备限制 |

可用用户判断逻辑在 `UserService::isAvailable()`：

- 未封禁。
- 有可用流量。
- 未过期或 `expired_at` 为 `NULL`。

### 7.2 套餐模块

主要模型：

```text
app/Models/Plan.php
```

主要服务：

```text
app/Services/PlanService.php
```

套餐展示和购买规则：

- 新购：要求 `show = true`、`sell = true`、容量未满。
- 续费：如果用户当前套餐相同，要求 `renew = true`、`sell = true`。
- 已下架但允许续费的套餐，对当前持有用户仍可续费。
- `capacity_limit` 不为空时，会按当前有效用户数量判断是否售罄。

套餐周期采用新版周期 key，同时兼容旧版周期映射。常见周期包括：

- 月付
- 季付
- 半年付
- 年付
- 两年付
- 三年付
- 一次性
- 流量重置包

### 7.3 订单模块

主要模型：

```text
app/Models/Order.php
```

主要服务：

```text
app/Services/OrderService.php
```

订单创建流程：

1. 用户选择套餐和周期。
2. `PlanService::validatePurchase()` 校验套餐可购买性。
3. `OrderService::createFromRequest()` 创建订单。
4. 可选应用优惠码。
5. 应用用户 VIP 折扣。
6. 判断订单类型：新购、续费、升级、流量重置。
7. 计算升级剩余价值。
8. 设置邀请返佣。
9. 使用余额抵扣。
10. 保存订单。

订单支付完成流程：

1. 支付插件或回调调用订单支付逻辑。
2. 订单状态从待支付变为处理中。
3. 分发 `OrderHandleJob`。
4. `OrderService::open()` 根据订单类型开通套餐。
5. 更新用户套餐、流量、到期时间、速度限制、设备限制。
6. 订单状态变为完成。

### 7.4 支付模块

支付模型：

```text
app/Models/Payment.php
```

支付服务：

```text
app/Services/PaymentService.php
```

插件目录中已有多个支付插件：

```text
plugins/AlipayF2f
plugins/Btcpay
plugins/CoinPayments
plugins/Coinbase
plugins/Epay
plugins/Mgate
```

支付回调入口：

```text
/api/v1/guest/payment/notify/{method}/{uuid}
```

支付扩展建议优先走插件系统，不建议把第三方支付逻辑硬编码进核心控制器。

### 7.5 节点模块

主要模型：

```text
app/Models/Server.php
app/Models/ServerGroup.php
app/Models/ServerRoute.php
app/Models/ServerStat.php
app/Models/StatServer.php
```

主要服务：

```text
app/Services/ServerService.php
app/Services/NodeSyncService.php
app/Services/NodeRegistry.php
```

节点对接接口主要有两代：

- V1 兼容接口：`/api/v1/server/...`
- V2 新接口：`/api/v2/server/handshake`、`/api/v2/server/report`

节点鉴权通过 `server` 中间件完成，核心校验项：

- `token` 必须等于后台配置 `server_token`。
- `node_id` 必填。
- `node_type` 需要是合法节点类型。

节点可用用户由 `ServerService::getAvailableUsers()` 决定：

- 用户 group_id 在节点 group_ids 中。
- 用户流量未用尽。
- 用户未过期或长期有效。
- 用户未封禁。

### 7.6 订阅模块

订阅入口：

```text
GET /s/{token}
GET /api/v1/client/subscribe?token=...
```

中间件：

```text
client
```

核心控制器：

```text
app/Http/Controllers/V1/Client/ClientController.php
```

核心流程：

1. 根据 token 找到用户。
2. 判断用户是否可用。
3. 获取用户可用节点。
4. 根据 `types` 参数过滤节点协议。
5. 根据 `filter` 参数过滤节点名称或标签。
6. 根据 `flag` 参数或 User-Agent 匹配协议生成器。
7. 调用对应 `app/Protocols/*.php` 的 `handle()` 输出订阅。

支持的协议生成器：

| 类 | flags |
| --- | --- |
| `Clash` | `clash` |
| `ClashMeta` | `meta`, `verge`, `flclash`, `nekobox`, `clashmetaforandroid` |
| `General` | `general`, `v2rayn`, `v2rayng`, `passwall`, `ssrplus`, `sagernet` |
| `Loon` | `loon` |
| `QuantumultX` | `quantumult%20x`, `quantumult-x` |
| `Shadowrocket` | `shadowrocket` |
| `Shadowsocks` | `shadowsocks` |
| `SingBox` | `sing-box`, `hiddify`, `sfm` |
| `Stash` | `stash` |
| `Surfboard` | `surfboard` |
| `Surge` | `surge` |

订阅模板位于：

```text
resources/rules/
```

常见模板：

```text
default.clash.yaml
default.clashmeta.yaml
default.sing-box.json
default.surfboard.conf
default.surge.conf
```

### 7.7 订阅二维码

用户二维码接口：

```text
GET /api/v1/user/subscribe/qrcode
```

参数：

| 参数 | 说明 |
| --- | --- |
| `target` | `raw`, `clash`, `meta`, `flclash`, `stash`, `surge`, `surfboard`, `sing-box`, `hiddify`, `nekobox`, `v2rayng`, `loon` |
| `mode` | `subscribe` 或 `import` |
| `size` | 240 到 800 |

后端会根据目标客户端生成：

- 原始订阅 URL。
- 带 `flag` 的订阅 URL。
- 客户端导入 URL。
- SVG 二维码 data URI。

### 7.8 工单模块

用户接口：

```text
GET  /api/v1/user/ticket/fetch
POST /api/v1/user/ticket/save
POST /api/v1/user/ticket/reply
POST /api/v1/user/ticket/close
POST /api/v1/user/ticket/withdraw
```

后台接口：

```text
/api/v2/{secure_path}/ticket/*
```

相关模型：

```text
Ticket
TicketMessage
```

自动关闭命令：

```text
check:ticket
```

### 7.9 邀请返佣

用户接口：

```text
GET  /api/v1/user/invite/save
POST /api/v1/user/invite/delete
GET  /api/v1/user/invite/fetch
GET  /api/v1/user/invite/details
POST /api/v1/user/transfer
```

相关模型：

```text
InviteCode
CommissionLog
```

返佣检查命令：

```text
check:commission
```

返佣计算位于 `OrderService::setInvite()`，自动审核和发放位于 `CheckCommission`。

### 7.10 礼品卡和兑换码

用户接口：

```text
POST /api/v1/user/gift-card/check
POST /api/v1/user/gift-card/redeem
GET  /api/v1/user/gift-card/history
GET  /api/v1/user/gift-card/detail
GET  /api/v1/user/gift-card/types
```

后台接口：

```text
/api/v2/{secure_path}/gift-card/*
```

相关模型：

```text
GiftCardTemplate
GiftCardCode
GiftCardUsage
```

### 7.11 知识库与公告

知识库：

- 用户侧：`/api/v1/user/knowledge/fetch`
- 后台：`/api/v2/{secure_path}/knowledge/*`

公告：

- 用户侧：`/api/v1/user/notice/fetch`
- 后台：`/api/v2/{secure_path}/notice/*`

相关模型：

```text
Knowledge
Notice
```

### 7.12 统计与流量

相关模型：

```text
Stat
StatUser
StatServer
ServerStat
TrafficResetLog
```

相关服务：

```text
StatisticalService
TrafficResetService
UserService::trafficFetch()
```

流量上报后会进入 Job：

```text
TrafficFetchJob
StatUserJob
StatServerJob
```

## 8. 插件系统

插件目录：

```text
plugins/{PluginName}
```

插件通常包含：

```text
Plugin.php
config.json
database/migrations/
routes/
resources/views/
resources/assets/
Commands/
Providers/
```

核心服务：

```text
app/Services/Plugin/PluginManager.php
app/Services/Plugin/AbstractPlugin.php
app/Services/Plugin/HookManager.php
```

插件能力：

- 安装、卸载、更新。
- 自动运行插件 migration。
- 发布插件静态资源。
- 注册插件路由。
- 注册插件视图命名空间。
- 注册插件命令。
- 监听动作钩子。
- 过滤数据。
- 拦截当前响应。

钩子示例：

```text
client.subscribe.before
client.subscribe.unavailable
client.subscribe.servers
user.subscribe.response
order.create.before
order.create.after
order.after_create
order.open.before
order.open.after
order.cancel.before
order.cancel.after
traffic.process.before
traffic.before_process
server.users.get
user.login.after
user.password.reset.after
```

查看支持的钩子：

```bash
php artisan hook:list
```

## 9. 主题系统

主题源码目录：

```text
theme/
```

当前主题：

```text
theme/Xboard
theme/Flavor
theme/Rocket
```

公开运行目录：

```text
public/theme/{ThemeName}
```

主题最少需要：

```text
config.json
dashboard.blade.php
```

主题配置文件 `config.json` 至少需要：

- `name`
- `description`
- `version`
- `configs`

`ThemeService` 支持：

- 扫描系统主题。
- 扫描用户上传主题。
- 上传主题 zip。
- 根据版本号升级主题。
- 切换主题。
- 删除用户主题。
- 初始化主题配置。
- 更新主题配置。
- 刷新公开目录。

主题路径优先级：

1. `theme/{ThemeName}`
2. `storage/theme/{ThemeName}`

注意：前台渲染读取的是：

```php
admin_setting('frontend_theme', 'Xboard')
```

后台主题切换服务中也维护：

```php
admin_setting('current_theme')
```

开发和排查时应同时关注这两个配置是否一致。

## 10. Rocket 主题简介

Rocket 是当前重点开发的新主题。

源码目录：

```text
theme/Rocket
```

运行目录：

```text
public/theme/Rocket
```

主要文件：

| 文件 | 说明 |
| --- | --- |
| `src/main.js` | Vue 应用入口 |
| `src/App.vue` | 顶层路由出口、全局弹窗、Toast |
| `src/router/index.js` | Hash 路由定义和登录守卫 |
| `src/stores/auth.js` | 登录状态 |
| `src/stores/user.js` | 用户数据、订阅、套餐等 |
| `src/stores/app.js` | 主题、侧边栏、Toast |
| `src/components/layout/AppLayout.vue` | 登录后主布局 |
| `src/components/ui/SubscribeModal.vue` | 订阅客户端和二维码弹窗 |
| `src/components/ui/QrCode.vue` | 前端二维码组件 |
| `src/views/dashboard/Dashboard.vue` | 用户首页 |
| `src/views/shop/Shop.vue` | 套餐购买页 |
| `src/views/profile/Profile.vue` | 用户设置、登录设备 |
| `src/views/invite/Invite.vue` | 邀请返佣 |
| `src/views/ticket/*` | 工单 |

Rocket 构建特点：

- Vite `base` 固定为 `/theme/Rocket/`。
- 入口 JS 固定为 `assets/app.js`。
- 样式固定为 `assets/style.css`。
- 动态 chunk 保留 hash 文件名。
- 旧入口 hash 文件只保留转发模块，避免历史缓存导致白屏。
- `dashboard.blade.php` 中 JS 入口不能带 query 参数，否则 ES Module 会重复执行。

## 11. 多语言

后端语言文件：

```text
resources/lang/zh-CN.json
resources/lang/zh-TW.json
resources/lang/en-US.json
resources/lang/ru-RU.json
```

请求语言由 `Language` 中间件读取：

```text
content-language
```

主题前端通常也会读取 `window.settings.i18n`，并在请求头中带上 `content-language`。

## 12. 计划任务与队列

Octane 中通过 `OctaneServiceProvider` 每 30 秒触发一次：

```bash
php artisan schedule:run
```

为了避免多个 worker 重复执行，使用 Redis lock：

```text
scheduler-lock
```

常见命令：

| 命令 | 说明 |
| --- | --- |
| `check:order` | 检查待处理订单并派发订单 Job |
| `check:commission` | 自动审核和发放佣金 |
| `check:server` | 检查节点离线 |
| `check:ticket` | 自动关闭长时间无响应工单 |
| `check:traffic-exceeded` | 检查流量超标并通知节点 |
| `clear:user` | 清理没有任何数据的用户 |
| `reset:password` | 重置用户密码 |
| `backup:database` | 备份数据库 |
| `xboard:install` | 初始化安装 |
| `xboard:update` | 更新流程 |
| `hook:list` | 扫描钩子 |

## 13. 安全与稳定性设计

当前项目中值得注意的安全设计：

- 后台入口使用随机 `secure_path`。
- 后台 POST 操作有审计日志。
- 管理日志过滤 `password`、`token`、`secret`、`key`、`api_key`。
- 节点接口使用 `server_token`。
- 用户接口使用 Sanctum token。
- 密码错误次数限制。
- TrustProxies 包含 Cloudflare 和内网网段。
- Octane 下增加 `EnsureTransactionState`，避免请求结束后残留数据库事务。
- 插件钩子存储在应用容器中，并在 Octane worker starting 时重置，避免常驻内存污染。

## 14. 当前项目的重点改动

结合当前仓库状态，近期重点改动集中在：

- 新主题 `Flavor` 的功能补齐。
- 新主题 `Rocket` 的功能复刻和稳定性修复。
- 订阅按钮、订阅二维码和客户端导入链接。
- Clash Meta、Sing-box、Hiddify 等订阅模板优化。
- 登录设备活动会话信息补全。
- Rocket 主题白屏问题修复：稳定入口、禁止 JS query、旧 hash 转发。
- Docker 部署、线上替换主题、主题包构建流程。

## 15. 关键排查入口

| 问题 | 优先查看 |
| --- | --- |
| 页面白屏 | 浏览器 console、`theme/{theme}/dashboard.blade.php`、`public/theme/{theme}/assets` |
| 套餐不显示 | `PlanService`、`PlanController`、套餐 `show/sell/renew/capacity_limit` |
| 订阅不对 | `ClientController`、`app/Protocols/*`、`resources/rules/*` |
| 二维码不识别 | `UserController::getSubscribeQrCode()`、目标客户端 import scheme |
| Hiddify 导入失败 | `SingBox` 协议、订阅 URL 是否可公网访问、客户端是否请求到本地 127.0.0.1 |
| 邀请码接口 400 | `InviteController`、请求方法、用户认证 |
| 新建工单无反应 | 前端事件、`POST /api/v1/user/ticket/save`、请求体 |
| 登录设备 IP 未记录 | `AuthService`、`personal_access_tokens` 字段、代理头 |
| 后台打不开 | `secure_path`、Nginx 转发、Octane reload |
| 节点不在线 | `server_token`、节点 ID/type、`ServerService`、缓存 key |

## 16. 术语表

| 名称 | 说明 |
| --- | --- |
| 面板 | Xboard 后端和前端整体 |
| 节点 | 实际提供代理服务的服务器 |
| 订阅 | 用户客户端拉取节点配置的 URL |
| flag | 订阅接口识别客户端类型的参数 |
| theme | 用户前台主题 |
| secure_path | 后台安全入口路径 |
| group_id | 用户所属权限组 |
| group_ids | 节点允许访问的用户组 |
| transfer_enable | 用户总流量 |
| u/d | 用户上传/下载已用流量 |
| Octane | Laravel 常驻 HTTP 服务 |
| Horizon | Laravel 队列监控和消费者 |
| Hook | 插件系统扩展点 |

