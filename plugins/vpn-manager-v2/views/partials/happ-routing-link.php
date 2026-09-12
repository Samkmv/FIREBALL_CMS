<?php

$routingLink = (new \Fireball\VpnManagerV2\Support\HappRoutingProfile())->activeLink([
    'happ_routing_enabled' => true,
    'happ_routing_link' => $routingLink ?? '',
]);
?>
<?php if ($routingLink !== ''): ?>
    <div data-vpn-v2-happ-routing>
        <a class="btn btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2"
           href="<?= htmlSC($routingLink) ?>" data-vpn-v2-happ-apply>
            <i class="ci-external-link" aria-hidden="true"></i>
            <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_happ_apply')) ?></span>
        </a>
        <button class="btn btn-outline-secondary rounded-pill" type="button"
                data-vpn-v2-copy-value="<?= htmlSC($routingLink) ?>"
                data-vpn-v2-copy-done="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copied')) ?>"
                data-vpn-v2-copy-failed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_link_copy_failed')) ?>">
            <span data-vpn-v2-copy-label><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_happ_copy')) ?></span>
        </button>
        <div class="small text-body-secondary mt-2" data-vpn-v2-copy-status aria-live="polite"></div>
        <div class="mt-2 d-none" data-vpn-v2-manual-copy>
            <label class="form-label small" for="vpnV2HappRoutingCopy"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_happ_link')) ?></label>
            <input class="form-control font-monospace" id="vpnV2HappRoutingCopy" type="text"
                   readonly value="<?= htmlSC($routingLink) ?>" data-vpn-v2-copy-input>
        </div>
    </div>
<?php endif; ?>
