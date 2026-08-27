> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# نظام إدارة مفتوح (open-admin)

نظام إدارة خلفي متكامل مبني على webman v2 + Flutter.

## بيان حقوق النشر

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **غير قابل للتعديل أو الإزالة أو العكس.** يجب أن يتضمن رأس جميع الملفات الجديدة بيان حقوق النشر أعلاه كتعليق افتتاحي.

## قائمة الميزات

| المجال | الميزة |
|----|------|
| المصادقة | دخول/تسجيل/تحديث/خروج + كود تحقق + قفل الحساب + حد الجلسات |
| لوحة التحكم | إحصائيات فورية/اتجاهات/توزيعات/سجلات (ذاكرة Redis مؤقتة 5 دقائق) |
| المستخدمون | CRUD + حذف جماعي/تفعيل وتعطيل + استيراد من Excel |
| الأدوار والصلاحيات | CRUD + شجرة الصلاحيات + تحقق RBAC method.path |
| إعدادات النظام | CRUD لأزواج المفاتيح والقيم |
| تدقيق العمليات | الاستعلام عن السجلات + كشف تلقائي لجهة المصدر عبر 8 منصات |
| الملفات | رفع + تصدير Excel/PDF (إخفاء البيانات الحساسة) |
| الأمان | دفاع متعمق من 18 طبقة (XSS/حقن SQL/CSRF/تحديد المعدل/CSP...) |
| التشغيل والصيانة | فحص الصحة/مقاييس Prometheus/توثيق API/security.txt + Docker + CI/CD |

## التقنيات المستخدمة

### الخلفية
- PHP 8.3+, webman v2 (workerman/webman)
- قاعدة البيانات: MySQL 8.0+، بادئة الجداول `logistics_`
- المفتاح الأساسي: BIGINT غير تلقائي الزيادة، يُولَّد عبر `erikwang2013/snowflake-php`
- تشفير وفك تشفير معرفات طبقة API: `erikwang2013/hashids`
- مصادقة JWT: `erikwang2013/jwt-webman`
- تشفير وفك تشفير البيانات الحساسة في API: `erikwang2013/encryption`
- تشفير وفك تشفير الحقول الحساسة في قاعدة البيانات: `erikwang2013/encryptable`
- مزامنة ES والاستعلام: `erikwang2013/webman-scout`
- أعلام الدول: `erikwang2013/season`

### الواجهة الأمامية
- Flutter 3.x، دليل المصدر `apps/flutter/`
- تصميم الويب وفق نمط لوحة إدارة PC (وليس نمط تطبيق الهاتف)
- دعم عميل الهاتف وواجهة المدير
- HarmonyOS ArkTS، دليل المصدر `apps/harmonyos/`

## هيكل المشروع

