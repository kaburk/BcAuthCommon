# マイグレーション仕様

## 概要

この文書は、BcPasskeyAuth と BcSocialAuth が必要とするテーブルのマイグレーション仕様を定義します。

- `passkey_credentials` — BcPasskeyAuth が管理する WebAuthn 資格情報テーブル
- `auth_provider_links` — BcSocialAuth が管理する外部プロバイダ連携テーブル

各プラグインの `config/Migrations/` 配下に BcMigration を継承したクラスとして実装します。

---

## passkey_credentials（BcPasskeyAuth）

### テーブル概要

1 ユーザーが複数のパスキーを登録できる 1 対多の構造です。  
Discoverable Credential を前提とするため、user_handle と credential_id の両方を保持します。

### カラム定義

| カラム名 | 型 | NULL | デフォルト | 説明 |
| --- | --- | --- | --- | --- |
| `id` | int (auto) | NO | — | 主キー |
| `user_id` | int | NO | — | users テーブルの ID |
| `prefix` | varchar(50) | NO | `'Admin'` | 利用プレフィックス（Admin / Front） |
| `user_handle` | varchar(191) | NO | — | WebAuthn user handle（Base64URL）。users.id とは別の WebAuthn 用識別子 |
| `credential_id` | text | NO | — | Base64URL エンコードした credential ID（一意） |
| `public_key` | mediumtext | NO | — | CBOR または PEM 形式の公開鍵データ |
| `counter` | int unsigned | NO | `0` | sign counter。認証のたびに更新され、リプレイ攻撃検知に使う |
| `transports` | varchar(255) | YES | NULL | 利用可能なトランスポート（JSON 配列形式。例: `["internal","hybrid"]`） |
| `aaguid` | varchar(36) | YES | NULL | 認証器識別子（UUID 形式）。オプション保存 |
| `name` | varchar(255) | YES | NULL | ユーザーが識別しやすい表示名 |
| `last_used` | datetime | YES | NULL | 最終利用日時 |
| `created` | datetime | YES | NULL | 登録日時 |
| `modified` | datetime | YES | NULL | 最終更新日時 |

### インデックス定義

