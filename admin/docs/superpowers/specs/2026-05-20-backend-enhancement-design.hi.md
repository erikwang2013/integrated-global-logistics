> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-20-backend-enhancement-design.md) | [English](2026-05-20-backend-enhancement-design.en.md) | [한국어](2026-05-20-backend-enhancement-design.ko.md) | [Русский](2026-05-20-backend-enhancement-design.ru.md) | [Deutsch](2026-05-20-backend-enhancement-design.de.md) | [Français](2026-05-20-backend-enhancement-design.fr.md) | [Español](2026-05-20-backend-enhancement-design.es.md) | [Português](2026-05-20-backend-enhancement-design.pt.md) | [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) | [العربية](2026-05-20-backend-enhancement-design.ar.md) | [বাংলা](2026-05-20-backend-enhancement-design.bn.md) | [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) | [日本語](2026-05-20-backend-enhancement-design.ja.md)

# उप-प्रोजेक्ट A: बैकएंड एन्हांसमेंट — डिज़ाइन स्पेक

## दायरा

यह बैकएंड एन्हांसमेंट है, कुल 15 फीचर पॉइंट, जिसमें 9 नई फ़ाइलें + 4 संशोधित फ़ाइलें शामिल हैं।

---

## नई/संशोधित फ़ाइलों की सूची

```
app/middleware/
├── OperationLog.php          # नया: ऑपरेशन लॉग की स्वतः रिकॉर्डिंग
├── Cors.php                  # नया: क्रॉस-ओरिजिन
└── RateLimit.php             # नया: Redis रेट लिमिटिंग
app/admin/controller/
├── ConfigController.php      # नया: सिस्टम कॉन्फ़िगरेशन CRUD
├── LogController.php         # नया: ऑपरेशन लॉग क्वेरी
├── ProfileController.php     # नया: व्यक्तिगत केंद्र (लॉगआउट सहित)
├── UploadController.php      # नया: फ़ाइल अपलोड
├── ImportController.php      # नया: Excel से उपयोगकर्ता आयात
└── HealthController.php      # नया: हेल्थ चेक
app/model/
├── AdminUser.php             # संशोधित: SoftDeletes + Searchable trait जोड़ा गया
└── OperationLog.php          # संशोधित: public $timestamps = false जोड़ा गया
app/middleware/
└── AdminAuth.php             # संशोधित: JWT ब्लैकलिस्ट सत्यापन
app/admin/controller/
├── DashboardController.php   # संशोधित: डेटाबेस रीयल-टाइम स्टैटिस्टिक्स में बदला गया
└── UserController.php        # संशोधित: बैच ऑपरेशन जोड़े गए
config/
└── route.php                 # संशोधित: नए रूट + मिडलवेयर जोड़े गए
```

---

## 1. मिडलवेयर

### 1.1 CORS मिडलवेयर

**फ़ाइल**: `app/middleware/Cors.php`

- OPTIONS प्रीफ़्लाइट अनुरोध सीधे 204 लौटाता है
- गैर-प्रीफ़्लाइट अनुरोध के रिस्पॉन्स हेडर में `Access-Control-Allow-Origin: *` जोड़ा जाता है
- अनुमत हेडर: `Authorization, Content-Type, API-Version`
- अधिकतम कैश: 86400 सेकंड

माउंटिंग: ग्लोबल मिडलवेयर (`config/middleware.php`)

### 1.2 रेट लिमिटिंग मिडलवेयर

**फ़ाइल**: `app/middleware/RateLimit.php`

- स्टोरेज: Redis Sorted Set स्लाइडिंग विंडो
- डिफ़ॉल्ट: 60 अनुरोध/मिनट/IP/रूट
- संवेदनशील इंटरफ़ेस:
  - `/api/auth/login`: 10 अनुरोध/मिनट
  - `/api/auth/register`: 5 अनुरोध/मिनट
- सीमा पार होने पर `429 Too Many Requests` लौटाता है

माउंटिंग: ग्लोबल मिडलवेयर (`config/middleware.php`), Cors के बाद और ApiVersion से पहले

### 1.3 ऑपरेशन लॉग मिडलवेयर

**फ़ाइल**: `app/middleware/OperationLog.php`

