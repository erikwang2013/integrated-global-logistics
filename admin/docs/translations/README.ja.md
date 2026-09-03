> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

# 开放管理后台 (open-admin)

webman v2 + Flutter ベースのフルスタック管理バックエンドシステム。

> [アーキテクチャ図](docs/ARCHITECTURE.ja.md) | [設計ドキュメント](docs/DESIGN.ja.md) | [セキュリティアーキテクチャ](docs/SECURITY.ja.md) | [API リファレンス](docs/API.ja.md)

## 機能一覧

<img src="../../../docs/diagrams/description.svg" alt="機能一覧" width="100%">


| 業務領域 | 機能 | 説明 |
|--------|------|------|
| 🔐 認証 | ログイン/トークン更新/ログアウト | クリック型 CAPTCHA + JWT + ブラックリスト |
| | アカウントロック | 5 回失敗で 15 分間ロック |
| | 同時セッション制限 | 同一ユーザー最大 3 つの有効 Token |
| 📊 ダッシュボード | リアルタイム統計/トレンド図/分布図/最近の操作 | Redis キャッシュ 5 分 |
| 👥 ユーザー管理 | CRUD + 一括削除/有効・無効化 | ソフト削除 + パスワード再確認 |
| | Excel 一括インポート | 行単位の検証 + エラーレポート |
| 🔒 ロール・権限 | ロール CRUD + 権限ツリー | RBAC method.path 粒度の認可 |
| ⚙ システム設定 | キーバリュー CRUD | グループ管理 |
| 📋 操作監査 | ログ照会 + ソース端検出 | 8 プラットフォーム自動識別 |
| 📁 ファイル管理 | アップロード/Excel エクスポート/PDF エクスポート | 機密データ自動マスキング |
| 🛡 セキュリティ | 18 層の多層防御 | XSS/SQLインジェクション/パストラバーサル/コマンドインジェクション/CSRF/レート制限/CSP... |
| 🏥 運用保守 | ヘルスチェック/metrics/API ドキュメント/security.txt | Prometheus + OpenAPI 3.0 + hg/apidoc 対話型ドキュメント |
| 🌐 国際化 | 中国語・英語切り替え | Accept-Language ヘッダー / ?lang= パラメータ |

## 技術スタック

| レイヤー | 技術 | 説明 |
|---|------|------|
| バックエンドフレームワーク | webman v2 (workerman) | 超高性能 PHP 常駐プロセスフレームワーク |
| PHP バージョン | 8.3+ | |
| データベース | MySQL 8.0+ | テーブルプレフィックス `logistics_`、BIGINT 非オートインクリメント主キー |
| 検索エンジン | Elasticsearch | `webman-scout` 経由で同期・検索 |
| 管理画面フロントエンド | Flutter 3.x | Web は PC 管理バックエンドスタイル（`apps/flutter/`） |
| モバイル端末 | HarmonyOS ArkTS | 鴻蒙（HarmonyOS）ネイティブクライアント（`apps/harmonyos/`）、スマホ/タブレット/2in1 対応 |

## コア依存パッケージ

| パッケージ | 用途 |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake アルゴリズムでグローバル一意の BIGINT 主キーを生成 |
| `erikwang2013/hashids` | API 層の ID 暗号化・復号化、実データベース ID の隠蔽 |
| `erikwang2013/jwt-webman` | JWT 認証トークンの発行・検証 |
| `erikwang2013/encryption` | インターフェース転送層の機密データ暗号化・復号化 |
| `erikwang2013/encryptable` | データベース保存層の機密フィールド自動暗号化・復号化 |
| `erikwang2013/webman-scout` | Elasticsearch データ同期と全文検索 |
| `erikwang2013/season` | 国旗データ |
| `erikwang2013/poster-php` | クリック型 CAPTCHA の生成・検証 + ポスター生成 |
| `phpoffice/phpspreadsheet` | Excel エクスポート |
| `barryvdh/laravel-dompdf` | PDF エクスポート（Dompdf ベース） |

