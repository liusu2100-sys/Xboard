# Xboard 开发文档

本文档面向参与 Xboard 二次开发的工程师，覆盖本地开发、Docker 管理、后端功能开发、用户主题开发、订阅协议开发、插件开发、数据库迁移、发布打包、线上排障等内容。

如果只想快速理解项目，请先阅读：

```text
docs/zh-CN/project-overview.md
```

## 1. 开发原则

Xboard 当前是一个 Laravel Octane 常驻进程项目，开发时要始终记住三件事：

1. 传统 PHP-FPM 的“每个请求重新加载代码”不再成立。
2. 修改 PHP 代码后，经常需要 `octane:reload`。
3. 静态主题资源是浏览器直接从 `public/theme/{theme}` 加载的，不是从 `theme/{theme}/src` 加载的。

推荐开发原则：

- 业务规则放 Service，不要堆在 Controller。
- HTTP 参数校验优先使用 Request 类或控制器内 `validate()`。
- 新接口要明确属于 V1 用户侧、V2 后台侧、节点侧还是游客侧。
- 涉及金额、余额、订单开通必须使用事务。
- 涉及 Octane 常驻内存的静态缓存、单例、全局变量要格外谨慎。
- 主题开发必须区分源码目录和运行目录。
- 订阅协议开发要用真实客户端测试，不只看 YAML/JSON 是否能生成。
- 插件扩展优先使用 Hook，不要直接改核心业务，除非确实是系统能力。

## 2. 环境要求

后端：

- PHP 8.2+
- Composer
- MySQL 5.7+ 或 SQLite
- Redis
- Docker / Docker Compose

前端主题：

- Node.js
- npm
- Vite

当前 Docker 镜像已经包含 PHP 运行环境，日常开发最推荐使用 Docker Compose。

## 3. Docker 管理

### 3.1 启动服务

```bash
docker compose up -d
```

### 3.2 查看服务状态

```bash
docker compose ps
```

### 3.3 查看日志

```bash
docker compose logs -f web
docker compose logs -f horizon
docker compose logs -f ws-server
```

### 3.4 执行 Artisan 命令

```bash
docker compose exec web php artisan migrate --force
docker compose exec web php artisan optimize:clear
docker compose exec web php artisan octane:reload
```

当前环境中也可以使用容器名：