- केवल POST/PUT/DELETE रिकॉर्ड करता है
- रिकॉर्ड किए जाने वाले फ़ील्ड: user_id, action, method, path, ip, input(JSON)
- रिस्पॉन्स लौटने के बाद एसिंक्रोनस रूप से लिखा जाता है (ब्लॉकिंग नहीं)

माउंटिंग: `/admin` रूट ग्रुप, AdminPermission के बाद

### 1.4 ग्लोबल मिडलवेयर एक्ज़ीक्यूशन चेन

```
सभी अनुरोध:
  Cors → RateLimit → ApiVersion → {Route मिडलवेयर} → Controller

/admin/* अनुरोध:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 लॉगआउट (JWT ब्लैकलिस्ट)

**फ़ाइल**: `app/middleware/AdminAuth.php` (संशोधित)

**सिद्धांत**: JWT स्वयं स्टेटलेस है; लॉगआउट के समय token को Redis ब्लैकलिस्ट में जोड़ा जाता है, AdminAuth सत्यापन के समय पहले ब्लैकलिस्ट जांचता है।

**AdminAuth परिवर्तन**:
- `process()` की शुरुआत में जोड़ा गया: Redis `jwt_blacklist` संग्रह से जांचें कि क्या वर्तमान token ब्लैकलिस्ट में है
- ब्लैकलिस्ट में होने पर 401 लौटाता है

**लॉगआउट रूट** (व्यक्तिगत केंद्र के अंतर्गत):

| विधि | रूट | विवरण |
|------|------|------|
| `POST` | `/admin/profile/logout` | वर्तमान Bearer token को Redis ब्लैकलिस्ट में जोड़ता है, TTL=token की शेष वैधता |

**Logout तर्क**:
```php
// टोकन की शेष वैधता पार्स करें
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// ब्लैकलिस्ट में जोड़ें
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. नए कंट्रोलर और मौजूदा परिवर्तन

### 2.1 सिस्टम कॉन्फ़िगरेशन CRUD (`ConfigController`)

`BaseController` से विरासत लेता है।

| विधि | रूट | विवरण |
|------|------|------|
| `index()` | GET `/admin/config` | पेजिनेटेड सूची, `group` से फ़िल्टर किया जा सकता है, `page`/`limit` पेजिनेशन |
| `store()` | POST `/admin/config` | कॉन्फ़िगरेशन आइटम बनाता है, अनिवार्य: group, key, value |
| `update()` | PUT `/admin/config/{id}` | कॉन्फ़िगरेशन आइटम की value/type/description अपडेट करता है |
| `destroy()` | DELETE `/admin/config/{id}` | कॉन्फ़िगरेशन आइटम हटाता है, `confirmPassword()` आवश्यक |

### 2.2 ऑपरेशन लॉग क्वेरी (`LogController`)

`BaseController` से विरासत लेता है।

| विधि | रूट | विवरण |
|------|------|------|
| `index()` | GET `/admin/log` | पेजिनेटेड सूची, फ़िल्टर समर्थित: user_id, action, path, created_at(रेंज) |

कोई जोड़/बदलाव/हटाना प्रदान नहीं करता; लॉग मिडलवेयर द्वारा स्वतः रिकॉर्ड किए जाते हैं।

### 2.3 व्यक्तिगत केंद्र (`ProfileController`)

`BaseController` से विरासत लेता है। वर्तमान लॉग-इन उपयोगकर्ता पर कार्य करता है (`$request->adminId`)।

| विधि | रूट | विवरण |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email अपडेट करता है |
| `updatePassword()` | PUT `/admin/profile/password` | पासवर्ड बदलता है, old_password, new_password, new_password_confirmation आवश्यक |

### 2.4 फ़ाइल अपलोड (`UploadController`)

`BaseController` से विरासत लेता है।

| विधि | रूट | विवरण |
|------|------|------|
| `upload()` | POST `/admin/upload` | फ़ाइल प्राप्त करता है, image/jpeg/png/gif/pdf/xlsx/docx समर्थित |

- अधिकतम 10MB
- स्टोरेज पथ: `public/upload/{date}/{hash}.{ext}`
- रिस्पॉन्स: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 डैशबोर्ड वास्तविक डेटा

**फ़ाइल**: `app/admin/controller/DashboardController.php` (संशोधित)

वर्तमान हार्ड-कोडेड नकली डेटा को डेटाबेस रीयल-टाइम स्टैटिस्टिक्स में बदलें:

