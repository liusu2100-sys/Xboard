# Xboard 中文文档

当前中文文档主要面向 Xboard 二次开发、主题开发、订阅模板维护和 Docker 线上部署。

## 文档列表

- [项目介绍文档](./project-overview.md)
- [开发文档](./development-guide.md)
- [营销与客服功能复刻方案](./marketing-customer-service.md)
- [订阅设备管理开发与变更记录](./subscription-device-management.md)
- [稳定性与幂等优化变更记录](./stability-idempotency-2026-09-18.md)
- [节点内核与订阅模板兼容性审计](./node-kernel-subscription-audit-2026-09-19.md)

## 阅读建议

第一次接手项目时，建议先读项目介绍文档，理解模块边界、运行拓扑、业务闭环和主题系统。

准备动手开发时，再读开发文档，按其中的后端开发、主题开发、订阅协议开发、发布和排障流程执行。

需要将优惠券、卡密、邀请返佣或工单能力迁移到其他项目时，阅读营销与客服功能复刻方案；该文档同时记录当前 Xboard 的实现边界与建议修正项。
