<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Support\AdminTableState;
use Fireball\VpnManagerV2\Support\AdminActionDropdown;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$accessRequests = is_array($accessRequests ?? null) ? $accessRequests : [];
$returnQuery = AdminTableState::sanitize($returnQuery ?? '');
$addUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/create');
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC($addUrl) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_subscription')) . '</span></a>';

$rows = [];
$mobileCards = [];
foreach ($subscriptions as $subscription) {
    $id = (int)$subscription['id'];
    $showUrl = AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/' . $id, $returnQuery);
    $editUrl = AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/edit/' . $id, $returnQuery);
    $suspendUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $id . '/suspend');
    $deleteUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $id . '/delete');
    $deleteRetry = (string)$subscription['status'] === 'delete_failed';
    $deleteLabel = FireballPluginVpnManagerV2::t($deleteRetry
        ? 'vpn_manager_v2_action_retry_delete'
        : 'vpn_manager_v2_action_delete_forever');
    $deleteConfirm = sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_delete_subscription'), $id);
    $badge = ProvisioningStatus::badge((string)$subscription['status']);
    $nodeCount = (int)($subscription['node_count'] ?? 0);
    $activeCount = (int)($subscription['active_node_count'] ?? 0);
    $nodeText = $activeCount . ' / ' . $nodeCount;
    $traffic = TrafficFormatter::limit(isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : null);
    $showAction = [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'),
        'href' => $showUrl,
        'icon' => 'ci-eye',
    ], [
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'),
        'href' => $editUrl,
        'icon' => 'ci-edit-2',
    ]];
    if ((string)$subscription['status'] === 'active') {
        $showAction[] = [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_suspend'),
            'type' => 'form',
            'action' => $suspendUrl,
            'icon' => 'ci-pause-circle',
            'hidden' => ['return_query' => $returnQuery],
        ];
    }
    if ((string)$subscription['status'] !== 'deleting') {
        $showAction[] = [
            'label' => $deleteLabel,
            'type' => 'form',
            'action' => $deleteUrl,
            'icon' => $deleteRetry ? 'ci-refresh-cw' : 'ci-trash',
            'class' => 'text-danger',
            'hidden' => ['return_query' => $returnQuery],
            'form_attributes' => [
                'data-admin-delete-form' => true,
                'data-delete-message' => $deleteConfirm,
                'data-delete-item' => '#' . $id,
                'data-delete-confirm-label' => $deleteLabel,
            ],
        ];
    }
    $desktopAction = AdminActionDropdown::render($showAction);

    $rows[] = ['cells' => [
        ['value' => '#' . $id],
        ['html' => '<span class="fw-medium">' . htmlSC((string)$subscription['user_name'])
            . '</span><div class="small text-body-secondary">' . htmlSC((string)$subscription['user_login'])
            . ' · ' . htmlSC((string)$subscription['user_email']) . '</div>'],
        ['html' => '<span class="fw-medium">' . htmlSC((string)$subscription['plan_name'])
            . '</span><div class="small text-body-secondary">#' . (int)$subscription['plan_id'] . '</div>'],
        ['html' => $badge],
        ['value' => (string)$subscription['starts_at']],
        ['value' => (string)($subscription['expires_at'] ?: '—')],
        ['html' => htmlSC($nodeText) . '<div class="small text-body-secondary">'
            . htmlSC($traffic) . ' · ' . (int)$subscription['device_limit'] . '</div>'],
        ['html' => '<div class="text-end">' . $desktopAction . '</div>'],
    ]];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$subscription['plan_name'],
        'icon' => 'ci-link',
        'status' => [['html' => $badge]],
        'actions' => $showAction,
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user'), 'value' => (string)$subscription['user_name']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_starts_at'), 'value' => (string)$subscription['starts_at']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_expires_at'), 'value' => (string)($subscription['expires_at'] ?: '—')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes'), 'value' => $nodeText],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_traffic_limit'), 'value' => $traffic],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => $actions,
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<?php if ($accessRequests !== []): ?>
    <section class="border rounded-5 p-3 p-md-4 mb-4" aria-labelledby="vpnV2AccessRequestsTitle">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1" id="vpnV2AccessRequestsTitle"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_access_requests_title')) ?></h2>
                <p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_access_requests_help')) ?></p>
            </div>
            <span class="badge rounded-pill text-bg-warning"><?= count($accessRequests) ?></span>
        </div>
        <div class="vstack gap-2">
            <?php foreach ($accessRequests as $accessRequest): ?>
                <div class="bg-body-tertiary rounded-4 p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold"><?= htmlSC((string)($accessRequest['user_name'] ?? '')) ?></div>
                        <div class="small text-body-secondary">
                            <?= htmlSC((string)($accessRequest['user_login'] ?? '')) ?> ·
                            <?= htmlSC((string)($accessRequest['user_email'] ?? '')) ?> ·
                            <?= htmlSC((string)($accessRequest['requested_at'] ?? '')) ?>
                        </div>
                    </div>
                    <a class="btn btn-sm btn-dark rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/create?user_id=' . (int)$accessRequest['user_id'] . '&request_id=' . (int)$accessRequest['id'])) ?>">
                        <i class="ci-plus me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_create_subscription')) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="border rounded-5 p-3 p-md-4">
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_starts_at')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_expires_at')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_subscriptions'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