```
open-admin/
├── app/
│   ├── admin/controller/       # وحدات تحكم لوحة الإدارة (14 وحدة)
│   │   ├── BaseController.php      # وحدة التحكم الأساسية
│   │   ├── DashboardController.php # لوحة التحكم (ذاكرة Redis المؤقتة)
│   │   ├── UserController.php      # CRUD للمستخدمين + عمليات جماعية
│   │   ├── RoleController.php      # CRUD للأدوار
│   │   ├── PermissionController.php# CRUD للصلاحيات
│   │   ├── ConfigController.php    # CRUD لإعدادات النظام
│   │   ├── LogController.php       # الاستعلام عن سجلات العمليات
│   │   ├── ProfileController.php   # الملف الشخصي + تسجيل الخروج
│   │   ├── ExportController.php    # تصدير Excel/PDF
│   │   ├── ImportController.php    # استيراد المستخدمين من Excel
│   │   ├── UploadController.php    # رفع الملفات
│   │   ├── HealthController.php    # فحص الصحة
│   │   ├── DocsController.php      # توثيق OpenAPI
│   │   └── MetricsController.php   # مقاييس مراقبة Prometheus
│   ├── api/v1/controller/      # وحدات تحكم API v1 (تحكم ترويسة الإصدار)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # فئات الأدوات المشتركة
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # التعريفات المشتركة (تتضمن Apidoc Definitions)
│   ├── middleware/             # الوسائط (8 وسائط)
│   │   ├── Cors.php            # مشاركة الموارد عبر النطاقات (عام)
│   │   ├── SecurityFilter.php  # اعتراض الهجمات (عام: XSS/حقن SQL/اجتياز المسار/حقن الأوامر/CSRF)
│   │   ├── RateLimit.php       # تحديد معدل Redis (عام، Lua ذرّي)
│   │   ├── ApiVersion.php      # التحقق من إصدار API
│   │   ├── AdminAuth.php       # مصادقة JWT + قائمة سوداء
│   │   ├── AdminPermission.php # التحقق من صلاحيات RBAC (ذاكرة Redis مؤقتة 60 ثانية)
│   │   └── OperationLog.php    # تسجيل تلقائي لسجلات العمليات (يتضمن كشف جهة المصدر)
│   ├── model/                  # نماذج البيانات
│   ├── queue/                  # مهام الطوابير
│   └── process/                # العمليات (Http, Monitor)
├── apps/
│   ├── flutter/                # لوحة إدارة Flutter Web
│   │   └── lib/app/
│   │       ├── pages/          # 6 صفحات كاملة
│   │       │   ├── dashboard/  # لوحة التحكم
│   │       │   ├── login/      # تسجيل الدخول
│   │       │   ├── user/       # إدارة المستخدمين
│   │       │   ├── role/       # الأدوار والصلاحيات
│   │       │   ├── config/     # إعدادات النظام
│   │       │   ├── log/        # سجلات العمليات
│   │       │   └── profile/    # الملف الشخصي
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # تخطيط متجاوب
│   │       └── theme/          # ثيم Material 3
│   └── harmonyos/              # عميل HarmonyOS
├── config/                     # ملفات الإعدادات
│   ├── route.php               # المسارات + استراتيجية إصدار API
│   └── middleware.php           # تسجيل الوسائط العامة
├── database/
│   ├── install.sql             # سكربت تثبيت شامل (دمج جميع SQL)
│   └── backup/                 # سكربتات النسخ الاحتياطي لقاعدة البيانات
│       ├── backup.sh           # mysqldump+gzip، احتفاظ 30 يومًا
│       └── restore.sh          # استعادة تفاعلية
├── docs/                       # الوثائق
│   ├── ARCHITECTURE.md         # رسوم Mermaid للعمارة
│   ├── DESIGN.md               # وثيقة التصميم
│   ├── SECURITY.md             # تصميم بنية الأمان
│   ├── API.md                  # وثيقة مرجع API
│   ├── nginx-security.conf     # مرجع إعدادات أمان Nginx
│   ├── diagrams/               # رسوم العمارة المفصلة
│   └── superpowers/            # المواصفات والخطط
│       ├── specs/              # مواصفات التصميم
│       └── plans/              # خطط التنفيذ
├── public/                     # نقطة الدخول العامة
├── runtime/                    # ملفات وقت التشغيل
├── tests/                      # الاختبارات
├── vendor/                     # اعتماديات Composer
├── CLAUDE.md                   # هذا الملف
├── README.md                   # الوثيقة الصينية
├── docs/translations/README.en.md                # الوثيقة الإنجليزية
├── docs/translations/README.ko.md ... README.ja.md  # وثائق متعددة اللغات (كورية/روسية/ألمانية/فرنسية/إسبانية/برتغالية/هندية/عربية/بنغالية/إندونيسية/يابانية)
├── .env                        # متغيرات البيئة (لا تخضع لإدارة الإصدارات)
├── .env.example                # قالب متغيرات البيئة
├── .env.docker                 # متغيرات بيئة Docker
├── composer.json               # اعتماديات PHP
├── Dockerfile                  # بناء Docker
├── docker-compose.yml          # ترتيب Docker
└── .github/
    └── workflows/
        └── ci.yml              # خط CI/CD (فحص PHP+PHPUnit+Flutter analyze)
```

## سلسلة تنفيذ الوسائط

```
全局:  Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → {路由中间件}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → ApiVersion → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(方法检查→405) → RateLimit → Controller
```

## تحسينات الأمان

- **تقييد طرق HTTP**: يسمح SecurityFilter فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD، والطرق غير القياسية تُرجع 405
- **ترويسة CSP**: حقن Content-Security-Policy + X-Permitted-Cross-Domain-Policies في جميع الاستجابات
- **قفل الحساب**: 5 محاولات دخول فاشلة متتالية تقفل الحساب لمدة 15 دقيقة
- **حد الجلسات المتزامنة**: 3 رموز نشطة كحد أقصى لنفس المستخدم، وعند التجاوز يُضاف أقدم رمز إلى القائمة السوداء
- **security.txt**: نقطة `/.well-known/security.txt` وفق RFC 9116
- **إعدادات أمان Nginx**: مرجع تحصين أمان الوكيل العكسي في `docs/nginx-security.conf`

