> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Flutter Multi-Plattform-PC-Layout — Designspezifikation

Datum: 2026-05-18

## Ziel

Aktiviert die Desktop-Plattformen macOS und Windows und stellt sicher, dass alle Plattformen – iOS (iPhone + iPad), macOS, Windows, Linux – das PC-Verwaltungskonsolen-Layout (Sidebar + Topbar + Inhaltsbereich) verwenden; auf Smartphones wird über ein Drawer-Menü adaptiert.

## Plattformstrategie

| Plattform | Status | Beschreibung |
|------|------|------|
| Linux | Aktiviert | Keine Aktion erforderlich |
| macOS | Aktivierung erforderlich | `flutter config --enable-macos-desktop` |
| Windows | Aktivierung erforderlich | `flutter config --enable-windows-desktop` |
| iOS | Vorhanden | Deckt iPhone (Handy-Layout) und iPad (Desktop-Layout) ab |
| Web | Vorhanden | Keine Aktion erforderlich |

iPad hat kein eigenes Plattform-Ziel; über den responsiven Breakpoint TABLET wird das Desktop-Layout erreicht.

## Responsive Breakpoints

| Breakpoint | Bereich | Layout-Modus |
|------|------|----------|
| PHONE | 0 - 767 | Drawer-Menü (AppBar + Drawer) |
| TABLET | 768 - 1199 | Einklappbare Sidebar (standardmäßig eingeklappt, 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (standardmäßig ausgeklappt, 240px) |

Die Mindestbreite des iPads im Hochformat beträgt 768px, trifft damit auf TABLET zu und erhält das Sidebar-Layout.
Alle iPhones sind schmaler als 768px, treffen auf PHONE zu und erhalten das Drawer-Menü.

## Dateiänderungen

### 1. main.dart — Breakpoint-Konfiguration

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Übriger Code bleibt unverändert

### 2. admin_layout.dart — Responsiver Navigationswechsel

- `_isPhone`: trifft den PHONE-Breakpoint
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; der NavigationDrawer im Drawer verwendet dieselben Menüpunkte wie die Desktop-Sidebar
- `_buildDesktopLayout()`: Bestehendes Row-Layout (Sidebar + Topbar + Inhaltsbereich)
- Bei TABLET ist die Sidebar standardmäßig eingeklappt, bei DESKTOP standardmäßig ausgeklappt

### 3. app_theme.dart — Dunkles Theme ergänzen

- Komponentenstile als private Konstanten extrahieren: `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Helles und dunkles Theme verwenden denselben Satz Komponentenstile
- Dunkles Theme ergänzen mit Material 3 + gleichem seed + dark brightness
