<?php
use Fireball\VpnManager\Support\Formatter;

$plan = is_array($plan ?? null) ? $plan : null;
$inbounds = is_array($inbounds ?? null) ? $inbounds : [];
$selectedInboundIds = array_map('intval', is_array($selectedInboundIds ?? null) ? $selectedInboundIds : []);
$isEdit = $plan !== null;
$action = $isEdit ? base_href('/admin/plugins/vpn-manager/plans/edit/' . (int)$plan['id']) : base_href('/admin/plugins/vpn-manager/plans/create');
$trafficMode = (string)($plan['traffic_mode'] ?? 'shared');
$trafficBytes = (int)($plan['traffic_limit_bytes'] ?? 0);
$trafficValue = $trafficBytes > 0 ? Formatter::bytesToGb($trafficBytes) : '0';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? '',
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_name')) ?></label>
                <input class="form-control" type="text" name="name" required value="<?= htmlSC((string)($plan['name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_duration_days')) ?></label>
                <input class="form-control" type="number" min="1" step="1" name="duration_days" value="<?= (int)($plan['duration_days'] ?? 30) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_device_limit')) ?></label>
                <input class="form-control" type="number" min="1" step="1" name="device_limit" value="<?= (int)($plan['device_limit'] ?? 1) ?>">
            </div>
            <div class="col-12">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_description')) ?></label>
                <textarea class="form-control" name="description" rows="3"><?= htmlSC((string)($plan['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_traffic_limit')) ?></label>
                <input class="form-control" type="number" min="0" step="0.01" name="traffic_limit_value" value="<?= htmlSC($trafficValue) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_traffic_unit')) ?></label>
                <select class="form-select" name="traffic_unit">
                    <option value="mb">MB</option>
                    <option value="gb" selected>GB</option>
                    <option value="tb">TB</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_sort_order')) ?></label>
                <input class="form-control" type="number" min="0" step="1" name="sort_order" value="<?= (int)($plan['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check form-switch py-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="vpnPlanActive" <?= (int)($plan['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnPlanActive"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_active')) ?></label>
                </div>
            </div>
        </div>

        <div class="border rounded-4 p-3 mt-4">
            <h2 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_plan_traffic_mode')) ?></h2>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="traffic_mode" value="shared" id="vpnTrafficShared" <?= $trafficMode === 'shared' ? 'checked' : '' ?>>
                <label class="form-check-label" for="vpnTrafficShared"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_traffic_shared')) ?></label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="traffic_mode" value="per_node" id="vpnTrafficPerNode" <?= $trafficMode === 'per_node' ? 'checked' : '' ?>>
                <label class="form-check-label" for="vpnTrafficPerNode"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_traffic_per_node')) ?></label>
            </div>
        </div>

        <div class="border rounded-4 p-3 mt-4">
            <h2 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_plan_inbounds')) ?></h2>
            <?php if (empty($inbounds)): ?>
                <div class="alert alert-warning mb-0">
                    <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_warning_sync_inbounds_first')) ?>
                </div>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($inbounds as $inbound): ?>
                        <?php
                        $id = (int)$inbound['inbound_id'];
                        $serverLine = trim((string)($inbound['country'] ?? '') . ' ' . (string)($inbound['city'] ?? ''));
                        $remark = trim((string)($inbound['remark'] ?? ''));
                        $inboundTitle = trim((string)($inbound['name'] ?? 'Inbound') . ($remark !== '' && $remark !== (string)($inbound['name'] ?? '') ? ' / ' . $remark : ''));
                        ?>
                        <div class="col-md-6">
                            <div class="form-check border rounded-4 p-3 ps-5 h-100">
                                <input class="form-check-input" type="checkbox" name="inbound_ids[]" value="<?= $id ?>" id="vpnPlanInbound<?= $id ?>" <?= in_array($id, $selectedInboundIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-medium" for="vpnPlanInbound<?= $id ?>">
                                    <?= htmlSC((string)$inbound['server_name']) ?>
                                </label>
                                <div class="small text-body-secondary">
                                    <?= htmlSC($inboundTitle) ?> · <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_remote_id')) ?> #<?= htmlSC((string)$inbound['remote_inbound_id']) ?>
                                </div>
                                <?php if ($serverLine !== ''): ?>
                                    <div class="small text-body-secondary"><?= htmlSC($serverLine) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_save')) ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/plans') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_cancel')) ?></a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
