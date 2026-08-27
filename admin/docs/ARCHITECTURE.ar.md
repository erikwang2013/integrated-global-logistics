# مخطط العمارة ورسوم بيانية لمنطق الأعمال

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](ARCHITECTURE.md) | [English](ARCHITECTURE.en.md) | [한국어](ARCHITECTURE.ko.md) | [Русский](ARCHITECTURE.ru.md) | [Deutsch](ARCHITECTURE.de.md) | [Français](ARCHITECTURE.fr.md) | [Español](ARCHITECTURE.es.md) | [Português](ARCHITECTURE.pt.md) | [हिन्दी](ARCHITECTURE.hi.md) | [العربية](ARCHITECTURE.ar.md) | [বাংলা](ARCHITECTURE.bn.md) | [Bahasa Indonesia](ARCHITECTURE.id.md) | [日本語](ARCHITECTURE.ja.md)

> تُعرض رسوم Mermaid البيانية أدناه تلقائيًا في GitHub / GitLab / VS Code. للبيئات الأخرى استخدم [Mermaid Live Editor](https://mermaid.live/) لعرضها.

---

## 1. طوبولوجيا النظام

```mermaid
flowchart TB
    subgraph "طبقة العميل"
        A1["Flutter Web<br/>لوحة إدارة PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>عميل الهاتف/الجهاز اللوحي"]
    end

    subgraph "طبقة البوابة/الحدود (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>وكيل عكسي + HTTPS + Gzip<br/>خدمة الملفات الثابتة"]
    end

    subgraph "طبقة التطبيق (webman v2)"
        C0["وسيط ApiVersion<br/>التحقق من ترويسة API-Version"]
        C1["وسيط AdminAuth<br/>التحقق من JWT"]
        C2["وسيط AdminPermission<br/>التحقق من صلاحيات RBAC"]
        C3["وحدات تحكم الإدارة<br/>Dashboard / User / Role / Permission"]
        C4["وحدات تحكم عامة v1<br/>Captcha / Auth"]
        C5["الخدمات المشتركة<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "طبقة التخزين"
        D1[("MySQL 8.0<br/>التخزين الرئيسي<br/>بادئة الجداول erik_")]
        D2[("Elasticsearch<br/>البحث النصي الكامل<br/>بادئة الفهارس erik_")]
        D3[("Redis<br/>Session / التخزين المؤقت<br/>تخزين Captcha")]
    end

    subgraph "خارجي"
        E1["DevEco Studio<br/>بناء HarmonyOS"]
        E2["Flutter SDK<br/>بناء الويب"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. العمارة الطبقية للخلفية

```mermaid
flowchart TD
    subgraph "طبقة المسارات Route Layer"
        R1["config/route.php<br/>تعيين URL → Controller"]
    end

    subgraph "طبقة الوسائط Middleware Layer"
        M_RL["RateLimit<br/>تحديد معدل Redis بنافذة منزلقة<br/>ترويسات استجابة X-RateLimit"]
        M_SF["SecurityFilter<br/>اعتراض هجمات الكشف<br/>XSS/حقن SQL/اجتياز المسار/CSRF"]
        M0["ApiVersion<br/>التحقق من إصدار API<br/>حقن apiVersion"]
        M1["AdminAuth<br/>التحقق من رمز JWT<br/>حقن adminId"]
        M2["AdminPermission<br/>تحقق RBAC<br/>مطابقة method.path<br/>ذاكرة Redis مؤقتة للصلاحيات 60s"]
    end

    subgraph "طبقة وحدات التحكم Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + بحث + ترقيم صفحات"]
        CT3["RoleController<br/>CRUD + مزامنة الصلاحيات"]
        CT4["PermissionController<br/>CRUD + بناء الشجرة"]
        CT5["DashboardController<br/>إحصائيات/اتجاهات/توزيعات"]
        CT6["ExportController<br/>تصدير Excel/PDF"]
        CT7["CaptchaController<br/>توليد/تحقق كود التحقق"]
        CT8["AuthController<br/>دخول/تسجيل/تحديث"]
    end

    subgraph "طبقة الخدمات Service Layer"
        S1["HashidsService<br/>ترميز وفك ترميز المعرفات"]
        S2["SnowflakeService<br/>توليد معرفات فريدة عالميًا"]
        S3["EncryptionService<br/>تشفير وفك تشفير + إخفاء"]
    end

    subgraph "طبقة النماذج Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "طبقة السائقين Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

---

## 3. دورة حياة الطلب

