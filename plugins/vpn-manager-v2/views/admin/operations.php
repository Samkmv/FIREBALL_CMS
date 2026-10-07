<?php
use Fireball\VpnManagerV2\Support\AdminActionDropdown;
use Fireball\VpnManagerV2\Support\LocalizedValue;

$operations = is_array($operations ?? null) ? $operations : [];
$pagination = $pagination ?? null;
$rows = [];
foreach ($operations as $operation) {
    $status = (string)$operation['status'];
    $class = in_array($status, ['completed'], true) ? 'text-bg-success'
        : (in_array($status, ['failed'], true) ? 'text-bg-danger'
            : (in_array($status, ['running'], true) ? 'text-bg-primary'
                : (in_array($status, ['cancelled'], true) ? 'text-bg-secondary' : 'text-bg-warning')));
    $cancelActions = in_array($status, ['pending', 'retry'], true) ? [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_cancel_operation'),
        'type' => 'form',
        'action' => base_href('/admin/plugins/vpn-manager-v2/operations/'
            . (string)$operation['operation_id'] . '/cancel'),
        'form_attributes' => ['data-vpn-v2-async-operation' => true],
        'icon' => 'ci-close',
        'class' => 'text-danger',
    ]] : [];
    $cancel = AdminActionDropdown::render($cancelActions);
    $rows[] = ['cells' => [
        ['html' => (!empty($operation['subscription_id'])
            ? '<a href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . (int)$operation['subscription_id'])) . '">'
                . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_subscriptions')) . ' #' . (int)$operation['subscription_id'] . '</a><br>' : '')
            . (!empty($operation['server_id'])
                ? '<a href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . (int)$operation['server_id'] . '/recovery')) . '">'
                    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_action')) . ' #' . (int)$operation['server_id'] . '</a><br>' : '')
            . '<code class="small">' . htmlSC((string)$operation['operation_id']) . '</code>'],
        ['value' => LocalizedValue::operationType($operation['operation_type'] ?? '')],
        ['value' => LocalizedValue::operationSource($operation['source'] ?? '')],
        ['html' => '<span class="badge rounded-pill ' . $class . '">'
            . htmlSC(LocalizedValue::operationStatus($status)) . '</span>'],
        ['value' => (int)$operation['processed_count'] . ' / ' . (int)$operation['total_count']],
        ['value' => (int)$operation['attempts'] . ' / ' . (int)$operation['max_attempts']],
        ['html' => !empty($operation['last_error']) ? '<span class="text-danger">' . htmlSC((string)$operation['last_error']) . '</span>' : '—'],
        ['value' => (string)$operation['updated_at']],
        ['html' => $cancel],
    ]];
}
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<?php require __DIR__ . '/partials/operation-alert.php'; ?>
<details class="fb-plugin-details border rounded-5 p-3 p-md-4 mb-4 mt-0">
    <summary class="p-0 fs-6">
        <span>
            <span class="rounded-circle bg-body-tertiary border d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem" aria-hidden="true"><i class="ci-settings"></i></span>
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_advanced')) ?>
        </span>
        <i class="ci-chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="d-flex flex-wrap gap-2 border-top pt-3 mt-3">
        <form class="mw-100" method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/sync/full')) ?>" data-vpn-v2-async-operation>
            <?= get_csrf_field() ?>
            <button class="btn btn-dark rounded-pill mw-100 text-wrap d-inline-flex align-items-center gap-2" type="submit"><i class="ci-refresh-cw flex-shrink-0" aria-hidden="true"></i><span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_full_sync')) ?></span></button>
        </form>
        <form class="mw-100" method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations/retry')) ?>" data-vpn-v2-async-operation>
            <?= get_csrf_field() ?>
            <button class="btn btn-outline-warning rounded-pill mw-100 text-wrap d-inline-flex align-items-center gap-2" type="submit"><i class="ci-rotate-ccw flex-shrink-0" aria-hidden="true"></i><span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_operations')) ?></span></button>
        </form>
        <form class="mw-100" method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations/process')) ?>" data-vpn-v2-async-operation>
            <?= get_csrf_field() ?>
            <button class="btn btn-outline-primary rounded-pill mw-100 text-wrap d-inline-flex align-items-center gap-2" type="submit"><i class="ci-play flex-shrink-0" aria-hidden="true"></i><span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_process_operations')) ?></span></button>
        </form>
    </div>
</details>
<div class="border rounded-5 p-3 p-md-4" data-vpn-v2-operations-table>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_operations')) ?></h2>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations/clear')) ?>"
              data-admin-delete-form
              data-confirm-title="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_clear_operations_title')) ?>"
              data-delete-message="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_clear_operations')) ?>"
              data-confirm-hint="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_clear_operations_hint')) ?>"
              data-delete-confirm-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_clear_operations')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="confirmation" value="clear_vpn_operations">
            <button class="btn btn-danger rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 py-2" type="submit" <?= (int)($pagination['total_records'] ?? count($operations)) === 0 ? 'disabled' : '' ?>>
                <i class="ci-trash" aria-hidden="true"></i>
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_clear_operations')) ?></span>
            </button>
        </form>
    </div>
<?= view()->renderPartial('admin/partials/table', [
    'columns' => [
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_operation_id')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_operation_type')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_source')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_processed')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_attempts')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_error')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_updated_at')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions')],
    ],
    'rows' => $rows,
    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_operations'),
]) ?>
<?= view()->renderPartial('admin/partials/table_footer', [
    'visible' => count($operations),
    'total' => (int)($pagination['total_records'] ?? count($operations)),
    'pagination' => $pagination,
]) ?>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
