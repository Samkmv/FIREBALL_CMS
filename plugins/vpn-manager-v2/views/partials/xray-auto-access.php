<?php
$xrayAccess = $smartConnectSettings['xray_auto'] ?? [];
if (empty($xrayAccess['enabled']) || empty($xrayAccess['export'])
    || (empty($xrayAdmin) && empty($xrayAccess['visible']))
    || strtolower((string)parse_url((string)$subscriptionUrl, PHP_URL_SCHEME)) !== 'https') {
    return;
}
$xrayLink = $subscriptionUrl . '?format=xray';
$xrayText = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_xray_' . $key));
?>
<div class="border rounded-4 p-3 mb-3" data-vpn-v2-access>
    <div class="fw-semibold"><?= $xrayText('name') ?></div>
    <p class="small text-body-secondary mb-2"><?= $xrayText('access_help') ?></p>
    <?php if (!empty($xrayAdmin) && is_array($xrayDiagnostics ?? null)): ?>
        <p class="small text-body-secondary mb-2">
            <?= !empty($xrayDiagnostics['error']) ? $xrayText('diagnostic_error') : htmlSC(sprintf(
                FireballPluginVpnManagerV2::t('vpn_manager_v2_xray_diagnostic'),
                $xrayDiagnostics['count'], $xrayDiagnostics['skipped']
            )) ?>
        </p>
    <?php endif; ?>
    <button class="btn btn-outline-secondary rounded-pill" type="button"
            data-vpn-v2-copy-value="<?= htmlSC($xrayLink) ?>"
            data-vpn-v2-copy-done="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copied')) ?>"
            data-vpn-v2-copy-failed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copy_failed')) ?>">
        <i class="ci-copy" aria-hidden="true"></i> <span data-vpn-v2-copy-label><?= $xrayText('copy') ?></span>
    </button>
    <div class="small text-body-secondary mt-2" data-vpn-v2-copy-status aria-live="polite"></div>
    <div class="d-none mt-2" data-vpn-v2-manual-copy>
        <input class="form-control font-monospace" aria-label="<?= $xrayText('copy') ?>" readonly value="<?= htmlSC($xrayLink) ?>" data-vpn-v2-copy-input>
    </div>
</div>
