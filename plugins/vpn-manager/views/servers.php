<?php
require_once __DIR__ . '/partials/helpers.php';

$servers = is_array($servers ?? null) ? $servers : [];
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/servers/create')) . '"><i class="ci-plus"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_add_server')) . '</a>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_servers_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_name')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_location')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_panel_url')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_last_check')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $server): array {
                $actions = [
                    ['label' => FireballPluginVpnManager::t('vpn_manager_action_edit'), 'href' => base_href('/admin/plugins/vpn-manager/servers/edit/' . (int)$server['id']), 'icon' => 'ci-edit'],
                    ['label' => FireballPluginVpnManager::t('vpn_manager_action_test_connection'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/servers/test'), 'hidden' => ['id' => (int)$server['id']], 'icon' => 'ci-activity'],
                    ['label' => FireballPluginVpnManager::t('vpn_manager_action_sync_inbounds'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/servers/sync-inbounds'), 'hidden' => ['id' => (int)$server['id']], 'icon' => 'ci-refresh-cw'],
                    ['label' => !empty($server['is_enabled']) ? FireballPluginVpnManager::t('vpn_manager_action_disable') : FireballPluginVpnManager::t('vpn_manager_action_enable'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/servers/toggle'), 'hidden' => ['id' => (int)$server['id']], 'icon' => 'ci-power'],
                    ['type' => 'divider'],
                    ['label' => FireballPluginVpnManager::t('vpn_manager_action_delete'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/servers/delete'), 'hidden' => ['id' => (int)$server['id']], 'icon' => 'ci-trash', 'class' => 'text-danger'],
                ];

                $location = trim((string)($server['country'] ?? '') . ' ' . (string)($server['city'] ?? '')) ?: '-';
                $status = (string)($server['status'] ?? 'unchecked');
                if (empty($server['is_enabled'])) {
                    $status = 'disabled';
                }

                return [
                    'cells' => [
                        ['value' => '#' . (int)$server['id']],
                        ['html' => '<span class="fw-medium">' . htmlSC((string)$server['name']) . '</span><div class="small text-body-secondary">' . htmlSC((string)$server['code']) . '</div>'],
                        ['value' => $location],
                        ['value' => (string)$server['panel_url']],
                        ['html' => vpnm_status_badge($status)],
                        ['html' => htmlSC((string)($server['last_check_at'] ?? '-')) . (!empty($server['last_error']) ? '<div class="small text-danger text-break">' . htmlSC((string)$server['last_error']) . '</div>' : '')],
                        ['html' => vpnm_actions_dropdown($actions)],
                    ],
                ];
            }, $servers),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_servers'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
