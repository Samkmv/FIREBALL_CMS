<?php
use Fireball\VpnManager\Services\QrCodeService;
use Fireball\VpnManager\Services\SubscriptionLinkService;
use Fireball\VpnManager\Support\Formatter;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$active = null;
foreach ($subscriptions as $item) {
    if (($item['status'] ?? '') === 'active') {
        $active = $item;
        break;
    }
}
$visible = $active ?: ($subscriptions[0] ?? null);
$subscriptionUrl = is_array($visible) ? (new SubscriptionLinkService())->subscriptionUrl($visible) : '';
?>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h1 class="h3 mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_title')) ?></h1>
                    <p class="text-body-secondary mb-0"><?= htmlSC((string)($serviceName ?? 'My VPN')) ?></p>
                </div>
            </div>

            <?php if (!$visible): ?>
                <div class="border rounded-5 p-4 p-md-5 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary mb-3" style="width:3rem;height:3rem;"><i class="ci-server fs-4"></i></div>
                    <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_empty_title')) ?></h2>
                    <p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_empty_text')) ?></p>
                </div>
            <?php else: ?>
                <?php
                $status = (string)($visible['status'] ?? '');
                $statusText = match ($status) {
                    'active' => FireballPluginVpnManager::t('vpn_manager_my_vpn_active'),
                    'expired' => FireballPluginVpnManager::t('vpn_manager_my_vpn_expired'),
                    'traffic_exceeded' => FireballPluginVpnManager::t('vpn_manager_my_vpn_traffic_exceeded'),
                    default => FireballPluginVpnManager::t('vpn_manager_my_vpn_status_other'),
                };
                ?>
                <div class="border rounded-5 p-4 p-md-5">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                        <div>
                            <div class="badge rounded-pill text-success bg-success-subtle mb-2"><?= htmlSC($statusText) ?></div>
                            <h2 class="h4 mb-1"><?= htmlSC((string)($visible['plan_name'] ?? $settings['subscription_title'] ?? 'VPN subscription')) ?></h2>
                            <div class="text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_valid_until')) ?>: <?= htmlSC(Formatter::dateTime((string)($visible['expires_at'] ?? ''))) ?></div>
                        </div>
                        <i class="ci-server fs-2 text-body-secondary"></i>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic_used')) ?></div>
                                <div class="fw-semibold"><?= htmlSC(Formatter::bytes((int)($visible['traffic_used_bytes'] ?? 0))) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic_limit')) ?></div>
                                <div class="fw-semibold"><?= htmlSC(Formatter::bytes((int)($visible['traffic_limit_bytes'] ?? 0))) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_devices')) ?></div>
                                <div class="fw-semibold"><?= (int)($visible['device_limit'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="button" <?= $subscriptionUrl === '' ? 'disabled' : '' ?> data-vpn-copy-value="<?= htmlSC($subscriptionUrl) ?>" data-vpn-copy-done="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_copied')) ?>"><i class="ci-copy"></i><span data-vpn-copy-label><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_copy_subscription')) ?></span></button>
                        <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2 <?= $subscriptionUrl === '' ? 'disabled' : '' ?>" href="#my-vpn-qr" <?= $subscriptionUrl === '' ? 'aria-disabled="true"' : '' ?>><i class="ci-scan"></i><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_show_qr')) ?></a>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/my-vpn') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_android')) ?></a>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/my-vpn') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_iphone')) ?></a>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/my-vpn') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_windows')) ?></a>
                    </div>
                    <?php if ($subscriptionUrl !== ''): ?>
                        <div class="mt-4" id="my-vpn-qr">
                            <?= (new QrCodeService())->render($subscriptionUrl) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