```mermaid
sequenceDiagram
    participant C as العميل
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: طلب HTTPS<br/>Header: API-Version: v1, Accept-Language: zh_CN
    N->>MW_LOC: إعادة توجيه

    MW_LOC->>MW_LOC: locale = zh_CN (Accept-Language / ?lang=)
    MW_LOC->>MW_SF: تمرير

    alt طريقة HTTP غير قياسية (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else الطريقة قانونية (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: اجتاز فحص القائمة البيضاء للطرق
    end

    alt تفعيل كشف الهجوم
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: تمرير

    alt تفعيل تحديد المعدل
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: تمرير

    alt إصدار غير مدعوم
        MW0-->>C: 400 إصدار API غير مدعوم
    else الإصدار صالح
        MW0->>MW0: $request->apiVersion = v1
    end

    alt الرمز مفقود أو غير صالح
        MW1-->>C: 401 Unauthorized
    else الرمز صالح
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt لا صلاحية
        MW2-->>C: 403 Forbidden
    else لديه صلاحية
        MW2->>CTL: دخول وحدة التحكم
    end

    CTL->>CTL: التحقق من المعاملات (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt عملية حساسة (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt كلمة مرور خاطئة
            CTL-->>C: 422 فشل التحقق من كلمة المرور
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: فك تشفير تلقائي عبر encryptable cast
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: بناء استجابة JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: تسجيل سجل العملية (POST/PUT/DELETE)
```

---

## 4. عملية المصادقة وكود التحقق

