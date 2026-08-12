<?php
$values = array_replace((array)($plan ?? []), (array)($form_data ?? []));
$permissionValues = is_array($values['permissions'] ?? null) ? $values['permissions'] : [];
$value = static fn(string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
?>
<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="border rounded-4 p-4 p-lg-5" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_name')) ?></label><input class="form-control" id="subscription_plan_name" name="name" value="<?= htmlSC((string)$value('name')) ?>" data-slug-source="#subscription_plan_slug" required></div>
            <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" id="subscription_plan_slug" name="slug" value="<?= htmlSC((string)$value('slug')) ?>" inputmode="url" autocomplete="off" spellcheck="false" autocapitalize="off" lang="en" pattern="[a-z0-9-]+" data-slug-input required><div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_slug_hint')) ?></div></div>
            <div class="col-12"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_description')) ?></label><textarea class="form-control" name="description" rows="4"><?= htmlSC((string)$value('description')) ?></textarea></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_price')) ?></label><input class="form-control" name="price" inputmode="decimal" value="<?= htmlSC(isset($values['price_minor']) ? \Fireball\Subscriptions\Support\Money::decimal((int)$values['price_minor']) : (string)$value('price', '0.00')) ?>" required></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_currency')) ?></label><input class="form-control text-uppercase" name="currency" maxlength="3" value="<?= htmlSC((string)$value('currency', 'RUB')) ?>" required></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_duration')) ?></label><input class="form-control" type="number" name="duration_value" min="1" value="<?= (int)$value('duration_value', 30) ?>" required></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_unit')) ?></label><select class="form-select" name="duration_unit"><option value="days" <?= $value('duration_unit', 'days') === 'days' ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?></option><option value="months" <?= $value('duration_unit') === 'months' ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_months')) ?></option></select></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_sort')) ?></label><input class="form-control" type="number" name="sort_order" min="0" value="<?= (int)$value('sort_order', 0) ?>"></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_grace')) ?></label><input class="form-control" type="number" name="grace_period_days" min="0" value="<?= (int)$value('grace_period_days', 0) ?>"></div>
        </div>
        <hr class="my-4">
        <h2 class="h5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permissions_title')) ?></h2>
        <div class="row g-3">
            <?php foreach (['posts.view_paid', 'videos.view_paid', 'camera_archive.view', 'camera_archive.download'] as $permission): ?>
                <div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[<?= htmlSC($permission) ?>]" value="1" <?= !empty($permissionValues[$permission]) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permission_' . str_replace('.', '_', $permission))) ?></span></label></div>
            <?php endforeach; ?>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_archive_days')) ?></label><input class="form-control" type="number" min="0" name="permissions[camera_archive.max_days]" value="<?= (int)($permissionValues['camera_archive.max_days'] ?? 0) ?>"></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_fragment_minutes')) ?></label><input class="form-control" type="number" min="0" name="permissions[camera_archive.max_fragment_minutes]" value="<?= (int)($permissionValues['camera_archive.max_fragment_minutes'] ?? 0) ?>"></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="all_cameras" value="1" <?= !empty($values['all_cameras']) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_all_cameras')) ?></span></label></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_camera_ids')) ?></label><textarea class="form-control" name="camera_ids" rows="3" placeholder="camera-1, camera-2"><?= htmlSC((string)$value('camera_ids')) ?></textarea></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_camera_group_ids')) ?></label><textarea class="form-control" name="camera_group_ids" rows="3" placeholder="group-1"><?= htmlSC((string)$value('camera_group_ids')) ?></textarea></div>
        </div>
        <hr class="my-4">
        <div class="row g-3">
            <?php foreach ([['is_active', 'subscriptions_field_active', true], ['is_public', 'subscriptions_field_public', true], ['is_recurring', 'subscriptions_field_recurring', false]] as [$key, $label, $default]): ?>
                <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" <?= $value($key, $default) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></span></label></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/subscriptions/plans') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_cancel')) ?></a></div>
    </form>
<?php require __DIR__ . '/shell-close.php'; ?>
