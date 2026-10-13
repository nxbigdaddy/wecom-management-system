# 05 - 管理后台设计

> 企业微信管理系统 · 原生 PHP · Web 管理界面 · 设计文档

---

## 5.1 设计原则

| 原则 | 说明 |
|------|------|
| **原生 PHP 模板** | 不引入模板引擎，PHP 本身就是模板语言 |
| **前后端混合渲染** | 首屏 PHP 直出 HTML，交互用原生 JS |
| **响应式布局** | 适配 PC + 平板，移动端可浏览但不做重度交互 |
| **纯 CSS（无框架）** | 手写 CSS，或使用极简 CSS 库如 Pico.css（可选） |
| **CSRF 保护** | 所有 POST 操作带 CSRF Token |
| **简单 RBAC** | 支持超级管理员和普通管理员角色 |

---

## 5.2 页面结构

### 5.2.1 布局模板

```
┌─────────────────────────────────────────────────────────┐
│  Header（顶栏）                                         │
│  [Logo] 企业微信管理系统           [用户头像▼] [退出]    │
├────────────────┬────────────────────────────────────────┤
│  Sidebar       │  Content（内容区）                      │
│  （侧边栏）     │                                        │
│                │  ┌──────────────────────────────────┐  │
│  ▸ 仪表盘      │  │  面包屑导航                       │  │
│  ▸ 通讯录管理   │  │  ├ 首页 / 通讯录 / 部门列表       │  │
│    ├ 部门管理   │  ├──────────────────────────────────┤  │
│    ├ 成员管理   │  │                                  │  │
│    └ 标签管理   │  │  具体功能内容                     │  │
│  ▸ 消息推送     │  │  （表格/表单/图表）               │  │
│  ▸ 客户联系     │  │                                  │  │
│  ▸ 审批管理     │  │                                  │  │
│  ▸ 素材管理     │  │                                  │  │
│  ▸ 回调日志     │  │                                  │  │
│  ▸ 系统设置     │  │                                  │  │
│                │  └──────────────────────────────────┘  │
├────────────────┴────────────────────────────────────────┤
│  Footer（底栏）© 2025 WxWork Manager                   │
└─────────────────────────────────────────────────────────┘
```

### 5.2.2 文件组织

```
admin/
├── layout/
│   ├── header.php       # <!DOCTYPE> ~ <body> + 顶栏
│   ├── footer.php       # 底栏 + </body></html>
│   ├── sidebar.php      # 侧边栏菜单
│   └── page.php         # 渲染入口：render($title, $view, $data)
│
├── modules/
│   ├── dashboard/
│   │   └── index.php    # 仪表盘
│   ├── contacts/
│   │   ├── departments.php    # 部门列表
│   │   ├── department_edit.php# 部门编辑
│   │   ├── members.php        # 成员列表
│   │   ├── member_edit.php    # 成员编辑
│   │   ├── member_import.php  # 批量导入
│   │   ├── tags.php           # 标签管理
│   │   └── tag_members.php    # 标签成员
│   ├── messages/
│   │   ├── send.php           # 发送消息
│   │   ├── log.php            # 发送记录
│   │   ├── templates.php      # 消息模板
│   │   └── webhook.php        # 群机器人
│   ├── external/
│   │   ├── contacts.php       # 客户列表
│   │   ├── contact_detail.php # 客户详情
│   │   ├── groups.php         # 客户群列表
│   │   ├── transfer.php       # 离职继承
│   │   └── moment.php         # 朋友圈管理
│   ├── approval/
│   │   ├── templates.php      # 审批模板
│   │   ├── list.php           # 审批列表
│   │   └── detail.php         # 审批详情
│   ├── checkin/
│   │   └── index.php          # 打卡管理（查询/统计/导出）
│   ├── meeting/
│   │   └── index.php          # 会议管理（创建/查询/导出）
│   ├── calendar/
│   │   └── index.php          # 日程管理（日历+日程 双Tab）
│   ├── smartsheet/
│   │   └── index.php          # 智能表格（7Tab 完整管理）
│   ├── wedrive/
│   │   └── index.php          # 微盘管理（空间/文件/权限/分享）
│   ├── wedoc/
│   │   └── index.php          # 文档管理（查询/创建/权限/分享）
│   ├── media/
│   │   ├── list.php           # 素材列表
│   │   └── upload.php         # 上传素材
│   ├── callback/
│   │   └── log.php            # 回调日志
│   └── system/
│       ├── config.php         # 系统配置
│       ├── users.php          # 管理员管理
│       └── sync.php           # 数据同步
│
├── login.php                   # 登录页面
└── logout.php                  # 退出登录
```

