# BcAuthCommon plugin for baserCMS

BcAuthCommon は、baserCMS 5 向けの複数認証プラグインで共有する共通処理を担うプラグインです。

現在は `BcPasskeyAuth` と `BcSocialAuth` の共通ログイン完了処理とリダイレクト正規化を中心に実装しています。

## 実装済み

- `AuthLoginService`: 認証済み `user_id` から baserCMS ログイン完了処理を実行
- `AuthRedirectService`: redirect の安全性確認と prefix ごとの既定遷移先決定
- `AuthLoginResult`: completed / two_factor_required を表す結果 DTO

## 対象

- ログイン成功後の baserCMS ログイン確立
- リダイレクト判定
- ログイン画面の認証入口管理
- 監査ログ

## 方針

- 先に責務境界を定義し、重複が顕在化した箇所から実コード化する
- 各認証方式のプロトコル詳細までは共通化しない
- 初期のログイン画面組み込みは template override を許容する

## ドキュメント

- 全体整理: docs/auth-plugin-spec-summary.md
- 共通責務: docs/auth-common-architecture.md

## 残タスク

- 認証入口定義を扱う `AuthEntryService` の要否判断
- 認証試行の共通監査ログ出力
- 連携解除や監査画面などの共通責務切り出し判断
