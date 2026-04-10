# AuthLoginService / AuthRedirectService インターフェース仕様

## 概要

この文書は、BcAuthCommon が担う 2 つの共通サービスのインターフェース仕様を定義します。

- `AuthLoginService` — 認証成功後のログイン確立責務
- `AuthRedirectService` — ログイン後のリダイレクト先確定責務

現時点では BcAuthCommon は概念基盤であり、このインターフェースは実コード化タイミングを見据えた事前設計です。  
BcPasskeyAuth と BcSocialAuth は、それぞれこのインターフェースへ準拠する形で実装します。

---

## AuthLoginService

### 責務

- 認証成功後に baserCMS ユーザーのログイン状態を確立する
- 二段階認証が有効な prefix では、login_code 画面への受け渡しを行う
- `Users.afterLogin` イベントを dispatch する
- `AuthRedirectService` を通じてリダイレクト先を確定する

### メソッド仕様

#### `login(array $params, ServerRequest $request, Response $response): AuthLoginResult`

**引数**

| パラメータ | 型 | 必須 | 説明 |
| --- | --- | --- | --- |
| `user_id` | int | 必須 | 特定された baserCMS ユーザーの ID |
| `prefix` | string | 必須 | `Admin` / `Front` / `Api/Admin` |
| `auth_source` | string | 必須 | `passkey`, `social:google`, `social:x` など認証方式の識別子 |
| `redirect` | string\|null | 任意 | ログイン前の遷移先。未指定なら prefix 既定値を使う |
| `saved` | bool | 任意 | 自動ログイン保存を有効にするか。既定は false |

**戻り値: `AuthLoginResult`**

| フィールド | 型 | 説明 |
| --- | --- | --- |
| `status` | string | `completed` または `two_factor_required` |
| `redirect_url` | string | 遷移先 URL |
| `request` | ServerRequest | セッション書き込み後のリクエスト |
| `response` | Response | cookie 付与後のレスポンス |

**status = `completed` の場合の処理内容**

1. `UsersService::login($request, $response, $user_id)` を呼び出してセッションを確立する
2. `Users.afterLogin` イベントを dispatch する（`user`, `loginRedirect` を渡す）
3. HTTPS かつ `saved=true` の場合は自動ログイン保存 cookie を付与する
4. `AuthRedirectService::resolve()` で確定した URL を `redirect_url` に設定する

**status = `two_factor_required` の場合の処理内容**

Admin または Api/Admin で `use_two_factor_authentication` 設定が有効な場合に該当します。

1. セッションへ次の情報を書き込む（キー: `TwoFactorAuth.Admin`）

| セッションキー | 値 |
| --- | --- |
| `user_id` | ユーザー ID |
| `email` | ユーザーのメールアドレス |
| `saved` | 自動ログイン保存フラグ |
| `date` | 現在日時（`Y-m-d H:i:s`） |
| `redirect` | 正規化済みリダイレクト先 |
| `auth_source` | 認証方式の識別子 |

2. 二段階認証コードをメール送信する
3. `redirect_url` を `/baser/admin/baser-core/users/login_code` に設定する  
   元の redirect がある場合は `?redirect=` クエリを付与する

### 注意点

- `Api/Admin` prefix では、二段階認証は HTTP レスポンスコード 200 + エラーメッセージで返す既存仕様に従う
- `completed` 以外で `UsersService::login()` を呼ばないことを厳守する
- 二段階認証コードの送信失敗は例外とせず、ログへ記録して `two_factor_required` のまま進む

---

## AuthRedirectService

### 責務

- redirect 値を検証し、安全な遷移先 URL を確定して返す
- 外部 URL やインジェクションを防ぐ
- prefix ごとの既定リダイレクト先へのフォールバックを持つ

### メソッド仕様

#### `resolve(string|null $redirect, string $prefix): string`

**引数**

| パラメータ | 型 | 必須 | 説明 |
| --- | --- | --- | --- |
| `redirect` | string\|null | 任意 | ログイン画面クエリや認証開始時のセッションから取得した遷移先 |
| `prefix` | string | 必須 | `Admin` / `Front` など |

**戻り値**

安全と判断した遷移先 URL 文字列を返します。

**判定ルール（上から順に評価）**

| 条件 | 結果 |
| --- | --- |
| `$redirect` が null または空 | prefix の既定 URL を返す |
| `//` から始まる（protocol-relative URL） | prefix の既定 URL を返す |
| `http://` または `https://` から始まり、かつ同一ホストでない | prefix の既定 URL を返す |
| `javascript:` などの危険スキームを含む | prefix の既定 URL を返す |
| 上記以外（相対 URL、または同一ホスト絶対 URL） | `$redirect` をそのまま返す |

**prefix ごとの既定 URL**

| prefix | 既定値の参照元 |
| --- | --- |
| Admin | `Configure::read('BcPrefixAuth.Admin.loginRedirect')` |
| Front | `Configure::read('BcPrefixAuth.Front.loginRedirect')` |
| その他 | `'/'` |

### redirect の受け渡しパターン

redirect 値が AuthRedirectService::resolve() に渡るまでの経路は 3 種類あります。

1. **ログイン画面クエリ**  
   `/baser/admin/baser-core/users/login?redirect=%2Fadmin%2Fdashboard` などから取得

2. **認証開始時のセッション退避**  
   OAuth の認可開始前に、元いたページの URL をセッションへ保存しておき、callback 後に取り出す

3. **二段階認証画面への引き渡し**  
   `TwoFactorAuth.Admin.redirect` セッションキーに保存した値を login_code 完了後に読み取る

どの経路から来た値も、`resolve()` を通してから使います。

---

## 利用例（疑似コード）

### BcPasskeyAuth の assertion 検証後

```php
// PasskeysController（Admin）
$userId = $this->PasskeyAuthService->verifyAssertion($assertionResponse, $challenge);

$loginResult = $this->AuthLoginService->login([
    'user_id'     => $userId,
    'prefix'      => 'Admin',
    'auth_source' => 'passkey',
    'redirect'    => $this->request->getSession()->read('BcPasskeyAuth.loginChallenge.Admin.redirect'),
    'saved'       => false,
], $this->request, $this->response);

if ($loginResult->status === 'completed') {
    return $this->redirect($loginResult->redirect_url);
}
// two_factor_required → AuthLoginService 内部で login_code へリダイレクト先が設定済み
return $this->redirect($loginResult->redirect_url);
```

### BcSocialAuth の callback 後

```php
// AuthController（Admin）
$profile = $this->SocialAuthService->handleCallback($provider, $this->request);
$userId  = $this->ProviderLinkService->resolveUser($profile);

$loginResult = $this->AuthLoginService->login([
    'user_id'     => $userId,
    'prefix'      => 'Admin',
    'auth_source' => 'social:' . $provider,
    'redirect'    => $this->SocialAuthService->getStoredRedirect($provider, 'Admin'),
    'saved'       => false,
], $this->request, $this->response);

return $this->redirect($loginResult->redirect_url);
```

---

## 関連文書

- 共通責務の全体方針: auth-common-architecture.md
- BcPasskeyAuth の詳細設計: ../../BcPasskeyAuth/docs/passkey-auth-design.md
- BcSocialAuth の詳細設計: ../../BcSocialAuth/docs/social-auth-design.md
