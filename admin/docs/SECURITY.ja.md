# セキュリティアーキテクチャ設計ドキュメント

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](SECURITY.md) | [English](SECURITY.en.md) | [한국어](SECURITY.ko.md) | [Русский](SECURITY.ru.md) | [Deutsch](SECURITY.de.md) | [Français](SECURITY.fr.md) | [Español](SECURITY.es.md) | [Português](SECURITY.pt.md) | [हिन्दी](SECURITY.hi.md) | [العربية](SECURITY.ar.md) | [বাংলা](SECURITY.bn.md) | [Bahasa Indonesia](SECURITY.id.md) | [日本語](SECURITY.ja.md)

## 1. 多層防御の全景

システムは 7 層の多層防御モデルを採用し、外から内へ層ごとに悪意のあるリクエストをフィルタリングします。どの単一の層が機能しなくなっても、後続の層が防御を引き継ぎます。

ミドルウェアチェーン全体は以下の順序で実行されます（`config/middleware.php` 参照）：

```
请求 → Cors → Locale(Accept-Language) → SecurityMiddleware (erikwang2013/security-php, 31种检测器) → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| 層 | ミドルウェア/仕組み | 防御対象 |
|----|--------|---------|
| 1 | SecurityMiddleware (erikwang2013/security-php) | 31 種の攻撃検知 + HTTP メソッド検証 + リクエストボディサイズ制限 + Content-Type 検証 + CSRF + IP 攻撃昇格ブラックリスト |
| 2 | Cors | クロスオリジンセキュリティ + レスポンスセキュリティヘッダー注入 |
| 3 | RateLimit | Redis スライディングウィンドウレート制限、ブルートフォース攻撃防止 |
| 4 | AdminAuth | JWT 認証 + ブラックリストログアウト |
| 5 | AdminPermission | RBAC method.path 粒度の認可 |
| 6 | OperationLog | 操作監査 + ソース端追跡 |
| 7 | データ暗号化 | Hashids ID 難読化 + Encryptable DB 暗号化 + EncryptionService 転送暗号化 |

フロントエンド（Flutter）にも独立した入力検証が 3 層あり、バックエンドは信用しません。各層が独立して防御します。

---

## 2. 攻撃検知エンジン

## 2. 攻撃検知エンジン (erikwang2013/security-php)

攻撃検知は自前の SecurityMiddleware (erikwang2013/security-php) から `erikwang2013/security-php` v1.1+ 専用セキュリティパッケージへ移行されました。**31 種の検知器**を提供し、5 大攻撃カテゴリをカバーします。

### 2.1 検知器の分類

**インジェクション攻撃 (11種):** XSS、SQLインジェクション、コマンドインジェクション、NoSQLインジェクション、LDAPインジェクション、XPathインジェクション、JNDI/Log4Shell、SSIサーバーサイドインクルード、GraphQLインジェクション、SSTIテンプレートインジェクション

**プロトコルとリクエスト攻撃 (9種):** SSRF、XXE、HTTPレスポンスヘッダーインジェクション、Hostヘッダー攻撃、Request Smuggling、Open Redirect、CORSバイパス、WebSocketハイジャック、DNS Rebinding

**HTTP プロトコル層検証 (6種):** HTTPメソッド検証(405)、リクエストボディサイズ制限(413)、Content-Type検証(415)、CSRF Originチェック、IP攻撃昇格ブラックリスト、機密データ漏洩検知

**データとシリアライゼーション攻撃 (5種):** PHP逆シリアライゼーション、CSV数式インジェクション、メールヘッダーインジェクション、JWT攻撃（構造分析）、JS Prototype Pollution

**ファイルとパス攻撃 (2種):** パストラバーサル、悪意のあるファイルアップロード

### 2.2 処理モード

各検知器は独立して 2 つのモードをサポートします：
- `block` — 攻撃を検知したら即座にブロックし、設定されたステータスコードを返す
- `log` — ログ記録のみでブロックしない（`header_injection`、`ssti`、`nosql_injection` はデフォルトで log モード、誤検知を防止）

### 2.3 IP 攻撃昇格ブラックリスト

同一 IP が 60 秒以内に 5 回攻撃検知を発動 → 自動で 15 分間ブロック。ストレージバックエンドは Redis（分散）、File（単機 JSON）、Cache（高並行独立ファイル）から選択可能で、現在は Redis ストレージを使用しています。

### 2.4 セキュリティログ

ファイル位置：`runtime/logs/security.log`（自動ローテーション、10MB/ファイル）

---

## 4. レスポンスセキュリティヘッダー

すべてのヘッダーは `Cors` ミドルウェアで注入され、`$response->withHeaders()` で各レスポンスに追加されます。

| ヘッダー | 値 | 役割 |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | 任意のオリジンのクロスオリジンアクセスを許可（内網管理バックエンドのシーン） |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | 許可されるメソッドセット |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | 許可されるカスタムヘッダー |
| Access-Control-Max-Age | `86400` | プリフライトリクエストを 24 時間キャッシュ |
| X-Content-Type-Options | `nosniff` | ブラウザの MIME スニッフィングを禁止 |
| X-Frame-Options | `DENY` | すべての iframe 埋め込みを禁止、クリックジャッキングを防止 |
| X-XSS-Protection | `1; mode=block` | ブラウザ内蔵 XSS フィルターを有効化し、ページレンダリングをブロック |
| Referrer-Policy | `strict-origin-when-cross-origin` | 同一オリジンでは完全な URL、クロスオリジンではドメインのみ送信 |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | サイト全体でカメラ/マイク/位置情報 API を無効化 |

OPTIONS プリフライトリクエストは直接 204 空レスポンスを返し、後続のミドルウェアチェーンには入りません。

### 4.2 Content-Security-Policy (CSP)

他のセキュリティヘッダーとともに Cors ミドルウェアで注入され、多層防御を提供し、ブラウザが読み込み・実行できるリソースソースを制限します。

| ヘッダー | 値 | 役割 |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | スクリプト/スタイル/画像/接続/フレーム/フォームなどのリソースソースを制限 |
| X-Permitted-Cross-Domain-Policies | `none` | Adobe Flash/PDF などのクロスドメインポリシーファイルの読み込みを禁止 |

CSP ポリシーの要点：
- `default-src 'self'`：デフォルトでは同一オリジンリソースのみ許可
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`：同一オリジンスクリプト + インラインスクリプト（Flutter Web に必須）+ eval（Flutter Web デバッグに必須）を許可
- `frame-ancestors 'none'`：任意のページによる iframe 埋め込みを禁止、X-Frame-Options: DENY と二重の保険
- `base-uri 'self'`：`<base>` タグを同一オリジンのみに制限
- `form-action 'self'`：フォームの送信先を同一オリジンのみに制限

