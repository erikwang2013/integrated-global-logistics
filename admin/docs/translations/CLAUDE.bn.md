> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# ওপেন-অ্যাডমিন (open-admin)

webman v2 + Flutter ভিত্তিক ফুল-স্ট্যাক অ্যাডমিন ব্যাকএন্ড সিস্টেম।

## কপিরাইট ঘোষণা

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **পরিবর্তনযোগ্য নয়, অপসারণযোগ্য নয়, অপরিবর্তনীয়।** সমস্ত নতুন তৈরি ফাইলের হেডার কমেন্টে অবশ্যই উপরের কপিরাইট ঘোষণাটি অন্তর্ভুক্ত করতে হবে।

## ফিচার তালিকা

| ডোমেইন | ফিচার |
|----|------|
| অথেনটিকেশন | লগইন/রেজিস্ট্রেশন/রিফ্রেশ/লগআউট + ক্যাপচা + অ্যাকাউন্ট লক + সেশন সীমাবদ্ধতা |
| ড্যাশবোর্ড | রিয়েল-টাইম পরিসংখ্যান/ট্রেন্ড/ডিস্ট্রিবিউশন/লগ (Redis 5m ক্যাশ) |
| ইউজার | CRUD + ব্যাচ ডিলিট/সক্রিয়-নিষ্ক্রিয় + Excel ইমপোর্ট |
| রোল পারমিশন | CRUD + পারমিশন ট্রি + RBAC method.path অথরাইজেশন |
| সিস্টেম কনফিগারেশন | কী-ভ্যালু CRUD |
| অপারেশন অডিট | লগ কোয়েরি + 8 প্ল্যাটফর্ম সোর্স অটো ডিটেকশন |
| ফাইল | আপলোড + Excel/PDF এক্সপোর্ট (সংবেদনশীল ডেটা মাস্কিং) |
| সিকিউরিটি | 18 লেয়ার ডিফেন্স-ইন-ডেপথ (XSS/SQL ইনজেকশন/CSRF/রেট লিমিট/CSP...) |
| অপারেশন | হেলথ চেক/Prometheus মেট্রিক্স/API ডকুমেন্টেশন/security.txt + Docker + CI/CD |

## টেকনোলজি স্ট্যাক

### ব্যাকএন্ড
- PHP 8.3+, webman v2 (workerman/webman)
- ডেটাবেস: MySQL 8.0+，টেবিল প্রিফিক্স `logistics_`
- প্রাইমারি কী: BIGINT নন-অটো-ইনক্রিমেন্ট，`erikwang2013/snowflake-php` দ্বারা উৎপন্ন
- API লেয়ার ID এনক্রিপশন/ডিক্রিপশন: `erikwang2013/hashids`
- JWT অথেনটিকেশন: `erikwang2013/jwt-webman`
- API সংবেদনশীল ডেটা এনক্রিপশন/ডিক্রিপশন: `erikwang2013/encryption`
- ডেটাবেস সংবেদনশীল ফিল্ড এনক্রিপশন/ডিক্রিপশন: `erikwang2013/encryptable`
- ES সিঙ্ক ও কোয়েরি: `erikwang2013/webman-scout`
- দেশের পতাকা: `erikwang2013/season`

### ফ্রন্টএন্ড
- Flutter 3.x，সোর্স কোড ডিরেক্টরি `apps/flutter/`
- ওয়েব এন্ড PC অ্যাডমিন ব্যাকএন্ড স্টাইলে ডিজাইন করা（মোবাইল অ্যাপ স্টাইল নয়）
- ক্লায়েন্ট ও অ্যাডমিন এন্ড উভয়ই সাপোর্ট করে
- HarmonyOS ArkTS，সোর্স কোড ডিরেক্টরি `apps/harmonyos/`

## প্রজেক্ট স্ট্রাকচার

