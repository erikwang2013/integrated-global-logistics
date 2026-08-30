# 开放管理后台 — Flutter 客户端

物流聚合平台（Integrated Global Logistics）的开放管理后台 Flutter Web 客户端（PC 风格），配套 PHP webman 后端（[admin/](../../README.md)）。

## 功能清单

- **仪表盘**：实时统计 / 趋势图 / 分布图 / 最近操作（Redis 缓存 5 分钟）；
- **用户管理**：CRUD + 批量删除 / 启禁用、Excel 批量导入；
- **角色权限**：角色 CRUD + 权限树，RBAC method.path 粒度鉴权；
- **系统配置**：键值对 CRUD，分组管理；
- **操作审计**：日志查询 + 来源端检测（8 平台自动识别）；
- **统计报表**：业务数据统计与报表展示；
- **国际化**：中英文切换（Accept-Language 头 / ?lang= 参数）。

## 安装

```bash
cd admin/apps/flutter
flutter pub get
```

后端地址在 `lib/app/config/app_config.dart` 中配置：dev 环境默认 `http://localhost:8791`（webman 默认端口），生产环境按 `Environment.prod` 的 `_baseUrls` 修改。

## 使用

```bash
flutter run -d chrome        # 启动 Flutter Web
# 或 flutter build web 后部署静态产物
```

1. **登录**：访问首页使用管理员账号登录（账号由后端安装向导创建）；
2. **仪表盘**：登录后默认展示统计概览；
3. **各管理页面**：左侧导航进入用户 / 角色 / 配置 / 审计 / 报表等模块；
4. **退出**：右上角登出，清除 JWT 令牌。

## 目录结构

```
lib/
├── main.dart                 # 入口
├── app/
│   ├── config/               # 环境配置（baseUrl 等）
│   ├── i18n/                 # 国际化文案
│   ├── routes/               # 路由
│   └── services/             # API 服务层
├── layouts/                  # 布局组件
├── pages/                    # 页面（仪表盘 / 用户 / 角色 / 配置 / 日志 / 报表）
├── theme/                    # 主题
└── widgets/                  # 通用组件
```

详细 API 说明见 [admin/docs/API.md](../../docs/API.md)。
