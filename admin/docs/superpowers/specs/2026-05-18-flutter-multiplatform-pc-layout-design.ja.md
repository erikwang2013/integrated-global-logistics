> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Flutter マルチプラットフォーム PC スタイルレイアウト — 設計仕様

日付: 2026-05-18

## 目標

macOS、Windows デスクトッププラットフォームを有効化し、iOS (iPhone + iPad)、macOS、Windows、Linux の全プラットフォームで PC 管理コンソールスタイルのレイアウト（サイドバー + トップバー + コンテンツ領域）を使用し、スマートフォンではドロワーメニューで対応します。

## プラットフォーム戦略

| プラットフォーム | 状態 | 説明 |
|------|------|------|
| Linux | 有効済み | 操作不要 |
| macOS | 要有効化 | `flutter config --enable-macos-desktop` |
| Windows | 要有効化 | `flutter config --enable-windows-desktop` |
| iOS | 既存 | iPhone (スマホレイアウト) と iPad (デスクトップレイアウト) の両方をカバー |
| Web | 既存 | 操作不要 |

iPad には独立したプラットフォームターゲットがなく、レスポンシブブレークポイントで TABLET に該当させてデスクトップレイアウトを実現します。

## レスポンシブブレークポイント

| ブレークポイント | 範囲 | レイアウトモード |
|------|------|----------|
| PHONE | 0 - 767 | ドロワーメニュー (AppBar + Drawer) |
| TABLET | 768 - 1199 | 折りたたみ可能なサイドバー (デフォルトで 64px に折りたたみ) |
| DESKTOP | 1200 - 2460 | サイドバー (デフォルトで 240px に展開) |

iPad 縦向きの最小幅は 768px のため TABLET に該当し、サイドバーレイアウトが適用されます。
iPhone は幅がすべて 768px 未満のため PHONE に該当し、ドロワーメニューが適用されます。

## ファイル変更

### 1. main.dart — ブレークポイント設定

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- その他のコードは変更なし

### 2. admin_layout.dart — レスポンシブナビゲーション切り替え

- `_isPhone`: PHONE ブレークポイントに該当
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer。Drawer 内の NavigationDrawer はデスクトップサイドバーと同じメニュー項目を再利用
- `_buildDesktopLayout()`: 既存の Row レイアウト（サイドバー + トップバー + コンテンツ領域）
- TABLET ではサイドバーがデフォルトで折りたたまれ、DESKTOP ではデフォルトで展開される

### 3. app_theme.dart — ダークテーマの補完

- コンポーネントスタイルをプライベート定数 `_dataTableTheme`、`_cardTheme`、`_inputDecorationTheme`、`_dividerTheme` に抽出
- ライトテーマとダークテーマで同一のコンポーネントスタイルを再利用
- ダークテーマは Material 3 + 同一 seed + dark 輝度を追加
