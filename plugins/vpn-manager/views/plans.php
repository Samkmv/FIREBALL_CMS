<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$plans = is_array($plans ?? null) ? $plans : [];
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/plans/create')) . '"><i class="ci-plus"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_add_plan')) . '</a>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_plans_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_name')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_duration')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic_limit')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_devices')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_servers')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $plan): array {
                $trafficMode = (string)($plan['traffic_mode'] ?? 'shared');
                $trafficModeLabel = FireballPluginVpnManager::t($trafficMode === 'per_node' ? 'vpn_manager_traffic_per_node' : 'vpn_manager_traffic_shared');
                return [
                    'cells' => [
                        ['value' => '#' . (int)$plan['id']],
                        ['html' => '<span class="fw-medium">' . htmlSC((string)$plan['name']) . '</span><div class="small text-body-secondary">' . htmlSC($trafficModeLabel) . '</div>'],
                        ['value' => (string)(int)$plan['duration_days'] . ' ' . FireballPluginVpnManager::t('vpn_manager_days')],
                        ['value' => Formatter::bytes((int)$plan['traffic_limit_bytes'])],
                        ['value' => (string)(int)$plan['device_limit']],
                        ['value' => (string)(int)($plan['server_count'] ?? 0)],
                        ['html' => vpnm_status_badge(!empty($plan['is_active']) ? 'active' : 'disabled')],
                        ['html' => vpnm_actions_dropdown([
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_edit'), 'href' => base_href('/admin/plugins/vpn-manager/plans/edit/' . (int)$plan['id']), 'icon' => 'ci-edit'],
                            ['label' => !empty($plan['is_active']) ? FireballPluginVpnManager::t('vpn_manager_action_disable') : FireballPluginVpnManager::t('vpn_manager_action_enable'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/plans/toggle'), 'hidden' => ['id' => (int)$plan['id']], 'icon' => 'ci-power'],
                            ['type' => 'divider'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_delete'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/plans/delete'), 'hidden' => ['id' => (int)$plan['id']], 'icon' => 'ci-trash', 'class' => 'text-danger'],
                        ])],
                    ],
                ];
            }, $plans),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_plans'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
