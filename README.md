# BcAuthCommon plugin for baserCMS

BcAuthCommon は、baserCMS 5 向けの複数認証プラグインで共有する共通処理を担うプラグインです。

現状は、`BcAuthPasskey` と `BcAuthSocial` の共通ログイン完了処理・リダイレクト正規化・監査ログ・認証入口管理を実装しています。

また、ログイン / ログアウト時の「最近の動き（Dblog）」記録は BcAuthCommon 側のイベントリスナーで処理します。

今後もBcAuthCommonを使ったログイン周りの別のプラグインを別途作成予定です。

## 実装済み機能

| ファイル | 概要 |
|---|---|
| `src/BcAuthCommonPlugin.php` | プラグインクラス |
| `src/Model/Entity/BcAuthLoginLog.php` | 監査ログ エンティティ |
| `src/Model/Table/BcAuthLoginLogsTable.php` | 監査ログ テーブル（`bc_auth_login_logs`） |
| `src/Service/AuthLoginService.php` | 認証済み `user_id` から baserCMS ログイン完了処理を実行。二段階認証要否を判定し `completed` / `two_factor_required` を返す |
| `src/Service/AuthLoginServiceInterface.php` | インターフェース |
| `src/Service/AuthLoginResult.php` | `status` / `redirect_url` / `request` / `response` を持つ結果 DTO |
| `src/Service/AuthRedirectService.php` | redirect の安全性確認（外部 URL 排除）と prefix ごとの既定遷移先決定 |
| `src/Service/AuthRedirectServiceInterface.php` | インターフェース |
| `src/Service/AuthLoginLogService.php` | ログイン成功・失敗・キャンセルの監査ログ書き込み（`bc_auth_login_logs`） |
| `src/Event/BcAuthCommonControllerEventListener.php` | ログイン / ログアウトの監査ログと最近の動き記録 |
| `src/Service/AuthEntryService.php` | 複数認証プラグイン同時有効時のログインボタン順序制御 |
| `config/Migrations/20260415000001_CreateBcAuthLoginLogs.php` | マイグレーション |

## DB テーブル

| テーブル | 用途 |
|---|---|
| `bc_auth_login_logs` | 認証成功・失敗・キャンセルの監査ログ |

主なカラム:

- `user_id`
- `username`
- `prefix`
- `auth_source`
- `event`
- `ip_address`
- `user_agent`
- `referer`
- `request_path`
- `detail`（補足 JSON）
- `created`

管理画面の検索条件:

- 状態
- ログインID
- IPアドレス
- 認証種別
- リファラー
- 期間（開始 / 終了）

## ドキュメント

- 全体整理: [docs/auth-plugin-spec-summary.md](docs/auth-plugin-spec-summary.md)
- 共通責務: [docs/auth-common-architecture.md](docs/auth-common-architecture.md)
- サービス I/F 仕様: [docs/auth-login-redirect-service-spec.md](docs/auth-login-redirect-service-spec.md)

## ライセンス

MIT License. 詳細は [LICENSE.txt](LICENSE.txt) を参照してください。
