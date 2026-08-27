> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# تخطيط نمط الحاسوب متعدد المنصات في Flutter — المواصفات التصميمية

التاريخ: 2026-05-18

## الهدف

تفعيل منصتي سطح المكتب macOS وWindows، وضمان أن تستخدم جميع المنصات — iOS (iPhone + iPad) وmacOS وWindows وLinux — تخطيط لوحة الإدارة بأسلوب الحاسوب (شريط جانبي + شريط علوي + منطقة محتوى)، بينما تستخدم الهواتف قائمة الدرج (Drawer) للتكيف.

## إستراتيجية المنصات

| المنصة | الحالة | الشرح |
|------|------|------|
| Linux | مفعّلة | لا حاجة لإجراء |
| macOS | يجب التفعيل | `flutter config --enable-macos-desktop` |
| Windows | يجب التفعيل | `flutter config --enable-windows-desktop` |
| iOS | موجودة | تغطي كلا من iPhone (تخطيط الهاتف) وiPad (تخطيط سطح المكتب) |
| Web | موجودة | لا حاجة لإجراء |

لا يوجد هدف منصة مستقل لـ iPad؛ يتم تحقيق تخطيط سطح المكتب عبر إصابة نقطة الفصل TABLET في التصميم المتجاوب.

## نقاط الفصل المتجاوبة

| نقطة الفصل | النطاق | نمط التخطيط |
|------|------|----------|
| PHONE | 0 - 767 | قائمة الدرج (AppBar + Drawer) |
| TABLET | 768 - 1199 | شريط جانبي قابل للطي (مطوي افتراضيًا 64px) |
| DESKTOP | 1200 - 2460 | شريط جانبي (ممتد افتراضيًا 240px) |

الحد الأدنى لعرض iPad في الوضع الرأسي هو 768px، فيصيب TABLET ويحصل على تخطيط الشريط الجانبي.
جميع عروض iPhone أقل من 768px، فتصيب PHONE وتحصل على قائمة الدرج.

## تغييرات الملفات

### 1. main.dart — إعداد نقاط الفصل

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- باقي الكود دون تغيير

### 2. admin_layout.dart — تبديل التنقل المتجاوب

- `_isPhone`: يصيب نقطة فصل PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer، حيث يعيد NavigationDrawer داخل Drawer استخدام نفس عناصر القائمة الخاصة بالشريط الجانبي لسطح المكتب
- `_buildDesktopLayout()`: تخطيط Row الحالي (شريط جانبي + شريط علوي + منطقة محتوى)
- في TABLET يُطوى الشريط الجانبي افتراضيًا، وفي DESKTOP يُفتح افتراضيًا

### 3. app_theme.dart — إكمال السمة الداكنة

- استخراج أنماط المكوّنات كثوابت خاصة `_dataTableTheme` و`_cardTheme` و`_inputDecorationTheme` و`_dividerTheme`
- تستخدم السمتان الفاتحة والداكنة نفس مجموعة أنماط المكوّنات
- تُستكمل السمة الداكنة باستخدام Material 3 + نفس seed + إضاءة dark