```mermaid
sequenceDiagram
    participant U as المستخدم
    participant CL as العميل
    participant SV as الخادم
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === الخطوة الأولى: الحصول على كود التحقق ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: توليد صورة خلفية 300×200
    CAP->>CAP: وضع N أهداف صينية عشوائيًا
    CAP->>CAP: توليد key وتخزين targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === الخطوة الثانية: نقر المستخدم ===
    CL->>CL: عرض صورة كود التحقق
    CL->>CL: تلميح "انقر بالترتيب: شجرة ← طائر ← زهرة"
    U->>CL: النقر على مواضع النص في الصورة بالترتيب
    CL->>CL: جمع clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === الخطوة الثالثة: تسجيل الدخول ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt كود تحقق خاطئ
        CAP-->>SV: false
        SV-->>CL: 422 خطأ في كود التحقق
    else كود التحقق صحيح
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt بيانات اعتماد خاطئة
            SV-->>CL: 401 اسم المستخدم أو كلمة المرور خاطئة
        else البيانات صحيحة
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === الطلبات اللاحقة ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. نموذج صلاحيات RBAC

```mermaid
flowchart LR
    subgraph "المستخدمون User"
        U1["admin<br/>(مدير فائق)"]
        U2["editor<br/>(محرر)"]
        U3["viewer<br/>(قراءة فقط)"]
    end

    subgraph "الأدوار Role"
        R1["super_admin<br/>معرّف الصلاحية: *"]
        R2["editor<br/>معرّف الصلاحية: get.*, post.*"]
        R3["viewer<br/>معرّف الصلاحية: get.*"]
    end

    subgraph "الصلاحيات Permission (شجرة)"
        P1["dashboard<br/>type=1 قائمة"]
        P2["user<br/>type=1 قائمة"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 زر"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (كل الصلاحيات)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "أنواع الصلاحيات"
        T1["type=1 قائمة<br/>تتحكم في إظهار/إخفاء الشريط الجانبي"]
        T2["type=2 زر<br/>تتحكم في أزرار عمليات الصفحة"]
        T3["type=3 API<br/>تتحكم في الوصول إلى الواجهات"]
    end

    subgraph "تنسيق معرّف الصلاحية"
        F1["{method}.{path}<br/>مثال: get.admin/user<br/>مثال: post.admin/user<br/>مثال: delete.admin/role"]
    end

    subgraph "عملية الحكم"
        J1["استخراج Token → adminId"]
        J2["البحث عن أدوار المستخدم"]
        J3["جمع جميع slugs الصلاحيات"]
        J4["بناء method.path"]
        J5{"مطابق؟"}
        J6["تمرير"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"نعم / slug=*"| J6
        J5 -->|"لا"| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. دورة حياة المعرف بالكامل

```mermaid
flowchart LR
    subgraph "1. التوليد"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>مثال: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. التخزين"
        S1["جداول MySQL erik_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["الحقول الحساسة<br/>encryptable cast<br/>تشفير AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. النقل"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["سلسلة hashid<br/>مثال: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. فك الترميز العكسي"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. طبقات تشفير البيانات

```mermaid
flowchart TB
    subgraph "تشفير طبقة النقل (encryption)"
        E1["إرسال العميل للبيانات الحساسة"]
        E2["تشفير AES-256-CBC"]
        E3["نقل النص المشفر عبر API"]
        E4["فك تشفير الخادم ومعالجتها"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "تشفير طبقة التخزين (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["الكتابة: تشفير تلقائي"]
        D3["MySQL VARCHAR(500)<br/>تخزين النص المشفر"]
        D4["القراءة: فك تشفير تلقائي"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "إخفاء طبقة العرض (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. علاقة ER لقاعدة البيانات

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "مشفّر"
        VARCHAR phone "مشفّر"
        VARCHAR id_card "مشفّر"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "حذف ناعم"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "إحالة ذاتية"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1قائمة 2زر 3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erik_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "جهة المصدر"
        TEXT input "مموّه"
        DATETIME created_at
    }

    erik_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : "user_id"
    erik_admin_role ||--o{ erik_admin_user_role : "role_id"
    erik_admin_role ||--o{ erik_admin_role_permission : "role_id"
    erik_admin_permission ||--o{ erik_admin_role_permission : "permission_id"
    erik_admin_user ||--o{ erik_operation_log : "user_id"
    erik_admin_permission ||--o{ erik_admin_permission : "parent_id"
```

---

## 9. عملية التصدير

```mermaid
sequenceDiagram
    participant C as العميل
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as نظام الملفات

    Note over C,FS: === تصدير Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: البيانات
    CTL->>CTL: فك تشفير الحقول الحساسة
    CTL->>CTL: معالجة الإخفاء (maskPhone/maskEmail)
    CTL->>CTL: بناء PhpSpreadsheet<br/>رأس أزرق بنص أبيض<br/>حدود رفيعة لصفوف البيانات<br/>تجميد الصف الأول<br/>فلترة تلقائية
    CTL->>FS: كتابة runtime/tmp/export_*.xlsx
    CTL-->>C: تنزيل الملف

    Note over C,FS: === تصدير PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>رأس الصفحة: العنوان+حقوق النشر+الوقت<br/>المحتوى: جدول أو بطاقات<br/>تذييل الصفحة: حقوق نشر غير قابلة للإزالة
    CTL->>CTL: عرض Dompdf A4 أفقي
    CTL->>FS: كتابة runtime/tmp/export_*.pdf
    CTL-->>C: تنزيل الملف
```

---

## 10. شجرة مكونات Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["نموذج الدخول<br/>اسم المستخدم/كلمة المرور/كود التحقق"]
    LF --> CAPTCHA["مكون كود التحقق بالنقر<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Circle لعلامة النقر"]

    DB --> SIDEBAR["الشريط الجانبي NavigationDrawer<br/>قابل للطي 64px / 240px<br/>لوحة التحكم/المستخدمون/الأدوار/الإعدادات/السجلات"]
    DB --> HEADER["الشريط العلوي 56px<br/>زر الطي + قائمة المستخدم<br/>AlertDialog لتسجيل الخروج"]
    DB --> CONTENT["منطقة المحتوى"]
    CONTENT --> DASH["DashboardPage<br/>بطاقات إحصائية GridView<br/>رسم خطي للاتجاهات LineChart<br/>رسم دائري للتوزيع PieChart<br/>آخر العمليات ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. توجيه صفحات HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>الإقلاع"]
    EA -->|"بدون Token"| LP["LoginPage<br/>صفحة الدخول"]
    EA -->|"لديه Token"| DP["DashboardPage<br/>لوحة التحكم"]

    LP -->|"نجاح الدخول<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>قائمة المستخدمين"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>الملف الشخصي"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>تفاصيل/إضافة/تعديل المستخدم"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"تسجيل الخروج<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. بانوراما الدفاع المتعمق الأمني

```mermaid
flowchart TB
    subgraph "الطبقة 1: التحقق بين الإنسان والآلة"
        L1["كود تحقق بالنقر<br/>Click Captcha<br/>إلزامي للدخول/التسجيل"]
    end

    subgraph "الطبقة 2: تأكيد العملية"
        L2["تأكيد كلمة المرور مرتين<br/>confirmPassword()<br/>إلزامي لعمليات DELETE"]
    end

    subgraph "الطبقة 3: أمان النقل"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "الطبقة 4: التحقق من الهوية"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "الطبقة 5: تحقق الصلاحيات"
        L5["RBAC<br/>بدقة method.path<br/>المدير الفائق *"]
    end

    subgraph "الطبقة 6: حماية البيانات"
        L6["معرفات الواجهات: تشفير Hashids<br/>جسم الطلب: تشفير Encryption<br/>طبقة التخزين: تشفير Encryptable<br/>التصدير: إخفاء + حقوق نشر"]
    end

    subgraph "الطبقة 7: التدقيق والتتبع"
        L7["OperationLog<br/>تسجيل جميع العمليات<br/>المستخدم/IP/الوقت/جهة المصدر/المعاملات"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. طوبولوجيا النشر

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "خوادم الويب"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 إعادة توجيه<br/>gzip on"]
        STA["الملفات الثابتة<br/>Flutter Web build/"]
    end

    subgraph "خوادم التطبيق (قابلة للتوسع الأفقي)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "طبقة البيانات"
        MYSQL["MySQL 8.0<br/>نسخ رئيسي-تابع<br/>بادئة erik_"]
        ES["Elasticsearch 8.x<br/>مجموعة من 3 عقد<br/>بادئة erik_"]
        REDIS["Redis 7.x<br/>وضع الحارس<br/>poster:captcha:*"]
    end

    subgraph "المراقبة"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```
