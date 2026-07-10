<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$connections = is_array($connections ?? null) ? $connections : [];
$actions = '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/jobs/sync-traffic')) . '" method="post">' . get_csrf_field() . '<button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-refresh-cw"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_sync_traffic')) . '</button></form>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_connections_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_subscription')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_user')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_server')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_inbound')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_client_email')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_last_sync')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_last_error')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $node): array {
                $id = (int)$node['id'];
                $status = (string)($node['status'] ?? '');
                $toggleStatus = $status === 'active' ? 'disabled' : 'active';
                $userLabel = trim((string)($node['user_name'] ?? ''));
                $serverLabel = trim((string)($node['server_name'] ?? ''));
                $inboundLabel = trim((string)($node['inbound_name'] ?? ''));
                return [
                    'cells' => [
                        ['value' => '#' . $id],
                        ['html' => '<a href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/' . (int)$node['subscription_id'])) . '">#' . (int)$node['subscription_id'] . '</a>'],
                        ['value' => $userLabel !== '' ? $userLabel : FireballPluginVpnManager::t('vpn_manager_user_missing')],
                        ['value' => $serverLabel !== '' ? $serverLabel : FireballPluginVpnManager::t('vpn_manager_server_missing')],
                        ['value' => $inboundLabel !== '' ? $inboundLabel : FireballPluginVpnManager::t('vpn_manager_inbound_missing')],
                        ['html' => vpnm_status_badge((string)($node['status'] ?? ''))],
                        ['value' => (string)($node['client_email'] ?? '-')],
                        ['value' => Formatter::bytes((int)($node['traffic_used_bytes'] ?? 0)) . ' / ' . Formatter::bytes((int)($node['traffic_limit_bytes'] ?? 0))],
                        ['value' => Formatter::dateTime((string)($node['last_sync_at'] ?? ''))],
                        ['html' => !empty($node['last_error']) ? '<div class="small text-danger text-break">' . htmlSC((string)$node['last_error']) . '</div>' : '<span class="text-body-secondary">' . htmlSC(FireballPluginVpnManager::t('vpn_manager_none')) . '</span>'],
                        ['html' => vpnm_actions_dropdown([
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_open'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/' . (int)$node['subscription_id']), 'icon' => 'ci-eye'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_sync_connection'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/connections/sync'), 'hidden' => ['id' => $id], 'icon' => 'ci-refresh-cw'],
                            ['label' => $toggleStatus === 'active' ? FireballPluginVpnManager::t('vpn_manager_action_enable') : FireballPluginVpnManager::t('vpn_manager_action_disable'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/connections/status'), 'hidden' => ['id' => $id, 'status' => $toggleStatus], 'icon' => 'ci-power'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_reset_traffic'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/connections/reset-traffic'), 'hidden' => ['id' => $id], 'icon' => 'ci-activity'],
                            ['type' => 'divider'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_delete_from_server'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/connections/delete'), 'hidden' => ['id' => $id], 'icon' => 'ci-trash', 'class' => 'text-danger'],
                        ])],
                    ],
                ];
            }, $connections),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_connections'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
