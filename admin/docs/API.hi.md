# API संदर्भ दस्तावेज़

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

## 1. अवलोकन

open-admin (ओपन एडमिन पैनल) webman v2 पर आधारित है और RESTful JSON API प्रदान करता है। सभी एडमिन साइड इंटरफ़ेस के लिए JWT प्रमाणीकरण और RBAC अनुमति सत्यापन आवश्यक है; सार्वजनिक इंटरफ़ेस API संस्करण हेडर के माध्यम से संस्करण-नियंत्रित कंट्रोलर में रूट होते हैं।

- **बेस URL**: `http://localhost:8787`
- **भाषा**: `Accept-Language` हेडर या `?lang=zh_CN|en` पैरामीटर से स्विच (डिफ़ॉल्ट zh_CN), Locale मिडलवेयर स्वचालित रूप से पहचान करता है

> **एंडपॉइंट अवलोकन**: प्रमाणीकरण(5) | डैशबोर्ड(1) | उपयोगकर्ता(7) | भूमिका(4) | अनुमति(4) | कॉन्फ़िग(4) | लॉग(1) | प्रोफ़ाइल केंद्र(3) | आयात-निर्यात(3) | अपलोड(1) | संचालन(4: health/metrics/docs/security.txt) | कुल 37 एंडपॉइंट
- **प्रमाणीकरण**: `Authorization: Bearer <token>` (JWT)
- **रिस्पॉन्स फॉर्मेट**: `{ "code": 0, "message": "success", "data": {...} }`
- **दस्तावेज़ एंडपॉइंट**: `GET /api/docs` OpenAPI 3.0 JSON स्पेकिफिकेशन लौटाता है

### रिक्वेस्ट आवश्यकताएँ

- केवल `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` मेथड की अनुमति है; अन्य HTTP मेथड (जैसे TRACE, CONNECT, PATCH) उपयोग करने पर 405 लौटता है
- सभी `POST` / `PUT` रिक्वेस्ट में `Content-Type: application/json` सेट होना चाहिए (फ़ाइल अपलोड को छोड़कर), अन्यथा 415 लौटता है
- रिक्वेस्ट बॉडी का आकार 10MB से अधिक नहीं हो सकता, अन्यथा 413 लौटता है
- सुरक्षा फ़िल्टर सभी रिक्वेस्ट इनपुट की XSS, SQL इंजेक्शन, पाथ ट्रैवर्सल, कमांड इंजेक्शन स्कैन करता है; हिट होने पर 403 लौटता है
- लगातार 5 बार लॉगिन विफल होने पर खाता लॉक ट्रिगर होता है (15 मिनट); लॉक अवधि के दौरान लॉगिन रिक्वेस्ट 429 लौटाती है
- एक ही उपयोगकर्ता अधिकतम 3 मान्य टोकन एक साथ रख सकता है; अधिक होने पर सबसे पुराना टोकन स्वचालित रूप से ब्लैकलिस्ट में जाता है

## 2. एरर कोड

| code | अर्थ | ट्रिगर परिदृश्य |
|------|------|---------|
| 0 | सफल | |
| 400 | रिक्वेस्ट पैरामीटर त्रुटि | रिक्वेस्ट फॉर्मेट सही नहीं |
| 401 | प्रमाणीकरण नहीं | टोकन अनुपस्थित / समाप्त / ब्लैकलिस्ट में |
| 403 | अनुमति नहीं / सुरक्षा इंटरसेप्ट | RBAC अनुमति अपर्याप्त / SecurityFilter हिट |
| 404 | संसाधन मौजूद नहीं | क्वेरी/अपडेट/डिलीट का लक्ष्य मौजूद नहीं |
| 405 | रिक्वेस्ट मेथड की अनुमति नहीं | केवल GET/POST/PUT/DELETE/OPTIONS/HEAD; गैर-मानक मेथड सीधे अस्वीकृत |
| 413 | रिक्वेस्ट बॉडी बहुत बड़ी | Content-Length 10MB से अधिक |
| 415 | असमर्थित मीडिया टाइप | POST/PUT रिक्वेस्ट का Content-Type JSON नहीं और फ़ाइल अपलोड नहीं |
| 422 | पैरामीटर सत्यापन विफल | अनिवार्य फ़ील्ड अनुपस्थित, फॉर्मेट गलत, बिज़नेस सत्यापन पास नहीं |
| 429 | रिक्वेस्ट बहुत बार-बार | RateLimit ट्रिगर / खाता लॉक (लगातार 5 बार लॉगिन विफल, 15 मिनट लॉक) |
| 500 | सर्वर आंतरिक त्रुटि | |

## 3. सार्वजनिक एंडपॉइंट

सभी सार्वजनिक एंडपॉइंट `/api` ग्रुप में माउंट होते हैं, `ApiVersion` मिडलवेयर `API-Version` हेडर के अनुसार इन्हें संबंधित संस्करण-नियंत्रित कंट्रोलर (जैसे `app\api\v1\controller\AuthController`) में वितरित करता है।

### 3.1 स्वास्थ्य जाँच

```
GET /health
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: नहीं

**रिस्पॉन्स उदाहरण**:
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

`database`、`redis`、`elasticsearch` के मान: `"ok"` | `"unavailable"`। `elasticsearch` ES अनुपलब्ध होने पर `"unavailable"` लौटाता है; क्लस्टर स्वास्थ्य स्थिति green/yellow नहीं होने पर वास्तविक status मान लौटाता है (जैसे `"red"`)।

### 3.2 API दस्तावेज़

```
GET /api/docs
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: ग्लोबल डिफ़ॉल्ट (60 बार/मिनट)
- **रिस्पॉन्स**: OpenAPI 3.0.3 JSON स्पेकिफिकेशन, सभी एंडपॉइंट परिभाषाओं, पैरामीटर और Schema सहित

### 3.3 कैप्चा जनरेट करें

