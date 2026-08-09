<?php
$old = static fn(string $key, mixed $fallback = ''): mixed => $form_data[$key] ?? $profile[$key] ?? $fallback;
$customValues = array_replace((array)($profile['custom_values'] ?? []), (array)($form_data['fields'] ?? []));
$returnTo = (string)session()->get('subscriptions.checkout_return', '');
session()->remove('subscriptions.checkout_return');
?>
<section class="container py-5 subscriptions-public">
    <div class="row justify-content-center"><div class="col-xl-9">
        <?php get_alerts(); ?>
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
            <div><h1 class="h3 mb-2"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_profile_title')) ?></h1><p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_profile_subtitle')) ?></p></div>
            <span class="badge rounded-pill <?= $completion['complete'] ? 'text-bg-success' : 'text-bg-warning' ?>"><?= htmlSC(str_replace(':percent', (string)$completion['percent'], FireballPluginSubscriptions::t('subscriptions_profile_percent'))) ?></span>
        </div>
        <?php if ($completion['missing']): ?><div class="alert alert-warning"><strong><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_missing_fields')) ?></strong><ul class="mb-0 mt-2"><?php foreach ($completion['missing'] as $missing): ?><li><?= htmlSC((string)$missing) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form class="border rounded-5 p-4 p-lg-5" method="post" novalidate>
            <?= get_csrf_field() ?>
            <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= htmlSC($returnTo) ?>"><?php endif; ?>
            <div class="row g-3">
                <?php foreach ($fields as $field): ?>
                    <?php if (!$field['is_editable']) continue; $key = (string)$field['field_key']; $name = $field['is_system'] ? $key : 'fields[' . $key . ']'; $fieldValue = $field['is_system'] ? $old($key) : ($customValues[$key] ?? ''); ?>
                    <div class="<?= $field['field_type'] === 'textarea' ? 'col-12' : 'col-md-6' ?>">
                        <?php if (in_array($field['field_type'], ['checkbox', 'boolean'], true)): ?>
                            <label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="<?= htmlSC($name) ?>" value="1" <?= !empty($fieldValue) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC((string)$field['label']) ?></span></label>
                        <?php else: ?>
                            <label class="form-label"><?= htmlSC((string)$field['label']) ?><?= $field['is_required'] ? ' *' : '' ?></label>
                            <?php if ($field['field_type'] === 'textarea'): ?><textarea class="form-control" name="<?= htmlSC($name) ?>" rows="4" placeholder="<?= htmlSC((string)$field['placeholder']) ?>" <?= $field['is_required'] ? 'required' : '' ?>><?= htmlSC((string)$fieldValue) ?></textarea>
                            <?php elseif (in_array($field['field_type'], ['select', 'radio'], true)): ?><select class="form-select" name="<?= htmlSC($name) ?>" <?= $field['is_required'] ? 'required' : '' ?>><option value=""></option><?php foreach ($field['options'] as $option): ?><option value="<?= htmlSC((string)$option) ?>" <?= (string)$fieldValue === (string)$option ? 'selected' : '' ?>><?= htmlSC((string)$option) ?></option><?php endforeach; ?></select>
                            <?php else: ?><input class="form-control" type="<?= in_array($field['field_type'], ['email', 'number', 'date'], true) ? htmlSC((string)$field['field_type']) : 'text' ?>" name="<?= htmlSC($name) ?>" value="<?= htmlSC((string)$fieldValue) ?>" placeholder="<?= htmlSC((string)$field['placeholder']) ?>" <?= $field['is_required'] ? 'required' : '' ?>><?php endif; ?>
                            <?php if ($field['description']): ?><div class="form-text"><?= htmlSC((string)$field['description']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-dark rounded-pill mt-4" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button>
        </form>
    </div></div>
</section>
