# API リファレンスドキュメント

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](API.md) | [English](API.en.md) | [한국어](API.ko.md) | [Русский](API.ru.md) | [Deutsch](API.de.md) | [Français](API.fr.md) | [Español](API.es.md) | [Português](API.pt.md) | [हिन्दी](API.hi.md) | [العربية](API.ar.md) | [বাংলা](API.bn.md) | [Bahasa Indonesia](API.id.md) | [日本語](API.ja.md)

## 1. 概要

开放管理后台 (open-admin) は webman v2 をベースに構築された RESTful JSON API を提供します。すべての管理側インターフェースには JWT 認証と RBAC 権限検証が必要で、公開インターフェースは API バージョンヘッダーによってバージョン化されたコントローラーにルーティングされます。

- **ベース URL**: `http://localhost:8787`
- **言語**: `Accept-Language` ヘッダーまたは `?lang=zh_CN|en` パラメータで切り替え（デフォルト zh_CN）、Locale ミドルウェアが自動検出

> **エンドポイント一覧**: 認証(5) | ダッシュボード(1) | ユーザー(7) | ロール(4) | 権限(4) | 設定(4) | ログ(1) | 個人センター(3) | インポート・エクスポート(3) | アップロード(1) | 運用(4: health/metrics/docs/security.txt) | 全 37 エンドポイント
- **認証**: `Authorization: Bearer <token>`（JWT）
- **レスポンス形式**: `{ "code": 0, "message": "success", "data": {...} }`
- **ドキュメントエンドポイント**: `GET /api/docs` が OpenAPI 3.0 JSON 仕様を返す

### リクエスト要件

- 許可されるメソッドは `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` のみ。その他の HTTP メソッド（TRACE、CONNECT、PATCH など）を使用すると 405 が返る
- すべての `POST` / `PUT` リクエストは `Content-Type: application/json` を設定する必要がある（ファイルアップロードを除く）。違反すると 415 が返る
- リクエストボディのサイズは 10MB を超えてはならない。超過すると 413 が返る
- セキュリティフィルターはすべてのリクエスト入力に対し XSS、SQL インジェクション、パストラバーサル、コマンドインジェクションをスキャンし、ヒットすると 403 が返る
- 連続 5 回のログイン失敗でアカウントロックが発生（15 分間）。ロック中はログインリクエストに 429 が返る
- 同一ユーザーが同時に保持できる有効 Token は最大 3 つ。超過時は最も古い Token が自動的にブラックリスト入りする

## 2. エラーコード

| code | 意味 | 発生シーン |
|------|------|---------|
| 0 | 成功 | |
| 400 | リクエストパラメータエラー | リクエスト形式が不正 |
| 401 | 未認証 | Token 欠落 / 期限切れ / ブラックリスト入り |
| 403 | 権限なし / セキュリティブロック | RBAC 権限不足 / SecurityFilter ヒット |
| 404 | リソースが存在しない | 照会/更新/削除の対象が存在しない |
| 405 | リクエストメソッドが許可されていない | GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可、非標準メソッドは直接拒否 |
| 413 | リクエストボディが大きすぎる | Content-Length が 10MB 超過 |
| 415 | サポートされていないメディアタイプ | POST/PUT リクエストの Content-Type が JSON 以外かつファイルアップロードでない |
| 422 | パラメータ検証失敗 | 必須フィールド欠落、形式不一致、業務検証エラー |
| 429 | リクエストが多すぎる | RateLimit 発動 / アカウントロック（連続5回ログイン失敗で15分ロック） |
| 500 | サーバー内部エラー | |

## 3. 公開エンドポイント

すべての公開エンドポイントは `/api` グループにマウントされ、`ApiVersion` ミドルウェアが `API-Version` ヘッダーに応じて対応するバージョン化コントローラー（例: `app\api\v1\controller\AuthController`）へ振り分けます。

### 3.1 ヘルスチェック

```
GET /health
```