```
POST /api/v1/captcha/generate
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: ग्लोबल डिफ़ॉल्ट (60 बार/मिनट)

**रिक्वेस्ट बॉडी**:
```json
{
  "difficulty": "medium"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| difficulty | string | नहीं | `easy` / `medium` / `hard`, डिफ़ॉल्ट `medium` |

**रिस्पॉन्स उदाहरण** — क्लिक प्रकार (`type: "click"`):
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

**रिस्पॉन्स उदाहरण** — स्लाइडर प्रकार (`type: "slider"`):
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

**रिस्पॉन्स उदाहरण** — रोटेट प्रकार (`type: "rotate"`):
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| key | string | कैप्चा पहचानकर्ता, सत्यापन के समय वापस भेजा जाता है |
| type | string | कैप्चा प्रकार: `click` / `slider` / `rotate` |
| image | string | base64 data URI छवि |
| extra | object | प्रकार-संबंधित अतिरिक्त डेटा (नीचे देखें) |

**`extra` प्रकार-वार विवरण**:

| type | extra फ़ील्ड | प्रकार | विवरण |
|------|-----------|------|------|
| click | targets | array | क्लिक लक्ष्य, इसमें `order`(क्रम) `text`(संकेत टेक्स्ट) `x` `y`(निर्देशांक) |
| slider | x, y | int | गैप का ऊपरी-बाएँ कोने के निर्देशांक (300×200 कैनवास पर आधारित) |
| slider | puzzle_w, puzzle_h | int | पहेली छवि की चौड़ाई-ऊँचाई |
| slider | puzzle | string | पहेली छवि base64 data URI |
| rotate | angle | int | सही रोटेशन कोण (0-359), छवि सीधी करने के लिए `360-angle` रोटेट करना होगा |

### 3.4 कैप्चा सत्यापित करें

```
POST /api/v1/captcha/verify
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: ग्लोबल डिफ़ॉल्ट (60 बार/मिनट)

**रिक्वेस्ट बॉडी** — क्लिक प्रकार (`type: "click"`):
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

**रिक्वेस्ट बॉडी** — स्लाइडर प्रकार (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**रिक्वेस्ट बॉडी** — रोटेट प्रकार (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| key | string | हाँ | कैप्चा key, generate द्वारा लौटाया गया |
| type | string | हाँ | कैप्चा प्रकार, generate द्वारा लौटाए गए `type` के समान होना चाहिए |
| clicks | वैरिएंट | हाँ | उत्तर डेटा, फॉर्मेट type के अनुसार बदलता है (नीचे देखें) |

**`clicks` प्रकार-वार विवरण**:

| type | clicks प्रकार | विवरण | त्रुटि सहनशीलता |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | क्लिक निर्देशांक एरे, order क्रम में | 18px त्रिज्या |
| slider | `int` | स्लाइडर X-अक्ष ऑफ़सेट | ±4px |
| rotate | `int` | रोटेशन कोण (0-359) | ±5° |

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

सत्यापन पास होने के बाद, बैकएंड `captcha_verified:{key}` को Redis में लिखता है (TTL 300s), लॉगिन इंटरफ़ेस इसी के आधार पर आगे बढ़ने देता है।
सत्यापन विफल होने पर `code` 422 होता है, `message` `"验证失败，请重试"` होता है, और `data.valid` `false` होता है।

### 3.5 लॉगिन

```
POST /api/v1/auth/login
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: 10 बार/मिनट (IP + पाथ के अनुसार)

**रिक्वेस्ट बॉडी**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम |
| password | string | हाँ | min:6, max:32 (प्लेनटेक्स्ट) | AES-256-CBC-HMAC एन्क्रिप्शन के बाद Base64 एन्कोडिंग (प्लेनटेक्स्ट के साथ संगत) |
| captcha_key | string | हाँ | | कैप्चा key (पहले `/api/v1/captcha/verify` से सत्यापित करना आवश्यक) |

### पासवर्ड एन्क्रिप्शन प्रोटोकॉल

**RSA-2048 असममित एन्क्रिप्शन** का उपयोग होता है; पब्लिक कुंजी फ्रंटएंड कोड में रखी जाती है (सुरक्षित रूप से उजागर की जा सकती है), प्राइवेट कुंजी केवल सर्वर साइड के पास होती है।

```
एन्क्रिप्शन प्रवाह (क्लाइंट):
  RSA पब्लिक कुंजी (PEM) → PKCS1v1.5 एन्क्रिप्शन → Base64 एन्कोडिंग → ट्रांसमिशन

डिक्रिप्शन प्रवाह (सर्वर साइड, स्तर-दर-स्तर फॉलबैक):
  1. RSA प्राइवेट कुंजी डिक्रिप्ट → सफल और मान्य UTF-8 → डिक्रिप्ट किया गया परिणाम उपयोग करें
  2. AES-256-CBC-HMAC डिक्रिप्ट → सफल → डिक्रिप्ट किया गया परिणाम उपयोग करें (पुराने क्लाइंट संगतता)
  3. प्लेनटेक्स्ट फॉलबैक → सीधे मूल इनपुट उपयोग करें
```

पब्लिक कुंजी फ्रंटएंड ऐप्लिकेशन में अंतर्निहित होती है, नेटवर्क पर ट्रांसमिट करने की आवश्यकता नहीं। प्राइवेट कुंजी केवल `.env` के `RSA_PRIVATE_KEY` में संग्रहीत होती है, लीक नहीं हो सकती।

> AES सममित एन्क्रिप्शन पुराने संस्करण संगतता के लिए है; सभी क्लाइंट RSA में माइग्रेट हो जाने के बाद हटा दिया जाएगा।

**रिस्पॉन्स उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| access_token | string | JWT एक्सेस टोकन |
| refresh_token | string | JWT रीफ़्रेश टोकन |
| expires_in | int | एक्सेस टोकन वैधता अवधि (सेकंड), डिफ़ॉल्ट 7200 |
| user.id | string | hashid एन्क्रिप्टेड उपयोगकर्ता ID |
| user.username | string | उपयोगकर्ता नाम |
| user.real_name | string | वास्तविक नाम |

**संभावित त्रुटियाँ**:
- 422: पैरामीटर सत्यापन विफल (अनिवार्य फ़ील्ड अनुपस्थित, फॉर्मेट गलत)
- 422: कृपया पहले कैप्चा सत्यापन पूरा करें (captcha_key `/api/v1/captcha/verify` से पास नहीं हुआ)
- 401: उपयोगकर्ता नाम या पासवर्ड गलत
- 403: खाता अक्षम किया जा चुका है
- 429: खाता लॉक हो चुका है, कृपया 15 मिनट बाद प्रयास करें (लगातार 5 बार लॉगिन विफल होने पर ट्रिगर)

### 3.6 रजिस्ट्रेशन

```
POST /api/v1/auth/register
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: 5 बार/मिनट (IP + पाथ के अनुसार)

**रिक्वेस्ट बॉडी**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम (अद्वितीय) |
| password | string | हाँ | min:6, max:32 (प्लेनटेक्स्ट) | AES-256-CBC-HMAC एन्क्रिप्शन के बाद Base64 एन्कोडिंग |
| real_name | string | हाँ | max:50 | वास्तविक नाम |
| captcha_key | string | हाँ | | कैप्चा key (पहले `/api/v1/captcha/verify` से सत्यापित करना आवश्यक) |

**रिस्पॉन्स उदाहरण**:
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

रजिस्ट्रेशन सफल होने पर सीधे JWT टोकन लौटता है; उपयोगकर्ता स्थिति डिफ़ॉल्ट रूप से सक्षम होती है (status=1)।

### 3.7 टोकन रीफ़्रेश

```
POST /api/v1/auth/refresh
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: ग्लोबल डिफ़ॉल्ट (60 बार/मिनट)

**रिक्वेस्ट बॉडी**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| refresh_token | string | हाँ | लॉगिन/रजिस्ट्रेशन के समय प्राप्त refresh_token |

**रिस्पॉन्स उदाहरण**:
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

रीफ़्रेश सफल होने पर नया access_token और refresh_token दोनों लौटते हैं, पुराने टोकन स्वचालित रूप से अमान्य हो जाते हैं। रीफ़्रेश के समय उपयोगकर्ता का अंतिम लॉगिन समय और IP अपडेट होता है।

**संभावित त्रुटियाँ**:
- 422: रीफ़्रेश टोकन अनुपस्थित
- 401: रीफ़्रेश टोकन अमान्य या समाप्त

### 3.8 Prometheus मॉनिटरिंग मेट्रिक्स

```
GET /metrics
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **रेट लिमिट**: नहीं
- **रिस्पॉन्स फॉर्मेट**: Prometheus text format (`text/plain; version=0.0.4`)

सार्वजनिक Prometheus मॉनिटरिंग मेट्रिक्स एंडपॉइंट, Grafana/Prometheus द्वारा स्क्रेप करने हेतु।

**रिस्पॉन्स उदाहरण**:
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

| मेट्रिक नाम | प्रकार | विवरण |
|------|------|------|
| `openadmin_http_requests_total` | gauge | संचयी HTTP रिक्वेस्ट की कुल संख्या |
| `openadmin_active_users` | gauge | वर्तमान सक्रिय उपयोगकर्ता संख्या (24 घंटे में लॉगिन) |
| `openadmin_db_connection_status` | gauge | डेटाबेस कनेक्शन स्थिति, 1=सामान्य, 0=असामान्य |
| `openadmin_redis_connection_status` | gauge | Redis कनेक्शन स्थिति, 1=सामान्य, 0=असामान्य |
| `openadmin_memory_usage_bytes` | gauge | PHP प्रोसेस की वर्तमान मेमोरी उपयोग (bytes) |

## 4. डैशबोर्ड

सभी एडमिन साइड इंटरफ़ेस `/admin` ग्रुप में माउंट होते हैं और तीन मिडलवेयर से गुजरते हैं: `AdminAuth` (JWT प्रमाणीकरण), `AdminPermission` (RBAC अनुमति सत्यापन), `OperationLog` (ऑपरेशन रिकॉर्डिंग)।

### 4.1 डैशबोर्ड डेटा

```
GET /admin/dashboard
```

- **प्रमाणीकरण**: JWT + RBAC
- **कैश**: Redis 5 मिनट

**रिस्पॉन्स उदाहरण**:
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
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| label | string | मेट्रिक नाम |
| value | string | मेट्रिक मान (स्ट्रिंग प्रकार) |
| icon | string | Material आइकन नाम |
| color | string | कार्ड रंग मान |
| trend | float? | दैनिक चक्र वृद्धि दर (प्रतिशत), केवल "कुल उपयोगकर्ता" में यह फ़ील्ड होता है |

| trends फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| dates | array{string} | पिछले 30 दिनों की तिथि श्रृंखला |
| series | array{object} | ट्रेंड लाइन डेटा, प्रत्येक में name (नाम), data (मान एरे), color (रंग) |

## 5. उपयोगकर्ता प्रबंधन

सभी उपयोगकर्ता प्रबंधन इंटरफ़ेस द्वारा लौटाया गया `id` hashid एन्क्रिप्टेड स्ट्रिंग है। पासवर्ड फ़ील्ड रिस्पॉन्स से हटा दिया गया है। फ़ोन नंबर और ईमेल सूची इंटरफ़ेस में मास्क करके दिखाए जाते हैं, विवरण इंटरफ़ेस में प्लेनटेक्स्ट लौटते हैं (डेटाबेस एन्क्रिप्टेड फ़ील्ड Encryptable trait द्वारा स्वचालित रूप से डिक्रिप्ट होते हैं)।

### 5.1 उपयोगकर्ता सूची

```
GET /admin/user
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पेज संख्या |
| limit | int | नहीं | 15 | प्रति पेज संख्या |
| keyword | string | नहीं | | सर्च कीवर्ड, उपयोगकर्ता नाम और वास्तविक नाम से मिलान |
| status | int | नहीं | | स्थिति फ़िल्टर, 0=अक्षम, 1=सक्षम |

**रिस्पॉन्स उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड उपयोगकर्ता ID |
| username | string | उपयोगकर्ता नाम |
| real_name | string | वास्तविक नाम |
| phone | string | मास्क किया गया फ़ोन नंबर (`138****5678` फॉर्मेट) |
| email | string | मास्क किया गया ईमेल (`a***@example.com` फॉर्मेट) |
| status | int | 1=सक्षम, 0=अक्षम |
| last_login_at | string | अंतिम लॉगिन समय (datetime) |
| created_at | string | निर्माण समय (datetime) |

### 5.2 उपयोगकर्ता बनाएँ

```
POST /admin/user
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
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

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम (अद्वितीय) |
| password | string | हाँ | min:6, max:32 | पासवर्ड (bcrypt संग्रहित) |
| real_name | string | हाँ | max:50 | वास्तविक नाम |
| phone | string | नहीं | | फ़ोन नंबर (Encryptable एन्क्रिप्शन संग्रहित) |
| email | string | नहीं | | ईमेल (Encryptable एन्क्रिप्शन संग्रहित) |
| status | int | नहीं | in:0,1 | स्थिति, डिफ़ॉल्ट 1 (सक्षम) |

**रिस्पॉन्स उदाहरण**:
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

**संभावित त्रुटियाँ**:
- 422: उपयोगकर्ता नाम पहले से मौजूद है
- 422: पैरामीटर सत्यापन विफल (अनिवार्य फ़ील्ड अनुपस्थित)

### 5.3 उपयोगकर्ता विवरण

```
GET /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पाथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है

**रिस्पॉन्स उदाहरण**:
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

विवरण इंटरफ़ेस में `phone` और `email` प्लेनटेक्स्ट लौटते हैं (डेटाबेस में एन्क्रिप्टेड संग्रहित, Encryptable cast स्वचालित रूप से डिक्रिप्ट करता है), मास्क नहीं होते। `password` और `id_card` हमेशा रिस्पॉन्स में नहीं होते।

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं

### 5.4 उपयोगकर्ता अपडेट करें

```
PUT /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पाथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है

**रिक्वेस्ट बॉडी**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| real_name | string | नहीं | वास्तविक नाम, भेजने पर पुराना मान बना रहता है |
| password | string | नहीं | नया पासवर्ड, खाली स्ट्रिंग या भेजे न जाने पर संशोधित नहीं होता |
| phone | string | नहीं | फ़ोन नंबर |
| email | string | नहीं | ईमेल |
| status | int | नहीं | 0=अक्षम, 1=सक्षम |

**रिस्पॉन्स उदाहरण**:
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

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं

### 5.5 उपयोगकर्ता हटाएँ

```
DELETE /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पाथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है
- **संवेदनशील ऑपरेशन**: पासवर्ड पुनः पुष्टि आवश्यक

**रिक्वेस्ट बॉडी**:
```json
{
  "password": "admin_password"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| password | string | हाँ | वर्तमान लॉगिन उपयोगकर्ता का पासवर्ड (पुनः पुष्टि) |

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

सॉफ्ट डिलीट किया जाता है (Eloquent SoftDeletes), डेटा में deleted_at चिह्नित होता है, भौतिक रूप से नहीं हटता।

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं
- 422: संवेदनशील ऑपरेशन के लिए पासवर्ड पुष्टि आवश्यक है (password खाली)
- 422: पासवर्ड सत्यापन विफल (पासवर्ड मेल नहीं खाता)

### 5.6 उपयोगकर्ता बैच डिलीट

```
POST /admin/user/batch/destroy
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड पुनः पुष्टि आवश्यक

**रिक्वेस्ट बॉडी**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| ids | array{string} | हाँ | hashid एन्क्रिप्टेड उपयोगकर्ता ID एरे |
| password | string | हाँ | वर्तमान लॉगिन उपयोगकर्ता का पासवर्ड (पुनः पुष्टि) |

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

सॉफ्ट डिलीट किया जाता है, `data.count` वास्तविक हटाई गई संख्या है।

**संभावित त्रुटियाँ**:
- 422: कृपया हटाने के लिए उपयोगकर्ता चुनें (ids खाली)
- 422: अमान्य ID (hashid डिकोड विफल)
- 422: पासवर्ड सत्यापन विफल

### 5.7 उपयोगकर्ता बैच सक्षम/अक्षम

```
POST /admin/user/batch/status
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| ids | array{string} | हाँ | hashid एन्क्रिप्टेड उपयोगकर्ता ID एरे |
| status | int | हाँ | 0=अक्षम, 1=सक्षम |

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message status मान के अनुसार गतिशील रूप से `"批量启用成功"` या `"批量禁用成功"` में बदलता है।

**संभावित त्रुटियाँ**:
- 422: कृपया उपयोगकर्ता चुनें (ids खाली)
- 422: स्थिति मान अमान्य (status 0 या 1 नहीं)

## 6. भूमिका प्रबंधन

### 6.1 भूमिका सूची

```
GET /admin/role
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पेज संख्या |
| limit | int | नहीं | 15 | प्रति पेज संख्या |

**रिस्पॉन्स उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड भूमिका ID |
| name | string | भूमिका का नाम |
| slug | string | भूमिका पहचानकर्ता (अद्वितीय, अनुमति निर्णय हेतु) |
| description | string | भूमिका विवरण |
| status | int | 1=सक्षम, 0=अक्षम |
| users_count | int | इस भूमिका वाले उपयोगकर्ताओं की संख्या |

### 6.2 भूमिका बनाएँ

```
POST /admin/role
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| name | string | हाँ | max:50 | भूमिका का नाम |
| slug | string | हाँ | max:50 | भूमिका पहचानकर्ता |
| description | string | नहीं | | भूमिका विवरण, डिफ़ॉल्ट खाली स्ट्रिंग |
| status | int | नहीं | | स्थिति, डिफ़ॉल्ट 1 |
| permission_ids | array{int} | नहीं | | अनुमति ID एरे (मूल INT ID, hashid नहीं) |

**रिस्पॉन्स उदाहरण**:
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

### 6.3 भूमिका अपडेट करें

```
PUT /admin/role/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| name | string | नहीं | भूमिका का नाम |
| description | string | नहीं | विवरण |
| status | int | नहीं | 0=अक्षम, 1=सक्षम |
| permission_ids | array{int} | नहीं | अनुमति ID एरे, भेजने पर भूमिका अनुमतियाँ सिंक (ओवरराइट) होती हैं |

**रिस्पॉन्स उदाहरण**:
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

### 6.4 भूमिका हटाएँ

```
DELETE /admin/role/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड पुनः पुष्टि आवश्यक

**रिक्वेस्ट बॉडी**:
```json
{
  "password": "admin_password"
}
```

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

हटाते समय भूमिका और सभी अनुमतियों/उपयोगकर्ताओं के बीच संबंध स्वचालित रूप से समाप्त हो जाते हैं, फिर भूमिका रिकॉर्ड भौतिक रूप से हटा दिया जाता है।

## 7. अनुमति प्रबंधन

अनुमति ट्री संरचना (parent_id सेल्फ-रिलेशन) में होती है, तीन प्रकार की होती है। सूची इंटरफ़ेस पूर्ण अनुमति ट्री लौटाता है।

### 7.1 अनुमति ट्री

```
GET /admin/permission
```

- **प्रमाणीकरण**: JWT + RBAC

**रिस्पॉन्स उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड |
| parent_id | string | पैरेंट अनुमति hashid, "0" रूट नोड दर्शाता है |
| name | string | अनुमति का नाम |
| slug | string | अनुमति पहचानकर्ता (रूट/बटन पहचानकर्ता) |
| type | int | 1=मेनू, 2=बटन, 3=इंटरफ़ेस |
| icon | string | मेनू आइकन (Material आइकन नाम) |
| path | string | फ्रंटएंड रूट पाथ |
| sort | int | सॉर्ट मान (आरोही) |
| children | array? | चाइल्ड अनुमति सूची (रिकर्सिव), कोई चाइल्ड नहीं होने पर यह फ़ील्ड शामिल नहीं होती |

### 7.2 अनुमति बनाएँ

```
POST /admin/permission
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
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

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| parent_id | int | नहीं | | पैरेंट अनुमति ID (मूल INT प्रकार), डिफ़ॉल्ट 0 |
| name | string | हाँ | max:50 | अनुमति का नाम |
| slug | string | हाँ | max:100 | अनुमति पहचानकर्ता |
| type | int | हाँ | in:1,2,3 | 1=मेनू, 2=बटन, 3=इंटरफ़ेस |
| icon | string | नहीं | | मेनू आइकन, डिफ़ॉल्ट खाली |
| path | string | नहीं | | फ्रंटएंड रूट पाथ, डिफ़ॉल्ट खाली |
| sort | int | नहीं | | सॉर्ट मान, डिफ़ॉल्ट 0 |

**रिस्पॉन्स उदाहरण**:
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

### 7.3 अनुमति अपडेट करें

```
PUT /admin/permission/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| name | string | नहीं | अनुमति का नाम |
| icon | string | नहीं | आइकन |
| path | string | नहीं | रूट पाथ |
| sort | int | नहीं | सॉर्ट मान |

### 7.4 अनुमति हटाएँ

```
DELETE /admin/permission/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड पुनः पुष्टि आवश्यक

**रिक्वेस्ट बॉडी**:
```json
{
  "password": "admin_password"
}
```

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

हटाते समय सभी चाइल्ड अनुमतियाँ (`parent_id` = वर्तमान अनुमति ID वाले रिकॉर्ड) कैस्केड हटाई जाती हैं, साथ ही सभी भूमिकाओं के साथ संबंध समाप्त होते हैं।

## 8. सिस्टम कॉन्फ़िगरेशन

सिस्टम कॉन्फ़िगरेशन `group` + `key` के संयोजन से अद्वितीय होता है।

### 8.1 कॉन्फ़िगरेशन सूची

```
GET /admin/config
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पेज संख्या |
| limit | int | नहीं | 15 | प्रति पेज संख्या |
| group | string | नहीं | | कॉन्फ़िगरेशन ग्रुप के अनुसार फ़िल्टर |

**रिस्पॉन्स उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid |
| group | string | कॉन्फ़िगरेशन ग्रुप (जैसे `system`, `email`, `storage`) |
| key | string | कॉन्फ़िगरेशन कुंजी |
| value | string | कॉन्फ़िगरेशन मान |
| type | string | मान प्रकार संकेत (`string`, `integer`, `boolean`, `json` आदि) |
| description | string | कॉन्फ़िगरेशन विवरण |

### 8.2 कॉन्फ़िगरेशन बनाएँ

```
POST /admin/config
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| group | string | हाँ | max:100 | कॉन्फ़िगरेशन ग्रुप |
| key | string | हाँ | max:100 | कॉन्फ़िगरेशन कुंजी (एक ही ग्रुप में अद्वितीय) |
| value | string | हाँ | | कॉन्फ़िगरेशन मान |
| type | string | नहीं | | मान प्रकार, डिफ़ॉल्ट `string` |
| description | string | नहीं | | कॉन्फ़िगरेशन विवरण, डिफ़ॉल्ट खाली |

**रिस्पॉन्स उदाहरण**:
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

**संभावित त्रुटियाँ**:
- 422: कॉन्फ़िगरेशन आइटम पहले से मौजूद है (एक ही group + key)

### 8.3 कॉन्फ़िगरेशन अपडेट करें

```
PUT /admin/config/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**रिक्वेस्ट बॉडी**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| value | string | नहीं | अपडेट कॉन्फ़िगरेशन मान |
| type | string | नहीं | अपडेट मान प्रकार |
| description | string | नहीं | अपडेट विवरण टेक्स्ट |

### 8.4 कॉन्फ़िगरेशन हटाएँ

```
DELETE /admin/config/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड पुनः पुष्टि आवश्यक

**रिक्वेस्ट बॉडी**:
```json
{
  "password": "admin_password"
}
```

कॉन्फ़िगरेशन रिकॉर्ड भौतिक रूप से हटा दिया जाता है।

## 9. ऑपरेशन लॉग

ऑपरेशन लॉग केवल-पठनीय इंटरफ़ेस है, `OperationLog` मिडलवेयर हर POST/PUT/DELETE रिक्वेस्ट पर स्वचालित रूप से लिखता है; संग्रहीत फ़ील्ड में `user_id`, `action`, `method`, `path`, `ip`, `source`, `input` शामिल हैं।

### 9.1 ऑपरेशन लॉग सूची

```
GET /admin/log
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पेज संख्या |
| limit | int | नहीं | 15 | प्रति पेज संख्या |
| user_id | int | नहीं | | उपयोगकर्ता ID से सटीक फ़िल्टर (मूल INT प्रकार) |
| action | string | नहीं | | ऑपरेशन एक्शन से सटीक फ़िल्टर |
| path | string | नहीं | | रिक्वेस्ट पाथ से फ़ज़ी फ़िल्टर |
| start_date | string | नहीं | | आरंभ तिथि (Y-m-d फॉर्मेट) |
| end_date | string | नहीं | | समाप्ति तिथि (Y-m-d फॉर्मेट) |

**रिस्पॉन्स उदाहरण**:
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
        "path": "/api/v1/auth/login",
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid |
| user_name | string | ऑपरेटिंग उपयोगकर्ता नाम (user संबंध से प्राप्त, लॉगिन न करने पर "सिस्टम" दिखता है) |
| action | string | ऑपरेशन एक्शन विवरण |
| method | string | HTTP मेथड (POST/PUT/DELETE) |
| path | string | रिक्वेस्ट पाथ |
| ip | string | क्लाइंट IP |
| source | string | रिक्वेस्ट स्रोत |
| input | string | रिक्वेस्ट पैरामीटर JSON स्ट्रिंग (फ़ाइलें शामिल नहीं) |
| created_at | string | ऑपरेशन समय (datetime) |

## 10. प्रोफ़ाइल केंद्र

प्रोफ़ाइल केंद्र इंटरफ़ेस के लिए केवल JWT प्रमाणीकरण आवश्यक है (RBAC अनुमति सत्यापन आवश्यक नहीं — `AdminPermission` मिडलवेयर को इसे व्हाइटलिस्ट में डालना चाहिए)।

### 10.1 व्यक्तिगत जानकारी अपडेट करें

```
PUT /admin/profile
```

- **प्रमाणीकरण**: JWT

**रिक्वेस्ट बॉडी**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| real_name | string | नहीं | वास्तविक नाम |
| phone | string | नहीं | फ़ोन नंबर (Encryptable एन्क्रिप्शन संग्रहित) |
| email | string | नहीं | ईमेल (Encryptable एन्क्रिप्शन संग्रहित) |

**रिस्पॉन्स उदाहरण**:
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

रिस्पॉन्स में `phone` और `email` प्लेनटेक्स्ट लौटते हैं, `password` और `id_card` हटा दिए गए हैं।

### 10.2 पासवर्ड बदलें

```
PUT /admin/profile/password
```

- **प्रमाणीकरण**: JWT

**रिक्वेस्ट बॉडी**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| old_password | string | हाँ | | वर्तमान पासवर्ड |
| new_password | string | हाँ | min:6, max:32 | नया पासवर्ड |

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**संभावित त्रुटियाँ**:
- 422: कृपया पुराना पासवर्ड और नया पासवर्ड भरें
- 422: पुराना पासवर्ड गलत
- 422: नया पासवर्ड 6-32 अक्षरों का होना चाहिए

### 10.3 लॉगआउट

```
POST /admin/profile/logout
```

- **प्रमाणीकरण**: JWT

**रिक्वेस्ट बॉडी**: नहीं (कोई requestBody नहीं, टोकन Authorization हेडर से पढ़ा जाता है)

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

लॉगआउट लॉजिक: JWT डिकोड करके शेष वैधता अवधि (exp - now) प्राप्त करें, उस टोकन का md5 हैश Redis ब्लैकलिस्ट `jwt_blacklist:{md5}` में लिखें, TTL = शेष वैधता अवधि। ब्लैकलिस्ट में मौजूद टोकन `AdminAuth` मिडलवेयर में रोक दिए जाते हैं, 401 लौटता है।

टोकन नहीं होने पर 401 लौटता है। टोकन समाप्त/अमान्य होने पर (डिकोड में एक्सेप्शन) फिर भी लॉगआउट सफल माना जाता है।

## 11. आयात-निर्यात

### 11.1 Excel निर्यात

```
POST /admin/export/excel
```

- **प्रमाणीकरण**: JWT + RBAC
- **रिस्पॉन्स प्रकार**: फ़ाइल डाउनलोड (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**रिक्वेस्ट बॉडी**:
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

| फ़ील्ड | प्रकार | अनिवार्य | डिफ़ॉल्ट | विवरण |
|------|------|------|------|------|
| table | string | नहीं | `admin_user` | निर्यात टेबल नाम। समर्थित: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | नहीं | | निर्यात कॉलम फ़ील्ड नाम एरे, खाली होने पर टेबल की सभी कॉलम निर्यात होती हैं |
| conditions | object | नहीं | `{}` | फ़िल्टर शर्तें, key-value जोड़े, मान खाली न होने पर WHERE में उपयोग |
| title | string | नहीं | `数据导出` | Excel शीर्षक (Sheet नाम के रूप में दिखता है) |

**समर्थित टेबल और कॉलम**:

| table | उपलब्ध कॉलम |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

संवेदनशील फ़ील्ड `phone`, `email`, `id_card` निर्यात के समय स्वचालित रूप से मास्क होते हैं। डेटा की ऊपरी सीमा 10000 पंक्तियाँ। Excel की पहली पंक्ति फ़्रीज़ और ऑटो फ़िल्टर।

### 11.2 PDF निर्यात

```
POST /admin/export/pdf
```

- **प्रमाणीकरण**: JWT + RBAC
- **रिस्पॉन्स प्रकार**: फ़ाइल डाउनलोड (`application/pdf`, A4 लैंडस्केप)

**रिक्वेस्ट बॉडी**:
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

या टेबल मोड:
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

| फ़ील्ड | प्रकार | अनिवार्य | डिफ़ॉल्ट | विवरण |
|------|------|------|------|------|
| type | string | नहीं | `table` | निर्यात प्रकार: `table` / `dashboard` |
| title | string | नहीं | `数据导出` | PDF शीर्षक |
| data | object | नहीं | `{}` | निर्यात डेटा |

`type=dashboard` होने पर `data` में `stats` एरे होना चाहिए (कार्ड रूप में रेंडर); `type=table` होने पर `data` में `columns` और `rows` एरे होने चाहिए।

PDF टेम्पलेट में कॉपीराइट जानकारी और निर्यात टाइमस्टैम्प शामिल होता है।

### 11.3 उपयोगकर्ता आयात (Excel)

```
POST /admin/import/users
```

- **प्रमाणीकरण**: JWT + RBAC
- **रिक्वेस्ट प्रकार**: `multipart/form-data` (फ़ाइल अपलोड)

**फॉर्म फ़ील्ड**:

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| file | file | हाँ | `.xlsx` या `.xls` फॉर्मेट |

**Excel कॉलम आवश्यकताएँ**:

| कॉलम नाम | अनिवार्य | विवरण |
|------|------|------|
| username | हाँ | उपयोगकर्ता नाम (अद्वितीय) |
| password | हाँ | पासवर्ड (bcrypt हैश संग्रहित) |
| real_name | हाँ | वास्तविक नाम |
| phone | नहीं | फ़ोन नंबर |
| email | नहीं | ईमेल |
| status | नहीं | स्थिति, डिफ़ॉल्ट 1 |

पहली पंक्ति कॉलम शीर्षक होती है (केस-असंवेदनशील), दूसरी पंक्ति से डेटा शुरू होता है।

**रिस्पॉन्स उदाहरण**:
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

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| total | int | कुल पंक्तियाँ (शीर्षक पंक्ति को छोड़कर) |
| success | int | सफल आयात संख्या |
| failed | int | विफल संख्या |
| errors | array | विफल विवरण, प्रत्येक में row (Excel पंक्ति संख्या) और reason (विफल कारण) |

## 12. फ़ाइल अपलोड

```
POST /admin/upload
```

- **प्रमाणीकरण**: JWT + RBAC
- **रिक्वेस्ट प्रकार**: `multipart/form-data`

**फॉर्म फ़ील्ड**:

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| file | file | हाँ | अपलोड फ़ाइल |

**अनुमत फ़ाइल प्रकार**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**अधिकतम फ़ाइल आकार**: 10MB

**रिस्पॉन्स उदाहरण**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

फ़ाइलें तिथि-वार डायरेक्टरी में `public/upload/{Y-m-d}/` पर संग्रहीत होती हैं, फ़ाइल नाम `md5(uniqid) + मूल एक्सटेंशन` होता है। `url` साइट रूट पाथ के सापेक्ष रिलेटिव पाथ है।

**संभावित त्रुटियाँ**:
- 422: कृपया फ़ाइल चुनें (अपलोड नहीं हुई)
- 422: असमर्थित फ़ाइल प्रकार
- 422: फ़ाइल आकार 10MB से अधिक नहीं हो सकता
- 500: फ़ाइल अपलोड विफल (फ़ाइल अमान्य)

## 13. रिस्पॉन्स हेडर

सभी इंटरफ़ेस (ग्लोबल मिडलवेयर परत में इंजेक्ट) में निम्न रिस्पॉन्स हेडर होते हैं:

| हेडर | विवरण |
|----|------|
| `X-RateLimit-Limit` | रेट लिमिट ऊपरी सीमा (संख्या) |
| `X-RateLimit-Remaining` | शेष रिक्वेस्ट संख्या |
| `X-RateLimit-Reset` | रेट लिमिट विंडो रीसेट टाइमस्टैम्प |
| `Retry-After` | केवल रेट लिमिट ट्रिगर होने पर, प्रतीक्षा सेकंड सुझाव |
| `X-Content-Type-Options` | `nosniff` (webman डिफ़ॉल्ट, MIME स्निफिंग प्रतिबंधित) |
| `X-Frame-Options` | `DENY` (webman के CORS मिडलवेयर/बेस कॉन्फ़िगरेशन द्वारा) |

रेट लिमिट विवरण:
- डिफ़ॉल्ट ग्लोबल लिमिट: 60 बार/मिनट / IP+पाथ
- लॉगिन एंडपॉइंट `/api/v1/auth/login`: 10 बार/मिनट
- रजिस्ट्रेशन एंडपॉइंट `/api/v1/auth/register`: 5 बार/मिनट
- Redis एटॉमिक स्लाइडिंग विंडो एल्गोरिदम (Lua ZSET) उपयोग, TOCTOU रेस से बचाव
- Redis अनुपलब्ध होने पर fail open (अनुमति), रिक्वेस्ट ब्लॉक नहीं होती

## 14. प्रमाणीकरण प्रवाह

पूर्ण प्रमाणीकरण सीक्वेंस:

```
1. क्लाइंट रिक्वेस्ट POST /api/v1/captcha/generate
    ↓
   सर्वर लौटाता है: key + type(click|slider|rotate) + base64 छवि + extra(प्रकार-संबंधित डेटा)
   
2. उपयोगकर्ता कैप्चा ऑपरेशन पूरा करता है (क्लिक/ड्रैग/रोटेट), क्लाइंट उत्तर एकत्र करता है
   
3. क्लाइंट रिक्वेस्ट POST /api/v1/captcha/verify
   (रिक्वेस्ट हेडर: Content-Type: application/json)
   रिक्वेस्ट बॉडी: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // निर्देशांक एरे
   - type=slider: clicks = 120                   // X ऑफ़सेट
   - type=rotate: clicks = 315                   // रोटेशन कोण
    ↓
   सर्वर:
   a. स्टोरेज से captcha:key डेटा पढ़ें (TTL 300s)
   b. type के अनुसार उत्तर सत्यापित करें (click: यूक्लिडियन दूरी ≤18px / slider: ±4px / rotate: ±5°)
   c. सत्यापन पास → Redis में `captcha_verified:{key}` = 1 लिखें (TTL 300s)
   d. सत्यापन विफल → 422 लौटाएँ, गिनती +1, 3 बार से अधिक पर key रद्द
    ↓
   सर्वर लौटाता है: { valid: true/false }

4. क्लाइंट रिक्वेस्ट POST /api/v1/auth/login
   (रिक्वेस्ट हेडर: Content-Type: application/json)
   रिक्वेस्ट बॉडी: { username, password(एन्क्रिप्टेड), captcha_key }
    ↓
   सर्वर:
   a. पैरामीटर सत्यापन → 422
   b. captcha_verified:{key} मौजूद है या नहीं जाँचें → 422
   c. captcha_verified:{key} हटाएँ (एक बार उपयोग)
   d. पासवर्ड डिक्रिप्ट करें: EncryptionService::decrypt(password) → प्लेनटेक्स्ट
   e. उपयोगकर्ता क्रेडेंशियल सत्यापित करें (password_verify) → 401
   f. खाता स्थिति जाँचें → 403/429
   g. JWT जारी करें (access + refresh) → 200
   h. last_login_at / last_login_ip अपडेट करें
    ↓
   क्लाइंट सेव करता है: access_token, refresh_token, expires_in

5. बाद की रिक्वेस्ट JWT के साथ
   रिक्वेस्ट हेडर: Authorization: Bearer <access_token>
    ↓
   AdminAuth मिडलवेयर:
   a. Bearer टोकन निकालें
   b. ब्लैकलिस्ट जाँचें (Redis jwt_blacklist:{md5}) → 401
   c. JWT डिकोड करें, समाप्ति सत्यापित करें → 401
   d. $request->adminId = sub फ़ील्ड सेट करें
    ↓
   AdminPermission मिडलवेयर:
   a. संसाधन रूट के लिए अनुमति पहचानकर्ता पार्स करें
   b. उपयोगकर्ता भूमिकाएँ → भूमिका अनुमतियाँ क्वेरी करें, मिलान करें
   c. अनुमति नहीं → 403
    ↓
   Controller रिक्वेस्ट हैंडल करता है
    ↓
   Response + X-RateLimit-* हेडर

6. Access Token समाप्ति से पहले रीफ़्रेश
   क्लाइंट रिक्वेस्ट POST /api/v1/auth/refresh
   रिक्वेस्ट बॉडी: { refresh_token: "..." }
    ↓
   सर्वर refresh_token डिकोड करता है → नया access + refresh जारी करता है
    ↓
   क्लाइंट लोकल टोकन अपडेट करता है

7. लॉगआउट
   क्लाइंट रिक्वेस्ट POST /admin/profile/logout
   रिक्वेस्ट हेडर: Authorization: Bearer <access_token>
    ↓
   सर्वर:
   a. JWT डिकोड करके शेष TTL प्राप्त करें
   b. Redis ब्लैकलिस्ट लिखें: jwt_blacklist:{md5(token)} = 1, TTL = शेष वैधता अवधि
   c. सफलता लौटाएँ
```

### JWT संरचना

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, डिफ़ॉल्ट TTL 7200 सेकंड (JWT कॉन्फ़िग `default_expire` द्वारा नियंत्रित)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, डिफ़ॉल्ट TTL 1209600 सेकंड (JWT कॉन्फ़िग `refresh_expire` द्वारा नियंत्रित, यानी 14 दिन)

### सुरक्षा प्रबंधन

- पासवर्ड `PASSWORD_BCRYPT` हैश में संग्रहीत
- पासवर्ड ट्रांसमिशन परत AES-256-CBC-HMAC एन्क्रिप्शन (क्लाइंट एन्क्रिप्ट → सर्वर डिक्रिप्ट), प्लेनटेक्स्ट फॉलबैक संगत
- संवेदनशील फ़ील्ड (phone, email, id_card) `erikwang2013/encryptable` द्वारा डेटाबेस परत में पारदर्शी एन्क्रिप्शन/डिक्रिप्शन
- API परत ID `erikwang2013/hashids` द्वारा एन्क्रिप्टेड ट्रांसमिशन, मूल snowflake ID सीक्वेंस उजागर होने से बचाव
- SecurityFilter ग्लोबल XSS, SQL इंजेक्शन, पाथ ट्रैवर्सल, कमांड इंजेक्शन स्कैन करता है, एक ही IP 5 बार/60 सेकंड पर 15 मिनट अस्थायी ब्लैकलिस्ट
- संवेदनशील ऑपरेशन (उपयोगकर्ता, भूमिका, अनुमति, कॉन्फ़िग हटाना) के लिए वर्तमान लॉगिन उपयोगकर्ता का पासवर्ड पुनः पुष्टि आवश्यक
- समवर्ती सत्र सीमा: एक ही उपयोगकर्ता अधिकतम 3 मान्य टोकन, चौथे डिवाइस पर लॉगिन करने पर सबसे पुराना टोकन बलपूर्वक ब्लैकलिस्ट में
- खाता लॉक: लगातार 5 बार लॉगिन विफल → 15 मिनट खाता लॉक, लॉक अवधि में 429 लौटता है

### मिडलवेयर आर्किटेक्चर

ग्लोबल मिडलवेयर सभी रिक्वेस्ट पर क्रम से लागू होता है:

```
Cors（क्रॉस-ओरिजिन प्रीप्रोसेसिंग + रिस्पॉन्स हेडर）
  → Locale（Accept-Language भाषा पहचान / ?lang=zh_CN|en）
  → SecurityFilter（HTTP मेथड सीमा/रिक्वेस्ट बॉडी आकार/Content-Type सत्यापन/XSS/SQL इंजेक्शन/पाथ ट्रैवर्सल/कमांड इंजेक्शन/CSRF अटैक इंटरसेप्शन）
  → RateLimit（Redis स्लाइडिंग विंडो रेट लिमिट + खाता लॉक: 5 बार लॉगिन विफल → 15 मिनट लॉक）
  → AdminAuth（JWT प्रमाणीकरण + ब्लैकलिस्ट, /admin रूट ग्रुप）
  → AdminPermission（RBAC प्रमाणीकरण / Redis 60s कैश, /admin रूट ग्रुप）
  → OperationLog（POST/PUT/DELETE स्वचालित रिकॉर्डिंग, स्रोत डिवाइस पहचान सहित, /admin रूट ग्रुप）
```

`/health` और `/api/docs` सार्वजनिक एंडपॉइंट हैं, केवल `Cors → SecurityFilter → RateLimit` से गुजरते हैं।

सुरक्षा संवर्द्धन:
- **खाता लॉक**: लगातार 5 बार लॉगिन विफल होने पर खाता स्वचालित रूप से 15 मिनट के लिए लॉक, इस दौरान लॉगिन 429 लौटाता है
- **समवर्ती सत्र सीमा**: एक ही उपयोगकर्ता अधिकतम 3 मान्य टोकन, अधिक होने पर सबसे पुराना टोकन स्वचालित रूप से ब्लैकलिस्ट में
- **security.txt**: `GET /.well-known/security.txt` RFC 9116 मानक सुरक्षा संपर्क जानकारी प्रदान करता है
- **Nginx सुरक्षा कॉन्फ़िगरेशन**: `docs/nginx-security.conf` देखें, पूर्ण रिवर्स प्रॉक्सी सुरक्षा हार्डनिंग उदाहरण

### ऑपरेशन स्रोत डिवाइस पहचान

OperationLog मिडलवेयर क्लाइंट प्लेटफ़ॉर्म स्वचालित रूप से पहचानता है और ऑपरेशन लॉग के `source` फ़ील्ड में लिखता है:

| प्लेटफ़ॉर्म | पहचान विधि |
|------|---------|
| `ipados` | UA में iPad |
| `macos` | UA में Macintosh/Mac OS |
| `windows` | UA में Windows |
| `linux` | UA में Linux (Android नहीं) |
| `ios` | UA में iPhone / iOS / CFNetwork |
| `android` | UA में Android |
| `harmonyos` | UA में HarmonyOS / OpenHarmony या `X-Client-Platform` हेडर स्पष्ट घोषणा |
| `web` | डिफ़ॉल्ट (उपरोक्त सभी प्लेटफ़ॉर्म से मेल न खाने पर) |

> दो-स्तरीय पहचान: `X-Client-Platform` रिक्वेस्ट हेडर (नेटिव ऐप घोषणा) → User-Agent स्वचालित अनुमान (फॉलबैक)। ऑपरेशन लॉग क्वेरी `GET /admin/log` के `source` फ़ील्ड से स्रोत डिवाइस देखा जा सकता है।

## 15. डिप्लॉयमेंट और संचालन

### Docker Compose

प्रोजेक्ट रूट में `docker-compose.yml` उपलब्ध है, जो 5 सेवाओं का ऑर्केस्ट्रेशन करता है (Nginx, webman app, MySQL, Redis, Elasticsearch)। PHP `Dockerfile` द्वारा बिल्ड होती है (`php:8.3-cli` पर आधारित, OPcache सक्षम)।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions निरंतर इंटीग्रेशन पाइपलाइन परिभाषित करता है:
- `php -l` सिंटैक्स जाँच
- PHPUnit यूनिट टेस्ट
- `flutter analyze` स्टैटिक विश्लेषण

### डेटाबेस बैकअप

`database/backup/` डायरेक्टरी में बैकअप और रिस्टोर स्क्रिप्ट:
- `backup.sh` — mysqldump + gzip कंप्रेस्ड बैकअप, 30 दिन पुरानी बैकअप फ़ाइलें स्वचालित सफाई
- `restore.sh` — इंटरैक्टिव रिस्टोर, मौजूदा बैकअप सूचीबद्ध करके उपयोगकर्ता चुनता है

### Nginx सुरक्षा कॉन्फ़िगरेशन

प्रोडक्शन डिप्लॉयमेंट में रिवर्स प्रॉक्सी सुरक्षा हार्डनिंग कॉन्फ़िगरेशन के लिए `docs/nginx-security.conf` देखें।

## 16. आंतरिक gRPC इंटरफ़ेस (e-cat → PHP worker)

केवल e-cat क्वेरी गेटवे द्वारा उपयोग हेतु। PHP पक्ष पर यह workerman h2c सेवा है (`tcp://0.0.0.0:8792`, प्रक्रिया `internal_grpc`)। कॉन्ट्रैक्ट `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`) में परिभाषित है, संदेश protobuf में एन्कोडेड होते हैं और metadata में `x-internal-token` होना अनिवार्य है (मान `config/logistics.php` के `internal_token` से, प्रोडक्शन में `INTERNAL_TOKEN` पर्यावरण चर द्वारा ओवरराइड)। टोकन अनुपस्थित/गलत होने पर `code=401 UNAUTHORIZED` लौटता है।

### 16.1 RPC अवलोकन

| RPC | अनुरोध | प्रतिक्रिया | विवरण |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | वाहक पहचान; न पहचाने जाने पर `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | ट्रैकिंग क्वेरी; `credential_id` वैकल्पिक (बहु-क्रेडेंशियल परिदृश्य में निर्दिष्ट करें, `carrier_code` से मेल खाना चाहिए, अन्यथा `400 INVALID_PARAMS`)। प्रत्येक क्वेरी `logistics_tracking_query` में संग्रहीत होती है |
| `Carriers` | `{}` | `{code, carriers[]}` | रजिस्ट्री के सभी वाहक `{carrier_code, channel}` |

### 16.2 त्रुटि कॉन्ट्रैक्ट (प्रतिक्रिया `code` फ़ील्ड, gRPC status सदैव 0)

| `code` | `error_code` | परिदृश्य |
|---|---|---|
| 400 | `INVALID_PARAMS` | पैरामीटर अनुपस्थित / credential_id बेमेल |
| 401 | `UNAUTHORIZED` | आंतरिक टोकन गलत |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | वाहक पंजीकृत नहीं / ट्रैकिंग नहीं मिली / पहचान नहीं हो सकी |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | अपस्ट्रीम क्रेडेंशियल त्रुटि / नेटवर्क त्रुटि / वाहक व्यावसायिक त्रुटि (संरचित अपस्ट्रीम संदेश सहित, जैसे `[SF-INTERNATIONAL A1001] अनिवार्य पैरामीटर खाली नहीं हो सकते`) |
| 500 | `INTERNAL_ERROR` | आंतरिक अपवाद |


## 17. e-cat क्वेरी गेटवे बाहरी API

Rust हाई-फ़्रीक्वेंसी गेटवे (`infrastructure/tracking-gateway`), `0.0.0.0:8080` पर सुनता है (डिफ़ॉल्ट), अनुरोध हेडर में `X-API-Key` ले जाते हैं (कॉन्फ़िग `api_keys`)। गेटवे API-Key प्रमाणीकरण, Redis कैश हिट (प्रीफ़िक्स `logistics:`), दर-सीमा, प्रति-वाहक सर्किट ब्रेकर और RoundRobin worker अग्रेषण संभालता है; क्रेडेंशियल कभी गेटवे तक नहीं पहुँचते।

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | स्वास्थ्य जाँच (सार्वजनिक एंडपॉइंट, कुंजी आवश्यक नहीं) |
| `GET /v1/openapi.json` | OpenAPI 3.0 दस्तावेज़ (सार्वजनिक एंडपॉइंट, कुंजी आवश्यक नहीं) |
| `GET /v1/carriers` | वाहक सूची (PHP gRPC `InternalService.Carriers` को अग्रेषित) |
| `POST /v1/tracking/query` | ट्रैकिंग क्वेरी (`{"carrier_code": "...", "tracking_no": "..."}`); `carrier_code` अनुपस्थित होने पर गेटवे स्वतः पहचानता है (detect, query के अंदर पूर्ण होता है) |
| `GET /v1/tracking/{query_no}` | क्वेरी नंबर द्वारा अंतिम ट्रैकिंग परिणाम |
| `POST /v1/subscriptions` | कॉलबैक सदस्यता पंजीकरण (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` डिफ़ॉल्ट `tracking.update`) |


