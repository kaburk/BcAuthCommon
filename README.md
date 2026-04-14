# BcAuthCommon plugin for baserCMS

BcAuthCommon は、baserCMS 5 向けの複数認証プラグインで共有する共通処理を担うプラグインです。

現在は `BcPasskeyAuth` と `BcSocialAuth` の共通ログイン完了処理とリダイレクト正規化を中心に実装しています。

## 実装済み

| ファイル | 概要 |
|---|---|
| `src/Service/AuthLoginService.php` | 認証済み `user_id` から baserCMS ログイン完了処理を実行。二段階認証要否を判定し `completed` / `two_factor_required` を返す |
| `src/Service/AuthLoginServiceInterface.php` | インターフェース |
| `src/Service/AuthRedirectService.php` | redirect の安全性確認（外部 URL 排除）と prefix ごとの既定遷移先決定 |
| `src/Service/AuthRedirectServiceInterface.php` | インターフェース |
| `src/Service/AuthLoginResult.php` | `status` / `redirect_url` / `request` / `response` を持つ結果 DTO |

## 対象

- ログイン成功後の baserCMS ログイン確立
- 二段階認証との整合（`two_factor_required` への受け渡し）
- リダイレクト判定（外部 URL 排除・prefix ごとのフォールバック）
- ログイン画面の認証入口管理（将来）
- 監査ログ（将来）

## 方針

- 先に責務境界を定義し、重複が顕在化した箇所から実コード化する
- 各認証方式のプロトコル詳細までは共通化しない
- 初期のログイン画面組み込みは template override を許容する

## ドキュメント

- 全体整理: [docs/auth-plugin-spec-summary.md](docs/auth-plugin-spec-summary.md)
- 共通責務: [docs/auth-common-architecture.md](docs/auth-common-architecture.md)
- サービス I/F 仕様: [docs/auth-login-redirect-service-spec.md](docs/auth-login-redirect-service-spec.md)
- マイグレーション仕様: [docs/migration-spec.md](docs/migration-spec.md)

## 残タスク

- `AuthEntryService` の要否判断（複数プラグイン同時有効時のログインボタン順序制御）
- 認証試行の共通監査ログ出力（ログイン成功・失敗・キャンセルの記録形式統一）
- 連携解除や監査画面などの共通責務切り出し判断
