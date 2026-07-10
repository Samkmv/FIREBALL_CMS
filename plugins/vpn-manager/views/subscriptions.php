<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$actions = '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/jobs/check-expirations')) . '" method="post">' . get_csrf_field() . '<button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-clock"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_check_expirations')) . '</button></form>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_subscriptions_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_user')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_plan')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_start')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_expires_at')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_servers')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $item): array {
                return [
                    'cells' => [
                        ['value' => '#' . (int)$item['id']],
                        ['value' => (string)($item['user_name'] ?? $item['user_email'] ?? '-')],
                        ['value' => (string)($item['plan_name'] ?? '-')],
                        ['html' => vpnm_status_badge((string)($item['status'] ?? ''))],
                        ['value' => (string)($item['starts_at'] ?? '-')],
                        ['value' => (string)($item['expires_at'] ?? '-')],
                        ['value' => Formatter::bytes((int)($item['traffic_used_bytes'] ?? 0)) . ' / ' . Formatter::bytes((int)($item['traffic_limit_bytes'] ?? 0))],
                        ['value' => (string)(int)($item['node_count'] ?? 0)],
                        ['html' => vpnm_actions_dropdown([
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_open'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/' . (int)$item['id']), 'icon' => 'ci-eye'],
                        ])],
                    ],
                ];
            }, $subscriptions),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_subscriptions'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