## プロジェクト構造

```
open-admin/
├── app/
│   ├── admin/controller/       # 管理側コントローラー
│   │   ├── DashboardController.php # ダッシュボード（Redis キャッシュ）
│   │   ├── UserController.php      # ユーザー CRUD + 一括操作
│   │   ├── RoleController.php      # ロール CRUD
│   │   ├── PermissionController.php# 権限 CRUD
│   │   ├── ConfigController.php    # システム設定 CRUD
│   │   ├── LogController.php       # 操作ログ照会
│   │   ├── ProfileController.php   # 個人センター + ログアウト
│   │   ├── ExportController.php    # Excel/PDF エクスポート
│   │   ├── ImportController.php    # Excel ユーザーインポート
│   │   ├── UploadController.php    # ファイルアップロード
│   │   ├── HealthController.php    # ヘルスチェック
│   │   ├── DocsController.php      # OpenAPI ドキュメント
│   │   └── BaseController.php      # ベースコントローラー
│   ├── api/
│   │   └── v1/controller/          # API v1 コントローラー（バージョンはリクエストヘッダー API-Version で制御）
│   │       ├── CaptchaController.php # クリック型 CAPTCHA
│   │       └── AuthController.php    # ログイン/トークン更新
│   ├── common/                 # 共通ユーティリティクラス
│   │   ├── HashidsService.php  # ID エンコード/デコード
│   │   ├── SnowflakeService.php# Snowflake ID 生成
│   │   └── EncryptionService.php # データ暗号化・復号化 + マスキング
│   ├── middleware/             # ミドルウェア
│   │   ├── Cors.php            # クロスオリジン
│   │   ├── SecurityFilter.php  # 攻撃検知ブロック（HTTP メソッド制限/XSS/SQL インジェクション/パストラバーサル/コマンドインジェクション/CSRF）
│   │   ├── RateLimit.php       # Redis レート制限（スライディングウィンドウ + レスポンスヘッダー）
│   │   ├── AdminAuth.php       # JWT 認証 + ブラックリスト
│   │   ├── AdminPermission.php # RBAC 権限検証
│   │   └── OperationLog.php    # 操作ログ自動記録（ソース端検出含む）
│   └── model/                  # データモデル
├── apps/
│   ├── flutter/                # Flutter Web 管理バックエンド（PC スタイル）
│   │   └── lib/app/
│   │       ├── pages/          # 5 つの完全ページ（ダッシュボード/ユーザー/ロール/設定/ログ/個人センター）
│   │       ├── services/       # ApiService（JWT インターセプター）+ AuthService（Token 永続化）
│   │       └── layouts/        # レスポンシブ管理レイアウト（サイドバー+ヘッダー+コンテンツ領域）
│   └── harmonyos/              # HarmonyOS ネイティブクライアント（Token シームレス更新）
├── config/                     # 設定ファイル（中国語コメント含む）
│   ├── route.php               # ルーティング + API バージョン戦略
│   ├── middleware.php           # グローバルミドルウェア登録
│   └── ...                     # 各コンポーネントの設定
├── database/install.sql        # SQL インストールスクリプト（権限シードデータ含む）
├── public/                     # 公開エントリ
├── runtime/                    # ランタイムファイル
└── vendor/                     # Composer 依存関係
```

## 環境要件

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41（フロントエンド開発のみ必要）
- Elasticsearch >= 7.x（任意、検索機能に必要）

## クイックスタート

### 1. 依存関係のインストール

```bash
composer install
```

### 2. 環境変数の設定

環境変数をコピーして変更します（任意。設定しなければ `config/*.php` 内のデフォルト値が使用されます）:

```bash
cp .env.example .env
```

主要な設定項目：

| 環境変数 | 説明 | デフォルト値 |
|---------|------|--------|
| `JWT_SECRET` | JWT 署名キー | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids ソルト値 | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API 暗号化キー | 32 バイトのデフォルト値 |
| `SNOWFLAKE_DATACENTER_ID` | データセンター ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ワーカーノード ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES アドレス | `http://localhost:9200` |