---

## 5.3 路由设计

管理后台使用单一入口 `public/index.php`，通过 URL 路径路由：

```
URI Pattern                              → 实际处理文件
────────────────────────────────────────────────────────
GET  /                                   → admin/modules/dashboard/index.php
GET  /login                              → admin/login.php
POST /login                              → admin/login.php (处理登录)
GET  /logout                             → admin/logout.php

# 通讯录
GET  /contacts/departments               → admin/modules/contacts/departments.php
GET  /contacts/departments/create        → admin/modules/contacts/department_edit.php
POST /contacts/departments/create        → admin/modules/contacts/department_edit.php
GET  /contacts/departments/{id}/edit     → admin/modules/contacts/department_edit.php
POST /contacts/departments/{id}/edit     → admin/modules/contacts/department_edit.php
POST /contacts/departments/{id}/delete   → admin/modules/contacts/department_edit.php

GET  /contacts/members                   → admin/modules/contacts/members.php
GET  /contacts/members/{userid}          → admin/modules/contacts/member_edit.php
GET  /contacts/members/create            → admin/modules/contacts/member_edit.php
POST /contacts/members/create            → admin/modules/contacts/member_edit.php

# 消息
GET  /messages/send                      → admin/modules/messages/send.php
POST /messages/send                      → admin/modules/messages/send.php
GET  /messages/log                       → admin/modules/messages/log.php
GET  /messages/templates                 → admin/modules/messages/templates.php
POST /messages/templates                 → admin/modules/messages/templates.php

# 客户
GET  /external/contacts                  → admin/modules/external/contacts.php
GET  /external/contacts/{external_userid}→ admin/modules/external/contact_detail.php
GET  /external/groups                    → admin/modules/external/groups.php
GET  /external/transfer                  → admin/modules/external/transfer.php

# 审批
GET  /approval/list                      → admin/modules/approval/list.php
GET  /approval/{sp_no}                   → admin/modules/approval/detail.php

# 素材
GET  /media                              → admin/modules/media/list.php
GET  /media/upload                       → admin/modules/media/upload.php
POST /media/upload                       → admin/modules/media/upload.php

# 回调日志
GET  /callback/log                       → admin/modules/callback/log.php

# 系统设置
GET  /system/config                      → admin/modules/system/config.php
POST /system/config                      → admin/modules/system/config.php
GET  /system/sync                        → admin/modules/system/sync.php
POST /system/sync                        → admin/modules/system/sync.php
```

`.htaccess` (Apache) 或 Nginx rewrite 把所有请求指向 `index.php`：

```apache
# public/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

```nginx
# nginx.conf
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## 5.4 页面详细设计

### 5.4.1 登录页面

```
┌──────────────────────────────────────────┐
│                                          │
│           [企业微信 Logo]                │
│          企业微信管理系统                 │
│                                          │
│     ┌─────────────────────────┐          │
│     │  用户名: [___________]  │          │
│     │  密码:   [___________]  │          │
│     │  [验证码] [_______]     │          │
│     │                         │          │
│     │  [     登  录     ]     │          │
│     └─────────────────────────┘          │
│                                          │
└──────────────────────────────────────────┘
```

- 验证码：简单数学题（防止暴力破解）
- 密码错误 5 次锁定 IP 15 分钟
- 登录成功记录 `last_login_at` 和 `last_login_ip`
- Session 过期时间：2 小时（与 access_token 对齐）

### 5.4.2 仪表盘

```
┌──────────────────────────────────────────────────────────┐
│  仪表盘                                                  │
├────────────┬────────────┬────────────┬───────────────────┤
│  成员总数   │  部门总数   │  客户总数   │  今日消息发送      │
│    128     │     15     │    2,367   │      45           │
│  较上月+3   │            │  较昨日+12  │                   │
├────────────┴────────────┴────────────┴───────────────────┤
│                                                          │
│  [近7天消息发送趋势 - 简单柱状图]                          │
│                                                          │
├──────────────────────────────────────────────────────────┤
│  最近回调事件（表格）                                      │
│  时间 | 事件类型 | 变更类型 | 处理结果                    │
│  ...                                                     │
├──────────────────────────────────────────────────────────┤
│  系统状态                                                │
│  access_token 状态: ✅ 有效 (剩余 1h 42m)                 │
│  Redis 状态:      ✅ 正常                                │
│  MySQL 状态:      ✅ 正常                                │
│  API 最近调用:    2 分钟前 (成功)                         │
└──────────────────────────────────────────────────────────┘
```

