> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Disposición de estilo PC para múltiples plataformas en Flutter — Especificación de diseño

Fecha: 2026-05-18

## Objetivo

Habilitar las plataformas de escritorio macOS y Windows, garantizando que iOS (iPhone + iPad), macOS, Windows y Linux usen la disposición de estilo PC para panel de administración (barra lateral + barra superior + área de contenido), y que los móviles se adapten con un menú de cajón.

## Estrategia por plataforma

| Plataforma | Estado | Descripción |
|------|------|------|
| Linux | Habilitado | Sin acción requerida |
| macOS | Debe habilitarse | `flutter config --enable-macos-desktop` |
| Windows | Debe habilitarse | `flutter config --enable-windows-desktop` |
| iOS | Ya existe | Cubre tanto iPhone (disposición móvil) como iPad (disposición de escritorio) |
| Web | Ya existe | Sin acción requerida |

iPad no tiene un objetivo de plataforma independiente; consigue la disposición de escritorio alcanzando el nivel TABLET a través de los puntos de interrupción responsivos.

## Puntos de interrupción responsivos

| Punto de interrupción | Rango | Modo de disposición |
|------|------|----------|
| PHONE | 0 - 767 | Menú de cajón (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barra lateral plegable (plegada por defecto, 64px) |
| DESKTOP | 1200 - 2460 | Barra lateral (expandida por defecto, 240px) |

En orientación vertical, la anchura mínima del iPad es 768px, alcanza TABLET y obtiene la disposición de barra lateral.
Todas las anchuras de iPhone son inferiores a 768px, alcanzan PHONE y obtienen el menú de cajón.

## Cambios de archivos

### 1. main.dart — Configuración de puntos de interrupción

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- El resto del código permanece sin cambios

### 2. admin_layout.dart — Cambio de navegación responsivo

- `_isPhone`: alcanza el punto de interrupción PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; el NavigationDrawer dentro del Drawer reutiliza los mismos elementos de menú que la barra lateral de escritorio
- `_buildDesktopLayout()`: disposición Row existente (barra lateral + barra superior + área de contenido)
- En TABLET la barra lateral está plegada por defecto; en DESKTOP, expandida

### 3. app_theme.dart — Complemento del tema oscuro

- Extraer los estilos de los componentes como constantes privadas `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Los temas claro y oscuro reutilizan el mismo conjunto de estilos de componentes
- El tema oscuro usa Material 3 + el mismo seed + luminosidad dark
