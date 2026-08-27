> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](10-frontend-components.md) | [English](10-frontend-components.en.md) | [한국어](10-frontend-components.ko.md) | [Русский](10-frontend-components.ru.md) | [Deutsch](10-frontend-components.de.md) | [Français](10-frontend-components.fr.md) | [Español](10-frontend-components.es.md) | [Português](10-frontend-components.pt.md) | [हिन्दी](10-frontend-components.hi.md) | [العربية](10-frontend-components.ar.md) | [বাংলা](10-frontend-components.bn.md) | [Bahasa Indonesia](10-frontend-components.id.md) | [日本語](10-frontend-components.ja.md)

# ফ্রন্টএন্ড কম্পোনেন্ট আর্কিটেকচার

## Flutter Web কম্পোনেন্ট ট্রি

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["লগইন ফর্ম<br/>ব্যবহারকারীর নাম+পাসওয়ার্ড"]
    login --> captcha["ক্লিক ক্যাপচা কম্পোনেন্ট<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>ক্লিক চিহ্নিত Circle"]

    dashboard --> sidebar["সাইডবার NavigationDrawer<br/>ভাঁজযোগ্য 64px/240px<br/>ড্যাশবোর্ড/ব্যবহারকারী/ভূমিকা/কনফিগ/লগ"]
    dashboard --> header["টপবার 56px<br/>ভাঁজ বোতাম+ব্যবহারকারী মেনু<br/>লগআউট নিশ্চিতকরণ AlertDialog"]
    dashboard --> content["কনটেন্ট অঞ্চল"]

    content --> stats["পরিসংখ্যান কার্ড GridView×4"]
    content --> chart["ট্রেন্ড লাইন চার্ট LineChart"]
    content --> pie["বিতরণ পাই চার্ট PieChart"]
    content --> logs["সাম্প্রতিক অপারেশন ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS পেজ রাউটিং

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Token নেই"| loginH["LoginPage"]
    entry -->|"Token আছে"| dashH["DashboardPage"]

    loginH -->|"লগইন সফল replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"লগআউট নিশ্চিতকরণ replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
