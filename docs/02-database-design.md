# 02 - 数据库设计

> 企业微信管理系统 · MySQL 8.0 · InnoDB · utf8mb4 · 设计文档

---

## 2.1 命名规范

| 规范 | 示例 |
|------|------|
| 表名 | `wxwork_` 前缀 + 下划线小写 | `wxwork_department` |
| 字段 | 下划线小写 | `created_at` |
| 主键 | 统一 `id` (INT UNSIGNED AUTO_INCREMENT) | `id` |
| 时间戳 | 统一 `created_at`, `updated_at` (DATETIME) | `created_at` |
| 布尔值 | TINYINT(1) DEFAULT 0 | `is_enabled` |
| JSON | JSON 类型（MySQL 8.0 原生支持） | `extra_data` |
| 关联字段 | `{表名}_id` | `department_id` |

---

## 2.2 表分类

```
数据库 wxwork_manager
│
├── 🔧 系统配置表 (3张)
│   ├── wxwork_config          系统配置
│   ├── wxwork_token            access_token 存储
│   └── wxwork_admin_user      后台管理员
│
├── 📇 通讯录表 (4张)
│   ├── wxwork_department      部门
│   ├── wxwork_member          成员
│   ├── wxwork_tag             标签
│   └── wxwork_tag_member      标签-成员关联
│
├── 👥 客户联系表 (3张)
│   ├── wxwork_external_contact   客户
│   ├── wxwork_external_group     客户群
│   └── wxwork_external_follow    跟进人关系
│
├── 📨 消息/日志表 (3张)
│   ├── wxwork_message_log        消息发送记录
│   ├── wxwork_callback_log       回调日志
│   └── wxwork_api_log            API 请求日志
│
└── 📋 业务辅助表 (3张)
    ├── wxwork_approval_template  审批模板缓存
    ├── wxwork_media              素材记录
    └── wxwork_message_template   消息模板
```

共 **16 张表**。

---

## 2.3 详细建表语句

### 2.3.1 系统配置表

#### `wxwork_config` — 系统配置

```sql
CREATE TABLE `wxwork_config` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `cfg_key` VARCHAR(100) NOT NULL COMMENT '配置键',
    `cfg_value` TEXT COMMENT '配置值',
    `cfg_desc` VARCHAR(500) DEFAULT '' COMMENT '配置说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`cfg_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- 初始数据
INSERT INTO `wxwork_config` (`cfg_key`, `cfg_value`, `cfg_desc`) VALUES
('corpid', '', '企业ID'),
('corpsecret', '', '应用Secret'),
('token', '', '回调Token（用于签名验证）'),
('encoding_aes_key', '', '回调消息加解密Key'),
('agent_id', '', '应用AgentId'),
('callback_url', '', '回调URL'),
('api_domain', 'https://qyapi.weixin.qq.com', 'API域名'),
('token_ahead_refresh', '300', 'Token提前刷新秒数'),
('http_timeout', '10', 'HTTP请求超时秒数'),
('http_retry', '1', 'HTTP重试次数');
```

#### `wxwork_token` — access_token 存储

```sql
CREATE TABLE `wxwork_token` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `token` VARCHAR(1024) NOT NULL COMMENT 'access_token值',
    `expires_at` DATETIME NOT NULL COMMENT '过期时间（expires_in换算）',
    `token_type` VARCHAR(20) NOT NULL DEFAULT 'access_token' COMMENT 'access_token / suite_token / provider_token',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1有效 0无效',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type_status` (`token_type`, `status`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='access_token存储表（DB兜底）';
```

#### `wxwork_admin_user` — 后台管理员

```sql
CREATE TABLE `wxwork_admin_user` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（password_hash）',
    `realname` VARCHAR(50) DEFAULT '' COMMENT '真实姓名',
    `email` VARCHAR(100) DEFAULT '' COMMENT '邮箱',
    `role` VARCHAR(20) NOT NULL DEFAULT 'admin' COMMENT '角色：admin/superadmin',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `last_login_at` DATETIME COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) DEFAULT '' COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员表';