---

## 5. レート制限戦略

### アルゴリズム

Redis Sorted Set スライディングウィンドウ + Lua 原子化スクリプト、主要な操作：

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Lua スクリプトは Redis サーバー側でシングルスレッド実行されるため、**本質的に原子化**されており、TOCTOU（Time-of-check to Time-of-use）競合状態を排除します。

### レート制限設定

| ルート | 制限 | ウィンドウ | シーン |
|------|------|------|------|
| デフォルト（全ルート） | 60 回/分 | 60s | 汎用 API |
| `/api/v1/auth/login` | 10 回/分 | 60s | ログイン（ブルートフォース防止） |
| `/api/v1/auth/register` | 5 回/分 | 60s | 登録（一括登録防止） |

### レスポンスヘッダー

レート制限発動時は HTTP 429 と JSON body を返します：
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

すべてのレスポンス（正常レスポンスを含む）は以下のヘッダーを保持します：

| ヘッダー | 説明 |
|----|------|
| X-RateLimit-Limit | 現在のウィンドウで許可される最大リクエスト数 |
| X-RateLimit-Remaining | 現在のウィンドウで残り利用可能なリクエスト数 |
| X-RateLimit-Reset | ウィンドウがリセットされる Unix タイムスタンプ |
| Retry-After | レート制限時のみ付与、推奨待機秒数 |

