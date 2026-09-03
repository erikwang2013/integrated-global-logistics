> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# সাবপ্রজেক্ট A: ব্যাকএন্ড এনহ্যান্সমেন্ট — ডিজাইন স্পেসিফিকেশন

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## সুযোগ

এটি ব্যাকএন্ড এনহ্যান্সমেন্ট, মোট 15টি ফিচার পয়েন্ট, 9টি নতুন ফাইল + 4টি পরিবর্তিত ফাইল জড়িত।

---

## নতুন/পরিবর্তিত ফাইলের তালিকা

```
app/middleware/
├── OperationLog.php          # নতুন: অপারেশন লগ স্বয়ংক্রিয় রেকর্ডিং
├── Cors.php                  # নতুন: CORS (ক্রস-অরিজিন)
└── RateLimit.php             # নতুন: Redis রেট লিমিটিং
app/admin/controller/
├── ConfigController.php      # নতুন: সিস্টেম কনফিগ CRUD
├── LogController.php         # নতুন: অপারেশন লগ কোয়েরি
├── ProfileController.php     # নতুন: ব্যক্তিগত প্রোফাইল (লগআউটসহ)
├── UploadController.php      # নতুন: ফাইল আপলোড
├── ImportController.php      # নতুন: Excel-এর মাধ্যমে ব্যবহারকারী ইমপোর্ট
└── HealthController.php      # নতুন: হেলথ চেক
app/model/
├── AdminUser.php             # পরিবর্তিত: SoftDeletes + Searchable trait যোগ করা হয়েছে
└── OperationLog.php          # পরিবর্তিত: public $timestamps = false যোগ করা হয়েছে
app/middleware/
└── AdminAuth.php             # পরিবর্তিত: JWT ব্ল্যাকলিস্ট যাচাই
app/admin/controller/
├── DashboardController.php   # পরিবর্তিত: ডেটাবেস রিয়েল-টাইম পরিসংখ্যানে পরিবর্তন
└── UserController.php        # পরিবর্তিত: ব্যাচ অপারেশন যোগ করা হয়েছে
config/
└── route.php                 # পরিবর্তিত: নতুন রাউট + মিডলওয়্যার
```

---

## 1. মিডলওয়্যার

### 1.1 CORS মিডলওয়্যার

**ফাইল**: `app/middleware/Cors.php`

- OPTIONS প্রি-ফ্লাইট অনুরোধ সরাসরি 204 রিটার্ন করে
- নন-প্রি-ফ্লাইট অনুরোধে রেসপন্স হেডারে `Access-Control-Allow-Origin: *` যুক্ত হয়
- অনুমোদিত হেডার: `Authorization, Content-Type`
- সর্বোচ্চ ক্যাশ: 86400 সেকেন্ড

মাউন্ট: গ্লোবাল মিডলওয়্যার (`config/middleware.php`)

### 1.2 রেট লিমিট মিডলওয়্যার

**ফাইল**: `app/middleware/RateLimit.php`

- স্টোরেজ: Redis Sorted Set স্লাইডিং উইন্ডো
- ডিফল্ট: 60 বার/মিনিট/IP/রাউট
- সংবেদনশীল এন্ডপয়েন্ট:
  - `/api/v1/auth/login`: 10 বার/মিনিট
  - `/api/v1/auth/register`: 5 বার/মিনিট
- লিমিট অতিক্রম করলে `429 Too Many Requests` রিটার্ন হয়

মাউন্ট: গ্লোবাল মিডলওয়্যার (`config/middleware.php`), Cors-এর পরে, ApiVersion-এর আগে

### 1.3 অপারেশন লগ মিডলওয়্যার

**ফাইল**: `app/middleware/OperationLog.php`

- শুধুমাত্র POST/PUT/DELETE রেকর্ড করে
- রেকর্ড ফিল্ড: user_id, action, method, path, ip, input(JSON)
- রেসপন্স ফেরত পাঠানোর পর অ্যাসিঙ্ক্রোনাসভাবে লেখা হয় (ব্লক করে না)

মাউন্ট: `/admin` রাউট গ্রুপে, AdminPermission-এর পরে

### 1.4 গ্লোবাল মিডলওয়্যার এক্সিকিউশন চেইন

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 লগআউট (JWT ব্ল্যাকলিস্ট)

