<?php
$smartAccessSettings = is_array($smartConnectSettings ?? null) ? $smartConnectSettings : [];
$smartAccessAutomatic = \Fireball\VpnManagerV2\DTO\SmartConnectData::automatic($smartAccessSettings);
$smartAccessSingbox = !empty($smartAccessSettings['smart_connect_enabled']) && !empty($smartAccessSettings['smart_connect_singbox_enabled']);
?>
<?php if ($smartAccessAutomatic && !empty($smartAccessSettings['smart_connect_happ_enabled'])): ?>
    <div class="border rounded-4 p-3 mb-3">
        <div class="fw-semibold mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_happ_access')) ?></div>
        <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_happ_access_help')) ?></div>
    </div>
<?php endif; ?>
<?php if ($smartAccessSingbox && ($subscriptionUrl ?? '') !== ''): $smartAccessUrl = $subscriptionUrl . '?format=singbox'; ?>
    <div class="border rounded-4 p-3 mb-3" data-vpn-v2-access>
        <div class="fw-semibold mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_singbox_access')) ?></div>
        <p class="small text-body-secondary mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_singbox_access_help')) ?></p>
        <p class="small text-body-secondary mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_hwid_help')) ?></p>
        <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button"
            data-vpn-v2-copy-value="<?= htmlSC($smartAccessUrl) ?>"
            data-vpn-v2-copy-done="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copied')) ?>"
            data-vpn-v2-copy-failed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copy_failed')) ?>">
            <i class="ci-copy" aria-hidden="true"></i><span data-vpn-v2-copy-label><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_copy_singbox')) ?></span>
        </button>
        <div class="small text-body-secondary mt-2" data-vpn-v2-copy-status aria-live="polite"></div>
        <div class="d-none mt-2" data-vpn-v2-manual-copy>
            <label class="form-label small"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_manual_copy_label')) ?>
                <input class="form-control font-monospace mt-1" readonly value="<?= htmlSC($smartAccessUrl) ?>" data-vpn-v2-copy-input>
            </label>
        </div>
    </div>
<?php endif; ?>