```
open-admin/
├── app/
│   ├── admin/controller/       # অ্যাডমিন এন্ড কন্ট্রোলার (14 个)
│   │   ├── BaseController.php      # বেস কন্ট্রোলার
│   │   ├── DashboardController.php # ড্যাশবোর্ড（Redis ক্যাশ）
│   │   ├── UserController.php      # ইউজার CRUD + ব্যাচ অপারেশন
│   │   ├── RoleController.php      # রোল CRUD
│   │   ├── PermissionController.php# পারমিশন CRUD
│   │   ├── ConfigController.php    # সিস্টেম কনফিগারেশন CRUD
│   │   ├── LogController.php       # অপারেশন লগ কোয়েরি
│   │   ├── ProfileController.php   # ব্যক্তিগত সেন্টার + লগআউট
│   │   ├── ExportController.php    # Excel/PDF এক্সপোর্ট
│   │   ├── ImportController.php    # Excel ইমপোর্ট ইউজার
│   │   ├── UploadController.php    # ফাইল আপলোড
│   │   ├── HealthController.php    # হেলথ চেক
│   │   ├── DocsController.php      # OpenAPI ডকুমেন্টেশন
│   │   └── MetricsController.php   # Prometheus মনিটরিং মেট্রিক্স
│   ├── api/v1/controller/      # API v1 কন্ট্রোলার（ভার্সন হেডার নিয়ন্ত্রণ）
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # সাধারণ ইউটিলিটি ক্লাস
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # সাধারণ ডেফিনিশন（Apidoc Definitions সহ）
│   ├── middleware/             # মিডলওয়্যার（8 个）
│   │   ├── Cors.php            # ক্রস-অরিজিন（গ্লোবাল）
│   │   └── (erikwang2013/security-php প্যাকেজে স্থানান্তরিত)  # 31 种攻击检测
│   │   ├── RateLimit.php       # Redis রেট লিমিট（গ্লোবাল，Lua অ্যাটমিক）
│   │   ├── AdminAuth.php       # JWT অথেনটিকেশন + ব্ল্যাকলিস্ট
│   │   ├── AdminPermission.php # RBAC পারমিশন ভ্যালিডেশন（Redis 60s ক্যাশ）
│   │   └── OperationLog.php    # অপারেশন লগ অটো রেকর্ডিং（সোর্স ডিটেকশন সহ）
│   ├── model/                  # ডেটা মডেল
│   ├── queue/                  # কিউ টাস্ক
│   └── process/                # প্রসেস (Http, Monitor)
├── apps/
│   ├── flutter/                # Flutter Web অ্যাডমিন ব্যাকএন্ড
│   │   └── lib/app/
│   │       ├── pages/          # 6 个 সম্পূর্ণ পৃষ্ঠা
│   │       │   ├── dashboard/  # ড্যাশবোর্ড
│   │       │   ├── login/      # লগইন
│   │       │   ├── user/       # ইউজার ম্যানেজমেন্ট
│   │       │   ├── role/       # রোল পারমিশন
│   │       │   ├── config/     # সিস্টেম কনফিগারেশন
│   │       │   ├── log/        # অপারেশন লগ
│   │       │   └── profile/    # ব্যক্তিগত সেন্টার
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # রেসপন্সিভ লেআউট
│   │       └── theme/          # Material 3 থিম
│   └── harmonyos/              # HarmonyOS ক্লায়েন্ট
├── config/                     # কনফিগারেশন ফাইল
│   ├── route.php               # রাউট + API ভার্সন পলিসি
│   └── middleware.php           # গ্লোবাল মিডলওয়্যার রেজিস্ট্রেশন
├── database/
│   ├── install.sql             # ফুল ইনস্টলেশন স্ক্রিপ্ট（সব SQL মার্জড）
│   └── backup/                 # ডেটাবেস ব্যাকআপ স্ক্রিপ্ট
│       ├── backup.sh           # mysqldump+gzip，30 天保留
│       └── restore.sh          # ইন্টারঅ্যাক্টিভ রিস্টোর
├── docs/                       # ডকুমেন্টেশন
│   ├── ARCHITECTURE.md         # Mermaid আর্কিটেকচার ডায়াগ্রাম
│   ├── DESIGN.md               # ডিজাইন ডকুমেন্টেশন
│   ├── SECURITY.md             # সিকিউরিটি আর্কিটেকচার ডিজাইন
│   ├── API.md                  # API রেফারেন্স ডকুমেন্টেশন
│   ├── nginx-security.conf     # Nginx সিকিউরিটি রেফারেন্স কনফিগ
│   ├── diagrams/               # বিভক্ত আর্কিটেকচার ডায়াগ্রাম
│   └── superpowers/            # স্পেসিফিকেশন ও প্ল্যান
│       ├── specs/              # ডিজাইন স্পেসিফিকেশন
│       └── plans/              # ইমপ্লিমেন্টেশন প্ল্যান
├── public/                     # পাবলিক এন্ট্রি
├── runtime/                    # রানটাইম ফাইল
├── tests/                      # টেস্ট
├── vendor/                     # Composer ডিপেন্ডেন্সি
├── CLAUDE.md                   # এই ফাইল
├── README.md                   # চাইনিজ ডকুমেন্টেশন
├── README.en.md                # ইংরেজি ডকুমেন্টেশন
├── README.ko.md ... README.ja.md  # মাল্টি-ল্যাঙ্গুয়েজ ডকুমেন্টেশন（কোরিয়ান/রুশ/জার্মান/ফরাসি/স্প্যানিশ/পর্তুগিজ/হিন্দি/আরবি/বাংলা/ইন্দোনেশিয়ান/জাপানি）
├── .env                        # এনভায়রনমেন্ট ভেরিয়েবল（ভার্সন কন্ট্রোলে অন্তর্ভুক্ত নয়）
├── .env.example                # এনভায়রনমেন্ট ভেরিয়েবল টেমপ্লেট
├── .env.docker                 # Docker এনভায়রনমেন্ট ভেরিয়েবল
├── composer.json               # PHP ডিপেন্ডেন্সি
├── Dockerfile                  # Docker বিল্ড
├── docker-compose.yml          # Docker অর্কেস্ট্রেশন
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD পাইপলাইন（PHP语法+PHPUnit+Flutter analyze）
```

