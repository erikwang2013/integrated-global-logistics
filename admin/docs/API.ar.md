# وثيقة مرجع API

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

## 1. نظرة عامة

نظام الإدارة المفتوح (open-admin) مبني على webman v2 ويوفّر واجهات RESTful JSON API. جميع واجهات الإدارة تتطلب مصادقة JWT وتحقق صلاحيات RBAC، بينما تُوجَّه الواجهات العامة عبر ترويسة إصدار API إلى وحدات التحكم حسب الإصدار.

- **عنوان URL الأساسي**: `http://localhost:8787`
- **إصدار API**: يُتحكم عبر ترويسة الطلب `API-Version: v1` (الافتراضي v1 عند الغياب)
- **اللغة**: التبديل عبر ترويسة `Accept-Language` أو المعامل `?lang=zh_CN|en` (الافتراضي zh_CN)، ويتم الكشف تلقائيًا عبر وسيط Locale

> **نظرة عامة على النقاط الطرفية**: المصادقة(5) | لوحة التحكم(1) | المستخدمون(7) | الأدوار(4) | الصلاحيات(4) | الإعدادات(4) | السجلات(1) | الملف الشخصي(3) | الاستيراد والتصدير(3) | الرفع(1) | التشغيل والصيانة(4: health/metrics/docs/security.txt) | بإجمالي 37 نقطة طرفية
- **المصادقة**: `Authorization: Bearer <token>` (JWT)
- **تنسيق الاستجابة**: `{ "code": 0, "message": "success", "data": {...} }`
- **نقطة التوثيق**: تُرجع `GET /api/docs` مواصفات OpenAPI 3.0 بصيغة JSON

### متطلبات الطلبات

- يُسمح فقط بطرق `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`، واستخدام طرق HTTP أخرى (مثل TRACE وCONNECT وPATCH) يُرجع 405
- يجب أن تُضبط جميع طلبات `POST` / `PUT` على `Content-Type: application/json` (باستثناء رفع الملفات)، وإلا يُرجع 415
- يجب ألا يتجاوز حجم جسم الطلب 10MB، وإلا يُرجع 413
- يفحص مرشح الأمان جميع مدخلات الطلبات بحثًا عن XSS وحقن SQL واجتياز المسار وحقن الأوامر، ويعيد 403 عند الإصابة
- فشل تسجيل الدخول 5 مرات متتالية يفعّل قفل الحساب (15 دقيقة)، وتُرجع طلبات الدخول خلال فترة القفل 429
- يمكن لنفس المستخدم حمل 3 رموز نشطة كحد أقصى، وعند تجاوز العدد يُضاف أقدم رمز تلقائيًا إلى القائمة السوداء

## 2. أكواد الأخطاء

| code | المعنى | سيناريو التفعيل |
|------|------|---------|
| 0 | نجاح | |
| 400 | خطأ في معاملات الطلب | تنسيق الطلب غير صحيح |
| 401 | غير مصادَق | الرمز مفقود / منتهي الصلاحية / في القائمة السوداء |
| 403 | لا صلاحية / اعتراض أمني | صلاحيات RBAC غير كافية / إصابة في SecurityFilter |
| 404 | المورد غير موجود | الهدف من الاستعلام/التحديث/الحذف غير موجود |
| 405 | طريقة الطلب غير مسموحة | يُسمح فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD، وتُرفض الطرق غير القياسية مباشرة |
| 413 | جسم الطلب كبير جدًا | Content-Length يتجاوز 10MB |
| 415 | نوع الوسائط غير مدعوم | Content-Type لطلبات POST/PUT ليس JSON وليس رفع ملفات |
| 422 | فشل التحقق من المعاملات | حقول إلزامية مفقودة، تنسيق غير مطابق، أو فشل تحقق الأعمال |
| 429 | الطلبات متكررة جدًا | تفعيل RateLimit / قفل الحساب (5 محاولات دخول فاشلة تقفل 15 دقيقة) |
| 500 | خطأ داخلي في الخادم | |

## 3. النقاط الطرفية العامة

تُركّب جميع النقاط الطرفية العامة تحت مجموعة `/api`، ويتم توزيعها عبر وسيط `ApiVersion` وفق ترويسة `API-Version` إلى وحدات التحكم حسب الإصدار (مثل `app\api\v1\controller\AuthController`).

### 3.1 فحص الصحة

```
GET /health
```

- **المصادقة**: غير مطلوبة
- **تحديد المعدل**: بدون

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

قيم `database` و`redis` و`elasticsearch`: `"ok"` | `"unavailable"`. يُرجع `elasticsearch` القيمة `"unavailable"` عند عدم وصول ES، ويعيد قيمة الحالة الفعلية (مثل `"red"`) إذا لم تكن الحالة الصحية للمجموعة green/yellow.

### 3.2 توثيق API

```
GET /api/docs
```

- **المصادقة**: غير مطلوبة
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)
- **الاستجابة**: مواصفات OpenAPI 3.0.3 بصيغة JSON، تتضمن تعريفات جميع النقاط الطرفية والمعاملات وSchemas

### 3.3 توليد كود التحقق

```
POST /api/captcha/generate
```

- **المصادقة**: غير مطلوبة
- **ترويسة الطلب**: `API-Version: v1` (إلزامية)
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "difficulty": "medium"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| difficulty | string | لا | `easy` / `medium` / `hard`، الافتراضي `medium` |

