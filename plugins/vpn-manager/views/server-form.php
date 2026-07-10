<?php
use Fireball\VpnManager\Support\Crypto;

$server = is_array($server ?? null) ? $server : null;
$isEdit = $server !== null;
$action = $isEdit ? base_href('/admin/plugins/vpn-manager/servers/edit/' . (int)$server['id']) : base_href('/admin/plugins/vpn-manager/servers/create');
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? '',
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_name')) ?></label>
                <input class="form-control" type="text" name="name" required value="<?= htmlSC((string)($server['name'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_code')) ?></label>
                <input class="form-control" type="text" name="code" value="<?= htmlSC((string)($server['code'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_country')) ?></label>
                <input class="form-control" type="text" name="country" value="<?= htmlSC((string)($server['country'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_city')) ?></label>
                <input class="form-control" type="text" name="city" value="<?= htmlSC((string)($server['city'] ?? '')) ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_panel_url')) ?></label>
                <input class="form-control" type="url" name="panel_url" required placeholder="https://vpn.example.com" value="<?= htmlSC((string)($server['panel_url'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_panel_path')) ?></label>
                <input class="form-control" type="text" name="panel_path" placeholder="panel" value="<?= htmlSC((string)($server['panel_path'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_auth_type')) ?></label>
                <?php $authType = (string)($server['api_auth_type'] ?? 'token'); ?>
                <select class="form-select" name="api_auth_type">
                    <option value="token" <?= $authType === 'token' ? 'selected' : '' ?>>Token</option>
                    <option value="password" <?= $authType === 'password' ? 'selected' : '' ?>>Username / Password</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_api_token')) ?></label>
                <input class="form-control" type="password" name="api_token" autocomplete="new-password" value="<?= htmlSC(Crypto::mask($server['api_token_encrypted'] ?? null)) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_secret_hint')) ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_username')) ?></label>
                <input class="form-control" type="password" name="username" autocomplete="new-password" value="<?= htmlSC(Crypto::mask($server['username_encrypted'] ?? null)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_password')) ?></label>
                <input class="form-control" type="password" name="password" autocomplete="new-password" value="<?= htmlSC(Crypto::mask($server['password_encrypted'] ?? null)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_sort_order')) ?></label>
                <input class="form-control" type="number" min="0" step="1" name="sort_order" value="<?= (int)($server['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-md-8 d-flex align-items-end">
                <div class="form-check form-switch py-2">
                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="vpnServerEnabled" <?= (int)($server['is_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="vpnServerEnabled"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_enabled')) ?></label>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_save')) ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/servers') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_cancel')) ?></a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
