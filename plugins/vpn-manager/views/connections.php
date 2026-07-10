<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$connections = is_array($connections ?? null) ? $connections : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_connections_title'),
    'subtitle' => $subtitle ?? '',
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
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_uuid')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_last_sync')],
            ],
            'rows' => array_map(static function (array $node): array {
                return [
                    'cells' => [
                        ['value' => '#' . (int)$node['id']],
                        ['html' => '<a href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/' . (int)$node['subscription_id'])) . '">#' . (int)$node['subscription_id'] . '</a>'],
                        ['value' => (string)($node['user_name'] ?? '-')],
                        ['value' => (string)($node['server_name'] ?? '-')],
                        ['value' => (string)($node['inbound_name'] ?? '-')],
                        ['html' => vpnm_status_badge((string)($node['status'] ?? ''))],
                        ['value' => '••••••••'],
                        ['value' => Formatter::bytes((int)($node['traffic_used_bytes'] ?? 0)) . ' / ' . Formatter::bytes((int)($node['traffic_limit_bytes'] ?? 0))],
                        ['html' => htmlSC((string)($node['last_sync_at'] ?? '-')) . (!empty($node['last_error']) ? '<div class="small text-danger text-break">' . htmlSC((string)$node['last_error']) . '</div>' : '')],
                    ],
                ];
            }, $connections),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_connections'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
