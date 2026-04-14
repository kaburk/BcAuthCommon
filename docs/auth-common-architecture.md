# BcAuthCommon アーキテクチャメモ

## 概要

BcAuthCommon は、baserCMS 5 に複数の認証方式を追加する際の共通責務を扱う共通基盤プラグインです。

現在は `AuthLoginService` と `AuthRedirectService` を実装済みで、まずは BcAuthPasskey と BcAuthSocial のあいだで重複しやすいログイン完了処理を担います。

## 背景

baserCMS 5 で追加したい認証方式は、少なくとも次の 3 系統があります。

- 標準のメールアドレス / パスワード認証
- WebAuthn / Passkey 認証
- OAuth 2.0 / OpenID Connect 系の外部認証

これらはプロトコル自体は異なる一方で、ログイン成功後に必要となるアプリケーション側の処理には共通部分があります。

共通部分を早い段階で整理しておかないと、各プラグインが個別にログイン状態確立、リダイレクト、ボタン差し込み、監査ログなどを実装し、後で競合や重複が発生しやすくなります。

## 目的

- 複数認証プラグインが同時に導入されても破綻しない構成を整理する
- BcAuthPasskey と BcAuthSocial の共通責務を定義する
- 今すぐ共通プラグインを作らなくても、後で抽出しやすい境界を明確にする
- 旧 BcGoogleLogin のような単独プラグインも共通基盤へ寄せやすい形にする

## 非目的

- 初期段階で巨大な認証フレームワークを先に作ること
- baserCMS コアの認証機構を全面的に置き換えること
- 各認証方式のプロトコル差異を吸収しきる抽象化を最初から作ること

## 対象責務

### 1. 認証成功後のログイン確立

どの認証方式でも、最終的には baserCMS ユーザーを特定し、既存の認証状態へ接続する必要があります。

共通責務として扱いたい内容は次の通りです。

- ユーザーエンティティからログイン状態を確立する
- prefix ごとのセッションキーへ適切に接続する
- saved や redirect などの共通パラメータを受け渡す
- ログイン成功メッセージや post-login hook の呼び出しを整理する

### 2. リダイレクト判定

ログイン後は、元の要求ページまたは prefix ごとの既定画面へ戻す必要があります。

認証プラグインごとにバラバラに実装すると、安全性や整合性を崩しやすいため、次を共通化候補とします。

- redirect パラメータの正規化
- 不正な外部 URL の排除
- Admin と Front それぞれの既定リダイレクト先の決定

### 3. ログイン画面への認証入口差し込み

複数プラグインが同時にログイン画面へボタンを追加することを前提に、共通の描画ポイントを整理します。

理想形は次の通りです。

- 認証方式ごとにボタン定義を登録できる
- 表示順序を制御できる
- Admin と Front で同じ考え方を使える
- 各プラグインは自分のボタン定義だけを提供する

ただし現実的な導入順としては、初期段階ではテンプレート override で認証入口エリアを実装し、その内部構造だけを共通化可能な形に寄せる方が安全です。

### 4. 監査ログ

認証方式が増えると、成功や失敗の記録も分散しがちです。

共通化候補は次の通りです。

- 認証方式
- provider 名または credential 種別
- 成功、失敗、キャンセル
- user_id
- prefix
- IP アドレス
- user agent
- 発生日時

### 5. アカウントひも付けポリシー

パスキーでは user_id と credential のひも付け、外部認証では user_id と provider account のひも付けが必要です。

データモデル自体は別でも、ポリシー面で共通化したい項目があります。

- 自動ひも付けの可否
- 手動ひも付けの要否
- 解除時の安全確認
- ログイン中ユーザーによる自己管理範囲

## 共通化しない責務

次の責務は各プラグインに残すべきです。

- WebAuthn の challenge / attestation / assertion 検証
- OAuth / OIDC の state / nonce / token exchange
- provider 固有 UI や設定項目
- パスキー登録やプロバイダ連携のプロトコル詳細

