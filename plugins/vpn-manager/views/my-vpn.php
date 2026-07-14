<?php
use Fireball\VpnManager\Services\QrCodeService;
use Fireball\VpnManager\Services\SubscriptionLinkService;
use Fireball\VpnManager\Support\Formatter;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$selectedSubscription = is_array($selectedSubscription ?? null) ? $selectedSubscription : null;
$active = $selectedSubscription;
foreach ($subscriptions as $item) {
    if ($selectedSubscription !== null && (int)($item['id'] ?? 0) === (int)($selectedSubscription['id'] ?? 0)) {
        $active = $item;
        break;
    }
    if ($active !== null) {
        break;
    }
    if (($item['status'] ?? '') === 'active') {
        $active = $item;
        break;
    }
}
$visible = $active ?: ($subscriptions[0] ?? null);
$linkService = new SubscriptionLinkService();
$subscriptionUrl = is_array($visible) ? $linkService->subscriptionUrl($visible, 'plain') : '';
$subscriptionStandardUrl = is_array($visible) ? $linkService->subscriptionUrl($visible, 'base64') : '';
$status = is_array($visible) ? (string)($visible['status'] ?? '') : '';
$isReady = $subscriptionUrl !== '' && $status === 'active' && (int)($visible['node_count'] ?? 0) > 0;
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
                    'active' => FireballPluginVpnManager::t('vpn_manager_status_active'),
                    'provisioning' => FireballPluginVpnManager::t('vpn_manager_status_provisioning'),
                    'provisioning_failed' => FireballPluginVpnManager::t('vpn_manager_status_provisioning_failed'),
                    'suspended' => FireballPluginVpnManager::t('vpn_manager_status_suspended'),
                    'expired' => FireballPluginVpnManager::t('vpn_manager_status_expired'),
                    'traffic_exceeded' => FireballPluginVpnManager::t('vpn_manager_status_traffic_exceeded'),
                    'sync_error' => FireballPluginVpnManager::t('vpn_manager_status_sync_error'),
                    'cancelled' => FireballPluginVpnManager::t('vpn_manager_status_cancelled'),
                    'deleting' => FireballPluginVpnManager::t('vpn_manager_status_deleting'),
                    'delete_failed' => FireballPluginVpnManager::t('vpn_manager_status_delete_failed'),
                    default => FireballPluginVpnManager::t('vpn_manager_safe_connection_error'),
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
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_traffic')) ?></div>
                                <div class="fw-semibold"><?= htmlSC(Formatter::traffic((int)($visible['traffic_used_bytes'] ?? 0), (int)($visible['traffic_limit_bytes'] ?? 0))) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_servers')) ?></div>
                                <div class="fw-semibold"><?= htmlSC(trim((string)($visible['server_names'] ?? '')) !== '' ? (string)$visible['server_names'] : FireballPluginVpnManager::t('vpn_manager_none')) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_connections')) ?></div>
                                <div class="fw-semibold"><?= (int)($visible['node_count'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php if ($subscriptionUrl !== '' && $linkService->isLocalUrl($subscriptionUrl)): ?>
                        <div class="alert alert-warning rounded-4">
                            <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_local_url_warning')) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$isReady): ?>
                        <div class="alert alert-warning rounded-4">
                            <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_qr_pending')) ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="button" <?= !$isReady ? 'disabled' : '' ?> data-vpn-copy-value="<?= htmlSC($subscriptionUrl) ?>" data-vpn-copy-done="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_link_copied')) ?>"><i class="ci-copy"></i><span data-vpn-copy-label><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_copy_subscription')) ?></span></button>
                        <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" <?= !$isReady ? 'disabled' : '' ?> data-bs-toggle="modal" data-bs-target="#myVpnQrModal"><i class="ci-scan"></i><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_show_qr')) ?></button>
                        <?php if ($subscriptionStandardUrl !== '' && $subscriptionStandardUrl !== $subscriptionUrl): ?>
                            <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" data-vpn-copy-value="<?= htmlSC($subscriptionStandardUrl) ?>" data-vpn-copy-done="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_copied')) ?>"><i class="ci-copy"></i><span data-vpn-copy-label><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_link_standard_label')) ?></span></button>
                        <?php endif; ?>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/vpn/instructions/android') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_android')) ?></a>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/vpn/instructions/ios') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_iphone')) ?></a>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/vpn/instructions/windows') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_windows')) ?></a>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/vpn/instructions/macos') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instruction_macos')) ?></a>
                    </div>

                    <div class="border-top pt-4 mt-4">
                        <h3 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_instructions')) ?></h3>
                        <div class="row g-3 small text-body-secondary">
                            <?php foreach (['install_app', 'copy_link', 'add_link', 'refresh_subscription', 'choose_server', 'check_connection'] as $step): ?>
                                <div class="col-md-6"><?= htmlSC(str_replace('{service}', (string)($serviceName ?? 'VPN'), FireballPluginVpnManager::t('vpn_manager_instruction_' . $step))) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($isReady): ?>
                    <div class="modal fade" id="myVpnQrModal" tabindex="-1" aria-labelledby="myVpnQrModalTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content rounded-5">
                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="myVpnQrModalTitle"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_my_vpn_show_qr')) ?></h2>
                                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_close')) ?>"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <?= (new QrCodeService())->render($subscriptionUrl) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