**本番環境では必ずすべてのキーをランダム文字列に変更してください。**

### 3. ワンクリックインストール

サービス起動後、ブラウザでインストールウィザードにアクセスしてデータベース初期化と管理者作成を行います：

```bash
php start.php start
```

デフォルトでは `http://0.0.0.0:8787` で待ち受けます（ポートは `config/server.php` で変更可能）。

ブラウザで **`http://localhost:8787/install`** を開き、ウィザードに従って入力します：

| ステップ | 内容 |
|------|------|
| ① データベース設定 | ホストアドレス、ポート、データベース名、ユーザー名、パスワード |
| ② 管理者設定 | 管理者ユーザー名、パスワード（デフォルト admin / admin888） |

「インストール開始」をクリックすると、テーブル作成、権限データのシード、管理者アカウント作成が自動で行われ、`.env` にデータベース設定が書き込まれます。

> インストール完了後、`runtime/install.lock` ロックファイルが生成されます。再インストールが必要な場合はこのファイルを削除してください。

### 4. ログイン

`http://localhost:8787` にアクセスし、インストール時に設定した管理者アカウントでログインします。

### 5. フロントエンドの起動（任意）

**Flutter 管理バックエンド（Web）:**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web（PC 管理バックエンドスタイル）
```

**HarmonyOS クライアント（モバイル）:**

DevEco Studio で `apps/harmonyos/` ディレクトリを開き、実機またはエミュレータに接続して実行します。

### 6. Docker Compose ワンクリックデプロイ（本番環境推奨）

プロジェクトには完全な Docker オーケストレーション構成が用意されており、Nginx、PHP (webman app)、MySQL、Redis、Elasticsearch の 5 つのサービスが含まれます。

```bash
# 1. Docker 環境変数の設定
cp .env.docker .env

# 2. すべてのサービスを起動
docker-compose up -d

# 3. ブラウザでインストールウィザードにアクセスし初期化
# http://localhost:8787/install  (データベースと管理者情報を入力)
# または SQL マイグレーションを手動実行（app コンテナに入る）:
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. アクセス
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx リバースプロキシ)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer、`php:8.3-cli` ベース
- `docker-compose.yml`: 5 サービス構成、ネットワーク分離、データボリューム永続化
- `.env.docker`: Docker 環境専用の環境変数


## データベース規約

- **テーブルプレフィックス**: `logistics_`
- **主キー**: 全テーブルの主キーは `id BIGINT UNSIGNED NOT NULL`、**AUTO_INCREMENT は禁止**
- **ID 生成**: 主キー ID はアプリケーション層の `SnowflakeService::generate()` で生成、分散環境で一意
- **必須フィールド**: 各テーブルに `id`, `created_at`, `updated_at` を含める
- **ソフト削除**: ソフト削除が必要なテーブルに `deleted_at DATETIME DEFAULT NULL` を追加
- **機密フィールド**: 携帯番号、メール、身分証番号などは `encryptable` プラグインで自動暗号化・復号化、DB フィールドは `VARCHAR(500)` で暗号文を保存

## API ドキュメント

完全な API リファレンス（統一レスポンス形式、エラーコード、全エンドポイント詳細、認証フロー、レート制限戦略、ミドルウェアチェーン）は **[docs/API.md](docs/API.ja.md)** を参照してください。要点は以下の通り：

- **統一レスポンス形式**: `{ "code": 0, "message": "success", "data": {...} }`、`code=0` は成功を意味
- **エラーコード**: `400` パラメータエラー / `401` 未ログイン / `403` 権限なし / `404` 存在しない / `422` 検証失敗 / `429` レート制限 / `500` サーバーエラー
- **認証**: `Authorization: Bearer <token>`；access_token の有効期限は 2 時間、refresh_token は 14 日
- **ID 処理**: リクエスト/レスポンス内の ID は hashids 暗号化文字列、実際のデータベース ID を公開しない

