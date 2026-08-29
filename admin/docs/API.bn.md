# API রেফারেন্স ডকুমেন্টেশন

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

## 1. ওভারভিউ

ওপেন অ্যাডমিন (open-admin) webman v2-ভিত্তিক, RESTful JSON API প্রদান করে। সব অ্যাডমিন এন্ডপয়েন্টে JWT অথেনটিকেশন ও RBAC পারমিশন ভেরিফিকেশন প্রয়োজন, পাবলিক এন্ডপয়েন্টগুলো API ভার্সন হেডারের মাধ্যমে ভার্সনযুক্ত কন্ট্রোলারে রাউট হয়।

- **বেস URL**: `http://localhost:8787`
- **API ভার্সন**: রিকোয়েস্ট হেডার `API-Version: v1` দিয়ে নিয়ন্ত্রিত (না দিলে ডিফল্ট v1)
- **ভাষা**: `Accept-Language` হেডার বা `?lang=zh_CN|en` প্যারামিটার দিয়ে সুইচ করা হয় (ডিফল্ট zh_CN), Locale মিডলওয়্যার স্বয়ংক্রিয় ডিটেক্ট করে

> **এন্ডপয়েন্ট ওভারভিউ**: অথেনটিকেশন(5) | ড্যাশবোর্ড(1) | ইউজার(7) | রোল(4) | পারমিশন(4) | কনফিগ(4) | লগ(1) | প্রোফাইল(3) | ইমপোর্ট-এক্সপোর্ট(3) | আপলোড(1) | অপারেশন(4: health/metrics/docs/security.txt) | মোট ৩৭টি এন্ডপয়েন্ট
- **অথেনটিকেশন**: `Authorization: Bearer <token>` (JWT)
- **রেসপন্স ফরম্যাট**: `{ "code": 0, "message": "success", "data": {...} }`
- **ডকুমেন্টেশন এন্ডপয়েন্ট**: `GET /api/docs` OpenAPI 3.0 JSON স্পেসিফিকেশন রিটার্ন করে

### রিকোয়েস্ট প্রয়োজনীয়তা

- শুধুমাত্র `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` মেথড অনুমোদিত, অন্য HTTP মেথড (যেমন TRACE, CONNECT, PATCH) ব্যবহার করলে 405 রিটার্ন হয়
- সব `POST` / `PUT` রিকোয়েস্টে `Content-Type: application/json` সেট করতে হবে (ফাইল আপলোড ছাড়া), না হলে 415 রিটার্ন হয়
- রিকোয়েস্ট বডির সাইজ 10MB-এর বেশি হতে পারবে না, না হলে 413 রিটার্ন হয়
- সিকিউরিটি ফিল্টার সব রিকোয়েস্ট ইনপুটে XSS, SQL ইনজেকশন, পাথ ট্রাভার্সাল, কমান্ড ইনজেকশন স্ক্যান করে, মিললে 403 রিটার্ন হয়
- টানা ৫ বার লগইন ব্যর্থ হলে অ্যাকাউন্ট লক ট্রিগার হয় (১৫ মিনিট), লক থাকা অবস্থায় লগইন রিকোয়েস্টে 429 রিটার্ন হয়
- একই ইউজার একসাথে সর্বোচ্চ ৩টি সক্রিয় টোকেন রাখতে পারে, এর বেশি হলে সবচেয়ে পুরনো টোকেন স্বয়ংক্রিয়ভাবে ব্ল্যাকলিস্টে যায়

## 2. এরর কোড

| code | অর্থ | ট্রিগার দৃশ্য |
|------|------|---------|
| 0 | সফল | |
| 400 | রিকোয়েস্ট প্যারামিটার এরর | রিকোয়েস্ট ফরম্যাট সঠিক নয় |
| 401 | অথেনটিকেটেড নয় | Token অনুপস্থিত / মেয়াদোত্তীর্ণ / ব্ল্যাকলিস্টে আছে |
| 403 | অনুমতি নেই / সিকিউরিটি ব্লক | RBAC পারমিশন অপর্যাপ্ত / SecurityFilter ম্যাচ |
| 404 | রিসোর্স নেই | কোয়েরি/আপডেট/ডিলিটের টার্গেট নেই |
| 405 | রিকোয়েস্ট মেথড অনুমোদিত নয় | শুধুমাত্র GET/POST/PUT/DELETE/OPTIONS/HEAD অনুমোদিত, নন-স্ট্যান্ডার্ড মেথড সরাসরি রিজেক্ট |
| 413 | রিকোয়েস্ট বডি অনেক বড় | Content-Length 10MB-এর বেশি |
| 415 | আনসাপোর্টেড মিডিয়া টাইপ | POST/PUT রিকোয়েস্টের Content-Type JSON নয় এবং ফাইল আপলোডও নয় |
| 422 | প্যারামিটার ভ্যালিডেশন ব্যর্থ | আবশ্যক ফিল্ড অনুপস্থিত, ফরম্যাট সঠিক নয়, বিজনেস ভ্যালিডেশন পাস হয়নি |
| 429 | রিকোয়েস্ট অতিরিক্ত ঘন ঘন | RateLimit ট্রিগার / অ্যাকাউন্ট লক (টানা ৫ বার লগইন ব্যর্থ হলে ১৫ মিনিট লক) |
| 500 | সার্ভার অভ্যন্তরীণ এরর | |

## 3. পাবলিক এন্ডপয়েন্ট

সব পাবলিক এন্ডপয়েন্ট `/api` গ্রুপে মাউন্ট করা, `ApiVersion` মিডলওয়্যার `API-Version` হেডার অনুযায়ী সংশ্লিষ্ট ভার্সনযুক্ত কন্ট্রোলারে ডিসপ্যাচ করে (যেমন `app\api\v1\controller\AuthController`)।

### 3.1 হেলথ চেক

```
GET /health
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: নেই

**রেসপন্স উদাহরণ**:
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

`database`, `redis`, `elasticsearch` এর মান: `"ok"` | `"unavailable"`। ES-এ পৌঁছানো না গেলে `elasticsearch` `"unavailable"` রিটার্ন করে, ক্লাস্টার হেলথ স্ট্যাটাস green/yellow না হলে প্রকৃত status মান রিটার্ন হয় (যেমন `"red"`)।

### 3.2 API ডকুমেন্টেশন

```
GET /api/docs
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)
- **রেসপন্স**: OpenAPI 3.0.3 JSON স্পেসিফিকেশন, সব এন্ডপয়েন্ট ডেফিনিশন, প্যারামিটার ও Schema সহ

### 3.3 ক্যাপচা জেনারেট

