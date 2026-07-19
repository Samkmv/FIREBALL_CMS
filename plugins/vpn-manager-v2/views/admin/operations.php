<?php
$operations = is_array($operations ?? null) ? $operations : [];
$rows = [];
foreach ($operations as $operation) {
    $status = (string)$operation['status'];
    $class = in_array($status, ['completed'], true) ? 'text-bg-success'
        : (in_array($status, ['failed'], true) ? 'text-bg-danger'
            : (in_array($status, ['running'], true) ? 'text-bg-primary' : 'text-bg-warning'));
    $cancel = in_array($status, ['pending', 'retry'], true)
        ? '<form method="post" action="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations/'
            . (string)$operation['operation_id'] . '/cancel')) . '" data-vpn-v2-async-operation>'
            . get_csrf_field()
            . '<button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">'
            . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_cancel_operation'))
            . '</button></form>'
        : '—';
    $rows[] = ['cells' => [
        ['html' => '<code>' . htmlSC((string)$operation['operation_id']) . '</code>'],
        ['value' => (string)$operation['operation_type']],
        ['value' => (string)$operation['source']],
        ['html' => '<span class="badge rounded-pill ' . $class . '">' . htmlSC($status) . '</span>'],
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
<div data-vpn-v2-operation-alert aria-live="polite"></div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/sync/full')) ?>" data-vpn-v2-async-operation>
        <?= get_csrf_field() ?>
        <button class="btn btn-dark rounded-pill" type="submit"><i class="ci-refresh-cw me-2"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_full_sync')) ?></button>
    </form>
    <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations/retry')) ?>" data-vpn-v2-async-operation>
        <?= get_csrf_field() ?>
        <button class="btn btn-outline-warning rounded-pill" type="submit"><i class="ci-rotate-ccw me-2"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_operations')) ?></button>
    </form>
</div>
<div class="border rounded-5 p-3 p-md-4">
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
</div>
<?= view()->renderPartial('admin/shell_close') ?>
