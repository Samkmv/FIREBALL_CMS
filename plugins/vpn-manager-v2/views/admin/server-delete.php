<?php
$serverId = (int)$server['id'];
$disabled = empty($server['is_enabled']);
$hasConnections = (int)$counts['connections'] > 0 || (int)$counts['plan_nodes'] > 0 || (int)($counts['presets'] ?? 0) > 0;
$tr = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_remove_server_' . $key));
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<section class="border rounded-5 p-3 p-md-4">
    <h2 class="h4 mb-2"><?= htmlSC((string)$server['name']) ?> <span class="text-body-secondary">#<?= $serverId ?></span></h2>
    <p class="text-body-secondary"><?= $tr('help') ?></p>
    <div class="d-flex flex-wrap gap-3 mb-3">
        <span><?= $tr('plans') ?>: <strong><?= (int)$counts['plan_nodes'] ?></strong></span>
        <span><?= $tr('connections') ?>: <strong><?= (int)$counts['connections'] ?></strong></span>
        <?php if (!empty($counts['presets'])): ?>
            <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_presets_title')) ?> <strong><?= (int)$counts['presets'] ?></strong></span>
        <?php endif; ?>
        <span><?= $tr('subscriptions') ?>: <strong><?= (int)$counts['subscriptions'] ?></strong></span>
    </div>
    <?php if (!$disabled && $hasConnections): ?>
        <div class="alert alert-warning"><?= $tr('disable_first') ?></div>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/toggle')) ?>">
            <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= $serverId ?>">
            <button class="btn btn-outline-warning rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_disable')) ?></button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/delete')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="id" value="<?= $serverId ?>">
            <input type="hidden" name="confirmation" value="delete_vpn_server_<?= $serverId ?>">
            <?php if ($disabled): ?>
                <input type="hidden" name="remove_locally" value="1">
                <input type="hidden" name="fingerprint" value="<?= htmlSC($fingerprint) ?>">
                <div class="alert alert-info"><?= $tr('preserved') ?></div>
                <label class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="server_is_gone" value="1" required>
                    <span class="form-check-label"><?= $tr('ack') ?></span>
                </label>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                    <i class="ci-trash" aria-hidden="true"></i><span><?= $tr('submit') ?></span>
                </button>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers')) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_cancel')) ?></a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?= view()->renderPartial('admin/shell_close') ?>
