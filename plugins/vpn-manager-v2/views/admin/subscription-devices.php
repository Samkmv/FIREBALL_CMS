<?php

use Fireball\VpnManagerV2\Support\Permissions;

$subscription = is_array($subscription ?? null) ? $subscription : [];
$groups = is_array($groups ?? null) ? $groups : [];
$subscriptionId = (int)($subscription['id'] ?? 0);
?>

<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2"
       href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId)) ?>">
        <i class="ci-arrow-left" aria-hidden="true"></i>
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_back')) ?>
    </a>
</div>

<div class="alert alert-info rounded-4">
    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_native_hwid_note')) ?>
</div>

<?php if ($groups === []): ?>
    <div class="alert alert-secondary rounded-4"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_empty_connections')) ?></div>
<?php endif; ?>

<div class="d-grid gap-4">
<?php foreach ($groups as $group): ?>
    <?php
    $nodeId = (int)($group['node_id'] ?? 0);
    $devices = is_array($group['devices'] ?? null) ? $group['devices'] : [];
    $limit = max(0, (int)($group['limit'] ?? 0));
    ?>
    <section class="border rounded-5 p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h6 mb-1"><?= htmlSC((string)($group['server_name'] ?? ('#' . $nodeId))) ?></h2>
                <div class="small text-body-secondary"><?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_count'), count($devices), $limit)) ?> · #<?= $nodeId ?></div>
            </div>
            <?php if ($devices !== [] && Permissions::allows(Permissions::MANAGE_SUBSCRIPTIONS)): ?>
                <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/devices/' . $nodeId . '/clear')) ?>"
                      data-admin-delete-form data-delete-message="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_clear_confirm')) ?>"
                      data-delete-item="#<?= $subscriptionId ?>" data-delete-confirm-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_clear')) ?>">
                    <?= get_csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_clear')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($group['error'])): ?>
            <div class="alert alert-danger rounded-4 mb-0"><?= htmlSC((string)$group['error']) ?></div>
        <?php elseif (!empty($group['over_limit'])): ?>
            <div class="alert alert-warning rounded-4"><?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_over_limit'), count($devices), $limit)) ?></div>
        <?php endif; ?>

        <?php if (empty($group['error']) && $devices === []): ?>
            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_empty')) ?></div>
        <?php elseif (empty($group['error'])): ?>
            <div class="row g-3">
            <?php foreach ($devices as $device): ?>
                <?php
                $deviceId = (int)($device['id'] ?? 0);
                $model = trim((string)($device['deviceModel'] ?? ''));
                $os = trim((string)($device['deviceOs'] ?? ''));
                $version = trim((string)($device['osVersion'] ?? ''));
                $fingerprint = trim((string)($device['fingerprint'] ?? ''));
                ?>
                <div class="col-12 col-lg-6">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="d-flex justify-content-between gap-3">
                            <div class="min-w-0">
                                <div class="fw-semibold text-break"><?= htmlSC($model !== '' ? $model : FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_unknown')) ?></div>
                                <div class="small text-body-secondary"><?= htmlSC(trim($os . ($version !== '' ? ' ' . $version : '')) ?: '—') ?></div>
                            </div>
                            <span class="badge rounded-pill text-bg-light border">#<?= $deviceId ?></span>
                        </div>
                        <dl class="row small mt-3 mb-0">
                            <dt class="col-5"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_first_seen')) ?></dt>
                            <dd class="col-7 text-end"><?= htmlSC((string)($device['firstSeen'] ?? '—')) ?></dd>
                            <dt class="col-5"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_last_seen')) ?></dt>
                            <dd class="col-7 text-end"><?= htmlSC((string)($device['lastSeen'] ?? '—')) ?></dd>
                            <?php if ($fingerprint !== ''): ?>
                                <dt class="col-5"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_fingerprint')) ?></dt>
                                <dd class="col-7 text-end"><code><?= htmlSC($fingerprint) ?></code></dd>
                            <?php endif; ?>
                        </dl>
                        <?php if (Permissions::allows(Permissions::MANAGE_SUBSCRIPTIONS)): ?>
                            <form class="mt-3" method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/devices/' . $nodeId . '/' . $deviceId . '/delete')) ?>"
                                  data-admin-delete-form data-delete-message="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_delete_confirm')) ?>"
                                  data-delete-item="#<?= $deviceId ?>" data-delete-confirm-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_delete')) ?>">
                                <?= get_csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_devices_delete')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
