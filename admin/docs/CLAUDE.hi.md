# 开放管理后台 (open-admin) — ओपन एडमिन पैनल

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

webman v2 + Flutter पर आधारित पूर्ण-स्टैक प्रशासन पैनल प्रणाली (open-admin)।

## कॉपीराइट घोषणा

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **संशोधन नहीं किया जा सकता、नहीं हटाया जा सकता、अपरिवर्तनीय。** सभी नई फ़ाइलों के हेडर में उपरोक्त कॉपीराइट घोषणा होनी चाहिए।

## फीचर सूची

| डोमेन | फीचर |
|----|------|
| प्रमाणीकरण | लॉगिन/रजिस्ट्रेशन/रीफ़्रेश/लॉगआउट + कैप्चा + खाता लॉक + सत्र सीमा |
| डैशबोर्ड | रीयल-टाइम आँकड़े/ट्रेंड/वितरण/लॉग（Redis 5m कैश）|
| उपयोगकर्ता | CRUD + बैच डिलीट/सक्षम-अक्षम + Excel आयात |
| भूमिका-अनुमति | CRUD + अनुमति ट्री + RBAC method.path प्रमाणीकरण |
| सिस्टम कॉन्फ़िगरेशन | कुंजी-मूल्य जोड़ी CRUD |
| ऑपरेशन ऑडिट | लॉग क्वेरी + 8 प्लेटफ़ॉर्म स्रोत डिवाइस स्वचालित पहचान |
| फ़ाइलें | अपलोड + Excel/PDF निर्यात（संवेदनशील डेटा मास्किंग）|
| सुरक्षा | 18 परत गहन सुरक्षा（XSS/SQL इंजेक्शन/CSRF/रेट लिमिट/CSP...）|
| संचालन | स्वास्थ्य जाँच/Prometheus मेट्रिक्स/API दस्तावेज़/security.txt + Docker + CI/CD |

## तकनीकी स्टैक

### बैकएंड
- PHP 8.3+, webman v2 (workerman/webman)
- डेटाबेस: MySQL 8.0+, टेबल प्रीफ़िक्स `logistics_`
- प्राथमिक कुंजी: BIGINT गैर-ऑटोइन्क्रीमेंट, `erikwang2013/snowflake-php` द्वारा जनरेट
- API परत ID एन्क्रिप्शन/डिक्रिप्शन: `erikwang2013/hashids`
- JWT प्रमाणीकरण: `erikwang2013/jwt-webman`
- API संवेदनशील डेटा एन्क्रिप्शन/डिक्रिप्शन: `erikwang2013/encryption`
- डेटाबेस संवेदनशील फ़ील्ड एन्क्रिप्शन/डिक्रिप्शन: `erikwang2013/encryptable`
- ES सिंक और क्वेरी: `erikwang2013/webman-scout`
- देश ध्वज: `erikwang2013/season`

### फ्रंटएंड
- Flutter 3.x, स्रोत डायरेक्टरी `apps/flutter/`
- वेब संस्करण PC एडमिन पैनल शैली में डिज़ाइन किया गया (मोबाइल App शैली नहीं)
- क्लाइंट और एडमिन संस्करण समर्थित
- HarmonyOS ArkTS, स्रोत डायरेक्टरी `apps/harmonyos/`

## प्रोजेक्ट संरचना