ここを無理に共通化すると、抽象化だけが増えて実装速度が落ちます。

## 想定サービス境界

### AuthLoginService

責務:

- ユーザー特定後のログイン状態確立
- prefix ごとのログイン処理分岐
- post-login redirect 情報の生成

### AuthRedirectService

責務:

- redirect 値の正規化
- 安全な内部 URL のみ許可
- 既定リダイレクト先の決定

### AuthEntryService

責務:

- ログイン画面へ表示する認証入口の登録
- ボタンやリンクの表示順制御
- Admin と Front での描画用データ提供

### AuthAuditLogService

責務:

- 認証試行の成功、失敗、キャンセル記録
- 将来的な監査画面やレポートへの接続

## ログイン完了の共通契約

BcAuthCommon で最も先に固定すべきなのは、認証成功後に 何をもって ログイン完了 とみなすかです。

baserCMS コアでは、パスワードログイン時に Authentication.afterIdentify が発火し、Admin では必要に応じて二段階認証画面へ遷移します。

一方で、パスキー認証や外部認証が UsersService::login() を直接呼ぶだけでは、このイベント経由の二段階認証が動きません。

そのため、共通層では ログイン成功 と セッション確立完了 を分けて扱います。

### AuthLoginService が返す結果

想定する結果種別は次の 2 つです。

- completed: その場でセッション確立まで完了した状態
- two_factor_required: 外部認証自体は成功したが、追加確認が必要な状態

### completed の処理内容

- UsersService::login() により prefix 対応のセッションを確立する
- Users.afterLogin を dispatch する
- 必要に応じて自動ログイン保存用 cookie を付与する
- AuthRedirectService で確定した遷移先へ返す

### two_factor_required の処理内容

Admin または Api/Admin で、サイト設定の二段階認証が有効な場合は、認証成功直後にログインを完了させません。

この場合はコア既存仕様に合わせ、次の情報をセッションへ保存して login_code 画面へ処理を引き渡します。

- user_id
- email
- saved
- date
- redirect
- auth_source

`auth_source` は `password` `passkey` `social:google` `social:x` のような記録用の識別子です。

これにより、パスワードログインと追加認証プラグインで二段階認証ポリシーを統一できます。

## リダイレクト正規化ルール

AuthRedirectService では、単に redirect パラメータを返すのではなく、prefix と安全性を見たうえで遷移先を確定します。

初期仕様として、次のルールを採用します。

1. 相対 URL または同一ホスト内 URL のみ許可する
2. スキーム付き外部 URL は拒否する
3. `//example.com` のような protocol-relative URL は拒否する
4. 値が空または不正な場合は prefix ごとの loginRedirect を使う
5. Admin は `BcPrefixAuth.Admin.loginRedirect`、Front は `BcPrefixAuth.Front.loginRedirect` を既定値とする

redirect の受け渡し元は次を想定します。

- ログイン画面クエリの `redirect`
- 認証開始時にセッションへ退避した値
- 二段階認証画面へ引き渡された値

## 認証入口データの共通フォーマット

AuthEntryService は、各プラグインが自由な HTML を返すのではなく、最低限次の定義データを返す前提で整理します。

| 項目 | 用途 |
| --- | --- |
| key | 入口の一意キー。例: `passkey.login`, `social.google` |
| type | `button` または `link` |
| provider | `passkey`, `google`, `x` など |
| label | 画面表示文言 |
| icon | アイコン識別子または asset path |
| url | 認証開始 URL |
| sort | 表示順 |
| prefixes | 表示対象 prefix 一覧 |
| enabled | 現在の設定で有効か |
| requires_javascript | WebAuthn など JS 必須か |

初期実装では template override を使ってもよいですが、テンプレート内部ではこの配列をもとに描画する形を推奨します。

## 監査ログの最小仕様

監査ログは共通責務として早めに項目だけ固定しておく価値があります。

初期の最小項目は次の通りです。

