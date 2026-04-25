<?php
/**
 * @var \BaserCore\View\BcAdminAppView $this
 * @var array $statusList
 */
$query = $this->getRequest()->getQueryParams();
?>

<?php echo $this->BcAdminForm->create(null, ['novalidate' => true, 'method' => 'get', 'url' => ['action' => 'index']]) ?>
<p class="bca-search__input-list">
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('status', __d('baser_core', '状態'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('status', [
            'type' => 'select',
            'options' => $statusList,
            'empty' => __d('baser_core', '指定なし'),
            'value' => (string) ($query['status'] ?? '')
        ]) ?>
    </span>
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('username', __d('baser_core', 'ログインID'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('username', ['type' => 'text', 'value' => (string) ($query['username'] ?? '')]) ?>
    </span>
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('ip_address', __d('baser_core', 'IPアドレス'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('ip_address', ['type' => 'text', 'value' => (string) ($query['ip_address'] ?? '')]) ?>
    </span>
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('auth_source', __d('baser_core', '認証種別'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('auth_source', ['type' => 'text', 'value' => (string) ($query['auth_source'] ?? '')]) ?>
    </span>
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('referer', __d('baser_core', 'リファラー'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('referer', ['type' => 'text', 'value' => (string) ($query['referer'] ?? '')]) ?>
    </span>
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('from', __d('baser_core', '期間(開始)'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('from', ['type' => 'date', 'value' => (string) ($query['from'] ?? '')]) ?>
    </span>
    <span class="bca-search__input-item">
        <?php echo $this->BcAdminForm->label('to', __d('baser_core', '期間(終了)'), ['class' => 'bca-search__input-item-label']) ?>
        <?php echo $this->BcAdminForm->control('to', ['type' => 'date', 'value' => (string) ($query['to'] ?? '')]) ?>
    </span>
</p>
<div class="button bca-search__btns">
    <div class="bca-search__btns-item">
        <?php echo $this->BcAdminForm->button(__d('baser_core', '検索'), [
            'id' => 'BtnSearchSubmit',
            'class' => 'bca-btn bca-loading',
            'data-bca-btn-type' => 'search'
        ]) ?>
    </div>
    <div class="bca-search__btns-item">
        <?php echo $this->BcAdminForm->button(__d('baser_core', 'クリア'), [
            'id' => 'BtnSearchClear',
            'class' => 'bca-btn',
            'data-bca-btn-type' => 'clear'
        ]) ?>
    </div>
</div>
<?php echo $this->BcAdminForm->end() ?>
