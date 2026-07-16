<?php

use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Support\Permissions;

$plan = is_array($plan ?? null) ? $plan : null;
$servers = is_array($servers ?? null) ? $servers : [];
$inbounds = is_array($inbounds ?? null) ? $inbounds : [];
$selectedNodes = is_array($selectedNodes ?? null) ? $selectedNodes : [];
$editing = $plan !== null;
$affectedSubscriptions = max(0, (int)($affectedSubscriptions ?? 0));
$missingConnectionCount = max(0, (int)($missingConnectionCount ?? 0));
$latestReconciliation = is_array($latestReconciliation ?? null) ? $latestReconciliation : null;
$obsoleteConnectionCount = max(0, (int)($obsoleteConnectionCount ?? 0));
$obsoleteSubscriptionCount = max(0, (int)($obsoleteSubscriptionCount ?? 0));
$obsoleteTargets = is_array($obsoleteTargets ?? null) ? $obsoleteTargets : [];
$planId = (int)($plan['id'] ?? 0);
$action = $editing
    ? base_href('/admin/plugins/vpn-manager-v2/plans/edit/' . $planId)
    : base_href('/admin/plugins/vpn-manager-v2/plans/create');
$traffic = TrafficFormatter::inputParts(isset($plan['traffic_limit_bytes']) ? (int)$plan['traffic_limit_bytes'] : null);
$inboundsById = [];
foreach ($inbounds as $inbound) {
    $inboundsById[(int)$inbound['id']] = $inbound;
}
if ($selectedNodes === []) {
    $selectedNodes[] = [
        'server_id' => 0,
        'inbound_id' => 0,
        'flow_override' => null,
        'sort_order' => 0,
    ];
}