| 項目 | 用途 |
| --- | --- |
| auth_type | `password`, `passkey`, `social` |
| provider | `google`, `x` など。パスキーは `passkey` |
| event_type | `success`, `failure`, `cancel`, `link`, `unlink` |
| user_id | 特定できた baserCMS ユーザー |
| prefix | `Admin`, `Front`, `Api/Admin` |
| ip | 接続元 IP |
| user_agent | 利用端末識別 |
| error_code | challenge 不一致などの内部分類 |
| occurred | 発生日時 |

最初から管理画面を作る必要はありませんが、記録項目だけ先に統一しておくと、各プラグインが独自ログ形式を持たずに済みます。

## 追加実装の判断基準

BcAuthCommon では `AuthLoginService` と `AuthRedirectService` を実装済みです。次のいずれかが成立した時点で、残る共通責務の追加実装を検討します。

- BcAuthPasskey と BcAuthSocial の両方で redirect 正規化コードが重複した
- 二段階認証への受け渡しコードが重複した
- ログイン画面入口の配列定義と描画コードが重複した
- 監査ログ形式を 2 プラグイン以上で共有する必要が生じた

## UI アーキテクチャ方針

ログイン画面には 認証入口エリア を 1 箇所持たせ、その中に複数プラグインが要素を登録する構成が望ましいです。

表示イメージ:

- パスワードでログイン
- パスキーでログイン
- Google でログイン
- X でログイン
- Apple でログイン

各プラグインは HTML を直接好き勝手に差し込むのではなく、可能であれば次のような定義データを返す構成が望ましいです。

- type
- label
- icon
- url または action
- sort order
- enabled 条件

## 実装方式の推奨

ログイン画面への組み込みは、理想と初期実装で分けて考えるのが妥当です。

### 初期実装の推奨

- Admin と Front のログインテンプレートを override する
- そのテンプレート内で 認証入口エリア を 1 つ定義する
- BcAuthPasskey と BcAuthSocial は、そのエリアに並ぶ要素を描画する

理由:

- 現状の baserCMS テンプレートには認証入口用の明示的フックがない
- 差し込み位置を安定させやすい
- Google と X のような複数ボタンを最初から破綻なく置きやすい

### 将来の推奨

- 認証入口エリアを共通描画ポイントとしてコアまたは共通基盤へ寄せる
- 各認証プラグインはボタン定義だけを登録する

つまり、両方対応は可能ですが、最初から完全に両方式へ対応するのは少し重いです。
初手としては テンプレート override で入って、構造だけ共通入口へ寄せるのが最も現実的です。

## BcGoogleLogin 由来の知見

旧 BcGoogleLogin では、ログイン画面への Google ボタン追加と、メールアドレス一致による既存ユーザーログインが中核でした。

この知見は活かせますが、5 系では次を改善対象とします。

- ボタン追加を共通入口設計に寄せる
- メールアドレス一致だけに依存しない
- callback 後のログイン確立を他方式とそろえる

## 導入順の考え方

現時点の推奨順序は次の通りです。

1. BcAuthPasskey を独立して最小実装する
2. BcAuthSocial を Google 先行、X 後追いで設計、実装する
3. 両者で重複した部分だけを見て BcAuthCommon を実コードへ昇格させる

つまり、最初から BcAuthCommon を完成品として作るのではなく、先に責務だけを固定し、重複が見えた時点でコード共通化するのが現実的です。

## 関連文書

- サービス I/F 仕様（メソッド定義・利用例）: auth-login-redirect-service-spec.md
- マイグレーション仕様（テーブル定義・実装コード）: migration-spec.md
- 横断サマリ: auth-plugin-spec-summary.md

## まとめ

BcAuthCommon は、認証方式ごとの差分を消すための抽象化ではなく、ログイン成功後の接続、画面入口、監査、リダイレクトのようなアプリケーション共通責務だけを切り出すための整理です。

この方針により、BcAuthPasskey と BcAuthSocial、さらに将来の BcGoogleLogin5 相当の実装も、同時利用できる方向で進めやすくなります。