**ফাইল**: `app/middleware/AdminAuth.php` (পরিবর্তিত)

**নীতি**: JWT নিজে স্টেটলেস; লগআউটের সময় token Redis ব্ল্যাকলিস্টে যুক্ত হয়, AdminAuth যাচাই করার সময় প্রথমে ব্ল্যাকলিস্ট চেক করে।

**AdminAuth পরিবর্তন**:
- `process()`-এর শুরুতে নতুন: Redis `jwt_blacklist` সেটে বর্তমান token ব্ল্যাকলিস্টে আছে কিনা চেক করা
- ব্ল্যাকলিস্টে থাকলে 401 রিটার্ন হয়

**লগআউট রাউট** (প্রোফাইল সেকশনে):

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `POST` | `/admin/profile/logout` | বর্তমান Bearer token Redis ব্ল্যাকলিস্টে যোগ করে, TTL=token-এর অবশিষ্ট মেয়াদ |

**Logout লজিক**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. নতুন কন্ট্রোলার ও বিদ্যমান পরিবর্তন

### 2.1 সিস্টেম কনফিগ CRUD (`ConfigController`)

`BaseController` উত্তরাধিকারসূত্রে গ্রহণ করে।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `index()` | GET `/admin/config` | পেজিনেটেড তালিকা, `group` দিয়ে ফিল্টার করা যায়, `page`/`limit` দিয়ে পেজিনেশন |
| `store()` | POST `/admin/config` | কনফিগ আইটেম তৈরি, আবশ্যক: group, key, value |
| `update()` | PUT `/admin/config/{id}` | কনফিগ আইটেম আপডেট: value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | কনফিগ আইটেম মুছুন, `confirmPassword()` প্রয়োজন |

### 2.2 অপারেশন লগ কোয়েরি (`LogController`)

`BaseController` উত্তরাধিকারসূত্রে গ্রহণ করে।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `index()` | GET `/admin/log` | পেজিনেটেড তালিকা, ফিল্টার সমর্থন: user_id, action, path, created_at (রেঞ্জ) |

কোনো add/edit/delete নেই; লগ মিডলওয়্যার দ্বারা স্বয়ংক্রিয়ভাবে রেকর্ড হয়।

### 2.3 ব্যক্তিগত প্রোফাইল (`ProfileController`)

`BaseController` উত্তরাধিকারসূত্রে গ্রহণ করে। বর্তমান লগইন করা ব্যবহারকারীর উপর অপারেশন করে (`$request->adminId`)।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email আপডেট |
| `updatePassword()` | PUT `/admin/profile/password` | পাসওয়ার্ড পরিবর্তন, প্রয়োজন: old_password, new_password, new_password_confirmation |

### 2.4 ফাইল আপলোড (`UploadController`)

`BaseController` উত্তরাধিকারসূত্রে গ্রহণ করে।

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `upload()` | POST `/admin/upload` | ফাইল গ্রহণ করে, সমর্থিত: image/jpeg/png/gif/pdf/xlsx/docx |

- সর্বোচ্চ 10MB
- স্টোরেজ পাথ: `public/upload/{date}/{hash}.{ext}`
- রিটার্ন: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 ড্যাশবোর্ড রিয়েল ডেটা

**ফাইল**: `app/admin/controller/DashboardController.php` (পরিবর্তিত)

বর্তমান হার্ডকোডেড ফেক ডেটাকে ডেটাবেস রিয়েল-টাইম পরিসংখ্যানে পরিবর্তন করুন:

| মেট্রিক | উৎস | ব্যাখ্যা |
|------|------|------|
| মোট ব্যবহারকারী | `AdminUser::count()` | সফট-ডিলিটেড বাদে |
| আজকের নতুন | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| মোট ভূমিকা | `AdminRole::count()` | |
| মোট পারমিশন | `AdminPermission::count()` | |
| ট্রেন্ড ডেটা | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | প্রতিদিনের ভিত্তিতে সাম্প্রতিক 7 দিনের নতুন ব্যবহারকারী |
| বিতরণ ডেটা | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | স্ট্যাটাস অনুযায়ী বিতরণ |
| সাম্প্রতিক অপারেশন | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | সাম্প্রতিক 10টি অপারেশন লগ |

