# baserCMS 認証プラグイン仕様サマリ

## 概要

この文書は、baserCMS 5 向けに検討している次の 3 つの認証関連プラグインの仕様を横断的に整理したサマリです。

- BcAuthCommon
- BcAuthPasskey
- BcAuthSocial

個別設計書はそれぞれ存在しますが、全体像、責務分担、フェーズ 1 の決定事項、実装順の判断材料を 1 つの文書で確認できるようにすることを目的とします。

## 対象プラグインの役割

### BcAuthCommon

認証プロトコルそのものではなく、複数認証方式で共通化しうるアプリケーション責務を扱う共通基盤プラグインです。

主な対象は次の通りです。

- ログイン成功後の baserCMS ログイン確立
- リダイレクト正規化
- ログイン画面の認証入口管理
- 監査ログ
- アカウントひも付けポリシーの共通整理

### BcAuthPasskey

WebAuthn / Passkey を利用したローカル認証プラグインです。

初期段階では Admin ログインを優先し、既存ユーザーがログイン後にパスキーを登録し、登録済みパスキーでログインできる構成を目指します。

### BcAuthSocial

Google や X などの OAuth 2.0 / OIDC 系外部認証を追加するプラグインです。

Google と X を本体同梱としつつ、追加プロバイダは ProviderAdapterRegistry によるアドオン登録方式で拡張できる前提です。

## 全体アーキテクチャ

認証方式は異なっても、アプリケーション側では次の流れを共有します。

1. 認証方式ごとのプロトコルを実行する
2. baserCMS ユーザーを特定する
3. 二段階認証要否を判定する
4. セッション確立または login_code 画面への受け渡しを行う
5. 安全な redirect を確定する
6. 認証入口表示や監査ログ形式をそろえる

このうち 1 は各プラグイン固有であり、2 以降の一部が BcAuthCommon の共通化候補です。

## 共通で固定した仕様

### ログイン完了の扱い

認証成功 と セッション確立完了 は同義として扱いません。

理由は、baserCMS コアの Admin 二段階認証が Authentication.afterIdentify ベースで動作しており、追加認証プラグインが UsersService::login() を直接呼ぶだけでは既存ポリシーを迂回する可能性があるためです。

そのため、共通ログイン層が返す結果は次の 2 種別を基本とします。

- completed
- two_factor_required

### 二段階認証との整合

フェーズ 1 では、BcAuthPasskey と BcAuthSocial のどちらも Admin 二段階認証を免除しません。

つまり、外部認証またはパスキーで本人確認が成功しても、Admin で二段階認証が有効なら既存の login_code 画面へ処理を引き渡します。

### リダイレクトルール

redirect は自由入力値をそのまま信用せず、次のルールで正規化します。

1. 相対 URL または同一ホスト内 URL のみ許可する
2. 外部 URL は拒否する
3. `//` から始まる protocol-relative URL は拒否する
4. 不正または空の場合は prefix ごとの loginRedirect を使う

### ログイン画面の認証入口

初期段階では template override を許容します。

ただし、描画は自由 HTML 差し込みではなく、次のような定義データに寄せる前提で整理します。

- key
- type
- provider
- label
- icon
- url
- sort
- prefixes
- enabled
- requires_javascript

## フェーズ 1 の決定事項

### BcAuthPasskey

- 対応 prefix は Admin を最優先とする
- ログイン対象は既存ユーザーに登録済みの credential のみとする
- 新規ユーザー自動作成は行わない
- パスキー登録はログイン済みユーザー本人のみ許可する
- 登録時の user verification は required とする
- Discoverable Credential を優先する
- Admin API の JWT ログインは初期対象外とする

### BcAuthSocial

- 対応 prefix は Admin を最優先とする
- 同梱 provider は Google と X とする
- 新規ユーザー自動作成は行わない
- 同一性判定は provider_user_id を主体とする
- 自動連携は既定で無効とする
- Google は条件付きで 連携候補提示 まで許容する
- X は既存連携または事前連携前提とする
- Admin API ログインは初期対象外とする