```
POST /api/captcha/generate
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রিকোয়েস্ট হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "difficulty": "medium"
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| difficulty | string | না | `easy` / `medium` / `hard`, ডিফল্ট `medium` |

**রেসপন্স উদাহরণ** — ক্লিক টাইপ (`type: "click"`):
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

**রেসপন্স উদাহরণ** — স্লাইডার টাইপ (`type: "slider"`):
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

**রেসপন্স উদাহরণ** — রোটেট টাইপ (`type: "rotate"`):
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| key | string | ক্যাপচা আইডেন্টিফায়ার, ভেরিফিকেশনে রিটার্ন করা হয় |
| type | string | ক্যাপচা টাইপ: `click` / `slider` / `rotate` |
| image | string | base64 data URI ইমেজ |
| extra | object | টাইপ-সম্পর্কিত অতিরিক্ত ডেটা (নিচে দেখুন) |

**`extra` টাইপ অনুযায়ী বর্ণনা**:

| type | extra ফিল্ড | টাইপ | বর্ণনা |
|------|-----------|------|------|
| click | targets | array | ক্লিক টার্গেট, `order`(অর্ডার) `text`(প্রম্পট টেক্সট) `x` `y`(স্থানাঙ্ক) সহ |
| slider | x, y | int | গ্যাপের বাম-উপরের কোণের স্থানাঙ্ক (300×200 ক্যানভাসের ভিত্তিতে) |
| slider | puzzle_w, puzzle_h | int | পাজল পিসের প্রস্থ ও উচ্চতা |
| slider | puzzle | string | পাজল পিসের base64 data URI |
| rotate | angle | int | সঠিক ঘূর্ণন কোণ (0-359), ইমেজ সঠিক করতে `360-angle` ঘোরাতে হবে |

### 3.4 ক্যাপচা ভেরিফাই

```
POST /api/captcha/verify
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রিকোয়েস্ট হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)

**রিকোয়েস্ট বডি** — ক্লিক টাইপ (`type: "click"`):
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

