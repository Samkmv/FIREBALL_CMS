<?php $subscriptionId = (int)$subscription['id']; ?>
<?php if (\Fireball\VpnManagerV2\Support\Permissions::allows(\Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS) && !in_array((string)($subscription['status'] ?? ''), ['deleted', 'deleting', 'delete_failed'], true)): ?>
    <form class="border rounded-5 p-3 p-md-4 mb-4" id="client-name" data-vpn-v2-client-name method="post"
          action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/rename')) ?>">
        <?= get_csrf_field() ?>
        <input type="hidden" name="return_query" value="<?= htmlSC($returnQuery) ?>">
        <label class="h6 d-block mb-2" for="vpnV2ClientDisplayName"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_title')) ?></label>
        <div class="row g-2 align-items-start">
            <div class="col-lg">
                <input class="form-control" id="vpnV2ClientDisplayName" name="client_display_name" maxlength="160"
                       value="<?= htmlSC((string)($subscription['client_display_name'] ?? '')) ?>"
                       placeholder="<?= htmlSC((string)$subscription['user_name']) ?>">
            </div>
            <div class="col-lg-auto">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center justify-content-center gap-2 text-wrap w-100" type="submit">
                    <i class="ci-edit-2 flex-shrink-0" aria-hidden="true"></i><span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_action')) ?></span>
                </button>
            </div>
        </div>
        <div class="form-text mt-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_help')) ?></div>
        <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_reset_help')) ?></div>
    </form>
<?php endif; ?>