### BcAuthCommon

- `AuthLoginService` と `AuthRedirectService` を先行実装する
- 認証プロトコル詳細までは共通化しない
- 次の共通化候補は認証入口、監査ログ、連携ポリシーとする

## ひも付けポリシー

### パスキー

パスキーは user_id と credential の 1 対多管理を前提とします。

- credential_id は一意
- 登録は本人のみ
- 削除も本人のみ
- 削除後もパスワードログインが残る前提で運用する

### 外部認証

外部認証は provider_user_id を主キー相当として扱います。

- メールアドレスは補助情報
- email_verified=false の自動連携は認めない
- X はメール前提で設計しない
- 既定では確認なし自動連携を行わない

## データモデル方針

### BcAuthPasskey

テーブル案: passkey_credentials

主要項目:

- user_id
- prefix
- user_handle
- credential_id
- public_key
- counter
- transports
- name
- last_used

### BcAuthSocial

テーブル案: auth_provider_links

主要項目:

- user_id
- prefix
- provider
- provider_user_id
- email
- email_verified
- name
- avatar_url
- profile
- last_login

## 実装順の推奨

現時点では次の順で進めるのが妥当です。

1. BcAuthPasskey の最小 Admin ログイン仕様を固める
2. BcAuthSocial の Google Provider を先に具体化する
3. 両者で重複する login 完了処理と redirect 処理を確認する
4. 必要になった時点で BcAuthCommon の責務を追加実装する

## 現在の実装状況（2026-04-14 時点）

### 実装済み

#### BcAuthCommon

| ファイル | 概要 |
|---|---|
| `src/BcAuthCommonPlugin.php` | プラグインクラス |
| `src/Service/AuthLoginService.php` | ログイン完了処理・二段階認証受け渡し |
| `src/Service/AuthLoginServiceInterface.php` | インターフェース |
| `src/Service/AuthRedirectService.php` | redirect 正規化・外部 URL 排除 |
| `src/Service/AuthRedirectServiceInterface.php` | インターフェース |
| `src/Service/AuthLoginResult.php` | completed / two_factor_required を返す DTO |

#### BcAuthPasskey

| ファイル | 概要 |
|---|---|
| `config/Migrations/20260409000001_CreatePasskeyCredentials.php` | マイグレーション |
| `config/routes.php` | Admin / Front ルート定義 |
| `config/setting.php` | 管理ナビメニュー登録 |
| `config.php` | adminLink / installMessage 設定 |
| `src/BcAuthPasskeyPlugin.php` | プラグインクラス |
| `src/Model/Entity/PasskeyCredential.php` | エンティティ |
| `src/Model/Table/PasskeyCredentialsTable.php` | テーブルクラス |
| `src/Service/PasskeyAuthServiceInterface.php` | サービスインターフェース |
| `src/Service/PasskeyAuthService.php` | WebAuthn challenge / assertion / attestation |
| `src/Controller/Admin/PasskeysController.php` | Admin: login_challenge / login / register_challenge / register / index / delete |
| `src/Controller/PasskeysController.php` | Front: login_challenge / login |
| `templates/Admin/Passkeys/index.php` | パスキー管理画面 |
| `templates/plugin/BcAdminThird/Admin/Users/login.php` | Admin ログイン画面 override（パスキーボタン＋ソーシャルボタン統合） |
| `templates/plugin/BcFront/Users/login.php` | Front ログイン画面 override（パスキーボタン） |
| `webroot/js/passkey-auth.js` | Base64URL 変換・login / register フロー |

> **注意**: Admin ログイン画面のソーシャルログインボタンは現在 BcAuthPasskey の template override 内に統合されており、`class_exists(ProviderAdapterRegistry::class)` で条件分岐している。BcAuthSocial が単体でインストールされても Admin ログイン画面にソーシャルボタンは表示されない（後述の残タスク参照）。

#### BcAuthSocial