$renderNodeRow = static function (array $node, string|int $index) use ($servers, $inbounds, $inboundsById): string {
    $serverId = (int)($node['server_id'] ?? 0);
    $inboundId = (int)($node['inbound_id'] ?? 0);
    $selectedInbound = $inboundsById[$inboundId] ?? null;
    if (array_key_exists('flow_override', $node)) {
        $storedFlow = $node['flow_override'];
        $flowValue = $storedFlow === null ? '__auto__' : ((string)$storedFlow === '' ? '__none__' : (string)$storedFlow);
    } else {
        $flowValue = '__auto__';
    }
    $allowedFlows = is_array($selectedInbound['allowed_flows'] ?? null) ? $selectedInbound['allowed_flows'] : [];

    ob_start();
    ?>
    <div class="border rounded-4 p-3" data-vpn-v2-plan-node-row>
        <div class="row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_server')) ?></label>
                <select class="form-select" name="nodes[<?= htmlSC((string)$index) ?>][server_id]" data-vpn-v2-node-server required>
                    <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_select_server')) ?></option>
                    <?php foreach ($servers as $server): ?>
                        <?php $serverEnabled = !empty($server['is_enabled']); ?>
                        <option value="<?= (int)$server['id'] ?>"
                                data-eligible="<?= $serverEnabled ? '1' : '0' ?>"
                                <?= (int)$server['id'] === $serverId ? 'selected' : '' ?>
                                <?= !$serverEnabled && (int)$server['id'] !== $serverId ? 'disabled' : '' ?>>
                            #<?= (int)$server['id'] ?> · <?= htmlSC((string)$server['name']) ?> (<?= htmlSC((string)$server['code']) ?>)<?= !$serverEnabled ? ' — ' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_disabled_suffix')) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_inbound')) ?></label>
                <select class="form-select" name="nodes[<?= htmlSC((string)$index) ?>][inbound_id]" data-vpn-v2-node-inbound required>
                    <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_select_inbound')) ?></option>
                    <?php foreach ($inbounds as $inbound): ?>
                        <?php
                        $eligible = !empty($inbound['server_is_enabled'])
                            && !empty($inbound['is_enabled'])
                            && (string)$inbound['status'] === 'active';
                        $flowsJson = json_encode((array)($inbound['allowed_flows'] ?? []), JSON_UNESCAPED_SLASHES) ?: '[]';
                        ?>
                        <option value="<?= (int)$inbound['id'] ?>"
                                data-server-id="<?= (int)$inbound['server_id'] ?>"
                                data-eligible="<?= $eligible ? '1' : '0' ?>"
                                data-allowed-flows="<?= htmlSC($flowsJson) ?>"
                                <?= (int)$inbound['id'] === $inboundId ? 'selected' : '' ?>
                                <?= !$eligible && (int)$inbound['id'] !== $inboundId ? 'disabled' : '' ?>>
                            #<?= (int)$inbound['id'] ?> · <?= htmlSC((string)$inbound['name']) ?> · <?= htmlSC(strtoupper((string)$inbound['protocol'])) ?> / <?= htmlSC(strtoupper((string)($inbound['network'] ?: '—'))) ?> / <?= htmlSC(strtoupper((string)($inbound['security'] ?: 'none'))) ?><?= !$eligible ? ' — ' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_disabled_suffix')) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_flow_override')) ?></label>
                <select class="form-select" name="nodes[<?= htmlSC((string)$index) ?>][flow_override]" data-vpn-v2-node-flow>
                    <option value="__auto__" <?= $flowValue === '__auto__' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_automatic')) ?></option>
                    <?php foreach ($allowedFlows as $flow): ?>
                        <option value="<?= htmlSC((string)$flow) ?>" <?= $flowValue === (string)$flow ? 'selected' : '' ?>><?= htmlSC((string)$flow) ?></option>
                    <?php endforeach; ?>
                    <option value="__none__" <?= $flowValue === '__none__' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none')) ?></option>
                </select>
            </div>
            <div class="col-8 col-lg-1">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_sort_order')) ?></label>
                <input class="form-control" type="number" min="0" max="1000000" step="1"
                       name="nodes[<?= htmlSC((string)$index) ?>][sort_order]" value="<?= (int)($node['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-4 col-lg-1 d-flex justify-content-end">
                <button class="btn btn-outline-danger btn-icon rounded-circle" type="button"
                        data-vpn-v2-node-remove title="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_remove_node')) ?>"
                        aria-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_remove_node')) ?>">
                    <i class="ci-trash" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
    <?php

    return (string)ob_get_clean();
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? '',
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<?php if ($editing): ?>
    <div class="small text-body-secondary mb-3">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_id')) ?>:
        <span class="fw-semibold text-body">#<?= $planId ?></span>
    </div>
<?php endif; ?>

<form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
    <?= get_csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label" for="vpnV2PlanName"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_name')) ?></label>
            <input class="form-control" id="vpnV2PlanName" type="text" name="name" maxlength="255" required value="<?= htmlSC((string)($plan['name'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="vpnV2PlanDuration"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_duration_days')) ?></label>
            <input class="form-control" id="vpnV2PlanDuration" type="number" name="duration_days" min="1" max="36500" step="1" required value="<?= (int)($plan['duration_days'] ?? 30) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="vpnV2PlanDevices"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_device_limit')) ?></label>
            <input class="form-control" id="vpnV2PlanDevices" type="number" name="device_limit" min="1" max="100000" step="1" required value="<?= (int)($plan['device_limit'] ?? 1) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="vpnV2PlanDescription"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_description')) ?></label>
            <textarea class="form-control" id="vpnV2PlanDescription" name="description" rows="3" maxlength="12000"><?= htmlSC((string)($plan['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vpnV2PlanTraffic"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_traffic_limit')) ?></label>
            <input class="form-control" id="vpnV2PlanTraffic" type="number" name="traffic_limit_value" min="0" step="0.01" value="<?= htmlSC((string)$traffic['value']) ?>">
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_traffic_zero_unlimited')) ?></div>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="vpnV2PlanTrafficUnit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_traffic_unit')) ?></label>
            <select class="form-select" id="vpnV2PlanTrafficUnit" name="traffic_unit">
                <?php foreach (['mb' => 'MB', 'gb' => 'GB', 'tb' => 'TB'] as $unit => $label): ?>
                    <option value="<?= $unit ?>" <?= $traffic['unit'] === $unit ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch border rounded-4 p-3 ps-5 w-100">
                <input class="form-check-input" id="vpnV2PlanActive" type="checkbox" name="is_active" value="1" <?= (int)($plan['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label fw-medium" for="vpnV2PlanActive"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_plan_active')) ?></label>
            </div>
        </div>
    </div>

    <section class="border rounded-4 p-3 mt-4"
             data-vpn-v2-plan-nodes
             data-next-index="<?= count($selectedNodes) ?>"
             data-flow-auto-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_automatic')) ?>"
             data-flow-none-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none')) ?>">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h6 mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_nodes_title')) ?></h2>
                <p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_nodes_help')) ?></p>
            </div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" data-vpn-v2-node-add>
                <i class="ci-plus" aria-hidden="true"></i>
                <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_node')) ?></span>
            </button>
        </div>

        <?php if ($servers === [] || $inbounds === []): ?>
            <div class="alert alert-warning rounded-4">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_warning_plan_topology_empty')) ?>
            </div>
        <?php endif; ?>

        <div class="d-grid gap-3" data-vpn-v2-plan-node-list>
            <?php foreach ($selectedNodes as $index => $node): ?>
                <?= $renderNodeRow((array)$node, $index) ?>
            <?php endforeach; ?>
        </div>

        <template data-vpn-v2-plan-node-template>
            <?= $renderNodeRow([
                'server_id' => 0,
                'inbound_id' => 0,
                'flow_override' => null,
                'sort_order' => count($selectedNodes),
            ], '__INDEX__') ?>
        </template>
    </section>

    <?php if ($editing): ?>
        <div class="form-check form-switch border rounded-4 p-3 ps-5 mt-4">
            <input class="form-check-input" id="vpnV2ReconcileExisting" type="checkbox"
                   name="reconcile_existing" value="1" checked>
            <label class="form-check-label fw-medium" for="vpnV2ReconcileExisting">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_reconcile_existing_label')) ?>
            </label>
            <div class="form-text">
                <?= htmlSC(sprintf(
                    FireballPluginVpnManagerV2::t('vpn_manager_v2_reconcile_affected_count'),
                    $affectedSubscriptions
                )) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_save')) ?></button>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans')) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?></a>
    </div>