## استراتيجية إصدار API

يُتحكم في الإصدار عبر ترويسة الطلب `API-Version` (الافتراضي `v1`)، ولا يظهر في URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

لإضافة إصدار جديد يكفي إنشاء دليل `app/api/{version}/controller/` وتسجيله في وسيط `ApiVersion`.

## استراتيجية تحديد المعدل

نافذة منزلقة في Redis (Lua ذرّي)، الافتراضي 60 مرة/دقيقة/IP/المسار:
- الدخول: 10 مرات/دقيقة
- التسجيل: 5 مرات/دقيقة
- ترويسات الاستجابة: `X-RateLimit-Limit/Remaining/Reset`، مع إضافة `Retry-After` عند تجاوز الحد

## معايير الكود

### PHP
- مراجع الدوال/الفئات العامة لا تُسبق بـ `\`، وتُستورد عبر `use`
- يجب أن تتضمن ملفات الإعدادات تعليقات صينية تشرح معنى كل خيار
- يجب أن يتضمن رأس جميع ملفات `.php` الجديدة بيان حقوق النشر

### قاعدة البيانات
- بادئة الجداول: `logistics_`
- المفتاح الأساسي `id`: نوع BIGINT، غير تلقائي الزيادة، يُولَّد عبر snowflake
- الحقول الحساسة تُشفَّر وتُفك تلقائيًا عبر trait الخاص بـ `erikwang2013/encryptable`
- ملفات الترحيل بصيغة SQL

### Flutter
- تخطيط الويب بنمط لوحة إدارة PC (شريط جانبي + شريط علوي + منطقة محتوى)
- إدارة الحالة عبر GetX، المفردة `ApiService` (Dio + معترض JWT)
- استمرار الرمز عبر `shared_preferences`
- نقاط التوقف المتجاوبة: الهاتف (< 768px) والحاسوب المكتبي (>= 768px)

### HarmonyOS
- استخدام عميل HTTP الأصلي `@ohos.net.http`
- تحديث الرمز دون إشعار: استدعاء تلقائي لـ `/api/auth/refresh` عند 401
- إعادة توجيه تلقائية إلى صفحة الدخول عند فشل التحديث

## النشر

### Docker Compose (موصى به لبيئة الإنتاج)

يرتب `docker-compose.yml` في جذر المشروع 5 خدمات:

| الخدمة | الوصف |
|------|------|
| `nginx` | وكيل عكسي Nginx (80/443)، خدمة الملفات الثابتة |
| `app` | تطبيق webman PHP 8.3، بناء عبر `Dockerfile` (يتضمن OPcache) |
| `mysql` | MySQL 8.0، استمرارية عبر وحدات التخزين |
| `redis` | Redis 7 Alpine، التخزين المؤقت/تحديد المعدل/Session |
| `elasticsearch` | Elasticsearch 8.x، البحث النصي الكامل |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

يعرّف `.github/workflows/ci.yml` خط أنابيب GitHub Actions:

- فحص بناء جملة PHP (`php -l`)
- اختبارات PHPUnit
- تحليل Flutter الثابت (`flutter analyze`)

### النسخ الاحتياطي لقاعدة البيانات

`database/backup/backup.sh` — mysqldump + gzip، تنظيف تلقائي للنسخ الأقدم من 30 يومًا.
`database/backup/restore.sh` — استعادة تفاعلية، تعرض النسخ الاحتياطية المتاحة للاختيار.

### المراقبة

نقطة `GET /metrics` (عبر `MetricsController`) تخرج بصيغة نص Prometheus، وتتضمن 5 مقاييس gauge:
- `openadmin_http_requests_total` — إجمالي عدد الطلبات
- `openadmin_active_users` — عدد المستخدمين النشطين
- `openadmin_db_connection_status` — حالة اتصال قاعدة البيانات (0/1)
- `openadmin_redis_connection_status` — حالة اتصال Redis (0/1)
- `openadmin_memory_usage_bytes` — استهلاك الذاكرة