### 5.4.3 部门管理

```
┌──────────────────────────────────────────────────────────┐
│  部门管理                        [+ 创建部门] [同步部门]   │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌ 树形结构                      ┌ 部门详情（点击展开）  │
│  │                              │                       │
│  │ ▼ 公司总部 (ID:1)            │ 部门ID: 3              │
│  │   ▼ 技术部 (ID:2)            │ 部门名称: 后端开发组   │
│  │     ▸ 前端组 (ID:3)         │ 父部门: 技术部         │
│  │     ▸ 后端组 (ID:4)          │ 负责人: 张三           │
│  │   ▸ 市场部 (ID:5)            │ 成员数: 12             │
│  │   ▸ 人事部 (ID:6)            │                       │
│  │                              │ [编辑] [删除]         │
│  │                              │ [查看成员]            │
│  └──────────────────────────────┴───────────────────────│
└──────────────────────────────────────────────────────────┘
```

**功能**：
- 左侧树形展示（递归渲染）
- 点击节点 → 右侧显示详情 + 子部门列表
- 创建/编辑部门弹出 Modal 或跳转编辑页
- 「同步部门」按钮 → 从企业微信 API 全量同步部门到数据库

### 5.4.4 成员管理

```
┌──────────────────────────────────────────────────────────┐
│  成员管理              [+ 创建成员] [批量导入] [同步成员]  │
│                                                          │
│  搜索: [____________] 🔍                                  │
│  部门: [全部 ▼]  状态: [全部 ▼]  标签: [全部 ▼]           │
├──────────────────────────────────────────────────────────┤
│  表格:                                                   │
│  ☐ | 头像 | 姓名 | 部门 | 手机号 | 状态 | 标签 | 操作    │
│  ☐ | 🧑   | 张三 | 技术部 | 138**** | ✅ | 技术组 | [编辑]│
│  ☐ | 🧑   | 李四 | 市场部 | 139**** | ✅ | - | [编辑]    │
│  ...                                                     │
│                                                          │
│  分页: ← 1 2 3 ... 25 →  共 490 条                      │
│                                                          │
│  批量操作: [批量删除] [批量打标签] [批量发消息]              │
└──────────────────────────────────────────────────────────┘
```

**功能**：
- 分页列表（默认每页 20 条）
- 按部门/状态/标签筛选（联动企业微信 API）
- 单个成员的创建/编辑/删除
- 批量删除、批量打标签、批量发消息
- 点击姓名 → 弹出成员详情（头像、自定义字段等）
- 「同步成员」按钮 → 从企业微信全量同步

### 5.4.5 消息推送

```
┌──────────────────────────────────────────────────────────┐
│  发送消息                                                 │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  消息类型: [文本 ▼]                                       │
│                                                          │
│  发送范围:                                                │
│  ○ 全部成员  ○ 指定成员  ○ 指定部门  ○ 指定标签            │
│                                                          │
│  ┌ 当选择「指定成员」时：                                  │
│  │ 成员选择器: [搜索成员...]                               │
│  │ 已选: [张三 ×] [李四 ×] [王五 ×]                       │
│  │ 搜索结果: 赵六 (+) 钱七 (+)                            │
│  └────────────────────────────                            │
│                                                          │
│  消息内容:                                                │
│  ┌──────────────────────────────────────┐                │
│  │  [文本输入区 / 图文编辑器]            │                │
│  │                                      │                │
│  └──────────────────────────────────────┘                │
│                                                          │
│  □ 保密消息  □ 开启重复检查                               │
│                                                          │
│  [预览]  [保存为模板]  [发送]                              │
│                                                          │
├──────────────────────────────────────────────────────────┤
│  发送历史（下方）                                          │
│  时间 | 类型 | 发送人 | 接收数 | 状态 | 操作               │
│  ...                                                     │
└──────────────────────────────────────────────────────────┘
```

**消息类型切换**：选择不同 message type 后，内容编辑区动态切换：
- **文本** → textarea
- **图片/语音/视频/文件** → 先上传素材获得 media_id，再发送
- **文本卡片** → title + description + url + btntxt 输入框
- **图文消息** → 多条 article 的编辑（可添加/删除/排序）
- **模板卡片** → 根据模板类型展开不同字段

### 5.4.6 客户列表