## মিডলওয়্যার এক্সিকিউশন চেইন

```
全局:  Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → {路由中间件}
/admin: Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → Controller
/health: Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → Controller
```

> **দ্রষ্টব্য**: যে অ্যাডমিন এন্ড API গুলোতে পারমিশন ভ্যালিডেশনের প্রয়োজন নেই（যেমন ব্যক্তিগত সেন্টার ভিউ）সেগুলো `/admin` গ্রুপের বাইরে আলাদাভাবে রেজিস্টার করুন，শুধু `AdminAuth` মিডলওয়্যার যোগ করুন। গ্রুপের ভেতরের রাউটগুলো `AdminPermission` দ্বারা `method.path` ফরম্যাটের পারমিশন আইডেন্টিফায়ার ভ্যালিডেট করা হয়।
> 
> **Redis প্রিফিক্স**: সব key তে স্বয়ংক্রিয়ভাবে `open-admin:` প্রিফিক্স যোগ হয়，`.env` এর `REDIS_PREFIX` দিয়ে কাস্টমাইজ করা যায়।

## সিকিউরিটি এনহ্যান্সমেন্ট

- **অ্যাটাক ডিটেকশন**：erikwang2013/security-php প্যাকেজ（31 种检测器：XSS/SQL ইনজেকশন/কমান্ড ইনজেকশন/পাথ ট্রাভার্সাল/SSRF/XXE/JNDI/ডিসিরিয়ালাইজেশন/JWT অ্যাটাক/CSRF/সংবেদনশীল ডেটা লিক ইত্যাদি + HTTP মেথড ভ্যালিডেশন/রিকোয়েস্ট বডি সাইজ লিমিট/Content-Type ভ্যালিডেশন + IP অ্যাটাক এস্কেলেশন ব্ল্যাকলিস্ট）
- **CSP হেডার**：Content-Security-Policy + X-Permitted-Cross-Domain-Policies সব রেসপন্সে ইনজেক্ট করা হয়
- **অ্যাকাউন্ট লক**：টানা 5 বার লগইন ব্যর্থ হলে，অ্যাকাউন্ট 15 মিনিটের জন্য লক হয়
- **কনকারেন্ট সেশন লিমিট**：একই ইউজারের সর্বোচ্চ 3 个 সক্রিয় Token，অতিরিক্ত হলে সবচেয়ে পুরনো Token ব্ল্যাকলিস্টে যুক্ত হয়
- **security.txt**：`/.well-known/security.txt` RFC 9116 এন্ডপয়েন্ট
- **Nginx সিকিউরিটি কনফিগ**：`docs/nginx-security.conf` রিভার্স প্রক্সি সিকিউরিটি হার্ডেনিং রেফারেন্স

