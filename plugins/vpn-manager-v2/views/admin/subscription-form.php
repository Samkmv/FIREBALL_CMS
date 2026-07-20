<?php

use Fireball\VpnManagerV2\Support\TrafficFormatter;

$users = is_array($users ?? null) ? $users : [];
$plans = is_array($plans ?? null) ? $plans : [];
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
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionUser"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_user')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionUser" name="user_id" required>
                <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_select_user')) ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>">
                        #<?= (int)$user['id'] ?> · <?= htmlSC((string)$user['name']) ?> · <?= htmlSC((string)$user['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="vpnV2SubscriptionPlan"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_plan')) ?></label>
            <select class="form-select" id="vpnV2SubscriptionPlan" name="plan_id" required>
                <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_select_plan')) ?></option>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?= (int)$plan['id'] ?>">
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
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_expiry_note')) ?></div>
        </div>
    </div>

    <?php if ($users === [] || $plans === []): ?>
        <div class="alert alert-warning rounded-4 mt-4">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_warning_subscription_prerequisites')) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill" type="submit" <?= $users === [] || $plans === [] ? 'disabled' : '' ?>>
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_create_and_provision')) ?>
        </button>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions')) ?>">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?>
        </a>
    </div>
</form>

<?= view()->renderPartial('admin/shell_close') ?>
