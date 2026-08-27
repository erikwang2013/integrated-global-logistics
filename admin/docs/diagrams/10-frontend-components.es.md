> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](10-frontend-components.md) | [English](10-frontend-components.en.md) | [한국어](10-frontend-components.ko.md) | [Русский](10-frontend-components.ru.md) | [Deutsch](10-frontend-components.de.md) | [Français](10-frontend-components.fr.md) | [Español](10-frontend-components.es.md) | [Português](10-frontend-components.pt.md) | [हिन्दी](10-frontend-components.hi.md) | [العربية](10-frontend-components.ar.md) | [বাংলা](10-frontend-components.bn.md) | [Bahasa Indonesia](10-frontend-components.id.md) | [日本語](10-frontend-components.ja.md)

# Arquitectura de componentes del frontend

## Árbol de componentes de Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Formulario de inicio de sesión<br/>Usuario + contraseña"]
    login --> captcha["Componente de captcha de clic<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Marcador Circle al hacer clic"]

    dashboard --> sidebar["Barra lateral NavigationDrawer<br/>Plegable 64px/240px<br/>Dashboard/Usuario/Rol/Config/Logs"]
    dashboard --> header["Barra superior 56px<br/>Botón de plegado + menú de usuario<br/>Confirmación de salida con AlertDialog"]
    dashboard --> content["Área de contenido"]

    content --> stats["Tarjetas de estadísticas GridView×4"]
    content --> chart["Gráfico de tendencias LineChart"]
    content --> pie["Gráfico circular de distribución PieChart"]
    content --> logs["Operaciones recientes ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Rutas de páginas de HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Sin Token"| loginH["LoginPage"]
    entry -->|"Con Token"| dashH["DashboardPage"]

    loginH -->|"Inicio de sesión exitoso replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Confirmación de salida replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
