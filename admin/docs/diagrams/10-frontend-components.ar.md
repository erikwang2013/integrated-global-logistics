> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](10-frontend-components.md) | [English](10-frontend-components.en.md) | [한국어](10-frontend-components.ko.md) | [Русский](10-frontend-components.ru.md) | [Deutsch](10-frontend-components.de.md) | [Français](10-frontend-components.fr.md) | [Español](10-frontend-components.es.md) | [Português](10-frontend-components.pt.md) | [हिन्दी](10-frontend-components.hi.md) | [العربية](10-frontend-components.ar.md) | [বাংলা](10-frontend-components.bn.md) | [Bahasa Indonesia](10-frontend-components.id.md) | [日本語](10-frontend-components.ja.md)

# بنية مكونات الواجهة الأمامية

## شجرة مكونات Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["نموذج تسجيل الدخول<br/>اسم المستخدم + كلمة المرور"]
    login --> captcha["مكوّن رمز التحقق بالنقر<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Circle لتحديد النقرات"]

    dashboard --> sidebar["الشريط الجانبي NavigationDrawer<br/>قابل للطي 64px/240px<br/>لوحة التحكم/المستخدمون/الأدوار/الإعدادات/السجلات"]
    dashboard --> header["الشريط العلوي 56px<br/>زر الطي + قائمة المستخدم<br/>AlertDialog لتأكيد الخروج"]
    dashboard --> content["منطقة المحتوى"]

    content --> stats["بطاقات الإحصائيات GridView×4"]
    content --> chart["مخطط الخطوط للاتجاه LineChart"]
    content --> pie["مخطط دائري للتوزيع PieChart"]
    content --> logs["أحدث العمليات ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## توجيه صفحات HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"بدون Token"| loginH["LoginPage"]
    entry -->|"مع Token"| dashH["DashboardPage"]

    loginH -->|"تسجيل دخول ناجح replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"تأكيد الخروج replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
