<?php

use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\AdminActionDropdown;

$plans = is_array($plans ?? null) ? $plans : [];
$addUrl = base_href('/admin/plugins/vpn-manager-v2/plans/create');
$actions = Permissions::allows(Permissions::MANAGE_PLANS)
    ? '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
        . htmlSC($addUrl) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
        . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_plan')) . '</span></a>'
    : '';

$planActions = static function (array $plan): array {
    $id = (int)$plan['id'];

    $actions = [];
    if (Permissions::allows(Permissions::MANAGE_PLANS)) {
        $actions[] =
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'),
            'href' => base_href('/admin/plugins/vpn-manager-v2/plans/edit/' . $id),
            'icon' => 'ci-edit',
        ];
        $actions[] =
        [
            'label' => FireballPluginVpnManagerV2::t(!empty($plan['is_active'])
                ? 'vpn_manager_v2_action_disable'
                : 'vpn_manager_v2_action_enable'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/plans/toggle'),
            'hidden' => ['id' => $id],
            'icon' => 'ci-power',
        ];
    }
    if (Permissions::allows(Permissions::RECONCILE)) {
        $actions[] =
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_preview_reconciliation'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/plans/' . $id . '/preview'),
            'hidden' => [],
            'icon' => 'ci-search',
        ];
        $actions[] =
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_reconcile'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/plans/' . $id . '/reconcile'),
            'hidden' => [],
            'icon' => 'ci-refresh-cw',
        ];
    }
    if (Permissions::allows(Permissions::MANAGE_PLANS)) {
        $actions[] = ['type' => 'divider'];
        $actions[] = [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_delete_plan'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/plans/' . $id . '/delete'),
            'icon' => 'ci-trash',
            'class' => 'text-danger',
            'form_attributes' => [
                'data-admin-delete-form' => true,
                'data-delete-message' => sprintf(
                    FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_delete_plan'),
                    (string)$plan['name']
                ),
                'data-delete-item' => (string)$plan['name'],
            ],
        ];
    }

    return $actions;
};

$rows = [];
$mobileCards = [];
foreach ($plans as $plan) {
    $id = (int)$plan['id'];
    $status = !empty($plan['is_active'])
        ? '<span class="badge rounded-pill text-bg-success">' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_status_active')) . '</span>'
        : '<span class="badge rounded-pill text-bg-secondary">' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_status_disabled')) . '</span>';
    $duration = (int)$plan['duration_days'] . ' ' . FireballPluginVpnManagerV2::t('vpn_manager_v2_days');
    $traffic = TrafficFormatter::limit(isset($plan['traffic_limit_bytes']) ? (int)$plan['traffic_limit_bytes'] : null);
    $servers = (int)($plan['server_count'] ?? 0);
    $nodes = (int)($plan['node_count'] ?? 0);
    $subscriptions = (int)($plan['subscription_count'] ?? 0);
    $mismatches = (int)($plan['mismatch_subscription_count'] ?? 0);
    $lastReconcile = trim((string)($plan['last_reconcile_at'] ?? ''));
    $lastStatus = trim((string)($plan['last_reconcile_status'] ?? ''));
    $lastStatusLabel = $lastStatus !== ''
        ? FireballPluginVpnManagerV2::t('vpn_manager_v2_reconcile_status_' . $lastStatus)
        : '';

    $rows[] = [
        'cells' => [
            ['value' => '#' . $id],
            ['html' => '<span class="fw-medium">' . htmlSC((string)$plan['name']) . '</span>'],
            ['value' => $duration],
            ['value' => $traffic],
            ['value' => (string)(int)$plan['device_limit']],
            ['html' => htmlSC((string)$servers) . '<div class="small text-body-secondary">'
                . htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_nodes_count'), $nodes)) . '</div>'],
            ['value' => (string)$subscriptions],
            ['html' => $mismatches > 0
                ? '<span class="badge rounded-pill text-bg-warning">' . $mismatches . '</span>'
                : '<span class="badge rounded-pill text-bg-success">0</span>'],
            ['html' => htmlSC($lastReconcile !== '' ? $lastReconcile : '—')
                . ($lastStatusLabel !== '' ? '<div class="small text-body-secondary">' . htmlSC($lastStatusLabel) . '</div>' : '')],
            ['html' => $status],
            ['html' => '<div class="text-end">' . AdminActionDropdown::render($planActions($plan)) . '</div>'],
        ],
    ];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$plan['name'],
        'icon' => 'ci-package',
        'status' => [['html' => $status]],
        'actions' => $planActions($plan),
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_duration'), 'value' => $duration],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_traffic_limit'), 'value' => $traffic],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_device_limit'), 'value' => (string)(int)$plan['device_limit']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_servers'), 'value' => (string)$servers],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes'), 'value' => (string)$nodes],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_subscriptions'), 'value' => (string)$subscriptions],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_discrepancies'), 'value' => (string)$mismatches],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_last_reconciliation'), 'value' => $lastReconcile !== '' ? $lastReconcile : '—'],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_plans_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="border rounded-5 p-3 p-md-4">
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_name')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_duration')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_traffic_limit')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_device_limit')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_servers')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_subscriptions')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_discrepancies')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_last_reconciliation')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_plans'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
