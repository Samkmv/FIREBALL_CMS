<?php
use Fireball\VpnManager\Support\Formatter;

$subscription = is_array($subscription ?? null) ? $subscription : [];
$subscriptionId = (int)($subscription['id'] ?? 0);
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => ($title ?? FireballPluginVpnManager::t('vpn_manager_subscription_extend_title')) . ' #' . $subscriptionId,
    'subtitle' => $subtitle ?? '',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions/' . $subscriptionId)) . '">' . htmlSC(FireballPluginVpnManager::t('vpn_manager_back_to_list')) . '</a>',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/plugins/vpn-manager/subscriptions/extend') ?>" method="post">
        <?= get_csrf_field() ?>
        <input type="hidden" name="id" value="<?= $subscriptionId ?>">

        <div class="mb-4">
            <h2 class="h5 mb-1"><?= htmlSC((string)($subscription['plan_name'] ?? FireballPluginVpnManager::t('vpn_manager_subscription_card_title'))) ?></h2>
            <div class="text-body-secondary">
                <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_expires_at')) ?>:
                <?= htmlSC(Formatter::dateTime((string)($subscription['expires_at'] ?? ''))) ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_extend_days')) ?></label>
                <input class="form-control" type="number" min="1" max="3650" step="1" name="days" value="7" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch py-2">
                    <input class="form-check-input" type="checkbox" name="reset_traffic" value="1" id="vpnExtendResetTraffic">
                    <label class="form-check-label fw-medium" for="vpnExtendResetTraffic"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_reset_traffic')) ?></label>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch py-2">
                    <input class="form-check-input" type="checkbox" name="enable_clients" value="1" id="vpnExtendEnableClients" checked>
                    <label class="form-check-label fw-medium" for="vpnExtendEnableClients"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_enable_clients')) ?></label>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_action_extend')) ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/subscriptions/' . $subscriptionId) ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_cancel')) ?></a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
