<?php

$users = is_array($users ?? null) ? $users : [];
$plans = is_array($plans ?? null) ? $plans : [];
$preselectedUserId = max(0, (int)($preselectedUserId ?? 0));
// FIREBALL_VPN_MANUAL_EXPIRY_V1
// FIREBALL_VPN_MANUAL_CUSTOMERS_V1
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_create_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/create')) ?>" method="post">
    <?= get_csrf_field() ?>

    <div class="alert alert-info rounded-4">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_local_first_note')) ?>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label d-block">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_owner_type')) ?>
            </label>
            <div class="d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input"
                           id="vpnV2OwnerRegistered"
                           type="radio"
                           name="owner_type"
                           value="registered"
                           checked>
                    <label class="form-check-label" for="vpnV2OwnerRegistered">
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_owner_registered')) ?>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input"
                           id="vpnV2OwnerManual"
                           type="radio"
                           name="owner_type"
                           value="manual">
                    <label class="form-check-label" for="vpnV2OwnerManual">
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_owner_manual')) ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="col-lg-6" data-vpn-owner-section="registered">
            <label class="form-label" for="vpnV2SubscriptionUser"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_user')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionUser" name="user_id" required>
                <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_select_user')) ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)$user['id'] === $preselectedUserId ? 'selected' : '' ?>>
                        #<?= (int)$user['id'] ?> · <?= htmlSC((string)$user['name']) ?> · <?= htmlSC((string)$user['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6 d-none" data-vpn-owner-section="manual">
            <label class="form-label" for="vpnV2ManualCustomerName">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_customer_name')) ?>
            </label>
            <input class="form-control"
                   id="vpnV2ManualCustomerName"
                   type="text"
                   name="manual_customer_name"
                   maxlength="190"
                   autocomplete="off"
                   placeholder="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_customer_name_placeholder')) ?>">
            <div class="form-text">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_customer_help')) ?>
            </div>
        </div>

        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionPlan"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_plan')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionPlan" name="plan_id" required>
                <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_select_plan')) ?></option>
                <?php require __DIR__ . '/partials/subscription-plan-options.php'; ?>
            </select>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionStartsAt"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_starts_at')) ?></label>
            <input class="form-control" id="vpnV2SubscriptionStartsAt" type="datetime-local" name="starts_at" required
                   value="<?= htmlSC((string)($defaultStartsAt ?? date('Y-m-d\\TH:i'))) ?>">
        </div>
        <?php $editingPeriod = false; require __DIR__ . '/partials/subscription-period.php'; ?>
    </div>

    <?php if ($plans === []): ?>
        <div class="alert alert-warning rounded-4 mt-4">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_warning_subscription_prerequisites')) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill text-wrap" type="submit" <?= $plans === [] ? 'disabled' : '' ?>>
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_create_and_provision')) ?>
        </button>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions')) ?>">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?>
        </a>
    </div>
</form>

<script>
(() => {
    const registeredRadio = document.getElementById('vpnV2OwnerRegistered');
    const manualRadio = document.getElementById('vpnV2OwnerManual');
    const userSelect = document.getElementById('vpnV2SubscriptionUser');
    const manualName = document.getElementById('vpnV2ManualCustomerName');

    const registeredSection = document.querySelector(
        '[data-vpn-owner-section="registered"]'
    );
    const manualSection = document.querySelector(
        '[data-vpn-owner-section="manual"]'
    );

    const syncOwner = () => {
        if (!registeredRadio || !manualRadio) {
            return;
        }

        const manual = manualRadio.checked;

        registeredSection?.classList.toggle('d-none', manual);
        manualSection?.classList.toggle('d-none', !manual);

        if (userSelect) {
            userSelect.disabled = manual;
            userSelect.required = !manual;
        }

        if (manualName) {
            manualName.disabled = !manual;
            manualName.required = manual;
        }
    };

    registeredRadio?.addEventListener('change', syncOwner);
    manualRadio?.addEventListener('change', syncOwner);

    syncOwner();
})();
</script>

<?= view()->renderPartial('admin/shell_close') ?>
