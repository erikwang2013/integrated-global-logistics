> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](10-frontend-components.md) | [English](10-frontend-components.en.md) | [한국어](10-frontend-components.ko.md) | [Русский](10-frontend-components.ru.md) | [Deutsch](10-frontend-components.de.md) | [Français](10-frontend-components.fr.md) | [Español](10-frontend-components.es.md) | [Português](10-frontend-components.pt.md) | [हिन्दी](10-frontend-components.hi.md) | [العربية](10-frontend-components.ar.md) | [বাংলা](10-frontend-components.bn.md) | [Bahasa Indonesia](10-frontend-components.id.md) | [日本語](10-frontend-components.ja.md)

# Архитектура фронтенд-компонентов

## Дерево компонентов Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["форма входа<br/>имя пользователя + пароль"]
    login --> captcha["компонент кликовой капчи<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>метки кликов Circle"]

    dashboard --> sidebar["боковая панель NavigationDrawer<br/>сворачиваемая 64px/240px<br/>Дашборд/Пользователи/Роли/Конфигурация/Журналы"]
    dashboard --> header["верхняя панель 56px<br/>кнопка сворачивания + меню пользователя<br/>подтверждение выхода AlertDialog"]
    dashboard --> content["область содержимого"]

    content --> stats["карточки статистики GridView×4"]
    content --> chart["линейный график трендов LineChart"]
    content --> pie["круговая диаграмма распределения PieChart"]
    content --> logs["последние операции ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Маршрутизация страниц HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"нет Token"| loginH["LoginPage"]
    entry -->|"есть Token"| dashH["DashboardPage"]

    loginH -->|"успешный вход replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"подтверждение выхода replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
