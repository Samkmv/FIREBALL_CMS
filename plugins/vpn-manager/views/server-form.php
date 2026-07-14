<?php
use Fireball\VpnManager\Services\CountryFlagService;
use Fireball\VpnManager\Services\SubscriptionLinkService;
use Fireball\VpnManager\Support\Crypto;

$server = is_array($server ?? null) ? $server : null;
$isEdit = $server !== null;
$action = $isEdit ? base_href('/admin/plugins/vpn-manager/servers/edit/' . (int)$server['id']) : base_href('/admin/plugins/vpn-manager/servers/create');
$flagService = new CountryFlagService();
$countries = $flagService->countries();
$countryCode = $flagService->normalizeCountryCode((string)($server['country_code'] ?? ''));
$countryName = trim((string)($server['country_name'] ?? $server['country'] ?? ''));
$preview = (new SubscriptionLinkService())->configNameForServer([
    'server_name' => (string)($server['name'] ?? FireballPluginVpnManager::t('vpn_manager_server_preview_name')),
    'country_code' => $countryCode,
    'country_name' => $countryName,
    'city' => (string)($server['city'] ?? ''),
    'show_flag' => (int)($server['show_flag'] ?? 1),
], '');
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? '',
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="alert alert-info rounded-4 mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="ci-info fs-4 mt-1"></i>
            <div>
                <div class="fw-semibold mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_title')) ?></div>
                <div class="small mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_intro')) ?></div>
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_panel_url')) ?></div>
                        <div><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_panel_url')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_panel_path')) ?></div>
                        <div><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_panel_path')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_public_host')) ?></div>
                        <div><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_public_host')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_api_token')) ?> / <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_username')) ?></div>
                        <div><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_auth')) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_after_save_title')) ?></div>
                        <div><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_help_after_save')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_name')) ?></label>
                <input class="form-control" type="text" name="name" required placeholder="Germany 01" value="<?= htmlSC((string)($server['name'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_name_hint')) ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_code')) ?></label>
                <input class="form-control" type="text" name="code" placeholder="de-01" value="<?= htmlSC((string)($server['code'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_code_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_country_code')) ?></label>
                <select class="form-select" name="country_code">
                    <option value=""><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_country_not_selected')) ?></option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?= htmlSC($country['code']) ?>" <?= $countryCode === $country['code'] ? 'selected' : '' ?>>
                            <?= htmlSC($country['flag'] . ' ' . $country['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_country_code_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_country_name')) ?></label>
                <input class="form-control" type="text" name="country_name" placeholder="Germany" value="<?= htmlSC($countryName) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_country_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_city')) ?></label>
                <input class="form-control" type="text" name="city" placeholder="Frankfurt" value="<?= htmlSC((string)($server['city'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_server_city_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch border rounded-4 p-3 ps-5 h-100">
                    <input class="form-check-input" type="checkbox" name="show_flag" value="1" id="vpnServerShowFlag" <?= (int)($server['show_flag'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnServerShowFlag"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_show_flag')) ?></label>
                    <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_flag_preview')) ?>: <?= htmlSC($countryCode !== '' ? $flagService->flagFromCountryCode($countryCode) : FireballPluginVpnManager::t('vpn_manager_no_flag')) ?></div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="border rounded-4 p-3 h-100">
                    <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_preview')) ?></div>
                    <div class="fw-semibold"><?= htmlSC($preview) ?></div>
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_panel_url')) ?></label>
                <input class="form-control" type="url" name="panel_url" required placeholder="https://2.27.121.41:2053" value="<?= htmlSC((string)($server['panel_url'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_panel_url_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_panel_path')) ?></label>
                <input class="form-control" type="text" name="panel_path" placeholder="/" value="<?= htmlSC((string)($server['panel_path'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_panel_path_hint')) ?></div>
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_public_host')) ?></label>
                <input class="form-control" type="text" name="public_host" placeholder="vpn.example.com" value="<?= htmlSC((string)($server['public_host'] ?? '')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_public_host_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_auth_type')) ?></label>
                <?php $authType = (string)($server['api_auth_type'] ?? 'token'); ?>
                <select class="form-select" name="api_auth_type">
                    <option value="token" <?= $authType === 'token' ? 'selected' : '' ?>>Token</option>
                    <option value="password" <?= $authType === 'password' ? 'selected' : '' ?>>Username / Password</option>
                </select>
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_auth_type_hint')) ?></div>
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_api_token')) ?></label>
                <input class="form-control" type="password" name="api_token" autocomplete="new-password" placeholder="<?= htmlSC(FireballPluginVpnManager::t('vpn_manager_api_token_placeholder')) ?>" value="<?= htmlSC(Crypto::mask($server['api_token_encrypted'] ?? null)) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_api_token_hint')) ?> <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_secret_hint')) ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_username')) ?></label>
                <input class="form-control" type="password" name="username" autocomplete="new-password" placeholder="admin" value="<?= htmlSC(Crypto::mask($server['username_encrypted'] ?? null)) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_username_hint')) ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_password')) ?></label>
                <input class="form-control" type="password" name="password" autocomplete="new-password" value="<?= htmlSC(Crypto::mask($server['password_encrypted'] ?? null)) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_password_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_sort_order')) ?></label>
                <input class="form-control" type="number" min="0" step="1" name="sort_order" value="<?= (int)($server['sort_order'] ?? 0) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_sort_order_hint')) ?></div>
            </div>
            <div class="col-md-8 d-flex align-items-end">
                <div class="form-check form-switch py-2">
                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="vpnServerEnabled" <?= (int)($server['is_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnServerEnabled"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_enabled')) ?></label>
                    <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_enabled_hint')) ?></div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_save')) ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/servers') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_cancel')) ?></a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