## API ভার্সন পলিসি

ভার্সন রিকোয়েস্ট হেডার `API-Version` দিয়ে নিয়ন্ত্রিত হয়（ডিফল্ট `v1`），URL এ প্রকাশ করা হয় না：

```bash
curl http://localhost:8787/api/v1/auth/login
```

নতুন ভার্সন যোগ করতে শুধু `app/api/{version}/controller/` ডিরেক্টরি তৈরি করে `ApiVersion` মিডলওয়্যারে রেজিস্টার করুন।

## রেট লিমিট পলিসি

Redis স্লাইডিং উইন্ডো（Lua অ্যাটমিক），ডিফল্ট 60 次/分钟/IP/রাউট：
- লগইন: 10 次/分钟
- রেজিস্ট্রেশন: 5 次/分钟
- রেসপন্স হেডার: `X-RateLimit-Limit/Remaining/Reset`，সীমা অতিক্রম করলে `Retry-After` যুক্ত হয়

## কোড কনভেনশন

### PHP
- গ্লোবাল ফাংশন/ক্লাস রেফারেন্সে আগের `\` যোগ করা হয় না，`use` ইমপোর্ট ব্যবহার করুন
- কনফিগারেশন ফাইলে প্রতিটি কনফিগ আইটেমের অর্থ বোঝানোর জন্য চাইনিজ কমেন্ট থাকতে হবে
- সব নতুন তৈরি `.php` ফাইলের হেডারে কপিরাইট ঘোষণা থাকতে হবে
- **Redis `support\Redis` ইউটিলিটি ক্লাস দিয়ে অ্যাক্সেস করুন**（সিঙ্গেলটন কানেকশন পুল，স্বয়ংক্রিয়ভাবে `REDIS_HOST/PORT/PASSWORD/DB` এনভায়রনমেন্ট ভেরিয়েবল পড়ে），সব key তে স্বয়ংক্রিয়ভাবে প্রিফিক্স যোগ হয়（ডিফল্ট `open-admin:`，`REDIS_PREFIX` এনভায়রনমেন্ট ভেরিয়েবল দিয়ে কনফিগারযোগ্য）
- **রাউট পারমিশন**: `/admin` গ্রুপের ভেতরের রাউটগুলোতে `method.path` ফরম্যাটের পারমিশন প্রয়োজন（যেমন `get.admin/dashboard`），পারমিশন ভ্যালিডেশন ছাড়া রাউটগুলো গ্রুপের বাইরে রেখে শুধু `AdminAuth` মিডলওয়্যার যোগ করুন
- **CORS**: নতুন রিকোয়েস্ট হেডার যোগ করলে `Cors.php` মিডলওয়্যার এবং `route.php` fallback এর `Access-Control-Allow-Headers` একসাথে আপডেট করতে হবে
- **সুপার অ্যাডমিন প্রোটেকশন**: `RoleController` এর `update`/`destroy` মেথড `slug == 'super_admin'` রোল পরিচালনা করতে পারবে না
- webman PHP Warning কে এক্সেপশনে রূপান্তর করে，অসংজ্ঞায়িত প্রপার্টি/ভেরিয়েবল 500 এরর সৃষ্টি করে

### ডেটাবেস
- টেবিল প্রিফিক্স: `logistics_`
- প্রাইমারি কী `id`: BIGINT টাইপ，নন-অটো-ইনক্রিমেন্ট，snowflake দ্বারা উৎপন্ন
- সংবেদনশীল ফিল্ড `erikwang2013/encryptable` trait দিয়ে স্বয়ংক্রিয় এনক্রিপশন/ডিক্রিপশন
- মাইগ্রেশন ফাইল SQL ফরম্যাট ব্যবহার করে

### Flutter
- ওয়েব এন্ড লেআউট PC অ্যাডমিন ব্যাকএন্ড স্টাইল（সাইডবার + টপবার + কনটেন্ট এরিয়া）
- GetX স্টেট ম্যানেজমেন্ট ব্যবহার করুন，**সব API রিকোয়েস্ট অবশ্যই `ApiService` সিঙ্গেলটন দিয়ে যেতে হবে**（Dio + JWT ইন্টারসেপ্টর），আলাদা Dio ইনস্ট্যান্স তৈরি বা baseUrl হার্ডকোড করা নিষিদ্ধ
- Token পারসিস্টেন্স `shared_preferences` ব্যবহার করে
- রেসপন্সিভ ব্রেকপয়েন্ট: মোবাইল (< 768px) ও ডেস্কটপ (>= 768px)
- **পৃষ্ঠার হেডার Row অবশ্যই `Wrap` ব্যবহার করবে**，সাইডবার খুললে ওভারফ্লো রোধ করতে；ফিল্টার ChoiceChip অবশ্যই `Obx` এর ভেতরে থাকতে হবে যাতে রেসপন্সিভ আপডেট হয়
- **DataTable অবশ্যই `SingleChildScrollView(scrollDirection: Axis.horizontal)` এ মোড়ানো থাকতে হবে** কলাম ওভারফ্লো রোধ করতে
- স্বতন্ত্র পৃষ্ঠা（যেমন ProfilePage）অবশ্যই `Scaffold` অন্তর্ভুক্ত করবে，নইলে `TextField` ইত্যাদি Material কম্পোনেন্ট "No Material widget found" এরর দেবে
- সাইডবার খোলা/বন্ধ করার সময় `_showCollapsedContent` দিয়ে কনটেন্ট বিলম্বিত করে স্যুইচ করুন，অ্যানিমেশন চলাকালীন RenderFlex ওভারফ্লো এড়াতে

### HarmonyOS
- `@ohos.net.http` নেটিভ HTTP ক্লায়েন্ট ব্যবহার করুন
- Token নির্বিঘ্ন রিফ্রেশ：401 হলে স্বয়ংক্রিয়ভাবে `/api/v1/auth/refresh` কল হয়
- রিফ্রেশ ব্যর্থ হলে স্বয়ংক্রিয়ভাবে লগইন পৃষ্ঠায় রিডাইরেক্ট হয়

## ডিপ্লয়মেন্ট

### Docker Compose（প্রোডাকশন পরিবেশে প্রস্তাবিত）

প্রজেক্ট রুট ডিরেক্টরির `docker-compose.yml` 5 个 সার্ভিস অর্কেস্ট্রেট করে：

| সার্ভিস | বর্ণনা |
|------|------|
| `nginx` | Nginx রিভার্স প্রক্সি（80/443），স্ট্যাটিক ফাইল সার্ভিস |
| `app` | webman PHP 8.3 অ্যাপ，`Dockerfile` দিয়ে বিল্ড（OPcache সহ） |
| `mysql` | MySQL 8.0，ডেটা ভলিউম পারসিস্টেন্স |
| `redis` | Redis 7 Alpine，ক্যাশ/রেট লিমিট/Session |
| `elasticsearch` | Elasticsearch 8.x，ফুল-টেক্সট সার্চ |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions পাইপলাইন সংজ্ঞায়িত করে：

- PHP সিনট্যাক্স চেক (`php -l`)
- PHPUnit ইউনিট টেস্ট
- Flutter স্ট্যাটিক অ্যানালাইসিস (`flutter analyze`)

### ডেটাবেস ব্যাকআপ

`database/backup/backup.sh` — mysqldump + gzip，30 দিনের পুরনো ব্যাকআপ স্বয়ংক্রিয় পরিষ্কার।
`database/backup/restore.sh` — ইন্টারঅ্যাক্টিভ রিস্টোর，ব্যবহারের জন্য উপলব্ধ ব্যাকআপ তালিকাভুক্ত করে।

### মনিটরিং

`GET /metrics` এন্ডপয়েন্ট（`MetricsController`）Prometheus text format আউটপুট করে，5 个 gauge মেট্রিক সহ：
- `openadmin_http_requests_total` — মোট রিকোয়েস্ট সংখ্যা
- `openadmin_active_users` — সক্রিয় ইউজার সংখ্যা
- `openadmin_db_connection_status` — ডেটাবেস কানেকশন স্ট্যাটাস (0/1)
- `openadmin_redis_connection_status` — Redis কানেকশন স্ট্যাটাস (0/1)
- `openadmin_memory_usage_bytes` — মেমোরি ব্যবহারের পরিমাণ
