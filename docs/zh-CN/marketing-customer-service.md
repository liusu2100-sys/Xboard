# 营销与客服功能复刻方案

本文档基于当前 Xboard 仓库的实现整理，面向需要在另一个 PHP/Laravel 项目中复刻以下功能的开发人员：优惠券、卡密/礼品卡/CDKEY、邀请返佣、工单及佣金提现申请。

目标不是复制现有控制器代码，而是保留成熟的业务闭环，并修复现有实现中并发、状态语义和审计维度不足的问题。本文中的“目标项目”指需要接入这些能力的新项目。

## 1. 业务范围与依赖

这四个模块应建立在以下基础能力之上：

- 用户认证：能够识别当前用户并获得稳定的用户 ID。
- 商品或套餐：至少包含商品 ID、名称、原价、周期和可售状态。
- 订单：包含订单号、用户、金额、状态、支付完成时间和退款状态。
- 账户能力：余额、流量、套餐有效期、设备数等资产由统一服务修改。
- 管理后台：可供运营创建规则、批量生成卡密、查询审计流水和处理工单。

整体关系如下：

```text
注册（可使用邀请码）
  -> 用户关系绑定
  -> 下单（可使用优惠券、余额或升级差额）
  -> 支付完成并开通套餐
  -> 创建待结算邀请佣金
  -> 定时任务结算佣金

卡密兑换 -> 校验资格 -> 发放余额/流量/套餐 -> 写兑换审计
工单      -> 用户提问 -> 管理员回复 -> 通知、关闭或提现审核
```

当前 Xboard 用户端路由在 `app/Http/Routes/V1/UserRoute.php`，后台路由在 `app/Http/Routes/V2/AdminRoute.php`。业务代码主要位于 `app/Services`，这是目标项目应采用的分层方式：Controller 只负责参数和响应，Service 负责规则与事务。

## 2. 通用实现约定

### 2.1 金额、时间与状态

- 所有金额以分保存为整数。例如 `1888` 代表 `18.88` 元；不要使用浮点数计算金额。
- 时间建议使用数据库 `datetime`；若与 Xboard 数据兼容，可使用 Unix 秒级整数。一个项目内必须保持一致。
- 所有状态使用明确常量或 PHP Enum，不要依赖魔法数字散落在控制器中。
- 所有资产变化都应生成不可变流水，至少记录来源、关联业务 ID、变更前后值、操作者和时间。

### 2.2 事务与幂等

下列操作必须使用数据库事务和行锁：

- 使用优惠券创建订单。
- 兑换卡密并发放奖励。
- 消耗邀请码使用次数并创建邀请关系。
- 支付回调完成订单并创建佣金记录。
- 审核佣金提现并扣减可提现余额。

支付回调、兑换请求和定时任务均可能重复执行。目标项目要为它们设置唯一约束或幂等键，例如 `unique(payment_channel, payment_id)`、`unique(code_id, request_id)` 和 `unique(order_id, beneficiary_user_id, level)`。

### 2.3 建议目录

```text
app/
  Domain/
    Coupon/CouponService.php
    GiftCard/GiftCardService.php
    GiftCard/RewardService.php
    Referral/ReferralService.php
    Referral/CommissionService.php
    Ticket/TicketService.php
  Http/Controllers/Api/
  Http/Controllers/Admin/
  Jobs/
  Console/Commands/
```

`RewardService` 是关键公共层。余额、流量、套餐、有效期和设备数的增加或减少都应通过它完成，卡密奖励、人工补偿、邀请奖励和活动奖励因此能共享一致的校验与审计逻辑。

## 3. 优惠券

### 3.1 功能描述

优惠券用于在创建订单时减少应付金额。它可以是固定金额减免或百分比折扣，并可限制使用期限、总库存、单用户次数、适用套餐和适用周期。

当前 Xboard 由 `CouponService` 实现主要规则：检查启用状态、库存、开始/结束时间、套餐限制、周期限制、单用户次数，随后计算折扣并扣减全局库存。对应实现为 `app/Services/CouponService.php`。

### 3.2 推荐表结构

