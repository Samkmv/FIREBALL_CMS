<?php

$settings = is_array($settings ?? null) ? $settings : [];
$templateVariables = is_array($templateVariables ?? null) ? $templateVariables : [];
$checked = static fn(string $key): string => !empty($settings[$key]) ? ' checked' : '';
$switch = static function (string $key, string $label, string $help = '') use ($checked): string {
    return '<div class="form-check form-switch">'
        . '<input type="hidden" name="' . htmlSC($key) . '" value="0">'
        . '<input class="form-check-input" type="checkbox" role="switch" id="vpnV2Setting-'
        . htmlSC($key) . '" name="' . htmlSC($key) . '" value="1"' . $checked($key) . '>'
        . '<label class="form-check-label fw-semibold" for="vpnV2Setting-' . htmlSC($key) . '">'
        . htmlSC($label) . '</label>'
        . ($help !== '' ? '<div class="small text-body-secondary">' . htmlSC($help) . '</div>' : '')
        . '</div>';
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<form action="<?= base_href('/admin/plugins/vpn-manager-v2/settings') ?>" method="post" data-vpn-v2-settings-form>
    <?= get_csrf_field() ?>

    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-image fs-4 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_branding')) ?></h2>
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <label class="form-label" for="vpnV2ServiceName"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_service_name')) ?></label>
                <input class="form-control" id="vpnV2ServiceName" type="text" name="service_name" maxlength="120" required
                       value="<?= htmlSC((string)($settings['service_name'] ?? 'VPN V2')) ?>">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="vpnV2Logo"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_logo')) ?></label>
                <input class="form-control" id="vpnV2Logo" type="text" name="logo" maxlength="255" placeholder="/uploads/vpn/logo.png"
                       value="<?= htmlSC((string)($settings['logo'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_logo_help')) ?></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="vpnV2ServerNameTemplate"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_server_name_template')) ?></label>
                <input class="form-control font-monospace" id="vpnV2ServerNameTemplate" type="text" name="server_name_template" maxlength="240" required
                       value="<?= htmlSC((string)($settings['server_name_template'] ?? '')) ?>">
                <div class="form-text">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_template_help')) ?>
                    <span class="font-monospace"><?= htmlSC(implode(' ', array_map(static fn(string $item): string => '{' . $item . '}', $templateVariables))) ?></span>
                </div>
            </div>
            <div class="col-12">
                <?= $switch(
                    'global_show_flags',
                    FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_global_show_flags'),
                    FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_global_show_flags_help')
                ) ?>
            </div>
        </div>
    </section>

    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-bell fs-4 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_notifications')) ?></h2>
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-6"><?= $switch(
                'notifications_profile_enabled',
                FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notifications_profile'),
                FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notifications_profile_help')
            ) ?></div>
            <div class="col-12 col-lg-6"><?= $switch(
                'notifications_email_enabled',
                FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notifications_email')
            ) ?></div>
            <div class="col-12 col-md-6"><?= $switch('notify_expiration_3_days', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notify_expiration_3_days')) ?></div>
            <div class="col-12 col-md-6"><?= $switch('notify_expiration_day', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notify_expiration_day')) ?></div>
            <div class="col-12 col-md-6"><?= $switch('notify_traffic_80', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notify_traffic_80')) ?></div>
            <div class="col-12 col-md-6"><?= $switch('notify_traffic_100', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notify_traffic_100')) ?></div>
            <div class="col-12 col-md-6"><?= $switch('notify_provisioned', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notify_provisioned')) ?></div>
            <div class="col-12 col-md-6"><?= $switch('notify_critical_errors', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_notify_critical_errors')) ?></div>
            <div class="col-12"><?= $switch('retry_failed_operations', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_retry_failed_operations')) ?></div>
        </div>
    </section>

    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-link fs-4 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_subscription')) ?></h2>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="vpnV2SubscriptionName"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_subscription_name')) ?></label>
                <input class="form-control" id="vpnV2SubscriptionName" type="text" name="subscription_name" maxlength="120" required
                       value="<?= htmlSC((string)($settings['subscription_name'] ?? 'VPN V2')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_subscription_name_help')) ?></div>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="vpnV2ExpiredBehavior"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_expired_behavior')) ?></label>
                <select class="form-select" id="vpnV2ExpiredBehavior" name="expired_subscription_behavior">
                    <option value="gone" <?= ($settings['expired_subscription_behavior'] ?? 'gone') === 'gone' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_expired_behavior_gone')) ?></option>
                    <option value="not_found" <?= ($settings['expired_subscription_behavior'] ?? '') === 'not_found' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_expired_behavior_not_found')) ?></option>
                </select>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="vpnV2SubscriptionCache"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_subscription_cache_ttl')) ?></label>
                <input class="form-control" id="vpnV2SubscriptionCache" type="number" name="subscription_cache_ttl_seconds" min="30" max="3600" step="1"
                       value="<?= (int)($settings['subscription_cache_ttl_seconds'] ?? 300) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="vpnV2QrCache"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_qr_cache_ttl')) ?></label>
                <input class="form-control" id="vpnV2QrCache" type="number" name="qr_cache_ttl_seconds" min="60" max="86400" step="1"
                       value="<?= (int)($settings['qr_cache_ttl_seconds'] ?? 3600) ?>">
            </div>
        </div>
    </section>

    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-refresh-cw fs-4 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_sync')) ?></h2>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-12"><?= $switch('sync_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_sync_enabled')) ?></div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="vpnV2SyncInterval"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_sync_interval')) ?></label>
                <input class="form-control" id="vpnV2SyncInterval" type="number" name="sync_interval_minutes" min="1" max="1440" step="1"
                       value="<?= (int)($settings['sync_interval_minutes'] ?? 15) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="vpnV2CheckInterval"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_check_interval')) ?></label>
                <input class="form-control" id="vpnV2CheckInterval" type="number" name="server_check_interval_minutes" min="1" max="1440" step="1"
                       value="<?= (int)($settings['server_check_interval_minutes'] ?? 10) ?>">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label" for="vpnV2SettingsCache"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_settings_cache_ttl')) ?></label>
                <input class="form-control" id="vpnV2SettingsCache" type="number" name="settings_cache_ttl_seconds" min="30" max="1800" step="1"
                       value="<?= (int)($settings['settings_cache_ttl_seconds'] ?? 300) ?>">
            </div>
        </div>
    </section>

    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-shield fs-4 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_security')) ?></h2>
        </div>
        <div class="vstack gap-3">
            <?= $switch('hide_sensitive_data', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_hide_sensitive_data')) ?>
            <?= $switch('mask_subscription_links', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_mask_subscription_links')) ?>
            <div class="alert alert-info rounded-4 mb-0 small">
                <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_secrets_unchanged')) ?>
            </div>
        </div>
    </section>

    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-user fs-4 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_profile')) ?></h2>
        </div>
        <div class="row g-3">
            <div class="col-12"><?= $switch('public_account_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_public_account')) ?></div>
            <div class="col-12"><?= $switch('show_qr_in_profile', FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_profile_qr')) ?></div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="vpnV2SupportName"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_support_name')) ?></label>
                <input class="form-control" id="vpnV2SupportName" type="text" name="support_name" maxlength="120"
                       value="<?= htmlSC((string)($settings['support_name'] ?? '')) ?>">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="vpnV2SupportUrl"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_setting_support_url')) ?></label>
                <input class="form-control" id="vpnV2SupportUrl" type="url" name="support_url" maxlength="255" placeholder="https://support.example.com"
                       value="<?= htmlSC((string)($settings['support_url'] ?? '')) ?>">
            </div>
        </div>
    </section>

    <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
        <i class="ci-save" aria-hidden="true"></i>
        <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_save')) ?></span>
    </button>
</form>

<?= view()->renderPartial('admin/shell_close') ?>
