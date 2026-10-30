# 开放管理后台 — 设计文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 详细的 Mermaid 架构图请参阅 [ARCHITECTURE.md](ARCHITECTURE.md)（GitHub/GitLab/VS Code 可自动渲染）。

## 1. 系统架构

> **功能清单**：认证(login/register/refresh/logout + 账号锁定 + 会话限制) | 仪表盘(Redis缓存) | 用户CRUD+批量+导入 | 角色权限(RBAC) | 系统配置 | 操作审计(8平台来源端) | 文件(上传+导出+脱敏) | 安全(18层防御) | 运维(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Layui Admin (Browser)│  │  API Clients (HTTP/JSON)     │  │
│  │  管理后台 (浏览器)     │  │  第三方客户端 / API           │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │  文件存储    │  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. 后端架构

### 2.1 分层设计

| 层 | 目录 | 职责 |
|---|------|------|
| 路由 | `config/route.php` | URL 到控制器的映射，中间件绑定，版本化路由 |
| 中间件 | `app/middleware/` | 攻击拦截(SecurityFilter)、限流(RateLimit)、认证(JWT)、授权(RBAC) |
| 控制器 | 14 个：Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (管理端) + Captcha/Auth (API v1) | 请求参数校验、调用业务逻辑、响应格式化 |
| 业务服务 | `app/service/` | 可复用的业务逻辑（预留） |
| 数据模型 | `app/model/` | ORM 映射、关联关系、字段加解密 |
| 公共工具 | `app/common/` | Hashids、Snowflake、Encryption 服务 |

### 2.2 请求生命周期

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  Locale ──────────────► Accept-Language / ?lang= 语言检测
  │
  ▼
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID 生命周期

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 数据加密体系

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-256-CBC（随机 IV + HMAC，`ENCRYPTABLE_CIPHER` 默认 aes-256-cbc），独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. 数据库设计

### 3.1 ER 关系

```
logistics_admin_user ──┬── logistics_admin_user_role ──┬── logistics_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    logistics_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    logistics_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           logistics_operation_log
             (操作日志)

logistics_system_config (系统配置) — 独立表
```

### 3.2 核心表结构

| 表名 | 字段数 | 说明 |
|------|-------|------|
| `logistics_admin_user` | 14 | 管理用户，phone/email/id_card 加密存储，支持软删除 |
| `logistics_admin_role` | 7 | 角色，slug 唯一 |
| `logistics_admin_permission` | 10 | 权限树（parent_id 自引用），type: 1=菜单 2=按钮 3=API |
| `logistics_admin_user_role` | 2 | 用户-角色多对多中间表 |
| `logistics_admin_role_permission` | 2 | 角色-权限多对多中间表 |
| `logistics_system_config` | 8 | 键值对配置，group+key 联合唯一 |
| `logistics_operation_log` | 9 | 操作审计日志（含 source 来源端） |

### 3.3 主键规范

- 类型: `BIGINT UNSIGNED NOT NULL`
- 特性: **非自增**，由 Snowflake 算法在应用层生成
- 优势: 全局唯一、分布式友好、趋势递增利于索引、不暴露业务量
- 配置: datacenter_id(0-31) + worker_id(0-31)，默认由进程 PID 派生（`SNOWFLAKE_DATACENTER_ID` / `SNOWFLAKE_WORKER_ID` 可覆盖）；跨主机部署需显式分配，避免 ID 冲突

## 4. API 设计

### 4.1 URL 规范

```
公开接口:  /api/v1/captcha/{generate|verify}
           /api/v1/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API 版本策略

API 版本号直接体现在 URL 路径中（`/api/v1/*`），由路由分组解析到 `app/api/{version}/controller/` 下对应控制器：

| 机制 | 说明 |
|------|------|
| URL 形态 | `POST /api/v1/auth/login` — 版本号是路径首段，无需请求头 |
| 解析 | `config/route.php` 中 `Route::group('/api/v1', ...)` 直接绑定版本目录，`v()` 辅助函数按版本解析控制器类 |
| 目录 | 控制器按版本组织: `app/api/{version}/controller/` |
| 未知版本 | 路径不匹配任何路由分组 → 404 |

扩展示例——新增 v2 API：
1. 创建 `app/api/v2/controller/...` 控制器
2. `config/route.php` 追加 `Route::group('/api/v2', ...)`（复用 `v('v2', ...)` 写法）
3. v1 分组与存量请求不受影响

```bash
curl /api/v1/auth/login   # v1（当前）
curl /api/v2/auth/login   # v2（新增分组后）
```

### 4.3 限流策略

基于 Redis Sorted Set 滑动窗口算法，原子化 Lua 脚本执行：

| 接口 | 限制 |
|------|------|
| 默认 | 60 次/分钟/IP/路由 |
| POST /api/v1/auth/login | 10 次/分钟 |
| POST /api/v1/auth/register | 5 次/分钟 |

超限返回 429，响应头包含 X-RateLimit-Limit / Remaining / Reset / Retry-After。

### 4.4 统一响应

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 含义 | 触发场景 |
|------|------|---------|
| 0 | 成功 | 正常响应 |
| 400 | 参数错误 | 请求格式不正确 |
| 401 | 未认证 | Token 缺失/过期/无效 |
| 403 | 无权限 | 用户角色不包含所需权限 |
| 404 | 不存在 | 资源未找到 |
| 422 | 验证失败 | 表单参数不符合规则 / 密码确认失败 / 重复链上交易 |
| 429 | 限流 / 锁定 | RateLimit 触发 / 登录失败锁定 |
| 500 | 服务端错误 | 未预期异常 |

### 4.5 认证流程（含点击验证码）

```
客户端                               服务端
  │                                    │
  │  ① POST /api/v1/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), texts}    │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/v1/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

令牌语义：access / refresh / client 三类 `token_type` 严格区分，AdminAuth 只接受 access；refresh 单次使用（Redis `SET NX` 原子占用），刷新成功后旧 refresh 立即进黑名单并签发新 refresh；管理员被禁用或删除后，其 access token 在下一次请求即返回 401。

### 4.6 权限模型 (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 敏感操作二次确认

删除用户、角色、权限等敏感操作，需要在请求体中传入当前用户密码进行身份复核：

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

前端在触发删除操作前弹出确认对话框，收集用户密码后发送请求。

## 5. 前端设计

### 5.1 管理端 UI（Layui）

- 管理页面与静态资源位于 `public/`，由 PHP 控制器直接渲染，使用内置 Layui 组件。
- 登录、仪表盘、用户、角色、配置、日志、个人中心均为 Layui 页面；Layui 是唯一管理端 UI。

## 6. 安全设计

### 6.1 纵深防御

| 层面 | 措施 |
|------|------|
| 方法限制 | SecurityFilter HTTP 方法白名单，仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD，非标准方法返回 405 |
| 攻击拦截 | SecurityFilter 中间件，XSS/SQL注入/路径遍历/命令注入/CSRF 检测拦截 |
| 人机验证 | 点击验证码（Click Captcha），登录/注册强制校验 |
| 账号锁定 | 连续 5 次登录失败按用户名 + IP 锁定 15 分钟，锁定期间返回 429 |
| 会话限制 | 同一用户最多 3 个并发 Token，超出时最旧 Token 自动黑名单 |
| 令牌类型 | access / refresh / client 严格区分；refresh 单次使用并轮换；禁用管理员立即失效 |
| 限流 | RateLimit 中间件，Redis 滑动窗口，Lua 原子化（phpredis eval 签名已修复） |
| CSP | Content-Security-Policy 头限制资源来源，防 XSS 与数据注入 |
| 操作确认 | 删除等敏感操作需输入当前用户密码二次确认 |
| 传输 | HTTPS + JWT Bearer Token |
| 接口ID | Hashids 加密，外部不可逆推真实 ID |
| 请求体 | AES-256-CBC 敏感字段加密 |
| 数据库 | BIGINT 主键（不暴露自增量） |
| 数据库 | AES-256-CBC + 随机 IV + HMAC 敏感字段加密存储 |
| 认证 | JWT HS256，2h 过期 + refresh token |
| 授权 | RBAC，method.path 粒度权限控制；AdminPermission 无 adminId 时返回 401 |
| 审计 | OperationLog 记录所有操作（含来源端 source 自动检测） |

### 6.2 密钥管理

```
JWT_SECRET_KEY            → JWT 签名密钥，安装器生成；代码无默认值
ENCRYPTABLE_KEY           → DB 存储加密密钥（32 字符），与传输密钥独立
ENCRYPTION_KEY            → API 传输加密密钥（32 字符）
INTERNAL_TOKEN            → 网关 → PHP worker 共享密钥；为空时内部接口 fail-closed
ENCRYPTION_PREVIOUS_KEYS  → 旧加密密钥列表，仅用于迁移历史数据
HASHIDS_SALT              → `.env` 模板中存在，但 `config/hashids.php` 当前未读取；盐值为空，只做 ID 混淆，不是权限边界
```

已安装环境缺失 `JWT_SECRET_KEY` / `ENCRYPTABLE_KEY` / `ENCRYPTION_KEY`，或仍为旧公开默认值时，`support/bootstrap/Secrets.php` 拒绝启动；`APP_DEBUG=true` 时仅记录告警。`INTERNAL_TOKEN` 为空不阻止 PHP 启动，但内部接口一律拒绝；网关侧空 token 直接拒绝启动。手工生成密钥：JWT / 内部令牌用 `openssl rand -hex 32`，`ENCRYPTABLE_KEY` / `ENCRYPTION_KEY` 需 32 字符，用 `openssl rand -hex 16`。

### 6.3 敏感数据保护

| 场景 | 字段 | 措施 |
|------|------|------|
| 列表展示 | phone | 脱敏: 138****1234 |
| 列表展示 | email | 脱敏: a***@example.com |
| 详情查看 | phone/email | 需解密接口 |
| 导出Excel | phone/email | 脱敏后导出 |
| 导出PDF | 全字段 | 脱敏 + 不可移除版权水印 |
| 存储 | phone/email/id_card | encryptable 加密为密文 |

## 7. 导出设计

### 7.1 Excel 导出

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF 导出

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. 部署架构

### 8.1 推荐拓扑

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + Redis
                    静态文件: public/
internal gRPC: 127.0.0.1:8792（仅本机/容器网络，e-cat → PHP worker）
```

### 8.2 Docker Compose（推荐生产环境）

项目根目录的 `docker-compose.yml` 编排了上述拓扑的全部服务：

| 服务 | 镜像/构建 | 端口 | 说明 |
|------|----------|------|------|
| `nginx` | nginx:alpine | 宿主 80, 443 | 反向代理 + 静态文件 + Gzip，配置文件 `docs/nginx-docker.conf` |
| `app` | 本地 `Dockerfile` 构建 | 仅容器网络 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 仅容器网络 3306 | 主数据库，数据卷持久化 |
| `redis` | redis:7-alpine | 仅容器网络 6379 | 缓存 / 限流 / 验证码 |

MySQL / Redis 不发布宿主端口。密钥不再写入 `docker-compose.yml`：安装器生成后写入 `.env.docker`，`app` 通过 `env_file` 读取。安装完成后需重建 app 使新密钥生效。

```bash
docker compose up -d
# 浏览器完成 /install（数据库主机填 mysql）后：
docker compose up -d --force-recreate app
```

### 8.3 CI/CD

GitHub Actions 持续集成定义在 `.github/workflows/ci.yml`：
- PHP 语法检查 (`php -l`)
- PHPUnit 单元测试

### 8.4 数据库备份

`database/backup/backup.sh` — mysqldump + gzip 备份，自动清理 30 天前旧备份。
`database/backup/restore.sh` — 交互式选择并恢复备份。

### 8.5 监控

`GET /metrics` 端点（`MetricsController`）以 Prometheus text format 暴露 5 个 gauge 指标：HTTP 请求总数、活跃用户数、数据库/Redis 连接状态、内存使用量。

### 8.6 环境要求

| 组件 | 最低版本 | 推荐配置 |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ 主从复制 |
| Redis | 6.x | 7.x 哨兵模式 |
| Nginx | 1.20+ | 反向代理 + gzip + SSL |