```sql
CREATE TABLE coupons (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  discount_type TINYINT NOT NULL COMMENT '1=fixed cents, 2=percentage',
  discount_value INT UNSIGNED NOT NULL,
  total_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  per_user_limit INT UNSIGNED NULL,
  plan_ids JSON NULL,
  periods JSON NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status TINYINT NOT NULL DEFAULT 1 COMMENT '1=active, 0=disabled',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE coupon_usages (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  discount_amount INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_coupon_order (coupon_id, order_id),
  KEY idx_coupon_user (coupon_id, user_id)
);
```

### 3.3 接口契约

```text
POST /api/coupons/validate
POST /api/orders

GET    /api/admin/coupons
POST   /api/admin/coupons
PATCH  /api/admin/coupons/{id}
POST   /api/admin/coupons/batch
DELETE /api/admin/coupons/{id}
```

`POST /api/coupons/validate` 只能返回是否可用、折扣金额和原因，不能消耗次数。消耗必须发生在订单创建事务中；否则用户仅刷新页面就可能耗尽优惠券。

### 3.4 下单规则

1. 锁定优惠券行：`SELECT ... FOR UPDATE`。
2. 校验状态、时间、库存、套餐、周期和该用户历史使用次数。
3. 计算优惠：固定金额直接减；百分比使用整数运算并向下取整。
4. 折扣不得超过订单商品金额。
5. 写订单折扣字段、`coupon_usages` 和 `used_count`。
6. 订单创建失败时回滚全部操作。

单用户次数的统计应排除未支付和已取消订单。退款后的优惠券是否返还必须明确规则：通常不返还使用资格；如果业务要求返还，应记录反向流水，不能简单递减计数。

## 4. 卡密、礼品卡与 CDKEY

### 4.1 功能描述

当前 Rocket 主题将“礼品卡”和“CDKEY”作为不同入口，但后端复用同一礼品卡体系。目标项目也应只维护一套兑换系统，前端根据场景显示不同名称即可。

卡密分为三种业务类型：

- 通用卡：奖励余额、流量、设备数、有效期或流量重置。
- 套餐卡：为符合条件的用户开通指定套餐。
- 盲盒卡：按权重从奖励池随机选择一项奖励。

当前实现可参考 `app/Services/GiftCardService.php`、`app/Models/GiftCardTemplate.php` 和 `app/Models/GiftCardCode.php`。

### 4.2 推荐表结构

```sql
CREATE TABLE gift_card_templates (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  type TINYINT NOT NULL COMMENT '1=general, 2=plan, 3=mystery',
  conditions JSON NULL,
  rewards JSON NOT NULL,
  limits JSON NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE gift_card_codes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL UNIQUE,
  batch_no VARCHAR(64) NULL,
  state TINYINT NOT NULL DEFAULT 1 COMMENT '1=active, 2=disabled, 3=expired',
  max_usage INT UNSIGNED NOT NULL DEFAULT 1,
  usage_count INT UNSIGNED NOT NULL DEFAULT 0,
  first_used_at DATETIME NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_card_batch (batch_no),
  KEY idx_card_template (template_id)
);

CREATE TABLE gift_card_usages (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  request_id VARCHAR(64) NULL,
  rewards JSON NOT NULL,
  source_ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_card_request (code_id, request_id),
  KEY idx_card_user (code_id, user_id)
);
```

### 4.3 规则 JSON 约定

奖励示例：

```json
{
  "balance": 1000,
  "traffic_bytes": 107374182400,
  "plan_id": 3,
  "plan_validity_days": 30,
  "device_limit": 3,
  "reset_traffic": false
}
```

使用条件示例：

```json
{
  "new_user_only": true,
  "paid_user_only": false,
  "required_invite": false,
  "allowed_plan_ids": [1, 2],
  "max_use_per_user": 1,
  "cooldown_hours": 0
}
```

盲盒奖励建议为 `[{ "weight": 80, "reward": {...} }]`。抽取后必须把实际奖励快照写入 `gift_card_usages`，而非只保留模板配置。

### 4.4 接口与兑换事务

