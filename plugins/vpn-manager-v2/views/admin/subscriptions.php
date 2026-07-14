<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$addUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/create');
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC($addUrl) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_subscription')) . '</span></a>';

$rows = [];
$mobileCards = [];
foreach ($subscriptions as $subscription) {
    $id = (int)$subscription['id'];
    $showUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $id);
    $badge = ProvisioningStatus::badge((string)$subscription['status']);
    $nodeCount = (int)($subscription['node_count'] ?? 0);
    $activeCount = (int)($subscription['active_node_count'] ?? 0);
    $nodeText = $activeCount . ' / ' . $nodeCount;
    $traffic = TrafficFormatter::limit(isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : null);
    $showAction = [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'),
        'href' => $showUrl,
        'icon' => 'ci-eye',
    ]];
    $desktopAction = '<a class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" href="'
        . htmlSC($showUrl) . '" title="' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'))
        . '" aria-label="' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'))
        . '"><i class="ci-eye" aria-hidden="true"></i></a>';

    $rows[] = ['cells' => [
        ['value' => '#' . $id],
        ['html' => '<span class="fw-medium">' . htmlSC((string)$subscription['user_name'])
            . '</span><div class="small text-body-secondary">' . htmlSC((string)$subscription['user_email']) . '</div>'],
        ['html' => '<span class="fw-medium">' . htmlSC((string)$subscription['plan_name'])
            . '</span><div class="small text-body-secondary">#' . (int)$subscription['plan_id'] . '</div>'],
        ['html' => $badge],
        ['value' => (string)$subscription['starts_at']],
        ['value' => (string)($subscription['expires_at'] ?: '—')],
        ['html' => htmlSC($nodeText) . '<div class="small text-body-secondary">'
            . htmlSC($traffic) . ' · ' . (int)$subscription['device_limit'] . '</div>'],
        ['html' => '<div class="d-flex justify-content-end">' . $desktopAction . '</div>'],
    ]];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$subscription['plan_name'],
        'icon' => 'ci-link',
        'status' => [['html' => $badge]],
        'actions' => $showAction,
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user'), 'value' => (string)$subscription['user_name']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_starts_at'), 'value' => (string)$subscription['starts_at']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_expires_at'), 'value' => (string)($subscription['expires_at'] ?: '—')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes'), 'value' => $nodeText],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_traffic_limit'), 'value' => $traffic],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="border rounded-5 p-3 p-md-4">
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_starts_at')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_expires_at')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_subscriptions'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