</form>

<?php if ($editing): ?>
    <section class="border rounded-5 p-3 p-md-4 mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h2 class="h6 mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_reconciliation_title')) ?></h2>
                <p class="small text-body-secondary mb-0">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_reconciliation_help')) ?>
                </p>
                <div class="d-flex flex-wrap gap-3 small text-body-secondary mt-2">
                    <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_affected_subscriptions')) ?>:
                        <strong class="text-body"><?= $affectedSubscriptions ?></strong></span>
                    <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_missing_connections')) ?>:
                        <strong class="text-body"><?= $missingConnectionCount ?></strong></span>
                    <span>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_obsolete_connections')) ?>:
                    <strong class="text-body"><?= $obsoleteConnectionCount ?></strong></span>
                </div>
                <?php if ($obsoleteTargets !== []): ?>
                    <div class="small text-body-secondary mt-2">
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_obsolete_targets')) ?>:
                        <?php foreach ($obsoleteTargets as $target): ?>
                            <span class="badge rounded-pill text-bg-light border ms-1">
                                #<?= (int)$target['server_id'] ?> <?= htmlSC((string)$target['server_name']) ?> /
                                #<?= (int)$target['inbound_id'] ?> <?= htmlSC((string)$target['inbound_name']) ?> ·
                                <?= (int)$target['connection_count'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($latestReconciliation !== null): ?>
                <div class="small text-body-secondary text-md-end">
                    <div><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_last_reconciliation')) ?>:
                        <?= htmlSC((string)($latestReconciliation['finished_at'] ?? $latestReconciliation['started_at'] ?? '—')) ?></div>
                    <div><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_operation_status')) ?>:
                        <span class="fw-semibold text-body"><?= htmlSC(FireballPluginVpnManagerV2::t(
                            'vpn_manager_v2_reconcile_status_' . (string)$latestReconciliation['status']
                        )) ?></span></div>
                    <div><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_processed')) ?>:
                        <?= (int)$latestReconciliation['processed_count'] ?> / <?= (int)$latestReconciliation['total_count'] ?> ·
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_successful')) ?>: <?= (int)$latestReconciliation['success_count'] ?> ·
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_errors')) ?>: <?= (int)$latestReconciliation['failure_count'] ?> ·
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_skipped')) ?>: <?= (int)$latestReconciliation['skipped_count'] ?>
                    </div>
                    <div><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_started_at')) ?>:
                        <?= htmlSC((string)($latestReconciliation['started_at'] ?? '—')) ?> ·
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_finished_at')) ?>:
                        <?= htmlSC((string)($latestReconciliation['finished_at'] ?? '—')) ?></div>
                    <?php if ((int)$latestReconciliation['failure_count'] > 0): ?>
                        <a href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections')) ?>">
                            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_open_sync_errors')) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if (Permissions::allows(Permissions::RECONCILE)): ?>
            <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/' . $planId . '/preview')) ?>">
                <?= get_csrf_field() ?>
                <button class="btn btn-outline-secondary rounded-pill" type="submit">
                    <i class="ci-search" aria-hidden="true"></i>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_preview_reconciliation')) ?>
                </button>
            </form>
            <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/' . $planId . '/reconcile')) ?>">
                <?= get_csrf_field() ?>
                <button class="btn btn-dark rounded-pill" type="submit">
                    <i class="ci-refresh-cw" aria-hidden="true"></i>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_reconcile')) ?>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($obsoleteConnectionCount > 0 && Permissions::allows(Permissions::DELETE_CONNECTIONS)): ?>
            <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/' . $planId . '/remove-obsolete')) ?>"
                  data-admin-delete-form
                  data-delete-message="<?= htmlSC(sprintf(
                      FireballPluginVpnManagerV2::t('vpn_manager_v2_confirm_remove_obsolete'),
                      $obsoleteSubscriptionCount,
                      $obsoleteConnectionCount
                  )) ?>"
                  data-delete-item="#<?= $planId ?>"
                  data-delete-confirm-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_remove_obsolete')) ?>">
                <?= get_csrf_field() ?>
                <button class="btn btn-outline-danger rounded-pill" type="submit">
                    <i class="ci-trash" aria-hidden="true"></i>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_remove_obsolete')) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
