<?php
use Fireball\VpnManagerV2\Support\LocalizedValue;

$logs = is_array($logs ?? null) ? $logs : [];
$logCounts = is_array($logCounts ?? null) ? $logCounts : ['sync_logs' => count($logs), 'events' => 0, 'total' => count($logs)];
$rows = [];
foreach ($logs as $log) {
    $changed = json_decode((string)($log['changed_fields_json'] ?? ''), true);
    $rows[] = ['cells' => [
        ['value' => '#' . (int)$log['id']],
        ['value' => LocalizedValue::operationType($log['operation_type'] ?? '')],
        ['value' => LocalizedValue::operationSource($log['source'] ?? '')],
        ['value' => !empty($log['server_id']) ? '#' . (int)$log['server_id'] . ' · ' . (string)($log['server_name'] ?? '') : '—'],
        ['value' => !empty($log['subscription_id']) ? '#' . (int)$log['subscription_id'] : '—'],
        ['value' => !empty($log['connection_id']) ? '#' . (int)$log['connection_id'] : '—'],
        ['value' => is_array($changed) && $changed !== []
            ? implode(', ', array_map([LocalizedValue::class, 'changedField'], $changed))
            : '—'],
        ['value' => LocalizedValue::operationStatus($log['status'] ?? '')],
        ['html' => !empty($log['safe_error']) ? '<span class="text-danger">' . htmlSC((string)$log['safe_error']) . '</span>' : '—'],
        ['value' => (string)$log['created_at']],
    ]];
}
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_logs_stored_title')) ?></div>
            <div class="h3 mb-1 mt-1"><?= (int)($logCounts['total'] ?? 0) ?></div>
            <div class="small text-body-secondary">
                <?= htmlSC(sprintf(
                    FireballPluginVpnManagerV2::t('vpn_manager_v2_logs_stored_breakdown'),
                    (int)($logCounts['sync_logs'] ?? 0),
                    (int)($logCounts['events'] ?? 0)
                )) ?>
            </div>
        </div>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/sync-logs/clear')) ?>"
              data-admin-delete-form
              data-delete-message="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_clear_logs')) ?>"
              data-delete-item="<?= (int)($logCounts['total'] ?? 0) ?>"
              data-confirm-item-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_logs_stored_title')) ?>"
              data-delete-confirm-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_clear_logs')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="confirmation" value="clear_all_vpn_logs">
            <button class="btn btn-outline-danger rounded-pill" type="submit" <?= (int)($logCounts['total'] ?? 0) === 0 ? 'disabled' : '' ?>>
                <i class="ci-trash me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_clear_logs')) ?>
            </button>
        </form>
    </div>
    <div class="small text-body-secondary mt-3">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_clear_logs_help')) ?>
    </div>
</div>

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
