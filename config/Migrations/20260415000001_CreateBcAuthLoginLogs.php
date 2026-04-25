<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateBcAuthLoginLogs extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('bc_auth_login_logs', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true, 'default' => null, 'comment' => 'NULLは該当ユーザーなし（失敗時等）'])
            ->addColumn('username', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'ログインID（通常はメールアドレス）'])
            ->addColumn('prefix', 'string', ['limit' => 30, 'null' => false, 'default' => 'Admin'])
            ->addColumn('auth_source', 'string', ['limit' => 50, 'null' => false, 'comment' => 'password / social:google / social:x / passkey 等'])
            ->addColumn('event', 'string', ['limit' => 30, 'null' => false, 'comment' => 'login_success / login_failure / logout / link_cancel 等'])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true, 'default' => null])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('referer', 'string', ['limit' => 2048, 'null' => true, 'default' => null])
            ->addColumn('request_path', 'string', ['limit' => 2048, 'null' => true, 'default' => null])
            ->addColumn('detail', 'text', ['null' => true, 'default' => null, 'comment' => '任意の補足情報（JSON 文字列等）'])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['user_id'])
            ->addIndex(['username'])
            ->addIndex(['event'])
            ->addIndex(['auth_source'])
            ->addIndex(['ip_address'])
            ->addIndex(['created'])
            ->create();
    }
}