| インデックス名 | 種別 | 対象カラム | 理由 |
| --- | --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` | — |
| `UNIQUE_credential_id` | UNIQUE | `credential_id`（先頭 191 文字） | assertion 時の高速検索と重複防止 |
| `IDX_user_id` | INDEX | `user_id` | ユーザー単位の一覧取得 |
| `IDX_user_handle` | INDEX | `user_handle` | Discoverable Credential 時の user handle 照合 |

> `credential_id` は text 型のため、MySQL の UNIQUE インデックスは先頭 191 文字に限定します（utf8mb4 で 767 バイト制限）。

### 実装コード（参考）

```php
<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreatePasskeyCredentials extends BcMigration
{
    public function up(): void
    {
        $this->table('passkey_credentials', [
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('prefix', 'string', [
                'default' => 'Admin',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('user_handle', 'string', [
                'default' => null,
                'limit' => 191,
                'null' => false,
            ])
            ->addColumn('credential_id', 'text', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('public_key', 'text', [
                'default' => null,
                'null' => false,
                // mediumtext が必要な場合は 'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM
            ])
            ->addColumn('counter', 'integer', [
                'default' => 0,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('transports', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('aaguid', 'string', [
                'default' => null,
                'limit' => 36,
                'null' => true,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('last_used', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['user_id'])
            ->addIndex(['user_handle'])
            ->create();

        // credential_id は text 型のため prefix 指定付きで UNIQUE 追加
        $this->execute(
            'ALTER TABLE passkey_credentials ADD UNIQUE INDEX UNIQUE_credential_id (credential_id(191))'
        );
    }

    public function down(): void
    {
        $this->table('passkey_credentials')->drop()->save();
    }
}
```

### 運用上の注意

- `counter` の値が前回登録値より小さい場合はリプレイ攻撃の疑いがあるため、ログへ記録してログインを拒否する
- `public_key` は秘密鍵ではないが、値の過失変更を防ぐため直接 UPDATE を避けて削除再登録を基本とする
- `user_handle` は WebAuthn の仕様上 64 バイト以下の不透明な識別子。users.id の Base64URL 変換値を使うことが多いが、将来の ID 体系変更を避けるため独立した列として管理する

---

## auth_provider_links（BcSocialAuth）

### テーブル概要

baserCMS ユーザーと外部プロバイダアカウントの連携情報を管理するテーブルです。  
同一ユーザーが複数プロバイダを連携できる 1 対多の構造です。  
`(provider, provider_user_id)` の組み合わせを実質的な主キーとして扱います。

### カラム定義

| カラム名 | 型 | NULL | デフォルト | 説明 |
| --- | --- | --- | --- | --- |
| `id` | int (auto) | NO | — | 主キー |
| `user_id` | int | NO | — | users テーブルの ID |
| `prefix` | varchar(50) | NO | `'Admin'` | 利用プレフィックス（Admin / Front） |
| `provider` | varchar(50) | NO | — | プロバイダ識別子（`google`, `x`, `line`, `apple` など） |
| `provider_user_id` | varchar(255) | NO | — | 外部プロバイダ側のユーザー一意識別子（Google は `sub`、X は数値 ID） |
| `email` | varchar(255) | YES | NULL | 取得したメールアドレス。X など取得不可の場合は NULL |
| `email_verified` | tinyint(1) | NO | `0` | メール確認済みか |
| `name` | varchar(255) | YES | NULL | 外部プロバイダから取得した表示名 |
| `avatar_url` | varchar(512) | YES | NULL | アバター画像 URL |
| `profile` | text | YES | NULL | プロフィール補助情報（JSON シリアライズ。最小限） |
| `linked_by` | varchar(20) | NO | `'self'` | 連携の起点（`self` / `admin` / `auto`） |
| `last_login` | datetime | YES | NULL | この連携を使った最終ログイン日時 |
| `last_login_ip` | varchar(45) | YES | NULL | 最終ログイン元 IP（IPv6 対応で 45 文字） |
| `last_login_user_agent` | varchar(512) | YES | NULL | 最終ログイン端末の User-Agent |
| `disabled` | tinyint(1) | NO | `0` | 個別連携の無効化フラグ。1 でこの連携によるログインを拒否 |
| `created` | datetime | YES | NULL | 連携作成日時 |
| `modified` | datetime | YES | NULL | 最終更新日時 |

### インデックス定義

| インデックス名 | 種別 | 対象カラム | 理由 |
| --- | --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` | — |
| `UNIQUE_provider_link` | UNIQUE | `provider`, `provider_user_id` | 同一プロバイダへの重複登録防止 |
| `IDX_user_id` | INDEX | `user_id` | ユーザー単位の連携一覧取得 |
| `IDX_prefix` | INDEX | `prefix` | prefix 単位のフィルタ |

### 実装コード（参考）

```php
<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateAuthProviderLinks extends BcMigration
{
    public function up(): void
    {
        $this->table('auth_provider_links', [
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('prefix', 'string', [
                'default' => 'Admin',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('provider', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('provider_user_id', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('email', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('email_verified', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('avatar_url', 'string', [
                'default' => null,
                'limit' => 512,
                'null' => true,
            ])
            ->addColumn('profile', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('linked_by', 'string', [
                'default' => 'self',
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('last_login', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('last_login_ip', 'string', [
                'default' => null,
                'limit' => 45,
                'null' => true,
            ])
            ->addColumn('last_login_user_agent', 'string', [
                'default' => null,
                'limit' => 512,
                'null' => true,
            ])
            ->addColumn('disabled', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['provider', 'provider_user_id'], ['unique' => true, 'name' => 'UNIQUE_provider_link'])
            ->addIndex(['user_id'])
            ->addIndex(['prefix'])
            ->create();
    }

    public function down(): void
    {
        $this->table('auth_provider_links')->drop()->save();
    }
}
```

### 運用上の注意

- `disabled = 1` にしても users テーブルのアカウントは無効にならない。連携のみを無効化する
- `email_verified = false` の連携は、自動連携ポリシーが有効でも候補対象にしない
- `profile` 列には個人情報を最小限にとどめる。ID・表示名・アバター URL 以上の情報は保存しない
- `linked_by = 'auto'` の連携は管理画面で識別できるよう一覧に表示することを推奨する

---

## ファイル配置

| テーブル | プラグイン | 推奨ファイル名（例） |
| --- | --- | --- |
| `passkey_credentials` | BcPasskeyAuth | `plugins/BcPasskeyAuth/config/Migrations/20260409000001_CreatePasskeyCredentials.php` |
| `auth_provider_links` | BcSocialAuth | `plugins/BcSocialAuth/config/Migrations/20260409000001_CreateAuthProviderLinks.php` |

ファイル名先頭のタイムスタンプは実装時点の日時に合わせてください。

---

## 関連文書

- インターフェース仕様: auth-login-redirect-service-spec.md
- 共通責務の全体方針: auth-common-architecture.md
- BcPasskeyAuth の詳細設計: ../../BcPasskeyAuth/docs/passkey-auth-design.md
- BcSocialAuth の詳細設計: ../../BcSocialAuth/docs/social-auth-design.md
