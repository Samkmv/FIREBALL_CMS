<?php

use Fireball\VpnManagerV2\Support\TrafficFormatter;

$plans = is_array($plans ?? null) ? $plans : [];
$addUrl = base_href('/admin/plugins/vpn-manager-v2/plans/create');
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC($addUrl) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_plan')) . '</span></a>';

$planActions = static function (array $plan): array {
    $id = (int)$plan['id'];

    return [
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'),
            'href' => base_href('/admin/plugins/vpn-manager-v2/plans/edit/' . $id),
            'icon' => 'ci-edit',
        ],
        [
            'label' => FireballPluginVpnManagerV2::t(!empty($plan['is_active'])
                ? 'vpn_manager_v2_action_disable'
                : 'vpn_manager_v2_action_enable'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/plans/toggle'),
            'hidden' => ['id' => $id],
            'icon' => 'ci-power',
        ],
    ];
};

$desktopActions = static function (array $plan) use ($planActions): string {
    $html = '<div class="d-flex flex-wrap justify-content-end gap-1">';
    foreach ($planActions($plan) as $action) {
        $label = (string)$action['label'];
        $content = '<i class="' . htmlSC((string)$action['icon']) . '" aria-hidden="true"></i>'
            . '<span class="visually-hidden">' . htmlSC($label) . '</span>';
        if (($action['type'] ?? 'link') === 'form') {
            $html .= '<form method="post" action="' . htmlSC((string)$action['action']) . '">' . get_csrf_field();
            foreach ((array)$action['hidden'] as $name => $value) {
                $html .= '<input type="hidden" name="' . htmlSC((string)$name) . '" value="' . htmlSC((string)$value) . '">';
            }
            $html .= '<button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="submit" title="'
                . htmlSC($label) . '" aria-label="' . htmlSC($label) . '">' . $content . '</button></form>';
            continue;
        }

        $html .= '<a class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" href="'
            . htmlSC((string)$action['href']) . '" title="' . htmlSC($label) . '" aria-label="'
            . htmlSC($label) . '">' . $content . '</a>';
    }

    return $html . '</div>';
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

    $rows[] = [
        'cells' => [
            ['value' => '#' . $id],
            ['html' => '<span class="fw-medium">' . htmlSC((string)$plan['name']) . '</span>'],
            ['value' => $duration],
            ['value' => $traffic],
            ['value' => (string)(int)$plan['device_limit']],
            ['html' => htmlSC((string)$servers) . '<div class="small text-body-secondary">'
                . htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_nodes_count'), $nodes)) . '</div>'],
            ['html' => $status],
            ['html' => $desktopActions($plan)],
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
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_plans'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
