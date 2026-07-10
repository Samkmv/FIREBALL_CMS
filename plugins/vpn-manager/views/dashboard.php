<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$stats = is_array($stats ?? null) ? $stats : [];
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$events = is_array($events ?? null) ? $events : [];
$statCards = [
    ['vpn_manager_stat_subscriptions', (int)($stats['subscriptions'] ?? 0), 'ci-credit-card'],
    ['vpn_manager_stat_active_subscriptions', (int)($stats['active_subscriptions'] ?? 0), 'ci-check-circle'],
    ['vpn_manager_stat_provisioning_failed', (int)($stats['provisioning_failed'] ?? 0), 'ci-alert-triangle'],
    ['vpn_manager_stat_connections', (int)($stats['connections'] ?? 0), 'ci-link'],
    ['vpn_manager_stat_vpn_users', (int)($stats['vpn_users'] ?? 0), 'ci-user'],
    ['vpn_manager_stat_expired_subscriptions', (int)($stats['expired_subscriptions'] ?? 0), 'ci-close-circle'],
    ['vpn_manager_stat_traffic_exceeded', (int)($stats['traffic_exceeded'] ?? 0), 'ci-alert-triangle'],
    ['vpn_manager_stat_sync_errors', (int)($stats['sync_errors'] ?? 0), 'ci-alert-triangle'],
    ['vpn_manager_stat_traffic_month', Formatter::bytes((int)($stats['traffic_month'] ?? 0)), 'ci-activity'],
];

$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/create')) . '"><i class="ci-plus"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_quick_create_subscription')) . '</a>'
    . '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/servers/create')) . '"><i class="ci-server"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_quick_add_server')) . '</a>'
    . '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/plans/create')) . '"><i class="ci-layers"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_quick_create_plan')) . '</a>'
    . '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/logs')) . '"><i class="ci-list"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_quick_open_logs')) . '</a>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_dashboard_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="row g-3 mb-4">
        <?php foreach ($statCards as $card): ?>
            <div class="col-6 col-xl-3">
                <div class="border rounded-5 p-3 p-md-4 h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t($card[0])) ?></div>
                            <div class="h4 mb-0 mt-1"><?= htmlSC((string)$card[1]) ?></div>
                        </div>
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary" style="width:2.5rem;height:2.5rem;">
                            <i class="<?= htmlSC((string)$card[2]) ?>"></i>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="border rounded-5 p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_latest_subscriptions')) ?></h2>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/subscriptions') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_view_all')) ?></a>
        </div>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_user')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_plan')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_expires_at')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_servers')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_nodes')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $item): array {
                $traffic = Formatter::bytes((int)($item['traffic_used_bytes'] ?? 0)) . ' / ' . Formatter::bytes((int)($item['traffic_limit_bytes'] ?? 0));
                $userLabel = trim((string)($item['user_name'] ?? $item['user_email'] ?? ''));
                $planLabel = trim((string)($item['plan_name'] ?? ''));
                return [
                    'cells' => [
                        ['value' => '#' . (int)$item['id']],
                        ['value' => $userLabel !== '' ? $userLabel : FireballPluginVpnManager::t('vpn_manager_user_missing')],
                        ['value' => $planLabel !== '' ? $planLabel : FireballPluginVpnManager::t('vpn_manager_plan_missing')],
                        ['html' => vpnm_status_badge((string)($item['status'] ?? ''))],
                        ['value' => Formatter::dateTime((string)($item['expires_at'] ?? ''))],
                        ['value' => $traffic],
                        ['value' => (string)(int)($item['server_count'] ?? 0)],
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

    <div class="border rounded-5 p-3 p-md-4 mt-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_latest_events')) ?></h2>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/logs') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_view_all')) ?></a>
        </div>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_event')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_message')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_created_at')],
            ],
            'rows' => array_map(static function (array $event): array {
                return [
                    'cells' => [
                        ['value' => '#' . (int)$event['id']],
                        ['value' => (string)$event['event_type']],
                        ['html' => '<span class="text-break">' . htmlSC((string)$event['message']) . '</span>'],
                        ['value' => Formatter::dateTime((string)($event['created_at'] ?? ''))],
                    ],
                ];
            }, $events),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_logs'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
