> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Flutter : disposition de style PC multiplateforme — Spécification de conception

Date : 2026-05-18

## Objectif

Activer les plateformes de bureau macOS et Windows, garantir que toutes les plateformes — iOS (iPhone + iPad), macOS, Windows, Linux — utilisent la disposition de style console d'administration PC (barre latérale + barre supérieure + zone de contenu), et adapter les téléphones avec un menu tiroir.

## Stratégie par plateforme

| Plateforme | Statut | Description |
|------|------|------|
| Linux | Activée | Aucune action requise |
| macOS | À activer | `flutter config --enable-macos-desktop` |
| Windows | À activer | `flutter config --enable-windows-desktop` |
| iOS | Existe déjà | Couvre à la fois iPhone (disposition mobile) et iPad (disposition bureau) |
| Web | Existe déjà | Aucune action requise |

L'iPad n'a pas de cible de plateforme dédiée ; il obtient la disposition bureau via le seuil réactif TABLET.

## Seuils réactifs

| Seuil | Plage | Mode de disposition |
|------|------|----------|
| PHONE | 0 - 767 | Menu tiroir (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barre latérale repliable (repliée par défaut à 64px) |
| DESKTOP | 1200 - 2460 | Barre latérale (déployée par défaut à 240px) |

La largeur minimale de l'iPad en portrait est de 768px : il atteint TABLET et obtient la disposition à barre latérale.
Tous les iPhones, de largeur inférieure à 768px, atteignent PHONE et obtiennent le menu tiroir.

## Modifications de fichiers

### 1. main.dart — configuration des seuils

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Le reste du code reste inchangé

### 2. admin_layout.dart — bascule de navigation réactive

- `_isPhone`: atteint le seuil PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer ; le NavigationDrawer dans le Drawer réutilise les mêmes éléments de menu que la barre latérale du bureau
- `_buildDesktopLayout()`: disposition Row existante (barre latérale + barre supérieure + zone de contenu)
- La barre latérale est repliée par défaut en TABLET et déployée par défaut en DESKTOP

### 3. app_theme.dart — complément du thème sombre

- Extraire les styles de composants en constantes privées `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Les thèmes clair et sombre réutilisent le même ensemble de styles de composants
- Le thème sombre utilise Material 3 + la même seed + la luminosité dark
