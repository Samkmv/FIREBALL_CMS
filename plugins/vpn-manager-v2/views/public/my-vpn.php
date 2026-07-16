<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$selected = is_array($selectedSubscription ?? null) ? $selectedSubscription : null;
$servers = is_array($servers ?? null) ? $servers : [];
$instructions = is_array($instructions ?? null) ? $instructions : [];
$subscriptionUrl = trim((string)($subscriptionUrl ?? ''));
$subscriptionQr = (string)($subscriptionQr ?? '');
$linkReady = !empty($linkReady) && $subscriptionUrl !== '';
$localSubscriptionUrl = !empty($localSubscriptionUrl);
$serviceName = trim((string)($serviceName ?? 'VPN V2')) ?: 'VPN V2';
$logo = trim((string)($logo ?? ''));
$supportName = trim((string)($supportName ?? ''));
$supportUrl = trim((string)($supportUrl ?? ''));
$showQrInProfile = !empty($showQrInProfile);
?>

<section class="container py-4 py-md-5" data-vpn-v2-profile>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 text-body-secondary small mb-2">
                        <?php if ($logo !== ''): ?>
                            <img src="<?= htmlSC(base_href($logo)) ?>" alt="" width="28" height="28" class="rounded object-fit-contain">
                        <?php else: ?>
                            <i class="ci-server" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span><?= htmlSC($serviceName) ?></span>
                    </div>
                    <h1 class="h3 mb-1"><?= htmlSC($title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_title')) ?></h1>
                    <p class="text-body-secondary mb-0"><?= htmlSC($subtitle ?? '') ?></p>
                </div>
                <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/profile') ?>">
                    <i class="ci-arrow-left" aria-hidden="true"></i>
                    <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_back')) ?></span>
                </a>
            </div>

            <?php if ($subscriptions === [] || $selected === null): ?>
                <div class="border rounded-5 p-4 p-md-5 text-center" data-vpn-v2-profile-empty>
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary mb-3 p-3">
                        <i class="ci-server fs-3" aria-hidden="true"></i>
                    </div>
                    <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_empty_title')) ?></h2>
                    <p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_empty_text')) ?></p>
                </div>
            <?php else: ?>
                <?php if (count($subscriptions) > 1): ?>
                    <div class="border rounded-5 p-3 mb-4" data-vpn-v2-subscription-selector>
                        <div class="small fw-semibold text-body-secondary px-2 mb-2">
                            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_select_subscription')) ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($subscriptions as $subscription): ?>
                                <?php $isSelected = (int)$subscription['id'] === (int)$selected['id']; ?>
                                <a class="btn <?= $isSelected ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill d-inline-flex align-items-center gap-2"
                                   href="<?= base_href('/profile/vpn-v2/' . (int)$subscription['id']) ?>"
                                   <?= $isSelected ? 'aria-current="page"' : '' ?>>
                                    <span><?= htmlSC((string)$subscription['plan_name']) ?></span>
                                    <span class="small opacity-75">#<?= (int)$subscription['id'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <article class="border rounded-5 p-4 p-md-5 mb-4" data-vpn-v2-subscription-card>
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
                        <div>
                            <div class="mb-2"><?= ProvisioningStatus::badge((string)$selected['effective_status']) ?></div>
                            <h2 class="h4 mb-1"><?= htmlSC((string)$selected['plan_name']) ?></h2>
                            <?php if (trim((string)($selected['plan_description'] ?? '')) !== ''): ?>
                                <p class="text-body-secondary mb-0"><?= htmlSC((string)$selected['plan_description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_remaining')) ?></div>
                            <div class="fw-semibold" data-vpn-v2-remaining><?= htmlSC((string)$selected['remaining_display']) ?></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_starts_at')) ?></div>
                                <div class="fw-semibold"><?= htmlSC((string)$selected['starts_at_display']) ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_expires_at')) ?></div>
                                <div class="fw-semibold"><?= htmlSC((string)$selected['expires_at_display']) ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_traffic')) ?></div>
                                <div class="fw-semibold"><?= htmlSC((string)$selected['traffic_usage_display']) ?></div>
                                <?php if ((int)($selected['traffic_limit_bytes'] ?? 0) > 0): ?>
                                    <div class="progress mt-2" role="progressbar" aria-valuenow="<?= (int)$selected['traffic_percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: <?= (int)$selected['traffic_percent'] ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="bg-body-tertiary rounded-4 p-3 h-100">
                                <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_connections')) ?></div>
                                <div class="fw-semibold"><?= (int)$selected['connection_count'] ?></div>
                                <div class="small text-body-secondary"><?= htmlSC(sprintf(
                                    FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_device_limit'),
                                    (int)$selected['device_limit']
                                )) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h3 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_servers_title')) ?></h3>
                            <span class="small text-body-secondary">
                                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_by_plan')) ?>:
                                <?= (int)($selected['plan_connection_count'] ?? 0) ?> ·
                                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_actually_created')) ?>:
                                <?= (int)$selected['connection_count'] ?>
                            </span>
                        </div>
                        <?php if ((int)($selected['creating_count'] ?? 0) > 0): ?>
                            <div class="alert alert-info rounded-4 py-2 mb-3">
                                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_server_adding')) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ((int)($selected['failed_count'] ?? 0) > 0): ?>
                            <div class="alert alert-warning rounded-4 py-2 mb-3">
                                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_server_unavailable')) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($servers === []): ?>
                            <div class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_no_servers')) ?></div>
                        <?php else: ?>
                            <div class="row g-3" data-vpn-v2-profile-servers>
                                <?php foreach ($servers as $server): ?>
                                    <?php
                                    $location = implode(', ', array_filter([(string)$server['country'], (string)$server['city']]));
                                    ?>
                                    <div class="col-12 col-md-6">
                                        <div class="border rounded-4 p-3 h-100 d-flex align-items-center gap-3">
                                            <span class="fs-3" aria-hidden="true"><?= htmlSC((string)($server['flag'] ?: '🌐')) ?></span>
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-break"><?= htmlSC((string)$server['name']) ?></div>
                                                <div class="small text-body-secondary"><?= htmlSC($location !== ''
                                                    ? $location
                                                    : FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_location_unknown')) ?></div>
                                                <div class="small mt-1 <?= (string)($server['status'] ?? '') === 'active' ? 'text-success' : 'text-body-secondary' ?>">
                                                    <?= htmlSC(FireballPluginVpnManagerV2::t(
                                                        (string)($server['status'] ?? '') === 'active'
                                                            ? 'vpn_manager_v2_server_status_available'
                                                            : 'vpn_manager_v2_server_status_disabled'
                                                    )) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <div class="border rounded-5 p-4 p-md-5 mb-4" data-vpn-v2-access>
                    <div class="row g-4 align-items-center">
                        <div class="<?= $showQrInProfile ? 'col-lg-7' : 'col-12' ?>">
                            <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_access_title')) ?></h2>
                            <p class="text-body-secondary mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_access_help')) ?></p>
                            <?php if ($localSubscriptionUrl && $linkReady): ?>
                                <div class="alert alert-warning rounded-4">
                                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_local_url_warning')) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($linkReady): ?>
                                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="button"
                                        data-vpn-v2-copy-value="<?= htmlSC($subscriptionUrl) ?>"
                                        data-vpn-v2-copy-done="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copied')) ?>"
                                        data-vpn-v2-copy-failed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copy_failed')) ?>">
                                    <i class="ci-copy" aria-hidden="true"></i>
                                    <span data-vpn-v2-copy-label><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_copy_link')) ?></span>
                                </button>
                                <div class="small text-body-secondary mt-2" data-vpn-v2-copy-status aria-live="polite"></div>
                                <div class="mt-3 d-none" data-vpn-v2-manual-copy>
                                    <label class="form-label small" for="vpnV2ProfileSubscriptionUrl">
                                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_manual_copy_label')) ?>
                                    </label>
                                    <input class="form-control font-monospace" id="vpnV2ProfileSubscriptionUrl" type="url"
                                           readonly value="<?= htmlSC($subscriptionUrl) ?>" data-vpn-v2-copy-input>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info rounded-4 mb-0" data-vpn-v2-access-unavailable>
                                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_access_unavailable')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($showQrInProfile): ?>
                            <div class="col-lg-5 text-center">
                                <h3 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_qr_title')) ?></h3>
                                <?php if ($linkReady && $subscriptionQr !== ''): ?>
                                    <div data-vpn-v2-profile-qr><?= $subscriptionQr ?></div>
                                    <div class="small text-body-secondary mt-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_qr_help')) ?></div>
                                <?php else: ?>
                                    <div class="text-body-secondary">—</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-info rounded-4 d-flex align-items-start gap-3 mb-4" data-vpn-v2-refresh-hint>
                    <i class="ci-info fs-5 mt-1" aria-hidden="true"></i>
                    <div><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_refresh_hint')) ?></div>
                </div>

                <?php if ($supportName !== '' || $supportUrl !== ''): ?>
                    <section class="border rounded-5 p-4 mb-4" data-vpn-v2-support>
                        <div class="d-flex align-items-start gap-3">
                            <i class="ci-life-buoy fs-4 text-body-secondary" aria-hidden="true"></i>
                            <div>
                                <h2 class="h6 mb-1"><?= htmlSC($supportName !== ''
                                    ? $supportName
                                    : FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_support_title')) ?></h2>
                                <p class="small text-body-secondary mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_support_help')) ?></p>
                                <?php if ($supportUrl !== ''): ?>
                                    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= htmlSC($supportUrl) ?>" rel="noopener noreferrer">
                                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_support_action')) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="border rounded-5 p-4 p-md-5" id="vpn-v2-instructions" data-vpn-v2-instructions>
                    <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_instructions_title')) ?></h2>
                    <p class="text-body-secondary mb-4"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_instructions_subtitle')) ?></p>
                    <div class="vstack gap-3">
                        <?php foreach ($instructions as $instruction): ?>
                            <details class="border rounded-4 p-3" id="vpn-v2-instructions-<?= htmlSC((string)$instruction['key']) ?>" <?= !empty($instruction['selected']) ? 'open' : '' ?>>
                                <summary class="fw-semibold d-flex align-items-center gap-2">
                                    <i class="<?= htmlSC((string)$instruction['icon']) ?>" aria-hidden="true"></i>
                                    <span><?= htmlSC((string)$instruction['label']) ?></span>
                                </summary>
                                <ol class="mt-3 mb-0 ps-4 vstack gap-2">
                                    <?php foreach ((array)$instruction['steps'] as $step): ?>
                                        <li><?= htmlSC((string)$step) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