## フロントエンド説明

### Flutter 管理バックエンド（PC スタイル）

- **レイアウト**: サイドバー（折りたたみ可能 64px/240px）+ ヘッダー + コンテンツ領域、レスポンシブ 3 ブレークポイント（スマホ/タブレット/デスクトップ）
- **ページ**: ログイン、ダッシュボード、ユーザー管理、ロール権限、システム設定、操作ログ、個人センター
- **状態管理**: GetX（`ApiService` シングルトン + `AuthService` Token 永続化）
- **ダッシュボード**: 統計カード、トレンド折れ線グラフ（fl_chart）、円グラフ、最近の操作ログ
- **エクスポート**: Excel/PDF エクスポート、PDF には削除不可の著作権情報を含む
- **一括操作**: 複数選択の一括削除、一括有効化/無効化
- **テーマ**: Material 3 ライト/ダークのデュアルテーマ

### HarmonyOS モバイル

- **ページ**: ログイン、ダッシュボード、ユーザー一覧/詳細、個人センター
- **認証**: JWT Bearer + 401 時に Token を自動シームレス更新、更新失敗時は自動でログインページへリダイレクト
- **ストレージ**: Token は AppStorage で管理

## 開発規約

- グローバル関数/クラス参照には前置 `\` を付けず、統一して `use` でインポート
- すべての PHP ファイルの先頭に著作権宣言を含める
- すべての設定ファイルに中国語のコメント説明を含める
- データベース主キーはアプリケーション層の snowflake で生成し、オートインクリメントは禁止
- API 層のすべてのパラメータとレスポンス内の ID は hashids で暗号化・復号化する
- AdminPermission ミドルウェアは Redis でユーザー権限をキャッシュ（TTL=60s）、N+1 クエリボトルネックを解消

## デプロイ

### Docker Compose（推奨）

プロジェクトルートに `docker-compose.yml` があり、5 つのサービスを構成します：

| サービス | イメージ | ポート |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | ローカル `Dockerfile` でビルド | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP イメージは `Dockerfile` でビルド、ベースイメージは `php:8.3-cli`、OPcache を有効化。

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions 継続的インテグレーションパイプライン：`.github/workflows/ci.yml`

- PHP 構文チェック (`php -l`)
- PHPUnit ユニットテスト
- Flutter 静的解析 (`flutter analyze`)

### データベースバックアップ

`database/backup/` ディレクトリ：

- `backup.sh` — mysqldump + gzip バックアップ、30 日前の古いバックアップを自動削除
- `restore.sh` — 対話式リストア、利用可能なバックアップを一覧表示して選択

### Nginx セキュリティ設定

本番デプロイでは `docs/nginx-security.conf` を参照してリバースプロキシのセキュリティ強化を設定してください。

## オープンソースは継続が困難、ご支援をお願いします

| 微信 (WeChat) | 支付宝 (Alipay) |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

### 海外送金による投げ銭（クロスボーダー送金）

**受取人情報**

- 受取人名：WANG KEXUN
- 受取口座番号：881015918251

**受取銀行**

- ZA Bank SWIFT Code：AABLHKHHXXX
- 銀行名：ZA Bank Limited
- 銀行番号：387
- 銀行住所：Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**クロスボーダー送金代理銀行（必要な場合）**

> これはクロスボーダー送金の代理銀行（中継銀行）情報であり、受取銀行の情報ではありません。代理銀行情報の提供が必要かどうかは送金銀行にお問い合わせください。

- **香港ドル・人民元・米ドルの送金**、代理銀行は Citibank：
  - 銀行名：Citibank N.A. Hong Kong
  - SWIFT Code：CITIHKHXXXX
  - 銀行番号：006
  - 支店名：Hong Kong Branch
  - 支店番号：391
  - 銀行住所：Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **その他の通貨の送金**、代理銀行は BNY Mellon：
  - 銀行名：THE BANK OF NEW YORK MELLON
  - SWIFT Code：IRVTUS3NXXX
  - 銀行住所：THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
