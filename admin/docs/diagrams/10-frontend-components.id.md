> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](10-frontend-components.md) | [English](10-frontend-components.en.md) | [한국어](10-frontend-components.ko.md) | [Русский](10-frontend-components.ru.md) | [Deutsch](10-frontend-components.de.md) | [Français](10-frontend-components.fr.md) | [Español](10-frontend-components.es.md) | [Português](10-frontend-components.pt.md) | [हिन्दी](10-frontend-components.hi.md) | [العربية](10-frontend-components.ar.md) | [বাংলা](10-frontend-components.bn.md) | [Bahasa Indonesia](10-frontend-components.id.md) | [日本語](10-frontend-components.ja.md)

# Arsitektur Komponen Frontend

## Pohon Komponen Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Formulir login<br/>Nama pengguna + kata sandi"]
    login --> captcha["Komponen captcha klik<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Circle penanda klik"]

    dashboard --> sidebar["Sidebar NavigationDrawer<br/>Dapat dilipat 64px/240px<br/>Dashboard/Pengguna/Role/Konfigurasi/Log"]
    dashboard --> header["Bilah atas 56px<br/>Tombol lipat + menu pengguna<br/>AlertDialog konfirmasi keluar"]
    dashboard --> content["Area konten"]

    content --> stats["Kartu statistik GridView×4"]
    content --> chart["Grafik garis tren LineChart"]
    content --> pie["Pie chart distribusi PieChart"]
    content --> logs["Operasi terbaru ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Rute Halaman HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Tanpa Token"| loginH["LoginPage"]
    entry -->|"Dengan Token"| dashH["DashboardPage"]

    loginH -->|"Login berhasil replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Konfirmasi keluar replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