| ファイル | 概要 |
|---|---|
| `config/Migrations/20260409000001_CreateAuthProviderLinks.php` | マイグレーション |
| `config/routes.php` | Admin ルート定義 |
| `config/setting.php` | env ベースの provider 設定 |
| `config.php` | adminLink / installMessage 設定 |
| `src/BcAuthSocialPlugin.php` | プラグインクラス |
| `src/Adapter/ProviderAdapterInterface.php` | アダプタインターフェース |
| `src/Adapter/ProviderAdapterRegistry.php` | シングルトン registry |
| `src/Adapter/ProviderUserProfile.php` | プロフィール DTO |
| `src/Adapter/GoogleProviderAdapter.php` | Google OIDC アダプタ |
| `src/Adapter/XProviderAdapter.php` | X（OAuth 2.0 / PKCE）アダプタ |
| `src/Model/Entity/AuthProviderLink.php` | エンティティ |
| `src/Model/Table/AuthProviderLinksTable.php` | テーブルクラス |
| `src/Model/Entity/SocialAuthConfig.php` | バーチャルエンティティ（env 読み書き） |
| `src/Model/Table/SocialAuthConfigsTable.php` | バーチャルテーブル |
| `src/Service/SocialAuthService.php` | 認可 URL 生成・callback 処理・ユーザーひも付け |
| `src/Service/SocialAuthConfigsService.php` | provider 設定の読み書き |
| `src/Service/SocialAuthConfigsServiceInterface.php` | インターフェース |
| `src/ServiceProvider/BcAuthSocialServiceProvider.php` | DI 登録 |
| `src/Controller/Admin/AuthController.php` | login / callback / link_candidate / confirm_link / cancel_link |
| `src/Controller/Admin/SocialAuthAccountsController.php` | 連携済みアカウント管理 |
| `src/Controller/Admin/SocialAuthConfigsController.php` | provider 設定画面 |
| `templates/Admin/Auth/link_candidate.php` | 連携候補確認画面 |
| `templates/Admin/SocialAuthAccounts/index.php` | 連携済みアカウント一覧 |
| `templates/Admin/SocialAuthConfigs/index.php` | provider 設定画面 |
| `templates/element/social_login_buttons.php` | ログイン画面埋め込み用ソーシャルボタン element |

## 導入・設定フロー見直し

### 3. マイグレーション

- 新規導入時の migration は、手動実行を前提にしない
- baserCMS の `BcPlugin::install()` は、`config/Migrations` があれば install 時に自動で migration を実行する
- したがって、認証プラグインの初回導入フローは「有効化」ではなく「インストール」を正規ルートとして案内する
- 再有効化（attach）は DB 初期化を伴わないため、migration の自動実行対象ではない
- 今後の改善対象は「attach 時に未初期化状態を検知して install へ誘導すること」であり、「毎回 attach 時に migration を流すこと」ではない

### 4. 設定

- BcAuthPasskey は外部プロバイダ設定を必要としないため、原則として env 入力不要とする
- BcAuthSocial は provider ごとに `enabled` / `clientId` / `clientSecret` / `redirectUri` を必要とする
- 既定値や空の env キー追加など、アプリ側で安全に自動補完できる項目は GUI から `.env` に書き込めるようにする
- `clientId` / `clientSecret` / `redirectUri` のような実値は、`.env` が書き込み可能な場合は管理画面から保存し、書き込み不可の場合は手作業用の案内を出す
- 設定画面は `SiteConfigsService::putEnv()` を利用し、`.env` への保存可否を `isWritableEnv()` で判定する
- callback URL は画面内で自動表示し、プロバイダ側コンソールへ転記する手順を案内する

### 実装方針

- 初回導入導線では、install 完了後に「次に必要な設定」と「確認用 URL」を表示する
- BcAuthSocial では provider 設定画面を優先実装し、env 直編集を必須にしない
- `.env` に保存できない環境では、GUI 上で必要キーと推奨値を提示し、手動設定へフォールバックする
- secret を DB に二重保存する構成は初期段階では採用しない

### 残タスク