### 降級戦略

Redis 異常（接続タイムアウト、利用不可など）時は **fail-open**：

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, 放行所有请求
}
```

短時間レート制限保護を失う方が、正常な業務リクエストをブロックするよりはましです。

### 5.4 アカウントロック機構

ログインインターフェースはレート制限に加えて、特定ユーザーを狙ったブルートフォースを防ぐ**アカウントロック**機構を追加で備えています。

**ロックフロー**：

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**ロック中の動作**：

ロック中はすべてのログインリクエストが直接 429 を返し、パスワード検証を行いません。ブルートフォースの試行を完全に阻止します。

**設定定数**：

| 定数 | 値 | 意味 |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | 最大連続失敗回数 |
| LOCKOUT_DURATION | 900 | ロック持続時間（秒）、すなわち 15 分 |

注意：アカウントロックは IP ではなく `userId` に基づくため、攻撃者が IP を変更してもロックを回避できません。IP レート制限（10回/分）と重ねて二重防御を形成します：
- IP 層：10 回/分のレート制限が分散ブルートフォースを阻止
- アカウント層：5 回失敗でロックが対象型ブルートフォースを阻止

---

## 6. 認証と認可

### 6.1 JWT 認証

AdminAuth ミドルウェアで実装され、認証が必要なルートグループにマウントされます。

**パラメータ設定**（`config/plugin/erikwang2013/jwt/jwt`、`.env` から注入）：

| パラメータ | 値 | 説明 |
|------|-----|------|
| アルゴリズム | HS256 | HMAC-SHA256 対称署名 |
| キー | `JWT_SECRET` | 環境変数から注入、本番環境では交換が必要 |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| 発行者 | `open-admin` | `JWT_ISSUER` |
| オーディエンス | `open-admin` | `JWT_AUDIENCE` |

**Token 抽出**：`Authorization: Bearer <token>` ヘッダーから抽出し、`Bearer ` プレフィックスを除去して元の JWT を取得します。

**認証フロー**：
1. 空 token → 直接 401 `{"code": 401, "message": "未登录"}`
2. Redis ブラックリスト `jwt_blacklist:{md5(token)}` をチェック → ヒット → 401 `Token已失效，请重新登录`
3. JWT decode → 失敗（期限切れ/署名不一致） → 401 `Token已过期或无效`
4. 成功 → `$request->adminId` と `$request->adminUsername` を注入

**ブラックリスト機構**：ユーザーログアウト時、`md5(token)` を Redis に書き込み、TTL を JWT の残り有効期間に設定します。Redis 障害時はブラックリストチェックがスキップされ（fail-open）、この場合ログアウト済み Token が短時間使用可能ですが、JWT 自体の短期有効期間（2h）がフォールバック保護になります。

### 6.2 同時セッション制限

Token 漏洩後のマルチデバイス悪用を防ぐため、システムは同一ユーザーが同時に保持できる有効 Token 数を制限します。

**制限ロジック**：

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**設定定数**：

| 定数 | 値 | 意味 |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | 同一ユーザーの最大同時 Token 数 |

**強制ログアウトのシーン**：ユーザーが 4 台目のデバイスでログインすると、1 台目のデバイスの Token が強制的にブラックリスト入りし、以降のリクエストは 401 "Token已失效，请重新登录" を返します。

ログアウト時、現在の Token はセットから削除されます。Token が自然に期限切れになると Redis key が自動的に失効し、セットのメンバーも減少します。

### 6.3 RBAC 権限モデル

AdminPermission ミドルウェアで実装します。

**データモデル**：User -> Role -> Permission の 3 層関連

- `logistics_admin_user` (ユーザーテーブル)
- `logistics_admin_user_role` (ユーザー-ロール関連テーブル)
- `logistics_admin_role` (ロールテーブル)
- `logistics_admin_role_permission` (ロール-権限関連テーブル)
- `logistics_admin_permission` (権限テーブル)

**権限タイプ**：
| type | 意味 | 例 |
|------|------|------|
| 1 | メニュー権限 | 左側ナビゲーションの可視性を制御 |
| 2 | ボタン権限 | ページ内の操作ボタンを制御 (新規/編集/削除) |
| 3 | API 権限 | バックエンドインターフェースの呼び出しを制御 |

API 権限識別子の形式：`{method}.{path}`

例えば：
- `post.admin/user` — ユーザー作成
- `put.admin/user` — ユーザー編集
- `delete.admin/user` — ユーザー削除
- `get.admin/user` — ユーザー一覧閲覧

**認可フロー**：
1. `$request->adminId` が空 → 通過（ルートが認証前置を設定していない）
2. ユーザー → ロール（`status=0` の無効ロールはスキップ）→ 権限リストを取得
3. スーパー管理者（`slug = '*'`）→ 直接通過
4. `strtolower(method) . '.' . trim(path, '/')` を構築 → 権限リストと比較
5. マッチ失敗 → 403 `{"code": 403, "message": "无权限访问"}`

**再確認**：BaseController が `confirmPassword()` メソッドを提供し、機密操作（ユーザー削除、データエクスポートなど）では Controller 層で現在のパスワードの入力を追加要求し、セッションハイジャック後の未承認操作を防止します。

---

## 7. 監査ログ

### 7.1 操作ログ

OperationLog ミドルウェアが POST / PUT / DELETE リクエストの操作ログを自動記録します。GET リクエストは記録しません。

**記録フィールド**：

| フィールド | ソース | 説明 |
|------|------|------|
| id | SnowflakeService::generate() | グローバル一意 ID |
| user_id | `$request->adminId` | 操作者 ID、未ログインは 0 |
| action | `$request->method()` | method と同一 |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | リクエストパス |
| ip | `$request->getRealIp()` | クライアントの実 IP |
| source | detectSource() | クライアントソースプラットフォーム |
| input | リクエスト body（マスキング後 JSON） | 操作で送信されたデータ |
| created_at | `date('Y-m-d H:i:s')` | 操作時間 |

**機密フィールドのフィルタリング**：リクエストボディを再帰走査し、以下のフィールドの値を `***` に置き換えます：

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**ソース端検出**（`detectSource()`）：優先順位に従って：

1. 優先的に `X-Client-Platform` カスタムヘッダーを読み取る（ネイティブクライアントの明示宣言）
2. 降級して User-Agent 文字列から推測（`detectSource()` メソッドの検出順序）：

| プラットフォーム | UA キーワード |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | フォールバックのデフォルト値 |

**フォールトトレランス**：ログ書き込みの例外は業務リクエストをブロックしません（`catch (\Throwable)` で静かに握りつぶす）。

### 7.2 セキュリティログ

**ファイル位置**：`runtime/logs/security.log`

**記録内容**：
- 攻撃ブロックログ：攻撃カテゴリ、IP、パス、フィールド、ソース、payload 断片（先頭 200 文字）
- IP ブロック通知：ブロックされた IP、発動回数

ログ権限は `FILE_APPEND | LOCK_EX` で、並行安全な書き込みを保証します。

---

## 8. データ保護

システムはデータフローの 3 段階に対応する 3 層のデータ保護戦略を採用します。

### 8.1 転送層 — EncryptionService

`EncryptionService` は `erikwang2013/encryption` パッケージを使用し、API リクエスト/レスポンス内の機密フィールドを暗号化・復号化します。

**技術詳細**：
- アルゴリズム：`aes-256-cbc-hmac`（HMAC 署名付きで改ざん防止）
- キー：`ENCRYPTION_KEY` 環境変数、自動で 32 バイトに調整
- 用途：クライアントと API の間で携帯番号、身分証番号などのフィールドを転送

**マスキングユーティリティメソッド**：
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com`（ユーザー名が 2 文字超）または `a**@example.com`

