# 07 - 开发路线图

> 企业微信管理系统 · 原生 PHP · 分阶段实施计划

---

## 7.1 总体时间预估

| 阶段 | 内容 | 预估工作量 | 优先级 |
|------|------|:---:|:---:|
| **Phase 1** | 项目骨架 + 核心框架 | 2-3 天 | 🔴 必做 |
| **Phase 2** | 通讯录 + 消息 + 认证 API + 管理后台 | 4-5 天 | 🔴 必做 |
| **Phase 3** | 回调系统 + 日志系统 | 2-3 天 | 🔴 必做 |
| **Phase 4** | 客户联系/SCRM | 3-4 天 | 🟡 重要 |
| **Phase 5** | OA 模块（审批/打卡/日程/会议） | 2-3 天 | 🟡 重要 |
| **Phase 6** | 素材 + 应用 + 客服 + 会话存档 | 2-3 天 | 🟢 常用 |
| **Phase 7** | 完善管理后台 + 测试 + 文档 | 3-5 天 | 🟢 常用 |

> **总计：~18-26 天**（1 人全职），建议分阶段交付，核心功能先上线。

---

## 7.2 Phase 1 — 项目骨架 + 核心框架

### 目标
搭建项目基础结构，跑通 API 调用流程

### 任务清单

```
□ 1.1 项目初始化
    □ 创建目录结构（core/, api/, admin/, public/, models/, callback/, sync/, docs/）
    □ 编写 composer.json（PSR-4 autoload）
    □ 编写 config.php 配置模板
    □ 编写 .gitignore
    □ 创建 public/.htaccess（URL 重写）
    □ composer dump-autoload

□ 1.2 数据库初始化
    □ 创建 MySQL 数据库 wxwork_manager
    □ 执行 schema.sql 建表（所有 16 张表）
    □ 插入默认管理员账号

□ 1.3 Config.php
    □ 配置加载（数组 + get/set）
    □ 从 DB 加载的 loadFromDb()
    □ 快捷方法（getCorpId, getApiDomain 等）

□ 1.4 Database.php
    □ PDO 单例连接
    □ select / selectOne / selectValue / execute / insert
    □ 事务支持

□ 1.5 RedisClient.php
    □ Redis 连接（可选，不可用时不报错）
    □ get / set / setnx / del / exists / ttl
    □ isAvailable() 方法

□ 1.6 TokenManager.php
    □ Redis 主存储，DB 兜底
    □ 分布式锁防止并发刷新
    □ 提前刷新机制（过期前 5 分钟）
    □ 强制刷新 clearCache()

□ 1.7 HttpClient.php
    □ GET / POST / POST RAW / Upload
    □ 自动注入 access_token
    □ Token 过期自动重试
    □ 超时控制 + 重试次数
    □ API 日志记录到 wxwork_api_log

□ 1.8 Logger.php
    □ 按日期分文件写日志
    □ 日志级别（DEBUG/INFO/WARN/ERROR）
    □ 简单格式化

□ 1.9 Crypt.php
    □ verifyURL（URL 验证）
    □ decryptMsg（消息解密）
    □ encryptMsg（消息加密）
    □ PKCS7 补位/去补位
    □ SHA1 签名生成与验证

□ 1.10 Wxwork.php（门面）
    □ 构造函数
    □ __get 魔术方法懒加载 API 模块
    □ 快捷 HTTP 方法

□ 1.11 异常类
    □ WxworkException
    □ ApiException（携带 errcode）
    □ TokenException
    □ CryptException

□ 1.12 验证测试
    □ 编写 test/test_token.php（获取 token）
    □ 编写 test/test_http.php（调一个简单 API）
    □ 验证日志写入
```

### 验证标准
```bash
# 能成功获取 access_token 并调用一个 API
php test/test_token.php
# 输出: Token获取成功: xxxxx... 剩余7200秒
#       API调用成功: 获取到 15 个部门
```

---

## 7.3 Phase 2 — 通讯录 + 消息 + 认证 + 管理后台

### 目标
三个核心 API 模块 + 管理后台基本框架

### 任务清单

