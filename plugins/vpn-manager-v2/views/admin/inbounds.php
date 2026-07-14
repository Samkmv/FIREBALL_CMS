<?php

$inbounds = is_array($inbounds ?? null) ? $inbounds : [];
$servers = is_array($servers ?? null) ? $servers : [];
$statusBadge = static function (string $status): string {
    $classes = [
        'active' => 'text-bg-success',
        'disabled' => 'text-bg-secondary',
        'sync_missing' => 'text-bg-warning',
        'parse_error' => 'text-bg-danger',
    ];
    $status = array_key_exists($status, $classes) ? $status : 'parse_error';

    return '<span class="badge rounded-pill ' . $classes[$status] . '">'
        . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_inbound_status_' . $status)) . '</span>';
};

$rows = [];
$mobileCards = [];
foreach ($inbounds as $inbound) {
    $id = (int)($inbound['id'] ?? 0);
    $serverHtml = '<span class="fw-medium">' . htmlSC((string)($inbound['server_name'] ?? ''))
        . '</span><div class="small text-body-secondary">' . htmlSC((string)($inbound['server_code'] ?? '')) . '</div>';
    $status = $statusBadge((string)($inbound['status'] ?? 'parse_error'));
    $flow = trim((string)($inbound['default_flow'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none');
    $network = trim((string)($inbound['network'] ?? '')) ?: '—';
    $security = trim((string)($inbound['security'] ?? '')) ?: 'none';
    $syncedAt = trim((string)($inbound['synced_at'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_never');

    $rows[] = [
        'cells' => [
            ['value' => '#' . $id],
            ['html' => $serverHtml],
            ['value' => (string)$inbound['remote_inbound_id']],
            ['html' => '<span class="fw-medium">' . htmlSC((string)$inbound['name']) . '</span>'
                . (!empty($inbound['remark']) ? '<div class="small text-body-secondary">' . htmlSC((string)$inbound['remark']) . '</div>' : '')],
            ['value' => strtoupper((string)$inbound['protocol'])],
            ['value' => (string)(int)$inbound['port']],
            ['value' => strtoupper($network)],
            ['value' => strtoupper($security)],
            ['value' => $flow],
            ['html' => $status],
            ['value' => $syncedAt],
        ],
    ];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$inbound['name'],
        'icon' => 'ci-log-in',
        'status' => [['html' => $status]],
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server'), 'html' => $serverHtml],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_remote_id'), 'value' => (string)$inbound['remote_inbound_id']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol'), 'value' => strtoupper((string)$inbound['protocol'])],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_port'), 'value' => (string)(int)$inbound['port']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_transport'), 'value' => strtoupper($network)],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_security'), 'value' => strtoupper($security)],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_default_flow'), 'value' => $flow],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_synced_at'), 'value' => $syncedAt],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_inbounds_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_title')) ?></h2>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($servers as $server): ?>
            <form action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/inbounds/sync')) ?>" method="post">
                <?= get_csrf_field() ?>
                <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                    <i class="ci-refresh-cw" aria-hidden="true"></i>
                    <span><?= htmlSC((string)$server['name']) ?> (#<?= (int)$server['id'] ?>)</span>
                </button>
            </form>
        <?php endforeach; ?>
        <?php if ($servers === []): ?>
            <span class="text-body-secondary small"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_no_servers')) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="border rounded-5 p-3 p-md-4">
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_remote_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_name')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_port')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_transport')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_security')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_default_flow')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_synced_at')],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_inbounds'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