-- 默认管理员: admin / admin123
INSERT INTO `wxwork_admin_user` (`username`, `password`, `realname`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员', 'superadmin');
```

---

### 2.3.2 通讯录表

#### `wxwork_department` — 部门

```sql
CREATE TABLE `wxwork_department` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `dept_id` BIGINT UNSIGNED NOT NULL COMMENT '企业微信部门ID',
    `name` VARCHAR(200) NOT NULL COMMENT '部门名称',
    `name_en` VARCHAR(200) DEFAULT '' COMMENT '英文名称',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父部门ID（企业微信）',
    `order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `leader_userid` VARCHAR(128) DEFAULT '' COMMENT '部门负责人',
    `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '同步版本号',
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已删除（企业微信删除后标记）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_dept_id` (`dept_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信部门表';
```

#### `wxwork_member` — 成员

```sql
CREATE TABLE `wxwork_member` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `userid` VARCHAR(128) NOT NULL COMMENT '成员UserID',
    `name` VARCHAR(200) NOT NULL COMMENT '姓名',
    `english_name` VARCHAR(200) DEFAULT '' COMMENT '英文名',
    `alias` VARCHAR(200) DEFAULT '' COMMENT '别名',
    `mobile` VARCHAR(30) DEFAULT '' COMMENT '手机号',
    `email` VARCHAR(100) DEFAULT '' COMMENT '邮箱',
    `gender` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '性别：0未定义 1男 2女',
    `position` VARCHAR(200) DEFAULT '' COMMENT '职位',
    `telephone` VARCHAR(50) DEFAULT '' COMMENT '座机',
    `avatar` VARCHAR(500) DEFAULT '' COMMENT '头像URL',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '激活状态：1已激活 2已禁用 4未激活 5退出',
    `is_leader_in_dept` VARCHAR(500) DEFAULT '' COMMENT '在哪些部门是负责人，JSON: {"dept_id":1}',
    `open_userid` VARCHAR(128) DEFAULT '' COMMENT '全局唯一userid',
    `main_department` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '主部门ID',
    `extattr` JSON COMMENT '自定义字段',
    `external_profile` JSON COMMENT '对外属性',
    `address` VARCHAR(500) DEFAULT '' COMMENT '地址',
    `qr_code` VARCHAR(500) DEFAULT '' COMMENT '员工个人二维码',
    `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '同步版本号',
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已删除',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_userid` (`userid`),
    KEY `idx_mobile` (`mobile`),
    KEY `idx_name` (`name`),
    KEY `idx_status` (`status`),
    KEY `idx_main_dept` (`main_department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信成员表';
```

#### `wxwork_tag` — 标签

```sql
CREATE TABLE `wxwork_tag` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `tag_id` INT UNSIGNED NOT NULL COMMENT '企业微信标签ID',
    `tag_name` VARCHAR(200) NOT NULL COMMENT '标签名称',
    `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '同步版本号',
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已删除',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tag_id` (`tag_id`),
    KEY `idx_tag_name` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信标签表';
```

#### `wxwork_tag_member` — 标签-成员关联

```sql
CREATE TABLE `wxwork_tag_member` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `tag_id` INT UNSIGNED NOT NULL COMMENT '标签ID',
    `userid` VARCHAR(128) NOT NULL COMMENT '成员UserID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tag_user` (`tag_id`, `userid`),
    KEY `idx_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签成员关联表';
```

---

### 2.3.3 客户联系表

#### `wxwork_external_contact` — 客户

```sql
CREATE TABLE `wxwork_external_contact` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `external_userid` VARCHAR(128) NOT NULL COMMENT '客户ExternalUserID',
    `name` VARCHAR(200) NOT NULL COMMENT '客户名称',
    `avatar` VARCHAR(500) DEFAULT '' COMMENT '头像URL',
    `gender` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '性别',
    `type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '客户类型：1微信用户 2企业微信用户',
    `unionid` VARCHAR(128) DEFAULT '' COMMENT '微信unionid',
    `corp_name` VARCHAR(500) DEFAULT '' COMMENT '企业全称',
    `corp_full_name` VARCHAR(500) DEFAULT '' COMMENT '企业简称',
    `external_profile` JSON COMMENT '自定义展示字段',
    `sync_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_external_userid` (`external_userid`),
    KEY `idx_name` (`name`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信客户表';
```

#### `wxwork_external_follow` — 跟进人关系

```sql
CREATE TABLE `wxwork_external_follow` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `external_userid` VARCHAR(128) NOT NULL COMMENT '客户ExternalUserID',
    `userid` VARCHAR(128) NOT NULL COMMENT '跟进人UserID',
    `remark` VARCHAR(200) DEFAULT '' COMMENT '跟进人对客户的备注',
    `description` VARCHAR(500) DEFAULT '' COMMENT '跟进人对客户的描述',
    `remark_corp_name` VARCHAR(200) DEFAULT '' COMMENT '备注的企业名称',
    `remark_mobiles` VARCHAR(500) DEFAULT '' COMMENT '备注的手机号，逗号分隔',
    `add_way` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '添加来源',
    `state` VARCHAR(128) DEFAULT '' COMMENT '添加渠道state',
    `add_time` DATETIME COMMENT '添加时间',
    `tags` JSON COMMENT '客户标签',
    `sync_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_follow` (`external_userid`, `userid`),
    KEY `idx_userid` (`userid`),
    KEY `idx_add_time` (`add_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户跟进人关联表';
```

#### `wxwork_external_group` — 客户群

```sql
CREATE TABLE `wxwork_external_group` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `chat_id` VARCHAR(128) NOT NULL COMMENT '客户群ID',
    `name` VARCHAR(500) DEFAULT '' COMMENT '群名称',
    `owner` VARCHAR(128) DEFAULT '' COMMENT '群主UserID',
    `member_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '群成员数',
    `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '群状态：0正常 1离职待继承 2离职继承中 3离职继承完成',
    `notice` TEXT COMMENT '群公告',
    `create_time` DATETIME COMMENT '建群时间',
    `sync_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_chat_id` (`chat_id`),
    KEY `idx_owner` (`owner`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业微信客户群表';
```

---

### 2.3.4 消息与日志表

#### `wxwork_message_log` — 消息发送记录

```sql
CREATE TABLE `wxwork_message_log` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `msg_type` VARCHAR(30) NOT NULL COMMENT '消息类型：text/image/voice/video/file/textcard/news/mpnews/miniprogram_notice/taskcard/template_card',
    `to_users` TEXT COMMENT '接收人（JSON数组）',
    `to_party` TEXT COMMENT '接收部门（JSON数组）',
    `to_tag` TEXT COMMENT '接收标签（JSON数组）',
    `content` JSON COMMENT '消息内容',
    `safe` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否保密消息',
    `enable_id_trans` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否开启ID转译',
    `enable_duplicate_check` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否开启重复检查',
    `msg_id` VARCHAR(128) DEFAULT '' COMMENT '企业微信返回的msgid',
    `invalid_user` TEXT COMMENT '不合法的userid列表',
    `invalid_party` TEXT COMMENT '不合法的partyid列表',
    `invalid_tag` TEXT COMMENT '不合法的tagid列表',
    `errcode` INT NOT NULL DEFAULT 0 COMMENT '企业微信返回的错误码',
    `errmsg` VARCHAR(500) DEFAULT '' COMMENT '企业微信返回的错误信息',
    `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '发送状态：0待发送 1成功 2失败',
    `sent_by` VARCHAR(50) DEFAULT '' COMMENT '发送人（后台管理员）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_msg_type` (`msg_type`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`),
    KEY `idx_msg_id` (`msg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息发送记录表';
```

#### `wxwork_callback_log` — 回调日志

```sql
CREATE TABLE `wxwork_callback_log` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `event_type` VARCHAR(50) NOT NULL COMMENT '事件类型：change_contact/change_external_contact/sys_approval_change等',
    `change_type` VARCHAR(50) DEFAULT '' COMMENT '变更类型：create_user/update_user/delete_user等',
    `raw_xml` TEXT COMMENT '原始XML（加密前）',
    `decrypted_xml` TEXT COMMENT '解密后XML',
    `parsed_data` JSON COMMENT '解析后的数据',
    `handler` VARCHAR(200) DEFAULT '' COMMENT '处理的Handler类名',
    `handle_result` VARCHAR(50) DEFAULT 'success' COMMENT '处理结果',
    `handle_time_ms` INT UNSIGNED DEFAULT 0 COMMENT '处理耗时（毫秒）',
    `error_msg` TEXT COMMENT '错误信息（如有）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_created` (`created_at`),
    KEY `idx_handler` (`handler`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='回调日志表';
```

#### `wxwork_api_log` — API 请求日志

```sql
CREATE TABLE `wxwork_api_log` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `method` VARCHAR(10) NOT NULL COMMENT '请求方法：GET/POST',
    `url` VARCHAR(1024) NOT NULL COMMENT '完整请求URL',
    `request_body` TEXT COMMENT '请求体',
    `response_http_code` INT NOT NULL DEFAULT 0 COMMENT 'HTTP状态码',
    `response_body` TEXT COMMENT '响应体',
    `response_errcode` INT DEFAULT NULL COMMENT '企业微信errcode',
    `response_errmsg` VARCHAR(500) DEFAULT '' COMMENT '企业微信errmsg',
    `elapsed_ms` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '请求耗时（毫秒）',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '重试次数',
    `token_used` VARCHAR(100) DEFAULT '' COMMENT '使用的token（脱敏）',
    `ip` VARCHAR(50) DEFAULT '' COMMENT '本机IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_url` (`url`(255)),
    KEY `idx_created` (`created_at`),
    KEY `idx_errcode` (`response_errcode`),
    KEY `idx_method` (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API请求日志表';
```

---

### 2.3.5 业务辅助表

#### `wxwork_approval_template` — 审批模板缓存

```sql
CREATE TABLE `wxwork_approval_template` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `template_id` VARCHAR(128) NOT NULL COMMENT '审批模板ID',
    `template_name` VARCHAR(500) NOT NULL COMMENT '模板名称',
    `template_content` JSON COMMENT '模板内容（控件、流程等）',
    `template_icon` VARCHAR(500) DEFAULT '' COMMENT '模板图标',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审批模板缓存表';
```

#### `wxwork_media` — 素材记录

```sql
CREATE TABLE `wxwork_media` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `media_id` VARCHAR(256) NOT NULL COMMENT '企业微信返回的media_id',
    `media_type` VARCHAR(30) NOT NULL COMMENT '素材类型：image/voice/video/file',
    `filename` VARCHAR(500) DEFAULT '' COMMENT '原始文件名',
    `file_size` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
    `local_path` VARCHAR(500) DEFAULT '' COMMENT '本地存储路径',
    `url` VARCHAR(1024) DEFAULT '' COMMENT '临时访问URL',
    `expires_at` DATETIME COMMENT '临时素材过期时间（3天）',
    `is_permanent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否永久素材',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_media_id` (`media_id`),
    KEY `idx_type` (`media_type`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材记录表';
```

#### `wxwork_message_template` — 消息模板

```sql
CREATE TABLE `wxwork_message_template` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `tpl_name` VARCHAR(100) NOT NULL COMMENT '模板名称',
    `tpl_desc` VARCHAR(500) DEFAULT '' COMMENT '模板描述',
    `msg_type` VARCHAR(30) NOT NULL COMMENT '消息类型',
    `content` JSON COMMENT '消息内容模板（支持变量）',
    `variables` JSON COMMENT '模板变量列表',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tpl_name` (`tpl_name`),
    KEY `idx_type` (`msg_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息模板表';
```

---

## 2.4 表关系图（ER 简化版）

```
wxwork_department ──<  wxwork_member       (一对多：部门有多个成员)
wxwork_tag ──< wxwork_tag_member >── wxwork_member  (多对多：标签-成员)

wxwork_member ──< wxwork_external_follow >── wxwork_external_contact
                                                (多对多：成员跟进多个客户)

wxwork_member ──< wxwork_external_group      (成员是群主)

wxwork_admin_user ──< wxwork_message_log     (谁发了什么消息)

(无外键约束，应用层维护关系)
```

---

## 2.5 索引策略

| 原则 | 说明 |
|------|------|
| **唯一索引** | 企业微信 ID 字段（`dept_id`, `userid`, `tag_id`, `external_userid` 等）必须唯一索引 |
| **复合索引** | 关联查询字段建复合索引，如 `(external_userid, userid)` |
| **时间索引** | 日志类表的 `created_at` 必须建索引 |
| **不建外键** | 避免级联操作隐患，应用层保证一致性 |
| **JSON 字段不建索引** | MySQL 8.0 支持 JSON 索引但场景有限，更多用作灵活存储 |

---

## 2.6 分区策略（可选）

日志表 `wxwork_api_log` 和 `wxwork_callback_log` 数据量增长快，建议：

```sql
-- 按月分区（需在建表时定义）
ALTER TABLE wxwork_api_log 
PARTITION BY RANGE (TO_DAYS(created_at)) (
    PARTITION p202501 VALUES LESS THAN (TO_DAYS('2025-02-01')),
    PARTITION p202502 VALUES LESS THAN (TO_DAYS('2025-03-01')),
    ...
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

也可以考虑定时清理 90 天前的日志数据。

---

## 2.7 数据同步策略

通讯录和客户数据需要**定时从企业微信同步到本地 MySQL**，同步策略：

| 表 | 同步方式 | 频率 |
|------|----------|------|
| `wxwork_department` | 全量拉取 → 逐条 upsert | 每天 1 次 |
| `wxwork_member` | 全量拉取 → 逐条 upsert | 每天 1 次 |
| `wxwork_tag` | 全量拉取 → 逐条 upsert | 每天 1 次 |
| `wxwork_tag_member` | 先删后插 | 每天 1 次 |
| `wxwork_external_contact` | 分页拉取 → 逐条 upsert | 每 6 小时 |
| `wxwork_external_follow` | 分页拉取 → 逐条 upsert | 每 6 小时 |

**增量更新**：通过回调事件实时更新单条记录。

---

> 下一章节：[03 - 核心框架设计](./03-core-framework.md)
