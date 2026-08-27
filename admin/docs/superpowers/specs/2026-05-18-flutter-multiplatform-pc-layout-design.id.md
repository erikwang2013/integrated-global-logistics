> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Desain Tata Letak Gaya PC Multi-Platform Flutter — Spesifikasi Desain

Tanggal: 2026-05-18

## Tujuan

Mengaktifkan platform desktop macOS dan Windows, memastikan iOS (iPhone + iPad), macOS, Windows, dan Linux semuanya menggunakan tata letak gaya panel admin PC (sidebar + bilah atas + area konten), sedangkan ponsel menggunakan menu drawer yang adaptif.

## Strategi Platform

| Platform | Status | Keterangan |
|------|------|------|
| Linux | Sudah diaktifkan | Tidak perlu tindakan |
| macOS | Perlu diaktifkan | `flutter config --enable-macos-desktop` |
| Windows | Perlu diaktifkan | `flutter config --enable-windows-desktop` |
| iOS | Sudah ada | Mencakup iPhone (tata letak ponsel) dan iPad (tata letak desktop) |
| Web | Sudah ada | Tidak perlu tindakan |

iPad tidak memiliki target platform terpisah; tata letak desktop dicapai dengan mengenai titik putus responsif TABLET.

## Titik Putus Responsif

| Titik Putus | Rentang | Mode Tata Letak |
|------|------|----------|
| PHONE | 0 - 767 | Menu drawer (AppBar + Drawer) |
| TABLET | 768 - 1199 | Sidebar yang dapat dilipat (terlipat secara default 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (terbuka secara default 240px) |

Lebar minimum iPad dalam mode potret adalah 768px, masuk kategori TABLET, sehingga mendapatkan tata letak sidebar. Lebar iPhone semuanya kurang dari 768px, masuk kategori PHONE, sehingga mendapatkan menu drawer.

## Perubahan File

### 1. main.dart — Konfigurasi titik putus

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Kode lainnya tidak berubah

### 2. admin_layout.dart — Pengalihan navigasi responsif

- `_isPhone`: mengenai titik putus PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, NavigationDrawer di dalam Drawer menggunakan kembali item menu yang sama dengan sidebar desktop
- `_buildDesktopLayout()`: tata letak Row yang ada (sidebar + bilah atas + area konten)
- Sidebar terlipat secara default di TABLET, terbuka secara default di DESKTOP

### 3. app_theme.dart — Melengkapi tema gelap

- Ekstrak gaya komponen menjadi konstanta privat `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Tema terang dan gelap menggunakan kembali set gaya komponen yang sama
- Tema gelap dilengkapi menggunakan Material 3 + seed yang sama + kecerahan dark