```text
POST /api/gift-cards/check
POST /api/gift-cards/redeem
GET  /api/gift-cards/history

GET  /api/admin/gift-card/templates
POST /api/admin/gift-card/templates
POST /api/admin/gift-card/codes/generate
PATCH /api/admin/gift-card/codes/{id}
GET  /api/admin/gift-card/codes/export
```

兑换流程：

1. 事务内通过兑换码查询并锁定 `gift_card_codes` 行。
2. 校验模板启用、卡状态、到期时间、使用次数和用户条件。
3. 校验该用户的兑换次数及冷却时间。
4. 计算最终奖励，盲盒在此时抽取。
5. 调用 `RewardService` 发放资产。
6. 原子更新 `usage_count`，维护首次和最近使用时间。
7. 写入 `gift_card_usages` 审计记录后提交事务。

不要将“已使用”设计成卡密唯一状态。多次使用卡在第一次使用后仍应保持 `active`，可用性只由 `state`、`expires_at` 和 `usage_count < max_usage` 判断。

## 5. 邀请码与返佣

### 5.1 功能描述

邀请模块包含邀请码生成、注册绑定、访问统计、订单佣金、佣金结算、转余额和提现申请。一个用户只绑定一个直接邀请人，但佣金可沿邀请链上溯分配给一至三级邀请人。

当前 Xboard 用户侧接口包括生成、删除、统计和详情；订单服务创建佣金数据，`CheckCommission` 定时任务进行延迟结算。相关代码位于 `app/Http/Controllers/V1/User/InviteController.php`、`app/Services/OrderService.php` 和 `app/Console/Commands/CheckCommission.php`。

### 5.2 推荐表结构

```sql
CREATE TABLE invite_codes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL UNIQUE,
  status TINYINT NOT NULL DEFAULT 1,
  max_uses INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_invite_user (user_id)
);

CREATE TABLE referrals (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  inviter_id BIGINT UNSIGNED NOT NULL,
  invitee_id BIGINT UNSIGNED NOT NULL,
  invite_code_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_referral_invitee (invitee_id),
  KEY idx_referral_inviter (inviter_id)
);

CREATE TABLE commission_records (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  beneficiary_user_id BIGINT UNSIGNED NOT NULL,
  source_user_id BIGINT UNSIGNED NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  rate DECIMAL(5,2) NOT NULL,
  order_net_amount INT UNSIGNED NOT NULL,
  commission_amount INT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL COMMENT 'pending, available, void, withdrawn',
  available_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_commission_order_level (order_id, beneficiary_user_id, level),
  KEY idx_commission_owner_status (beneficiary_user_id, status)
);
```

### 5.3 核心规则

- 注册时锁定邀请码，创建用户后再写 `referrals`；数据库唯一约束确保用户不可更换邀请人。
- 邀请码可以配置强制注册、可重复使用、最大使用次数和有效期。
- 佣金以订单净实付金额为基础，即扣除优惠券、余额、折扣和升级抵扣后的金额。
- 支付完成仅创建 `pending` 佣金；等待退款观察期后由定时任务变更为 `available`。
- 订单退款、拒付或撤销时，将尚未提现的相关佣金变更为 `void`。
- 每一笔佣金必须保存 `level`、`rate` 和 `order_net_amount` 快照，不能在结算时读取当前后台比例。

提现建议单独设计 `commission_withdrawals` 表并经过审核。不要在用户提交申请时直接扣减佣金；应先冻结，审核通过后再变更为 `withdrawn`，拒绝时解冻。

## 6. 工单与佣金提现工单

### 6.1 功能描述

工单用于售后、技术支持、账单问题和提现申请。用户可创建、查看、回复和关闭；管理员可筛选、回复、关闭并触发通知。

当前 Xboard 通过 `TicketService` 创建工单和消息，并限制每位用户仅保留一个未关闭工单。管理员回复后会发送邮件通知。实现位于 `app/Services/TicketService.php`。

### 6.2 推荐表结构

