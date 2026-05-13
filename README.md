# BcAuthCommon plugin for baserCMS

BcAuthCommon は、baserCMS 5 の認証系プラグインで共通利用する機能を集約したプラグインです。

主に次の責務を担います。

- ログイン完了処理の共通化
- リダイレクト先の安全な正規化
- 監査ログの記録
- ログイン導線（認証入口）の管理
- ログイン / ログアウト時の最近の動き（Dblog）記録

現在は BcAuthPasskey と BcAuthSocial から利用しています。今後追加する認証プラグインでも、同じ共通基盤として利用する想定です。

## 対象と目的

- 対象: baserCMS 5 系の認証拡張プラグイン
- 目的: 認証方式ごとの差分を小さくし、監査・運用・保守を統一する

## できること

- 認証済み user_id から baserCMS ログイン状態を確立
- 二段階認証の要否判定と遷移先制御
- 外部 URL を排除した安全な redirect 決定
- ログイン成功 / 失敗 / ログアウト等の監査ログ記録
- ログイン画面の認証ボタン（入口）表示順制御

## 詳細ドキュメント

- 全体整理: [docs/auth-plugin-spec-summary.md](docs/auth-plugin-spec-summary.md)
- 共通責務と構成: [docs/auth-common-architecture.md](docs/auth-common-architecture.md)
- サービス I/F 仕様: [docs/auth-login-redirect-service-spec.md](docs/auth-login-redirect-service-spec.md)

## よく参照する実装ファイル（入口）

- src/Service/AuthLoginService.php
- src/Service/AuthRedirectService.php
- src/Service/AuthLoginLogService.php
- src/Event/BcAuthCommonControllerEventListener.php
- src/Service/AuthEntryService.php
- config/Migrations/20260415000001_CreateBcAuthLoginLogs.php

## 監査ログについて

監査ログの event 種別、カラム定義、検索条件、運用方針は次を参照してください。

- [docs/auth-common-architecture.md](docs/auth-common-architecture.md)
- [docs/auth-plugin-spec-summary.md](docs/auth-plugin-spec-summary.md)

## ログインフローについて

ログイン完了から二段階認証分岐、リダイレクト確定までの詳細シーケンスは次を参照してください。

- [docs/auth-login-redirect-service-spec.md](docs/auth-login-redirect-service-spec.md)

## 開発メモ

- 認証プラグイン側は認証方式固有の検証に集中し、共通処理は BcAuthCommon に寄せる
- auth_source は password / passkey / social:provider のように識別可能な値を使う

## ライセンス

MIT License.

詳細は [LICENSE.md](LICENSE.md) を参照してください。
