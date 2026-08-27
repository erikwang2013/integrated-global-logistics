> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Многоплатформенная компоновка Flutter в стиле PC — дизайн-спецификация

Дата: 2026-05-18

## Цель

Включить платформы macOS и Windows для десктопа, обеспечить использование компоновки в стиле PC-админки (боковая панель + верхняя панель + область содержимого) на всех платформах: iOS (iPhone + iPad), macOS, Windows, Linux; на телефонах использовать адаптацию через выдвижное меню.

## Стратегия платформ

| Платформа | Статус | Описание |
|------|------|------|
| Linux | включена | действий не требуется |
| macOS | требуется включить | `flutter config --enable-macos-desktop` |
| Windows | требуется включить | `flutter config --enable-windows-desktop` |
| iOS | уже есть | покрывает и iPhone (мобильная компоновка), и iPad (десктопная компоновка) |
| Web | уже есть | действий не требуется |

Отдельной платформенной цели для iPad нет: десктопная компоновка достигается попаданием в диапазон TABLET по адаптивному брейкпоинту.

## Адаптивные брейкпоинты

| Брейкпоинт | Диапазон | Режим компоновки |
|------|------|----------|
| PHONE | 0 - 767 | выдвижное меню (AppBar + Drawer) |
| TABLET | 768 - 1199 | сворачиваемая боковая панель (по умолчанию свёрнута до 64px) |
| DESKTOP | 1200 - 2460 | боковая панель (по умолчанию развёрнута 240px) |

Минимальная ширина iPad в портретной ориентации — 768px, попадает в TABLET и получает боковую панель.
Ширина iPhone всегда меньше 768px, попадает в PHONE и получает выдвижное меню.

## Изменения в файлах

### 1. main.dart — настройка брейкпоинтов

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- остальной код не меняется

### 2. admin_layout.dart — переключение адаптивной навигации

- `_isPhone`: попадание в брейкпоинт PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, NavigationDrawer внутри Drawer переиспользует те же пункты меню, что и десктопная боковая панель
- `_buildDesktopLayout()`: существующая компоновка Row (боковая панель + верхняя панель + область содержимого)
- в TABLET боковая панель по умолчанию свёрнута, в DESKTOP — развёрнута

### 3. app_theme.dart — дополнение тёмной темы

- извлечь стили компонентов в приватные константы `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- светлая и тёмная темы переиспользуют один и тот же набор стилей компонентов
- тёмная тема использует Material 3 + тот же seed + тёмную яркость
