# منصة تجميع اللوجستيات (Integrated Global Logistics)
<img src="../../diagrams/mascot.svg" alt="E-Cat 项目吉祥物" width="220" align="right">

منصة شاملة للاستعلام عن تتبع الشحنات عالميًا: **لوحة الإدارة admin** (PHP webman + Flutter) تتولى جانب الإدارة ومجموعة عمال الاستعلام، **البوابة عالية التردد e-cat** (عملية مقيمة بلغة Rust) تتحمل حركة الاستعلامات، و**الواجهة الموحدة global-logistics** (محولات PHP لـ 209 شركات شحن) تستعلم عن العالم كله عبر مدخل واحد.

> اللغات: [[English]](/docs/translations/en/README.md) · [[한국어]](/docs/translations/ko/README.md) · [[Русский]](/docs/translations/ru/README.md) · [[Deutsch]](/docs/translations/de/README.md) · [[Français]](/docs/translations/fr/README.md) · [[Español]](/docs/translations/es/README.md) · [[Português]](/docs/translations/pt/README.md) · [[हिन्दी]](/docs/translations/hi/README.md) · [[العربية]](/docs/translations/ar/README.md) · [[বাংলা]](/docs/translations/bn/README.md) · [[Bahasa Indonesia]](/docs/translations/id/README.md) · [[日本語]](/docs/translations/ja/README.md)（[انتقل إلى الترجمات](#الترجمات)）

## مقدمة المشروع

<img src="diagrams/intro.svg" alt="مقدمة المشروع" width="100%">

تجمّع المنصة تتبع **209** شركات شحن وبريد من حول العالم في منصة واحدة: يرسل التاجر والمستخدم النهائي رقم تتبع واحدًا فقط، وتتعرف المنصة تلقائيًا على القناة (محلية/دولية) والشركة — دون القلق بشأن اختلافات البروتوكولات (التوقيع، OAuth2، XML/JSON، تخطيط الحالات).

تتكون المنصة من ثلاثة مكونات تعمل معًا:

- **admin** (لوحة الإدارة، PHP webman v2 + Flutter) — جانب الإدارة ومجموعة عمال PHP: سجل الشركات، إدارة المفاتيح المشفّرة، سجل الاستعلامات، التقارير الإحصائية، إعداد اشتراكات الاستدعاء؛ نظام RBAC / JWT / تدقيق عمليات مكتمل؛
- **tracking-gateway** (البوابة عالية التردد، إطار Rust e-cat) — المدخل الأول لواجهة الاستعلام الخارجية: ذاكرة Redis، تحديد المعدل، قاطع دائرة لكل شركة، موازنة أحمال العمال؛ للتردد العالي فقط ولا يفهم بروتوكولات الشركات؛
- **global-logistics** (الواجهة الموحدة، حزمة PHP) — محولات 209 شركات (45 محلية + 164 دولية)، 187 قاعدة تعرّف تلقائي لأرقام التتبع، 7 دلالات حالات موحدة في `TrackStatus`.

**التقدم الحالي**: اكتمل M1–M13 بالكامل — M1 لوحة الإدارة (CRUD الناقل / بيانات الاعتماد / سجل الاستعلام / الاشتراك)، M2 بوابة الاستعلام (سلسلة واجهة برمجة التطبيقات الخارجية الكاملة)، M3 اشتراكات رد الاتصال، M4 المراقبة والإحصائيات، M5 توثيق OpenAPI الخارجي، M6 خمسة SDKs للعميل، M7 بوابة العميل (تسجيل / تطبيق / خطة / طلب)، M8 المدفوعات (Stripe / PayPal)، M9 العملات الرقمية (USDT TRC20 / BEP20 / ERC20)، M10 تكوين طرق الدفع، M11 الوسيطة الأمنية للبوابة، M12 خطة CDN (Cloudflare + رؤوس التخزين المؤقت)، M13 إدارة مزودي CDN. سلسلة استعلام التتبع العميل ← e-cat ← worker ← الناقل قابلة للعرض، وخمسة SDKs بدون تبعيات جاهزة للنسخ والاستخدام.

## وصف المشروع

<img src="diagrams/description.svg" alt="وصف المشروع" width="100%">

- **مدخل واحد**: `Logistics::track($trackingNo)` يتعرف تلقائيًا على القناة المحلية/الدولية والشركة؛ لا تتعامل طبقة الأعمال إلا مع شكل واحد؛
- **التعرّف التلقائي**: 187 قاعدة regex حساسة للترتيب، مع أولوية للقنوات المحلية؛ وعند تعذّر التعرّف يمكن الاستدعاء الصريح `domestic()` / `international()`؛
- **حالات موحدة**: تُخطَّط الحالات الأصلية المتنوعة إلى تعداد `TrackStatus` الموحد (بانتظار الاستلام / قيد النقل / قيد التوصيل / تم التسليم / استثناء / إرجاع / غير معروف)؛
- **تغطية عالمية**: الشحن السريع الأربعة DHL وFedEx وUPS وUSPS وأنظمة S10 للبريد الوطني (أربع مناطق: أوروبا، أمريكا اللاتينية والكاريبي، أفريقيا والشرق الأوسط، آسيا والمحيط الهادئ)؛
- **واجهة برمجة التطبيقات الخارجية**: توفر بوابة e-cat مصادقة API-Key، ونتائج ذاكرة التخزين المؤقت Redis (`X-Cache: HIT`)، وتحديد المعدل 429، وقاطع الدائرة حسب الناقل 503، وموازنة تحميل RoundRobin للعمال؛ خمسة SDKs بدون تبعيات (Python / PHP / Node.js / Go / Rust) جاهزة للنسخ والاستخدام؛
- **بوابة العميل والفواتير**（M7–M10）：تسجيل / تسجيل دخول العميل (JWT العميل معزول عن admin)، إدارة التطبيقات مع X-API-Key ذاتي الضبط، واجهة الخطط / الطلبات؛ مدفوعات Stripe / PayPal + العملات الرقمية USDT TRC20 / BEP20 / ERC20، طرق دفع Stripe (Apple Pay / Google Pay / Klarna / SEPA وغيرها) قابلة للتكوين بدون كود؛
- **تسريع CDN**（M12/M13）：خطة Cloudflare المجانية HTTPS كامل + تخزين مؤقت للحافة للأصول الثابتة، مزودو CDN / النطاقات / المفاتيح قابلة للتكوين في لوحة الإدارة (المفاتيح مشفّرة)؛
- **صفر مفاتيح مدمجة**: تُحقن جميع المفاتيح عبر الإعدادات، وتُخزَّن مشفّرة في قاعدة البيانات عبر Encryptable؛ الكود والمفاتيح منفصلان تمامًا.

## بنية المشروع

<img src="diagrams/architecture.svg" alt="بنية المشروع" width="100%">

مسار الاستعلام: **العميل ← بوابة استعلام e-cat ← مجموعة عمال PHP ← 209 شركات شحن**.

تتولى بوابة e-cat (Rust) مصادقة API-Key للواجهة الخارجية، والرد من ذاكرة Redis، وتحديد المعدل، وقاطع الدائرة لكل شركة، وموازنة أحمال RoundRobin؛ الرد من الذاكرة ورفض تحديد المعدل والفشل السريع لقاطع الدائرة كلها تحدث في جانب e-cat، ولا يستقبل عامل PHP إلا حركة الاستعلامات الفعلية — والتوسع الأفقي مجرد إضافة عمال.

**تقسيم العمل بإعادة استخدام محولات PHP الـ 209 عبر e-cat**: المحولات الـ 209 مكتوبة بـ PHP (`src/Carriers/Domestic` 45 + `International` 164)؛ وإعادة كتابتها بـ Rust مشروع شهور تفقد معه فوائد التحديثات المستمرة للحزمة الأصلية؛ لا يحتاج e-cat إلى فهم بروتوكولات الشركات، بل يعتمد فقط على عقد داخلي مستقر (`/internal/tracking/query` + مزامنة سجل `/internal/carriers`). لا تُسلَّم الاعتمادات إلى e-cat أبدًا — حدود الأمان واضحة.

لوحة الإدارة (متصفح) ← `/admin/*`: JWT + أذونات RBAC + تدقيق عمليات، تغطي carrier / carrier-credential / tracking-query / callback-subscription / statistics / client / client-app / plan / order / cdn-provider.

## هيكل المشروع

<img src="diagrams/structure.svg" alt="هيكل المشروع" width="100%">

```
integrated-global-logistics/
├── admin/                          # PHP webman v2 管理后台 + worker 池
│   ├── app/
│   │   ├── admin/controller/       # 管理端控制器（carrier、tracking、统计等）
│   │   ├── api/                    # API v1（验证码 / 登录 / 刷新令牌）
│   │   ├── common/                 # Hashids / Snowflake / 加密脱敏服务
│   │   ├── middleware/             # 安全过滤 / 限流 / JWT / RBAC / 审计
│   │   └── model/                  # 数据模型（logistics_ 前缀，snowflake 主键）
│   ├── apps/
│   │   ├── flutter/                # Flutter Web 管理后台（PC 风格）
│   │   └── harmonyos/              # HarmonyOS 原生客户端
│   ├── config/                     # 配置（含中文注释）
│   ├── database/
│   │   └── install.sql             # SQL 安装脚本（含权限种子数据）
│   └── docs/                       # API / 架构 / 设计 / 安全文档（12 语言）
├── infrastructure/                 # Rust e-cat 微服务框架 + 查询网关
│   ├── ecat/                       # 聚合 crate（App 骨架）
│   ├── ecat-transport-http/        # axum HTTP 传输
│   ├── ecat-data-redis/            # 轨迹缓存 + 限流存储
│   ├── ecat-client/                # RoundRobin 负载均衡
│   └── tracking-gateway/           # 对外查询网关（workspace 新成员）
├── sdk/                            # 对外 API 客户端 SDK（Python / PHP / Node.js / Go / Rust）
└── docs/                           # 平台规划与图示
    ├── diagrams/                   # SVG 架构图（本 README 引用）
    ├── translations/               # 12 语言 README
    └── logistics-aggregation-platform-plan.md  # 实施规划
```

## دورة الحياة

<img src="diagrams/lifecycle.svg" alt="دورة الحياة" width="100%">

**مسار الاستعلام (متزامن)**: عميل ← مصادقة API-Key ← تحديد معدل Redis ← بحث في الذاكرة (رد فوري عند الإصابة، `X-Cache: HIT`) ← فحص قاطع الدائرة (503 فشل سريع عند OPEN) ← اختيار عامل بـ RoundRobin ← واجهة `Logistics` لعامل PHP (RetryingClient داخل الحزمة يضيف محاولتين) ← 209 شركات شحن ← حفظ في `logistics_tracking_query` + كتابة الذاكرة ← إرجاع JSON موحد.

**مسار الاستدعاء (غير متزامن)**: webhook الشركة ← مسار قائمة بيضاء `/api/callback/{carrier}` + تحقق التوقيع ← حفظ في `logistics_tracking_event` + تحديث سجل الاستعلام ← الكتابة في قائمة webman ← مستهلكون غير متزامنين يدفعون إلى عنوان استدعاء التاجر وفق اشتراكه (توقيع HMAC + مفتاح Idempotency + إعادة محاولة بتراجع أسي + مدخل إعادة إرسال يدوي).

> يبقى دفع الاستدعاءات في النسخة الأولى على قائمة PHP — التحليل والبيانات في جانب PHP، ولا فائدة من نقل الأحداث بين اللغات؛ إذا أصبح إنتاج الدفع عنق الزجاجة (عشرات الآلاف في الدقيقة أو أكثر)، انقل المستهلك إلى e-cat (ecat-mq + وسيط retry) دون تغيير العقد الخارجي.

## الحماية الأمنية

<img src="diagrams/security.svg" alt="الأمان" width="100%">

دفاع متعمق متعدد الطبقات، أبرز النقاط:

- **طبقة البوابة** (tracking-gateway): مصادقة API-Key، تحديد معدل Redis (بالمفتاح / IP)، قاطع دائرة لكل شركة، حماية SSRF (حل القائمة البيضاء لنقاط نهاية العمال)؛ `/internal` يستمع للإنترانت فقط + ترويسة مفتاح مشترك؛ عزل الاعتمادات — لا يحتفظ e-cat باعتمادات نصية؛
- **طبقة التطبيق** (admin): JWT + قائمة سوداء (access 2h / refresh 14d)، أذونات RBAC بدقة method.path، تدقيق عمليات شامل؛ يصد `SecurityFilter` XSS / حقن SQL / CSRF / حقن الأوامر / اجتياز المسارات؛ البيانات الحساسة تُخزَّن مشفّرة بـ `Encryptable` + إخفاء عند التصدير؛ قفل 15 دقيقة بعد 5 محاولات فاشلة + كابتشا النقر؛
- **أمان الاستدعاءات**: مسار قائمة بيضاء + تحقق توقيع HMAC، تسليم at-least-once + مفتاح Idempotency لمنع التكرار؛
- **دلالات خطأ موحدة**: تحديد معدل 429، قاطع دائرة 503، خطأ شركة `carrier_error` — دون كشف التفاصيل الداخلية للعميل.
- **أمان الدفع** (M8/M10): التحقق من webhooks سترايب / باي بال (HMAC-SHA256 / verify-webhook-signature)، تأكيد الطلبات تلقائيًا + بديل يدوي في admin؛ مفاتيح الدفع مشفّرة عبر `Encryptable` في `logistics_system_config`؛
- **التحقق من مدفوعات العملات المشفرة** (M9): USDT TRC20 يُتحقق تلقائيًا عبر Tronscan API؛ BEP20 / ERC20 يُؤكد يدويًا؛
- **أمان مفاتيح العميل** (M7): X-API-Key يضبطه العميل (≥16 حرفًا)، يُخزَّن كـ sha256 — النص الصريح يُعاد مرة واحدة فقط عند الإنشاء؛ JWT العميل (token_type=client) معزول عن JWT الإدارة؛
- **كشف الهجمات في البوابة**（M11）：تم دمج `ecat-security` SecurityBodyLayer في البوابة (كاشفات حقن / بروتوكول / تسلسل بيانات / ملفات / تسريب بيانات حساسة)؛ تُحجب حمولات الهجوم في طبقة البوابة، وحزمة أمان طبقة التطبيق كاحتياط؛
- **أمان CDN**（M12）：خطة Cloudflare المجانية HTTPS كامل + جدار حماية مزدوج الطبقات (قواعد مُدارة عند الحافة + كشف طبقة تطبيق البوابة)؛ أصل Tunnel بدون تعريض المصدر؛ ردود الاتصال عبر نطاق فرعي DNS مباشر لمنع فقدان الطلبات عند تعطل CDN؛ تحديد المعدل حسب X-API-Key لا يتأثر بعناوين IP لحواف CDN؛ نقاط النهاية المُصادَق عليها no-store دائمًا لمنع خلط ذاكرة التخزين المؤقت بين المستخدمين؛
- **إدارة بيانات اعتماد CDN**（M13）：يُشفَّر access_key / access_secret لمزودي CDN عبر `Encryptable` في جدول `logistics_cdn_provider`، ويُكوَّن من `/admin/cdn/provider`؛

## المزايا

<img src="diagrams/description.svg" alt="مزايا المنصة" width="100%">

- **استعلامات التتبع المجمّعة: رقم تتبع واحد عبر العالم — 187 قاعدة أنماط تحدد تلقائيًا القناة المحلية / الدولية والناقل، 209 محولات ناقل تخرج 7 حالات قياسية من نوع `TrackStatus`؛**
- **تكامل متعدد الناقلين: 45 محولًا محليًا + 164 دوليًا، تغطية كاملة لـ DHL / FedEx / UPS / USPS والبريد الوطني S10، بيانات الاعتماد مشفرة بلا مفاتيح مضمّنة؛**
- **إدارة RBAC: JWT + قائمة سوداء + صلاحيات دقيقة method.path + سجل تدقيق كامل، فلتر أمني يحجب XSS / حقن SQL / CSRF / حقن الأوامر؛**
- **حلقة دفع مغلقة: Stripe / PayPal بالإضافة إلى USDT TRC20 / BEP20 / ERC20، تحقق من توقيع webhook يؤكد الطلبات تلقائيًا، طرق الدفع تفعَّل عبر الإعدادات؛**
- **بوابة العملاء والخطط: واجهات تسجيل / تسجيل دخول / إدارة التطبيقات / الخطط / الطلبات، مفتاح X-API-Key يضبطه العميل، معزول تمامًا عن admin؛**
- **حماية بوابة API: مصادقة API-Key، تحديد معدل Redis (429)، قاطع دائرة لكل ناقل (503)، حماية SSRF، حمولات الهجوم تُحجب في طبقة البوابة؛**
- **توزيع آمن عبر CDN: HTTPS كامل الموقع عبر Cloudflare + WAF مزدوج + تخزين طرفي، أصل Tunnel بلا تعرض عام؛**
- **SDK متعدد اللغات: خمسة SDK بدون تبعيات لـ Python / PHP / Node.js / Go / Rust، انسخ وشغّل.**

## التثبيت بنقرة واحدة

الموصى به: نشر بنقرة واحدة عبر Docker Compose — تشغيل 5 خدمات (Nginx / PHP / MySQL / Redis / Elasticsearch) مع فحوصات الصحة واستمرارية البيانات:

```bash
bash install.sh
```

بعد استنساخ المستودع:

```bash
cd integrated-global-logistics   # الدخول إلى جذر المشروع
bash install.sh                  # المنفذ 80 افتراضيًا، عدِّله بـ NGINX_PORT=8080
```

البرنامج النصي يتحقق من بيئة Docker، يشغّل جميع الخدمات ويستقصي فحوصات الصحة (حتى 120 ثانية)؛ بعد الجاهزية افتح `http://localhost/install` لإكمال معالج التثبيت (تهيئة قاعدة البيانات + إنشاء المسؤول). انظر [admin/README.md](../../admin/README.md) لنشر Docker Compose المفصل.

## بدء سريع

**لوحة الإدارة admin** (PHP webman):

```bash
cd admin
composer install
php start.php start
```

بعد التشغيل افتح معالج التثبيت في المتصفح لإكمال تهيئة قاعدة البيانات وإنشاء المدير: `http://localhost:8787/install` (المنفذ الافتراضي 8787، قابل للتعديل في `config/server.php`).

**بوابة استعلام infrastructure** (Rust e-cat):

```bash
cd infrastructure
cargo build
```

**استدعاء SDK** (خمسة عملاء بدون تبعيات، جاهزة للنسخ والاستخدام):

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
```

```bash
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/query \
  -H "Content-Type: application/json" -d '{"tracking_no": "LX123456789CN"}'
```

راجع [sdk/README.md](../../../sdk/README.md) للاستخدام والأمثلة بكل لغة.

للنشر التفصيلي انظر [admin/README.md](../../../admin/README.md) (Docker Compose يدير 5 خدمات: Nginx / PHP / MySQL / Redis / Elasticsearch) ووثيقة خطة التنفيذ.

## الوثائق

- [admin/docs/API.md](../../../admin/docs/API.md) — مرجع API (تنسيق استجابة موحد، أكواد الخطأ، مسار المصادقة، سياسات تحديد المعدل، سلسلة الوسائط)
- [admin/docs/ARCHITECTURE.md](../../../admin/docs/ARCHITECTURE.md) — تصميم البنية
- [admin/docs/DESIGN.md](../../../admin/docs/DESIGN.md) — وثيقة التصميم
- [admin/docs/SECURITY.md](../../../admin/docs/SECURITY.md) — بنية الأمان
- [docs/logistics-aggregation-platform-plan.md](../../../docs/logistics-aggregation-platform-plan.md) — خطة تنفيذ المنصة (البنية، تدفق البيانات، تصميم قاعدة البيانات، عقود API، المراحل)
- [admin/README.md](../../../admin/README.md) — الوصف الكامل للوحة الإدارة (تقنيات، معايير قاعدة البيانات، النشر، CI/CD)
- [sdk/README.md](../../../sdk/README.md) — حزم SDK لعملاء الواجهة البرمجية الخارجية (Python / PHP / Node.js / Go / Rust، خمس حزم بلا تبعيات، انسخ وشغّل)

## الترجمات

- [English](/docs/translations/en/README.md)
- [한국어](/docs/translations/ko/README.md)
- [Русский](/docs/translations/ru/README.md)
- [Deutsch](/docs/translations/de/README.md)
- [Français](/docs/translations/fr/README.md)
- [Español](/docs/translations/es/README.md)
- [Português](/docs/translations/pt/README.md)
- [हिन्दी](/docs/translations/hi/README.md)
- [العربية](/docs/translations/ar/README.md)
- [বাংলা](/docs/translations/bn/README.md)
- [Bahasa Indonesia](/docs/translations/id/README.md)
- [日本語](/docs/translations/ja/README.md)

## المشروع مفتوح المصدر يستحق الدعم

| WeChat | Alipay |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="WeChat"> | <img src="../../alipay.png" width="130" height="130" alt="Alipay"> |

### دعم مادي عبر التحويل الدولي (حوالة عابرة للحدود)

**معلومات المستفيد**

- اسم المستفيد: WANG KEXUN
- رقم حساب المستفيد: 881015918251

**البنك المستلم**

- رمز ZA Bank SWIFT: AABLHKHHXXX
- اسم البنك: ZA Bank Limited
- رمز البنك: 387
- عنوان البنك: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**البنك الوسيط للحوالة العابرة للحدود (إن لزم)**

> هذه معلومات البنك الوسيط (الممرر) للحوالة العابرة للحدود، وليست البنك المستلم. اسأل بنكك المُحوِّل عما إذا كان يتطلب إبلاغ معلومات البنك الوسيط.

- **للحوالات بالدولار الهونغ كونغي واليوان والدولار الأمريكي**، البنك الوسيط هو Citibank:
  - اسم البنك: Citibank N.A. Hong Kong
  - رمز SWIFT: CITIHKHXXXX
  - رمز البنك: 006
  - اسم الفرع: Hong Kong Branch
  - رمز الفرع: 391
  - عنوان البنك: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **للحوالات بعملات أخرى**، البنك الوسيط هو BNY Mellon:
  - اسم البنك: THE BANK OF NEW YORK MELLON
  - رمز SWIFT: IRVTUS3NXXX
  - عنوان البنك: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### التبرع بالعملات الرقمية (Crypto Donation)

إذا كان هذا المشروع مفيدًا لك، فمرحبًا بمسح رمز الاستجابة السريعة للتبرع، شكرًا لك!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## الترخيص

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

<!-- TRANSLATE-READY -->
