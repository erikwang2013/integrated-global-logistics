> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# المشروع الفرعي A: تحسين الواجهة الخلفية — المواصفات التصميمية

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## النطاق

هذا التحسين مخصص للواجهة الخلفية، بإجمالي 15 نقطة وظيفية، تشمل 9 ملفات جديدة + 4 ملفات معدلة.

---

## قائمة الملفات الجديدة/المعدلة

```
app/middleware/
├── OperationLog.php          # جديد: تسجيل تلقائي لسجل العمليات
├── Cors.php                  # جديد: عبر النطاقات (CORS)
└── RateLimit.php             # جديد: الحد من المعدل عبر Redis
app/admin/controller/
├── ConfigController.php      # جديد: CRUD لإعدادات النظام
├── LogController.php         # جديد: الاستعلام عن سجلات العمليات
├── ProfileController.php     # جديد: الحساب الشخصي (بما في ذلك تسجيل الخروج)
├── UploadController.php      # جديد: رفع الملفات
├── ImportController.php      # جديد: استيراد المستخدمين عبر Excel
└── HealthController.php      # جديد: الفحص الصحي
app/model/
├── AdminUser.php             # تعديل: إضافة SoftDeletes + Searchable trait
└── OperationLog.php          # تعديل: إضافة public $timestamps = false
app/middleware/
└── AdminAuth.php             # تعديل: التحقق من القائمة السوداء لـ JWT
app/admin/controller/
├── DashboardController.php   # تعديل: التحويل إلى إحصائيات لحظية من قاعدة البيانات
└── UserController.php        # تعديل: إضافة إجراءات دفعة جديدة
config/
└── route.php                 # تعديل: إضافة مسارات + وسائط
```

---

## 1. الوسائط (Middleware)

### 1.1 وسيط CORS

**الملف**: `app/middleware/Cors.php`

- طلبات OPTIONS المسبقة (preflight) تُرجع 204 مباشرة
- للطلبات غير المسبوقة، تُضاف `Access-Control-Allow-Origin: *` إلى رؤوس الاستجابة
- الرؤوس المسموحة: `Authorization, Content-Type`
- أقصى مدة تخزين مؤقت: 86400 ثانية

التركيب: وسيط عام (`config/middleware.php`)

### 1.2 وسيط الحد من المعدل

**الملف**: `app/middleware/RateLimit.php`

- التخزين: نافذة منزلقة عبر Sorted Set في Redis
- الافتراضي: 60 مرة/دقيقة/IP/مسار
- الواجهات الحساسة:
  - `/api/v1/auth/login`: 10 مرات/دقيقة
  - `/api/v1/auth/register`: 5 مرات/دقيقة
- عند تجاوز الحد يُرجع `429 Too Many Requests`

التركيب: وسيط عام (`config/middleware.php`)، بعد Cors وقبل ApiVersion

### 1.3 وسيط سجل العمليات

**الملف**: `app/middleware/OperationLog.php`

- يسجل POST/PUT/DELETE فقط
- الحقول المسجلة: user_id, action, method, path, ip, input(JSON)
- الكتابة غير المتزامنة بعد إرجاع الاستجابة (لا تحجب الطلب)

التركيب: مجموعة مسارات `/admin`، بعد AdminPermission

### 1.4 سلسلة تنفيذ الوسائط العامة