**রিকোয়েস্ট বডি** — স্লাইডার টাইপ (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**রিকোয়েস্ট বডি** — রোটেট টাইপ (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| key | string | হ্যাঁ | ক্যাপচা key, generate থেকে রিটার্ন করা |
| type | string | হ্যাঁ | ক্যাপচা টাইপ, generate-এর রিটার্ন করা `type`-এর সাথে মিলতে হবে |
| clicks | ভ্যারিয়েন্ট | হ্যাঁ | উত্তর ডেটা, ফরম্যাট type অনুযায়ী বদলায় (নিচে দেখুন) |

**`clicks` টাইপ অনুযায়ী বর্ণনা**:

| type | clicks টাইপ | বর্ণনা | এরর সহনশীলতা |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | ক্লিক কোঅর্ডিনেট অ্যারে, order অনুযায়ী | 18px রেডিয়াস |
| slider | `int` | স্লাইডারের X-অক্ষ অফসেট | ±4px |
| rotate | `int` | ঘূর্ণন কোণ (0-359) | ±5° |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

ভেরিফিকেশন পাস হলে ব্যাকএন্ড `captcha_verified:{key}` Redis-এ লেখে (TTL 300s), লগইন ইন্টারফেস据此 অনুমতি দেয়।
ভেরিফিকেশন ব্যর্থ হলে `code` হয় 422, `message` হয় `"验证失败，请重试"`, `data.valid` হয় `false`।

### 3.5 লগইন

```
POST /api/auth/login
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রিকোয়েস্ট হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: ১০ বার/মিনিট (IP + পাথ অনুযায়ী)

**রিকোয়েস্ট বডি**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম |
| password | string | হ্যাঁ | min:6, max:32 (প্লেইনটেক্সট) | AES-256-CBC-HMAC এনক্রিপশনের পর Base64 এনকোড (প্লেইনটেক্সট কম্প্যাটিবল) |
| captcha_key | string | হ্যাঁ | | ক্যাপচা key (আগে `/api/captcha/verify` দিয়ে ভেরিফাই করতে হবে) |

### পাসওয়ার্ড এনক্রিপশন প্রোটোকল

**RSA-2048 অ্যাসিমেট্রিক এনক্রিপশন** ব্যবহার করা হয়, পাবলিক কী ফ্রন্টএন্ড কোডে থাকে (নিরাপদে প্রকাশ করা যায়), প্রাইভেট কী শুধু সার্ভার সাইডে থাকে।

```
এনক্রিপশন ফ্লো (ক্লায়েন্ট):
  RSA পাবলিক কী (PEM) → PKCS1v1.5 এনক্রিপশন → Base64 এনকোড → ট্রান্সমিট

ডিক্রিপশন ফ্লো (সার্ভার, ধাপে ধাপে ফলব্যাক):
  1. RSA প্রাইভেট কী ডিক্রিপ্ট → সফল এবং বৈধ UTF-8 → ডিক্রিপ্ট করা রেজাল্ট ব্যবহার
  2. AES-256-CBC-HMAC ডিক্রিপ্ট → সফল → ডিক্রিপ্ট করা রেজাল্ট ব্যবহার (পুরনো ক্লায়েন্ট কম্প্যাটিবিলিটি)
  3. প্লেইনটেক্সট ফলব্যাক → সরাসরি অরিজিনাল ইনপুট ব্যবহার
```

পাবলিক কী ফ্রন্টএন্ড অ্যাপ্লিকেশনে বিল্ট-ইন, নেটওয়ার্কের মাধ্যমে ট্রান্সমিট করতে হয় না। প্রাইভেট কী শুধুমাত্র `.env`-এর `RSA_PRIVATE_KEY`-তে থাকে, লিক করা যাবে না।

> AES সিমেট্রিক এনক্রিপশন পুরনো ভার্সনের কম্প্যাটিবিলিটি সমাধান, সব ক্লায়েন্ট RSA-তে মাইগ্রেট হলে সরিয়ে ফেলা হবে।

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| access_token | string | JWT অ্যাক্সেস টোকেন |
| refresh_token | string | JWT রিফ্রেশ টোকেন |
| expires_in | int | অ্যাক্সেস টোকেনের বৈধতা (সেকেন্ড), ডিফল্ট 7200 |
| user.id | string | hashid এনক্রিপ্টেড ইউজার ID |
| user.username | string | ইউজারনেম |
| user.real_name | string | প্রকৃত নাম |

**সম্ভাব্য এরর**:
- 422: প্যারামিটার ভ্যালিডেশন ব্যর্থ (আবশ্যক ফিল্ড অনুপস্থিত, ফরম্যাট সঠিক নয়)
- 422: আগে ক্যাপচা ভেরিফিকেশন সম্পন্ন করুন (captcha_key `/api/captcha/verify` পাস করেনি)
- 401: ইউজারনেম বা পাসওয়ার্ড ভুল
- 403: অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে
- 429: অ্যাকাউন্ট লক হয়েছে, অনুগ্রহ করে ১৫ মিনিট পর আবার চেষ্টা করুন (টানা ৫ বার লগইন ব্যর্থ হলে ট্রিগার)

### 3.6 রেজিস্টার

```
POST /api/auth/register
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রিকোয়েস্ট হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: ৫ বার/মিনিট (IP + পাথ অনুযায়ী)

**রিকোয়েস্ট বডি**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম (ইউনিক) |
| password | string | হ্যাঁ | min:6, max:32 (প্লেইনটেক্সট) | AES-256-CBC-HMAC এনক্রিপশনের পর Base64 এনকোড |
| real_name | string | হ্যাঁ | max:50 | প্রকৃত নাম |
| captcha_key | string | হ্যাঁ | | ক্যাপচা key (আগে `/api/captcha/verify` দিয়ে ভেরিফাই করতে হবে) |

**রেসপন্স উদাহরণ**:
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

রেজিস্টার সফল হলে সরাসরি JWT টোকেন রিটার্ন হয়, ইউজার স্ট্যাটাস ডিফল্টভাবে সক্রিয় (status=1)।

### 3.7 টোকেন রিফ্রেশ

```
POST /api/auth/refresh
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রিকোয়েস্ট হেডার**: `API-Version: v1` (বাধ্যতামূলক)
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| refresh_token | string | হ্যাঁ | লগইন/রেজিস্টারে পাওয়া refresh_token |

**রেসপন্স উদাহরণ**:
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

রিফ্রেশ সফল হলে একসাথে নতুন access_token ও refresh_token রিটার্ন হয়, পুরনো টোকেন স্বয়ংক্রিয়ভাবে অকার্যকর হয়। রিফ্রেশের সময় ইউজারের শেষ লগইন সময় ও IP আপডেট হয়।

**সম্ভাব্য এরর**:
- 422: রিফ্রেশ টোকেন অনুপস্থিত
- 401: রিফ্রেশ টোকেন অবৈধ বা মেয়াদোত্তীর্ণ

### 3.8 Prometheus মনিটরিং মেট্রিক

```
GET /metrics
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: নেই
- **রেসপন্স ফরম্যাট**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus-এর স্ক্র্যাপিংয়ের জন্য পাবলিক Prometheus মনিটরিং মেট্রিক এন্ডপয়েন্ট।

**রেসপন্স উদাহরণ**:
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

| মেট্রিক নাম | টাইপ | বর্ণনা |
|------|------|------|
| `openadmin_http_requests_total` | gauge | মোট HTTP রিকোয়েস্ট সংখ্যা |
| `openadmin_active_users` | gauge | বর্তমান সক্রিয় ইউজার সংখ্যা (২৪ ঘণ্টার মধ্যে লগইন করেছে) |
| `openadmin_db_connection_status` | gauge | ডেটাবেস কানেকশন স্ট্যাটাস, 1=সাধারণ, 0=অস্বাভাবিক |
| `openadmin_redis_connection_status` | gauge | Redis কানেকশন স্ট্যাটাস, 1=সাধারণ, 0=অস্বাভাবিক |
| `openadmin_memory_usage_bytes` | gauge | PHP প্রসেসের বর্তমান মেমোরি ব্যবহার (bytes) |

## 4. ড্যাশবোর্ড

সব অ্যাডমিন এন্ডপয়েন্ট `/admin` গ্রুপে মাউন্ট করা, তিনটি মিডলওয়্যার পেরিয়ে যায়: `AdminAuth` (JWT অথেনটিকেশন), `AdminPermission` (RBAC পারমিশন ভেরিফিকেশন), `OperationLog` (অপারেশন রেকর্ড)।

### 4.1 ড্যাশবোর্ড ডেটা

```
GET /admin/dashboard
```

- **অথেনটিকেশন**: JWT + RBAC
- **ক্যাশ**: Redis ৫ মিনিট

**রেসপন্স উদাহরণ**:
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

| stats ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| label | string | মেট্রিক নাম |
| value | string | মেট্রিক মান (string টাইপ) |
| icon | string | Material আইকন নাম |
| color | string | কার্ড কালার ভ্যালু |
| trend | float? | দিন-ভিত্তিক মুকাবিলা বৃদ্ধির হার (শতাংশ), শুধুমাত্র "用户总数"-এ এই ফিল্ড আছে |

| trends ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| dates | array{string} | সাম্প্রতিক ৩০ দিনের তারিখ সিকোয়েন্স |
| series | array{object} | ট্রেন্ড লাইন ডেটা, প্রতিটিতে name (নাম), data (মান অ্যারে), color (কালার) থাকে |

## 5. ইউজার ম্যানেজমেন্ট

সব ইউজার ম্যানেজমেন্ট ইন্টারফেসের রিটার্ন করা `id` hashid এনক্রিপ্টেড স্ট্রিং। পাসওয়ার্ড ফিল্ড রেসপন্স থেকে বাদ দেওয়া হয়েছে। ফোন নম্বর ও ইমেইল লিস্ট ইন্টারফেসে মাস্ক করে দেখানো হয়, ডিটেইল ইন্টারফেসে প্লেইনটেক্সট রিটার্ন হয় (ডেটাবেস এনক্রিপ্টেড ফিল্ড Encryptable trait দিয়ে অটো ডিক্রিপ্ট হয়)।

### 5.1 ইউজার লিস্ট

```
GET /admin/user
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | আবশ্যক | ডিফল্ট মান | বর্ণনা |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |
| keyword | string | না | | সার্চ কীওয়ার্ড, ইউজারনেম ও প্রকৃত নামের সাথে ম্যাচ |
| status | int | না | | স্ট্যাটাস ফিল্টার, 0=নিষ্ক্রিয়, 1=সক্রিয় |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড ইউজার ID |
| username | string | ইউজারনেম |
| real_name | string | প্রকৃত নাম |
| phone | string | মাস্কড ফোন নম্বর (`138****5678` ফরম্যাট) |
| email | string | মাস্কড ইমেইল (`a***@example.com` ফরম্যাট) |
| status | int | 1=সক্রিয়, 0=নিষ্ক্রিয় |
| last_login_at | string | শেষ লগইন সময় (datetime) |
| created_at | string | তৈরি হওয়ার সময় (datetime) |

### 5.2 ইউজার তৈরি

```
POST /admin/user
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম (ইউনিক) |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড (bcrypt স্টোরেজ) |
| real_name | string | হ্যাঁ | max:50 | প্রকৃত নাম |
| phone | string | না | | ফোন নম্বর (Encryptable এনক্রিপ্টেড স্টোরেজ) |
| email | string | না | | ইমেইল (Encryptable এনক্রিপ্টেড স্টোরেজ) |
| status | int | না | in:0,1 | স্ট্যাটাস, ডিফল্ট 1 (সক্রিয়) |

**রেসপন্স উদাহরণ**:
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

**সম্ভাব্য এরর**:
- 422: ইউজারনেম ইতিমধ্যে আছে
- 422: প্যারামিটার ভ্যালিডেশন ব্যর্থ (আবশ্যক ফিল্ড অনুপস্থিত)

### 5.3 ইউজার ডিটেইল

```
GET /admin/user/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` hashid এনক্রিপ্টেড ইউজার ID

**রেসপন্স উদাহরণ**:
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

ডিটেইল ইন্টারফেসে `phone` ও `email` প্লেইনটেক্সট রিটার্ন হয় (ডেটাবেসে এনক্রিপ্টেড স্টোরেজ, Encryptable cast অটো ডিক্রিপ্ট করে), মাস্ক করা হয় না। `password` ও `id_card` সবসময় রেসপন্সে থাকে না।

**সম্ভাব্য এরর**:
- 404: ইউজার নেই

### 5.4 ইউজার আপডেট

```
PUT /admin/user/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` hashid এনক্রিপ্টেড ইউজার ID

**রিকোয়েস্ট বডি**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| real_name | string | না | প্রকৃত নাম, না পাঠালে আগের মান থাকে |
| password | string | না | নতুন পাসওয়ার্ড, খালি স্ট্রিং বা না পাঠালে পরিবর্তন হয় না |
| phone | string | না | ফোন নম্বর |
| email | string | না | ইমেইল |
| status | int | না | 0=নিষ্ক্রিয়, 1=সক্রিয় |

**রেসপন্স উদাহরণ**:
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

**সম্ভাব্য এরর**:
- 404: ইউজার নেই

### 5.5 ইউজার ডিলিট

```
DELETE /admin/user/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` hashid এনক্রিপ্টেড ইউজার ID
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| password | string | হ্যাঁ | বর্তমান লগইন ইউজারের পাসওয়ার্ড (দ্বিতীয় নিশ্চিতকরণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

সফট ডিলিট করা হয় (Eloquent SoftDeletes), ডেটাতে deleted_at মার্ক হয়, ফিজিক্যালি ডিলিট হয় না।

**সম্ভাব্য এরর**:
- 404: ইউজার নেই
- 422: সংবেদনশীল অপারেশনে পাসওয়ার্ড কনফার্মেশন ইনপুট প্রয়োজন (password খালি)
- 422: পাসওয়ার্ড ভেরিফিকেশন ব্যর্থ (পাসওয়ার্ড মিলছে না)

### 5.6 ইউজার বাল্ক ডিলিট

```
POST /admin/user/batch/destroy
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| ids | array{string} | হ্যাঁ | hashid এনক্রিপ্টেড ইউজার ID অ্যারে |
| password | string | হ্যাঁ | বর্তমান লগইন ইউজারের পাসওয়ার্ড (দ্বিতীয় নিশ্চিতকরণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

সফট ডিলিট করা হয়, `data.count` প্রকৃত ডিলিট সংখ্যা।

**সম্ভাব্য এরর**:
- 422: ডিলিট করার ইউজার নির্বাচন করুন (ids খালি)
- 422: অবৈধ ID (hashid ডিকোড ব্যর্থ)
- 422: পাসওয়ার্ড ভেরিফিকেশন ব্যর্থ

### 5.7 ইউজার বাল্ক সক্রিয়/নিষ্ক্রিয়

```
POST /admin/user/batch/status
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| ids | array{string} | হ্যাঁ | hashid এনক্রিপ্টেড ইউজার ID অ্যারে |
| status | int | হ্যাঁ | 0=নিষ্ক্রিয়, 1=সক্রিয় |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message status মান অনুযায়ী ডাইনামিকভাবে `"批量启用成功"` বা `"批量禁用成功"` হয়।

**সম্ভাব্য এরর**:
- 422: ইউজার নির্বাচন করুন (ids খালি)
- 422: স্ট্যাটাস মান অবৈধ (status 0 বা 1 নয়)

## 6. রোল ম্যানেজমেন্ট

### 6.1 রোল লিস্ট

```
GET /admin/role
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | আবশ্যক | ডিফল্ট মান | বর্ণনা |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড রোল ID |
| name | string | রোল নাম |
| slug | string | রোল আইডেন্টিফায়ার (ইউনিক, পারমিশন চেকের জন্য ব্যবহৃত) |
| description | string | রোল বর্ণনা |
| status | int | 1=সক্রিয়, 0=নিষ্ক্রিয় |
| users_count | int | এই রোলের অধীনে থাকা ইউজার সংখ্যা |

### 6.2 রোল তৈরি

```
POST /admin/role
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| name | string | হ্যাঁ | max:50 | রোল নাম |
| slug | string | হ্যাঁ | max:50 | রোল আইডেন্টিফায়ার |
| description | string | না | | রোল বর্ণনা, ডিফল্ট খালি স্ট্রিং |
| status | int | না | | স্ট্যাটাস, ডিফল্ট 1 |
| permission_ids | array{int} | না | | পারমিশন ID অ্যারে (অরিজিনাল INT ID, hashid নয়) |

**রেসপন্স উদাহরণ**:
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

### 6.3 রোল আপডেট

```
PUT /admin/role/{id}
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| name | string | না | রোল নাম |
| description | string | না | বর্ণনা |
| status | int | না | 0=নিষ্ক্রিয়, 1=সক্রিয় |
| permission_ids | array{int} | না | পারমিশন ID অ্যারে, পাঠালে সিঙ্ক (ওভাররাইট) হয় রোল পারমিশন |

**রেসপন্স উদাহরণ**:
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

### 6.4 রোল ডিলিট

```
DELETE /admin/role/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ডিলিটের সময় রোলের সাথে সব পারমিশন ও ইউজারের সম্পর্ক স্বয়ংক্রিয়ভাবে খুলে যায়, তারপর রোল রেকর্ড ফিজিক্যালি ডিলিট হয়।

## 7. পারমিশন ম্যানেজমেন্ট

পারমিশন ট্রি স্ট্রাকচার (parent_id সেলফ-রেফারেন্স), তিনটি টাইপে বিভক্ত। লিস্ট ইন্টারফেস সম্পূর্ণ পারমিশন ট্রি রিটার্ন করে।

### 7.1 পারমিশন ট্রি

```
GET /admin/permission
```

- **অথেনটিকেশন**: JWT + RBAC

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড |
| parent_id | string | প্যারেন্ট পারমিশনের hashid, "0" মানে রুট নোড |
| name | string | পারমিশন নাম |
| slug | string | পারমিশন আইডেন্টিফায়ার (রাউট/বাটন আইডেন্টিফায়ার) |
| type | int | 1=মেনু, 2=বাটন, 3=API |
| icon | string | মেনু আইকন (Material আইকন নাম) |
| path | string | ফ্রন্টএন্ড রাউট পাথ |
| sort | int | সর্ট ভ্যালু (অ্যাসেন্ডিং) |
| children | array? | চাইল্ড পারমিশন লিস্ট (রিকার্সিভ), চাইল্ড না থাকলে এই ফিল্ড থাকে না |

### 7.2 পারমিশন তৈরি

```
POST /admin/permission
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| parent_id | int | না | | প্যারেন্ট পারমিশন ID (অরিজিনাল INT টাইপ), ডিফল্ট 0 |
| name | string | হ্যাঁ | max:50 | পারমিশন নাম |
| slug | string | হ্যাঁ | max:100 | পারমিশন আইডেন্টিফায়ার |
| type | int | হ্যাঁ | in:1,2,3 | 1=মেনু, 2=বাটন, 3=API |
| icon | string | না | | মেনু আইকন, ডিফল্ট খালি |
| path | string | না | | ফ্রন্টএন্ড রাউট পাথ, ডিফল্ট খালি |
| sort | int | না | | সর্ট ভ্যালু, ডিফল্ট 0 |

**রেসপন্স উদাহরণ**:
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

### 7.3 পারমিশন আপডেট

```
PUT /admin/permission/{id}
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| name | string | না | পারমিশন নাম |
| icon | string | না | আইকন |
| path | string | না | রাউট পাথ |
| sort | int | না | সর্ট ভ্যালু |

### 7.4 পারমিশন ডিলিট

```
DELETE /admin/permission/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ডিলিটের সময় ক্যাসকেড করে সব চাইল্ড পারমিশন ডিলিট হয় (`parent_id` = বর্তমান পারমিশন ID-এর রেকর্ড), একই সাথে সব রোলের সাথে সম্পর্ক খুলে যায়।

## 8. সিস্টেম কনফিগারেশন

সিস্টেম কনফিগারেশন `group` + `key` কম্বিনেশনে ইউনিক।

### 8.1 কনফিগ লিস্ট

```
GET /admin/config
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | আবশ্যক | ডিফল্ট মান | বর্ণনা |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |
| group | string | না | | কনফিগ গ্রুপ অনুযায়ী ফিল্টার |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| id | string | hashid |
| group | string | কনফিগ গ্রুপ (যেমন `system`, `email`, `storage`) |
| key | string | কনফিগ কী |
| value | string | কনফিগ ভ্যালু |
| type | string | ভ্যালু টাইপ নির্দেশক (`string`, `integer`, `boolean`, `json` ইত্যাদি) |
| description | string | কনফিগ বর্ণনা |

### 8.2 কনফিগ তৈরি

```
POST /admin/config
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| group | string | হ্যাঁ | max:100 | কনফিগ গ্রুপ |
| key | string | হ্যাঁ | max:100 | কনফিগ কী (একই গ্রুপে ইউনিক) |
| value | string | হ্যাঁ | | কনফিগ ভ্যালু |
| type | string | না | | ভ্যালু টাইপ, ডিফল্ট `string` |
| description | string | না | | কনফিগ বর্ণনা, ডিফল্ট খালি |

**রেসপন্স উদাহরণ**:
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

**সম্ভাব্য এরর**:
- 422: কনফিগ আইটেম ইতিমধ্যে আছে (একই group + key)

### 8.3 কনফিগ আপডেট

```
PUT /admin/config/{id}
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| value | string | না | কনফিগ ভ্যালু আপডেট |
| type | string | না | ভ্যালু টাইপ আপডেট |
| description | string | না | বর্ণনা টেক্সট আপডেট |

### 8.4 কনফিগ ডিলিট

```
DELETE /admin/config/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

কনফিগ রেকর্ড ফিজিক্যালি ডিলিট হয়।

## 9. অপারেশন লগ

অপারেশন লগ রিড-অনলি ইন্টারফেস, `OperationLog` মিডলওয়্যার প্রতিটি POST/PUT/DELETE রিকোয়েস্টে স্বয়ংক্রিয়ভাবে লিখে, স্টোরেজ ফিল্ডে `user_id`, `action`, `method`, `path`, `ip`, `source`, `input` থাকে।

### 9.1 অপারেশন লগ লিস্ট

```
GET /admin/log
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | আবশ্যক | ডিফল্ট মান | বর্ণনা |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে সংখ্যা |
| user_id | int | না | | ইউজার ID অনুযায়ী সুনির্দিষ্ট ফিল্টার (অরিজিনাল INT টাইপ) |
| action | string | না | | অ্যাকশন অনুযায়ী সুনির্দিষ্ট ফিল্টার |
| path | string | না | | রিকোয়েস্ট পাথ অনুযায়ী ফাজি ফিল্টার |
| start_date | string | না | | শুরু তারিখ (Y-m-d ফরম্যাট) |
| end_date | string | না | | শেষ তারিখ (Y-m-d ফরম্যাট) |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| id | string | hashid |
| user_name | string | অপারেটর ইউজারনেম (user সম্পর্ক থেকে পাওয়া, লগইন ছাড়া অপারেশনে "系统" দেখায়) |
| action | string | অপারেশন অ্যাকশন বর্ণনা |
| method | string | HTTP মেথড (POST/PUT/DELETE) |
| path | string | রিকোয়েস্ট পাথ |
| ip | string | ক্লায়েন্ট IP |
| source | string | রিকোয়েস্ট সোর্স |
| input | string | রিকোয়েস্ট প্যারামিটার JSON স্ট্রিং (ফাইল বাদে) |
| created_at | string | অপারেশন সময় (datetime) |

## 10. প্রোফাইল সেন্টার

প্রোফাইল সেন্টার ইন্টারফেসে শুধুমাত্র JWT অথেনটিকেশন প্রয়োজন (RBAC পারমিশন ভেরিফিকেশন প্রয়োজন নেই — `AdminPermission` মিডলওয়্যারে হোয়াইটলিস্টে যোগ করা উচিত)।

### 10.1 ব্যক্তিগত তথ্য আপডেট

```
PUT /admin/profile
```

- **অথেনটিকেশন**: JWT

**রিকোয়েস্ট বডি**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| real_name | string | না | প্রকৃত নাম |
| phone | string | না | ফোন নম্বর (Encryptable এনক্রিপ্টেড স্টোরেজ) |
| email | string | না | ইমেইল (Encryptable এনক্রিপ্টেড স্টোরেজ) |

**রেসপন্স উদাহরণ**:
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

রেসপন্সে `phone` ও `email` প্লেইনটেক্সট রিটার্ন হয়, `password` ও `id_card` বাদ দেওয়া হয়েছে।

### 10.2 পাসওয়ার্ড পরিবর্তন

```
PUT /admin/profile/password
```

- **অথেনটিকেশন**: JWT

**রিকোয়েস্ট বডি**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| ফিল্ড | টাইপ | আবশ্যক | ভ্যালিডেশন রুল | বর্ণনা |
|------|------|------|---------|------|
| old_password | string | হ্যাঁ | | বর্তমান পাসওয়ার্ড |
| new_password | string | হ্যাঁ | min:6, max:32 | নতুন পাসওয়ার্ড |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**সম্ভাব্য এরর**:
- 422: পুরনো পাসওয়ার্ড ও নতুন পাসওয়ার্ড দিন
- 422: পুরনো পাসওয়ার্ড ভুল
- 422: নতুন পাসওয়ার্ডের দৈর্ঘ্য 6-32 অক্ষর

### 10.3 লগআউট

```
POST /admin/profile/logout
```

- **অথেনটিকেশন**: JWT

**রিকোয়েস্ট বডি**: নেই (কোনো requestBody নেই, Authorization হেডার থেকে token পড়া হয়)

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

লগআউট লজিক: JWT ডিকোড করে অবশিষ্ট বৈধতা সময় (exp - now) পায়, টোকেনের md5 হ্যাশ Redis ব্ল্যাকলিস্টে `jwt_blacklist:{md5}` লিখে, TTL = অবশিষ্ট বৈধতা সময়। ব্ল্যাকলিস্টের টোকেন `AdminAuth` মিডলওয়্যারে ব্লক হয়, 401 রিটার্ন হয়।

টোকেন না থাকলে 401 রিটার্ন হয়। টোকেন মেয়াদোত্তীর্ণ/অবৈধ হলে (ডিকোডে এক্সেপশন) তবুও লগআউট সফল ধরা হয়।

## 11. ইমপোর্ট ও এক্সপোর্ট

### 11.1 Excel এক্সপোর্ট

```
POST /admin/export/excel
```

- **অথেনটিকেশন**: JWT + RBAC
- **রেসপন্স টাইপ**: ফাইল ডাউনলোড (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | আবশ্যক | ডিফল্ট মান | বর্ণনা |
|------|------|------|------|------|
| table | string | না | `admin_user` | এক্সপোর্ট টেবিল নাম। সাপোর্টেড: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | না | | এক্সপোর্ট কলাম ফিল্ড নাম অ্যারে, খালি হলে টেবিলের সব কলাম এক্সপোর্ট হয় |
| conditions | object | না | `{}` | ফিল্টার কন্ডিশন, key-value পেয়ার, ভ্যালু খালি না হলে WHERE-এ ব্যবহৃত |
| title | string | না | `数据导出` | Excel শিরোনাম (Sheet নাম হিসেবে দেখানো হয়) |

**সাপোর্টেড টেবিল ও কলাম**:

| table | উপলব্ধ কলাম |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

সংবেদনশীল ফিল্ড `phone`, `email`, `id_card` এক্সপোর্টের সময় স্বয়ংক্রিয় মাস্কিং হয়। ডেটা সীমা ১০০০০ সারি। Excel-এর প্রথম সারি ফ্রিজ থাকে, অটো ফিল্টার থাকে।

### 11.2 PDF এক্সপোর্ট

```
POST /admin/export/pdf
```

- **অথেনটিকেশন**: JWT + RBAC
- **রেসপন্স টাইপ**: ফাইল ডাউনলোড (`application/pdf`, A4 ল্যান্ডস্কেপ)

**রিকোয়েস্ট বডি**:
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

অথবা টেবিল মোড:
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

| ফিল্ড | টাইপ | আবশ্যক | ডিফল্ট মান | বর্ণনা |
|------|------|------|------|------|
| type | string | না | `table` | এক্সপোর্ট টাইপ: `table` / `dashboard` |
| title | string | না | `数据导出` | PDF শিরোনাম |
| data | object | না | `{}` | এক্সপোর্ট ডেটা |

`type=dashboard` হলে `data`-তে `stats` অ্যারে থাকতে হবে (কার্ড ফর্মে রেন্ডার হয়); `type=table` হলে `data`-তে `columns` ও `rows` অ্যারে থাকতে হবে।

PDF টেমপ্লেটে কপিরাইট তথ্য ও এক্সপোর্ট টাইমস্ট্যাম্প থাকে।

### 11.3 ইউজার ইমপোর্ট (Excel)

```
POST /admin/import/users
```

- **অথেনটিকেশন**: JWT + RBAC
- **রিকোয়েস্ট টাইপ**: `multipart/form-data` (ফাইল আপলোড)

**ফর্ম ফিল্ড**:

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| file | file | হ্যাঁ | `.xlsx` বা `.xls` ফরম্যাট |

**Excel কলাম প্রয়োজনীয়তা**:

| কলাম নাম | আবশ্যক | বর্ণনা |
|------|------|------|
| username | হ্যাঁ | ইউজারনেম (ইউনিক) |
| password | হ্যাঁ | পাসওয়ার্ড (bcrypt হ্যাশ স্টোরেজ) |
| real_name | হ্যাঁ | প্রকৃত নাম |
| phone | না | ফোন নম্বর |
| email | না | ইমেইল |
| status | না | স্ট্যাটাস, ডিফল্ট 1 |

১ম সারি কলাম হেডার (কেস-ইনসেনসিটিভ), ২য় সারি থেকে ডেটা।

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বর্ণনা |
|------|------|------|
| total | int | মোট সারি সংখ্যা (হেডার সারি বাদে) |
| success | int | সফলভাবে ইমপোর্ট হওয়া সংখ্যা |
| failed | int | ব্যর্থ সংখ্যা |
| errors | array | ব্যর্থতার বিবরণ, প্রতিটিতে row (Excel সারি নম্বর) এবং reason (ব্যর্থতার কারণ) থাকে |

## 12. ফাইল আপলোড

```
POST /admin/upload
```

- **অথেনটিকেশন**: JWT + RBAC
- **রিকোয়েস্ট টাইপ**: `multipart/form-data`

**ফর্ম ফিল্ড**:

| ফিল্ড | টাইপ | আবশ্যক | বর্ণনা |
|------|------|------|------|
| file | file | হ্যাঁ | আপলোড ফাইল |

**অনুমোদিত ফাইল টাইপ**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**সর্বোচ্চ ফাইল সাইজ**: 10MB

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

ফাইল তারিখ অনুযায়ী ডিরেক্টরিতে সংরক্ষিত হয় `public/upload/{Y-m-d}/`, ফাইল নাম হয় `md5(uniqid) + অরিজিনাল এক্সটেনশন`। `url` সাইট রুট পাথের সাপেক্ষে রিলেটিভ পাথ।

**সম্ভাব্য এরর**:
- 422: ফাইল নির্বাচন করুন (আপলোড করা হয়নি)
- 422: আনসাপোর্টেড ফাইল টাইপ
- 422: ফাইল সাইজ 10MB-এর বেশি হতে পারবে না
- 500: ফাইল আপলোড ব্যর্থ (ফাইল অবৈধ)

## 13. রেসপন্স হেডার

সব ইন্টারফেসে (গ্লোবাল মিডলওয়্যার লেয়ারে ইনজেক্ট) নিম্নলিখিত রেসপন্স হেডার থাকে:

| হেডার | বর্ণনা |
|----|------|
| `X-RateLimit-Limit` | রেট লিমিট সীমা (বার সংখ্যা) |
| `X-RateLimit-Remaining` | অবশিষ্ট রিকোয়েস্ট সংখ্যা |
| `X-RateLimit-Reset` | রেট লিমিট উইন্ডো রিসেট টাইমস্ট্যাম্প |
| `Retry-After` | শুধুমাত্র রেট লিমিট ট্রিগার হলে, অপেক্ষার সেকেন্ড সংখ্যা |
| `X-Content-Type-Options` | `nosniff` (webman ডিফল্ট, MIME স্নিফিং নিষিদ্ধ) |
| `X-Frame-Options` | `DENY` (webman-এর CORS মিডলওয়্যার/বেস কনফিগ থেকে) |

রেট লিমিট বিস্তারিত:
- ডিফল্ট গ্লোবাল লিমিট: ৬০ বার/মিনিট / IP+পাথ
- লগইন এন্ডপয়েন্ট `/api/auth/login`: ১০ বার/মিনিট
- রেজিস্টার এন্ডপয়েন্ট `/api/auth/register`: ৫ বার/মিনিট
- Redis অ্যাটমিক স্লাইডিং উইন্ডো অ্যালগরিদম (Lua ZSET) ব্যবহার করে, TOCTOU রেস কন্ডিশন এড়ায়
- Redis অনুপলব্ধ হলে fail open (পাস), রিকোয়েস্ট ব্লক করে না

## 14. অথেনটিকেশন ফ্লো

সম্পূর্ণ অথেনটিকেশন সিকোয়েন্স:

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

### JWT স্ট্রাকচার

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, ডিফল্ট TTL 7200 সেকেন্ড (JWT কনফিগ `default_expire` দিয়ে নিয়ন্ত্রিত)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, ডিফল্ট TTL 1209600 সেকেন্ড (JWT কনফিগ `refresh_expire` দিয়ে নিয়ন্ত্রিত, অর্থাৎ ১৪ দিন)

### নিরাপত্তা ব্যবস্থাপনা

- পাসওয়ার্ড `PASSWORD_BCRYPT` হ্যাশে সংরক্ষিত
- পাসওয়ার্ড ট্রান্সপোর্ট লেয়ারে AES-256-CBC-HMAC এনক্রিপ্ট হয় (ক্লায়েন্ট এনক্রিপ্ট → সার্ভার ডিক্রিপ্ট), প্লেইনটেক্সট ফলব্যাক কম্প্যাটিবল
- সংবেদনশীল ফিল্ড (phone, email, id_card) `erikwang2013/encryptable` দিয়ে ডেটাবেস লেয়ারে ট্রান্সপারেন্ট এনক্রিপ্ট/ডিক্রিপ্ট হয়
- API লেয়ারের ID `erikwang2013/hashids` দিয়ে এনক্রিপ্টেড ট্রান্সমিট হয়, অরিজিনাল snowflake ID সিকোয়েন্স প্রকাশ এড়ায়
- SecurityFilter গ্লোবালি XSS, SQL ইনজেকশন, পাথ ট্রাভার্সাল, কমান্ড ইনজেকশন স্ক্যান করে, একই IP ৫ বার/৬০ সেকেন্ড হলে ১৫ মিনিট টেম্পোরারি ব্ল্যাকলিস্ট
- সংবেদনশীল অপারেশন (ইউজার, রোল, পারমিশন, কনফিগ ডিলিট) বর্তমান লগইন ইউজারের পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন
- কনকারেন্ট সেশন সীমা: একই ইউজারের সর্বোচ্চ ৩টি সক্রিয় Token, ৪র্থ ডিভাইসে লগইন করলে সবচেয়ে পুরনো Token ব্ল্যাকলিস্টে বাধ্যতামূলক যায়
- অ্যাকাউন্ট লক: টানা ৫ বার লগইন ব্যর্থ হলে ১৫ মিনিটের অ্যাকাউন্ট লক ট্রিগার, লক থাকা অবস্থায় 429 রিটার্ন

### মিডলওয়্যার আর্কিটেকচার

গ্লোবাল মিডলওয়্যার সব রিকোয়েস্টে ক্রমানুসারে কাজ করে:

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

`/health` এবং `/api/docs` পাবলিক এন্ডপয়েন্ট, শুধুমাত্র `Cors → SecurityFilter → RateLimit` পেরিয়ে যায়।

নিরাপত্তা এনহ্যান্সমেন্ট:
- **অ্যাকাউন্ট লক**: টানা ৫ বার লগইন ব্যর্থ হলে অ্যাকাউন্ট স্বয়ংক্রিয়ভাবে ১৫ মিনিট লক হয়, এই সময়ে লগইনে 429 রিটার্ন
- **কনকারেন্ট সেশন সীমা**: একই ইউজারের সর্বোচ্চ ৩টি সক্রিয় Token, এর বেশি হলে সবচেয়ে পুরনো Token স্বয়ংক্রিয়ভাবে ব্ল্যাকলিস্টে যায়
- **security.txt**: `GET /.well-known/security.txt` RFC 9116 স্ট্যান্ডার্ড সিকিউরিটি কন্টাক্ট তথ্য দেয়
- **Nginx নিরাপত্তা কনফিগ**: `docs/nginx-security.conf` দেখে সম্পূর্ণ রিভার্স প্রক্সি নিরাপত্তা হার্ডেনিং উদাহরণ

### অপারেশন সোর্স ডিভাইস ডিটেকশন

OperationLog মিডলওয়্যার স্বয়ংক্রিয়ভাবে ক্লায়েন্ট প্ল্যাটফর্ম চিনে, অপারেশন লগের `source` ফিল্ডে লেখে:

| প্ল্যাটফর্ম | ডিটেকশন পদ্ধতি |
|------|---------|
| `ipados` | UA-তে iPad আছে |
| `macos` | UA-তে Macintosh/Mac OS আছে |
| `windows` | UA-তে Windows আছে |
| `linux` | UA-তে Linux আছে (Android ছাড়া) |
| `ios` | UA-তে iPhone / iOS / CFNetwork আছে |
| `android` | UA-তে Android আছে |
| `harmonyos` | UA-তে HarmonyOS / OpenHarmony আছে বা `X-Client-Platform` হেডারে স্পষ্ট ডিক্লেয়ার করা |
| `web` | ডিফল্ট (উপরের সব মেলেনি) |

> দুই স্তরের ডিটেকশন: `X-Client-Platform` রিকোয়েস্ট হেডার (নেটিভ অ্যাপ ডিক্লেয়ার) → User-Agent অটো ইনফার (ফলব্যাক)। অপারেশন লগ কোয়েরি `GET /admin/log`-এর `source` ফিল্ডই সোর্স ডিভাইস।

## 15. ডিপ্লয় ও অপারেশন

### Docker Compose

প্রজেক্ট রুটে `docker-compose.yml` দেওয়া আছে, ৫টি সার্ভিস অর্কেস্ট্রেট করে (Nginx, webman app, MySQL, Redis, Elasticsearch)। PHP `Dockerfile` দিয়ে বিল্ড হয় (`php:8.3-cli` ভিত্তিক, OPcache সক্রিয়)।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions কন্টিনিউয়াস ইন্টিগ্রেশন পাইপলাইন ডেফাইন করে:
- `php -l` সিনট্যাক্স চেক
- PHPUnit ইউনিট টেস্ট
- `flutter analyze` স্ট্যাটিক অ্যানালাইসিস

### ডেটাবেস ব্যাকআপ

`database/backup/` ডিরেক্টরিতে ব্যাকআপ ও রিস্টোর স্ক্রিপ্ট আছে:
- `backup.sh` — mysqldump + gzip কমপ্রেস ব্যাকআপ, ৩০ দিন আগের পুরনো ব্যাকআপ ফাইল স্বয়ংক্রিয় পরিষ্কার
- `restore.sh` — ইন্টারঅ্যাকটিভ রিস্টোর, বিদ্যমান ব্যাকআপ তালিকা দেখিয়ে ইউজার নির্বাচন

### Nginx নিরাপত্তা কনফিগারেশন

প্রোডাকশন ডিপ্লয়ে `docs/nginx-security.conf` দেখে রিভার্স প্রক্সি নিরাপত্তা হার্ডেনিং কনফিগার করুন।

## 16. অভ্যন্তরীণ gRPC ইন্টারফেস (e-cat → PHP worker)

শুধুমাত্র e-cat ক্যোয়ারি গেটওয়ের ব্যবহারের জন্য। PHP পাশে এটি একটি workerman h2c সার্ভিস (`tcp://0.0.0.0:8792`, প্রসেস `internal_grpc`)। কন্ট্র্যাক্টটি `infrastructure/tracking-gateway/proto/internal.proto` (package `internal.v1`)-এ সংজ্ঞায়িত, মেসেজগুলো protobuf-এ এনকোড করা হয় এবং metadata-তে `x-internal-token` থাকা আবশ্যক (মান `config/logistics.php`-এর `internal_token` থেকে, প্রোডাকশনে `INTERNAL_TOKEN` এনভায়রনমেন্ট ভেরিয়েবল দিয়ে ওভাররাইড করা হয়)। টোকেন অনুপস্থিত/ভুল হলে `code=401 UNAUTHORIZED` রিটার্ন হয়।

### 16.1 RPC সারসংক্ষেপ

| RPC | অনুরোধ | প্রতিক্রিয়া | বিবরণ |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | ক্যারিয়ার সনাক্তকরণ; সনাক্ত না হলে `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | ট্র্যাকিং ক্যোয়ারি; `credential_id` ঐচ্ছিক (একাধিক ক্রেডেনশিয়ালের ক্ষেত্রে নির্দিষ্ট করুন, অবশ্যই `carrier_code`-এর সাথে মিলতে হবে, না হলে `400 INVALID_PARAMS`)। প্রতিটি ক্যোয়ারি `logistics_tracking_query`-তে সংরক্ষিত হয় |
| `Carriers` | `{}` | `{code, carriers[]}` | রেজিস্ট্রির সব ক্যারিয়ার `{carrier_code, channel}` |

### 16.2 ত্রুটি কন্ট্র্যাক্ট (প্রতিক্রিয়ার `code` ফিল্ড, gRPC status সর্বদা 0)

| `code` | `error_code` | পরিস্থিতি |
|---|---|---|
| 400 | `INVALID_PARAMS` | প্যারামিটার অনুপস্থিত / credential_id অমিল |
| 401 | `UNAUTHORIZED` | অভ্যন্তরীণ টোকেন ভুল |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | ক্যারিয়ার নিবন্ধিত নয় / ট্র্যাকিং পাওয়া যায়নি / সনাক্ত করা যায়নি |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | আপস্ট্রিম ক্রেডেনশিয়াল ত্রুটি / নেটওয়ার্ক ত্রুটি / ক্যারিয়ার ব্যবসায়িক ত্রুটি (কাঠামোবদ্ধ আপস্ট্রিম বার্তা সহ, যেমন `[SF-INTERNATIONAL A1001] বাধ্যতামূলক প্যারামিটার খালি রাখা যাবে না`) |
| 500 | `INTERNAL_ERROR` | অভ্যন্তরীণ ব্যতিক্রম |


## 17. e-cat ক্যোয়ারি গেটওয়ে বাহ্যিক API

Rust হাই-ফ্রিকোয়েন্সি গেটওয়ে (`infrastructure/tracking-gateway`), `0.0.0.0:8080`-এ শোনে (ডিফল্ট), অনুরোধ হেডারে `X-API-Key` বহন করে (কনফিগ `api_keys`)। গেটওয়ে API-Key প্রমাণীকরণ, Redis ক্যাশ হিট (প্রিফিক্স `logistics:`), রেট লিমিট, প্রতি-ক্যারিয়ার সার্কিট ব্রেকার এবং RoundRobin worker ফরওয়ার্ডিং পরিচালনা করে; ক্রেডেনশিয়াল কখনো গেটওয়েতে নামে না।

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | স্বাস্থ্য পরীক্ষা (পাবলিক এন্ডপয়েন্ট, কী প্রয়োজন নেই) |
| `GET /v1/openapi.json` | OpenAPI 3.0 ডকুমেন্টেশন (পাবলিক এন্ডপয়েন্ট, কী প্রয়োজন নেই) |
| `GET /v1/carriers` | ক্যারিয়ার তালিকা (PHP gRPC `InternalService.Carriers`-এ ফরওয়ার্ড করে) |
| `POST /v1/tracking/query` | ট্র্যাকিং ক্যোয়ারি (`{"carrier_code": "...", "tracking_no": "..."}`); `carrier_code` অনুপস্থিত থাকলে গেটওয়ে স্বয়ংক্রিয়ভাবে সনাক্ত করে (detect, query-র ভিতরে সম্পন্ন হয়) |
| `GET /v1/tracking/{query_no}` | ক্যোয়ারি নম্বর দিয়ে শেষ ট্র্যাকিং ফলাফল |
| `POST /v1/subscriptions` | কলব্যাক সাবস্ক্রিপশন নিবন্ধন (`{"carrier_code", "callback_url", "event_type"?}`, `event_type` ডিফল্ট `tracking.update`) |


গেটওয়ে পাশের অতিরিক্ত শর্ত: `rate_limit` (ডিফল্ট 100 বার/60 সেকেন্ড) অতিক্রম করলে `429`; ক্যারিয়ারের ধারাবাহিক ব্যর্থতা সার্কিট ব্রেকার সক্রিয় করে (ডিফল্ট ৫টি 5xx/ট্রান্সপোর্ট ত্রুটির পর 60 সেকেন্ড OPEN) → `503`; worker অপ্রাপ্য → `502`। গেটওয়ে ও worker-এর মধ্যে অভ্যন্তরীণ gRPC (h2c, `0.0.0.0:8792`), `x-internal-token` শেয়ার্ড কী প্রমাণীকরণ, ধারা 16 দেখুন।

### 17.1 প্রতিক্রিয়া খাম ও ত্রুটি

সব এন্ডপয়েন্ট একীভূত খাম `{"code": 0, "message": "ok", "data": ...}` রিটার্ন করে; `code != 0` মানে ত্রুটি এবং `data` বাদ থাকে। ব্যবসায়িক ত্রুটির বডিতে `error_code` / `error_message` থাকতে পারে (আপস্ট্রিম ক্যারিয়ার ডায়াগনস্টিক, গেটওয়ে সেভাবে পাস করে); `code` HTTP স্ট্যাটাসের সাথে মেলে:

| HTTP | অর্থ |
|---|---|
| 400 | প্যারামিটার ত্রুটি (`tracking_no` অনুপস্থিত / সাবস্ক্রিপশন প্যারামিটার অবৈধ) |
| 401 | কী অনুপস্থিত বা অবৈধ |
| 404 | ক্যোয়ারি নম্বর নেই |
| 429 | রেট লিমিট (ডিফল্ট প্রতি কী 100/60 সেকেন্ড), ব্যাকঅফ দিয়ে পুনরায় চেষ্টা করুন |
| 502 | worker অপ্রাপ্য বা আপস্ট্রিম ব্যর্থতা |
| 503 | ক্যারিয়ার সার্কিট ব্রেকার, ব্যাকঅফ দিয়ে পুনরায় চেষ্টা করুন |

### 17.2 কল উদাহরণ (curl)

ডেমো কী `demo-api-key`, গেটওয়ে ডিফল্ট `http://127.0.0.1:8080`:

```bash
# স্বাস্থ্য পরীক্ষা (পাবলিক এন্ডপয়েন্ট, কী প্রয়োজন নেই)
curl http://127.0.0.1:8080/v1/health

# OpenAPI ডকুমেন্টেশন (পাবলিক এন্ডপয়েন্ট, কী প্রয়োজন নেই)
curl http://127.0.0.1:8080/v1/openapi.json

# ক্যারিয়ার তালিকা
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# ট্র্যাকিং ক্যোয়ারি (ক্যারিয়ার নির্দিষ্ট করা ঐচ্ছিক)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# ক্যোয়ারি নম্বর দিয়ে শেষ ফলাফল
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# কলব্যাক সাবস্ক্রিপশন নিবন্ধন (কলব্যাক ঠিকানা পাবলিক http/https হতে হবে; গেটওয়ে লুপব্যাক/প্রাইভেট নেটওয়ার্ক প্রত্যাখ্যান করে)
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 কল উদাহরণ (SDK)

রিপোজিটরির `sdk/` ডিরেক্টরিতে পাঁচটি জিরো-ডিপেন্ডেন্সি SDK আছে (Python / PHP / Node.js / Go / Rust, কপি করে চালান):

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

SDK খামে `code != 0` হলে বা নেটওয়ার্ক ব্যর্থতায় ব্যতিক্রম (`TrackingApiError`) ছোড়ে, যাতে `code` / `message` / `error_code` / `error_message` / `http_status` থাকে; রেট লিমিট ও সার্কিট ব্রেকার ত্রুটিতে ব্যাকঅফ দিয়ে পুনরায় চেষ্টা করুন, ক্যারিয়ার পাশের ত্রুটিতে পুনরায় চেষ্টা করবেন না। সম্পূর্ণ ব্যবহার `sdk/README.md`-তে।
