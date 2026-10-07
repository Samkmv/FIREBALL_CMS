<?php

$subscription = is_array($subscription ?? null) ? $subscription : [];
$id = (int)($subscription['id'] ?? 0);
$trafficInput = is_array($trafficInput ?? null) ? $trafficInput : ['value' => '0', 'unit' => 'gb'];
$plans = is_array($plans ?? null) ? $plans : [];
$storedStatus = (string)($subscription['status'] ?? 'active');
$formStatus = in_array($storedStatus, ['active', 'suspended'], true) ? $storedStatus : 'active';
$returnQuery = \Fireball\VpnManagerV2\Support\AdminTableState::sanitize($returnQuery ?? '');
?>

<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<?php require __DIR__ . '/partials/subscription-client-name.php'; ?>

<form class="border rounded-5 p-3 p-md-4" method="post"
      action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/edit/' . $id)) ?>">
    <?= get_csrf_field() ?>
    <input type="hidden" name="return_query" value="<?= htmlSC($returnQuery) ?>">

    <div class="alert alert-info rounded-4">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_edit_sync_note')) ?>
        <?php if (\Fireball\VpnManagerV2\Support\ProvisioningStatus::subscriptionStatus($subscription) === 'expired'): ?>
            <div class="mt-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_renewal_note')) ?></div>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="vpnV2SubscriptionPlan"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_plan')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionPlan" name="plan_id" required>
                <?php $selectedPlanId = (int)$subscription['plan_id']; require __DIR__ . '/partials/subscription-plan-options.php'; ?>
            </select>
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_plan_change_help')) ?></div>
        </div>
        <?php $editingPeriod = true; require __DIR__ . '/partials/subscription-period.php'; ?>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionStatus"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_status')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionStatus" name="status" required>
                <?php foreach (['active', 'suspended', 'expired'] as $status): ?>
                    <option value="<?= htmlSC($status) ?>" <?= $formStatus === $status ? 'selected' : '' ?>>
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_provisioning_status_' . $status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionTrafficLimit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_traffic_limit')) ?></label>
            <div class="input-group">
                <input class="form-control" id="vpnV2SubscriptionTrafficLimit" type="number" name="traffic_limit_value"
                       min="0" step="0.01" value="<?= htmlSC((string)$trafficInput['value']) ?>" required>
                <select class="form-select" name="traffic_unit" style="max-width: 7rem">
                    <?php foreach (['mb', 'gb', 'tb'] as $unit): ?>
                        <option value="<?= $unit ?>" <?= (string)$trafficInput['unit'] === $unit ? 'selected' : '' ?>><?= strtoupper($unit) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_traffic_zero_unlimited')) ?></div>
        </div>
        <div class="col-12">
            <label class="form-label" for="vpnV2SubscriptionComment"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_internal_comment')) ?></label>
            <textarea class="form-control" id="vpnV2SubscriptionComment" name="internal_comment" rows="4"
                      maxlength="12000"><?= htmlSC((string)($subscription['internal_comment'] ?? '')) ?></textarea>
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_internal_comment_help')) ?></div>
        </div>
    </div>

    <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center justify-content-center gap-2 text-wrap" type="submit">
            <i class="ci-save flex-shrink-0" aria-hidden="true"></i> <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_save_and_sync')) ?></span>
        </button>
        <a class="btn btn-outline-secondary rounded-pill"
           href="<?= htmlSC(\Fireball\VpnManagerV2\Support\AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/' . $id, $returnQuery)) ?>">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?>
        </a>
    </div>
</form>
<?= view()->renderPartial('admin/shell_close') ?>