### 2.6 ব্যবহারকারী ব্যাচ অপারেশন

**ফাইল**: `app/admin/controller/UserController.php` (পরিবর্তিত, নতুন মেথড)

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | ব্যাচ ডিলিট, রিকোয়েস্ট বডি `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | ব্যাচ সক্রিয়/নিষ্ক্রিয়, রিকোয়েস্ট বডি `{ ids: [hashid, ...], status: 1|0 }` |

- প্রতিটি id আগে `decodeId()` দিয়ে BIGINT-এ রূপান্তরিত হয়
- `batchDestroy()` অবশ্যই `confirmPassword()` যাচাইয়ের মধ্য দিয়ে যাবে

### 2.7 ডেটা ইমপোর্ট

**ফাইল**: `app/admin/controller/ImportController.php` (নতুন)

| মেথড | রাউট | ব্যাখ্যা |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel ফাইল আপলোড করে ব্যাচে ব্যবহারকারী তৈরি |

প্রক্রিয়া:
1. `.xlsx` ফাইল গ্রহণ করুন
2. PhpSpreadsheet দিয়ে পার্স করুন, প্রত্যাশিত কলাম: `username, password, real_name, phone, email, status`
3. সারি ধরে যাচাই + তৈরি (snowflake দিয়ে ID জেনারেশন, bcrypt দিয়ে পাসওয়ার্ড, encryption দিয়ে phone/email এনক্রিপ্ট)
4. ফলাফল রিটার্ন: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 হেলথ চেক

**ফাইল**: `app/admin/controller/HealthController.php` (নতুন)

`GET /health` (প্রমাণীকরণ প্রয়োজন নেই, অপারেশন লগে গণনা করা হয় না):

প্রতিটি কম্পোনেন্টের সংযোগ অবস্থা রিটার্ন করে:
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

- কম্পোনেন্ট ডিটেকশন ব্যর্থ হলে সংশ্লিষ্ট ফিল্ডের মান হবে এরর ডেসক্রিপশন স্ট্রিং
- রাউটে `/admin` উপসর্গ নেই, আলাদাভাবে গ্লোবালে রেজিস্টার করা হয়েছে

---

## 3. মডেল সংশোধন

### 3.1 OperationLog টাইমস্ট্যাম্প

**ফাইল**: `app/model/OperationLog.php` (পরিবর্তিত)

টেবিল `logistics_operation_log`-এ শুধুমাত্র `created_at` কলাম আছে (`updated_at` নেই)। Eloquent-এর ডিফল্ট `save()` `updated_at` লেখার চেষ্টা করে, ফলে SQL এরর হয়।

ফিক্স: `public $timestamps = false;` + লেখার সময় ম্যানুয়ালি `created_at` নির্ধারণ।

### 3.2 AdminUser মডেল পরিবর্তন

- `Searchable` trait যোগ করুন
- `toSearchableArray()` বাস্তবায়ন: username, real_name রিটার্ন করে
- `UserController::index()`-এ কীওয়ার্ড পাওয়া গেলে MySQL LIKE-এর বদলে `AdminUser::search($kw)->get()` ব্যবহার করে

ES-এর জন্য প্রথমে ইনডেক্স তৈরি করতে হবে, Scout কমান্ড দিয়ে:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. রাউট পরিবর্তন

`config/route.php`-এ নতুন রাউট:

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

`config/middleware.php`-এ গ্লোবাল মিডলওয়্যার রেজিস্টার:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. এরর কোড সংযোজন

| code | অর্থ | ট্রিগার পরিস্থিতি |
|------|------|---------|
| 429 | অনুরোধ খুব বেশি ঘন ঘন | RateLimit ট্রিগার |

---

## 6. এই স্কোপের মধ্যে নেই

- নোটিফিকেশন সিস্টেম (মেসেজ কিউ + ফ্রন্টএন্ড পুশ ইনফ্রাস্ট্রাকচার প্রয়োজন)
- Flutter ফ্রন্টএন্ড পেজ (সাবপ্রজেক্ট B)
- HarmonyOS Token রিফ্রেশ (সাবপ্রজেক্ট C)
