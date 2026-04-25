# 認証プラグイン実装サマリ

## この文書は必要か

必要です。ただし、用途は「設計議論の履歴」ではなく、
**現在の実装状態を横断で素早く確認するための運用サマリ**に限定します。

詳細仕様は各プラグインの README / 詳細設計に持たせ、
この文書は次の 3 点だけを扱います。

- 現在の責務分担
- 直近の変更点
- 残タスク

## 対象

- BcAuthCommon
- BcAuthGuard
- BcAuthPasskey
- BcAuthSocial

## 現在の責務分担

### BcAuthCommon

- 認証共通ログ (`bc_auth_login_logs`) の保存
- ログイン / ログアウト時の最近の動き記録
- 認証ログ履歴画面（一覧 / 詳細 / 削除 / 一括削除 / 検索）

### BcAuthGuard

- Admin ログイン失敗回数の集計とロック制御
- IP 拒否（単体IP / CIDR）
- ロック中一覧（検索）と手動解除
- Guard 固有イベントの監査ログ記録
  - `lockout_started`
  - `lockout_denied`
  - `blocked_ip_denied`
- Guard 固有イベントの最近の動き記録
  - ロック開始
  - ログイン制限解除
  - IP拒否
  - ロック中拒否

### BcAuthPasskey / BcAuthSocial

- 各プロトコル固有の認証処理
- 成功 / 失敗 / キャンセル等の共通監査ログは BcAuthCommon 経由で記録

## 直近の確定事項（2026-04-25）

1. `bc_auth_login_logs` を検索向けに拡張
- `username`
- `referer`
- `request_path`
を正規カラムとして保持

2. 認証ログ履歴の検索をカラムベースに整理
- 状態
- ログインID
- IPアドレス
- 認証種別
- リファラー
- 期間（開始 / 終了）

3. ログイン / ログアウトの最近の動きは BcAuthCommon 側で処理

4. BcAuthGuard 側の最近の動きは Guard 固有イベントのみに限定

5. BcAuthGuard にロック中一覧と手動解除を追加
- 一覧検索: 状態 / プレフィックス / ログインID / IPアドレス
- 手動解除時は `released_reason=manual_release` を保存

6. IP拒否は単体IPに加えてCIDR指定にも対応

## 実装確認ポイント

1. IP拒否対象からのログイン試行で、ログインできないこと
2. CIDR指定の範囲内アドレスが拒否されること
2. ロック中は正しい資格情報でもログインできないこと
3. ロック解除後に再ログインできること
4. 認証ログ履歴検索（ログインID / リファラー）が期待通り動作すること
5. ロック中一覧の検索と手動解除が期待通り動作すること

## 残タスク（運用上）

1. E2E 受け入れ確認の最終実施
- IP拒否
- CIDR拒否
- ロック中拒否
- ロック解除後ログイン
- 履歴検索

2. DB 初期化前提の反映手順の明文化
- 初期マイグレーションを書き換えているため、既存 DB への適用手順を運用手順として残す

## 参照先

- BcAuthCommon 概要: ../README.md
- BcAuthCommon 共通責務: auth-common-architecture.md
- BcAuthCommon サービス仕様: auth-login-redirect-service-spec.md
- BcAuthGuard 概要: ../../BcAuthGuard/README.md
- BcAuthPasskey 詳細: ../../BcAuthPasskey/docs/passkey-auth-design.md
- BcAuthSocial 詳細: ../../BcAuthSocial/docs/social-auth-design.md
