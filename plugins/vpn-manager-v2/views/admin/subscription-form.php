<?php

use Fireball\VpnManagerV2\Support\TrafficFormatter;

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
                <?php foreach ($plans as $plan): ?>
                    <option value="<?= (int)$plan['id'] ?>"
                            data-duration-days="<?= (int)$plan['duration_days'] ?>">
                        #<?= (int)$plan['id'] ?> · <?= htmlSC((string)$plan['name']) ?> ·
                        <?= (int)$plan['duration_days'] ?> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_days')) ?> ·
                        <?= htmlSC(TrafficFormatter::limit(isset($plan['traffic_limit_bytes']) ? (int)$plan['traffic_limit_bytes'] : null)) ?> ·
                        <?= (int)$plan['device_limit'] ?> IP · <?= htmlSC(sprintf(
                            FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_connection_count'),
                            (int)$plan['node_count']
                        )) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionStartsAt"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_starts_at')) ?></label>
            <input class="form-control" id="vpnV2SubscriptionStartsAt" type="datetime-local" name="starts_at" required
                   value="<?= htmlSC((string)($defaultStartsAt ?? date('Y-m-d\\TH:i'))) ?>">
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2PlanDurationPreview">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_duration_label')) ?>
            </label>
            <input class="form-control"
                   id="vpnV2PlanDurationPreview"
                   type="text"
                   value="—"
                   readonly>
            <div class="form-text">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_duration_help')) ?>
            </div>
        </div>

        <div class="col-lg-6">
            <label class="form-label" for="vpnV2CalculatedExpiresAt">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_calculated_expires_at')) ?>
            </label>
            <input class="form-control"
                   id="vpnV2CalculatedExpiresAt"
                   type="text"
                   value="—"
                   readonly>
            <div class="form-text">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_calculated_expires_help')) ?>
            </div>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input"
                       id="vpnV2ManualExpiryOverride"
                       type="checkbox"
                       value="1">
                <label class="form-check-label" for="vpnV2ManualExpiryOverride">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_expiry_override')) ?>
                </label>
            </div>
        </div>

        <div class="col-lg-6 d-none" data-vpn-manual-expiry-section>
            <label class="form-label" for="vpnV2SubscriptionExpiresAt">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_expires_at')) ?>
            </label>
            <input class="form-control"
                   id="vpnV2SubscriptionExpiresAt"
                   type="datetime-local"
                   name="expires_at"
                   disabled>
            <div class="form-text">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_expiry_note')) ?>
            </div>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" id="vpnV2SubscriptionLifetime" type="checkbox" name="lifetime" value="1">
                <label class="form-check-label" for="vpnV2SubscriptionLifetime">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_lifetime')) ?>
                </label>
            </div>
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_lifetime_help')) ?></div>
        </div>
    </div>

    <?php if ($plans === []): ?>
        <div class="alert alert-warning rounded-4 mt-4">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_warning_subscription_prerequisites')) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill" type="submit" <?= $plans === [] ? 'disabled' : '' ?>>
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_create_and_provision')) ?>
        </button>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions')) ?>">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?>
        </a>
    </div>
</form>

<script>
(() => {
    const lifetime = document.getElementById('vpnV2SubscriptionLifetime');
    const expiresAt = document.getElementById('vpnV2SubscriptionExpiresAt');
    const manualExpiry = document.getElementById('vpnV2ManualExpiryOverride');
    const manualExpirySection = document.querySelector(
        '[data-vpn-manual-expiry-section]'
    );

    const plan = document.getElementById('vpnV2SubscriptionPlan');
    const startsAt = document.getElementById('vpnV2SubscriptionStartsAt');
    const durationPreview = document.getElementById('vpnV2PlanDurationPreview');
    const calculatedExpiresAt = document.getElementById('vpnV2CalculatedExpiresAt');

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

    const daysLabel = <?= json_encode(
        FireballPluginVpnManagerV2::t('vpn_manager_v2_days'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    const lifetimeLabel = <?= json_encode(
        FireballPluginVpnManagerV2::t('vpn_manager_v2_lifetime_short'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const selectedDurationDays = () => {
        if (!plan) {
            return 0;
        }

        const option = plan.options[plan.selectedIndex];
        return Math.max(
            0,
            parseInt(option?.dataset?.durationDays || '0', 10) || 0
        );
    };

    const parseLocalDate = (value) => {
        if (!value) {
            return null;
        }

        const match = value.match(
            /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/
        );

        if (!match) {
            return null;
        }

        const date = new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
            Number(match[4]),
            Number(match[5]),
            0,
            0
        );

        return Number.isNaN(date.getTime()) ? null : date;
    };

    const toInputValue = (date) => {
        const pad = (value) => String(value).padStart(2, '0');

        return [
            date.getFullYear(),
            '-',
            pad(date.getMonth() + 1),
            '-',
            pad(date.getDate()),
            'T',
            pad(date.getHours()),
            ':',
            pad(date.getMinutes())
        ].join('');
    };

    const calculatedExpiry = () => {
        const start = parseLocalDate(startsAt?.value || '');
        const days = selectedDurationDays();

        if (!start || days <= 0) {
            return null;
        }

        const result = new Date(start.getTime());
        // Calendar-day arithmetic preserves the selected local clock time
        // even when DST changes inside the subscription period.
        result.setDate(result.getDate() + days);

        return result;
    };

    const renderTariffExpiry = () => {
        const days = selectedDurationDays();

        if (durationPreview) {
            durationPreview.value = days > 0
                ? `${days} ${daysLabel}`
                : '—';
        }

        if (!calculatedExpiresAt) {
            return;
        }

        if (lifetime?.checked) {
            calculatedExpiresAt.value = lifetimeLabel;
            return;
        }

        const expiry = calculatedExpiry();
        calculatedExpiresAt.value = expiry
            ? expiry.toLocaleString()
            : '—';
    };

    const syncManualExpiry = (seedValue = false) => {
        if (!manualExpiry || !expiresAt) {
            return;
        }

        const enabled = manualExpiry.checked && !lifetime?.checked;

        manualExpirySection?.classList.toggle('d-none', !enabled);
        expiresAt.disabled = !enabled;
        expiresAt.required = enabled;

        if (enabled && seedValue && !expiresAt.value) {
            const expiry = calculatedExpiry();
            if (expiry) {
                expiresAt.value = toInputValue(expiry);
            }
        }

        if (!enabled) {
            // When this input is disabled it is not submitted at all.
            // Backend then calculates expiration from plan.duration_days.
            expiresAt.value = '';
        }
    };

    const syncLifetime = () => {
        if (!lifetime) {
            return;
        }

        if (lifetime.checked && manualExpiry) {
            manualExpiry.checked = false;
            manualExpiry.disabled = true;
        } else if (manualExpiry) {
            manualExpiry.disabled = false;
        }

        syncManualExpiry(false);
        renderTariffExpiry();
    };

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

    plan?.addEventListener('change', () => {
        renderTariffExpiry();
    });

    startsAt?.addEventListener('change', () => {
        renderTariffExpiry();
    });

    startsAt?.addEventListener('input', () => {
        renderTariffExpiry();
    });

    manualExpiry?.addEventListener('change', () => {
        syncManualExpiry(true);
    });

    lifetime?.addEventListener('change', syncLifetime);
    registeredRadio?.addEventListener('change', syncOwner);
    manualRadio?.addEventListener('change', syncOwner);

    syncOwner();
    syncLifetime();
    renderTariffExpiry();
})();
</script>

<?= view()->renderPartial('admin/shell_close') ?>