```
□ 2.1 BaseApi.php
    □ 基类（注入 Wxwork 实例）
    □ 便捷 HTTP 方法（get/post/upload）
    □ isSuccess / result 辅助

□ 2.2 AddressBook.php（完整）
    □ 部门 CRUD（create/update/delete/get/list/listAll）
    □ 成员 CRUD（create/update/delete/batchDelete/get/list/simpleList）
    □ 成员 ID 转换（convertToOpenId/convertToUserId）
    □ 二次验证（authSucc）
    □ 成员手机号/邮箱查 userid
    □ 标签管理（create/update/delete/get/list/addUsers/deleteUsers）
    □ 异步批量操作（syncUser/replaceUser/replaceParty/getResult）

□ 2.3 Message.php
    □ sendText / sendImage / sendVoice / sendVideo / sendFile
    □ sendTextCard / sendNews / sendMpNews
    □ sendMiniProgramNotice / sendTaskCard / sendTemplateCard
    □ sendToChat（群发）
    □ recall（撤回）
    □ Webhook（sendWebhookText/Markdown/Image/News）
    □ 底层 send() 通用方法

□ 2.4 Auth.php
    □ OAuth2 授权 URL 构造
    □ getUserInfo / getUserDetail
    □ 扫码登录 URL 构造
    □ getLoginInfo
    □ JS-SDK ticket + 签名生成

□ 2.5 Validator.php
    □ 规则解析引擎（required/string/int/email/pattern/min/max）
    □ validate + validateOrFail

□ 2.6 Router.php
    □ 路由注册（GET/POST/PUT/DELETE）
    □ 参数提取（{id} 等动态段）
    □ dispatch 分发

□ 2.7 Session.php
    □ login/logout/isLoggedIn/getUser
    □ CSRF token 生成/验证

□ 2.8 管理后台 - 布局
    □ layout/header.php（HTML 头部 + 顶栏）
    □ layout/sidebar.php（侧边栏菜单）
    □ layout/footer.php（底栏）
    □ layout/page.php（渲染引擎）
    □ 基础 CSS 样式（全局变量 + 布局 + 表单 + 表格 + 按钮）

□ 2.9 管理后台 - 登录系统
    □ login.php（登录页面 + 验证）
    □ logout.php
    □ 验证码（简单数学题）

□ 2.10 管理后台 - 仪表盘
    □ 统计卡片（连接真实数据）
    □ 系统状态检测（token/redis/mysql）

□ 2.11 管理后台 - 通讯录
    □ 部门管理页（树形展示 + CRUD）
    □ 成员管理页（列表 + 搜索 + 筛选 + 分页）
    □ 成员编辑页（表单）
    □ 标签管理页（标签列表 + 标签成员）

□ 2.12 管理后台 - 消息推送
    □ 消息发送页（类型切换 + 接收人选择 + 内容编辑）
    □ 发送历史列表
    □ 消息模板管理

□ 2.13 public/index.php 入口
    □ 自动加载 + 路由初始化 + dispatch

□ 2.14 通讯录数据模型
    □ DepartmentModel（增删改查 + upsert + softDelete）
    □ MemberModel
    □ TagModel + TagMemberModel
```

### 验证标准
```
管理后台能：
✅ 登录/退出
✅ 查看部门树、创建/编辑/删除部门
✅ 查看成员列表、搜索筛选、创建/编辑/删除成员
✅ 选择成员发送文本消息、查看发送记录
✅ 管理标签和标签成员
✅ 仪表盘显示基本统计
```

---

## 7.4 Phase 3 — 回调系统

### 目标
完整的回调接收 + 事件分发 + 日志

### 任务清单

```
□ 3.1 Server.php
    □ serve() 主入口
    □ verifyUrl() GET 请求验证
    □ handleEvent() POST 请求处理
    □ on() 注册处理器

□ 3.2 EventDispatcher.php
    □ register / dispatch
    □ 多处理器支持
    □ 兜底处理器

□ 3.3 HandlerInterface.php
□ 3.4 ChangeContact.php（通讯录变更处理器）
□ 3.5 ChangeExternal.php（客户变更处理器）
□ 3.6 DefaultHandler.php（兜底处理器）

□ 3.7 public/callback.php
    □ 初始化 Wxwork
    □ 注册处理器
    □ 调用 serve()

□ 3.8 回调日志
    □ CallbackLogModel
    □ 管理后台回调日志查看页

□ 3.9 消息推送回调支持（可选 Phase 3）
    □ 接收文本/图片等消息
    □ 菜单事件处理
```

### 验证标准
```
配置回调 URL 后：
✅ URL 验证通过（GET 请求返回明文 echostr）
✅ 收到通讯录变更事件，正确解密并记录
✅ 管理后台能看到回调日志
✅ 成员变更自动同步到本地数据库
```

---

## 7.5 Phase 4 — 客户联系/SCRM

### 目标
完整的客户管理功能

### 任务清单

```
□ 4.1 ExternalContact.php（完整 API）
    □ 客户列表 + 详情 + 批量获取
    □ 修改备注
    □ 离职继承（transfer + getTransferResult）
    □ 在职继承（transferCustomer + getTransferCustomerResult）
    □ 客户群管理（list + get + openGidToChatId）
    □ 企业标签（getCorpTagList/add/edit/del/markTag）
    □ 朋友圈（getMomentList/addMomentTask/getMomentTaskResult/cancel）
    □ 客户群发（addMsgTemplate/getGroupMsgListV2/getGroupMsgTask）
    □ 统计（getUserBehaviorData/groupChatStatistic）
    □ 欢迎语（sendWelcomeMsg）
    □ 进群方式（addJoinWay/get/update/del）
    □ 联系我（getContactWay/add/update/del）

□ 4.2 数据模型
    □ ExternalContactModel
    □ ExternalFollowModel
    □ ExternalGroupModel

□ 4.3 数据同步脚本
    □ SyncExternalContacts.php（全量同步客户 + 跟进关系）
    □ SyncTags.php（同步客户标签）

□ 4.4 管理后台 - 客户管理
    □ 客户列表页（搜索/筛选/分页）
    □ 客户详情页（基本信息 + 跟进记录 + 标签）
    □ 客户群列表页
    □ 离职继承管理页
    □ 在职继承管理页

□ 4.5 管理后台 - 客户标签
    □ 标签库管理（企业标签 CRUD）
    □ 给客户打标签
```

