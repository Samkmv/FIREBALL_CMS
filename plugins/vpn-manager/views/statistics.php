<?php
use Fireball\VpnManager\Support\Formatter;

$stats = is_array($stats ?? null) ? $stats : [];
$items = [
    'vpn_manager_stat_active_subscriptions' => (int)($stats['active_subscriptions'] ?? 0),
    'vpn_manager_stat_expires_3_days' => (int)($stats['expires_3_days'] ?? 0),
    'vpn_manager_stat_expires_today' => (int)($stats['expires_today'] ?? 0),
    'vpn_manager_stat_expired_subscriptions' => (int)($stats['expired_subscriptions'] ?? 0),
    'vpn_manager_stat_servers_online' => (int)($stats['servers_online'] ?? 0),
    'vpn_manager_stat_servers_error' => (int)($stats['servers_error'] ?? 0),
    'vpn_manager_stat_traffic_used' => Formatter::bytes((int)($stats['traffic_used'] ?? 0)),
    'vpn_manager_stat_sync_errors' => (int)($stats['sync_errors'] ?? 0),
];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_statistics_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="row g-3">
        <?php foreach ($items as $label => $value): ?>
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-5 p-3 p-md-4 h-100">
                    <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t($label)) ?></div>
                    <div class="h4 mb-0 mt-1"><?= htmlSC((string)$value) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
