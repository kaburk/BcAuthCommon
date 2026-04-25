<?php
declare(strict_types=1);

namespace BcAuthCommon\Controller\Admin;

use BaserCore\Utility\BcSiteConfig;
use Cake\ORM\Table;

class BcAuthLoginLogsController extends BcAuthCommonAdminAppController
{
    private Table $BcAuthLoginLogs;

    public function initialize(): void
    {
        parent::initialize();
        $this->BcAuthLoginLogs = $this->fetchTable('BcAuthCommon.BcAuthLoginLogs');
    }

    public function index()
    {
        $this->setViewConditions('BcAuthLoginLogs', [
            'default' => [
                'query' => [
                    'limit' => BcSiteConfig::get('admin_list_num'),
                    'sort' => 'created',
                    'direction' => 'desc',
                ]
            ]
        ]);

        $query = $this->BcAuthLoginLogs->find()->order([
            'BcAuthLoginLogs.created' => 'DESC',
            'BcAuthLoginLogs.id' => 'DESC',
        ]);

        $status = (string) $this->getRequest()->getQuery('status');
        if ($status !== '') {
            $query->where(['BcAuthLoginLogs.event' => $status]);
        }

        $username = trim((string) $this->getRequest()->getQuery('username'));
        if ($username !== '') {
            $conditions = [
                'BcAuthLoginLogs.username LIKE' => '%' . $username . '%',
            ];
            if (ctype_digit($username)) {
                $conditions[] = ['BcAuthLoginLogs.user_id' => (int) $username];
            }
            $query->where(['OR' => $conditions]);
        }

        $ipAddress = trim((string) $this->getRequest()->getQuery('ip_address'));
        if ($ipAddress !== '') {
            $query->where(['BcAuthLoginLogs.ip_address LIKE' => '%' . $ipAddress . '%']);
        }

        $authSource = trim((string) $this->getRequest()->getQuery('auth_source'));
        if ($authSource !== '') {
            $query->where(['BcAuthLoginLogs.auth_source LIKE' => '%' . $authSource . '%']);
        }

        $referer = trim((string) $this->getRequest()->getQuery('referer'));
        if ($referer !== '') {
            $query->where(['BcAuthLoginLogs.referer LIKE' => '%' . $referer . '%']);
        }

        $from = (string) $this->getRequest()->getQuery('from');
        if ($from !== '') {
            $query->where(['BcAuthLoginLogs.created >=' => $from . ' 00:00:00']);
        }

        $to = (string) $this->getRequest()->getQuery('to');
        if ($to !== '') {
            $query->where(['BcAuthLoginLogs.created <=' => $to . ' 23:59:59']);
        }

        $logs = $this->paginate($query);

        $this->set([
            'logs' => $logs,
            'statusList' => $this->getStatusList(),
        ]);
    }

    public function view(int $id)
    {
        $log = $this->BcAuthLoginLogs->get($id);
        $this->set([
            'log' => $log,
            'statusList' => $this->getStatusList(),
        ]);
    }

    public function delete(int $id)
    {
        $this->request->allowMethod(['post', 'delete']);

        try {
            $log = $this->BcAuthLoginLogs->get($id);
            if ($this->BcAuthLoginLogs->delete($log)) {
                $this->BcMessage->setSuccess(__d('baser_core', 'ログイン履歴 No.{0} を削除しました。', $id));
            } else {
                $this->BcMessage->setError(__d('baser_core', 'ログイン履歴の削除に失敗しました。'));
            }
        } catch (\Throwable $e) {
            $this->BcMessage->setError(__d('baser_core', 'ログイン履歴の削除中にエラーが発生しました。') . $e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }

    public function batch()
    {
        $this->request->allowMethod(['post']);

        $action = (string) $this->getRequest()->getData('batch');
        $targets = (array) $this->getRequest()->getData('batch_targets');
        $ids = array_map('intval', array_keys(array_filter($targets)));

        if ($action !== 'delete' || !$ids) {
            $this->BcMessage->setError(__d('baser_core', '一括処理の対象が選択されていません。'));
            return $this->redirect(['action' => 'index']);
        }

        try {
            $deleted = $this->BcAuthLoginLogs->deleteAll(['id IN' => $ids]);
            if ($deleted) {
                $this->BcMessage->setSuccess(__d('baser_core', '{0} 件のログイン履歴を削除しました。', count($ids)));
            } else {
                $this->BcMessage->setError(__d('baser_core', 'ログイン履歴の一括削除に失敗しました。'));
            }
        } catch (\Throwable $e) {
            $this->BcMessage->setError(__d('baser_core', 'ログイン履歴の一括削除中にエラーが発生しました。') . $e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }

    private function getStatusList(): array
    {
        return [
            'login_failure' => __d('baser_core', 'ログイン失敗'),
            'lockout_started' => __d('baser_core', 'ロック開始'),
            'lockout_denied' => __d('baser_core', 'ロック中拒否'),
            'blocked_ip_denied' => __d('baser_core', 'IP拒否'),
            'login_success' => __d('baser_core', 'ログイン成功'),
            'logout' => __d('baser_core', 'ログアウト'),
            'link_cancel' => __d('baser_core', '連携キャンセル'),
        ];
    }

}