```
┌──────────────────────────────────────────────────────────┐
│  客户联系                            [同步客户数据]        │
│                                                          │
│  搜索: [客户名称或ID...] 🔍                                │
│  跟进人: [全部 ▼]  标签: [全部 ▼]  添加时间: [近7天 ▼]     │
├──────────────────────────────────────────────────────────┤
│  客户列表:                                                │
│  头像 | 客户名称 | 跟进人 | 添加时间 | 标签 | 备注 | 操作  │
│  👤  | 刘先生   | 张三   | 2025-01-15 | ... | ... | [详情]│
│  ...                                                     │
│                                                          │
│  统计卡片（可折叠）                                        │
│  总客户: 2,367 | 今日新增: 12 | 本周新增: 89 | 本月新增: 312 │
└──────────────────────────────────────────────────────────┘
```

---

## 5.5 权限控制

### 角色定义

| 角色 | 权限 |
|------|------|
| `superadmin` | 全部功能，包括系统配置、管理员管理 |
| `admin` | 除系统配置、管理员管理外的所有功能 |

### 实现方式

```php
// admin/layout/page.php 或每个页面开头
if (!Session::isLoggedIn()) {
    header('Location: /login');
    exit;
}

// 权限检查中间件
function requireRole(string $role) {
    $user = Session::getUser();
    if ($user['role'] !== 'superadmin' && $user['role'] !== $role) {
        http_response_code(403);
        die('无权访问');
    }
}

// 在 system/* 页面中
requireRole('superadmin');
```

---

## 5.6 CSS/JS 策略

### CSS
- **方案 A（推荐）**：手写简洁 CSS，定义全局变量
- **方案 B**：引入 Pico.css（12KB 极小 CSS 库，无 class 写法）

```css
/* 全局变量（手写方案）*/
:root {
    --primary: #1890ff;
    --success: #52c41a;
    --warning: #faad14;
    --danger: #ff4d4f;
    --bg: #f0f2f5;
    --sidebar-width: 220px;
    --header-height: 56px;
}
```

### JS
- **原生 JS**（不引入 jQuery）
- 表单验证、Modal 弹窗、表格排序/搜索用原生实现
- 成员选择器等复杂组件可考虑轻量实现

```html
<!-- 示例：删除确认 -->
<button onclick="confirmDelete('/contacts/departments/3/delete')">删除</button>

<script>
function confirmDelete(url) {
    if (confirm('确定删除？此操作不可恢复。')) {
        fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } })
            .then(r => r.json())
            .then(data => {
                if (data.errcode === 0) location.reload();
                else alert('删除失败: ' + data.errmsg);
            });
    }
}
</script>
```

---

## 5.7 安全措施

| 措施 | 说明 |
|------|------|
| **CSRF Token** | 所有 POST 操作校验 CSRF Token（存 Session 中） |
| **XSS 过滤** | 所有用户输入输出时 `htmlspecialchars()` |
| **SQL 注入防护** | PDO 参数绑定，绝不平拼 SQL |
| **密码存储** | `password_hash()` + `password_verify()` |
| **登录限流** | IP 级别登录失败计数（Redis），5 次失败锁 15 分钟 |
| **敏感操作日志** | 删除成员、修改配置等记录到日志 |
| **Session 安全** | `session.cookie_httponly=1`, `session.cookie_samesite=Lax` |

---

## 5.8 页面渲染机制

```php
<?php
// admin/layout/page.php
namespace Wxwork\Admin;

class Page
{
    /**
     * 渲染完整页面
     * 
     * @param string $title  页面标题
     * @param string $view   视图文件路径（相对于 admin/modules/）
     * @param array  $data   传递给视图的数据
     */
    public static function render(string $title, string $view, array $data = []): void
    {
        // 提取变量到当前作用域
        extract($data);
        
        // 开启输出缓冲
        ob_start();
        require __DIR__ . '/../modules/' . $view . '.php';
        $content = ob_get_clean();
        
        // 渲染布局
        $user = \Wxwork\Core\Session::getUser();
        $csrfToken = \Wxwork\Core\Session::csrfToken();
        
        require __DIR__ . '/header.php';
        echo $content;
        require __DIR__ . '/footer.php';
    }
}
```

### 使用示例

```php
<?php
// admin/modules/contacts/members.php
use Wxwork\Core\Wxwork;
use Wxwork\Admin\Page;
use Wxwork\Core\Session;

// 权限检查
if (!Session::isLoggedIn()) { /* redirect */ }

$wx = new Wxwork();
$deptId = $_GET['department_id'] ?? 0;
$members = $wx->addressbook->userList($deptId, true);

Page::render('成员管理', 'contacts/members', [
    'members' => $members,
    'deptId'  => $deptId,
]);
```

---

> 下一章节：[06 - 回调系统设计](./06-callback-system.md)
