> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](DESIGN.md) | [English](DESIGN.en.md) | [한국어](DESIGN.ko.md) | [Русский](DESIGN.ru.md) | [Deutsch](DESIGN.de.md) | [Français](DESIGN.fr.md) | [Español](DESIGN.es.md) | [Português](DESIGN.pt.md) | [हिन्दी](DESIGN.hi.md) | [العربية](DESIGN.ar.md) | [বাংলা](DESIGN.bn.md) | [Bahasa Indonesia](DESIGN.id.md) | [日本語](DESIGN.ja.md)

# Open Admin — Document de conception

> Pour les diagrammes Mermaid détaillés, consultez [ARCHITECTURE.md](ARCHITECTURE.fr.md) (rendu automatique dans GitHub/GitLab/VS Code).

## 1. Architecture du système

> **Liste des fonctionnalités** : authentification (login/register/refresh/logout + verrouillage du compte + limitation des sessions) | tableau de bord (cache Redis) | CRUD utilisateurs + groupé + import | rôles et permissions (RBAC) | configuration système | audit des opérations (source de 8 plates-formes) | fichiers (upload + export + masquage) | sécurité (18 couches de défense) | exploitation (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
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
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Architecture backend

### 2.1 Conception en couches

| Couche | Répertoire | Responsabilités |
|---|------|------|
| Routage | `config/route.php` | Mapping URL vers contrôleur, liaison des middlewares, routage versionné |
| Middleware | `app/middleware/` | Interception des attaques (SecurityFilter), limitation de débit (RateLimit), authentification (JWT), autorisation (RBAC), version API  |
| Contrôleurs | 14 : Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (panneau d'administration) + Captcha/Auth (API v1) | Validation des paramètres de requête, appel de la logique métier, formatage des réponses |
| Services métier | `app/service/` | Logique métier réutilisable (réservé) |
| Modèles de données | `app/model/` | Mapping ORM, relations, chiffrement/déchiffrement des champs |
| Outils communs | `app/common/` | Services Hashids, Snowflake, Encryption |

### 2.2 Cycle de vie d'une requête

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

### 2.3 Cycle de vie des ID

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Système de chiffrement des données

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Conception de la base de données

### 3.1 Relations ER

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

### 3.2 Structure des tables principales

| Nom de table | Nombre de champs | Description |
|------|-------|------|
| `logistics_admin_user` | 14 | Utilisateurs d'administration, phone/email/id_card stockés chiffrés, prise en charge de la suppression douce |
| `logistics_admin_role` | 7 | Rôles, slug unique |
| `logistics_admin_permission` | 10 | Arbre des permissions (auto-référence parent_id), type : 1=menu 2=bouton 3=API |
| `logistics_admin_user_role` | 2 | Table de liaison many-to-many utilisateurs-rôles |
| `logistics_admin_role_permission` | 2 | Table de liaison many-to-many rôles-permissions |
| `logistics_system_config` | 8 | Configuration par paires clé-valeur, unicité combinée group+key |
| `logistics_operation_log` | 9 | Journal d'audit des opérations (avec champ source) |

### 3.3 Normes de clé primaire

- Type : `BIGINT UNSIGNED NOT NULL`
- Caractéristique : **non auto-incrémentée**, générée au niveau application par l'algorithme Snowflake
- Avantages : unicité globale, adapté au distribué, incrément tendanciel favorable aux index, n'expose pas le volume d'activité
- Configuration : datacenter_id (0-31) + worker_id (0-31), prise en charge de 1024 nœuds en concurrence

## 4. Conception de l'API

### 4.1 Normes URL

```
接口公开:  /api/v1/captcha/{generate|verify}
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

### 4.2 Stratégie de version API

La version de l'API est contrôlée par l'en-tête de requête, **non visible dans le chemin URL** :

```http
```

| Mécanisme | Description |
|------|------|
| Version par défaut | `v1` si l'en-tête `API-Version` est absent |
| Validation | Le middleware `ApiVersion` valide, une version non prise en charge renvoie 400 |
| Routage | La fonction d'aide `v()` résout dynamiquement la classe de contrôleur selon la version |
| Répertoire | Contrôleurs organisés par version : `app/api/{version}/controller/` |

Exemple d'extension — ajout d'une API v2 :
1. Créer `app/api/v2/controller/AuthController.php`
2. Ajouter `'v2'` à la constante `SUPPORTED` du middleware `ApiVersion`
3. Aucune modification des définitions de routes nécessaire

```bash
# Utiliser v1
curl /api/v1/auth/login

# Utiliser v2
curl /api/v1/auth/login

# Sans en-tête, v1 par défaut
curl /api/v1/auth/login
```

### 4.3 Stratégie de limitation de débit

Basée sur l'algorithme de fenêtre glissante Redis Sorted Set, exécutée en script Lua atomique :

| Interface | Limite |
|------|------|
| Défaut | 60 requêtes/minute/IP/route |
| POST /api/v1/auth/login | 10 requêtes/minute |
| POST /api/v1/auth/register | 5 requêtes/minute |

En cas de dépassement, 429 est renvoyé, avec les en-têtes X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Réponse unifiée

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 0 | Succès | Réponse normale |
| 400 | Erreur de paramètre | Format de requête incorrect |
| 401 | Non authentifié | Jeton absent/expiré/invalide |
| 403 | Accès refusé | Le rôle de l'utilisateur ne contient pas la permission requise |
| 404 | Introuvable | Ressource non trouvée |
| 422 | Échec de validation | Les paramètres du formulaire ne respectent pas les règles / échec de la confirmation du mot de passe |
| 500 | Erreur serveur | Exception inattendue |

### 4.5 Flux d'authentification (avec captcha à clic)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/v1/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
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

### 4.6 Modèle de permissions (RBAC)

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

### 4.7 Double confirmation des opérations sensibles

Les opérations sensibles telles que la suppression d'utilisateurs, de rôles ou de permissions exigent la transmission du mot de passe de l'utilisateur connecté dans le corps de requête pour une vérification d'identité :

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

Le frontend affiche une boîte de dialogue de confirmation avant de déclencher une opération de suppression, collecte le mot de passe de l'utilisateur puis envoie la requête.

## 5. Conception du frontend

### 5.1 Panneau d'administration Web Flutter

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Caractéristiques : barre latérale repliable, double thème Material 3, tableaux de données haute densité, boîtes de dialogue, interactions à survol de souris

### 5.2 Mobile HarmonyOS

Routage des pages :

| Page | Route | Description |
|------|------|------|
| LoginPage | `pages/LoginPage` | Connexion nom d'utilisateur + mot de passe + captcha à clic |
| DashboardPage | `pages/DashboardPage` | Cartes de statistiques + opérations récentes |
| UserListPage | `pages/UserListPage` | Liste des utilisateurs, recherche + rafraîchissement par glissement + chargement en remontant |
| UserDetailPage | `pages/UserDetailPage` | Ajout/modification/consultation/suppression (confirmation AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Espace personnel, déconnexion (confirmation AlertDialog) |

Flux de données : Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Conception de la sécurité

### 6.1 Défense en profondeur

| Couche | Mesure |
|------|------|
| Limitation des méthodes | Liste blanche des méthodes HTTP de SecurityFilter, seuls GET/POST/PUT/DELETE/OPTIONS/HEAD sont autorisés, les méthodes non standard renvoient 405 |
| Interception des attaques | Middleware SecurityFilter, détection et interception des XSS/injections SQL/traversées de chemin/injections de commandes/CSRF |
| Vérification homme-machine | Captcha à clic (Click Captcha), validation obligatoire à la connexion/inscription |
| Verrouillage du compte | 5 échecs de connexion consécutifs ⇒ verrouillage de 15 minutes, 429 pendant la période de verrouillage |
| Limitation des sessions | 3 jetons concurrents maximum par utilisateur, au-delà le jeton le plus ancien est automatiquement mis en liste noire |
| Limitation de débit | Middleware RateLimit, fenêtre glissante Redis, atomique en Lua |
| CSP | L'en-tête Content-Security-Policy limite les sources des ressources, contre XSS et l'injection de données |
| Confirmation des opérations | Les opérations sensibles comme la suppression exigent la double confirmation du mot de passe de l'utilisateur connecté |
| Transmission | HTTPS + jeton Bearer JWT |
| ID d'interface | Chiffrement Hashids, impossible de déduire les ID réels de l'extérieur |
| Corps de requête | Chiffrement des champs sensibles AES-256-CBC |
| Base de données | Clé primaire BIGINT (n'expose pas l'incrément) |
| Base de données | Stockage chiffré des champs sensibles AES-128-ECB |
| Authentification | JWT HS256, expiration 2 h + refresh token |
| Autorisation | RBAC, contrôle des permissions à la granularité method.path |
| Audit | OperationLog enregistre toutes les opérations (avec détection automatique du champ source) |

### 6.2 Gestion des clés

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Protection des données sensibles

| Scénario | Champ | Mesure |
|------|------|------|
| Affichage en liste | phone | Masquage : 138****1234 |
| Affichage en liste | email | Masquage : a***@example.com |
| Consultation des détails | phone/email | Interface de déchiffrement requise |
| Export Excel | phone/email | Export après masquage |
| Export PDF | tous les champs | Masquage + filigrane de copyright inamovible |
| Stockage | phone/email/id_card | Chiffrement en texte chiffré avec encryptable |

## 7. Conception de l'export

### 7.1 Export Excel

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 Export PDF

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Architecture de déploiement

### 8.1 Topologie recommandée

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (recommandé en production)

Le `docker-compose.yml` à la racine du projet orchestre tous les services de la topologie ci-dessus :

| Service | Image/build | Port | Description |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy inverse + fichiers statiques + Gzip |
| `app` | build local `Dockerfile` | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Base de données principale, persistance par volume |
| `redis` | redis:7-alpine | 6379 | Cache / limitation de débit / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Recherche plein texte |

Avant le démarrage, remplacez les clés `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` etc. du `docker-compose.yml` par des chaînes aléatoires.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

L'intégration continue GitHub Actions est définie dans `.github/workflows/ci.yml` :
- Vérification de la syntaxe PHP (`php -l`)
- Tests unitaires PHPUnit
- Analyse statique Flutter (`flutter analyze`)

### 8.4 Sauvegarde de la base de données

`database/backup/backup.sh` — sauvegarde mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours.
`database/backup/restore.sh` — sélection interactive et restauration des sauvegardes.

### 8.5 Supervision

Le point de terminaison `GET /metrics` (`MetricsController`) expose 5 métriques gauge au format texte Prometheus : nombre total de requêtes HTTP, nombre d'utilisateurs actifs, état de la connexion base de données/Redis, utilisation mémoire.

### 8.6 Prérequis

| Composant | Version minimale | Configuration recommandée |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ avec OPcache activé |
| MySQL | 8.0+ | 8.0+ en réplication maître-esclave |
| Elasticsearch | 7.x | 8.x cluster de 3 nœuds |
| Redis | 6.x | 7.x en mode sentinelle |
| Nginx | 1.20+ | Proxy inverse + gzip + SSL |
| Flutter SDK | 3.41+ | Dernière version stable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
