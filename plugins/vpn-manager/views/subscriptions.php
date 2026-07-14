<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$currentUrl = $_SERVER['REQUEST_URI'] ?? base_href('/admin/plugins/vpn-manager/subscriptions');
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/create')) . '"><i class="ci-plus"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_create_subscription')) . '</a>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/jobs/check-expirations')) . '" method="post">' . get_csrf_field() . '<button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-clock"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_check_expirations')) . '</button></form>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/jobs/check-traffic-limits')) . '" method="post">' . get_csrf_field() . '<button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-activity"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_check_traffic_limits')) . '</button></form>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/jobs/send-expiration-notifications')) . '" method="post">' . get_csrf_field() . '<button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-bell"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_send_notifications')) . '</button></form>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/jobs/send-traffic-notifications')) . '" method="post">' . get_csrf_field() . '<button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-bell"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_send_traffic_notifications')) . '</button></form>';
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
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_nodes')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $item) use ($currentUrl): array {
                $id = (int)$item['id'];
                $status = (string)($item['status'] ?? '');
                $toggleStatus = $status === 'active' ? 'suspended' : 'active';
                $userLabel = trim((string)($item['user_name'] ?? $item['user_email'] ?? ''));
                $planLabel = trim((string)($item['plan_name'] ?? ''));
                $deleteLabel = $status === 'delete_failed'
                    ? FireballPluginVpnManager::t('vpn_manager_action_retry_delete')
                    : FireballPluginVpnManager::t('vpn_manager_action_delete_forever');
                $confirm = sprintf(FireballPluginVpnManager::t('vpn_manager_confirm_delete_subscription'), $id);
                return [
                    'cells' => [
                        ['value' => '#' . $id],
                        ['value' => $userLabel !== '' ? $userLabel : FireballPluginVpnManager::t('vpn_manager_user_missing')],
                        ['value' => $planLabel !== '' ? $planLabel : FireballPluginVpnManager::t('vpn_manager_plan_missing')],
                        ['html' => vpnm_status_badge($status)],
                        ['value' => Formatter::dateTime((string)($item['starts_at'] ?? ''))],
                        ['value' => Formatter::dateTime((string)($item['expires_at'] ?? ''))],
                        ['value' => Formatter::traffic((int)($item['traffic_used_bytes'] ?? 0), (int)($item['traffic_limit_bytes'] ?? 0))],
                        ['value' => (string)(int)($item['server_count'] ?? 0)],
                        ['value' => (string)(int)($item['node_count'] ?? 0)],
                        ['html' => vpnm_actions_dropdown([
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_open'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/' . $id), 'icon' => 'ci-eye'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_extend'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/extend/' . $id), 'icon' => 'ci-calendar-plus'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_recreate_clients'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/subscriptions/provision'), 'hidden' => ['id' => $id], 'icon' => 'ci-refresh-cw'],
                            ['label' => $toggleStatus === 'active' ? FireballPluginVpnManager::t('vpn_manager_action_enable') : FireballPluginVpnManager::t('vpn_manager_action_disable'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/subscriptions/status'), 'hidden' => ['id' => $id, 'status' => $toggleStatus], 'icon' => 'ci-power'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_reset_traffic'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/subscriptions/reset-traffic'), 'hidden' => ['id' => $id], 'icon' => 'ci-activity'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_manual_reminder'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/subscriptions/manual-reminder'), 'hidden' => ['id' => $id], 'icon' => 'ci-bell'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_show_qr'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/' . $id . '#subscription-link'), 'icon' => 'ci-qr-code'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_copy_subscription_link'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/' . $id . '#subscription-link'), 'icon' => 'ci-copy'],
                            ['type' => 'divider'],
                            ['label' => $deleteLabel, 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/subscriptions/delete'), 'hidden' => ['id' => $id, 'return_url' => $currentUrl], 'icon' => 'ci-trash', 'class' => 'text-danger', 'confirm' => $confirm, 'delete_item' => '#' . $id, 'delete_confirm_label' => $deleteLabel],
                        ])],
                    ],
                ];
            }, $subscriptions),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_subscriptions'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
