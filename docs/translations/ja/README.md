# 物流統合プラットフォーム（Integrated Global Logistics）
<img src="../../diagrams/mascot.svg" alt="E-Cat 项目吉祥物" width="220" align="right">

世界の物流追跡クエリを一元的に扱うワンストッププラットフォーム：**admin 管理バックエンド**（PHP webman + Flutter）が管理面とクエリ worker プールを担い、**e-cat 高頻度ゲートウェイ**（Rust 常駐プロセス）がクエリトラフィックを支え、**global-logistics 統一ファサード**（209 社の運送会社 PHP アダプター）が一つの入口で全世界を検索します。

> 対応言語：[[English / 英語]](/docs/translations/en/README.md) · [[한국어 / 韓国語]](/docs/translations/ko/README.md) · [[Русский / ロシア語]](/docs/translations/ru/README.md) · [[Deutsch / ドイツ語]](/docs/translations/de/README.md) · [[Français / フランス語]](/docs/translations/fr/README.md) · [[Español / スペイン語]](/docs/translations/es/README.md) · [[Português / ポルトガル語]](/docs/translations/pt/README.md) · [[हिन्दी / ヒンディー語]](/docs/translations/hi/README.md) · [[العربية / アラビア語]](/docs/translations/ar/README.md) · [[বাংলা / ベンガル語]](/docs/translations/bn/README.md) · [[Bahasa Indonesia / インドネシア語]](/docs/translations/id/README.md) · [[日本語]](/docs/translations/ja/README.md)（[翻訳へジャンプ](#translations他の言語)）

## プロジェクト紹介

<img src="diagrams/intro.svg" alt="プロジェクト紹介" width="100%">

物流統合プラットフォームは、世界中の **209 社**の宅配便・郵便運送会社の追跡クエリを一つのプラットフォームに統合します：加盟店と C エンドは追跡番号を 1 つ入力するだけで、プラットフォームが国内・国際チャネルと運送会社を自動識別し、各社のプロトコル差異（署名、OAuth2、XML/JSON、ステータスマッピング）を意識する必要はありません。

プラットフォームは 3 つのコンポーネントが連携して構成されます：

- **admin 管理バックエンド**（PHP webman v2 + Flutter）—— 管理面と PHP worker プール：運送会社マスタ、鍵の暗号化管理、クエリ記録、統計レポート、コールバック購読設定、RBAC / JWT / 操作監査体制を完備；
- **tracking-gateway 高頻度ゲートウェイ**（Rust e-cat フレームワーク）—— 外部クエリ API の第一の入口：Redis キャッシュ、レート制限、運送会社別サーキットブレーカー、worker の負荷分散。高頻度面のみを担い、運送会社プロトコルは理解しません；
- **global-logistics 統一ファサード**（PHP パッケージ）—— 209 社の運送会社アダプター（国内 45 + 国際 164）、187 件の追跡番号自動識別ルール、`TrackStatus` 7 種の統一ステータスセマンティクス。

**現在の進捗**：M1–M13 すべて完了 —— M1 管理面（運送会社 / 資格情報 / クエリ記録 / 購読 CRUD）、M2 クエリゲートウェイ（外部 API 全チェーン）、M3 コールバック購読、M4 監視統計、M5 外部 OpenAPI ドキュメント、M6 5 つのクライアント SDK、M7 クライアントポータル（登録 / アプリ / プラン / 注文）、M8 決済（Stripe / PayPal）、M9 仮想通貨（USDT TRC20 / BEP20 / ERC20）、M10 決済方法設定、M11 ゲートウェイセキュリティミドルウェア、M12 CDN 計画（Cloudflare + キャッシュヘッダー）、M13 CDN プロバイダー管理。クライアント → e-cat → worker → 運送会社の追跡クエリチェーンがデモ可能で、5 つの依存ゼロ SDK はコピーしてすぐ使えます。

## プロジェクト説明

<img src="diagrams/description.svg" alt="プロジェクト説明" width="100%">

- **一つの入口**：`Logistics::track($trackingNo)` が国内・国際チャネルと運送会社を自動識別。ビジネス層は一種類の形にだけ対応します；
- **自動識別**：187 件の追跡番号正規表現ルールは順序に敏感で、国内チャネルを優先的にヒット。識別できない場合は `domestic()` / `international()` を明示的に呼び出せます；
- **統一ステータス**：各社まちまちの生のステータスを統一 `TrackStatus` 列挙型（集荷待ち / 輸送中 / 配達中 / 配達完了 / 異常 / 返送 / 識別不可）にマッピング；
- **グローバルカバレッジ**：DHL、FedEx、UPS、USPS の四大宅配便と各国郵便の S10 システム（ヨーロッパ、ラテンアメリカ・カリブ、アフリカ・中東、アジア太平洋の 4 地域）；
- **外部 API**：e-cat クエリゲートウェイが API-Key 認証、Redis キャッシュヒット（`X-Cache: HIT`）、レート制限 429、運送会社別サーキットブレーカー 503、RoundRobin worker 負荷分散を提供；依存ゼロの 5 SDK（Python / PHP / Node.js / Go / Rust）はコピーしてすぐ使えます；
- **クライアントポータルと課金**（M7–M10）：クライアント登録 / ログイン（client JWT は admin と分離）、X-API-Key 自設のアプリ管理、プラン / 注文 API；Stripe / PayPal に加え USDT TRC20 / BEP20 / ERC20 仮想通貨決済、Stripe 決済方法（Apple Pay / Google Pay / Klarna / SEPA など）は設定で即反映；
- **CDN 高速化**（M12/M13）：Cloudflare 無料プランで全サイト HTTPS + 静的アセットのエッジキャッシュ、CDN プロバイダー / ドメイン / キーは管理面で設定可能（キーは暗号化）；
- **鍵のハードコードゼロ**：各社の鍵はすべて設定注入を経由し、データベース層では Encryptable 暗号文で保存。コードと鍵は完全に分離されています。

## プロジェクトアーキテクチャ

<img src="diagrams/architecture.svg" alt="プロジェクトアーキテクチャ" width="100%">

クエリチェーン：**クライアント → e-cat クエリゲートウェイ → PHP worker プール → 209 社の運送会社**。

e-cat ゲートウェイ（Rust）は外部 API の API-Key 認証、Redis キャッシュヒット、レート制限、運送会社別サーキットブレーカーと RoundRobin 負荷分散を担当します。キャッシュヒット、レート制限拒否、サーキットブレーカーの高速失敗はすべて e-cat 側で完了し、PHP worker は実クエリトラフィックのみを引き受け、水平スケーリングは worker を追加するだけです。

**e-cat が 209 社の PHP アダプターを再利用する分業案**：209 個のアダプターは PHP（`src/Carriers/Domestic` 45 社 + `International` 164 社）で、Rust での書き直しは数ヶ月の工事になり、上流パッケージの継続的アップデートの恩恵も失われます。e-cat は運送会社プロトコルを理解する必要はなく、安定した内部契約（`/internal/tracking/query` + `/internal/carriers` レジストリ同期）にのみ依存します。資格情報は e-cat に渡されることはなく、セキュリティ境界は明確です。

管理面（ブラウザ）→ `/admin/*`：JWT + RBAC 権限 + 操作監査。carrier / carrier-credential / tracking-query / callback-subscription / statistics / client / client-app / plan / order / cdn-provider をカバー。

## プロジェクト構造

<img src="diagrams/structure.svg" alt="プロジェクト構造" width="100%">

```
integrated-global-logistics/
├── admin/                          # PHP webman v2 管理后台 + worker 池
│   ├── app/
│   │   ├── admin/controller/       # 管理端控制器（carrier、tracking、统计等）
│   │   ├── api/                    # API v1（验证码 / 登录 / 刷新令牌）
│   │   ├── common/                 # Hashids / Snowflake / 加密脱敏服务
│   │   ├── middleware/             # 安全过滤 / 限流 / JWT / RBAC / 审计
│   │   └── model/                  # 数据模型（logistics_ 前缀，snowflake 主键）
│   ├── apps/
│   │   ├── flutter/                # Flutter Web 管理后台（PC 风格）
│   │   └── harmonyos/              # HarmonyOS 原生客户端
│   ├── config/                     # 配置（含中文注释）
│   ├── database/
│   │   └── install.sql             # SQL 安装脚本（含权限种子数据）
│   └── docs/                       # API / 架构 / 设计 / 安全文档（12 语言）
├── infrastructure/                 # Rust e-cat 微服务框架 + 查询网关
│   ├── ecat/                       # 聚合 crate（App 骨架）
│   ├── ecat-transport-http/        # axum HTTP 传输
│   ├── ecat-data-redis/            # 轨迹缓存 + 限流存储
│   ├── ecat-client/                # RoundRobin 负载均衡
│   └── tracking-gateway/           # 对外查询网关（workspace 新成员）
├── sdk/                            # 对外 API 客户端 SDK（Python / PHP / Node.js / Go / Rust）
└── docs/                           # 平台规划与图示
    ├── diagrams/                   # SVG 架构图（本 README 引用）
    ├── translations/               # 12 语言 README
    └── logistics-aggregation-platform-plan.md  # 实施规划
```

## ライフサイクル

<img src="diagrams/lifecycle.svg" alt="ライフサイクル" width="100%">

**クエリチェーン（同期）**：クライアント → API-Key 認証 → Redis レート制限 → キャッシュ検索（ヒット即返却、`X-Cache: HIT`）→ サーキットブレーカー検査（OPEN なら 503 で高速失敗）→ RoundRobin で worker 選択 → PHP worker の `Logistics` ファサード（パッケージ内 RetryingClient が 2 回リトライ内蔵）→ 209 社の運送会社 → `logistics_tracking_query` に保存 + キャッシュ書き込み → 標準化 JSON を返却。

**コールバックチェーン（非同期）**：運送会社 webhook → `/api/callback/{carrier}` ホワイトリストルート + 署名検証 → `logistics_tracking_event` に保存 + クエリ記録を更新 → webman キューに書き込み → 非同期コンシューマーが購読設定に従って加盟店コールバック URL へプッシュ（HMAC 署名 + 冪等キー + 指数バックオフリトライ + 手動再プッシュ入口）。

> コールバックプッシュの初版は PHP キューに留めます —— イベント解析とデータは PHP 側にあり、言語をまたいでイベントを渡すメリットはありません。プッシュスループットがボトルネック（分あたり万件以上）になったら、コンシューマーを e-cat（ecat-mq + retry ミドルウェア）に移設します。外部契約は変わりません。

## セキュリティ対策

<img src="diagrams/security.svg" alt="セキュリティ対策" width="100%">

レイヤードディフェンスイン深度。要点は以下の通り：

- **ゲートウェイ層**（tracking-gateway）：API-Key 認証、Redis レート制限（key / IP 単位）、運送会社別サーキットブレーカー、SSRF 対策（worker エンドポイントのホワイトリスト解決）；`/internal` は内網のみ + 共有キーヘッダー；資格情報の隔離 —— e-cat は資格情報の平文を保持しません；
- **アプリケーション層**（admin）：JWT + ブラックリスト（2h access / 14d refresh）、RBAC method.path 粒度権限、操作監査の全チェーン記録；`SecurityFilter` が XSS / SQL インジェクション / CSRF / コマンドインジェクション / パストラバーサルを遮断；機密データは `Encryptable` で暗号化保存 + マスキングしてエクスポート；ログイン 5 回失敗で 15 分ロック + クリック CAPTCHA；
- **コールバックセキュリティ**：ホワイトリストルート + HMAC 署名検証、at-least-once 配信 + 冪等キーによる重複プッシュ防止；
- **統一エラーセマンティクス**：レート制限 429、サーキットブレーカー 503、運送会社エラーは `carrier_error`。クライアントに内部詳細を漏らしません。
- **決済セキュリティ**（M8/M10）：Stripe / PayPal webhook 検証（HMAC-SHA256 / verify-webhook-signature）、自動注文確定 + admin 手動フォールバック；決済キーは `Encryptable` で暗号化し `logistics_system_config` に保存；
- **仮想通貨入金検証**（M9）：USDT TRC20 は Tronscan API で自動検証、BEP20 / ERC20 は手動確認；
- **クライアントキーセキュリティ**（M7）：X-API-Key はクライアント自設（16 文字以上）、sha256 で保存し平文は作成時に一度だけ返却；クライアント JWT（token_type=client）は admin JWT と分離；
- **ゲートウェイ攻撃検知**（M11）：`ecat-security` SecurityBodyLayer をゲートウェイに統合（注入 / プロトコル / データシリアライズ / ファイル / 機密データ漏えい検知器）；攻撃ペイロードはゲートウェイ層で遮断、アプリケーション層のセキュリティパッケージがフォールバック；
- **CDN セキュリティ**（M12）：Cloudflare 無料プランで全サイト HTTPS + 二層 WAF（エッジ管理ルール + ゲートウェイのアプリケーション層検知）；Tunnel オリジンでソースをゼロ露出；コールバックは DNS 専用サブドメイン直結で CDN 障害時に注文を失わない；レート制限は X-API-Key 単位で CDN エッジ IP の影響を受けない；認証エンドポイントは常に no-store でユーザー間キャッシュ混入を防止；
- **CDN 認証情報管理**（M13）：CDN プロバイダーの access_key / access_secret を `Encryptable` で暗号化して `logistics_cdn_provider` テーブルに保存、`/admin/cdn/provider` で設定；

## 機能一覧

<img src="diagrams/description.svg" alt="プラットフォーム機能" width="100%">

- **追跡クエリ集約: 1 つの追跡番号で全世界を検索 — 187 の番号パターン規則が国内 / 国際チャネルと運送会社を自動判定し、209 社のアダプタが `TrackStatus` の 7 標準ステータスに出力を統一;**
- **マルチキャリア連携: 国内 45 + 国際 164 アダプタ、DHL / FedEx / UPS / USPS と各国郵便 S10 を完全カバー、認証情報は暗号化保存、ハードコードなし;**
- **管理画面 RBAC: JWT + ブラックリスト + method.path 粒度の権限 + 全操作監査、セキュリティフィルタが XSS / SQL インジェクション / CSRF / コマンドインジェクションを遮断;**
- **決済クローズドループ: Stripe / PayPal に加え USDT TRC20 / BEP20 / ERC20、webhook 署名検証で注文を自動確定、決済手段は設定で即有効化;**
- **クライアントポータルとプラン: 登録 / ログイン / アプリ管理 / プラン / 注文 API、X-API-Key はクライアントが自己設定、client JWT は admin と完全分離;**
- **API ゲートウェイ防御: API-Key 認証、Redis レート制限（429）、キャリア別サーキットブレーカー（503）、SSRF 対策、攻撃ペイロードはゲートウェイ層で遮断;**
- **CDN 安全配信: Cloudflare 無料版で全サイト HTTPS + 二重 WAF + エッジキャッシュ、Tunnel オリジンで公開露出ゼロ;**
- **多言語 SDK: Python / PHP / Node.js / Go / Rust 向け 5 つのゼロ依存 SDK、コピーするだけ。**

## ワンクリックインストール

推奨: Docker Compose によるワンコマンドデプロイ — 5 つのサービス（Nginx / PHP / MySQL / Redis / Elasticsearch）をヘルスチェックとデータ永続化付きで起動します:

```bash
bash install.sh
```

リポジトリをクローンした後:

```bash
cd integrated-global-logistics   # プロジェクトルートへ移動
bash install.sh                  # 既定ポート 80、NGINX_PORT=8080 で変更可能
```

スクリプトが Docker 環境を確認し、全サービスを起動してヘルスチェックをポーリング（最大 120 秒）; 準備完了後 `http://localhost/install` を開いてインストールウィザードを完了します（データベース初期化 + 管理者作成）。詳細な Docker Compose デプロイは [admin/README.md](../../admin/README.md) を参照。

## クイックスタート

**admin 管理バックエンド**（PHP webman）：

```bash
cd admin
composer install
php start.php start
```

起動後、ブラウザでインストールウィザードにアクセスしてデータベース初期化と管理者作成を完了します：`http://localhost:8787/install`（デフォルトポート 8787、`config/server.php` で変更可）。

**infrastructure クエリゲートウェイ**（Rust e-cat）：

```bash
cd infrastructure
cargo build
```

**SDK 呼び出し**（依存ゼロの 5 クライアント、コピーしてすぐ使えます）：

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
```

```bash
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/query \
  -H "Content-Type: application/json" -d '{"tracking_no": "LX123456789CN"}'
```

各言語の使い方と例は [sdk/README.md](../../../sdk/README.md) を参照してください。

詳細なデプロイは [admin/README.md](../../../admin/README.md)（Docker Compose で 5 サービスを編成：Nginx / PHP / MySQL / Redis / Elasticsearch）と実装計画ドキュメントを参照してください。

## ドキュメント

- [admin/docs/API.md](../../../admin/docs/API.md) —— API リファレンス（統一レスポンス形式、エラーコード、認証フロー、レート制限ポリシー、ミドルウェアチェーン）
- [admin/docs/ARCHITECTURE.md](../../../admin/docs/ARCHITECTURE.md) —— アーキテクチャ設計
- [admin/docs/DESIGN.md](../../../admin/docs/DESIGN.md) —— 設計ドキュメント
- [admin/docs/SECURITY.md](../../../admin/docs/SECURITY.md) —— セキュリティアーキテクチャ
- [docs/logistics-aggregation-platform-plan.md](../../../docs/logistics-aggregation-platform-plan.md) —— プラットフォーム実装計画（アーキテクチャ、データフロー、データベース設計、API 契約、マイルストーン）
- [admin/README.md](../../../admin/README.md) —— 管理バックエンド完全解説（技術スタック、データベース規約、デプロイ、CI/CD）
- [sdk/README.md](../../../sdk/README.md) —— 外部 API クライアント SDK（Python / PHP / Node.js / Go / Rust、5 つすべて依存なし、コピーしてすぐ使える）

## Translations（他の言語）

- [English](/docs/translations/en/README.md)
- [한국어](/docs/translations/ko/README.md)
- [Русский](/docs/translations/ru/README.md)
- [Deutsch](/docs/translations/de/README.md)
- [Français](/docs/translations/fr/README.md)
- [Español](/docs/translations/es/README.md)
- [Português](/docs/translations/pt/README.md)
- [हिन्दी](/docs/translations/hi/README.md)
- [العربية](/docs/translations/ar/README.md)
- [বাংলা](/docs/translations/bn/README.md)
- [Bahasa Indonesia](/docs/translations/id/README.md)
- [日本語](/docs/translations/ja/README.md)

## オープンソースは容易ではありません。応援をお願いします

| 微信（WeChat） | 支付宝（Alipay） |
|:---:|:---:|
| <img src="../../weixinpay.png" width="130" height="130" alt="微信"> | <img src="../../alipay.png" width="130" height="130" alt="支付宝"> |

### グローバル送金によるサポート（海外送金）

**受取人情報**

- 受取人氏名：WANG KEXUN
- 受取口座番号：881015918251

**受取銀行**

- ZA Bank SWIFT Code：AABLHKHHXXX
- 銀行名：ZA Bank Limited
- 銀行番号：387
- 銀行住所：Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**海外送金の中継銀行（必要な場合）**

> これは海外送金の中継銀行（intermediary bank）情報であり、受取銀行の情報ではありません。送金銀行に中継銀行情報が必要かどうかお問い合わせください。

- **香港ドル・人民元・米ドルの送金**、中継銀行は Citibank：
  - 銀行名：Citibank N.A. Hong Kong
  - SWIFT Code：CITIHKHXXXX
  - 銀行番号：006
  - 支店名：Hong Kong Branch
  - 支店番号：391
  - 銀行住所：Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **その他通貨の送金**、中継銀行は BNY Mellon：
  - 銀行名：THE BANK OF NEW YORK MELLON
  - SWIFT Code：IRVTUS3NXXX
  - 銀行住所：THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### 仮想通貨の寄付 (Crypto Donation)

このプロジェクトがお役に立ったら、QRコードをスキャンして寄付してください。ありがとうございます！

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

<!-- TRANSLATE-READY -->