```
open-admin/
├── app/
│   ├── admin/controller/       # एडमिन साइड कंट्रोलर (14)
│   │   ├── BaseController.php      # बेस कंट्रोलर
│   │   ├── DashboardController.php # डैशबोर्ड（Redis कैश）
│   │   ├── UserController.php      # उपयोगकर्ता CRUD + बैच ऑपरेशन
│   │   ├── RoleController.php      # भूमिका CRUD
│   │   ├── PermissionController.php# अनुमति CRUD
│   │   ├── ConfigController.php    # सिस्टम कॉन्फ़िगरेशन CRUD
│   │   ├── LogController.php       # ऑपरेशन लॉग क्वेरी
│   │   ├── ProfileController.php   # प्रोफ़ाइल केंद्र + लॉगआउट
│   │   ├── ExportController.php    # Excel/PDF निर्यात
│   │   ├── ImportController.php    # Excel उपयोगकर्ता आयात
│   │   ├── UploadController.php    # फ़ाइल अपलोड
│   │   ├── HealthController.php    # स्वास्थ्य जाँच
│   │   ├── DocsController.php      # OpenAPI दस्तावेज़
│   │   └── MetricsController.php   # Prometheus मॉनिटरिंग मेट्रिक्स
│   ├── api/v1/controller/      # API v1 कंट्रोलर（संस्करण हेडर नियंत्रण）
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # सार्वजनिक उपयोगिता क्लास
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # सार्वजनिक परिभाषाएँ（Apidoc Definitions सहित）
│   ├── middleware/             # मिडलवेयर（8）
│   │   ├── Cors.php            # क्रॉस-ओरिजिन（ग्लोबल）
│   │   ├── SecurityFilter.php  # अटैक इंटरसेप्शन（ग्लोबल：XSS/SQL इंजेक्शन/पाथ ट्रैवर्सल/कमांड इंजेक्शन/CSRF）
│   │   ├── RateLimit.php       # Redis रेट लिमिट（ग्लोबल，Lua एटॉमिक）
│   │   ├── ApiVersion.php      # API संस्करण सत्यापन
│   │   ├── AdminAuth.php       # JWT प्रमाणीकरण + ब्लैकलिस्ट
│   │   ├── AdminPermission.php # RBAC अनुमति सत्यापन（Redis 60s कैश）
│   │   └── OperationLog.php    # ऑपरेशन लॉग स्वचालित रिकॉर्डिंग（स्रोत डिवाइस पहचान सहित）
│   ├── model/                  # डेटा मॉडल
│   ├── queue/                  # क्यू टास्क
│   └── process/                # प्रोसेस (Http, Monitor)
├── apps/
│   ├── flutter/                # Flutter Web एडमिन पैनल
│   │   └── lib/app/
│   │       ├── pages/          # 6 पूर्ण पेज
│   │       │   ├── dashboard/  # डैशबोर्ड
│   │       │   ├── login/      # लॉगिन
│   │       │   ├── user/       # उपयोगकर्ता प्रबंधन
│   │       │   ├── role/       # भूमिका-अनुमति
│   │       │   ├── config/     # सिस्टम कॉन्फ़िगरेशन
│   │       │   ├── log/        # ऑपरेशन लॉग
│   │       │   └── profile/    # प्रोफ़ाइल केंद्र
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # रिस्पॉन्सिव लेआउट
│   │       └── theme/          # Material 3 थीम
│   └── harmonyos/              # HarmonyOS क्लाइंट
├── config/                     # कॉन्फ़िगरेशन फ़ाइलें
│   ├── route.php               # रूट + API संस्करण नीति
│   └── middleware.php           # ग्लोबल मिडलवेयर रजिस्ट्रेशन
├── database/
│   ├── install.sql             # पूर्ण इंस्टॉल स्क्रिप्ट（सभी SQL मर्ज）
│   └── backup/                 # डेटाबेस बैकअप स्क्रिप्ट
│       ├── backup.sh           # mysqldump+gzip，30 दिन रिटेंशन
│       └── restore.sh          # इंटरैक्टिव रिस्टोर
├── docs/                       # दस्तावेज़
│   ├── ARCHITECTURE.md         # Mermaid आर्किटेक्चर आरेख
│   ├── DESIGN.md               # डिज़ाइन दस्तावेज़
│   ├── SECURITY.md             # सुरक्षा आर्किटेक्चर डिज़ाइन
│   ├── API.md                  # API संदर्भ दस्तावेज़
│   ├── nginx-security.conf     # Nginx सुरक्षा संदर्भ कॉन्फ़िगरेशन
│   ├── diagrams/               # विघटित आर्किटेक्चर आरेख
│   └── superpowers/            # मानक और योजनाएँ
│       ├── specs/              # डिज़ाइन मानक
│       └── plans/              # कार्यान्वयन योजनाएँ
├── public/                     # सार्वजनिक एंट्री
├── runtime/                    # रनटाइम फ़ाइलें
├── tests/                      # टेस्ट
├── vendor/                     # Composer निर्भरताएँ
├── CLAUDE.md                   # यह फ़ाइल
├── README.md                   # चीनी विवरण
├── docs/translations/README.en.md                # अंग्रेज़ी विवरण
├── docs/translations/README.ko.md ... README.ja.md  # बहुभाषी विवरण（कोरियाई/रूसी/जर्मन/फ्रेंच/स्पेनिश/पुर्तगाली/हिंदी/अरबी/बांग्ला/इंडोनेशियाई/जापानी）
├── .env                        # पर्यावरण चर（वर्जन कंट्रोल में नहीं）
├── .env.example                # पर्यावरण चर टेम्पलेट
├── .env.docker                 # Docker पर्यावरण चर
├── composer.json               # PHP निर्भरताएँ
├── Dockerfile                  # Docker बिल्ड
├── docker-compose.yml          # Docker ऑर्केस्ट्रेशन
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD पाइपलाइन（PHP सिंटैक्स+PHPUnit+Flutter analyze）
```