### 8.2 保存層 — Encryptable Cast

`AdminUser` モデルは `Erikwang2013\Encryptable\Encryptable` Eloquent cast を使用し、対応フィールド：

- `email` → cast を Encryptable に、自動暗号化・復号化
- `phone` → cast を Encryptable に、自動暗号化・復号化
- `id_card` → cast を Encryptable に、自動暗号化・復号化

データベース書き込み時は自動で暗号文に暗号化され、読み取り時は自動で平文に復号されます。データベースの保存列タイプは `VARCHAR(500)`、暗号文は base64 形式で保存されます。

**キー体系**：転送層暗号化（`ENCRYPTION_KEY`）とは独立して `ENCRYPTABLE_KEY` を使用します。1 つのキーが漏洩しても別の層が機能しなくなることはありません。

キーローテーション：`ENCRYPTION_PREVIOUS_KEYS` 環境変数が履歴キーリスト（カンマ区切り）をサポートし、古いデータ読み取り時に履歴キーで復号を試み、書き戻し時は現在のキーで再暗号化します。

### 8.3 表示層 — ID 難読化とマスキング

**Hashids ID 難読化**：`HashidsService` は `erikwang2013/hashids` パッケージを使用します。

- 外部 API に返すデータベース BIGINT ID を hash 文字列にエンコード（例: `xK3mN9qR2pL7wV8b`）
- クライアントはリクエスト時に hash 文字列を渡し、バックエンドが自動で元の ID にデコード
- ソルト値 `HASHIDS_SALT` は環境変数から注入、ソルト値が異なればエンコード・デコード結果も完全に異なる
- hash 最小長 16 桁、62 文字の英数字セットを使用
- BaseController が `encodeId()`, `decodeId()`, `encodeIds()` の便利メソッドを提供