| मेट्रिक | स्रोत | विवरण |
|------|------|------|
| कुल उपयोगकर्ता | `AdminUser::count()` | सॉफ्ट-डिलीटेड को छोड़कर |
| आज के नए | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| कुल भूमिकाएँ | `AdminRole::count()` | |
| कुल अनुमतियाँ | `AdminPermission::count()` | |
| ट्रेंड डेटा | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | पिछले 7 दिनों के नए उपयोगकर्ताओं की दैनिक गणना |
| वितरण डेटा | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | स्थिति के अनुसार वितरण |
| हाल की गतिविधियाँ | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | हाल के 10 ऑपरेशन लॉग |

### 2.6 उपयोगकर्ता बैच ऑपरेशन

**फ़ाइल**: `app/admin/controller/UserController.php` (संशोधित, नई विधियाँ जोड़ी गईं)

| विधि | रूट | विवरण |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | बैच डिलीट, अनुरोध बॉडी `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | बैच सक्षम/अक्षम, अनुरोध बॉडी `{ ids: [hashid, ...], status: 1|0 }` |

- प्रत्येक id को पहले `decodeId()` से BIGINT में बदला जाता है
- `batchDestroy()` को `confirmPassword()` सत्यापन पास करना आवश्यक है

### 2.7 डेटा आयात

**फ़ाइल**: `app/admin/controller/ImportController.php` (नया)

| विधि | रूट | विवरण |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel फ़ाइल अपलोड करके बैच में उपयोगकर्ता बनाता है |

फ़्लो:
1. `.xlsx` फ़ाइल प्राप्त करें
2. PhpSpreadsheet पार्सिंग, अपेक्षित कॉलम: `username, password, real_name, phone, email, status`
3. पंक्ति-दर-पंक्ति सत्यापन + निर्माण (snowflake से ID, bcrypt पासवर्ड, encryption से phone/email एन्क्रिप्शन)
4. परिणाम लौटाएं: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 हेल्थ चेक

**फ़ाइल**: `app/admin/controller/HealthController.php` (नया)

`GET /health` (प्रमाणीकरण की आवश्यकता नहीं, ऑपरेशन लॉग में नहीं गिना जाता):

प्रत्येक कंपोनेंट की कनेक्शन स्थिति लौटाता है:
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

- कंपोनेंट जांच विफल होने पर संबंधित फ़ील्ड का मान त्रुटि विवरण स्ट्रिंग होता है
- रूट `/admin` प्रीफ़िक्स पर माउंट नहीं होता, ग्लोबल रूप से अलग से रजिस्टर होता है

---

## 3. मॉडल सुधार

### 3.1 OperationLog टाइमस्टैम्प

**फ़ाइल**: `app/model/OperationLog.php` (संशोधित)

तालिका `erik_operation_log` में केवल `created_at` कॉलम है (`updated_at` नहीं)। Eloquent का डिफ़ॉल्ट `save()` `updated_at` लिखने का प्रयास करता है, जिससे SQL त्रुटि होती है।

सुधार: `public $timestamps = false;` + लिखते समय `created_at` मैन्युअल रूप से निर्धारित करें।

### 3.2 AdminUser मॉडल परिवर्तन

- `Searchable` trait जोड़ें
- `toSearchableArray()` लागू करें: username, real_name लौटाता है
- `UserController::index()` में कीवर्ड मिलने पर MySQL LIKE के बजाय `AdminUser::search($kw)->get()` का उपयोग करें

ES में पहले इंडेक्स बनाना आवश्यक है, Scout कमांड के माध्यम से:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. रूट परिवर्तन

`config/route.php` में नए रूट:

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

`config/middleware.php` में ग्लोबल मिडलवेयर रजिस्टर करें:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. त्रुटि कोड पूरक

| code | अर्थ | ट्रिगर परिदृश्य |
|------|------|---------|
| 429 | बहुत अधिक अनुरोध | RateLimit ट्रिगर |

---

## 6. इस दायरे में शामिल नहीं

- नोटिफिकेशन सिस्टम (मैसेज क्यू + फ्रंटएंड पुश इन्फ्रास्ट्रक्चर की आवश्यकता)
- Flutter फ्रंटएंड पेज (उप-प्रोजेक्ट B)
- HarmonyOS Token रिफ्रेश (उप-प्रोजेक्ट C)
