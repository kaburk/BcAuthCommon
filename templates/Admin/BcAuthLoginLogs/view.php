<?php
/**
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \Cake\Datasource\EntityInterface $log
 * @var array $statusList
 */
$this->BcAdmin->setTitle(__d('baser_core', '認証ログイン履歴詳細'));
$detail = (string) ($log->detail ?? '');
if ($detail !== '') {
    $decoded = json_decode($detail, true);
    if (is_array($decoded)) {
        $detail = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
?>

<style>
#ListTable .bca-form-table__label {
    width: 180px;
    min-width: 180px;
    white-space: normal;
    word-break: keep-all;
}

#ListTable .bca-form-table__input pre {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>

<table class="list-table bca-form-table" id="ListTable">
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'No') ?></th>
        <td class="col-input bca-form-table__input"><?php echo $log->id ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', '状態') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h($statusList[$log->event] ?? $log->event) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', '認証種別') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h($log->auth_source) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'ログインID') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h((string) $log->username) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'ユーザーID') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h((string) $log->user_id) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'IPアドレス') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h($log->ip_address) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'ユーザーエージェント') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h($log->user_agent) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'リファラー') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h((string) $log->referer) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', 'リクエストパス') ?></th>
        <td class="col-input bca-form-table__input"><?php echo h((string) $log->request_path) ?></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', '詳細') ?></th>
        <td class="col-input bca-form-table__input"><pre><?php echo h($detail) ?></pre></td>
    </tr>
    <tr>
        <th class="col-head bca-form-table__label"><?php echo __d('baser_core', '登録日時') ?></th>
        <td class="col-input bca-form-table__input"><?php echo $this->BcTime->format($log->created, 'yyyy-MM-dd HH:mm:ss') ?></td>
    </tr>
</table>

<div class="bca-actions">
    <div class="bca-actions__before">
        <?php echo $this->BcHtml->link(__d('baser_core', '一覧に戻る'), ['action' => 'index'], [
            'class' => 'bca-btn bca-actions__item',
            'data-bca-btn-type' => 'back-to-list'
        ]) ?>
    </div>
    <div class="bca-actions__sub">
        <?php echo $this->BcAdminForm->postLink(__d('baser_core', '削除'), ['action' => 'delete', $log->id], [
            'confirm' => __d('baser_core', 'ログイン履歴 No.{0} を削除してもよろしいですか？', $log->id),
            'class' => 'bca-btn bca-actions__item',
            'data-bca-btn-type' => 'delete',
            'data-bca-btn-size' => 'sm',
            'data-bca-btn-color' => 'danger'
        ]) ?>
    </div>
</div>

<?php echo $this->fetch('postLink') ?>