**エクスポートマスキング**：Excel/PDF エクスポート時（ExportController）、機密フィールドを一律マスキング：
- 携帯番号：`138****1234`
- メール：`a***@example.com`
- 身分証番号：完全に `********` で覆う

---

## 9. キー管理

すべてのキーは `.env` 環境変数から注入され、設定ファイルは `getenv()` で読み取り、フォールバックのデフォルト値を内蔵します（開発環境のみ安全）。

| 環境変数 | 用途 | パッケージ | 本番要件 |
|----------|------|-----|---------|
| JWT_SECRET | JWT 署名キー | erikwang2013/jwt-webman | 64+ 文字のランダム文字列 |
| JWT_ALGORITHM | JWT 署名アルゴリズム | 同上 | HS256 を維持 |
| HASHIDS_SALT | ID エンコードソルト | erikwang2013/hashids | ランダム文字列 |
| SNOWFLAKE_DATACENTER_ID | データセンター ID (0-31) | erikwang2013/snowflake-php | 単一データセンターではデフォルトを維持 |
| ENCRYPTION_KEY | API 転送層暗号化キー | erikwang2013/encryption | 32 バイトのランダム文字列 |
| ENCRYPTABLE_KEY | DB 保存層暗号化キー | erikwang2013/encryptable | 32 バイトのランダム文字列、転送キーとは別のもの |

**セキュリティ要件**：
- `.env` ファイルは `.gitignore` に追加済み、バージョン管理へのコミットは厳禁
- `.env.example` は公開テンプレートファイルで、実際のキーは含まない
- 本番環境では**必ず**すべてのデフォルトキーをランダム文字列に交換
- キーの生成には `openssl rand -base64 32` を推奨