## मिडलवेयर निष्पादन चेन

```
ग्लोबल:  Cors → Locale(Accept-Language) → SecurityFilter(मेथड जाँच→405) → RateLimit → {रूट मिडलवेयर}
/admin: Cors → Locale(Accept-Language) → SecurityFilter(मेथड जाँच→405) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityFilter(मेथड जाँच→405) → RateLimit → ApiVersion → Controller
/health: Cors → Locale(Accept-Language) → SecurityFilter(मेथड जाँच→405) → RateLimit → Controller
```

## सुरक्षा संवर्द्धन

- **HTTP मेथड सीमा**: SecurityFilter केवल GET/POST/PUT/DELETE/OPTIONS/HEAD की अनुमति देता है, गैर-मानक मेथड 405 लौटाता है
- **CSP हेडर**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies सभी रिस्पॉन्स में इंजेक्ट
- **खाता लॉक**: लगातार 5 बार लॉगिन विफल, खाता 15 मिनट के लिए लॉक
- **समवर्ती सत्र सीमा**: एक ही उपयोगकर्ता अधिकतम 3 मान्य टोकन, अधिक होने पर सबसे पुराना टोकन ब्लैकलिस्ट में
- **security.txt**: `/.well-known/security.txt` RFC 9116 एंडपॉइंट
- **Nginx सुरक्षा कॉन्फ़िगरेशन**: `docs/nginx-security.conf` रिवर्स प्रॉक्सी सुरक्षा हार्डनिंग संदर्भ

## API संस्करण नीति

संस्करण रिक्वेस्ट हेडर `API-Version` द्वारा नियंत्रित होता है（डिफ़ॉल्ट `v1`），URL में नहीं दिखता：

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

नया संस्करण जोड़ने के लिए केवल `app/api/{version}/controller/` डायरेक्टरी बनाकर `ApiVersion` मिडलवेयर में रजिस्टर करें।

## रेट लिमिट नीति

Redis स्लाइडिंग विंडो（Lua एटॉमिक），डिफ़ॉल्ट 60 बार/मिनट/IP/रूट：
- लॉगिन: 10 बार/मिनट
- रजिस्ट्रेशन: 5 बार/मिनट
- रिस्पॉन्स हेडर: `X-RateLimit-Limit/Remaining/Reset`，सीमा पार होने पर `Retry-After` जुड़ता है