गेटवे पक्ष की अतिरिक्त शर्तें: `rate_limit` (डिफ़ॉल्ट 100 बार/60s) से अधिक पर `429`; वाहक की लगातार विफलता सर्किट ब्रेकर सक्रिय करती है (डिफ़ॉल्ट 5x 5xx/ट्रांसपोर्ट त्रुटि के बाद 60s OPEN) → `503`; worker अनुपलब्ध → `502`। गेटवे और worker के बीच आंतरिक gRPC (h2c, `0.0.0.0:8792`), `x-internal-token` साझा कुंजी प्रमाणीकरण, धारा 16 देखें।

### 17.1 प्रतिक्रिया लिफ़ाफ़ा और त्रुटियाँ

सभी एंडपॉइंट एक समान लिफ़ाफ़ा `{"code": 0, "message": "ok", "data": ...}` लौटाते हैं; `code != 0` त्रुटि है और `data` अनुपस्थित रहता है। व्यावसायिक त्रुटि निकाय `error_code` / `error_message` ले सकता है (अपस्ट्रीम वाहक निदान, गेटवे द्वारा पारदर्शी रूप से पास किया गया); `code` HTTP स्थिति के अनुरूप है:

| HTTP | अर्थ |
|---|---|
| 400 | पैरामीटर त्रुटि (`tracking_no` अनुपस्थित / सदस्यता पैरामीटर अवैध) |
| 401 | कुंजी अनुपस्थित या अवैध |
| 404 | क्वेरी नंबर मौजूद नहीं |
| 429 | दर-सीमा (डिफ़ॉल्ट 100/60s प्रति कुंजी), बैकऑफ़ के साथ पुनः प्रयास करें |
| 502 | worker अनुपलब्ध या अपस्ट्रीम विफलता |
| 503 | वाहक सर्किट ब्रेकर, बैकऑफ़ के साथ पुनः प्रयास करें |