**مثال على الاستجابة** — نوع النقر (`type: "click"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "type": "click",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "targets": [
        { "order": 1, "text": "A", "x": 120, "y": 85 },
        { "order": 2, "text": "B", "x": 310, "y": 42 }
      ]
    }
  }
}
```

**مثال على الاستجابة** — نوع الانزلاق (`type: "slider"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "def456abc789",
    "type": "slider",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "x": 120,
      "y": 60,
      "puzzle_w": 50,
      "puzzle_h": 50,
      "puzzle": "data:image/png;base64,iVBORw0KGgo..."
    }
  }
}
```

**مثال على الاستجابة** — نوع التدوير (`type: "rotate"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "ghi789abc012",
    "type": "rotate",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "angle": 45
    }
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| key | string | معرّف كود التحقق، يُعاد إرساله عند التحقق |
| type | string | نوع كود التحقق: `click` / `slider` / `rotate` |
| image | string | صورة base64 data URI |
| extra | object | بيانات إضافية مرتبطة بالنوع (انظر أدناه) |

**`extra` حسب النوع**:

| type | حقول extra | النوع | الوصف |
|------|-----------|------|------|
| click | targets | array | أهداف النقر، تتضمن `order` (الترتيب) و`text` (النص الإرشادي) و`x` `y` (الإحداثيات) |
| slider | x, y | int | إحداثيات الزاوية العلوية اليسرى للفجوة (على لوحة 300×200) |
| slider | puzzle_w, puzzle_h | int | عرض وارتفاع صورة اللغز |
| slider | puzzle | string | صورة اللغز base64 data URI |
| rotate | angle | int | زاوية التدوير الصحيحة (0-359)، يلزم التدوير بمقدار `360-angle` لإرجاع الصورة للوضع الصحيح |

### 3.4 التحقق من كود التحقق

```
POST /api/captcha/verify
```

- **المصادقة**: غير مطلوبة
- **ترويسة الطلب**: `API-Version: v1` (إلزامية)
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب** — نوع النقر (`type: "click"`):
```json
{
  "key": "abc123def456",
  "type": "click",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

**جسم الطلب** — نوع الانزلاق (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**جسم الطلب** — نوع التدوير (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| key | string | نعم | مفتاح كود التحقق، يُرجع من generate |
| type | string | نعم | نوع كود التحقق، يجب أن يطابق `type` الذي أرجعه generate |
| clicks | متغير | نعم | بيانات الإجابة، يتغير تنسيقها حسب type (انظر أدناه) |

**`clicks` حسب النوع**:

| type | نوع clicks | الوصف | هامش الخطأ |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | مصفوفة إحداثيات النقر، حسب ترتيب order | نصف قطر 18px |
| slider | `int` | إزاحة المحور X لشريط الانزلاق | ±4px |
| rotate | `int` | زاوية التدوير (0-359) | ±5° |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

بعد نجاح التحقق، يكتب الخادم `captcha_verified:{key}` في Redis (TTL 300s)، ويسمح بناءً عليه لواجهة الدخول.
عند فشل التحقق يكون `code` هو 422 و`message` هو `"验证失败，请重试"` و`data.valid` هو `false`.

### 3.5 تسجيل الدخول

```
POST /api/auth/login
```

- **المصادقة**: غير مطلوبة
- **ترويسة الطلب**: `API-Version: v1` (إلزامية)
- **تحديد المعدل**: 10 مرات/دقيقة (حسب IP + المسار)

**جسم الطلب**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم |
| password | string | نعم | min:6, max:32 (نص صريح) | مشفّر بـ AES-256-CBC-HMAC ثم ترميز Base64 (متوافق مع النص الصريح) |
| captcha_key | string | نعم | | مفتاح كود التحقق (يجب اجتياز `/api/captcha/verify` أولاً) |

### بروتوكول تشفير كلمة المرور

يُستخدم **تشفير غير متماثل RSA-2048**، والمفتاح العام موجود في كود الواجهة الأمامية (يمكن كشفه بأمان)، بينما يحتفظ الخادم بالمفتاح الخاص فقط.

```
加密流程 (客户端):
  RSA 公钥 (PEM) → PKCS1v1.5 加密 → Base64 编码 → 传输

解密流程 (服务端，逐级回退):
  1. RSA 私钥解密 → 成功且为合法 UTF-8 → 使用解密结果
  2. AES-256-CBC-HMAC 解密 → 成功 → 使用解密结果（旧客户端兼容）
  3. 明文回退 → 直接使用原始输入
```

المفتاح العام مدمج في تطبيق الواجهة الأمامية، ولا حاجة لنقله عبر الشبكة. المفتاح الخاص مخزّن فقط في `RSA_PRIVATE_KEY` داخل `.env`، ولا يجوز كشفه.

> تشفير AES المتماثل هو حل توافق للنسخ القديمة، وسيُزال بعد ترحيل جميع العملاء إلى RSA.

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| access_token | string | رمز وصول JWT |
| refresh_token | string | رمز تحديث JWT |
| expires_in | int | مدة صلاحية رمز الوصول (بالثواني)، الافتراضي 7200 |
| user.id | string | معرف المستخدم المشفّر بـ hashid |
| user.username | string | اسم المستخدم |
| user.real_name | string | الاسم الحقيقي |

**الأخطاء المحتملة**:
- 422: فشل التحقق من المعاملات (حقول إلزامية مفقودة، تنسيق غير مطابق)
- 422: يُرجى إكمال التحقق من كود التحقق أولاً (captcha_key لم يجتز `/api/captcha/verify`)
- 401: اسم المستخدم أو كلمة المرور خاطئة
- 403: الحساب معطّل
- 429: الحساب مقفول، يُرجى المحاولة بعد 15 دقيقة (يُفعَّل بعد 5 محاولات دخول فاشلة)

### 3.6 التسجيل

```
POST /api/auth/register
```

- **المصادقة**: غير مطلوبة
- **ترويسة الطلب**: `API-Version: v1` (إلزامية)
- **تحديد المعدل**: 5 مرات/دقيقة (حسب IP + المسار)

**جسم الطلب**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم (فريد) |
| password | string | نعم | min:6, max:32 (نص صريح) | مشفّر بـ AES-256-CBC-HMAC ثم ترميز Base64 |
| real_name | string | نعم | max:50 | الاسم الحقيقي |
| captcha_key | string | نعم | | مفتاح كود التحقق (يجب اجتياز `/api/captcha/verify` أولاً) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

بعد نجاح التسجيل تُرجع رموز JWT مباشرة، وتكون حالة المستخدم مفعّلة افتراضيًا (status=1).

### 3.7 تحديث الرمز

```
POST /api/auth/refresh
```

- **المصادقة**: غير مطلوبة
- **ترويسة الطلب**: `API-Version: v1` (إلزامية)
- **تحديد المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| refresh_token | string | نعم | refresh_token الذي حصلت عليه عند الدخول/التسجيل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

يعيد التحديث الناجح access_token وrefresh_token جديدين معًا، ويبطل الرمز القديم تلقائيًا. ويحدّث التحديث وقت آخر دخول وعنوان IP للمستخدم.

**الأخطاء المحتملة**:
- 422: رمز التحديث مفقود
- 401: رمز التحديث غير صالح أو منتهي الصلاحية

### 3.8 مقاييس مراقبة Prometheus

```
GET /metrics
```

- **المصادقة**: غير مطلوبة
- **تحديد المعدل**: بدون
- **تنسيق الاستجابة**: تنسيق نص Prometheus (`text/plain; version=0.0.4`)

نقطة طرفية عامة لمقاييس مراقبة Prometheus، تُستخدم لسحب البيانات عبر Grafana/Prometheus.

**مثال على الاستجابة**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| اسم المقياس | النوع | الوصف |
|------|------|------|
| `openadmin_http_requests_total` | gauge | إجمالي عدد طلبات HTTP التراكمية |
| `openadmin_active_users` | gauge | عدد المستخدمين النشطين حاليًا (سجّلوا الدخول خلال 24 ساعة) |
| `openadmin_db_connection_status` | gauge | حالة اتصال قاعدة البيانات، 1=طبيعي, 0=شاذ |
| `openadmin_redis_connection_status` | gauge | حالة اتصال Redis، 1=طبيعي, 0=شاذ |
| `openadmin_memory_usage_bytes` | gauge | استهلاك الذاكرة الحالي لعملية PHP (بايت) |

## 4. لوحة التحكم

تُركّب جميع واجهات الإدارة تحت مجموعة `/admin`، وتمر عبر ثلاثة وسائط: `AdminAuth` (مصادقة JWT) و`AdminPermission` (تحقق صلاحيات RBAC) و`OperationLog` (تسجيل العمليات).

### 4.1 بيانات لوحة التحكم

```
GET /admin/dashboard
```

- **المصادقة**: JWT + RBAC
- **التخزين المؤقت**: Redis لمدة 5 دقائق

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| حقول stats | النوع | الوصف |
|------|------|------|
| label | string | اسم المقياس |
| value | string | قيمة المقياس (نوع نصي) |
| icon | string | اسم أيقونة Material |
| color | string | قيمة لون البطاقة |
| trend | float? | معدل النمو اليومي (نسبة مئوية)، موجود فقط في «إجمالي المستخدمين» |

| حقول trends | النوع | الوصف |
|------|------|------|
| dates | array{string} | تسلسل تواريخ آخر 30 يومًا |
| series | array{object} | بيانات خط الاتجاه، كل عنصر يتضمن name (الاسم) وdata (مصفوفة القيم) وcolor (اللون) |

## 5. إدارة المستخدمين

جميع واجهات إدارة المستخدمين تُرجع `id` كسلسلة مشفّرة بـ hashid. حقل كلمة المرور مستبعد من الاستجابات. يُعرض رقم الهاتف والبريد الإلكتروني في واجهات القائمة بشكل مموّه، بينما تُرجع في واجهات التفاصيل بالنص الصريح (حقول قاعدة البيانات المشفرة تُفك تلقائيًا عبر trait الخاص بـ Encryptable).

### 5.1 قائمة المستخدمين

```
GET /admin/user
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |
| keyword | string | لا | | كلمة البحث، تطابق اسم المستخدم والاسم الحقيقي |
| status | int | لا | | فلترة الحالة، 0=معطّل، 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرف المستخدم المشفّر بـ hashid |
| username | string | اسم المستخدم |
| real_name | string | الاسم الحقيقي |
| phone | string | رقم هاتف مموّه (تنسيق `138****5678`) |
| email | string | بريد إلكتروني مموّه (تنسيق `a***@example.com`) |
| status | int | 1=مفعّل, 0=معطّل |
| last_login_at | string | وقت آخر دخول (datetime) |
| created_at | string | وقت الإنشاء (datetime) |

### 5.2 إنشاء مستخدم

```
POST /admin/user
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم (فريد) |
| password | string | نعم | min:6, max:32 | كلمة المرور (تُخزَّن بـ bcrypt) |
| real_name | string | نعم | max:50 | الاسم الحقيقي |
| phone | string | لا | | رقم الهاتف (مشفر التخزين عبر Encryptable) |
| email | string | لا | | البريد الإلكتروني (مشفر التخزين عبر Encryptable) |
| status | int | لا | in:0,1 | الحالة، الافتراضي 1 (مفعّل) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**الأخطاء المحتملة**:
- 422: اسم المستخدم موجود بالفعل
- 422: فشل التحقق من المعاملات (حقول إلزامية مفقودة)

### 5.3 تفاصيل المستخدم

```
GET /admin/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرف المستخدم المشفّر بـ hashid

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

في واجهة التفاصيل تُرجع `phone` و`email` بالنص الصريح (مشفرة في قاعدة البيانات، ويُفك تشفيرها تلقائيًا عبر cast الخاص بـ Encryptable)، دون إخفاء. أما `password` و`id_card` فلا تظهران أبدًا في الاستجابات.

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود

### 5.4 تحديث المستخدم

```
PUT /admin/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرف المستخدم المشفّر بـ hashid

**جسم الطلب**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| real_name | string | لا | الاسم الحقيقي، إن لم يُرسل يبقى على قيمته الأصلية |
| password | string | لا | كلمة مرور جديدة، لا تتغير عند إرسال سلسلة فارغة أو عدم إرسالها |
| phone | string | لا | رقم الهاتف |
| email | string | لا | البريد الإلكتروني |
| status | int | لا | 0=معطّل, 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود

### 5.5 حذف المستخدم

```
DELETE /admin/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرف المستخدم المشفّر بـ hashid
- **عملية حساسة**: تتطلب تأكيد كلمة المرور مرتين

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| password | string | نعم | كلمة مرور المستخدم المسجّل دخوله حاليًا (تأكيد ثانٍ) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

يُطبَّق حذف ناعم (Eloquent SoftDeletes)، حيث تُوسم البيانات بـ deleted_at دون حذف فيزيائي.

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود
- 422: العمليات الحساسة تتطلب إدخال كلمة المرور للتأكيد (password فارغة)
- 422: فشل التحقق من كلمة المرور (كلمة المرور غير مطابقة)

### 5.6 حذف المستخدمين بالجملة

```
POST /admin/user/batch/destroy
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور مرتين

**جسم الطلب**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| ids | array{string} | نعم | مصفوفة معرفات المستخدمين المشفرة بـ hashid |
| password | string | نعم | كلمة مرور المستخدم المسجّل دخوله حاليًا (تأكيد ثانٍ) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

يُطبَّق حذف ناعم، و`data.count` هو العدد الفعلي المحذوف.

**الأخطاء المحتملة**:
- 422: يُرجى اختيار المستخدمين المطلوب حذفهم (ids فارغة)
- 422: معرف غير صالح (فشل فك ترميز hashid)
- 422: فشل التحقق من كلمة المرور

### 5.7 تفعيل/تعطيل المستخدمين بالجملة

```
POST /admin/user/batch/status
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| ids | array{string} | نعم | مصفوفة معرفات المستخدمين المشفرة بـ hashid |
| status | int | نعم | 0=معطّل, 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

تتغير رسالة message ديناميكيًا حسب قيمة status إلى `"批量启用成功"` أو `"批量禁用成功"`.

**الأخطاء المحتملة**:
- 422: يُرجى اختيار المستخدمين (ids فارغة)
- 422: قيمة الحالة غير صالحة (status ليس 0 أو 1)

## 6. إدارة الأدوار

### 6.1 قائمة الأدوار

```
GET /admin/role
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرف الدور المشفّر بـ hashid |
| name | string | اسم الدور |
| slug | string | معرّف الدور (فريد، يُستخدم للحكم على الصلاحيات) |
| description | string | وصف الدور |
| status | int | 1=مفعّل, 0=معطّل |
| users_count | int | عدد المستخدمين الذين يملكون هذا الدور |

### 6.2 إنشاء دور

```
POST /admin/role
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| name | string | نعم | max:50 | اسم الدور |
| slug | string | نعم | max:50 | معرّف الدور |
| description | string | لا | | وصف الدور، الافتراضي سلسلة فارغة |
| status | int | لا | | الحالة، الافتراضي 1 |
| permission_ids | array{int} | لا | | مصفوفة معرفات الصلاحيات (معرفات INT أصلية، ليست hashid) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 تحديث الدور

```
PUT /admin/role/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| name | string | لا | اسم الدور |
| description | string | لا | الوصف |
| status | int | لا | 0=معطّل, 1=مفعّل |
| permission_ids | array{int} | لا | مصفوفة معرفات الصلاحيات، عند إرسالها تتم مزامنة (تغطية) صلاحيات الدور |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 حذف الدور

```
DELETE /admin/role/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور مرتين

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

عند الحذف تُفك تلقائيًا علاقات الدور بجميع الصلاحيات والمستخدمين، ثم يُحذف سجل الدور حذفًا فيزيائيًا.

## 7. إدارة الصلاحيات

تعتمد الصلاحيات بنية شجرية (parent_id إحالة ذاتية) وتنقسم إلى ثلاثة أنواع. تُرجع واجهة القائمة شجرة الصلاحيات الكاملة.

### 7.1 شجرة الصلاحيات

```
GET /admin/permission
```

- **المصادقة**: JWT + RBAC

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | مشفّر بـ hashid |
| parent_id | string | hashid للصلاحية الأم، `"0"` تعني عقدة جذرية |
| name | string | اسم الصلاحية |
| slug | string | معرّف الصلاحية (معرّف المسار/الزر) |
| type | int | 1=قائمة, 2=زر, 3=واجهة |
| icon | string | أيقونة القائمة (اسم أيقونة Material) |
| path | string | مسار توجيه الواجهة الأمامية |
| sort | int | قيمة الترتيب (تصاعدي) |
| children | array? | قائمة الصلاحيات الفرعية (تكراري)، لا يُتضمن هذا الحقل عند عدم وجود عقد فرعية |

### 7.2 إنشاء صلاحية

```
POST /admin/permission
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| parent_id | int | لا | | معرف الصلاحية الأم (نوع INT أصلي)، الافتراضي 0 |
| name | string | نعم | max:50 | اسم الصلاحية |
| slug | string | نعم | max:100 | معرّف الصلاحية |
| type | int | نعم | in:1,2,3 | 1=قائمة, 2=زر, 3=واجهة |
| icon | string | لا | | أيقونة القائمة، الافتراضي فارغ |
| path | string | لا | | مسار توجيه الواجهة الأمامية، الافتراضي فارغ |
| sort | int | لا | | قيمة الترتيب، الافتراضي 0 |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 تحديث الصلاحية

```
PUT /admin/permission/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| name | string | لا | اسم الصلاحية |
| icon | string | لا | الأيقونة |
| path | string | لا | مسار التوجيه |
| sort | int | لا | قيمة الترتيب |

### 7.4 حذف الصلاحية

```
DELETE /admin/permission/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور مرتين

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

عند الحذف تُحذف جميع الصلاحيات الفرعية بالتعاقب (السجلات التي يكون `parent_id` فيها مساويًا لمعرف الصلاحية الحالية)، مع فك الارتباط بجميع الأدوار.

## 8. إعدادات النظام

تتفرّد إعدادات النظام عبر مجموعة `group` + `key` معًا.

### 8.1 قائمة الإعدادات

```
GET /admin/config
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |
| group | string | لا | | فلترة حسب مجموعة الإعدادات |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | hashid |
| group | string | مجموعة الإعدادات (مثل `system` و`email` و`storage`) |
| key | string | مفتاح الإعداد |
| value | string | قيمة الإعداد |
| type | string | تلميح نوع القيمة (`string` و`integer` و`boolean` و`json` وغيرها) |
| description | string | وصف الإعداد |

### 8.2 إنشاء إعداد

```
POST /admin/config
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| group | string | نعم | max:100 | مجموعة الإعدادات |
| key | string | نعم | max:100 | مفتاح الإعداد (فريد داخل نفس المجموعة) |
| value | string | نعم | | قيمة الإعداد |
| type | string | لا | | نوع القيمة، الافتراضي `string` |
| description | string | لا | | وصف الإعداد، الافتراضي فارغ |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**الأخطاء المحتملة**:
- 422: عنصر الإعداد موجود بالفعل (نفس group + key)

### 8.3 تحديث الإعداد

```
PUT /admin/config/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| value | string | لا | تحديث قيمة الإعداد |
| type | string | لا | تحديث نوع القيمة |
| description | string | لا | تحديث نص الوصف |

### 8.4 حذف الإعداد

```
DELETE /admin/config/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور مرتين

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

يُحذف سجل الإعداد حذفًا فيزيائيًا.

## 9. سجلات العمليات

سجلات العمليات واجهة للقراءة فقط، يكتبها وسيط `OperationLog` تلقائيًا عند كل طلب POST/PUT/DELETE، وتشمل حقول التخزين `user_id` و`action` و`method` و`path` و`ip` و`source` و`input`.

### 9.1 قائمة سجلات العمليات

```
GET /admin/log
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |
| user_id | int | لا | | فلترة دقيقة حسب معرف المستخدم (نوع INT أصلي) |
| action | string | لا | | فلترة دقيقة حسب إجراء العملية |
| path | string | لا | | فلترة ضبابية حسب مسار الطلب |
| start_date | string | لا | | تاريخ البداية (تنسيق Y-m-d) |
| end_date | string | لا | | تاريخ النهاية (تنسيق Y-m-d) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | hashid |
| user_name | string | اسم مستخدم العملية (يُجلب عبر ارتباط user، وتُعرض العمليات غير المسجلة الدخول باسم «النظام») |
| action | string | وصف إجراء العملية |
| method | string | طريقة HTTP (POST/PUT/DELETE) |
| path | string | مسار الطلب |
| ip | string | عنوان IP للعميل |
| source | string | مصدر الطلب |
| input | string | سلسلة JSON لمعاملات الطلب (بدون ملفات) |
| created_at | string | وقت العملية (datetime) |

## 10. الملف الشخصي

تتطلب واجهات الملف الشخصي مصادقة JWT فقط (لا تتطلب تحقق صلاحيات RBAC — يجب إدراجها في القائمة البيضاء داخل وسيط `AdminPermission`).

### 10.1 تحديث المعلومات الشخصية

```
PUT /admin/profile
```

- **المصادقة**: JWT

**جسم الطلب**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| real_name | string | لا | الاسم الحقيقي |
| phone | string | لا | رقم الهاتف (مشفر التخزين عبر Encryptable) |
| email | string | لا | البريد الإلكتروني (مشفر التخزين عبر Encryptable) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

في الاستجابة تُرجع `phone` و`email` بالنص الصريح، بينما تُستبعد `password` و`id_card`.

### 10.2 تغيير كلمة المرور

```
PUT /admin/profile/password
```

- **المصادقة**: JWT

**جسم الطلب**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| old_password | string | نعم | | كلمة المرور الحالية |
| new_password | string | نعم | min:6, max:32 | كلمة المرور الجديدة |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**الأخطاء المحتملة**:
- 422: يُرجى إدخال كلمة المرور القديمة والجديدة
- 422: كلمة المرور القديمة خاطئة
- 422: طول كلمة المرور الجديدة 6-32 حرفًا

### 10.3 تسجيل الخروج

```
POST /admin/profile/logout
```

- **المصادقة**: JWT

**جسم الطلب**: لا يوجد (بدون requestBody، يُقرأ الرمز من ترويسة Authorization)

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

منطق تسجيل الخروج: فك ترميز JWT للحصول على الصلاحية المتبقية (exp - now)، ثم كتابة تجزئة md5 لهذا الرمز في القائمة السوداء لـ Redis `jwt_blacklist:{md5}`، وTTL = الصلاحية المتبقية. تُحظر الرموز الموجودة في القائمة السوداء داخل وسيط `AdminAuth` وتُرجع 401.

عند عدم وجود رمز يُرجع 401. وعند انتهاء صلاحية الرمز/عدم صحته (فك الترميز يرمي استثناء) يُعتبر تسجيل الخروج ناجحًا.

## 11. الاستيراد والتصدير

### 11.1 تصدير Excel

```
POST /admin/export/excel
```

- **المصادقة**: JWT + RBAC
- **نوع الاستجابة**: تنزيل ملف (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**جسم الطلب**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| الحقل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| table | string | لا | `admin_user` | اسم الجدول للتصدير. المدعوم: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | لا | | مصفوفة أسماء أعمدة التصدير، عند الفراغ تُصدَّر جميع أعمدة الجدول |
| conditions | object | لا | `{}` | شروط الفلترة، أزواج key-value، تُستخدم في WHERE عندما لا تكون القيمة فارغة |
| title | string | لا | `数据导出` | عنوان Excel (يُعرض كاسم ورقة Sheet) |

**الجداول والأعمدة المدعومة**:

| table | الأعمدة المتاحة |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

تُعالَج الحقول الحساسة `phone` و`email` و`id_card` بإخفاء تلقائي عند التصدير. حد البيانات 10000 سطر. أول صف من Excel مجمّد مع فلترة تلقائية.

### 11.2 تصدير PDF

```
POST /admin/export/pdf
```

- **المصادقة**: JWT + RBAC
- **نوع الاستجابة**: تنزيل ملف (`application/pdf`، A4 أفقي)

**جسم الطلب**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

أو وضع الجدول:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| الحقل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| type | string | لا | `table` | نوع التصدير: `table` / `dashboard` |
| title | string | لا | `数据导出` | عنوان PDF |
| data | object | لا | `{}` | بيانات التصدير |

عند `type=dashboard` يجب أن يتضمن `data` مصفوفة `stats` (تُعرض كبطاقات)؛ وعند `type=table` يجب أن يتضمن `data` مصفوفتي `columns` و`rows`.

يتضمن قالب PDF معلومات حقوق النشر وطابع وقت التصدير.

### 11.3 استيراد المستخدمين (Excel)

```
POST /admin/import/users
```

- **المصادقة**: JWT + RBAC
- **نوع الطلب**: `multipart/form-data` (رفع ملف)

**حقول النموذج**:

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| file | file | نعم | تنسيق `.xlsx` أو `.xls` |

**متطلبات أعمدة Excel**:

| اسم العمود | إلزامي | الوصف |
|------|------|------|
| username | نعم | اسم المستخدم (فريد) |
| password | نعم | كلمة المرور (مخزنة بتجزئة bcrypt) |
| real_name | نعم | الاسم الحقيقي |
| phone | لا | رقم الهاتف |
| email | لا | البريد الإلكتروني |
| status | لا | الحالة، الافتراضي 1 |

السطر 1 هو عنوان الأعمدة (غير حساس لحالة الأحرف)، ومن السطر 2 تبدأ البيانات.

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| الحقل | النوع | الوصف |
|------|------|------|
| total | int | إجمالي عدد الأسطر (بدون سطر العنوان) |
| success | int | عدد المستوردين بنجاح |
| failed | int | عدد الأسطر الفاشلة |
| errors | array | تفاصيل الفشل، كل عنصر يتضمن row (رقم السطر في Excel) وreason (سبب الفشل) |

## 12. رفع الملفات

```
POST /admin/upload
```

- **المصادقة**: JWT + RBAC
- **نوع الطلب**: `multipart/form-data`

**حقول النموذج**:

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| file | file | نعم | الملف المرفوع |

**أنواع الملفات المسموحة**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**الحد الأقصى لحجم الملف**: 10MB

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

تُخزَّن الملفات في مجلدات حسب التاريخ داخل `public/upload/{Y-m-d}/`، واسم الملف هو `md5(uniqid) + الامتداد الأصلي`. `url` هو مسار نسبي بالنسبة إلى جذر الموقع.

**الأخطاء المحتملة**:
- 422: يُرجى اختيار ملف (لم يُرفع)
- 422: نوع الملف غير مدعوم
- 422: لا يجوز أن يتجاوز حجم الملف 10MB
- 500: فشل رفع الملف (الملف غير صالح)

## 13. ترويسات الاستجابة

تتضمن جميع الواجهات (تُحقن في طبقة الوسائط العامة) الترويسات التالية:

| الترويسة | الوصف |
|----|------|
| `X-RateLimit-Limit` | حد تحديد المعدل (عدد المرات) |
| `X-RateLimit-Remaining` | عدد الطلبات المتبقية |
| `X-RateLimit-Reset` | طابع إعادة تعيين نافذة تحديد المعدل |
| `Retry-After` | تُرجع فقط عند تفعيل تحديد المعدل، عدد الثواني الموصى بالانتظار |
| `X-Content-Type-Options` | `nosniff` (افتراضي من webman، يمنع فحص MIME) |
| `X-Frame-Options` | `DENY` (يوفرها وسيط CORS/الإعدادات الأساسية في webman) |

تفاصيل تحديد المعدل:
- الحد العام الافتراضي: 60 مرة/دقيقة / IP+المسار
- نقطة الدخول `/api/auth/login`: 10 مرات/دقيقة
- نقطة التسجيل `/api/auth/register`: 5 مرات/دقيقة
- استخدام خوارزمية نافذة منزلقة ذرّية في Redis (Lua ZSET) لتجنب سباق TOCTOU
- عند تعذر الوصول إلى Redis يُفعَّل fail open (تمرير الطلبات)، دون حجب الطلبات

## 14. عملية المصادقة

التسلسل الكامل للمصادقة:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + type(click|slider|rotate) + base64 图片 + extra(类型相关数据)
   
2. 用户交互完成验证码操作（点击/拖拽/旋转），客户端收集答案
   
3. 客户端请求 POST /api/captcha/verify
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // 坐标数组
   - type=slider: clicks = 120                   // X 偏移量
   - type=rotate: clicks = 315                   // 旋转角度
    ↓
   服务端:
   a. 从存储读取 captcha:key 数据（TTL 300s）
   b. 按 type 校验答案（click: 欧氏距离 ≤18px / slider: ±4px / rotate: ±5°）
   c. 校验通过 → 写入 Redis `captcha_verified:{key}` = 1 (TTL 300s)
   d. 校验失败 → 返回 422，计数 +1，超过 3 次 key 作废
    ↓
   服务端返回: { valid: true/false }

4. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password(加密), captcha_key }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 检查 captcha_verified:{key} 是否存在 → 422
   c. 删除 captcha_verified:{key}（一次性使用）
   d. 解密密码: EncryptionService::decrypt(password) → 明文
   e. 校验用户凭证 (password_verify) → 401
   f. 检查账号状态 → 403/429
   g. 签发 JWT (access + refresh) → 200
   h. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

5. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

6. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

7. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### بنية JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`، TTL الافتراضي 7200 ثانية (يُتحكم عبر إعداد JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`، TTL الافتراضي 1209600 ثانية (يُتحكم عبر إعداد JWT `refresh_expire`، أي 14 يومًا)

### إدارة الأمان

- تُخزَّن كلمات المرور بتجزئة `PASSWORD_BCRYPT`
- تشفير طبقة نقل كلمة المرور عبر AES-256-CBC-HMAC (تشفير العميل → فك تشفير الخادم)، مع توافق الرجوع للنص الصريح
- الحقول الحساسة (phone, email, id_card) تُشفَّر وتُفك بشفافية في طبقة قاعدة البيانات عبر `erikwang2013/encryptable`
- معرفات طبقة API تُنقل مشفرة عبر `erikwang2013/hashids` لتجنب كشف تسلسل معرفات snowflake الأصلية
- يفحص SecurityFilter عالميًا XSS وحقن SQL واجتياز المسار وحقن الأوامر، ونفس IP 5 مرات/60 ثانية يُضاف مؤقتًا إلى القائمة السوداء لمدة 15 دقيقة
- العمليات الحساسة (حذف المستخدمين والأدوار والصلاحيات والإعدادات) تتطلب تأكيد كلمة مرور المستخدم المسجّل دخوله حاليًا مرتين
- حد الجلسات المتزامنة: 3 رموز نشطة كحد أقصى لنفس المستخدم، وعند دخول الجهاز الرابع يُضاف أقدم رمز قسريًا إلى القائمة السوداء
- قفل الحساب: 5 محاولات دخول فاشلة متتالية تُفعّل قفل الحساب لمدة 15 دقيقة، وتُرجع 429 خلال فترة القفل

### بنية الوسائط

تنفَّذ الوسائط العامة على جميع الطلبات بالترتيب التالي:

```
Cors（跨域预处理 + 响应头）
  → Locale（Accept-Language 语言检测 / ?lang=zh_CN|en）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ApiVersion（API 版本校验，/api 路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权 / Redis 60s 缓存，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health` و`/api/docs` نقطتان طرفيتان عامتان، تمران فقط عبر `Cors → SecurityFilter → RateLimit`.

تحسينات الأمان:
- **قفل الحساب**: 5 محاولات دخول فاشلة متتالية تقفل الحساب تلقائيًا لمدة 15 دقيقة، وتُرجع الدخولات خلالها 429
- **حد الجلسات المتزامنة**: 3 رموز نشطة كحد أقصى لنفس المستخدم، وعند التجاوز يُضاف أقدم رمز تلقائيًا إلى القائمة السوداء
- **security.txt**: يوفّر `GET /.well-known/security.txt` معلومات الاتصال الأمنية وفق معيار RFC 9116
- **إعدادات أمان Nginx**: راجع `docs/nginx-security.conf` للحصول على مثال كامل لتحصين أمان الوكيل العكسي

### كشف مصدر العمليات

يحدد وسيط OperationLog منصة العميل تلقائيًا ويكتبها في حقل `source` لسجل العمليات:

| المنصة | طريقة الكشف |
|------|---------|
| `ipados` | UA يتضمن iPad |
| `macos` | UA يتضمن Macintosh/Mac OS |
| `windows` | UA يتضمن Windows |
| `linux` | UA يتضمن Linux (وليس Android) |
| `ios` | UA يتضمن iPhone / iOS / CFNetwork |
| `android` | UA يتضمن Android |
| `harmonyos` | UA يتضمن HarmonyOS / OpenHarmony أو ترويسة `X-Client-Platform` تُصرّح صراحةً |
| `web` | الافتراضي (لم يُصب أيًا من المنصات أعلاه) |

> كشف بمستويين: ترويسة الطلب `X-Client-Platform` (تصريح تطبيقات العميل الأصلي) ← استنتاج User-Agent تلقائيًا (احتياطي). حقل `source` في استعلام سجلات العمليات `GET /admin/log` هو جهة المصدر.

## 15. النشر والتشغيل

### Docker Compose

يوفر جذر المشروع `docker-compose.yml` بترتيب 5 خدمات (Nginx وتطبيق webman وMySQL وRedis وElasticsearch). يُبنى PHP عبر `Dockerfile` (مبني على `php:8.3-cli`، مع تفعيل OPcache).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

يعرّف `.github/workflows/ci.yml` خط أنابيب التكامل المستمر عبر GitHub Actions:
- فحص بناء الجملة عبر `php -l`
- اختبارات PHPUnit
- تحليل ثابت عبر `flutter analyze`

### النسخ الاحتياطي لقاعدة البيانات

يوفر مجلد `database/backup/` سكربتي النسخ والاستعادة:
- `backup.sh` — نسخ احتياطي مضغوط mysqldump + gzip، مع تنظيف تلقائي للملفات الأقدم من 30 يومًا
- `restore.sh` — استعادة تفاعلية، تعرض النسخ الاحتياطية الموجودة لاختيار المستخدم

### إعدادات أمان Nginx

للنشر في بيئة الإنتاج، راجع `docs/nginx-security.conf` لتهيئة تحصين أمان الوكيل العكسي.

## 16. واجهة gRPC الداخلية (e-cat → PHP worker)

للاستخدام من قبل بوابة الاستعلام e-cat فقط. على جانب PHP هي خدمة workerman h2c (`tcp://0.0.0.0:8792`, العملية `internal_grpc`). العقد معرَّف في `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`), الرسائل مشفرة بـ protobuf ويجب أن تحمل metadata قيمة `x-internal-token` (القيمة من `config/logistics.php` — `internal_token`, وفي الإنتاج تُستبدل بمتغير البيئة `INTERNAL_TOKEN`). في حال غياب/خطأ التوكن يُرجع `code=401 UNAUTHORIZED`.

### 16.1 نظرة عامة على RPC

| RPC | الطلب | الاستجابة | الوصف |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | تحديد الناقل؛ عند عدم التعرف يُرجع `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | استعلام التتبع؛ `credential_id` اختياري (يُحدد في سيناريوهات الاعتمادات المتعددة، يجب أن يطابق `carrier_code` وإلا `400 INVALID_PARAMS`). يُسجَّل كل استعلام في `logistics_tracking_query` |
| `Carriers` | `{}` | `{code, carriers[]}` | جميع الناقلين في السجل `{carrier_code, channel}` |

### 16.2 عقد الأخطاء (حقل `code` في الاستجابة، حالة gRPC تساوي 0 دائمًا)

| `code` | `error_code` | السيناريو |
|---|---|---|
| 400 | `INVALID_PARAMS` | معاملات ناقصة / عدم تطابق credential_id |
| 401 | `UNAUTHORIZED` | التوكن الداخلي خاطئ |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | الناقل غير مسجَّل / التتبع غير موجود / لا يمكن تحديده |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | خطأ اعتمادات الناقل / خطأ شبكة / خطأ تجاري للناقل (يتضمن رسالة منظمة من المزود، مثل `[SF-INTERNATIONAL A1001] المعاملات الإلزامية لا يمكن أن تكون فارغة`) |
| 500 | `INTERNAL_ERROR` | استثناء داخلي |


## 17. الواجهة البرمجية الخارجية لبوابة الاستعلام e-cat

بوابة عالية التردد بلغة Rust (`infrastructure/tracking-gateway`), تستمع على `0.0.0.0:8080` (الافتراضي), الطلبات تحمل رأس `X-API-Key` (الإعداد `api_keys`). تتولى البوابة مصادقة API-Key, وضربات كاش Redis (البادئة `logistics:`), وتحديد المعدل, وقاطع الدائرة لكل ناقل, وإعادة التوجيه الدائري للعمال (RoundRobin)؛ لا تصل الاعتمادات إلى البوابة أبدًا.

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | فحص الصحة (نقطة عامة، لا حاجة لمفتاح) |
| `GET /v1/openapi.json` | توثيق OpenAPI 3.0 (نقطة عامة، لا حاجة لمفتاح) |
| `GET /v1/carriers` | قائمة الناقلين (إعادة توجيه إلى gRPC الخاص بـ PHP `InternalService.Carriers`) |
| `POST /v1/tracking/query` | استعلام التتبع (`{"carrier_code": "...", "tracking_no": "..."}`)؛ عند غياب `carrier_code` تحدد البوابة تلقائيًا (يتم detect داخل query) |
| `GET /v1/tracking/{query_no}` | آخر نتيجة تتبع حسب رقم الاستعلام |
| `POST /v1/subscriptions` | تسجيل اشتراك استدعاء (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` الافتراضي `tracking.update`) |


دلالات إضافية من جانب البوابة: تجاوز `rate_limit` (الافتراضي 100/60 ثانية) يُرجع `429`؛ فشل الناقل المتتالي يفعّل قاطع الدائرة (الافتراضي OPEN لمدة 60 ثانية بعد 5 أخطاء 5xx/نقل) → `503`؛ العامل غير متاح → `502`. بين البوابة والعامل gRPC داخلي (h2c, `0.0.0.0:8792`) بمصادقة المفتاح المشترك `x-internal-token`, انظر القسم 16.

### 17.1 غلاف الاستجابة والأخطاء

تعيد جميع النقاط الغلاف الموحد `{"code": 0, "message": "ok", "data": ...}`؛ `code != 0` يعني خطأ ويُحذف `data`. قد يحمل جسم الخطأ التجاري `error_code` / `error_message` (تشخيص الناقل من المزود، تمرره البوابة كما هو)؛ `code` يطابق حالة HTTP:

| HTTP | المعنى |
|---|---|
| 400 | خطأ معاملات (`tracking_no` مفقود / معاملات اشتراك غير صالحة) |
| 401 | المفتاح مفقود أو غير صالح |
| 404 | رقم الاستعلام غير موجود |
| 429 | تحديد معدل (الافتراضي 100/60 ثانية لكل مفتاح)، أعد المحاولة مع التراجع |
| 502 | العامل غير متاح أو فشل من المزود |
| 503 | قاطع دائرة الناقل، أعد المحاولة مع التراجع |

### 17.2 أمثلة الاستدعاء (curl)

مفتاح تجريبي `demo-api-key`, البوابة الافتراضية `http://127.0.0.1:8080`:

```bash
# فحص الصحة (نقطة عامة، لا حاجة لمفتاح)
curl http://127.0.0.1:8080/v1/health

# توثيق OpenAPI (نقطة عامة، لا حاجة لمفتاح)
curl http://127.0.0.1:8080/v1/openapi.json

# قائمة الناقلين
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# استعلام التتبع (تحديد الناقل اختياري)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# آخر نتيجة حسب رقم الاستعلام
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# تسجيل اشتراك الاستدعاء (عنوان الاستدعاء يجب أن يكون http/https عامًا؛ البوابة ترفض الحلقة/الشبكات الخاصة)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 أمثلة الاستدعاء (SDK)

خمس حزم SDK بلا تبعيات في دليل `sdk/` بالمستودع (Python / PHP / Node.js / Go / Rust, انسخ وشغّل):

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
print(result["status"], result["events"][0]["description"])
```

```php
require "TrackingClient.php";
$client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
$carriers = $client->list_carriers();
$sub = $client->subscribe("china-post", "https://example.com/cb");
```

```js
const { TrackingClient } = require("./tracking-client.js");
const client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
const detail = await client.getTracking("query_no");
console.log(detail.status);
```

```go
import "trackingclient"
client := trackingclient.NewClient("demo-api-key", "http://127.0.0.1:8080")
raw, err := client.QueryTracking("LX123456789CN", "china-post")
if err != nil { panic(err) }
fmt.Println(string(raw))
```

```rust
use trackingclient::tracking_client::TrackingClient;
let client = TrackingClient::new("demo-api-key", "http://127.0.0.1:8080")?;
let detail = client.get_tracking("query_no")?;
println!("{}", detail);
```

تُلقي حزم SDK استثناءً (`TrackingApiError`) عند `code != 0` في الغلاف أو فشل الشبكة، يحمل `code` / `message` / `error_code` / `error_message` / `http_status`؛ في أخطاء تحديد المعدل وقاطع الدائرة أعد المحاولة مع التراجع، ولا تعِد المحاولة لأخطاء جانب الناقل. الاستخدام الكامل في `sdk/README.md`.