### キー保存の分離

| 層 | 設定キー | キー環境変数 |
|----|--------|-------------|
| 転送暗号化 | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| 保存暗号化 | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID 難読化 | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT 署名 | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

システムは `/.well-known/security.txt` で RFC 9116 標準に準拠したセキュリティ連絡先情報エンドポイントを提供し、セキュリティ研究者が脆弱性発見時に報告経路を素早く見つけられるようにします。

**アクセス方法**：

```
GET /.well-known/security.txt
```

**レスポンス内容**：

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**フィールド説明**：

| フィールド | 説明 |
|------|------|
| Contact | セキュリティ脆弱性の報告連絡先 |
| Expires | ファイルの有効期限、定期的な更新が必要 |
| Preferred-Languages | 優先コミュニケーション言語 |
| Canonical | このファイルの正規 URL |
| Policy | セキュリティポリシー/脆弱性開示ポリシーのリンク |

このエンドポイントはレート制限、認証などのミドルウェアの対象外で、誰でも直接アクセスできます。

---

## 11. Nginx セキュリティ設定

プロジェクトは `docs/nginx-security.conf` を本番環境の Nginx リバースプロキシのセキュリティ強化参考設定として提供します。

**含まれるセキュリティ対策**：

| 設定項目 | 役割 |
|--------|------|
| `server_tokens off` | Nginx のバージョン番号を隠す |
| `client_max_body_size 10m` | リクエストボディサイズを制限、SecurityMiddleware (erikwang2013/security-php) と連携 |
| `limit_req_zone` | Nginx 層でのリクエスト頻度制限 |
| `limit_conn_zone` | 同時接続数制限 |
| `add_header` セキュリティヘッダー | Nginx 層で X-XSS-Protection などのセキュリティヘッダーを追加 |
| `if ($request_method)` | Nginx 層で非標準 HTTP メソッドを拒否 |
| SSL/TLS 設定 | 最新の TLS 1.2/1.3 設定、弱い暗号スイートを無効化 |
| バックエンドヘッダー隠蔽 | `proxy_hide_header` で webman バージョンなどの機密ヘッダーを除去 |

**使用方法**：`docs/nginx-security.conf` 内の設定を Nginx の server ブロックにマージし、実際のドメインと証明書パスに合わせて調整します。

---

## 12. 脅威モデル

### 12.1 防御済みの脅威

| 脅威タイプ | 攻撃ベクトル | 防御層 |
|----------|---------|---------|
| HTTP メソッド悪用 | TRACE/TRACK XST 攻撃、CONNECT トンネルプロキシ、WebDAV メソッドプローブ | SecurityMiddleware http_method 検知器 405 メソッドホワイトリスト |
| 対象型ブルートフォース | 特定ユーザーへのパスワード試行の繰り返し | アカウントロック (5回失敗で15分ロック) + RateLimit (ログイン 10/min) + Captcha |
| ブルートフォース | 分散 IP でのユーザー名/パスワード試行の繰り返し | RateLimit (ログイン 10/min) + Captcha |
| XSS クロスサイトスクリプティング | `<script>`, onerror, javascript: | SecurityMiddleware (erikwang2013/security-php) (5 種のパターン) + X-XSS-Protection レスポンスヘッダー + CSP |
| SQL インジェクション | UNION SELECT, OR 1=1, コメントバイパス | SecurityMiddleware (erikwang2013/security-php) (6 種のパターン) + Eloquent ORM パラメータ化クエリ |
| CSRF クロスサイトリクエストフォージェリ | 悪意のあるサイトによる代理リクエスト送信 | SecurityMiddleware (erikwang2013/security-php) Origin/Referer 検証 |
| パストラバーサル | `../../etc/passwd` | SecurityMiddleware (erikwang2013/security-php) パストラバーサルパターン + UploadController 拡張子ホワイトリスト |
| コマンドインジェクション | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityMiddleware (erikwang2013/security-php) (4 種のパターン) |
| セッションハイジャック | JWT Token の窃取 | JWT 短期有効 (2h) + ブラックリストログアウト + 機密操作のパスワード再確認 |
| ID 列挙 | 数字 ID を走査してデータ量を推測 | Hashids でランダム文字列に難読化 |
| データ漏洩 | DB ダンプ / 中間者攻撃 / ログ漏洩 | 3 層暗号化/マスキング + OperationLog 機密フィールドフィルタリング |
| DoS 攻撃 | 巨大なリクエストボディ / 高頻度リクエスト | リクエストボディ 10MB 制限 + RateLimit 60/min + IP ブラックリスト |
| 権限昇格 | 低権限ユーザーによる管理インターフェースアクセス | RBAC method.path 粒度の認可 |
| ファイルアップロード攻撃 | shell.php.png ダブル拡張子 | SecurityMiddleware (erikwang2013/security-php) 悪意のあるファイル検知 |

