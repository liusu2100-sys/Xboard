# 节点内核与订阅模板兼容性审计

审计日期：2026-09-19

## 1. 结论

本项目需要区分三个概念：

1. **面板节点类型**：Xboard 数据库和管理后台能够保存的协议。
2. **服务端节点内核**：Xboard-Node 在节点服务器上实际运行的 `sing-box` 或 `xray-core`。
3. **客户端订阅内核**：Mihomo、sing-box、Shadowrocket 等用户软件读取的订阅格式。

当前面板可保存 11 种节点类型，但不代表每个服务端内核和每个客户端都能使用全部 11 种。

官方 Xboard-Node 当前采用 `sing-box` / `xray-core` 双内核，官方声明支持 V2Ray 系、Trojan、Shadowsocks、Hysteria2、TUIC、AnyTLS。当前项目的 UniProxy 接口能够为全部面板节点类型生成节点配置，但最终能否启动仍取决于节点程序版本和所选内核。

参考：

- <https://github.com/cedar2025/Xboard-Node>
- <https://github.com/cedar2025/Xboard-Node/blob/dev/internal/kernel/kernel.go>
- <https://github.com/cedar2025/Xboard-Node/blob/dev/docs-custom-outbounds.md>

## 2. 服务端节点支持范围

### 2.1 面板可配置类型

`App\Models\Server::VALID_TYPES` 当前包含：

| 面板类型 | 说明 |
| --- | --- |
| `vmess` | VMess，旧名 `v2ray` 会归一化为 VMess |
| `vless` | VLESS，支持 TLS/Reality 和多种传输层 |
| `trojan` | Trojan，支持 TLS/Reality |
| `shadowsocks` | Shadowsocks，含 2022 系列密码 |
| `hysteria` | Hysteria 1/2，`hysteria2` 会归一化为此类型 |
| `tuic` | TUIC v4/v5 |
| `anytls` | AnyTLS |
| `socks` | SOCKS 节点 |
| `http` | HTTP 代理节点 |
| `naive` | NaiveProxy 节点 |
| `mieru` | Mieru 节点 |

### 2.2 Xboard-Node 双内核

| 内核 | 推荐用途 | 注意事项 |
| --- | --- | --- |
| `sing-box` | VMess、VLESS、Trojan、Shadowsocks、Hysteria2、TUIC、AnyTLS | 新协议覆盖更广；节点程序和客户端 sing-box 配置版本必须匹配 |
| `xray-core` | VMess、VLESS、Trojan、Shadowsocks，尤其 Xray 特有传输 | Hysteria2、TUIC、AnyTLS 不应默认假定可用，需以节点程序能力为准 |

`socks`、`http`、`naive`、`mieru` 虽可由面板建模，官方 README 没有把它们全部列为节点入站能力。上线前应使用实际 Xboard-Node 版本验证，不能只看管理后台能否创建。

当前面板没有保存或展示每台节点实际使用的核心名称和核心版本，`status.kernel_status` 只有健康布尔值。因此面板数据库本身无法证明远端节点正在使用哪一个内核；需要在节点服务器执行 `xbctl status` 或查看 Xboard-Node 日志。

## 3. 客户端订阅支持范围

以下为当前代码中各生成器的实际白名单：

| 订阅生成器/客户端标识 | 实际输出协议 |
| --- | --- |
| Clash | Shadowsocks、VMess、Trojan、SOCKS、HTTP |
| Clash Meta / Mihomo / Clash Verge / FlClash / NekoBox | Shadowsocks、VMess、Trojan、VLESS、Hysteria、TUIC、AnyTLS、SOCKS、HTTP、Mieru |
| sing-box / Hiddify / SFM | Shadowsocks、Trojan、VMess、VLESS、Hysteria、TUIC、AnyTLS、SOCKS、HTTP |
| General / V2rayN / V2rayNG / Passwall | VMess、VLESS、Shadowsocks、Trojan、Hysteria、AnyTLS、SOCKS、TUIC、HTTP |
| Shadowrocket | Shadowsocks、VMess、VLESS、Trojan、Hysteria、TUIC、AnyTLS、SOCKS |
| Stash | Shadowsocks、VMess、VLESS、Hysteria、Trojan、TUIC、AnyTLS、SOCKS、HTTP |
| Loon | Shadowsocks、VMess、Trojan、Hysteria、VLESS、AnyTLS |
| Quantumult X | Shadowsocks、VMess、VLESS、Trojan、SOCKS、HTTP |
| Surge | Shadowsocks、VMess、Trojan、Hysteria、AnyTLS、SOCKS、HTTP |
| Surfboard | Shadowsocks、VMess、Trojan、AnyTLS |

本次已增加 `mihomo`、`clashmeta`、`clash.meta` 等识别标识。此前原生 Mihomo User-Agent 可能无法命中 Clash Meta 生成器，最终退回 General Base64 链接，这是“导入格式错误”或“拿到错误订阅”的直接风险。

## 4. 当前数据库审计结果

本次只读取并统计当前数据库，没有修改节点或订阅模板数据。

| 检查项 | 结果 |
| --- | --- |
| 节点总数 | 34 |
| VLESS 节点 | 33，用户可见 28 |
| Shadowsocks 节点 | 1，当前隐藏 |
| VLESS Reality 节点 | 33 |
| Reality 缺少公钥/SNI/Short ID | 0/0/0 |
| Reality 缺少 uTLS 指纹 | 0 |
| 节点地址 | 29 个域名、5 个 IPv4 |

