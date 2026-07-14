<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

$subscription = is_array($subscription ?? null) ? $subscription : [];
$nodes = is_array($nodes ?? null) ? $nodes : [];
$subscriptionId = (int)($subscription['id'] ?? 0);
$subscriptionUrl = trim((string)($subscriptionUrl ?? ''));
$subscriptionQr = (string)($subscriptionQr ?? '');
$rows = [];
$mobileCards = [];
foreach ($nodes as $node) {
    $nodeId = (int)$node['id'];
    $showUrl = base_href('/admin/plugins/vpn-manager-v2/connections/' . $nodeId);
    $badge = ProvisioningStatus::badge((string)$node['status']);
    $flow = trim((string)($node['flow'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none');
    $actionItems = [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'),
        'href' => $showUrl,
        'icon' => 'ci-eye',
    ]];
    $desktop = '<a class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" href="' . htmlSC($showUrl)
        . '" title="' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view')) . '"><i class="ci-eye"></i></a>';
    if (ProvisioningStatus::canRetry((string)$node['status'])) {
        $retryUrl = base_href('/admin/plugins/vpn-manager-v2/connections/' . $nodeId . '/retry');
        $actionItems[] = [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_creation'),
            'type' => 'form',
            'action' => $retryUrl,
            'icon' => 'ci-refresh-cw',
        ];
        $desktop .= '<form method="post" action="' . htmlSC($retryUrl) . '">' . get_csrf_field()
            . '<button class="btn btn-sm btn-outline-warning btn-icon rounded-circle" type="submit" title="'
            . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_creation'))
            . '"><i class="ci-refresh-cw"></i></button></form>';
    }

    $rows[] = ['cells' => [
        ['value' => '#' . $nodeId],
        ['html' => htmlSC((string)$node['server_name']) . '<div class="small text-body-secondary">#' . (int)$node['server_id'] . '</div>'],
        ['html' => htmlSC((string)$node['inbound_name']) . '<div class="small text-body-secondary">remote #' . htmlSC((string)$node['remote_inbound_id']) . '</div>'],
        ['value' => strtoupper((string)$node['protocol'])],
        ['value' => strtoupper((string)($node['network'] ?: '—'))],
        ['value' => strtoupper((string)($node['security'] ?: 'none'))],
        ['value' => $flow],
        ['html' => $badge . (!empty($node['last_error']) ? '<div class="small text-danger mt-1">' . htmlSC((string)$node['last_error']) . '</div>' : '')],
        ['html' => '<div class="d-flex justify-content-end gap-1">' . $desktop . '</div>'],
    ]];
    $mobileCards[] = [
        'id' => (string)$nodeId,
        'title' => (string)$node['server_name'] . ' → ' . (string)$node['inbound_name'],
        'icon' => 'ci-share-2',
        'status' => [['html' => $badge]],
        'actions' => $actionItems,
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol'), 'value' => strtoupper((string)$node['protocol'])],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_network'), 'value' => strtoupper((string)($node['network'] ?: '—'))],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_security'), 'value' => strtoupper((string)($node['security'] ?: 'none'))],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_flow'), 'value' => $flow],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions')) ?>">
        <i class="ci-arrow-left" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_back_to_subscriptions')) ?>
    </a>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_id')) ?></span> <strong>#<?= $subscriptionId ?></strong></div>
        <?= ProvisioningStatus::badge((string)($subscription['status'] ?? '')) ?>
    </div>
    <dl class="row mb-0 g-3">
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$subscription['user_id'] ?> · <?= htmlSC((string)$subscription['user_name']) ?> · <?= htmlSC((string)$subscription['user_email']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$subscription['plan_id'] ?> · <?= htmlSC((string)$subscription['plan_name']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_period')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC((string)$subscription['starts_at']) ?> — <?= htmlSC((string)($subscription['expires_at'] ?: '—')) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_limits')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC(TrafficFormatter::limit(isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : null)) ?> · <?= (int)$subscription['device_limit'] ?> IP</dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_token_preview')) ?></dt>
        <dd class="col-sm-9 mb-0"><code><?= htmlSC((string)$subscription['token_preview']) ?></code></dd>
        <?php if (!empty($subscription['last_error'])): ?>
            <dt class="col-sm-3 text-danger"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_error')) ?></dt>
            <dd class="col-sm-9 mb-0 text-danger"><?= htmlSC((string)$subscription['last_error']) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_access_title')) ?></h2>
    <?php if ($subscriptionUrl !== ''): ?>
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <label class="form-label" for="vpnV2SubscriptionUrl"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_public_url')) ?></label>
                <input class="form-control font-monospace" id="vpnV2SubscriptionUrl" type="url" readonly value="<?= htmlSC($subscriptionUrl) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_url_help')) ?></div>
            </div>
            <div class="col-lg-5 text-lg-center">
                <?= $subscriptionQr ?>
                <div class="small text-body-secondary mt-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_qr_help')) ?></div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_access_pending')) ?></div>
    <?php endif; ?>
</div>

<div class="border rounded-5 p-3 p-md-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_nodes_title')) ?></h2>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_inbound')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_network')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_security')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_flow')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_connections'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
