<?php

$server = is_array($server ?? null) ? $server : null;
$editing = $server !== null;
$serverId = (int)($server['id'] ?? 0);
$action = $editing
    ? base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . $serverId)
    : base_href('/admin/plugins/vpn-manager-v2/servers/create');
$authType = (string)($server['auth_type'] ?? 'token');
$secretPlaceholder = static function (string $key) use ($server): string {
    return !empty($server['has_' . $key])
        ? FireballPluginVpnManagerV2::t('vpn_manager_v2_secret_saved_placeholder')
        : FireballPluginVpnManagerV2::t('vpn_manager_v2_secret_new_placeholder');
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? '',
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<?php if ($editing): ?>
    <div class="small text-body-secondary mb-3">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_server_id')) ?>:
        <span class="fw-semibold text-body">#<?= $serverId ?></span>
    </div>
<?php endif; ?>

<form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post" autocomplete="off">
    <?= get_csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="vpnV2Name"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_name')) ?></label>
            <input class="form-control" id="vpnV2Name" type="text" name="name" maxlength="255" required value="<?= htmlSC((string)($server['name'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vpnV2Code"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_code')) ?></label>
            <input class="form-control" id="vpnV2Code" type="text" name="code" maxlength="80" required value="<?= htmlSC((string)($server['code'] ?? '')) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label" for="vpnV2ApiUrl"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_api_url')) ?></label>
            <input class="form-control" id="vpnV2ApiUrl" type="url" name="api_url" maxlength="500" placeholder="https://api.example.com" value="<?= htmlSC((string)($server['api_url'] ?? '')) ?>">
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_api_url_help')) ?></div>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="vpnV2ConnectTimeout"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_connect_timeout')) ?></label>
            <input class="form-control" id="vpnV2ConnectTimeout" type="number" name="connect_timeout" min="1" max="30" value="<?= (int)($server['connect_timeout'] ?? 5) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="vpnV2ReadTimeout"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_read_timeout')) ?></label>
            <input class="form-control" id="vpnV2ReadTimeout" type="number" name="read_timeout" min="2" max="90" value="<?= (int)($server['read_timeout'] ?? 15) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label" for="vpnV2PanelUrl"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_panel_url')) ?></label>
            <input class="form-control" id="vpnV2PanelUrl" type="url" name="panel_url" maxlength="500" required placeholder="https://panel.example.com:2053" value="<?= htmlSC((string)($server['panel_url'] ?? '')) ?>">
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_panel_url_help')) ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vpnV2PanelPath"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_panel_path')) ?></label>
            <input class="form-control" id="vpnV2PanelPath" type="text" name="panel_path" maxlength="190" placeholder="/panel-path" value="<?= htmlSC((string)($server['panel_path'] ?? '')) ?>">
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_panel_path_help')) ?></div>
        </div>

        <div class="col-12"><hr class="my-1"></div>

        <div class="col-md-4">
            <label class="form-label" for="vpnV2AuthType"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_auth_type')) ?></label>
            <select class="form-select" id="vpnV2AuthType" name="auth_type">
                <option value="token" <?= $authType === 'token' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_auth_token')) ?></option>
                <option value="password" <?= $authType === 'password' ? 'selected' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_auth_password')) ?></option>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="vpnV2Token"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_token')) ?></label>
            <input class="form-control" id="vpnV2Token" type="password" name="token" autocomplete="new-password" value="" placeholder="<?= htmlSC($secretPlaceholder('token')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="vpnV2Username"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_username')) ?></label>
            <input class="form-control" id="vpnV2Username" type="password" name="username" autocomplete="new-password" value="" placeholder="<?= htmlSC($secretPlaceholder('username')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="vpnV2Password"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_password')) ?></label>
            <input class="form-control" id="vpnV2Password" type="password" name="password" autocomplete="new-password" value="" placeholder="<?= htmlSC($secretPlaceholder('password')) ?>">
        </div>
        <div class="col-12">
            <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_secret_help')) ?></div>
        </div>
        <div class="col-12"><hr class="my-1"></div>
        <div class="col-md-6">
            <div class="border rounded-4 p-3 h-100">
                <div class="form-check form-switch mb-0">
                    <input type="hidden" name="verify_ssl" value="0">
                    <input class="form-check-input" id="vpnV2VerifySsl" type="checkbox" name="verify_ssl" value="1" <?= (int)($server['verify_ssl'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnV2VerifySsl"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_verify_ssl')) ?></label>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded-4 p-3 h-100">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" id="vpnV2AllowPrivate" type="checkbox" name="allow_private_network" value="1" <?= !empty($server['allow_private_network']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnV2AllowPrivate"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_allow_private_network')) ?></label>
                </div>
                <div class="form-text"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_private_network_help')) ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded-4 p-3 h-100">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" id="vpnV2Maintenance" type="checkbox" name="maintenance_mode" value="1" <?= !empty($server['maintenance_mode']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnV2Maintenance"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_maintenance_mode')) ?></label>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded-4 p-3 h-100">
                <div class="form-check form-switch mb-0">
                    <input type="hidden" name="allow_new_connections" value="0">
                    <input class="form-check-input" id="vpnV2AllowNew" type="checkbox" name="allow_new_connections" value="1" <?= (int)($server['allow_new_connections'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnV2AllowNew"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_allow_new_connections')) ?></label>
                </div>
            </div>
        </div>
        <div class="col-12"><hr class="my-1"></div>

        <div class="col-md-4">
            <label class="form-label" for="vpnV2CountryCode"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_country_code')) ?></label>
            <input class="form-control text-uppercase" id="vpnV2CountryCode" type="text" name="country_code" maxlength="2" placeholder="DE" value="<?= htmlSC((string)($server['country_code'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vpnV2CountryName"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_country_name')) ?></label>
            <input class="form-control" id="vpnV2CountryName" type="text" name="country_name" maxlength="120" value="<?= htmlSC((string)($server['country_name'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vpnV2City"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_city')) ?></label>
            <input class="form-control" id="vpnV2City" type="text" name="city" maxlength="120" value="<?= htmlSC((string)($server['city'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <div class="border rounded-4 p-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" id="vpnV2ShowFlag" type="checkbox" name="show_flag" value="1" <?= (int)($server['show_flag'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnV2ShowFlag"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_show_flag')) ?></label>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded-4 p-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" id="vpnV2Enabled" type="checkbox" name="is_enabled" value="1" <?= (int)($server['is_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnV2Enabled"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_field_is_enabled')) ?></label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_save')) ?></button>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers')) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?></a>
    </div>
</form>

<?= view()->renderPartial('admin/shell_close') ?>
