<?php
$editingPeriod = !empty($editingPeriod);
$periodExpiry = trim((string)($subscription['expires_at'] ?? ''));
$periodInput = $periodExpiry !== '' && strtotime($periodExpiry) !== false ? date('Y-m-d\TH:i', strtotime($periodExpiry)) : '';
$periodBase = max(time(), strtotime((string)($subscription['starts_at'] ?? '')) ?: 0);
?>
<div class="col-lg-6">
    <label class="form-label" for="vpnV2ExpiryMode"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_period')) ?></label>
    <select class="form-select" id="vpnV2ExpiryMode" name="expiry_mode" required>
        <?php if ($editingPeriod): ?>
            <option value="preserve" selected><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_period_preserve')) ?></option>
        <?php endif; ?>
        <option value="plan" <?= !$editingPeriod ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_period_plan')) ?></option>
        <option value="manual"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_expiry_override')) ?></option>
        <option value="lifetime"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_lifetime')) ?></option>
    </select>
    <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t($editingPeriod ? 'vpn_manager_v2_period_edit_help' : 'vpn_manager_v2_calculated_expires_help')) ?></div>
</div>
<div class="col-lg-6 d-none" data-vpn-manual-expiry-section>
    <label class="form-label" for="vpnV2SubscriptionExpiresAt"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_expires_at')) ?></label>
    <input class="form-control" id="vpnV2SubscriptionExpiresAt" type="datetime-local" name="expires_at"
           value="<?= htmlSC($periodInput) ?>" disabled>
</div>
<div class="col-lg-6" data-vpn-expiry-preview-section>
    <label class="form-label" for="vpnV2CalculatedExpiresAt"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_expires_at')) ?></label>
    <input class="form-control" id="vpnV2CalculatedExpiresAt" type="text" readonly value="—">
</div>
<script>
(() => {
    const init = () => {
        const mode = document.getElementById('vpnV2ExpiryMode');
        const plan = document.getElementById('vpnV2SubscriptionPlan');
        const expiresAt = document.getElementById('vpnV2SubscriptionExpiresAt');
        const startsAt = document.getElementById('vpnV2SubscriptionStartsAt');
        const preview = document.getElementById('vpnV2CalculatedExpiresAt');
        const manualSection = document.querySelector('[data-vpn-manual-expiry-section]');
        const previewSection = document.querySelector('[data-vpn-expiry-preview-section]');
        const traffic = document.getElementById('vpnV2SubscriptionTrafficLimit');
        const trafficUnit = traffic?.closest('.input-group')?.querySelector('select');
        const originalTraffic = {value: traffic?.value, unit: trafficUnit?.value};
        const originalPlan = <?= (int)($subscription['plan_id'] ?? 0) ?>;
        const originalExpiry = <?= json_encode($periodInput) ?>;
        const termBase = <?= json_encode(date('Y-m-d\TH:i', $periodBase)) ?>;
        const lifetimeLabel = <?= json_encode(FireballPluginVpnManagerV2::t('vpn_manager_v2_lifetime_short'), JSON_UNESCAPED_UNICODE) ?>;
        const editing = <?= $editingPeriod ? 'true' : 'false' ?>;
        const parseDate = value => value ? new Date(value) : null;
        const toInput = date => {
            const pad = value => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };
        const planExpiry = () => {
            const base = parseDate(editing ? termBase : startsAt?.value);
            const days = Number(plan.selectedOptions[0]?.dataset.durationDays || 0);
            if (!base || Number.isNaN(base.getTime()) || days <= 0) return null;
            base.setDate(base.getDate() + days);
            return base;
        };
        const render = () => {
            const manual = mode.value === 'manual';
            manualSection.classList.toggle('d-none', !manual);
            previewSection.classList.toggle('d-none', manual);
            expiresAt.disabled = !manual;
            expiresAt.required = manual;
            if (manual) {
                if (!expiresAt.value) {
                    const expiry = planExpiry();
                    expiresAt.value = originalExpiry || (expiry ? toInput(expiry) : '');
                }
            } else {
                const value = mode.value === 'preserve' ? parseDate(originalExpiry) : planExpiry();
                preview.value = mode.value === 'lifetime' || (mode.value === 'preserve' && !originalExpiry)
                    ? lifetimeLabel : value && !Number.isNaN(value.getTime()) ? value.toLocaleString() : '—';
            }
        };
        plan.addEventListener('change', () => {
            const changed = editing && Number(plan.value) !== originalPlan;
            if (changed && mode.value === 'preserve') mode.value = 'plan';
            if (traffic && trafficUnit) {
                const option = plan.selectedOptions[0];
                traffic.value = changed ? option.dataset.trafficValue : originalTraffic.value;
                trafficUnit.value = changed ? option.dataset.trafficUnit : originalTraffic.unit;
                traffic.readOnly = changed;
                trafficUnit.disabled = changed;
            }
            render();
        });
        mode.addEventListener('change', render);
        startsAt?.addEventListener('input', render);
        render();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
    else init();
})();
</script>