## कोड मानक

### PHP
- ग्लोबल फ़ंक्शन/क्लास संदर्भ में आगे `\` नहीं जोड़ें, `use` इम्पोर्ट करें
- कॉन्फ़िगरेशन फ़ाइलों में हर कॉन्फ़िगरेशन आइटम का अर्थ समझाने वाली चीनी टिप्पणियाँ होनी चाहिए
- सभी नई `.php` फ़ाइलों के हेडर में कॉपीराइट घोषणा होनी चाहिए

### डेटाबेस
- टेबल प्रीफ़िक्स: `logistics_`
- प्राथमिक कुंजी `id`: BIGINT प्रकार, गैर-ऑटोइन्क्रीमेंट, snowflake द्वारा जनरेट
- संवेदनशील फ़ील्ड `erikwang2013/encryptable` trait से स्वचालित एन्क्रिप्शन/डिक्रिप्शन
- माइग्रेशन फ़ाइलें SQL फॉर्मेट में

### Flutter
- वेब संस्करण लेआउट PC एडमिन पैनल शैली（साइडबार + टॉपबार + कंटेंट एरिया）
- GetX स्टेट मैनेजमेंट，`ApiService` सिंगलटन（Dio + JWT इंटरसेप्टर）
- टोकन पर्सिस्टेंस `shared_preferences` से
- रिस्पॉन्सिव ब्रेकपॉइंट: मोबाइल (< 768px) और डेस्कटॉप (>= 768px)

### HarmonyOS
- `@ohos.net.http` नेटिव HTTP क्लाइंट उपयोग करें
- टोकन सीमलेस रीफ़्रेश：401 पर स्वचालित रूप से `/api/auth/refresh` कॉल
- रीफ़्रेश विफल होने पर स्वचालित रूप से लॉगिन पेज पर रीडायरेक्ट

## डिप्लॉयमेंट

### Docker Compose（प्रोडक्शन के लिए अनुशंसित）

प्रोजेक्ट रूट `docker-compose.yml` 5 सेवाओं का ऑर्केस्ट्रेशन करता है：

| सेवा | विवरण |
|------|------|
| `nginx` | Nginx रिवर्स प्रॉक्सी（80/443），स्टैटिक फ़ाइल सेवा |
| `app` | webman PHP 8.3 एप्लिकेशन，`Dockerfile` बिल्ड（OPcache सहित） |
| `mysql` | MySQL 8.0，डेटा वॉल्यूम पर्सिस्टेंस |
| `redis` | Redis 7 Alpine，कैश/रेट लिमिट/Session |
| `elasticsearch` | Elasticsearch 8.x，फुल-टेक्स्ट सर्च |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions पाइपलाइन परिभाषित करता है：

- PHP सिंटैक्स जाँच (`php -l`)
- PHPUnit यूनिट टेस्ट
- Flutter स्टैटिक विश्लेषण (`flutter analyze`)

### डेटाबेस बैकअप

`database/backup/backup.sh` — mysqldump + gzip，30 दिन पुराने बैकअप स्वचालित सफाई।
`database/backup/restore.sh` — इंटरैक्टिव रिस्टोर，उपलब्ध बैकअप सूचीबद्ध करके चुनने की सुविधा।

### मॉनिटरिंग

`GET /metrics` एंडपॉइंट（`MetricsController`）Prometheus text format आउटपुट करता है，5 gauge मेट्रिक्स सहित：
- `openadmin_http_requests_total` — रिक्वेस्ट कुल संख्या
- `openadmin_active_users` — सक्रिय उपयोगकर्ता संख्या
- `openadmin_db_connection_status` — डेटाबेस कनेक्शन स्थिति (0/1)
- `openadmin_redis_connection_status` — Redis कनेक्शन स्थिति (0/1)
- `openadmin_memory_usage_bytes` — मेमोरी उपयोग मात्रा
