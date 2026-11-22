<?php
$presetEditor = !empty($presetEditor);
$resolver = new \Fireball\VpnManagerV2\Services\VpnFlowResolver();
$inboundsById = [];
foreach ($inbounds as &$inbound) {
    $inbound['allowed_flows'] = array_values(array_filter(
        $resolver->allowedFlows($inbound),
        static fn(?string $flow): bool => $flow !== null
    ));
    $inboundsById[(int)$inbound['id']] = $inbound;
}
unset($inbound);
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
                <label class="form-label" for="vpnNode-server-<?= htmlSC((string)$index) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_server')) ?></label>
                <select class="form-select" id="vpnNode-server-<?= htmlSC((string)$index) ?>" name="nodes[<?= htmlSC((string)$index) ?>][server_id]" data-vpn-v2-node-server required>
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
                <label class="form-label" for="vpnNode-inbound-<?= htmlSC((string)$index) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_inbound')) ?></label>
                <select class="form-select" id="vpnNode-inbound-<?= htmlSC((string)$index) ?>" name="nodes[<?= htmlSC((string)$index) ?>][inbound_id]" data-vpn-v2-node-inbound required>
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
                <label class="form-label" for="vpnNode-flow_override-<?= htmlSC((string)$index) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_flow_override')) ?></label>
                <select class="form-select" id="vpnNode-flow_override-<?= htmlSC((string)$index) ?>" name="nodes[<?= htmlSC((string)$index) ?>][flow_override]" data-vpn-v2-node-flow>
                    <option value="__auto__" <?= $flowValue === '__auto__' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_automatic')) ?></option>
                    <?php foreach ($allowedFlows as $flow): ?>
                        <option value="<?= htmlSC((string)$flow) ?>" <?= $flowValue === (string)$flow ? 'selected' : '' ?>><?= htmlSC((string)$flow) ?></option>
                    <?php endforeach; ?>
                    <option value="__none__" <?= $flowValue === '__none__' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none')) ?></option>
                </select>
            </div>
            <div class="col-8 col-lg-1">
                <label class="form-label" for="vpnNode-sort_order-<?= htmlSC((string)$index) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_sort_order')) ?></label>
                <input class="form-control" type="number" min="0" max="1000000" step="1"
                       id="vpnNode-sort_order-<?= htmlSC((string)$index) ?>" name="nodes[<?= htmlSC((string)$index) ?>][sort_order]" value="<?= (int)($node['sort_order'] ?? 0) ?>">
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
<fieldset class="fb-card p-3 mt-3" <?= !empty($selectedPresetId) ? 'hidden disabled' : '' ?>
         aria-labelledby="vpnPlanNodeTitle"
         data-vpn-v2-plan-nodes
         data-next-index="<?= count($selectedNodes) ?>"
         data-flow-auto-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_automatic')) ?>"
         data-flow-none-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none')) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h6 mb-1" id="vpnPlanNodeTitle"><?= htmlSC(FireballPluginVpnManagerV2::t($presetEditor ? 'vpn_manager_v2_tab_connections' : 'vpn_manager_v2_plan_nodes_title')) ?></h2>
            <p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t($presetEditor ? 'vpn_manager_v2_preset_nodes_help' : 'vpn_manager_v2_plan_nodes_help')) ?></p>
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

    <?php if (!$presetEditor): ?>
        <p class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_order_shared')) ?></p>
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
</fieldset>
