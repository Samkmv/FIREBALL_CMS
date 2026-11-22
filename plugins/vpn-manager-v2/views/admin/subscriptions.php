<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Support\AdminTableState;
use Fireball\VpnManagerV2\Support\AdminActionDropdown;
use Fireball\VpnManagerV2\Support\SubscriptionListFilter;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$accessRequests = is_array($accessRequests ?? null) ? $accessRequests : [];
$subscriptionsTotal = max(0, (int)($subscriptionsTotal ?? count($subscriptions)));
$searchQuery = trim((string)($searchQuery ?? ''));
$statusFilter = ($statusFilter ?? '') === 'online' ? 'online' : SubscriptionListFilter::normalize($statusFilter ?? '');
$unavailablePresencePanels = (int)($unavailablePresencePanels ?? 0);
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$attentionCounts = ['expired' => (int)($statusCounts['expired'] ?? 0),
    'inactive' => SubscriptionListFilter::inactiveCount($statusCounts)];
$pagination = $pagination ?? null;
$returnQuery = AdminTableState::sanitize($returnQuery ?? '');
$listUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions');
$addUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/create');
$actions = '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="'
    . htmlSC($addUrl) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_subscription')) . '</span></a>';

$rows = [];
$mobileCards = [];
foreach ($subscriptions as $subscription) {
    $clientName = trim((string)($subscription['client_display_name'] ?? '')) ?: (string)$subscription['user_name'];
    $id = (int)$subscription['id'];
    $manualCustomer = (int)($subscription['user_id'] ?? 0) <= 0;
    $userMeta = $manualCustomer
        ? FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_customer_label')
        : trim(
            (string)($subscription['user_login'] ?? '')
            . ' · '
            . (string)($subscription['user_email'] ?? '')
        );
    $showUrl = AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/' . $id, $returnQuery);
    $editUrl = AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/edit/' . $id, $returnQuery);
    $suspendUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $id . '/suspend');
    $deleteUrl = base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $id . '/delete');
    $deleteRetry = in_array((string)$subscription['status'], ['delete_failed', 'pending_remote_delete'], true);
    $deleteLabel = FireballPluginVpnManagerV2::t($deleteRetry
        ? 'vpn_manager_v2_action_retry_delete'
        : 'vpn_manager_v2_action_delete_forever');
    $deleteConfirm = sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_delete_subscription'), $id);
    $displayStatus = ProvisioningStatus::subscriptionStatus($subscription);
    $badge = ProvisioningStatus::badge($displayStatus);
    if ($statusFilter === 'online') {
        $badge .= ' <span class="badge rounded-pill text-bg-success">'
            . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_presence_online')) . '</span>';
    }
    $externalBadge = empty($subscription['has_external_sources']) ? ''
        : '<span class="badge rounded-pill text-bg-info">'
            . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_has_external_sources')) . '</span>';

    $expiresDisplay = trim((string)($subscription['expires_at'] ?? ''))
        ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_lifetime_short');
    $nodeCount = (int)($subscription['node_count'] ?? 0);
    $activeCount = (int)($subscription['active_node_count'] ?? 0);
    $nodeText = $activeCount . ' / ' . $nodeCount;
    $showAction = [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'),
        'href' => $showUrl,
        'icon' => 'ci-eye',
    ], [
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'),
        'href' => $editUrl,
        'icon' => 'ci-edit-2',
    ]];
    if (ProvisioningStatus::deletionStarted((string)$subscription['status'])) {
        $showAction = array_slice($showAction, 0, 1);
    }
    if (in_array($displayStatus, ['active', 'partial_sync'], true)) {
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

    $clientLink = '<a class="fw-semibold text-break" href="' . htmlSC($showUrl) . '">' . htmlSC($clientName) . '</a>';
    $rows[] = ['cells' => [
        ['html' => $clientLink . '<div class="small text-body-secondary text-break">#' . $id . ' · ' . htmlSC($userMeta) . '</div>'],
        ['html' => '<span class="fw-medium text-break">' . htmlSC((string)$subscription['plan_name']) . '</span>'
            . ($externalBadge === '' ? '' : '<div class="mt-1">' . $externalBadge . '</div>')],
        ['html' => '<span class="d-inline-flex flex-wrap gap-1">' . $badge . '</span>'],
        ['value' => $expiresDisplay],
        ['value' => $nodeText],
        ['html' => '<div class="text-end">' . $desktopAction . '</div>'],
    ]];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => ['html' => $clientLink],
        'actions' => $showAction,
        'status' => [['html' => '<span class="fb-vpn-mobile-status d-flex flex-wrap align-items-center justify-content-between gap-1 w-100">'
            . '<span>' . $externalBadge . '</span><span class="d-inline-flex flex-wrap gap-1 ms-auto">' . $badge . '</span></span>']],
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan'), 'value' => (string)$subscription['plan_name']],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_expires_at'), 'value' => $expiresDisplay],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes'), 'value' => $nodeText],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_title'),
    'actions' => $actions,
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<section class="mb-3" aria-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_search_button')) ?>">
    <form method="get" action="<?= htmlSC($listUrl) ?>" class="row g-2 align-items-center"
          data-vpn-v2-live-search data-search-failed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_live_search_failed')) ?>">
        <div class="col-12 col-lg">
            <label class="visually-hidden" for="vpnV2SubscriptionQuery"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_search_placeholder')) ?></label>
            <div class="input-group">
                <span class="input-group-text bg-transparent"><i class="ci-search" aria-hidden="true"></i></span>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    id="vpnV2SubscriptionQuery"
                    maxlength="120"
                    value="<?= htmlSC($searchQuery) ?>"
                    placeholder="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_search_placeholder')) ?>"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="col col-lg-3">
            <label class="visually-hidden" for="vpnV2SubscriptionStatusFilter"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionStatusFilter" name="status">
                <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_filter_all')) ?></option>
                <option value="online" <?= $statusFilter === 'online' ? 'selected' : '' ?>>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_presence_online')) ?>
                </option>
                <?php foreach (SubscriptionListFilter::STATUSES as $filterStatus): ?>
                    <option value="<?= htmlSC($filterStatus) ?>" <?= $statusFilter === $filterStatus ? 'selected' : '' ?>>
                        <?= htmlSC($filterStatus === 'inactive'
                            ? FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_filter_inactive')
                            : ProvisioningStatus::label($filterStatus)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_search_button')) ?>
            </button>
        </div>
        <div class="col-auto" data-vpn-v2-search-clear <?= $searchQuery === '' && $statusFilter === '' ? 'hidden' : '' ?>>
            <a class="btn btn-outline-secondary" href="<?= htmlSC($listUrl) ?>">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_search_clear')) ?>
            </a>
        </div>
        <div class="col-12 col-lg-auto ms-lg-auto">
            <span class="small text-body-secondary" data-vpn-v2-search-count role="status">
                <?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_search_result'), $subscriptionsTotal)) ?>
            </span>
        </div>
    </form>
</section>

<?php if (array_sum($attentionCounts) > 0): ?>
    <div class="d-flex flex-wrap gap-2 mb-3" data-vpn-subscription-notices>
        <?php foreach ($attentionCounts as $noticeStatus => $noticeCount): ?>
            <?php if ($noticeCount <= 0) { continue; } ?>
            <a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2"
               href="<?= htmlSC($listUrl . '?status=' . $noticeStatus) ?>"
               data-vpn-subscription-status="<?= htmlSC($noticeStatus) ?>"
               <?= $statusFilter === $noticeStatus ? 'aria-current="page"' : '' ?>>
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_notice_' . $noticeStatus)) ?>
                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><?= $noticeCount ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($accessRequests !== []): ?>
    <details class="fb-card p-3 mb-3">
        <summary class="fw-semibold">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_access_requests_title')) ?>
            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><?= count($accessRequests) ?></span>
        </summary>
        <div class="vstack gap-2 mt-3">
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
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-dark rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/create?user_id=' . (int)$accessRequest['user_id'] . '&request_id=' . (int)$accessRequest['id'])) ?>">
                            <i class="ci-plus me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_create_subscription')) ?>
                        </a>
                        <form action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/access-requests/' . (int)$accessRequest['id'] . '/dismiss')) ?>" method="post" class="m-0">
                            <?= get_csrf_field() ?>
                            <input type="hidden" name="return_query" value="<?= htmlSC($returnQuery) ?>">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit">
                                <i class="ci-close me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_dismiss')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </details>
<?php endif; ?>

<div data-vpn-v2-search-results>
<?php if ($unavailablePresencePanels > 0): ?>
    <p class="alert alert-warning"><?= htmlSC(sprintf(
        FireballPluginVpnManagerV2::t('vpn_manager_v2_presence_unavailable'), $unavailablePresencePanels
    )) ?></p>
<?php endif; ?>

<div class="fb-vpn-subscriptions-list">
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_expires_at')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_nodes')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $rows,
        'table_class' => 'table-sm',
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_subscriptions'),
    ]) ?>
</div>

<?php if ($pagination && (int)($pagination['total_pages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <?= $pagination ?>
    </div>
<?php endif; ?>

</div>

<?= view()->renderPartial('admin/shell_close') ?>