```bash
docker exec wwwxboardcom-web-1 php artisan migrate --force
docker exec wwwxboardcom-web-1 php artisan view:clear
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

### 3.5 修改代码后的重载规则

| 修改内容 | 需要动作 |
| --- | --- |
| PHP Controller、Service、Model、Middleware、Provider | `octane:reload` |
| API 路由 | `octane:reload` |
| 协议生成器 `app/Protocols/*` | `octane:reload` |
| 订阅模板 `resources/rules/*` | 通常需要 `octane:reload`，并清理客户端缓存 |
| Blade 视图 | `view:clear`，必要时 `octane:reload` |
| 主题 Vue 源码 | 重新 `vite build` 并同步到 `public/theme/{theme}` |
| `.env` | 重启容器更稳妥 |
| Composer 依赖 | 重新安装依赖并重启 |

## 4. 安装和初始化

README 中的快速安装命令：

```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install
```

安装后会输出管理员账号和随机密码。密码只会明文显示一次，后续数据库中只保存哈希。

忘记管理员密码时：

```bash
docker exec wwwxboardcom-web-1 php artisan reset:password admin@demo.com 新密码
```

查看后台路径：

```bash
docker exec wwwxboardcom-web-1 php artisan tinker --execute='echo admin_setting("secure_path", admin_setting("frontend_admin_path", hash("crc32b", config("app.key")))) . PHP_EOL;'
```

## 5. 后端开发流程

### 5.1 判断接口归属

开发一个新接口前，先判断它属于哪个模块：

| 场景 | 路由位置 |
| --- | --- |
| 用户登录注册、找回密码 | `app/Http/Routes/V1/PassportRoute.php` |
| 游客可访问配置、支付回调 | `app/Http/Routes/V1/GuestRoute.php` |
| 用户中心功能 | `app/Http/Routes/V1/UserRoute.php` |
| 订阅客户端拉取配置 | `app/Http/Routes/V1/ClientRoute.php` |
| 节点拉配置/上报 | `app/Http/Routes/V1/ServerRoute.php` 或 `V2/ServerRoute.php` |
| 后台管理功能 | `app/Http/Routes/V2/AdminRoute.php` |

### 5.2 新增用户接口示例

假设要给用户中心新增一个接口：

```text
GET /api/v1/user/example
```

步骤：

1. 在 `app/Http/Routes/V1/UserRoute.php` 添加路由：

```php
$router->get('/example', [UserController::class, 'example']);
```

2. 在 `app/Http/Controllers/V1/User/UserController.php` 添加方法：

```php
public function example(Request $request)
{
    $user = $request->user();

    return $this->success([
        'id' => $user->id,
        'email' => $user->email,
    ]);
}
```

3. 修改后执行：

```bash
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

4. 前端通过 Axios 请求：

```js
request.get('/user/example')
```

注意：Rocket 主题中 Axios baseURL 是 `/api/v1`，所以前端不需要写完整 `/api/v1/user/example`。

### 5.3 新增后台接口示例

后台接口路径在：

```text
/api/v2/{secure_path}/...
```

例如要新增后台系统接口：

1. 在 `app/Http/Routes/V2/AdminRoute.php` 对应 group 中添加路由。
2. 在 `app/Http/Controllers/V2/Admin/*Controller.php` 实现方法。
3. 确保接口受 `admin` 中间件保护。
4. POST 操作会经过 `RequestLog` 写审计日志。

后台接口不要放到 V1 用户路由中，否则会绕过后台审计和权限边界。

### 5.4 Controller 返回格式

控制器通常继承：

```text
app/Http/Controllers/Controller.php
```

常见返回：

```php
return $this->success($data);
return $this->fail([400, __('Error message')]);
```

前端 Axios 拦截器会把标准响应中的 `data` 解包，也会把 `status = error/fail` 转成异常。

### 5.5 参数校验

简单接口可使用：

```php
$request->validate([
    'name' => ['required', 'string'],
]);
```

复杂接口建议新增 Request 类：

```text
app/Http/Requests/User
app/Http/Requests/Admin
app/Http/Requests/Passport
```

### 5.6 Service 使用建议

推荐把这些逻辑放 Service：

- 订单金额计算。
- 套餐可购买性校验。
- 用户流量重置。
- 节点配置生成。
- 插件安装卸载。
- 主题上传切换。
- 支付回调处理。
- 跨多个模型的事务。

不要让 Controller 同时做参数校验、业务判断、数据库事务、模型保存、事件分发和响应组装。

## 6. 数据库开发

### 6.1 新增迁移

```bash
docker exec wwwxboardcom-web-1 php artisan make:migration add_xxx_to_table
```

执行迁移：

```bash
docker exec wwwxboardcom-web-1 php artisan migrate --force
```

回滚本地最近迁移：

```bash
docker exec wwwxboardcom-web-1 php artisan migrate:rollback
```

线上谨慎使用 rollback，尤其是已经有真实数据的表。

### 6.2 时间字段约定

项目中大量业务表使用 Unix timestamp，而不是 Laravel 默认 datetime。开发前要查看已有迁移和模型字段，不要混用。

典型字段：

```text
created_at
updated_at
expired_at
paid_at
last_login_at
last_reset_at
next_reset_at
```

### 6.3 金额字段约定

订单和余额常用“分”为单位：

```text
total_amount
balance
commission_balance
discount_amount
surplus_amount
refund_amount
```

前端展示时通常需要除以 100。

### 6.4 流量字段约定

数据库通常保存字节：

```text
u
d
transfer_enable
```

套餐 `transfer_enable` 通常以 GB 配置，开通时乘以 `1073741824` 转成字节。

## 7. 订单和套餐开发

### 7.1 套餐显示规则

套餐在用户侧是否显示，不只取决于是否存在记录。当前规则在 `PlanService`：

- 新购用户：`show = true`、`sell = true`、容量未满。
- 当前套餐续费：`renew = true`、`sell = true`。
- `capacity_limit = null` 代表不限容量。

如果“新用户看不到基础套餐”，优先检查：

1. 套餐 `show` 是否为 true。
2. 套餐 `sell` 是否为 true。
3. 套餐 `renew` 是否符合续费场景。
4. `capacity_limit` 是否已满。
5. 套餐价格周期是否配置。
6. 前端是否过滤了空价格或不可购买周期。

### 7.2 订单创建规则

订单创建入口：

```text
POST /api/v1/user/order/save
```

核心服务：

```text
app/Services/OrderService.php
```

不要在前端计算最终金额。前端只负责展示，真实金额以服务端为准。

### 7.3 升级套餐

升级套餐由 `OrderService::setOrderType()` 判断。可能涉及：

- 是否允许变更套餐：`plan_change_enable`
- 是否计算剩余价值：`surplus_enable`
- 旧套餐剩余时间。
- 旧套餐剩余流量。
- 余额退回或抵扣。

修改升级逻辑时必须准备测试数据，不要只测新购。

## 8. 订阅协议开发

### 8.1 订阅入口

主要入口：

```text
GET /s/{token}
GET /api/v1/client/subscribe?token=...
```

常见参数：

| 参数 | 说明 |
| --- | --- |
| `flag` | 客户端标识，例如 `meta`、`stash`、`sing-box`、`hiddify` |
| `types` | 限定节点协议，例如 `vless,shadowsocks` |
| `filter` | 根据节点名或标签过滤 |

### 8.2 协议匹配规则

协议管理器：

```text
app/Support/ProtocolManager.php
```

协议类目录：

```text
app/Protocols/
```

每个协议类继承 `AbstractProtocol` 并声明：

```php
public $flags = ['sing-box', 'hiddify'];
```

当请求中 `flag` 或 User-Agent 包含对应标识时，系统会使用该协议类生成订阅。

### 8.3 新增协议生成器

步骤：

1. 新建文件：

```text
app/Protocols/NewClient.php
```

2. 继承 `AbstractProtocol`。
3. 设置 `$flags`。
4. 实现 `handle()`。
5. 如需模板，放到 `resources/rules/`。
6. 修改后执行：

```bash
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

7. 用真实客户端导入测试。

### 8.4 修改 Clash Meta 模板

模板位置：

```text
resources/rules/default.clashmeta.yaml
```

修改后要同时关注：

- YAML 缩进。
- `proxy-groups` 的代理名称是否能被协议生成器填充。
- `rules` 中引用的 `$app_name` 是否存在。
- DNS 是否适配目标客户端。
- Reality、VLESS、Hysteria2、TUIC 等新协议的字段是否被 `ClashMeta.php` 正确输出。

### 8.5 Hiddify 导入排查

Hiddify 通常走 Sing-box 配置。

优先检查：

1. 前端导入按钮是否生成 `flag=sing-box` 或 `flag=hiddify`。
2. 订阅 URL 是否是公网域名，不应是 `127.0.0.1`。
3. 服务端 `app_url` 是否配置正确。
4. 客户端能否直接访问订阅 URL。
5. `app/Protocols/SingBox.php` 输出是否符合 Hiddify 支持的 sing-box 版本。
6. 节点协议是否是 Hiddify 支持的协议组合。

## 9. 订阅二维码开发

二维码接口：

```text
GET /api/v1/user/subscribe/qrcode
```

核心方法：

```text
UserController::getSubscribeQrCode()
```

支持目标：

```text
raw
clash
meta
flclash
stash
surge
surfboard
sing-box
hiddify
nekobox
v2rayng
loon
```

开发注意：

- `mode=subscribe` 应输出普通订阅 URL。
- `mode=import` 应输出客户端深链。
- 二维码内容越长越难识别，应尽量短。
- 移动端 App 扫码失败时，先测试二维码 content 文本是否能手动导入。
- 如果客户端不支持深链，就让二维码使用普通订阅 URL。
- 不要把本地 `127.0.0.1`、`localhost`、Docker 内网地址写进二维码。

## 10. 主题开发

### 10.1 主题目录关系

源码目录：

```text
theme/Rocket
```

浏览器实际加载目录：

```text
public/theme/Rocket
```

这两个目录不要混淆。修改 Vue 源码后，不会自动影响线上页面，必须重新构建并同步到 `public/theme/Rocket`。

### 10.2 Rocket 开发命令

进入主题目录：

```bash
cd theme/Rocket
```

安装依赖：

```bash
npm install
```

开发服务：

```bash
npm run dev
```

构建：

```bash
npm run build
```

部署到公开目录：

```bash
./deploy.sh
```

当前 `deploy.sh` 会：

1. 执行 Vite build。
2. 重建 `theme/Rocket/assets`。
3. 复制 manifest。
4. 固定入口为 `assets/app.js`。
5. 固定样式为 `assets/style.css`。
6. 为旧 hash 生成兼容转发文件。
7. 清空并重建 `public/theme/Rocket`。
8. 清理 view cache。

### 10.3 Rocket 入口文件注意事项

`theme/Rocket/vite.config.js` 中：

```js
entryFileNames: 'assets/app.js'
```

`dashboard.blade.php` 中：

```html
<script type="module" crossorigin src="/theme/{{$theme}}/{{ $jsFile }}"></script>
```

不要给 JS module 入口加 `?v=xxx`。ES Module 会把：

```text
/theme/Rocket/assets/app.js?v=1
/theme/Rocket/assets/app.js
```

视为两个不同模块，动态 chunk 再导入 `./app.js` 时会导致入口执行两次，Vue 可能重复挂载并白屏。

CSS 可以带 `?v=`，JS 不要带。

### 10.4 主题配置

主题配置文件：

```text
theme/Rocket/config.json
```

配置会通过 `ThemeService` 初始化到：

```text
theme_Rocket
```

Blade 中会写入：

```js
window.settings = {...}
```

前端通过 `window.settings` 获取：

- 网站标题。
- 版本。
- logo。
- 主题色。
- 背景图。
- 首页 slogan。
- 活动弹窗配置。
- 多语言列表。

### 10.5 Rocket 路由

路由文件：

```text
theme/Rocket/src/router/index.js
```

公开页面：

```text
#/home
#/login
#/register
#/forget
```

登录后页面：

```text
#/dashboard
#/shop
#/order
#/order/:tradeNo
#/server
#/invite
#/gift-card
#/cdkey
#/ticket
#/ticket/:id
#/knowledge
#/profile
#/traffic
```

需要登录的页面会通过路由守卫检查 `auth.isLoggedIn`。

### 10.6 Rocket API 约定

请求封装：

```text
theme/Rocket/src/api/request.js
```

通常 baseURL 是：

```text
/api/v1
```

因此前端调用：

```js
request.get('/user/info')
```

实际请求：

```text
/api/v1/user/info
```

### 10.7 新增 Rocket 页面

步骤：

1. 在 `src/views/{module}/NewPage.vue` 创建页面。
2. 在 `src/router/index.js` 添加路由。
3. 如果需要导航，在 `AppLayout.vue` 中添加菜单。
4. 在 `src/api/user.js` 或对应 API 文件中封装请求。
5. 处理 loading、empty、error 状态。
6. 执行：

```bash
cd theme/Rocket
./deploy.sh
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

7. 浏览器访问真实页面验证。

### 10.8 主题打包

运行包应该包含：

```text
Rocket/
  config.json
  dashboard.blade.php
  assets/
```

不要把这些目录上传到运行主题包：

```text
node_modules/
dist/
src/
rocket_assets/
```

源码包可以包含：

```text
src/
assets/
package.json
vite.config.js
deploy.sh
```

运行包用于线上替换，源码包用于继续开发。

## 11. 主题线上替换流程

### 11.1 替换运行包

推荐在目标服务器上：

```bash
cd /path/to/xboard
rm -rf public/theme/Rocket
unzip /tmp/Rocket-theme-runtime-1.0.8-20260608.zip -d public/theme
docker exec <web容器名> php artisan view:clear
docker exec <web容器名> php artisan octane:reload
```

如果使用 CDN，必须清理：

```text
/
/theme/Rocket/*
/theme/Rocket/assets/app.js
```

### 11.2 判断是否上传错包

正确的运行目录应该类似：

```text
public/theme/Rocket/config.json
public/theme/Rocket/dashboard.blade.php
public/theme/Rocket/assets/app.js
public/theme/Rocket/assets/style.css
```

错误情况：

```text
public/theme/Rocket/node_modules
public/theme/Rocket/src
public/theme/Rocket/dist
public/theme/Rocket/rocket_assets
```

出现这些目录通常说明上传了源码包，而不是运行包。

## 12. 插件开发

### 12.1 插件目录

新插件目录：

```text
plugins/MyPlugin
```

基本结构：

```text
plugins/MyPlugin/
  Plugin.php
  config.json
  database/migrations/
  routes/
    web.php
    api.php
  resources/
    views/
    assets/
  Commands/
  Providers/
```

### 12.2 Plugin.php 示例

```php
<?php

namespace Plugin\MyPlugin;

use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        $this->listen('order.open.after', function ($order) {
            // 订单开通后的扩展逻辑
        });

        $this->filter('client.subscribe.servers', function ($servers, $user, $request) {
            return $servers;
        });
    }
}
```

### 12.3 插件配置

`config.json` 至少包含：

```json
{
  "name": "My Plugin",
  "code": "MyPlugin",
  "version": "1.0.0",
  "description": "Example plugin",
  "config": {}
}
```

### 12.4 插件注意事项

- 插件类命名空间必须符合 `Plugin\\{StudlyCode}`。
- 插件入口类通常是 `Plugin\\{PluginName}\\Plugin`。
- 插件 migration 会通过 `PluginManager` 执行。
- 插件启用后会在请求开始时初始化。
- 在 Octane 下插件不要使用不可控静态状态保存请求数据。

## 13. 多语言开发

后端语言文件：

```text
resources/lang/zh-CN.json
resources/lang/zh-TW.json
resources/lang/en-US.json
resources/lang/ru-RU.json
```

后端使用：

```php
__('The user does not exist')
```

前端主题可维护自己的 locale 工具，例如：

```text
theme/Rocket/src/utils/locale.js
```

请求中要带：

```text
content-language: zh-CN
```

## 14. 测试和验证

### 14.1 PHP 语法检查

```bash
php -l app/Services/OrderService.php
```

容器内：

```bash
docker exec wwwxboardcom-web-1 php -l app/Services/OrderService.php
```

### 14.2 路由检查

```bash
docker exec wwwxboardcom-web-1 php artisan route:list
```

如果路由改了但 `route:list` 看不到，执行：

```bash
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

### 14.3 构建主题

```bash
cd theme/Rocket
./deploy.sh
```

### 14.4 浏览器验证

至少验证：

```text
#/home
#/login
#/dashboard
#/shop
#/ticket
#/profile
```

未登录访问登录后页面时跳转到：

```text
#/login?redirect=/xxx
```

这是正常行为。

### 14.5 API 验证

登录后拿到 token：

```text
Authorization: Bearer xxx
```

请求用户信息：

```bash
curl -H "Authorization: Bearer xxx" https://example.com/api/v1/user/info
```

请求订阅：

```bash
curl "https://example.com/s/{token}?flag=meta"
curl "https://example.com/s/{token}?flag=sing-box"
```

## 15. 常见问题排查

### 15.1 前端页面空白

优先检查：

1. 浏览器 Console 是否有 404。
2. HTML 是否加载 `/theme/Rocket/assets/app.js`。
3. `public/theme/Rocket/assets/app.js` 是否存在。
4. 是否错误上传了源码包。
5. JS 入口是否带了 `?v=`。
6. CDN 是否还缓存旧 HTML 或旧 JS。
7. 是否执行了 `view:clear` 和 `octane:reload`。

Rocket 修复后的正确入口：

```html
<script type="module" crossorigin src="/theme/Rocket/assets/app.js"></script>
```

### 15.2 主题上传后还是旧页面

检查：

```bash
docker exec wwwxboardcom-web-1 php artisan tinker --execute='echo admin_setting("frontend_theme") . PHP_EOL; echo admin_setting("current_theme") . PHP_EOL;'
```

前台实际渲染看 `frontend_theme`。

### 15.3 套餐不显示

检查：

- `show`
- `sell`
- `renew`
- `capacity_limit`
- 价格周期
- 用户是否已有同套餐
- 前端是否只展示 `plan.prices` 中大于 0 的周期

代码入口：

```text
app/Services/PlanService.php
theme/Rocket/src/views/shop/Shop.vue
```

### 15.4 新建工单没反应

检查：

- 按钮是否绑定事件。
- 是否调用 `POST /api/v1/user/ticket/save`。
- 请求体是否包含 subject、level、message 等后端需要字段。
- token 是否过期。
- 后端返回是否被前端吞掉。

代码入口：

```text
theme/Rocket/src/views/ticket/Tickets.vue
app/Http/Controllers/V1/User/TicketController.php
```

### 15.5 邀请码生成 400

检查：

- 接口方法是 `GET /api/v1/user/invite/save`。
- 删除接口是 `POST /api/v1/user/invite/delete`。
- 用户是否登录。
- 后端是否限制生成数量。

代码入口：

```text
theme/Rocket/src/views/invite/Invite.vue
app/Http/Controllers/V1/User/InviteController.php
```

### 15.6 复制功能无效

检查：

- 浏览器是否 HTTPS。
- 是否使用 Clipboard API。
- 是否提供 fallback。
- 是否被 iframe 或权限策略限制。

代码入口：

```text
theme/Rocket/src/utils/clipboard.js
```

### 15.7 二维码无法扫描

检查：

- 二维码内容是否过长。
- 二维码是否被 CSS 压缩模糊。
- `mode=subscribe` 是否比 `mode=import` 更兼容。
- URL 是否公网可访问。
- 是否返回 `127.0.0.1` 或 `localhost`。

代码入口：

```text
theme/Rocket/src/components/ui/SubscribeModal.vue
theme/Rocket/src/components/ui/QrCode.vue
app/Http/Controllers/V1/User/UserController.php
```

### 15.8 Hiddify 导入报错

常见原因：

- 深链让 Hiddify 去请求了 `127.0.0.1`。
- 订阅 URL 不是公网 URL。
- `flag` 不正确。
- Sing-box 配置不兼容 Hiddify 当前版本。
- 节点协议字段缺失，例如 Reality fingerprint、server_name、flow。

优先测试：

```bash
curl "https://your-domain/s/{token}?flag=hiddify"
curl "https://your-domain/s/{token}?flag=sing-box"
```

### 15.9 登录设备 IP 未记录

检查：

- `personal_access_tokens` 是否有 session metadata 字段。
- 是否执行了迁移。
- 反向代理是否传递 `CF-Connecting-IP`、`X-Real-IP`、`X-Forwarded-For`。
- `TrustProxies` 是否信任当前代理来源。

代码入口：

```text
database/migrations/2026_06_07_000003_add_session_metadata_to_personal_access_tokens_table.php
app/Services/AuthService.php
```

### 15.10 修改 PHP 后无效

执行：

```bash
docker exec wwwxboardcom-web-1 php artisan optimize:clear
docker exec wwwxboardcom-web-1 php artisan view:clear
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

Octane 是常驻进程，不 reload 很容易看到旧代码。

## 16. 发布流程

### 16.1 后端发布

建议流程：

1. 备份数据库。
2. 拉取或上传代码。
3. 安装依赖。
4. 执行迁移。
5. 清缓存。
6. reload Octane。
7. 检查日志。

命令示例：

```bash
docker exec wwwxboardcom-web-1 php artisan backup:database
docker exec wwwxboardcom-web-1 php artisan migrate --force
docker exec wwwxboardcom-web-1 php artisan optimize:clear
docker exec wwwxboardcom-web-1 php artisan octane:reload
```

### 16.2 Rocket 主题发布

本机打包后上传运行包：

```text
/tmp/Rocket-theme-runtime-1.0.8-20260608.zip
```

目标服务器替换：

```bash
cd /path/to/xboard
rm -rf public/theme/Rocket
unzip Rocket-theme-runtime-1.0.8-20260608.zip -d public/theme
docker exec <web容器名> php artisan view:clear
docker exec <web容器名> php artisan octane:reload
```

清 CDN：

```text
/
/theme/Rocket/*
/theme/Rocket/assets/app.js
```

### 16.3 发布后检查

检查 HTML：

```bash
curl -s https://your-domain/ | grep 'theme/Rocket/assets'
```

正确结果应包含：

```html
<script type="module" crossorigin src="/theme/Rocket/assets/app.js"></script>
<link rel="stylesheet" crossorigin href="/theme/Rocket/assets/style.css?v=...">
```

检查资源：

```bash
curl -I https://your-domain/theme/Rocket/assets/app.js
curl -I https://your-domain/theme/Rocket/assets/style.css
```

状态应为 `200`。

## 17. 代码审查清单

提交前建议检查：

- 是否修改了正确版本的路由。
- 是否需要 `octane:reload`。
- 是否有数据库迁移。
- 是否涉及金额，金额单位是否为分。
- 是否涉及流量，流量单位是否为字节。
- 是否有权限校验。
- 是否返回统一响应格式。
- 是否有多语言文案。
- 是否会影响订阅协议。
- 是否会影响旧客户端兼容。
- 是否会影响当前主题和原主题。
- 是否有缓存或 CDN 风险。
- 是否会在 Octane 下产生静态状态污染。

## 18. 推荐开发顺序

新增一个完整用户侧功能时，推荐顺序：

1. 先确定数据模型。
2. 写 migration。
3. 写 Model 关系和字段转换。
4. 写 Service 业务规则。
5. 写 Controller。
6. 写 Route。
7. 写前端 API 封装。
8. 写页面和状态。
9. 本地验证 API。
10. 本地验证页面。
11. 构建主题。
12. reload Octane。
13. 打包运行包。
14. 上传线上测试。

## 19. 不推荐的做法

- 不要把主题源码包直接上传到 `public/theme`。
- 不要让 JS module 入口带 query 参数。
- 不要在 Controller 中写大段订单和金额逻辑。
- 不要在 Octane 项目中用静态变量保存请求级数据。
- 不要把后台接口放在 V1 用户路由。
- 不要只用浏览器页面判断接口是否正常，要看 Network 和后端日志。
- 不要在没有备份的情况下跑线上迁移。
- 不要用 `docker compose down -v`，除非明确要删除 Redis volume。
- 不要修改订阅模板后只测 Clash，必须测目标客户端。

## 20. 快速命令速查

```bash
# 查看服务
docker compose ps

# 查看 web 日志
docker compose logs -f web

# 清缓存
docker exec wwwxboardcom-web-1 php artisan optimize:clear
docker exec wwwxboardcom-web-1 php artisan view:clear

# reload Octane
docker exec wwwxboardcom-web-1 php artisan octane:reload

# 执行迁移
docker exec wwwxboardcom-web-1 php artisan migrate --force

# 重置密码
docker exec wwwxboardcom-web-1 php artisan reset:password admin@demo.com 新密码

# 查看后台路径
docker exec wwwxboardcom-web-1 php artisan tinker --execute='echo admin_setting("secure_path", admin_setting("frontend_admin_path", hash("crc32b", config("app.key")))) . PHP_EOL;'

# 构建 Rocket
cd theme/Rocket
./deploy.sh

# 查看路由
docker exec wwwxboardcom-web-1 php artisan route:list

# 扫描插件钩子
docker exec wwwxboardcom-web-1 php artisan hook:list
```