```
جميع الطلبات:
  Cors → RateLimit → ApiVersion → {وسائط المسار} → Controller

طلبات /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 تسجيل الخروج (القائمة السوداء لـ JWT)

**الملف**: `app/middleware/AdminAuth.php` (تعديل)

**المبدأ**: JWT بلا حالة بطبيعته؛ عند تسجيل الخروج يُضاف token إلى القائمة السوداء في Redis، ويفحص AdminAuth القائمة السوداء أولًا عند التحقق.

**تعديلات AdminAuth**:
- في بداية `process()`: التحقق من أن token الحالي موجود في القائمة السوداء عبر مجموعة `jwt_blacklist` في Redis
- إذا كان في القائمة السوداء يُرجع 401

**مسار تسجيل الخروج** (ضمن الحساب الشخصي):

| الطريقة | المسار | الشرح |
|------|------|------|
| `POST` | `/admin/profile/logout` | إضافة Bearer token الحالي إلى القائمة السوداء في Redis، TTL=المدة المتبقية لصلاحية token |

**منطق تسجيل الخروج**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. وحدات التحكم الجديدة والتعديلات على الموجودة

### 2.1 CRUD لإعدادات النظام (`ConfigController`)

يرث من `BaseController`.

| الطريقة | المسار | الشرح |
|------|------|------|
| `index()` | GET `/admin/config` | قائمة مرقّمة، يمكن التصفية حسب `group`، وترقيم الصفحات عبر `page`/`limit` |
| `store()` | POST `/admin/config` | إنشاء عنصر إعداد، الحقول الإلزامية: group, key, value |
| `update()` | PUT `/admin/config/{id}` | تحديث value/type/description لعنصر الإعداد |
| `destroy()` | DELETE `/admin/config/{id}` | حذف عنصر الإعداد، يتطلب `confirmPassword()` |

### 2.2 الاستعلام عن سجلات العمليات (`LogController`)

يرث من `BaseController`.

| الطريقة | المسار | الشرح |
|------|------|------|
| `index()` | GET `/admin/log` | قائمة مرقّمة، تدعم التصفية: user_id, action, path, created_at (نطاق) |

لا يوفر إنشاء/تعديل/حذف؛ السجلات تُسجل تلقائيًا عبر الوسيط.

### 2.3 الحساب الشخصي (`ProfileController`)

يرث من `BaseController`. يعمل على المستخدم المسجل حاليًا (`$request->adminId`).

| الطريقة | المسار | الشرح |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | تحديث real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | تغيير كلمة المرور، يتطلب old_password, new_password, new_password_confirmation |

### 2.4 رفع الملفات (`UploadController`)

يرث من `BaseController`.

| الطريقة | المسار | الشرح |
|------|------|------|
| `upload()` | POST `/admin/upload` | استقبال ملف، يدعم image/jpeg/png/gif/pdf/xlsx/docx |

- الحجم الأقصى 10MB
- مسار التخزين: `public/upload/{date}/{hash}.{ext}`
- الإرجاع: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 بيانات حقيقية للوحة التحكم

**الملف**: `app/admin/controller/DashboardController.php` (تعديل)

استبدال البيانات الوهمية المثبتة في الكود بإحصائيات لحظية من قاعدة البيانات:

| المؤشر | المصدر | الشرح |
|------|------|------|
| إجمالي المستخدمين | `AdminUser::count()` | بدون الحذف الناعم |
| الجدد اليوم | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| إجمالي الأدوار | `AdminRole::count()` | |
| إجمالي الصلاحيات | `AdminPermission::count()` | |
| بيانات الاتجاه | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | إحصاء الجدد في آخر 7 أيام حسب اليوم |
| بيانات التوزيع | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | التوزيع حسب الحالة |
| أحدث العمليات | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | آخر 10 سجلات عمليات |

### 2.6 العمليات الجماعية على المستخدمين

**الملف**: `app/admin/controller/UserController.php` (تعديل، إضافة دوال)

| الطريقة | المسار | الشرح |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | حذف جماعي، نص الطلب `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | تفعيل/تعطيل جماعي، نص الطلب `{ ids: [hashid, ...], status: 1|0 }` |

- يحوَّل كل id أولًا إلى BIGINT عبر `decodeId()`
- يجب أن يجتاز `batchDestroy()` التحقق عبر `confirmPassword()`

### 2.7 استيراد البيانات

**الملف**: `app/admin/controller/ImportController.php` (جديد)

| الطريقة | المسار | الشرح |
|------|------|------|
| `users()` | POST `/admin/import/users` | رفع ملف Excel وإنشاء المستخدمين بشكل جماعي |

التدفق:
1. استقبال ملف `.xlsx`
2. التحليل عبر PhpSpreadsheet، الأعمدة المتوقعة: `username, password, real_name, phone, email, status`
3. التحقق صفًا بصف + الإنشاء (توليد المعرّف عبر snowflake، كلمة مرور bcrypt، تشفير phone/email عبر encryption)
4. إرجاع النتيجة: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 الفحص الصحي

**الملف**: `app/admin/controller/HealthController.php` (جديد)

`GET /health` (بدون مصادقة، ولا يُحتسب في سجل العمليات):

يعيد حالة اتصال كل مكوّن:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- عند فشل فحص أحد المكوّنات، تكون قيمة الحقل المقابل هي سلسلة وصف الخطأ
- المسار لا يحمل بادئة `/admin`، ويُسجل منفردًا على المستوى العام

---

## 3. تصحيحات النماذج

### 3.1 الطوابع الزمنية لـ OperationLog

**الملف**: `app/model/OperationLog.php` (تعديل)

جدول `logistics_operation_log` يحتوي عمود `created_at` فقط (بدون `updated_at`). `save()` في Eloquent سيحاول افتراضيًا الكتابة إلى `updated_at`، مما يسبب خطأ SQL.

الإصلاح: `public $timestamps = false;` + تحديد `created_at` يدويًا عند الكتابة.

### 3.2 تعديل نموذج AdminUser

- إضافة trait `Searchable`
- تنفيذ `toSearchableArray()`: يُرجع username, real_name
- عند اكتشاف كلمة مفتاحية في `UserController::index()`، يُستخدم `AdminUser::search($kw)->get()` بدلًا من LIKE في MySQL

يجب إنشاء الفهرس في ES أولًا، ويمكن ذلك عبر أوامر Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. تغييرات التوجيه

مسارات جديدة في `config/route.php`:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

تسجيل الوسائط العامة في `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. رموز الخطأ الإضافية

| code | المعنى | سيناريو الإطلاق |
|------|------|---------|
| 429 | طلبات كثيرة جدًا | تفعيل RateLimit |

---

## 6. خارج نطاق هذا التحسين

- نظام الإشعارات (يتطلب قائمة انتظار رسائل + بنية تحتية للدفع من الواجهة الأمامية)
- صفحات Flutter للواجهة الأمامية (المشروع الفرعي B)
- تحديث Token في HarmonyOS (المشروع الفرعي C)
