<?php
$permissionLabel = FireballPluginSubscriptions::t('subscriptions_permission_' . str_replace('.', '_', $permission));
$permissionText = is_array($permissionDetails[$permission] ?? null) ? $permissionDetails[$permission] : [];
$isLimit = in_array($permission, ['camera_archive.max_days', 'camera_archive.max_fragment_minutes'], true);
?>
<div class="col-md-6" data-subscriptions-permission="<?= htmlSC($permission) ?>">
    <div class="border rounded-4 p-3 h-100">
        <?php if ($isLimit): ?>
            <label class="d-block mb-3">
                <span class="form-label d-block"><?= htmlSC($permissionLabel) ?></span>
                <input class="form-control" type="number" min="0" name="permissions[<?= htmlSC($permission) ?>]" value="<?= (int)($permissionValues[$permission] ?? 0) ?>">
            </label>
        <?php else: ?>
            <label class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="permissions[<?= htmlSC($permission) ?>]" value="1" <?= !empty($permissionValues[$permission]) ? 'checked' : '' ?>>
                <span class="form-check-label"><?= htmlSC($permissionLabel) ?></span>
            </label>
        <?php endif; ?>
        <label class="d-block mb-3">
            <span class="form-label d-block"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_title')) ?></span>
            <input class="form-control" name="permission_details[<?= htmlSC($permission) ?>][title]" maxlength="255" placeholder="<?= htmlSC($permissionLabel) ?>" value="<?= htmlSC((string)($permissionText['title'] ?? '')) ?>">
        </label>
        <label class="d-block mb-0">
            <span class="form-label d-block"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_description')) ?></span>
            <textarea class="form-control" name="permission_details[<?= htmlSC($permission) ?>][description]" maxlength="2000" rows="3"><?= htmlSC((string)($permissionText['description'] ?? '')) ?></textarea>
        </label>
    </div>
</div>
