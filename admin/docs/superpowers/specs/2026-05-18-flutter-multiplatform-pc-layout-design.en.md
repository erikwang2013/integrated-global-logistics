> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Flutter Multi-platform PC-style Layout — Design Specification

Date: 2026-05-18

## Goal

Enable the macOS and Windows desktop platforms, ensuring that all platforms — iOS (iPhone + iPad), macOS, Windows, Linux — use the PC admin console layout style (sidebar + top bar + content area), with a drawer menu on phones.

## Platform Strategy

| Platform | Status | Notes |
|------|------|------|
| Linux | Already enabled | No action needed |
| macOS | Needs enabling | `flutter config --enable-macos-desktop` |
| Windows | Needs enabling | `flutter config --enable-windows-desktop` |
| iOS | Already exists | Covers both iPhone (phone layout) and iPad (desktop layout) |
| Web | Already exists | No action needed |

The iPad has no separate platform target; it hits the TABLET breakpoint through responsive breakpoints to achieve the desktop layout.

## Responsive Breakpoints

| Breakpoint | Range | Layout Mode |
|------|------|----------|
| PHONE | 0 - 767 | Drawer menu (AppBar + Drawer) |
| TABLET | 768 - 1199 | Collapsible sidebar (collapsed to 64px by default) |
| DESKTOP | 1200 - 2460 | Sidebar (expanded to 240px by default) |

The iPad's minimum portrait width is 768px, which hits TABLET and gets the sidebar layout.
All iPhone widths are below 768px, which hit PHONE and get the drawer menu.

## File Changes

### 1. main.dart — Breakpoint Configuration

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- All other code unchanged

### 2. admin_layout.dart — Responsive Navigation Switching

- `_isPhone`: hits the PHONE breakpoint
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, where the NavigationDrawer inside the Drawer reuses the same menu items as the desktop sidebar
- `_buildDesktopLayout()`: existing Row layout (sidebar + top bar + content area)
- The sidebar is collapsed by default on TABLET, and expanded by default on DESKTOP

### 3. app_theme.dart — Dark Theme Completion

- Extract component styles into private constants `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Light and dark themes reuse the same set of component styles
- The dark theme adds Material 3 with the same seed and dark brightness
