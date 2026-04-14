<?php
declare(strict_types=1);

namespace BcAuthCommon\Model\Table;

use Cake\ORM\Table;

/**
 * BcAuthLoginLogsTable
 *
 * 認証イベント（ログイン成功・失敗・ログアウト等）の監査ログを管理するテーブル。
 * BcAuthCommon を利用する各認証プラグイン（BcAuthPasskey / BcAuthSocial）は
 * AuthLoginLogService 経由でここに記録する。
 */
class BcAuthLoginLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bc_auth_login_logs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
    }
}
