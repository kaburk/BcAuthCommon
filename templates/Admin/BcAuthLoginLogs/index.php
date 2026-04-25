<?php
/**
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \Cake\Datasource\Paging\PaginatedResultSet $logs
 * @var array $statusList
 */
$this->BcAdmin->setTitle(__d('baser_core', '認証ログイン履歴'));
$this->BcAdmin->setSearch('BcAuthCommon.bc_auth_login_logs_index');
?>

<?php echo $this->BcAdminForm->create(null, [
    'url' => ['action' => 'batch'],
    'type' => 'post'
]) ?>

<div class="bca-data-list__top">
    <div class="bca-action-table-listup">
        <?php echo $this->BcAdminForm->control('batch', [
            'type' => 'select',
            'options' => ['delete' => __d('baser_core', '削除')],
            'empty' => __d('baser_core', '一括処理'),
            'data-bca-select-size' => 'lg'
        ]) ?>
        <?php echo $this->BcAdminForm->button(__d('baser_core', '適用'), [
            'class' => 'bca-btn',
            'data-bca-btn-type' => 'delete'
        ]) ?>
    </div>
    <div class="bca-data-list__sub">
        <?php $this->BcBaser->element('pagination') ?>
    </div>
</div>

<table class="list-table bca-table-listup" id="ListTable">
    <thead class="bca-table-listup__thead">
    <tr>
        <th class="bca-table-listup__thead-th bca-table-listup__thead-th--select">
            <?php echo $this->BcAdminForm->control('checkall', ['type' => 'checkbox', 'label' => ' ']) ?>
        </th>
        <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('id', __d('baser_core', 'No')) ?></th>
        <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('event', __d('baser_core', '状態')) ?></th>
        <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('auth_source', __d('baser_core', '認証種別')) ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', 'ログインID') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('ip_address', __d('baser_core', 'IPアドレス')) ?></th>
        <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('created', __d('baser_core', '発生日時')) ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', 'アクション') ?></th>
    </tr>
    </thead>
    <tbody class="bca-table-listup__tbody">
    <?php if ($logs->count()): ?>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--select">
                    <?php echo $this->BcAdminForm->control('batch_targets.' . $log->id, [
                        'type' => 'checkbox',
                        'label' => false,
                        'value' => $log->id,
                        'class' => 'batch-targets bca-checkbox__input'
                    ]) ?>
                </td>
                <td class="bca-table-listup__tbody-td"><?php echo $log->id ?></td>
                <td class="bca-table-listup__tbody-td"><?php echo h($statusList[$log->event] ?? $log->event) ?></td>
                <td class="bca-table-listup__tbody-td"><?php echo h($log->auth_source) ?></td>
                <td class="bca-table-listup__tbody-td"><?php echo h((string) $log->username) ?></td>
                <td class="bca-table-listup__tbody-td"><?php echo h($log->ip_address) ?></td>
                <td class="bca-table-listup__tbody-td"><?php echo $this->BcTime->format($log->created, 'yyyy-MM-dd HH:mm:ss') ?></td>
                <td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--actions">
                    <?php
                    echo $this->BcHtml->link('', ['action' => 'view', $log->id], [
                        'title' => __d('baser_core', '詳細'),
                        'class' => 'bca-btn-icon',
                        'data-bca-btn-type' => 'preview',
                        'data-bca-btn-size' => 'lg',
                    ]);
                    echo $this->BcAdminForm->postLink('', ['action' => 'delete', $log->id], [
                        'confirm' => __d('baser_core', 'ログイン履歴 No.{0} を削除してもよろしいですか？', $log->id),
                        'title' => __d('baser_core', '削除'),
                        'class' => 'btn-delete bca-btn-icon',
                        'data-bca-btn-type' => 'delete',
                        'data-bca-btn-size' => 'lg',
                    ]);
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" class="bca-table-listup__tbody-td">
                <p class="no-data"><?php echo __d('baser_core', 'データが見つかりませんでした。') ?></p>
            </td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="bca-data-list__bottom">
    <div class="bca-data-list__sub">
        <?php $this->BcBaser->element('pagination') ?>
        <?php $this->BcBaser->element('list_num') ?>
    </div>
</div>

<?php echo $this->BcAdminForm->end() ?>
<?php echo $this->fetch('postLink') ?>