- **認証**: 不要
- **レート制限**: なし

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`、`redis`、`elasticsearch` の値: `"ok"` | `"unavailable"`。`elasticsearch` は ES に到達できない場合 `"unavailable"` を返し、クラスタのヘルスステータスが green/yellow 以外の場合は実際の status 値（例: `"red"`）を返します。

### 3.2 API ドキュメント

```
GET /api/docs
```

- **認証**: 不要
- **レート制限**: グローバルデフォルト (60回/分)
- **レスポンス**: OpenAPI 3.0.3 JSON 仕様、全エンドポイント定義、パラメータ、Schema を含む

### 3.3 キャプチャ生成

```
POST /api/v1/captcha/generate
```

- **認証**: 不要
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "difficulty": "medium"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| difficulty | string | いいえ | `easy` / `medium` / `hard`、デフォルト `medium` |

**レスポンス例** — クリック型 (`type: "click"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "type": "click",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "targets": [
        { "order": 1, "text": "A", "x": 120, "y": 85 },
        { "order": 2, "text": "B", "x": 310, "y": 42 }
      ]
    }
  }
}
```

**レスポンス例** — スライダー型 (`type: "slider"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "def456abc789",
    "type": "slider",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "x": 120,
      "y": 60,
      "puzzle_w": 50,
      "puzzle_h": 50,
      "puzzle": "data:image/png;base64,iVBORw0KGgo..."
    }
  }
}
```

**レスポンス例** — 回転型 (`type: "rotate"`):
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "ghi789abc012",
    "type": "rotate",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "extra": {
      "angle": 45
    }
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| key | string | キャプチャ識別子、検証時に返す |
| type | string | キャプチャタイプ：`click` / `slider` / `rotate` |
| image | string | base64 data URI 画像 |
| extra | object | タイプ別の付加データ（下記参照） |

**`extra` のタイプ別説明**:

| type | extra フィールド | 型 | 説明 |
|------|-----------|------|------|
| click | targets | array | クリック対象、`order`(順序) `text`(ヒント文字) `x` `y`(座標) を含む |
| slider | x, y | int | 欠け部分の左上座標 (300×200 キャンバス基準) |
| slider | puzzle_w, puzzle_h | int | パズル画像の幅と高さ |
| slider | puzzle | string | パズル画像の base64 data URI |
| rotate | angle | int | 正しい回転角度 (0-359)、`360-angle` 回転して画像を正す必要がある |

### 3.4 キャプチャ検証

```
POST /api/v1/captcha/verify
```

- **認証**: 不要
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ** — クリック型 (`type: "click"`):
```json
{
  "key": "abc123def456",
  "type": "click",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

**リクエストボディ** — スライダー型 (`type: "slider"`):
```json
{
  "key": "def456abc789",
  "type": "slider",
  "clicks": 120
}
```

**リクエストボディ** — 回転型 (`type: "rotate"`):
```json
{
  "key": "ghi789abc012",
  "type": "rotate",
  "clicks": 315
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| key | string | はい | キャプチャ key、generate が返したもの |
| type | string | はい | キャプチャタイプ、generate が返した `type` と一致させる必要がある |
| clicks | 可変 | はい | 回答データ、type によって形式が変わる（下記参照） |

**`clicks` のタイプ別説明**:

| type | clicks の型 | 説明 | 許容誤差 |
|------|------------|------|---------|
| click | `[{x:int, y:int}]` | クリック座標の配列、order 順 | 半径 18px |
| slider | `int` | スライダーの X 軸オフセット | ±4px |
| rotate | `int` | 回転角度 (0-359) | ±5° |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

検証通過後、バックエンドは `captcha_verified:{key}` を Redis に書き込み（TTL 300s）、ログインインターフェースはこれに基づいて通過を許可します。
検証失敗時は `code` が 422、`message` が `"验证失败，请重试"`、`data.valid` が `false` になります。

### 3.5 ログイン

```
POST /api/v1/auth/login
```

- **認証**: 不要
- **レート制限**: 10 回/分（IP + パス単位）

**リクエストボディ**:
```json
{
  "username": "admin",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "captcha_key": "abc123def456"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | はい | min:3, max:50 | ユーザー名 |
| password | string | はい | min:6, max:32 (平文) | AES-256-CBC-HMAC 暗号化後 Base64 エンコード（平文も互換） |
| captcha_key | string | はい | | キャプチャ key（事前に `/api/v1/captcha/verify` で検証が必要） |

### パスワード暗号化プロトコル

**RSA-2048 非対称暗号化**を使用します。公開鍵はフロントエンドコード内に配置（安全に公開可能）、秘密鍵はサーバー側のみが保持します。

```
暗号化フロー (クライアント):
  RSA 公開鍵 (PEM) → PKCS1v1.5 暗号化 → Base64 エンコード → 送信

復号化フロー (サーバー側、段階的にフォールバック):
  1. RSA 秘密鍵で復号 → 成功かつ有効な UTF-8 → 復号結果を使用
  2. AES-256-CBC-HMAC 復号 → 成功 → 復号結果を使用（旧クライアント互換）
  3. 平文フォールバック → 元の入力をそのまま使用
```

公開鍵はフロントエンドアプリに組み込まれており、ネットワーク経由での送信は不要です。秘密鍵は `.env` の `RSA_PRIVATE_KEY` にのみ保存され、漏洩させてはいけません。

> AES 対称暗号化は旧バージョン互換のための方式で、すべてのクライアントが RSA へ移行した後に削除されます。

**レスポンス例**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| access_token | string | JWT アクセストークン |
| refresh_token | string | JWT リフレッシュトークン |
| expires_in | int | アクセストークンの有効期間（秒）、デフォルト 7200 |
| user.id | string | hashid で暗号化されたユーザー ID |
| user.username | string | ユーザー名 |
| user.real_name | string | 氏名 |

**発生し得るエラー**:
- 422: パラメータ検証失敗（必須フィールド欠落、形式不一致）
- 422: 先にキャプチャ検証を完了してください（captcha_key が `/api/v1/captcha/verify` を通過していない）
- 401: ユーザー名またはパスワードが誤り
- 403: アカウントが無効化されている
- 429: アカウントがロックされています。15分後に再試行してください（連続5回のログイン失敗で発動）

### 3.6 登録

```
POST /api/v1/auth/register
```

- **認証**: 不要
- **レート制限**: 5 回/分（IP + パス単位）

**リクエストボディ**:
```json
{
  "username": "newuser",
  "password": "djGYscnyS5V6mW6KyDFjB8vGwjBBnB3Odpyxu8LY...",
  "real_name": "新用户",
  "captcha_key": "abc123def456"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | はい | min:3, max:50 | ユーザー名（一意） |
| password | string | はい | min:6, max:32 (平文) | AES-256-CBC-HMAC 暗号化後 Base64 エンコード |
| real_name | string | はい | max:50 | 氏名 |
| captcha_key | string | はい | | キャプチャ key（事前に `/api/v1/captcha/verify` で検証が必要） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

登録成功後、直接 JWT トークンが返され、ユーザー状態はデフォルトで有効（status=1）です。

### 3.7 トークン更新

```
POST /api/v1/auth/refresh
```

- **認証**: 不要
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| refresh_token | string | はい | ログイン/登録時に取得した refresh_token |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

更新成功時は新しい access_token と refresh_token が同時に返され、旧トークンは自動的に無効になります。更新時にはユーザーの最終ログイン時間と IP が更新されます。

**発生し得るエラー**:
- 422: リフレッシュトークンがありません
- 401: リフレッシュトークンが無効または期限切れ

### 3.8 Prometheus 監視メトリクス

```
GET /metrics
```

- **認証**: 不要
- **レート制限**: なし
- **レスポンス形式**: Prometheus text format (`text/plain; version=0.0.4`)

Prometheus 監視メトリクスを公開するエンドポイントで、Grafana/Prometheus が取得するために使用します。

**レスポンス例**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| メトリクス名 | 型 | 説明 |
|------|------|------|
| `openadmin_http_requests_total` | gauge | 累計 HTTP リクエスト総数 |
| `openadmin_active_users` | gauge | 現在のアクティブユーザー数（24時間以内にログイン） |
| `openadmin_db_connection_status` | gauge | データベース接続状態、1=正常, 0=異常 |
| `openadmin_redis_connection_status` | gauge | Redis 接続状態、1=正常, 0=異常 |
| `openadmin_memory_usage_bytes` | gauge | PHP プロセスの現在のメモリ使用量（bytes） |

## 4. ダッシュボード

すべての管理側インターフェースは `/admin` グループにマウントされ、`AdminAuth`（JWT 認証）、`AdminPermission`（RBAC 権限検証）、`OperationLog`（操作記録）の 3 つのミドルウェアを通過します。

### 4.1 ダッシュボードデータ

```
GET /admin/dashboard
```

- **認証**: JWT + RBAC
- **キャッシュ**: Redis 5 分

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats フィールド | 型 | 説明 |
|------|------|------|
| label | string | 指標名 |
| value | string | 指標値（文字列型） |
| icon | string | Material アイコン名 |
| color | string | カードの色値 |
| trend | float? | 日次前日比成長率（パーセント）、"用户总数" のみこのフィールドを持つ |

| trends フィールド | 型 | 説明 |
|------|------|------|
| dates | array{string} | 直近 30 日間の日付シーケンス |
| series | array{object} | トレンドラインデータ、各項目に name（名称）、data（数値配列）、color（色）を含む |

## 5. ユーザー管理

すべてのユーザー管理インターフェースが返す `id` は hashid 暗号化文字列です。パスワードフィールドはレスポンスから除外されています。携帯番号とメールは一覧インターフェースではマスク表示され、詳細インターフェースでは平文で返されます（データベースの暗号化フィールドは Encryptable trait が自動復号）。

### 5.1 ユーザー一覧

```
GET /admin/user
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1 ページあたりの件数 |
| keyword | string | いいえ | | 検索キーワード、ユーザー名と氏名を照合 |
| status | int | いいえ | | 状態フィルター、0=無効、1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化されたユーザー ID |
| username | string | ユーザー名 |
| real_name | string | 氏名 |
| phone | string | マスクされた携帯番号（`138****5678` 形式） |
| email | string | マスクされたメール（`a***@example.com` 形式） |
| status | int | 1=有効, 0=無効 |
| last_login_at | string | 最終ログイン時間 (datetime) |
| created_at | string | 作成時間 (datetime) |

### 5.2 ユーザー作成

```
POST /admin/user
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | はい | min:3, max:50 | ユーザー名（一意） |
| password | string | はい | min:6, max:32 | パスワード（bcrypt で保存） |
| real_name | string | はい | max:50 | 氏名 |
| phone | string | いいえ | | 携帯番号（Encryptable で暗号化保存） |
| email | string | いいえ | | メール（Encryptable で暗号化保存） |
| status | int | いいえ | in:0,1 | 状態、デフォルト 1（有効） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**発生し得るエラー**:
- 422: ユーザー名が既に存在します
- 422: パラメータ検証失敗（必須フィールド欠落）

### 5.3 ユーザー詳細

```
GET /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

詳細インターフェースでは `phone` と `email` が平文で返されます（データベースでは暗号化保存、Encryptable cast が自動復号）。マスクはされません。`password` と `id_card` は常にレスポンスに含まれません。

**発生し得るエラー**:
- 404: ユーザーが存在しない

### 5.4 ユーザー更新

```
PUT /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID

**リクエストボディ**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| real_name | string | いいえ | 氏名、未送信の場合は元の値を保持 |
| password | string | いいえ | 新しいパスワード、空文字または未送信の場合は変更しない |
| phone | string | いいえ | 携帯番号 |
| email | string | いいえ | メール |
| status | int | いいえ | 0=無効, 1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**発生し得るエラー**:
- 404: ユーザーが存在しない

### 5.5 ユーザー削除

```
DELETE /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID
- **機密操作**: パスワードの再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| password | string | はい | 現在ログイン中のユーザーパスワード（再確認） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ソフト削除（Eloquent SoftDeletes）を実行し、データは物理削除されず deleted_at がマークされます。

**発生し得るエラー**:
- 404: ユーザーが存在しない
- 422: 機密操作にはパスワード入力による確認が必要です（password が空）
- 422: パスワード検証失敗（パスワード不一致）

### 5.6 ユーザー一括削除

```
POST /admin/user/batch/destroy
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードの再確認が必要

**リクエストボディ**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| ids | array{string} | はい | hashid 暗号化されたユーザー ID 配列 |
| password | string | はい | 現在ログイン中のユーザーパスワード（再確認） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

ソフト削除を実行し、`data.count` が実際の削除数です。

**発生し得るエラー**:
- 422: 削除するユーザーを選択してください（ids が空）
- 422: 無効な ID（hashid デコード失敗）
- 422: パスワード検証失敗

### 5.7 ユーザー一括有効化/無効化

```
POST /admin/user/batch/status
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| ids | array{string} | はい | hashid 暗号化されたユーザー ID 配列 |
| status | int | はい | 0=無効, 1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message は status の値に応じて動的に `"批量启用成功"` または `"批量禁用成功"` に変わります。

**発生し得るエラー**:
- 422: ユーザーを選択してください（ids が空）
- 422: 状態値が無効（status が 0 または 1 ではない）

## 6. ロール管理

### 6.1 ロール一覧

```
GET /admin/role
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1 ページあたりの件数 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化されたロール ID |
| name | string | ロール名 |
| slug | string | ロール識別子（一意、権限判定に使用） |
| description | string | ロール説明 |
| status | int | 1=有効, 0=無効 |
| users_count | int | このロールを持つユーザー数 |

### 6.2 ロール作成

```
POST /admin/role
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| name | string | はい | max:50 | ロール名 |
| slug | string | はい | max:50 | ロール識別子 |
| description | string | いいえ | | ロール説明、デフォルト空文字列 |
| status | int | いいえ | | 状態、デフォルト 1 |
| permission_ids | array{int} | いいえ | | 権限 ID 配列（元の INT ID、hashid ではない） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 ロール更新

```
PUT /admin/role/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| name | string | いいえ | ロール名 |
| description | string | いいえ | 説明 |
| status | int | いいえ | 0=無効, 1=有効 |
| permission_ids | array{int} | いいえ | 権限 ID 配列、送信するとロール権限を同期（上書き） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 ロール削除

```
DELETE /admin/role/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードの再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

削除時、ロールとすべての権限・ユーザーとの関連付けが自動的に解除され、その後ロールレコードが物理削除されます。

## 7. 権限管理

権限はツリー構造（parent_id 自己参照）を採用し、3 つのタイプに分かれます。一覧インターフェースは完全な権限ツリーを返します。

### 7.1 権限ツリー

```
GET /admin/permission
```

- **認証**: JWT + RBAC

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化 |
| parent_id | string | 親権限の hashid、"0" はルートノード |
| name | string | 権限名 |
| slug | string | 権限識別子（ルート/ボタン識別子） |
| type | int | 1=メニュー, 2=ボタン, 3=インターフェース |
| icon | string | メニューアイコン（Material アイコン名） |
| path | string | フロントエンドルートパス |
| sort | int | ソート値（昇順） |
| children | array? | 子権限リスト（再帰）、子ノードがない場合はこのフィールドを含まない |

### 7.2 権限作成

```
POST /admin/permission
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| parent_id | int | いいえ | | 親権限 ID（元の INT 型）、デフォルト 0 |
| name | string | はい | max:50 | 権限名 |
| slug | string | はい | max:100 | 権限識別子 |
| type | int | はい | in:1,2,3 | 1=メニュー, 2=ボタン, 3=インターフェース |
| icon | string | いいえ | | メニューアイコン、デフォルト空 |
| path | string | いいえ | | フロントエンドルートパス、デフォルト空 |
| sort | int | いいえ | | ソート値、デフォルト 0 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 権限更新

```
PUT /admin/permission/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| name | string | いいえ | 権限名 |
| icon | string | いいえ | アイコン |
| path | string | いいえ | ルートパス |
| sort | int | いいえ | ソート値 |

### 7.4 権限削除

```
DELETE /admin/permission/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードの再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

削除時、すべての子権限（`parent_id` = 現在の権限 ID のレコード）がカスケード削除され、同時にすべてのロールとの関連が解除されます。

## 8. システム設定

システム設定は `group` + `key` の組み合わせで一意になります。

### 8.1 設定一覧

```
GET /admin/config
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1 ページあたりの件数 |
| group | string | いいえ | | 設定グループでフィルター |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid |
| group | string | 設定グループ（例: `system`、`email`、`storage`） |
| key | string | 設定キー |
| value | string | 設定値 |
| type | string | 値の型ヒント（`string`、`integer`、`boolean`、`json` など） |
| description | string | 設定説明 |

### 8.2 設定作成

```
POST /admin/config
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| group | string | はい | max:100 | 設定グループ |
| key | string | はい | max:100 | 設定キー（同じグループ内で一意） |
| value | string | はい | | 設定値 |
| type | string | いいえ | | 値の型、デフォルト `string` |
| description | string | いいえ | | 設定説明、デフォルト空 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**発生し得るエラー**:
- 422: 設定項目が既に存在します（同じ group + key）

### 8.3 設定更新

```
PUT /admin/config/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| value | string | いいえ | 設定値の更新 |
| type | string | いいえ | 値の型の更新 |
| description | string | いいえ | 説明文の更新 |

### 8.4 設定削除

```
DELETE /admin/config/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードの再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

設定レコードを物理削除します。

## 9. 操作ログ

操作ログは読み取り専用インターフェースで、`OperationLog` ミドルウェアが POST/PUT/DELETE リクエストのたびに自動的に書き込みます。保存フィールドは `user_id`、`action`、`method`、`path`、`ip`、`source`、`input` です。

### 9.1 操作ログ一覧

```
GET /admin/log
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1 ページあたりの件数 |
| user_id | int | いいえ | | ユーザー ID で完全一致フィルター（元の INT 型） |
| action | string | いいえ | | 操作アクションで完全一致フィルター |
| path | string | いいえ | | リクエストパスで部分一致フィルター |
| start_date | string | いいえ | | 開始日 (Y-m-d 形式) |
| end_date | string | いいえ | | 終了日 (Y-m-d 形式) |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid |
| user_name | string | 操作ユーザー名（user リレーションから取得、未ログイン操作は"系统"と表示） |
| action | string | 操作アクションの説明 |
| method | string | HTTP メソッド（POST/PUT/DELETE） |
| path | string | リクエストパス |
| ip | string | クライアント IP |
| source | string | リクエストソース |
| input | string | リクエストパラメータの JSON 文字列（ファイルを含まない） |
| created_at | string | 操作時間 (datetime) |

## 10. 個人センター

個人センターのインターフェースは JWT 認証のみ必要です（RBAC 権限検証は不要——`AdminPermission` ミドルウェアがこれをホワイトリストに追加します）。

### 10.1 個人情報の更新

```
PUT /admin/profile
```

- **認証**: JWT

**リクエストボディ**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| real_name | string | いいえ | 氏名 |
| phone | string | いいえ | 携帯番号（Encryptable で暗号化保存） |
| email | string | いいえ | メール（Encryptable で暗号化保存） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

レスポンス内の `phone` と `email` は平文で返され、`password` と `id_card` は除外されています。

### 10.2 パスワード変更

```
PUT /admin/profile/password
```

- **認証**: JWT

**リクエストボディ**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| old_password | string | はい | | 現在のパスワード |
| new_password | string | はい | min:6, max:32 | 新しいパスワード |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**発生し得るエラー**:
- 422: 旧パスワードと新パスワードを入力してください
- 422: 旧パスワードが誤り
- 422: 新パスワードの長さは 6-32 桁

### 10.3 ログアウト

```
POST /admin/profile/logout
```

- **認証**: JWT

**リクエストボディ**: なし（requestBody なし、Authorization ヘッダーから token を読み取る）

**レスポンス例**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

ログアウト処理：JWT をデコードして残りの有効期限 (exp - now) を取得し、その token の md5 ハッシュを Redis ブラックリスト `jwt_blacklist:{md5}` に書き込み、TTL = 残りの有効期限。ブラックリスト内の token は `AdminAuth` ミドルウェアでブロックされ、401 が返ります。

token がない場合は 401 が返ります。token が期限切れ/無効な場合（デコードで例外が発生）もログアウト成功と見なされます。

## 11. インポート・エクスポート

### 11.1 Excel エクスポート

```
POST /admin/export/excel
```

- **認証**: JWT + RBAC
- **レスポンスタイプ**: ファイルダウンロード（`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`）

**リクエストボディ**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| フィールド | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| table | string | いいえ | `admin_user` | エクスポートするテーブル名。対応: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | いいえ | | エクスポートする列のフィールド名配列、空の場合は該当テーブルの全列をエクスポート |
| conditions | object | いいえ | `{}` | フィルター条件、key-value ペア、値が空でない場合 WHERE に使用 |
| title | string | いいえ | `数据导出` | Excel タイトル（Sheet 名として表示） |

**対応テーブルと列**:

| table | 利用可能な列 |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

機密フィールド `phone`、`email`、`id_card` はエクスポート時に自動的にマスク処理されます。データ上限は 10000 行。Excel は先頭行固定、自動フィルター付き。

### 11.2 PDF エクスポート

```
POST /admin/export/pdf
```

- **認証**: JWT + RBAC
- **レスポンスタイプ**: ファイルダウンロード（`application/pdf`、A4 横向き）

**リクエストボディ**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

またはテーブルモード:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| フィールド | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| type | string | いいえ | `table` | エクスポートタイプ：`table` / `dashboard` |
| title | string | いいえ | `数据导出` | PDF タイトル |
| data | object | いいえ | `{}` | エクスポートデータ |

`type=dashboard` のとき `data` に `stats` 配列（カード形式でレンダリング）を含める必要があります。`type=table` のとき `data` に `columns` と `rows` 配列を含める必要があります。

PDF テンプレートには著作権情報とエクスポートタイムスタンプが含まれます。

### 11.3 ユーザーインポート (Excel)

```
POST /admin/import/users
```

- **認証**: JWT + RBAC
- **リクエストタイプ**: `multipart/form-data`（ファイルアップロード）

**フォームフィールド**:

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| file | file | はい | `.xlsx` または `.xls` 形式 |

**Excel 列の要件**:

| 列名 | 必須 | 説明 |
|------|------|------|
| username | はい | ユーザー名（一意） |
| password | はい | パスワード（bcrypt ハッシュで保存） |
| real_name | はい | 氏名 |
| phone | いいえ | 携帯番号 |
| email | いいえ | メール |
| status | いいえ | 状態、デフォルト 1 |

1 行目は列タイトル（大文字小文字を区別しない）、2 行目以降がデータです。

**レスポンス例**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| total | int | 総行数（タイトル行を除く） |
| success | int | 正常にインポートされた数 |
| failed | int | 失敗した件数 |
| errors | array | 失敗の詳細、各項目に row（Excel 行番号）と reason（失敗理由）を含む |

## 12. ファイルアップロード

```
POST /admin/upload
```

- **認証**: JWT + RBAC
- **リクエストタイプ**: `multipart/form-data`

**フォームフィールド**:

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| file | file | はい | アップロードするファイル |

**許可されるファイルタイプ**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**最大ファイルサイズ**: 10MB

**レスポンス例**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

ファイルは日付ごとのディレクトリ `public/upload/{Y-m-d}/` に保存され、ファイル名は `md5(uniqid) + 元の拡張子` です。`url` はサイトルートパスからの相対パスです。

**発生し得るエラー**:
- 422: ファイルを選択してください（アップロードされていません）
- 422: サポートされていないファイルタイプ
- 422: ファイルサイズが 10MB を超えることはできません
- 500: ファイルアップロード失敗（ファイルが無効）

## 13. レスポンスヘッダー

すべてのインターフェース（グローバルミドルウェア層で注入）には以下のレスポンスヘッダーが含まれます：

| ヘッダー | 説明 |
|----|------|
| `X-RateLimit-Limit` | レート制限の上限（回数） |
| `X-RateLimit-Remaining` | 残りリクエスト回数 |
| `X-RateLimit-Reset` | レート制限ウィンドウのリセットタイムスタンプ |
| `Retry-After` | レート制限発動時のみ返される、推奨待機秒数 |
| `X-Content-Type-Options` | `nosniff`（webman デフォルト、MIME スニッフィングを禁止） |
| `X-Frame-Options` | `DENY`（webman の CORS ミドルウェア/ベース設定で提供） |

レート制限の詳細:
- デフォルトのグローバル制限: 60 回/分 / IP+パス
- ログインエンドポイント `/api/v1/auth/login`: 10 回/分
- 登録エンドポイント `/api/v1/auth/register`: 5 回/分
- Redis の原子化スライディングウィンドウアルゴリズム（Lua ZSET）を使用し、TOCTOU 競合を回避
- Redis が利用できない場合は fail open（通過させる）、リクエストをブロックしない

## 14. 認証フロー

完全な認証シーケンス：

```
1. 客户端请求 POST /api/v1/captcha/generate
    ↓
   服务端返回: key + type(click|slider|rotate) + base64 图片 + extra(类型相关数据)
   
2. 用户交互完成验证码操作（点击/拖拽/旋转），客户端收集答案
   
3. 客户端请求 POST /api/v1/captcha/verify
   (请求头: Content-Type: application/json)
   请求体: { key, type, clicks }
   - type=click:  clicks = [{x, y}, ...]        // 坐标数组
   - type=slider: clicks = 120                   // X 偏移量
   - type=rotate: clicks = 315                   // 旋转角度
    ↓
   服务端:
   a. 从存储读取 captcha:key 数据（TTL 300s）
   b. 按 type 校验答案（click: 欧氏距离 ≤18px / slider: ±4px / rotate: ±5°）
   c. 校验通过 → 写入 Redis `captcha_verified:{key}` = 1 (TTL 300s)
   d. 校验失败 → 返回 422，计数 +1，超过 3 次 key 作废
    ↓
   服务端返回: { valid: true/false }

4. 客户端请求 POST /api/v1/auth/login
   (请求头: Content-Type: application/json)
   请求体: { username, password(加密), captcha_key }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 检查 captcha_verified:{key} 是否存在 → 422
   c. 删除 captcha_verified:{key}（一次性使用）
   d. 解密密码: EncryptionService::decrypt(password) → 明文
   e. 校验用户凭证 (password_verify) → 401
   f. 检查账号状态 → 403/429
   g. 签发 JWT (access + refresh) → 200
   h. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

5. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

6. Access Token 过期前刷新
   客户端请求 POST /api/v1/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

7. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT 構造

- **access_token**: `{ sub: <user_id>, username: "<name>" }`、デフォルト TTL 7200 秒（JWT 設定 `default_expire` で制御）
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`、デフォルト TTL 1209600 秒（JWT 設定 `refresh_expire` で制御、すなわち 14 日）

### セキュリティ管理

- パスワードは `PASSWORD_BCRYPT` ハッシュで保存
- パスワード転送層は AES-256-CBC-HMAC 暗号化（クライアント暗号化 → サーバー復号）、平文フォールバックに対応
- 機密フィールド（phone, email, id_card）は `erikwang2013/encryptable` でデータベース層において透過的に暗号化・復号化
- API 層の ID は `erikwang2013/hashids` で暗号化して送信し、元の snowflake ID 系列の露出を回避
- SecurityFilter はグローバルに XSS、SQL インジェクション、パストラバーサル、コマンドインジェクションをスキャン、同一 IP 5回/60秒で一時ブラックリスト 15 分
- 機密操作（ユーザー、ロール、権限、設定の削除）には現在ログイン中のユーザーパスワードによる再確認が必要
- 同時セッション制限：同一ユーザーの有効 Token は最大 3 つ、4 台目のデバイスでログインすると最も古い Token が強制的にブラックリスト入り
- アカウントロック：連続 5 回のログイン失敗で 15 分間のアカウントロックが発動、ロック中は 429 を返す

### ミドルウェアアーキテクチャ

グローバルミドルウェアはすべてのリクエストに作用し、順に実行されます：

```
Cors（跨域预处理 + 响应头）
  → Locale（Accept-Language 语言检测 / ?lang=zh_CN|en）
  → SecurityFilter（HTTP方法限制/请求体大小/Content-Type校验/XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截）
  → RateLimit（Redis 滑动窗口限流 + 账号锁定：5次登录失败锁定15分钟）
  → ClientAuth（客户端 JWT 认证，token_type=client，/api/v1 客户端门户路由组）
  → AdminAuth（JWT 认证 + 黑名单，/admin 路由组）
  → AdminPermission（RBAC 鉴权 / Redis 60s 缓存，/admin 路由组）
  → OperationLog（POST/PUT/DELETE 自动记录，含来源端检测，/admin 路由组）
```

`/health` と `/api/docs` は公開エンドポイントで、`Cors → SecurityFilter → RateLimit` のみを通過します。

セキュリティ強化：
- **アカウントロック**：連続 5 回のログイン失敗でアカウントが自動的に 15 分間ロックされ、その間のログインは 429 を返す
- **同時セッション制限**：同一ユーザーの有効 Token は最大 3 つ、超過時は最も古い Token が自動的にブラックリスト入り
- **security.txt**：`GET /.well-known/security.txt` で RFC 9116 標準のセキュリティ連絡先情報を提供
- **Nginx セキュリティ設定**：`docs/nginx-security.conf` を参照して完全なリバースプロキシセキュリティ強化の例を提供

### 操作ソース端検出

OperationLog ミドルウェアがクライアントプラットフォームを自動識別し、操作ログの `source` フィールドに書き込みます：

| プラットフォーム | 検出方法 |
|------|---------|
| `ipados` | UA に iPad を含む |
| `macos` | UA に Macintosh/Mac OS を含む |
| `windows` | UA に Windows を含む |
| `linux` | UA に Linux を含む（Android を除く） |
| `ios` | UA に iPhone / iOS / CFNetwork を含む |
| `android` | UA に Android を含む |
| `harmonyos` | UA に HarmonyOS / OpenHarmony を含む、または `X-Client-Platform` ヘッダーで明示宣言 |
| `web` | デフォルト（上記すべてに該当しない場合） |

> 2 段階検出：`X-Client-Platform` リクエストヘッダー（ネイティブ App の宣言）→ User-Agent 自動推測（フォールバック）。操作ログ照会 `GET /admin/log` の `source` フィールドがソース端です。

## 15. デプロイと運用

### Docker Compose

プロジェクトルートに `docker-compose.yml` があり、5 つのサービス（Nginx、webman app、MySQL、Redis、Elasticsearch）を構成します。PHP は `Dockerfile` でビルドされます（`php:8.3-cli` ベース、OPcache 有効）。

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` に GitHub Actions 継続的インテグレーションパイプラインが定義されています：
- `php -l` 構文チェック
- PHPUnit ユニットテスト
- `flutter analyze` 静的解析

### データベースバックアップ

`database/backup/` ディレクトリにバックアップ・リストアスクリプトがあります：
- `backup.sh` — mysqldump + gzip 圧縮バックアップ、30 日前の古いバックアップファイルを自動削除
- `restore.sh` — 対話式リストア、既存バックアップを一覧表示して選択

### Nginx セキュリティ設定

本番環境デプロイでは `docs/nginx-security.conf` を参照してリバースプロキシのセキュリティ強化設定を行ってください。

## 16. 内部 gRPC インターフェース（e-cat → PHP worker）

e-cat クエリゲートウェイ専用です。PHP 側は workerman h2c サービス（`tcp://0.0.0.0:8792`、プロセス `internal_grpc`）です。契約は `infrastructure/tracking-gateway/proto/internal.proto`（package `internal.v1`）に定義され、メッセージは protobuf でエンコードされ、metadata に `x-internal-token` を必ず含める必要があります（値は `config/logistics.php` の `internal_token`、本番では `INTERNAL_TOKEN` 環境変数で上書き）。トークンが無い/誤りの場合は `code=401 UNAUTHORIZED` を返します。

### 16.1 RPC 一覧

| RPC | リクエスト | レスポンス | 説明 |
|---|---|---|---|
| `Detect` | `{tracking_no}` | `{code, carrier_code, channel, confidence}` | 運送会社の識別。識別できない場合は `404 CARRIER_NOT_DETECTED` |
| `Query` | `{carrier_code, tracking_no, credential_id?}` | `{code, query_no, carrier_code, tracking_no, status, delivered_at, estimated_delivery_at, latest_description, raw_status, events[]}` | 追跡クエリ。`credential_id` は任意（複数認証情報の場合は指定、`carrier_code` と一致する必要があり、不一致は `400 INVALID_PARAMS`）。毎回のクエリは `logistics_tracking_query` に記録される |
| `Carriers` | `{}` | `{code, carriers[]}` | レジストリ内の全運送会社 `{carrier_code, channel}` |

### 16.2 エラー契約（レスポンスの `code` フィールド、gRPC status は常に 0）

| `code` | `error_code` | シナリオ |
|---|---|---|
| 400 | `INVALID_PARAMS` | パラメータ不足 / credential_id 不一致 |
| 401 | `UNAUTHORIZED` | 内部トークンが誤り |
| 404 | `CARRIER_NOT_FOUND` / `TRACKING_NOT_FOUND` / `CARRIER_NOT_DETECTED` | 運送会社が未登録 / 追跡が存在しない / 識別不可 |
| 5001 | `CARRIER_AUTH_ERROR` / `CARRIER_NETWORK_ERROR` / `CARRIER_ERROR` | 上流の認証情報エラー / ネットワーク異常 / 運送会社の業務エラー（構造化された上流メッセージを含む。例：`[SF-INTERNATIONAL A1001] 必須パラメータを空にすることはできません`） |
| 500 | `INTERNAL_ERROR` | 内部例外 |


## 17. e-cat クエリゲートウェイ外部 API

Rust 製ハイ頻度ゲートウェイ（`infrastructure/tracking-gateway`）、`0.0.0.0:8080`（デフォルト）で待ち受け、リクエストヘッダに `X-API-Key`（設定 `api_keys`）を付与します。ゲートウェイは API-Key 認証、Redis キャッシュヒット（プレフィックス `logistics:`）、レート制限、運送会社別サーキットブレーカー、RoundRobin による worker 転送を担います。認証情報がゲートウェイに渡ることはありません。

| 端点 | 说明 |
|---|---|
| `GET /v1/health` | ヘルスチェック（公開エンドポイント、キー不要） |
| `GET /v1/openapi.json` | OpenAPI 3.0 ドキュメント（公開エンドポイント、キー不要） |
| `GET /v1/carriers` | 運送会社一覧（PHP gRPC `InternalService.Carriers` を転送） |
| `POST /v1/tracking/query` | 追跡クエリ（`{"carrier_code": "...", "tracking_no": "..."}`）。`carrier_code` 省略時はゲートウェイが自動識別（detect は query 内部で完了） |
| `GET /v1/tracking/{query_no}` | クエリ番号で直近の追跡結果を取得 |
| `POST /v1/subscriptions` | コールバック購読の登録（`{"carrier_code", "callback_url", "event_type"?}`、`event_type` はデフォルト `tracking.update`） |


ゲートウェイ側の追加セマンティクス：`rate_limit`（デフォルト 100回/60秒）超過で `429`。運送会社の連続失敗でサーキットブレーカー発動（デフォルト 5xx/転送エラー 5回で 60秒 OPEN）→ `503`。worker 到達不能 → `502`。ゲートウェイと worker 間は内部 gRPC（h2c、`0.0.0.0:8792`）、共有キー `x-internal-token` で認証。第 16 節を参照。

### 17.1 レスポンスエンベロープとエラー

すべてのエンドポイントは統一エンベロープ `{"code": 0, "message": "ok", "data": ...}` を返します。`code != 0` はエラーで `data` は省略されます。業務エラーの本文には `error_code` / `error_message` が含まれる場合があります（上流運送会社の診断情報、ゲートウェイが透過的に転送）。`code` は HTTP ステータスと一致します：

| HTTP | 意味 |
|---|---|
| 400 | パラメータエラー（`tracking_no` 欠落 / 購読パラメータ不正） |
| 401 | キーが無い、または無効 |
| 404 | クエリ番号が存在しない |
| 429 | レート制限（デフォルト キーあたり 100回/60秒）、バックオフして再試行推奨 |
| 502 | worker 到達不能、または上流障害 |
| 503 | 運送会社のサーキットブレーカー、バックオフして再試行推奨 |

### 17.2 呼び出し例（curl）

デモキー `demo-api-key`、ゲートウェイデフォルト `http://127.0.0.1:8080`：

```bash
# ヘルスチェック（公開エンドポイント、キー不要）
curl http://127.0.0.1:8080/v1/health

# OpenAPI ドキュメント（公開エンドポイント、キー不要）
curl http://127.0.0.1:8080/v1/openapi.json

# 運送会社一覧
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/carriers

# 追跡クエリ（運送会社の指定は任意）
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "tracking_no": "LX123456789CN"}' \
  http://127.0.0.1:8080/v1/tracking/query

# クエリ番号で直近の結果を取得
curl -H "X-API-Key: demo-api-key" http://127.0.0.1:8080/v1/tracking/{query_no}

# コールバック購読の登録（コールバック先は公網 http/https 必須。ゲートウェイはループバック/私網を拒否）
curl -X POST -H "X-API-Key: demo-api-key" -H "Content-Type: application/json" \
  -d '{"carrier_code": "china-post", "callback_url": "https://example.com/cb", "event_type": "tracking.update"}' \
  http://127.0.0.1:8080/v1/subscriptions
```

### 17.3 呼び出し例（SDK）

リポジトリの `sdk/` ディレクトリに 5 つの依存なし SDK（Python / PHP / Node.js / Go / Rust、コピーしてすぐ使える）：

```python
from tracking_client import TrackingClient
client = TrackingClient("demo-api-key", "http://127.0.0.1:8080")
result = client.query_tracking("LX123456789CN")
print(result["status"], result["events"][0]["description"])
```

```php
require "TrackingClient.php";
$client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
$carriers = $client->list_carriers();
$sub = $client->subscribe("china-post", "https://example.com/cb");
```

```js
const { TrackingClient } = require("./tracking-client.js");
const client = new TrackingClient("demo-api-key", "http://127.0.0.1:8080");
const detail = await client.getTracking("query_no");
console.log(detail.status);
```

```go
import "trackingclient"
client := trackingclient.NewClient("demo-api-key", "http://127.0.0.1:8080")
raw, err := client.QueryTracking("LX123456789CN", "china-post")
if err != nil { panic(err) }
fmt.Println(string(raw))
```

```rust
use trackingclient::tracking_client::TrackingClient;
let client = TrackingClient::new("demo-api-key", "http://127.0.0.1:8080")?;
let detail = client.get_tracking("query_no")?;
println!("{}", detail);
```

SDK はエンベロープの `code != 0` またはネットワーク失敗時に例外（`TrackingApiError`）を投げ、`code` / `message` / `error_code` / `error_message` / `http_status` を含みます。レート制限・サーキットブレーカーはバックオフ再試行を推奨、運送会社側のエラーは再試行不要。詳細は `sdk/README.md`。