| # | 内容 | 対象 | 優先度 | 状態 |
|---|---|---|---|---|
| 1 | **BcAuthSocial 単体動作対応**: BcAuthPasskey なしで Admin ログイン画面にソーシャルボタンを表示するため、BcAuthSocial 自身の `templates/plugin/BcAdminThird/Admin/Users/login.php` override を追加する | BcAuthSocial | 高 | ✅ 完了 |
| 2 | **BcAuthSocial: Front prefix 用 AuthController 作成**（login / callback を Front 向けに実装） | BcAuthSocial | 高 | ✅ 完了 |
| 3 | **BcAuthSocial: Front ログイン画面へのソーシャルボタン統合**（template override または element 差し込み） | BcAuthSocial | 高 | ✅ 完了 |
| 4 | **Docker e2e 動作確認**（Google/X 302確認・X アカウント連携確認） | 全体 | 高 | ✅ 完了 |
| 5 | **X provider 実運用検証**（Basic Auth ヘッダー対応、連携成功確認） | BcAuthSocial | 中 | ✅ 完了 |
| 6 | **BcAuthPasskey install 後の登録導線補強** | BcAuthPasskey | 中 | ✅ 完了 |
| 7 | **Front パスキーボタンの URL 修正**（named route 対応・`prefix: false` 修正） | BcAuthPasskey | 中 | ✅ 完了 |
| 8 | **Admin Auth ルート URL 修正**（`/baser/admin/bc-auth-social/...` パターンに統一） | BcAuthSocial | 高 | ✅ 完了 |
| 9 | **Passkey 依存ライブラリ完備**（`phpdocumentor/reflection-docblock` インストール → loginChallenge 正常動作確認） | BcAuthPasskey | 高 | ✅ 完了 |
| 10 | **連携解除→再連携 UNIQUE 制約エラー修正**（`linkUser` の upsert 対応） | BcAuthSocial | 高 | ✅ 完了 |
| 11 | **Front 側デザイン調整**（実運用テーマに合わせた表示調整） | BcAuthPasskey | 低 | 未着手 |
| 12 | **監査ログ共通化**（ログイン成功・失敗・キャンセルの記録形式統一） | BcAuthCommon | 低 | ✅ 完了 |
| 13 | **認証入口定義の共通化**（複数プラグイン同時有効時のボタン順序制御） | BcAuthCommon | 低 | ✅ 完了 |

### 次の実装順

残る未着手タスクは Front 側デザイン調整のみ。実運用テーマができた段階で対応する。

1. **Front 側デザイン調整**（実運用テーマ確定後）

## 今後の拡張予定

### BcAuthSocial への追加プロバイダ

BcAuthSocial は `ProviderAdapterRegistry` による拡張基盤を持つため、OAuth 2.0 / OIDC を使うプロバイダは同プラグイン内に追加できます。

| プロバイダ | プロトコル | 優先度 |
|---|---|---|
| Microsoft アカウント（個人） | OAuth 2.0 / OIDC（`tenant_id=consumers`） | 高 |
| Microsoft Azure AD / Entra ID（法人） | OAuth 2.0 / OIDC（`tenant_id` 指定） | 中 |
| GitHub | OAuth 2.0 | 低 |
| LINE | OAuth 2.0 / OIDC | 低 |

**Microsoft アカウントの追加方針**

- Google / X と同じ `ProviderAdapterInterface` を実装する `MicrosoftAdapter` を追加する
- 個人アカウントは `tenant_id=consumers`、Azure AD（法人）は UUID テナントまたは `common` を設定項目に追加する
- `.env` キーは `SOCIAL_AUTH_MICROSOFT_ENABLED` / `_CLIENT_ID` / `_CLIENT_SECRET` / `_TENANT_ID` / `_REDIRECT_URI` の 5 系統とする
- `setting.php` の `providerLabels` / `envKeys` / `callbackUrls` に `microsoft` を追加するだけで設定画面に自動反映される
- ライブラリは `thephpleague/oauth2-microsoft`（`league` 系で統一）を想定する

### 別プラグインとして分離すべき認証方式