### 12.2 既知の制限

| 制限 | 影響範囲 | 緩和策 |
|------|---------|---------|
| CSRF 保護はブラウザのみに有効 | 非ブラウザクライアント（curl, Postman, モバイル App）は Origin/Referer チェックを回避可能 | 非ブラウザクライアントは本質的に CSRF 攻撃を受けない；Cookie の代わりに JWT 認証に依存 |
| Redis 利用不可時にレート制限とブラックリストが fail-open に降級 | 攻撃者がレート制限と高頻度ブロックを回避可能 | Redis 可用性の監視アラート；IP ブラックリストは file/redis/cache の 3 バックエンドで降級可能 |
| 独立 WAF エンジンなし | 正規表現マッチングベースの検知で、専用 WAF ルールエンジンではない | 本番では Nginx ModSecurity または Cloudflare WAF を前置することを推奨 |
| JWT はステートレスで能動的に無効化できない | Token が期限切れになる前にサーバー側から能動的に失効させられない（ブラックリストを除く） | ブラックリスト + 短期 2h TTL でリスクウィンドウを低減 |
| 管理エンドポイントに特別なレート制限なし | 管理インターフェースも通常インターフェースと共通の 60/min デフォルト制限 | 管理者の操作頻度は本質的に低く、当面区別は不要 |
| PCRE バックトラック制限 | パッケージ内蔵の 1,000,000 バックトラック上限+finally 復旧、極端に複雑な入力では性能リスクが残る | リクエストボディサイズ制限 (10MB) がフォールバック |
## 13. クライアントポータルと決済セキュリティ（M7–M10）
Stripe / PayPal webhook 署名検証（HMAC-SHA256 / verify-webhook-signature）；決済キーは `Encryptable` で暗号化し `logistics_system_config` に保存；USDT TRC20 は Tronscan で自動検証、BEP20 / ERC20 は手動；X-API-Key はクライアント自設（16 文字以上）で sha256 として保存。ゲートウェイ攻撃検知（M11）：`ecat-security` SecurityBodyLayer をゲートウェイに統合、攻撃ペイロードはゲートウェイ層で遮断。 **13.5 CDN セキュリティ（M12）**：Cloudflare 無料プランで全サイト HTTPS + 二層 WAF（エッジ管理ルール + ゲートウェイのアプリケーション層検知）；Tunnel オリジンでソースをゼロ露出；コールバックは DNS 専用サブドメイン直結で CDN 障害時に注文を失わない；レート制限は X-API-Key 単位で CDN エッジ IP の影響を受けない；認証エンドポイントは常に no-store でユーザー間キャッシュ混入を防止。 **13.6 CDN プロバイダーキー管理（M13）**：CDN プロバイダーのプロフィール（access_key / access_secret / ドメイン一覧）を `logistics_cdn_provider` テーブルに保存、機密フィールドは `Encryptable` で暗号化、`/admin/cdn/provider` の CRUD で管理。
