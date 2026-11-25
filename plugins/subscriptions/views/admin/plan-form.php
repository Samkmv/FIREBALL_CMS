<?php
$values = array_replace((array)($plan ?? []), (array)($form_data ?? []));
$permissionValues = is_array($values['permissions'] ?? null) ? $values['permissions'] : [];
$permissionDetails = is_array($values['permission_details'] ?? null) ? $values['permission_details'] : [];
if (isset($form_data['permission_details_present'])) {
    $permissionDetails = is_array($form_data['permission_details'] ?? null) ? $form_data['permission_details'] : [];
}
$customFeatures = is_array($values['features'] ?? null);
$featureItems = $customFeatures ? $values['features'] : [];
if (isset($form_data['features_present'])) {
    $customFeatures = ($form_data['features_mode'] ?? '') === 'custom';
    $featureItems = is_array($form_data['features'] ?? null) ? $form_data['features'] : [];
} elseif (!$customFeatures) {
    foreach ($permissionValues as $permission => $enabled) {
        if ($enabled) {
            $permissionTitle = trim((string)($permissionDetails[$permission]['title'] ?? ''));
            $featureItems[] = [
                'title' => ($permissionTitle !== '' ? $permissionTitle : FireballPluginSubscriptions::t('subscriptions_permission_' . str_replace('.', '_', $permission))) . (is_int($enabled) ? ' — ' . $enabled : ''),
                'description' => (string)($permissionDetails[$permission]['description'] ?? ''),
            ];
        }
    }
}
$featureItems = array_values($featureItems);
$value = static fn(string $key, mixed $default = ''): mixed => $values[$key] ?? $default;
?>
<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="border rounded-5 p-3 p-md-4" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_name')) ?></label><input class="form-control" id="subscription_plan_name" name="name" value="<?= htmlSC((string)$value('name')) ?>" data-slug-source="#subscription_plan_slug" required></div>
            <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" id="subscription_plan_slug" name="slug" value="<?= htmlSC((string)$value('slug')) ?>" inputmode="url" autocomplete="off" spellcheck="false" autocapitalize="off" lang="en" pattern="[a-z0-9-]+" data-slug-input required><div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_slug_hint')) ?></div></div>
            <div class="col-12"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_description')) ?></label><textarea class="form-control" name="description" rows="4"><?= htmlSC((string)$value('description')) ?></textarea></div>
            <div class="col-12">
                <label class="form-label" for="subscriptionHomepageCategory"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_home_category')) ?></label>
                <select class="form-select" id="subscriptionHomepageCategory" name="homepage_category">
                    <option value=""><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_home_category_none')) ?></option>
                    <?php foreach (\Fireball\Subscriptions\Repositories\PlanRepository::HOMEPAGE_CATEGORIES as $homepageCategory): ?>
                        <option value="<?= htmlSC($homepageCategory) ?>" <?= $value('homepage_category') === $homepageCategory ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_home_category_' . $homepageCategory)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_home_category_hint')) ?></div>
            </div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_price')) ?></label><input class="form-control" name="price" inputmode="decimal" value="<?= htmlSC(isset($values['price_minor']) ? \Fireball\Subscriptions\Support\Money::decimal((int)$values['price_minor']) : (string)$value('price', '0.00')) ?>" required></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_currency')) ?></label><input class="form-control text-uppercase" name="currency" maxlength="3" value="<?= htmlSC((string)$value('currency', 'RUB')) ?>" required></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_duration')) ?></label><input class="form-control" type="number" name="duration_value" min="1" value="<?= (int)$value('duration_value', 30) ?>" required></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_unit')) ?></label><select class="form-select" name="duration_unit"><option value="days" <?= $value('duration_unit', 'days') === 'days' ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?></option><option value="months" <?= $value('duration_unit') === 'months' ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_months')) ?></option></select></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_sort')) ?></label><input class="form-control" type="number" name="sort_order" min="0" value="<?= (int)$value('sort_order', 0) ?>"></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_grace')) ?></label><input class="form-control" type="number" name="grace_period_days" min="0" value="<?= (int)$value('grace_period_days', 0) ?>"></div>
        </div>
        <hr class="my-4">
        <section data-subscriptions-features-editor>
            <h2 class="h5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_features')) ?></h2>
            <p class="text-body-secondary small"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_hint')) ?></p>
            <input type="hidden" name="features_present" value="1">
            <label class="form-label" for="subscriptionFeaturesMode"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_mode')) ?></label>
            <select class="form-select mb-3" id="subscriptionFeaturesMode" name="features_mode" data-features-mode>
                <option value="automatic" <?= !$customFeatures ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_automatic')) ?></option>
                <option value="custom" <?= $customFeatures ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_custom')) ?></option>
            </select>
            <fieldset data-features-fields <?= !$customFeatures ? 'hidden disabled' : '' ?>>
                <div class="vstack gap-3 mb-3" data-features-items>
                    <?php foreach ($featureItems as $index => $feature): ?>
                        <?php require __DIR__ . '/plan-feature-fields.php'; ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-features-add><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_add')) ?></button>
            </fieldset>
            <template data-features-template>
                <?php $index = '__INDEX__'; $feature = []; require __DIR__ . '/plan-feature-fields.php'; ?>
            </template>
        </section>
        <hr class="my-4">
        <h2 class="h5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permissions_title')) ?></h2>
        <p class="text-body-secondary small"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permission_details_hint')) ?></p>
        <input type="hidden" name="permission_details_present" value="1">
        <div class="row g-3">
            <?php foreach (['posts.view_paid', 'videos.view_paid', 'music.listen_paid', 'music.download_paid', 'camera_archive.view', 'camera_archive.download', 'camera_archive.max_days', 'camera_archive.max_fragment_minutes'] as $permission): ?>
                <?php require __DIR__ . '/plan-permission-fields.php'; ?>
            <?php endforeach; ?>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="all_cameras" value="1" <?= !empty($values['all_cameras']) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_all_cameras')) ?></span></label></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_camera_ids')) ?></label><textarea class="form-control" name="camera_ids" rows="3" placeholder="camera-1, camera-2"><?= htmlSC((string)$value('camera_ids')) ?></textarea></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_camera_group_ids')) ?></label><textarea class="form-control" name="camera_group_ids" rows="3" placeholder="group-1"><?= htmlSC((string)$value('camera_group_ids')) ?></textarea></div>
        </div>
        <hr class="my-4">
        <div class="row g-3">
            <?php foreach ([['is_active', 'subscriptions_field_active', true], ['is_public', 'subscriptions_field_public', true], ['auto_renew_enabled', 'subscriptions_field_recurring', false]] as [$key, $label, $default]): ?>
                <div class="col-md-6 col-xl-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" <?= $value($key, $default) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></span></label></div>
            <?php endforeach; ?>
            <div class="col-md-6 col-xl-3">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="is_popular" value="1" <?= $value('is_popular', false) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_popular')) ?></span></label>
                <div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_popular_hint')) ?></div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/subscriptions/plans') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_cancel')) ?></a></div>
    </form>
    <script>
        (() => {
            const editor = document.querySelector('[data-subscriptions-features-editor]');
            const mode = editor.querySelector('[data-features-mode]');
            const fields = editor.querySelector('[data-features-fields]');
            const items = editor.querySelector('[data-features-items]');
            const add = editor.querySelector('[data-features-add]');
            const template = editor.querySelector('[data-features-template]');
            let nextIndex = <?= count($featureItems) ?>;
            mode.addEventListener('change', () => {
                fields.hidden = mode.value !== 'custom';
                fields.disabled = mode.value !== 'custom';
            });
            add.addEventListener('click', () => {
                if (items.children.length >= 50) return;
                const fragment = template.content.cloneNode(true);
                for (const input of fragment.querySelectorAll('[name]')) {
                    input.name = input.name.replace('__INDEX__', String(nextIndex));
                }
                nextIndex++;
                items.append(fragment);
                items.lastElementChild.querySelector('input').focus();
                add.disabled = items.children.length >= 50;
            });
            items.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-features-remove]');
                if (remove) {
                    remove.closest('[data-features-item]').remove();
                    add.disabled = false;
                }
            });
            add.disabled = items.children.length >= 50;
        })();
    </script>
<?php require __DIR__ . '/shell-close.php'; ?>