プロトコルが OAuth 2.0 / OIDC と根本的に異なる方式は、専用プラグインとして独立させます。

#### BcLdapAuth（Active Directory / LDAP）

- **プロトコル**: LDAP bind（OAuth コールバックフローなし）
- **対象**: 社内 Active Directory・OpenLDAP などを使った組織ログイン
- **設定項目**: OAuth 系とは全く異なる（ホスト・ポート・Bind DN・Base DN・StartTLS・ユーザー属性マッピング・グループフィルタ など）
- **実装方針**: `BcAuthCommon` の `AuthProviderInterface` を実装し、LDAP bind 成功後に `AuthLoginService` へ接続する
- **ライブラリ候補**: PHP 組み込みの `ldap_*` 関数群、または `vresh/ldap-record`
- **baserCMS との統合**: ユーザー自動作成は初期対象外。LDAP の DN / UID と baserCMS の `users.id` を `auth_provider_links` で紐付ける方式を想定（BcAuthCommon のスキーマ流用または専用テーブル）

#### BcSamlAuth（SAML 2.0 / SSO）

- **プロトコル**: SAML 2.0（IdP との XML メタデータ交換）
- **対象**: Okta・Azure AD（SAML モード）・Shibboleth などを使ったエンタープライズ SSO
- **実装方針**: `onelogin/php-saml` を利用して IndP 設定・SP 設定・Assertion マッピングを管理画面から設定できる形を目指す
- **優先度**: 低（要望があれば検討）

#### BcAuthSocial

| 優先度 | タスク | 概要 |
| --- | --- | --- |
| 中 | Front プレフィックス対応 | フロント側ログインへのソーシャルログイン導線追加 |
| 低 | X の実運用検証 | X PKCE フローの end-to-end 確認 |

#### BcAuthPasskey

| 優先度 | タスク | 概要 |
| --- | --- | --- |
| 低 | Front 側デザイン調整 | 実運用テーマに合わせたログイン画面の表示調整 |

#### BcAuthCommon（共通化判断）

| 優先度 | タスク | 概要 |
| --- | --- | --- |
| 中 | install / setup ガイドの共通化 | 認証プラグイン共通の導入チェックリストと確認表示を共通化する |

## 実コード化の判断基準

BcAuthCommon を独立実装へ進める基準は次の通りです。

- redirect 正規化が 2 プラグイン以上で重複した
- 二段階認証への受け渡しが 2 プラグイン以上で重複した
- 認証入口配列の組み立てと描画が重複した
- 監査ログ形式を共有したくなった

## ドキュメント対応表

| 文書 | パス | 内容 |
| --- | --- | --- |
| 本サマリ | auth-plugin-spec-summary.md | 全体像・フェーズ1決定事項・実装順 |
| 共通責務詳細 | auth-common-architecture.md | サービス責務・ログイン完了契約・リダイレクトルール・監査ログ |
| サービス I/F 仕様 | auth-login-redirect-service-spec.md | AuthLoginService / AuthRedirectService のメソッド仕様・利用例 |
| マイグレーション仕様 | migration-spec.md | passkey_credentials / auth_provider_links のカラム・インデックス・実装コード |
| BcAuthPasskey 詳細設計 | ../../BcAuthPasskey/docs/passkey-auth-design.md | 認証フロー・登録フロー・セキュリティ方針 |
| BcAuthSocial 詳細設計 | ../../BcAuthSocial/docs/social-auth-design.md | ProviderAdapter 設計・ユーザーひも付け方針 |

## 現時点の結論

現段階では、3 プラグインを 1 つの巨大な認証基盤として先に実装するのではなく、各認証方式の固有プロトコルは分離したまま、ログイン完了後の接続処理だけを共通化候補として扱う方針が最も現実的です。

特にフェーズ 1 では、次の方針を固定しています。

- Admin を優先する
- 新規ユーザー自動作成はしない
- Admin 二段階認証を迂回しない
- template override を許容しつつ、認証入口定義は共通フォーマットに寄せる
