> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Layout estilo PC multiplataforma do Flutter — Especificação de design

Data: 2026-05-18

## Objetivo

Habilitar as plataformas desktop macOS e Windows, garantindo que todas as plataformas — iOS (iPhone + iPad), macOS, Windows, Linux — usem o layout de painel administrativo estilo PC (barra lateral + barra superior + área de conteúdo), com o menu de gaveta (drawer) adaptado para celulares.

## Estratégia de plataforma

| Plataforma | Status | Descrição |
|------|------|------|
| Linux | Já habilitada | Nenhuma ação necessária |
| macOS | Precisa habilitar | `flutter config --enable-macos-desktop` |
| Windows | Precisa habilitar | `flutter config --enable-windows-desktop` |
| iOS | Já existente | Cobre tanto iPhone (layout de celular) quanto iPad (layout desktop) |
| Web | Já existente | Nenhuma ação necessária |

O iPad não possui alvo de plataforma independente; o layout desktop é obtido pelo breakpoint responsivo TABLET.

## Breakpoints responsivos

| Breakpoint | Faixa | Modo de layout |
|------|------|----------|
| PHONE | 0 - 767 | Menu de gaveta (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barra lateral recolhível (recolhida por padrão em 64px) |
| DESKTOP | 1200 - 2460 | Barra lateral (expandida por padrão em 240px) |

A largura mínima do iPad em modo retrato é 768px, que atinge TABLET e obtém o layout de barra lateral.
Todas as larguras do iPhone são menores que 768px, atingindo PHONE e obtendo o menu de gaveta.

## Alterações de arquivos

### 1. main.dart — configuração de breakpoints

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Restante do código inalterado

### 2. admin_layout.dart — alternância de navegação responsiva

- `_isPhone`: atinge o breakpoint PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, com o NavigationDrawer dentro do Drawer reutilizando os mesmos itens de menu da barra lateral desktop
- `_buildDesktopLayout()`: layout Row existente (barra lateral + barra superior + área de conteúdo)
- Em TABLET a barra lateral fica recolhida por padrão; em DESKTOP fica expandida por padrão

### 3. app_theme.dart — complemento do tema escuro

- Extrair os estilos de componentes para constantes privadas `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Temas claro e escuro reutilizam o mesmo conjunto de estilos de componentes
- O tema escuro complementa com Material 3 + mesmo seed + brilho dark
