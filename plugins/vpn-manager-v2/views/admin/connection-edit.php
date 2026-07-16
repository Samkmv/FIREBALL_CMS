<?php

$connection = is_array($connection ?? null) ? $connection : [];
$id = (int)($connection['id'] ?? 0);
$allowedFlows = is_array($allowedFlows ?? null) ? $allowedFlows : [null];
$trafficInput = is_array($trafficInput ?? null) ? $trafficInput : ['value' => '0', 'unit' => 'gb'];
$currentFlow = trim((string)($connection['flow'] ?? ''));
?>

<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<form class="border rounded-5 p-3 p-md-4" method="post"
      action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id . '/edit')) ?>">
    <?= get_csrf_field() ?>

    <div class="alert alert-info rounded-4">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_edit_identity_note')) ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2ConnectionFlow"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_flow')) ?></label>
            <select class="form-select" id="vpnV2ConnectionFlow" name="flow">
                <option value="__none__" <?= $currentFlow === '' ? 'selected' : '' ?>>
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none')) ?>
                </option>
                <?php foreach ($allowedFlows as $flow): ?>
                    <?php if ($flow === null): continue; endif; ?>
                    <option value="<?= htmlSC((string)$flow) ?>" <?= $currentFlow === (string)$flow ? 'selected' : '' ?>>
                        <?= htmlSC((string)$flow) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2ConnectionTrafficLimit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_traffic_limit')) ?></label>
            <div class="input-group">
                <input class="form-control" id="vpnV2ConnectionTrafficLimit" type="number" name="traffic_limit_value"
                       min="0" step="0.01" value="<?= htmlSC((string)$trafficInput['value']) ?>" required>
                <select class="form-select" name="traffic_unit" style="max-width: 7rem">
                    <?php foreach (['mb', 'gb', 'tb'] as $unit): ?>
                        <option value="<?= $unit ?>" <?= (string)$trafficInput['unit'] === $unit ? 'selected' : '' ?>><?= strtoupper($unit) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill" type="submit">
            <i class="ci-save" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_save_and_sync')) ?>
        </button>
        <a class="btn btn-outline-secondary rounded-pill"
           href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id)) ?>">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?>
        </a>
    </div>
</form>

<?= view()->renderPartial('admin/shell_close') ?>
