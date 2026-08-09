<?php $field = (array)($field ?? []); $selectedPlans = (array)($field['plan_ids'] ?? []); ?>
<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="border rounded-4 p-4 p-lg-5" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_name')) ?></label><input class="form-control" name="label" value="<?= htmlSC((string)($field['label'] ?? '')) ?>" required></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_key')) ?></label><input class="form-control" name="field_key" value="<?= htmlSC((string)($field['field_key'] ?? '')) ?>" <?= !empty($field['is_system']) ? 'readonly' : '' ?> required></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_type')) ?></label><select class="form-select" name="field_type" <?= !empty($field['is_system']) ? 'disabled' : '' ?>><?php foreach ($field_types as $type): ?><option value="<?= htmlSC($type) ?>" <?= ($field['field_type'] ?? 'text') === $type ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_type_' . $type)) ?></option><?php endforeach; ?></select><?php if (!empty($field['is_system'])): ?><input type="hidden" name="field_type" value="<?= htmlSC((string)$field['field_type']) ?>"><?php endif; ?></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_description')) ?></label><textarea class="form-control" name="description" rows="3"><?= htmlSC((string)($field['description'] ?? '')) ?></textarea></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_placeholder')) ?></label><input class="form-control" name="placeholder" value="<?= htmlSC((string)($field['placeholder'] ?? '')) ?>"></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_options')) ?></label><textarea class="form-control" name="options" rows="4"><?= htmlSC(implode("\n", (array)($field['options'] ?? []))) ?></textarea><div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_options_hint')) ?></div></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_validation')) ?></label><textarea class="form-control" name="validation_rules" rows="4"><?= htmlSC((string)($field['validation_rules'] ?? '')) ?></textarea></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_sort')) ?></label><input class="form-control" type="number" name="sort_order" value="<?= (int)($field['sort_order'] ?? 0) ?>" min="0"></div>
            <div class="col-md-9"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_plans')) ?></label><select class="form-select" name="plan_ids[]" multiple><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>" <?= in_array((int)$plan['id'], $selectedPlans, true) ? 'selected' : '' ?>><?= htmlSC((string)$plan['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="row g-3 mt-2">
            <?php foreach ([['is_required', 'subscriptions_field_required'], ['is_active', 'subscriptions_field_active'], ['is_editable', 'subscriptions_field_editable'], ['show_during_checkout', 'subscriptions_field_checkout'], ['use_in_receipt', 'subscriptions_field_receipt']] as [$key, $label]): ?>
                <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" <?= !array_key_exists($key, $field) || !empty($field[$key]) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></span></label></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/subscriptions/profile-fields') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_cancel')) ?></a></div>
    </form>
<?php require __DIR__ . '/shell-close.php'; ?>
