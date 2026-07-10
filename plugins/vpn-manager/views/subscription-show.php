<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$subscription = is_array($subscription ?? null) ? $subscription : [];
$nodes = is_array($nodes ?? null) ? $nodes : [];
$notifications = is_array($notifications ?? null) ? $notifications : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => ($title ?? FireballPluginVpnManager::t('vpn_manager_subscription_card_title')) . ' #' . (int)($subscription['id'] ?? 0),
    'subtitle' => $subtitle ?? '',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions')) . '">' . htmlSC(FireballPluginVpnManager::t('vpn_manager_back_to_list')) . '</a>',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_summary')) ?></h2>
                        <div class="text-body-secondary"><?= htmlSC((string)($subscription['user_name'] ?? $subscription['user_email'] ?? '-')) ?></div>
                    </div>
                    <?= vpnm_status_badge((string)($subscription['status'] ?? '')) ?>
                </div>
                <dl class="row mb-0">
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_plan')) ?></dt>
                    <dd class="col-7"><?= htmlSC((string)($subscription['plan_name'] ?? '-')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_expires_at')) ?></dt>
                    <dd class="col-7"><?= htmlSC((string)($subscription['expires_at'] ?? '-')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic_limit')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::bytes((int)($subscription['traffic_limit_bytes'] ?? 0))) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic_used')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::bytes((int)($subscription['traffic_used_bytes'] ?? 0))) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_devices')) ?></dt>
                    <dd class="col-7"><?= (int)($subscription['device_limit'] ?? 0) ?></dd>
                </dl>
                <form action="<?= base_href('/admin/plugins/vpn-manager/subscriptions/manual-reminder') ?>" method="post" class="mt-4">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)($subscription['id'] ?? 0) ?>">
                    <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                        <i class="ci-bell"></i><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_manual_reminder')) ?>
                    </button>
                </form>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_nodes')) ?></h2>
                <?= view()->renderPartial('admin/partials/table', [
                    'columns' => [
                        ['label' => FireballPluginVpnManager::t('vpn_manager_col_server')],
                        ['label' => FireballPluginVpnManager::t('vpn_manager_col_inbound')],
                        ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                        ['label' => FireballPluginVpnManager::t('vpn_manager_col_uuid')],
                        ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic')],
                    ],
                    'rows' => array_map(static function (array $node): array {
                        return [
                            'cells' => [
                                ['value' => (string)($node['server_name'] ?? '-')],
                                ['value' => (string)($node['inbound_name'] ?? '-')],
                                ['html' => vpnm_status_badge((string)($node['status'] ?? ''))],
                                ['value' => '••••••••'],
                                ['value' => Formatter::bytes((int)($node['traffic_used_bytes'] ?? 0)) . ' / ' . Formatter::bytes((int)($node['traffic_limit_bytes'] ?? 0))],
                            ],
                        ];
                    }, $nodes),
                    'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_connections'),
                ]) ?>
            </div>
        </div>
    </div>

    <div class="border rounded-5 p-3 p-md-4 mt-4">
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_notifications')) ?></h2>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_type')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_scheduled_for')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_channel')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_sent_at')],
            ],
            'rows' => array_map(static function (array $notification): array {
                return [
                    'cells' => [
                        ['value' => (string)$notification['type']],
                        ['value' => (string)($notification['scheduled_for'] ?? '-')],
                        ['value' => (string)$notification['channel']],
                        ['html' => vpnm_status_badge((string)$notification['status'])],
                        ['html' => htmlSC((string)($notification['sent_at'] ?? '-')) . (!empty($notification['error_message']) ? '<div class="small text-danger text-break">' . htmlSC((string)$notification['error_message']) . '</div>' : '')],
                    ],
                ];
            }, $notifications),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_notifications'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
