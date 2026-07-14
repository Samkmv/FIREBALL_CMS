<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;

$connections = is_array($connections ?? null) ? $connections : [];
$rows = [];
$mobileCards = [];
foreach ($connections as $connection) {
    $id = (int)$connection['id'];
    $showUrl = base_href('/admin/plugins/vpn-manager-v2/connections/' . $id);
    $badge = ProvisioningStatus::badge((string)$connection['status']);
    $flow = trim((string)($connection['flow'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none');
    $actions = [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'),
        'href' => $showUrl,
        'icon' => 'ci-eye',
    ]];
    $desktop = '<a class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" href="' . htmlSC($showUrl)
        . '" title="' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'))
        . '"><i class="ci-eye"></i></a>';
    if (ProvisioningStatus::canRetry((string)$connection['status'])) {
        $retryUrl = base_href('/admin/plugins/vpn-manager-v2/connections/' . $id . '/retry');
        $actions[] = [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_creation'),
            'type' => 'form',
            'action' => $retryUrl,
            'icon' => 'ci-refresh-cw',
        ];
        $desktop .= '<form method="post" action="' . htmlSC($retryUrl) . '">' . get_csrf_field()
            . '<button class="btn btn-sm btn-outline-warning btn-icon rounded-circle" type="submit" title="'
            . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_creation'))
            . '"><i class="ci-refresh-cw"></i></button></form>';
    }

    $rows[] = ['cells' => [
        ['value' => '#' . $id],
        ['html' => '<a class="text-decoration-none fw-medium" href="'
            . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . (int)$connection['subscription_id']))
            . '">#' . (int)$connection['subscription_id'] . '</a><div class="small text-body-secondary">'
            . htmlSC((string)$connection['plan_name']) . '</div>'],
        ['html' => htmlSC((string)$connection['user_name']) . '<div class="small text-body-secondary">' . htmlSC((string)$connection['user_email']) . '</div>'],
        ['html' => htmlSC((string)$connection['server_name']) . '<div class="small text-body-secondary">#' . (int)$connection['server_id'] . '</div>'],
        ['html' => htmlSC((string)$connection['inbound_name']) . '<div class="small text-body-secondary">remote #' . htmlSC((string)$connection['remote_inbound_id']) . '</div>'],
        ['value' => strtoupper((string)$connection['protocol'])],
        ['value' => strtoupper((string)($connection['network'] ?: '—'))],
        ['value' => strtoupper((string)($connection['security'] ?: 'none'))],
        ['value' => $flow],
        ['html' => $badge . (!empty($connection['last_error']) ? '<div class="small text-danger mt-1">' . htmlSC((string)$connection['last_error']) . '</div>' : '')],
        ['html' => '<div class="d-flex justify-content-end gap-1">' . $desktop . '</div>'],
    ]];
    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$connection['server_name'] . ' → ' . (string)$connection['inbound_name'],
        'icon' => 'ci-share-2',
        'status' => [['html' => $badge]],
        'actions' => $actions,
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_subscription'), 'value' => '#' . (int)$connection['subscription_id']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user'), 'value' => (string)$connection['user_name']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol'), 'value' => strtoupper((string)$connection['protocol'])],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_flow'), 'value' => $flow],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_connections_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="border rounded-5 p-3 p-md-4">
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_subscription')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_inbound')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_network')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_security')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_flow')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_connections'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
