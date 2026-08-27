> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Flutter মাল্টিপ্ল্যাটফর্ম PC-স্টাইল লেআউট — ডিজাইন স্পেসিফিকেশন

তারিখ: 2026-05-18

## লক্ষ্য

macOS ও Windows ডেস্কটপ প্ল্যাটফর্ম সক্রিয় করুন, নিশ্চিত করুন যে iOS (iPhone + iPad), macOS, Windows, Linux — সব প্ল্যাটফর্মে PC অ্যাডমিন প্যানেল-স্টাইল লেআউট (সাইডবার + টপবার + কনটেন্ট অঞ্চল) ব্যবহৃত হয়, আর মোবাইলে ড্রয়ার মেনু দিয়ে অভিযোজিত হয়।

## প্ল্যাটফর্ম কৌশল

| প্ল্যাটফর্ম | অবস্থা | ব্যাখ্যা |
|------|------|------|
| Linux | সক্রিয় | কোনো কাজ নেই |
| macOS | সক্রিয় করা প্রয়োজন | `flutter config --enable-macos-desktop` |
| Windows | সক্রিয় করা প্রয়োজন | `flutter config --enable-windows-desktop` |
| iOS | ইতিমধ্যে আছে | iPhone (মোবাইল লেআউট) এবং iPad (ডেস্কটপ লেআউট) — উভয়ই কভার করে |
| Web | ইতিমধ্যে আছে | কোনো কাজ নেই |

iPad-এর কোনো আলাদা প্ল্যাটফর্ম টার্গেট নেই; রেসপন্সিভ ব্রেকপয়েন্টে TABLET রেঞ্জে পড়লে ডেস্কটপ লেআউট পায়।

## রেসপন্সিভ ব্রেকপয়েন্ট

| ব্রেকপয়েন্ট | রেঞ্জ | লেআউট মোড |
|------|------|----------|
| PHONE | 0 - 767 | ড্রয়ার মেনু (AppBar + Drawer) |
| TABLET | 768 - 1199 | ভাঁজযোগ্য সাইডবার (ডিফল্টে ভাঁজ করা 64px) |
| DESKTOP | 1200 - 2460 | সাইডবার (ডিফল্টে প্রসারিত 240px) |

iPad পোর্ট্রেট মোডে ন্যূনতম প্রস্থ 768px, TABLET রেঞ্জে পড়ে, তাই সাইডবার লেআউট পায়।
iPhone-এর প্রস্থ সবসময় 768px-এর কম, PHONE রেঞ্জে পড়ে, তাই ড্রয়ার মেনু পায়।

## ফাইল পরিবর্তন

### 1. main.dart — ব্রেকপয়েন্ট কনফিগারেশন

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- বাকি কোড অপরিবর্তিত

### 2. admin_layout.dart — রেসপন্সিভ নেভিগেশন সুইচিং

- `_isPhone`: PHONE ব্রেকপয়েন্টে পড়ে
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; Drawer-এর ভিতরে NavigationDrawer ডেস্কটপ সাইডবারের মতো একই মেনু আইটেম পুনরায় ব্যবহার করে
- `_buildDesktopLayout()`: বিদ্যমান Row লেআউট (সাইডবার + টপবার + কনটেন্ট অঞ্চল)
- TABLET-এ সাইডবার ডিফল্টে ভাঁজ করা থাকে, DESKTOP-এ ডিফল্টে প্রসারিত থাকে

### 3. app_theme.dart — ডার্ক থিম সম্পূর্ণকরণ

- কম্পোনেন্ট স্টাইলগুলো প্রাইভেট কনস্ট্যান্ট `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`-তে উত্তোলন করুন
- লাইট ও ডার্ক থিম একই কম্পোনেন্ট স্টাইল পুনরায় ব্যবহার করে
- ডার্ক থিম Material 3 + একই seed + dark ব্রাইটনেস ব্যবহার করে