### 17.2 कॉल उदाहरण (curl)

डेमो कुंजी `demo-api-key`, गेटवे डिफ़ॉल्ट `http://127.0.0.1:8080`:

```bash
# स्वास्थ्य जाँच (सार्वजनिक एंडपॉइंट, कुंजी आवश्यक नहीं)
curl http://127.0.0.1:8080/v1/health

# OpenAPI दस्तावेज़ (सार्वजनिक एंडपॉइंट, कुंजी आवश्यक नहीं)
curl http://127.0.0.1:8080/v1/openapi.json

# वाहक सूची
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# ट्रैकिंग क्वेरी (वाहक निर्दिष्ट करना वैकल्पिक)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# क्वेरी नंबर द्वारा अंतिम परिणाम
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# कॉलबैक सदस्यता पंजीकरण (कॉलबैक पता सार्वजनिक http/https होना चाहिए; गेटवे लूपबैक/निजी नेटवर्क अस्वीकार करता है)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 कॉल उदाहरण (SDK)

रिपॉज़िटरी की `sdk/` निर्देशिका में पाँच शून्य-निर्भरता SDK हैं (Python / PHP / Node.js / Go / Rust, कॉपी करके उपयोग करें):

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

SDK लिफ़ाफ़े में `code != 0` होने या नेटवर्क विफलता पर अपवाद (`TrackingApiError`) फेंकते हैं, जिसमें `code` / `message` / `error_code` / `error_message` / `http_status` होते हैं; दर-सीमा और सर्किट ब्रेकर त्रुटियों पर बैकऑफ़ के साथ पुनः प्रयास करें, वाहक पक्ष की त्रुटियों पर पुनः प्रयास न करें। पूर्ण उपयोग `sdk/README.md` में।
