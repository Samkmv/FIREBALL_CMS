<?php
$settings = is_array($settings ?? null) ? $settings : [];
$checked = static fn(string $key): string => !empty($settings[$key]) ? 'checked' : '';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_settings_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/plugins/vpn-manager/settings') ?>" method="post">
        <?= get_csrf_field() ?>

        <section class="mb-4">
            <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_settings_branding')) ?></h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_service_name')) ?></label>
                    <input class="form-control" type="text" name="service_name" value="<?= htmlSC((string)($settings['service_name'] ?? 'My VPN')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_config_name_prefix')) ?></label>
                    <input class="form-control" type="text" name="config_name_prefix" value="<?= htmlSC((string)($settings['config_name_prefix'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_subscription_title')) ?></label>
                    <input class="form-control" type="text" name="subscription_title" value="<?= htmlSC((string)($settings['subscription_title'] ?? 'VPN subscription')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_server_name_template')) ?></label>
                    <input class="form-control" type="text" name="server_name_template" value="<?= htmlSC((string)($settings['server_name_template'] ?? '{service} — {server}')) ?>">
                    <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_name_template_hint')) ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_logo_path')) ?></label>
                    <input class="form-control" type="text" name="logo_path" value="<?= htmlSC((string)($settings['logo_path'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_support_email')) ?></label>
                    <input class="form-control" type="email" name="support_email" value="<?= htmlSC((string)($settings['support_email'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_support_url')) ?></label>
                    <input class="form-control" type="text" name="support_url" value="<?= htmlSC((string)($settings['support_url'] ?? '')) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_subscription_public_base_url')) ?></label>
                    <input class="form-control" type="text" name="subscription_public_base_url" placeholder="https://example.com" value="<?= htmlSC((string)($settings['subscription_public_base_url'] ?? '')) ?>">
                    <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_subscription_public_base_url_hint')) ?></div>
                </div>
            </div>
        </section>

        <section class="mb-4">
            <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_settings_automation')) ?></h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch py-2">
                        <input class="form-check-input" type="checkbox" name="traffic_sync_enabled" value="1" id="vpnTrafficSyncEnabled" <?= $checked('traffic_sync_enabled') ?>>
                        <label class="form-check-label fw-medium" for="vpnTrafficSyncEnabled"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_setting_traffic_sync_enabled')) ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_traffic_sync_interval')) ?></label>
                    <input class="form-control" type="number" min="1" step="1" name="traffic_sync_interval_minutes" value="<?= (int)($settings['traffic_sync_interval_minutes'] ?? 10) ?>">
                </div>
                <div class="col-md-4"></div>
                <?php foreach ([
                    'auto_disable_expired_subscriptions' => 'vpn_manager_setting_auto_disable_expired',
                    'auto_disable_traffic_exceeded' => 'vpn_manager_setting_auto_disable_traffic',
                    'notify_3_days_before_expire' => 'vpn_manager_setting_notify_3_days',
                    'notify_on_expire_day' => 'vpn_manager_setting_notify_today',
                    'notify_traffic_80' => 'vpn_manager_setting_notify_traffic_80',
                    'notify_traffic_100' => 'vpn_manager_setting_notify_traffic_100',
                    'use_push_notifications' => 'vpn_manager_setting_use_push',
                    'use_account_notifications' => 'vpn_manager_setting_use_account',
                    'use_email_notifications' => 'vpn_manager_setting_use_email',
                ] as $key => $label): ?>
                    <div class="col-md-6">
                        <div class="form-check form-switch border rounded-4 p-3 ps-5 h-100">
                            <input class="form-check-input" type="checkbox" name="<?= htmlSC($key) ?>" value="1" id="vpnSetting<?= htmlSC($key) ?>" <?= $checked($key) ?>>
                            <label class="form-check-label fw-medium" for="vpnSetting<?= htmlSC($key) ?>"><?= htmlSC(FireballPluginVpnManager::t($label)) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mb-4">
            <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_settings_sync')) ?></h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch py-2">
                        <input class="form-check-input" type="checkbox" name="sync_enabled" value="1" id="vpnSyncEnabled" <?= $checked('sync_enabled') ?>>
                        <label class="form-check-label fw-medium" for="vpnSyncEnabled"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_setting_sync_enabled')) ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_server_check_interval')) ?></label>
                    <input class="form-control" type="number" min="1" step="1" name="server_check_interval_minutes" value="<?= (int)($settings['server_check_interval_minutes'] ?? 10) ?>">
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch py-2">
                        <input class="form-check-input" type="checkbox" name="retry_failed_jobs" value="1" id="vpnRetryFailed" <?= $checked('retry_failed_jobs') ?>>
                        <label class="form-check-label fw-medium" for="vpnRetryFailed"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_setting_retry_failed')) ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_max_retry')) ?></label>
                    <input class="form-control" type="number" min="1" max="20" step="1" name="max_retry_attempts" value="<?= (int)($settings['max_retry_attempts'] ?? 3) ?>">
                </div>
            </div>
        </section>

        <section class="mb-4">
            <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_settings_security')) ?></h2>
            <div class="row g-2">
                <?php foreach ([
                    'hide_sensitive_data' => 'vpn_manager_setting_hide_sensitive',
                    'log_admin_actions' => 'vpn_manager_setting_log_admin',
                    'mask_subscription_links' => 'vpn_manager_setting_mask_links',
                    'allow_user_reset_config' => 'vpn_manager_setting_user_reset',
                    'allow_user_view_qr' => 'vpn_manager_setting_user_qr',
                    'public_account_enabled' => 'vpn_manager_setting_public_account',
                ] as $key => $label): ?>
                    <div class="col-md-6">
                        <div class="form-check form-switch border rounded-4 p-3 ps-5 h-100">
                            <input class="form-check-input" type="checkbox" name="<?= htmlSC($key) ?>" value="1" id="vpnSetting<?= htmlSC($key) ?>" <?= $checked($key) ?>>
                            <label class="form-check-label fw-medium" for="vpnSetting<?= htmlSC($key) ?>"><?= htmlSC(FireballPluginVpnManager::t($label)) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_save_settings')) ?></button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