---

## 7.6 Phase 5 — OA 模块 ✅ 已完成

### 任务清单

```
✅ 5.1 Approval.php
    ✅ 获取审批模板
    ✅ 创建审批申请
    ✅ 获取审批详情
    ✅ 获取审批单号列表
    ✅ 审批操作（同意/驳回）

✅ 5.2 Calendar.php
    ✅ 日历 CRUD
    ✅ 日程 CRUD
    ✅ 获取日程列表
    ✅ 管理后台：日程管理页（日历+日程 双Tab）

✅ 5.3 Checkin.php
    ✅ 获取打卡规则
    ✅ 获取打卡数据
    ✅ 日报/月报
    ✅ 排班管理（含 setScheduleList）
    ✅ 管理后台：打卡数据查询/统计/CSV导出

✅ 5.4 Meeting.php
    ✅ 创建/修改/取消会议
    ✅ 获取会议详情/列表
    ✅ 管理后台：创建/查询会议 Tab

✅ 5.5 管理后台页面
    ✅ 审批列表 + 详情
    ✅ 打卡数据查询
    ✅ 会议管理
    ✅ 日程管理
```

---

## 7.7 Phase 6 — 素材 + 应用 + 客服 + 文档 + 微盘 ✅ 已完成

### 任务清单

```
✅ 6.1 Media.php
    ✅ 临时素材上传/获取
    ✅ 永久素材上传/获取/删除/列表/数量
    ✅ 上传图片（返回 URL）
    ✅ 管理后台：素材列表/上传/下载

✅ 6.2 AppManage.php
    ✅ 应用列表/详情/设置
    ✅ 自定义菜单 CRUD

✅ 6.3 Kf.php
    ✅ 客服账号管理
    ✅ 接待人员管理
    ✅ 会话消息同步与发送

✅ 6.4 Wedrive.php — 微盘（全面实现）
    ✅ 空间管理（创建/重命名/解散/查询/设置）
    ✅ 空间权限管理（添加/移除/查询成员）
    ✅ 文件管理（列表/上传/下载/新建/重命名/移动/删除）
    ✅ 文件权限管理（添加/删除/查询成员）
    ✅ 分享管理（创建/查询分享链接）
    ✅ 容量查询
    ✅ 管理后台：4Tab（空间/文件/权限/分享）

✅ 6.5 Wedoc.php — 文档（全面实现）
    ✅ 文档创建（含智能表格）
    ✅ 文档基础操作（查询/重命名/删除/列表）
    ✅ 文档内容编辑（编辑/读取数据）
    ✅ 文档分享（创建/查询分享）
    ✅ 文档权限管理（权限查询/修改/加入规则/安全设置）
    ✅ 管理后台：4Tab（查询/创建/权限/分享）

✅ 6.6 管理后台页面
    ✅ 素材管理页
    ✅ 应用设置页
    ✅ 微盘管理页
    ✅ 文档管理页
```

---

## 7.8 Phase 7 — 完善与交付

### 任务清单

```
□ 7.1 管理后台完善
    □ 系统设置页（读写 wxwork_config 表）
    □ 管理员管理页（CRUD 管理员账号）
    □ 数据同步页（手动触发同步任务）
    □ 各页面性能优化、错误处理完善

□ 7.2 定时任务
    □ 编写 cron.example
    □ 通讯录定时同步脚本
    □ 客户数据定时同步脚本

□ 7.3 安全加固
    □ XSS 过滤全局应用
    □ CSRF 保护全覆盖
    □ 登录限流（Redis IP 计数）
    □ 敏感操作审计日志

□ 7.4 测试
    □ API 调用测试（各模块核心方法）
    □ 回调模拟测试
    □ 管理后台功能测试

□ 7.5 文档
    □ README.md（安装/配置/使用说明）
    □ config.example.php 配置说明注释
    □ API 使用示例（examples/ 目录）
```

---

## 7.9 文件创建顺序总结

按开发顺序，文件创建总数约 **55-60 个文件**：

| Phase | 文件数 | 说明 |
|-------|:---:|------|
| Phase 1 | ~15 | 核心框架 + 配置 + 测试 |
| Phase 2 | ~20 | API 模块 + 管理后台 + 模型 |
| Phase 3 | ~8 | 回调系统 |
| Phase 4 | ~8 | 客户联系 + 同步 |
| Phase 5 | ~5 | OA 模块 |
| Phase 6 | ~5 | 素材/应用/客服 |
| Phase 7 | ~5 | 完善 + 文档 + 示例 |

---

## 7.10 开发环境要求

```bash
# 本地开发
PHP 8.0+ + Composer
MySQL 8.0
Redis 6.0+
Apache 2.4+ / Nginx 1.18+

# 推荐开发方式
php -S localhost:8080 -t public/
# 然后浏览器访问 http://localhost:8080
```

---

> 接下来可以进入 **Phase 1** 开始编写代码了。需要我开始动手吗？
