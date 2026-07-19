<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Support\AdminTableState;
use Fireball\VpnManagerV2\Support\Permissions;

$subscription = is_array($subscription ?? null) ? $subscription : [];
$nodes = is_array($nodes ?? null) ? $nodes : [];
$subscriptionId = (int)($subscription['id'] ?? 0);
$subscriptionUrl = trim((string)($subscriptionUrl ?? ''));
$subscriptionQr = (string)($subscriptionQr ?? '');
$returnQuery = AdminTableState::sanitize($returnQuery ?? '');
$reconciliationSummary = is_array($reconciliationSummary ?? null) ? $reconciliationSummary : [
    'plan_count' => 0,
    'created_count' => 0,
    'missing_count' => 0,
    'obsolete_count' => 0,
    'matches' => true,
    'checked_at' => null,
];
$deleteRetry = (string)($subscription['status'] ?? '') === 'delete_failed';
$deleteLabel = FireballPluginVpnManagerV2::t($deleteRetry
    ? 'vpn_manager_v2_action_retry_delete'
    : 'vpn_manager_v2_action_delete_forever');
$deleteConfirm = sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_delete_subscription'), $subscriptionId);
$rows = [];
$mobileCards = [];
$reorderableNodes = array_values(array_filter($nodes, static fn(array $node): bool =>
    !in_array((string)($node['status'] ?? ''), ['deleted', 'deleting'], true)
));
foreach ($nodes as $node) {
    $nodeId = (int)$node['id'];
    $showUrl = base_href('/admin/plugins/vpn-manager-v2/connections/' . $nodeId);
    $editUrl = base_href('/admin/plugins/vpn-manager-v2/connections/' . $nodeId . '/edit');
    $badge = ProvisioningStatus::badge((string)$node['status']);
    $flow = trim((string)($node['flow'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none');
    $actionItems = [[
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view'),
        'href' => $showUrl,
        'icon' => 'ci-eye',
    ], [
        'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'),
        'href' => $editUrl,
        'icon' => 'ci-edit-2',
    ]];
    $desktop = '<a class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" href="' . htmlSC($showUrl)
        . '" title="' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_view')) . '"><i class="ci-eye"></i></a>';
    $desktop .= '<a class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" href="' . htmlSC($editUrl)
        . '" title="' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'))
        . '"><i class="ci-edit-2"></i></a>';
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
        ['value' => (string)(count($rows) + 1)],
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
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_field_sort_order'), 'value' => (string)(count($mobileCards) + 1)],
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
    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= htmlSC(AdminTableState::append('/admin/plugins/vpn-manager-v2/subscriptions', $returnQuery)) ?>">
        <i class="ci-arrow-left" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_back_to_subscriptions')) ?>
    </a>
    <a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= htmlSC(AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/edit/' . $subscriptionId, $returnQuery)) ?>">
        <i class="ci-edit-2" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit')) ?>
    </a>
    <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/sync/subscription/' . $subscriptionId)) ?>" data-vpn-v2-async-operation>
        <?= get_csrf_field() ?>
        <button class="btn btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
            <i class="ci-refresh-cw" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_sync_subscription')) ?>
        </button>
    </form>
    <?php if ((string)($subscription['status'] ?? '') === 'active'): ?>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/suspend')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="return_query" value="<?= htmlSC($returnQuery) ?>">
            <button class="btn btn-outline-warning rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                <i class="ci-pause-circle" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_suspend')) ?>
            </button>
        </form>
    <?php endif; ?>
    <?php if ((string)($subscription['status'] ?? '') !== 'deleting'): ?>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/delete')) ?>"
              data-admin-delete-form data-delete-message="<?= htmlSC($deleteConfirm) ?>"
              data-delete-item="#<?= $subscriptionId ?>" data-delete-confirm-label="<?= htmlSC($deleteLabel) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="return_query" value="<?= htmlSC($returnQuery) ?>">
            <button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                <i class="<?= $deleteRetry ? 'ci-refresh-cw' : 'ci-trash' ?>" aria-hidden="true"></i> <?= htmlSC($deleteLabel) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div data-vpn-v2-operation-alert aria-live="polite"></div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_reconciliation_title')) ?></h2>
            <div class="d-flex flex-wrap gap-3 small">
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_by_plan')) ?>:
                    <strong><?= (int)$reconciliationSummary['plan_count'] ?></strong></span>
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_actually_created')) ?>:
                    <strong><?= (int)$reconciliationSummary['created_count'] ?></strong></span>
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_missing_connections')) ?>:
                    <strong><?= (int)$reconciliationSummary['missing_count'] ?></strong></span>
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_obsolete_connections')) ?>:
                    <strong><?= (int)$reconciliationSummary['obsolete_count'] ?></strong></span>
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_last_check')) ?>:
                    <strong><?= htmlSC((string)($reconciliationSummary['checked_at'] ?? '—')) ?></strong></span>
            </div>
        </div>
        <?php if ((int)$reconciliationSummary['missing_count'] > 0
            && Permissions::allows(Permissions::CREATE_CONNECTIONS)
            && Permissions::allows(Permissions::RECONCILE)): ?>
            <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/create-missing')) ?>">
                <?= get_csrf_field() ?>
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                    <i class="ci-plus" aria-hidden="true"></i>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_create_missing')) ?>
                </button>
            </form>
        <?php elseif (!empty($reconciliationSummary['matches'])): ?>
            <span class="badge rounded-pill text-bg-success">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_reconciliation_matches')) ?>
            </span>
        <?php else: ?>
            <span class="badge rounded-pill text-bg-warning">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_reconciliation_has_differences')) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_id')) ?></span> <strong>#<?= $subscriptionId ?></strong></div>
        <?= ProvisioningStatus::badge((string)($subscription['status'] ?? '')) ?>
    </div>
    <dl class="row mb-0 g-3">
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$subscription['user_id'] ?> · <?= htmlSC((string)$subscription['user_name']) ?> · <?= htmlSC((string)$subscription['user_login']) ?> · <?= htmlSC((string)$subscription['user_email']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$subscription['plan_id'] ?> · <?= htmlSC((string)$subscription['plan_name']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_period')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC((string)$subscription['starts_at']) ?> — <?= htmlSC((string)($subscription['expires_at'] ?: '—')) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_limits')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC(TrafficFormatter::limit(isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : null)) ?> · <?= (int)$subscription['device_limit'] ?> IP</dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_token_preview')) ?></dt>
        <dd class="col-sm-9 mb-0"><code><?= htmlSC((string)$subscription['token_preview']) ?></code></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_revision')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= (int)$subscription['revision'] ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_internal_comment')) ?></dt>
        <dd class="col-sm-9 mb-0 text-break"><?= nl2br(htmlSC((string)($subscription['internal_comment'] ?? '—'))) ?></dd>
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

<?php if (count($reorderableNodes) > 1 && Permissions::allows(Permissions::MANAGE_SUBSCRIPTIONS)): ?>
    <form class="border rounded-5 p-3 p-md-4 mb-4"
          method="post"
          action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/connections/order')) ?>"
          data-vpn-v2-connection-order>
        <?= get_csrf_field() ?>
        <input type="hidden" name="return_query" value="<?= htmlSC($returnQuery) ?>">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_order_title')) ?></h2>
                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_order_help')) ?></div>
            </div>
            <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                <i class="ci-save" aria-hidden="true"></i>
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_save_connection_order')) ?>
            </button>
        </div>
        <div class="list-group list-group-flush border rounded-4 overflow-hidden" data-vpn-v2-connection-order-list>
            <?php foreach ($reorderableNodes as $node): ?>
                <?php $nodeId = (int)$node['id']; ?>
                <div class="list-group-item d-flex align-items-center gap-3 py-3"
                     draggable="true" data-vpn-v2-connection-order-item>
                    <input type="hidden" name="connection_order[]" value="<?= $nodeId ?>">
                    <i class="ci-menu text-body-tertiary" aria-hidden="true"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">
                            <?= htmlSC((string)$node['server_name']) ?> → <?= htmlSC((string)$node['inbound_name']) ?>
                        </div>
                        <div class="small text-body-secondary">
                            #<?= $nodeId ?> · <?= htmlSC(strtoupper((string)$node['protocol'])) ?>
                        </div>
                    </div>
                    <div class="btn-group" role="group" aria-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_order_actions')) ?>">
                        <button class="btn btn-sm btn-outline-secondary btn-icon" type="button"
                                data-vpn-v2-order-move="up"
                                title="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_move_connection_up')) ?>"
                                aria-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_move_connection_up')) ?>">
                            <i class="ci-chevron-up" aria-hidden="true"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-icon" type="button"
                                data-vpn-v2-order-move="down"
                                title="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_move_connection_down')) ?>"
                                aria-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_move_connection_down')) ?>">
                            <i class="ci-chevron-down" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>
<?php endif; ?>

<div class="border rounded-5 p-3 p-md-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_nodes_title')) ?></h2>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_field_sort_order')],
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
