<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Services\QrCodeService;
use Fireball\VpnManager\Services\SubscriptionLinkService;
use Fireball\VpnManager\Support\Formatter;

$subscription = is_array($subscription ?? null) ? $subscription : [];
$nodes = is_array($nodes ?? null) ? $nodes : [];
$notifications = is_array($notifications ?? null) ? $notifications : [];
$events = is_array($events ?? null) ? $events : [];
$diagnostics = is_array($diagnostics ?? null) ? $diagnostics : [];
$subscriptionId = (int)($subscription['id'] ?? 0);
$linkService = new SubscriptionLinkService();
$subscriptionUrl = $linkService->subscriptionUrl($subscription, 'plain');
$subscriptionStandardUrl = $linkService->subscriptionUrl($subscription, 'base64');
$toggleStatus = (string)($subscription['status'] ?? '') === 'active' ? 'suspended' : 'active';
$userLabel = trim((string)($subscription['user_name'] ?? $subscription['user_email'] ?? ''));
$planLabel = trim((string)($subscription['plan_name'] ?? ''));
$actions = '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions')) . '">' . htmlSC(FireballPluginVpnManager::t('vpn_manager_back_to_list')) . '</a>'
    . '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/extend/' . $subscriptionId)) . '"><i class="ci-calendar-plus"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_extend')) . '</a>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/status')) . '" method="post">' . get_csrf_field() . '<input type="hidden" name="id" value="' . $subscriptionId . '"><input type="hidden" name="status" value="' . htmlSC($toggleStatus) . '"><button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-power"></i>' . htmlSC($toggleStatus === 'active' ? FireballPluginVpnManager::t('vpn_manager_action_enable') : FireballPluginVpnManager::t('vpn_manager_action_disable')) . '</button></form>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/provision')) . '" method="post">' . get_csrf_field() . '<input type="hidden" name="id" value="' . $subscriptionId . '"><button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-refresh-cw"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_recreate_clients')) . '</button></form>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/status')) . '" method="post">' . get_csrf_field() . '<input type="hidden" name="id" value="' . $subscriptionId . '"><input type="hidden" name="status" value="deleted"><button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-trash"></i>' . htmlSC(FireballPluginVpnManager::t('vpn_manager_action_delete')) . '</button></form>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => ($title ?? FireballPluginVpnManager::t('vpn_manager_subscription_card_title')) . ' #' . $subscriptionId,
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_summary')) ?></h2>
                        <div class="text-body-secondary"><?= htmlSC($userLabel !== '' ? $userLabel : FireballPluginVpnManager::t('vpn_manager_user_missing')) ?></div>
                    </div>
                    <?= vpnm_status_badge((string)($subscription['status'] ?? '')) ?>
                </div>
                <dl class="row mb-0">
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_plan')) ?></dt>
                    <dd class="col-7"><?= htmlSC($planLabel !== '' ? $planLabel : FireballPluginVpnManager::t('vpn_manager_plan_missing')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_start')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::dateTime((string)($subscription['starts_at'] ?? ''))) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_expires_at')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::dateTime((string)($subscription['expires_at'] ?? ''))) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic_limit')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::bytes((int)($subscription['traffic_limit_bytes'] ?? 0))) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic_used')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::bytes((int)($subscription['traffic_used_bytes'] ?? 0))) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_devices')) ?></dt>
                    <dd class="col-7"><?= (int)($subscription['device_limit'] ?? 0) ?></dd>
                </dl>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <form action="<?= base_href('/admin/plugins/vpn-manager/subscriptions/manual-reminder') ?>" method="post">
                        <?= get_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $subscriptionId ?>">
                        <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                            <i class="ci-bell"></i><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_manual_reminder')) ?>
                        </button>
                    </form>
                    <form action="<?= base_href('/admin/plugins/vpn-manager/subscriptions/reset-traffic') ?>" method="post">
                        <?= get_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $subscriptionId ?>">
                        <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                            <i class="ci-activity"></i><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_reset_traffic')) ?>
                        </button>
                    </form>
                </div>
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
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_diagnostics')) ?></h2>
        <dl class="row mb-0">
            <dt class="col-md-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_diag_subscription_created')) ?></dt>
            <dd class="col-md-7"><?= htmlSC(!empty($diagnostics['subscription_created']) ? FireballPluginVpnManager::t('vpn_manager_yes') : FireballPluginVpnManager::t('vpn_manager_no')) ?></dd>
            <dt class="col-md-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_diag_plan_found')) ?></dt>
            <dd class="col-md-7"><?= htmlSC(!empty($diagnostics['plan_found']) ? FireballPluginVpnManager::t('vpn_manager_yes') : FireballPluginVpnManager::t('vpn_manager_no')) ?></dd>
            <dt class="col-md-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_diag_active_plan_items')) ?></dt>
            <dd class="col-md-7"><?= (int)($diagnostics['active_plan_items'] ?? 0) ?></dd>
            <dt class="col-md-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_diag_nodes_created')) ?></dt>
            <dd class="col-md-7"><?= (int)($diagnostics['nodes_created'] ?? 0) ?></dd>
            <dt class="col-md-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_diag_clients_created')) ?></dt>
            <dd class="col-md-7"><?= (int)($diagnostics['clients_created'] ?? 0) ?></dd>
            <dt class="col-md-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_diag_last_error')) ?></dt>
            <dd class="col-md-7 text-break"><?= htmlSC(trim((string)($diagnostics['last_error'] ?? '')) !== '' ? (string)$diagnostics['last_error'] : FireballPluginVpnManager::t('vpn_manager_none')) ?></dd>
        </dl>
    </div>

    <div class="border rounded-5 p-3 p-md-4 mt-4" id="subscription-link">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_copy_subscription_link')) ?></h2>
                <p class="text-body-secondary mb-0">
                    <?= htmlSC($subscriptionUrl !== '' ? FireballPluginVpnManager::t('vpn_manager_subscription_link_ready') : FireballPluginVpnManager::t('vpn_manager_subscription_link_unavailable')) ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" <?= $subscriptionUrl === '' ? 'disabled' : '' ?> data-vpn-copy-value="<?= htmlSC($subscriptionUrl) ?>" data-vpn-copy-done="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_copied')) ?>">
                    <i class="ci-copy"></i><span data-vpn-copy-label><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_copy_subscription_link')) ?></span>
                </button>
                <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2 <?= $subscriptionUrl === '' ? 'disabled' : '' ?>" href="#subscription-link" <?= $subscriptionUrl === '' ? 'aria-disabled="true"' : '' ?>>
                    <i class="ci-qr-code"></i><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_show_qr')) ?>
                </a>
            </div>
        </div>
        <?php if ($subscriptionUrl !== ''): ?>
            <?php if ($linkService->isLocalUrl($subscriptionUrl)): ?>
                <div class="alert alert-warning rounded-4">
                    <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_local_url_warning')) ?>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_link_happ_label')) ?></label>
                <div class="input-group">
                    <input class="form-control" type="text" value="<?= htmlSC($subscriptionUrl) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-vpn-copy-value="<?= htmlSC($subscriptionUrl) ?>" data-vpn-copy-done="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_copied')) ?>">
                        <i class="ci-copy"></i><span class="visually-hidden" data-vpn-copy-label><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_copy_subscription_link')) ?></span>
                    </button>
                </div>
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_link_happ_help')) ?></div>
            </div>
            <?php if ($subscriptionStandardUrl !== '' && $subscriptionStandardUrl !== $subscriptionUrl): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_link_standard_label')) ?></label>
                    <div class="input-group">
                        <input class="form-control" type="text" value="<?= htmlSC($subscriptionStandardUrl) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-vpn-copy-value="<?= htmlSC($subscriptionStandardUrl) ?>" data-vpn-copy-done="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_copied')) ?>">
                            <i class="ci-copy"></i><span class="visually-hidden" data-vpn-copy-label><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_copy_subscription_link')) ?></span>
                        </button>
                    </div>
                    <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_link_standard_help')) ?></div>
                </div>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-2 fw-semibold">
                        <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_qr_happ_label')) ?>
                    </div>
                    <?= (new QrCodeService())->render($subscriptionUrl) ?>
                </div>
                <?php if ($subscriptionStandardUrl !== '' && $subscriptionStandardUrl !== $subscriptionUrl): ?>
                    <div class="col-md-6">
                        <div class="mb-2 fw-semibold">
                            <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_qr_standard_label')) ?>
                        </div>
                        <?= (new QrCodeService())->render($subscriptionStandardUrl) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="border rounded-5 p-3 p-md-4 mt-4">
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_latest_events')) ?></h2>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_event')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_message')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_created_at')],
            ],
            'rows' => array_map(static function (array $event): array {
                $context = json_decode((string)($event['context_json'] ?? ''), true);
                $error = is_array($context) ? trim((string)($context['error_message'] ?? '')) : '';
                $message = '<div class="text-break">' . htmlSC((string)($event['message'] ?? '')) . '</div>';
                if ($error !== '') {
                    $message .= '<div class="small text-danger text-break mt-1">' . htmlSC($error) . '</div>';
                }

                return [
                    'cells' => [
                        ['value' => (string)($event['id'] ?? '')],
                        ['value' => (string)($event['event_type'] ?? '')],
                        ['html' => $message],
                        ['value' => Formatter::dateTime((string)($event['created_at'] ?? ''))],
                    ],
                ];
            }, $events),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_logs'),
        ]) ?>
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
                        ['value' => Formatter::dateTime((string)($notification['scheduled_for'] ?? ''))],
                        ['value' => (string)$notification['channel']],
                        ['html' => vpnm_status_badge((string)$notification['status'])],
                        ['html' => htmlSC(Formatter::dateTime((string)($notification['sent_at'] ?? ''))) . (!empty($notification['error_message']) ? '<div class="small text-danger text-break">' . htmlSC((string)$notification['error_message']) . '</div>' : '')],
                    ],
                ];
            }, $notifications),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_notifications'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
