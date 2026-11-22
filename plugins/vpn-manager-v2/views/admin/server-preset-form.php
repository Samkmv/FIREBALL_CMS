<?php
$t = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_' . $key));
$base = base_href('/admin/plugins/vpn-manager-v2/servers/presets');
$action = $base . ($preset ? '/' . (int)$preset['id'] : '/create');
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2 mb-3" href="<?= htmlSC($base) ?>">
    <i class="ci-arrow-left" aria-hidden="true"></i><?= $t('presets_title') ?>
</a>
<?php if ($linkedPlans !== []): ?>
    <div class="fb-card p-3 mb-3">
        <p class="small mb-1"><?= $t('preset_edit_help') ?></p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($linkedPlans as $linked): ?>
                <a class="fw-semibold text-break" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/edit/' . (int)$linked['id'])) ?>"><?= htmlSC($linked['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<form method="post" action="<?= htmlSC($action) ?>">
    <?= get_csrf_field() ?>
    <div class="fb-card p-3">
        <label class="form-label fw-semibold" for="vpnPresetName"><?= $t('preset_name') ?></label>
        <input class="form-control" id="vpnPresetName" name="name" required maxlength="255" value="<?= htmlSC($preset['name'] ?? '') ?>">
    </div>

    <?php $presetEditor = true; require __DIR__ . '/partials/plan-node-picker.php'; ?>

    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit"><?= $t('save') ?></button>
        <a class="btn btn-outline-secondary" href="<?= htmlSC($base) ?>"><?= $t('cancel') ?></a>
    </div>
</form>
<?= view()->renderPartial('admin/shell_close') ?>
