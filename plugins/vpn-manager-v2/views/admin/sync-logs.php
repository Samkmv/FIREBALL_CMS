<?php
$logs = is_array($logs ?? null) ? $logs : [];
$rows = [];
foreach ($logs as $log) {
    $changed = json_decode((string)($log['changed_fields_json'] ?? ''), true);
    $rows[] = ['cells' => [
        ['value' => '#' . (int)$log['id']],
        ['value' => (string)$log['operation_type']],
        ['value' => (string)$log['source']],
        ['value' => !empty($log['server_id']) ? '#' . (int)$log['server_id'] . ' · ' . (string)($log['server_name'] ?? '') : '—'],
        ['value' => !empty($log['subscription_id']) ? '#' . (int)$log['subscription_id'] : '—'],
        ['value' => !empty($log['connection_id']) ? '#' . (int)$log['connection_id'] : '—'],
        ['value' => is_array($changed) && $changed !== [] ? implode(', ', $changed) : '—'],
        ['value' => (string)$log['status']],
        ['html' => !empty($log['safe_error']) ? '<span class="text-danger">' . htmlSC((string)$log['safe_error']) . '</span>' : '—'],
        ['value' => (string)$log['created_at']],
    ]];
}
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<div class="border rounded-5 p-3 p-md-4">
<?= view()->renderPartial('admin/partials/table', [
    'columns' => [
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_operation_type')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_source')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_subscription')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_id')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_changed_fields')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_error')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_created_at')],
    ],
    'rows' => $rows,
    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_sync_logs'),
]) ?>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