使用真实可用节点执行脱敏生成测试：

- Mihomo：生成 28 个代理，Reality 28 个，缺失 `client-fingerprint` 为 0。
- Mihomo：节点域名会进入 `fake-ip-filter`，节点域名/IP 会生成优先级最高的直连规则。
- Hiddify/Sing-box：生成 28 个代理，旧 DNS `address` 字段为 0。
- Hiddify/Sing-box：废弃入站字段为 0，已删除的 `block`/`dns` 特殊出站为 0。
- Hiddify/Sing-box：已配置 `route.default_domain_resolver=local`。

## 5. 本次模板与生成器优化

### 5.1 Mihomo/Clash Meta

- 原生 `mihomo/...` User-Agent 直接匹配 Clash Meta 生成器。
- Reality VLESS/Trojan 未显式配置指纹时自动使用 `chrome`。
- 自动设置 `proxy-server-nameserver`，专门解析代理节点域名。
- 自动设置 `direct-nameserver`。
- 节点域名和面板域名动态加入 `fake-ip-filter` 和 `DOMAIN,...,DIRECT`。
- 节点 IP 动态加入 `/32` 或 `/128` 直连规则。
- 删除默认模板里的站点专属 `xinjingyun.pro`，避免模板迁移到其他域名后残留错误规则。
- YAML 数据库模板为空、损坏或缺少关键结构时回退到默认模板。
- 修复中文策略组名称被误当成正则表达式造成大量 `preg_match` 警告的问题。

Mihomo 官方说明 `proxy-server-nameserver` 专用于代理节点域名解析，可避免代理 DNS 依赖代理节点本身形成解析闭环：

- <https://wiki.metacubex.one/en/config/dns/>

### 5.2 Sing-box/Hiddify

- 默认模板升级为 Sing-box 1.14 风格 DNS server 格式。
- 使用 route action 代替已删除的 `block`/`dns` 特殊出站。
- 使用 route `sniff` action，移除 1.13 已删除的入站嗅探字段。
- 为域名型节点配置 `default_domain_resolver`。
- 旧数据库模板会在响应时自动转换，不要求立即手工改数据库。
- 显式报告旧 sing-box 版本时，生成器会反向转换成旧 DNS/入站格式。
- Hiddify/SFM 无法可靠报告内核版本时，按现代 1.14 兼容格式输出。
- Reality VLESS/Trojan 自动补充 uTLS `chrome` 指纹。

Sing-box 兼容依据：

- <https://sing-box.sagernet.org/migration/>
- <https://sing-box.sagernet.org/configuration/dns/server/legacy/>
- <https://sing-box.sagernet.org/configuration/shared/listen/>

### 5.3 其他 Reality 链接

General、Shadowrocket 和 Stash 的 Reality VLESS/Trojan 也增加了 `chrome` 指纹兜底，防止用户切换客户端后再次因缺少指纹而超时。

## 6. 模板存储优先级

生产环境运行时优先读取数据库表 `v2_subscribe_templates`，`resources/rules` 下的文件是新安装、回退和模板损坏时使用的默认值。

因此：

- 本次 PHP 生成器优化会立即处理数据库中的旧模板。
- 更新默认模板文件不会主动覆盖管理员在数据库中保存的自定义规则。
- 不应通过迁移强制覆盖数据库模板，否则会丢失线上自定义策略组和路由。
- 如需让后台编辑器显示新默认模板，应先备份数据库内容，再由管理员有选择地粘贴或重置。

## 7. 超时排查顺序

1. 在节点机确认 Xboard-Node、所选内核和监听端口处于运行状态。
2. 确认节点域名 A/AAAA 记录正确，客户端网络能访问节点端口。
3. 检查 VLESS Reality 的 SNI、公钥、Short ID、Flow 和指纹是否一致。
4. 查看订阅响应是否为 YAML；Mihomo 不应收到 General Base64 文本。
5. 检查节点域名是否进入 `proxy-server-nameserver`、`fake-ip-filter` 和直连规则。
6. 检查 CDN、Nginx、中转端口与面板下发的 `host/port` 是否一致。
7. 若只有某一种客户端失败，对比它实际选中的订阅生成器和版本兼容过滤结果。

## 8. 变更文件与线上替换

运行时必须替换：

```text
app/Support/AbstractProtocol.php
app/Protocols/Clash.php
app/Protocols/ClashMeta.php
app/Protocols/General.php
app/Protocols/Shadowrocket.php
app/Protocols/SingBox.php
app/Protocols/Stash.php
resources/rules/default.clashmeta.yaml
resources/rules/default.sing-box.json
```

测试与文档：

```text
tests/Unit/ProtocolCompatibilityTest.php
docs/zh-CN/node-kernel-subscription-audit-2026-09-19.md
docs/zh-CN/README.md
```

Docker 替换后执行：

```bash
docker exec <xboard容器名> php artisan optimize:clear
docker restart <xboard容器名>
```

若 Web、Horizon、WebSocket 使用多个容器并共享同一代码卷，应全部重启，避免 Octane 常驻进程继续使用旧类。

## 9. 回归验证

- PHPUnit：10 个测试、40 个断言通过。
- PHPStan：本次协议生成相关文件无错误。
- 默认 Sing-box JSON 解析通过。
- 默认 Clash Meta YAML 解析通过。
- 当前数据库真实节点脱敏生成通过。