```sql
CREATE TABLE tickets (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(255) NOT NULL,
  category VARCHAR(32) NOT NULL DEFAULT 'support',
  priority TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'pending_staff',
  last_message_role VARCHAR(16) NOT NULL,
  last_message_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_ticket_user_status (user_id, status),
  KEY idx_ticket_staff_queue (status, priority, last_message_at)
);

CREATE TABLE ticket_messages (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NULL,
  sender_role VARCHAR(16) NOT NULL COMMENT 'user, admin, system',
  content TEXT NOT NULL,
  attachments JSON NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ticket_message (ticket_id, created_at)
);
```

### 6.3 接口与状态机

```text
POST /api/tickets
GET  /api/tickets
GET  /api/tickets/{id}
POST /api/tickets/{id}/messages
POST /api/tickets/{id}/close

GET  /api/admin/tickets
POST /api/admin/tickets/{id}/messages
POST /api/admin/tickets/{id}/close
```

建议状态如下：

```text
用户创建/用户回复 -> pending_staff
管理员回复          -> pending_user
任一方关闭          -> closed
超时自动关闭        -> closed
```

关键规则：

- 创建和回复都必须验证工单所属用户，避免通过 ID 越权读取或回复。
- 若开启“等待回复”模式，`last_message_role = user` 时禁止用户再次发送消息。
- 已关闭工单不可回复；用户需要创建新工单。
- 自动关闭只应依据 `last_message_at`、`last_message_role` 和状态，不要依赖未维护字段。
- 提现申请可自动生成 `category=withdrawal` 工单，但实际资金冻结与审核应在提现模块处理。

## 7. 当前 Xboard 实现可借鉴与应修正项

| 模块 | 可直接借鉴 | 目标项目应修正 |
| --- | --- | --- |
| 优惠券 | 套餐、周期、库存、单用户次数限制 | 将查询和扣减完全放入订单事务，记录独立使用流水 |
| 礼品卡 | 模板、批次、预检查、盲盒、邀请人奖励 | 在事务内锁码；将可用状态与使用计数分离 |
| 邀请返佣 | 订单净额返佣、延迟结算、三级关系 | 邀请码加唯一约束；佣金保存级别和比例快照 |
| 工单 | 一人一张未关闭工单、管理员邮件通知 | 使用明确状态机和最后消息字段，完善权限与附件审计 |

特别注意：当前 `GiftCardService` 在构造阶段读取兑换码，兑换事务中没有重新锁定该行；高并发下可能突破最大使用次数。现有工单自动关闭逻辑也不宜原样复制，应改为使用本文定义的 `last_message_role` 和 `last_message_at`。

## 8. 开发与上线顺序

1. 建立订单、支付回调、余额账户和资产流水。
2. 实现优惠券与覆盖下单金额计算的自动化测试。
3. 实现邀请码注册绑定、订单佣金记录和延迟结算任务。
4. 实现礼品卡模板、批量生成、兑换、奖励发放和审计查询。
5. 实现工单、管理员后台、邮件通知与佣金提现审核。
6. 增加权限控制、速率限制、操作日志、导出和监控告警。

上线前至少覆盖以下测试：优惠券并发超限、卡密并发兑换、重复支付回调、重复兑换请求、邀请码并发注册、退款撤销佣金、工单越权访问、提现审核重复提交。

## 9. Xboard 对照入口

| 业务 | 当前代码入口 |
| --- | --- |
| 优惠券 | `app/Services/CouponService.php`、`app/Http/Controllers/V1/User/CouponController.php` |
| 卡密 | `app/Services/GiftCardService.php`、`app/Http/Controllers/V1/User/GiftCardController.php` |
| 邀请返佣 | `app/Http/Controllers/V1/User/InviteController.php`、`app/Services/OrderService.php`、`app/Console/Commands/CheckCommission.php` |
| 工单 | `app/Services/TicketService.php`、`app/Http/Controllers/V1/User/TicketController.php`、`app/Console/Commands/CheckTicket.php` |
| 用户端路由 | `app/Http/Routes/V1/UserRoute.php` |
| 后台管理路由 | `app/Http/Routes/V2/AdminRoute.php` |

目标项目若不是 Laravel，仍可保持本文的表结构、事务边界、接口契约与状态机；只需将 Service 替换为对应技术栈的领域服务即可。
