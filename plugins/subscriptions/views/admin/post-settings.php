<?php
$mode = (string)($form_data['subscription_access_mode'] ?? $rule['access_mode'] ?? 'public');
$selectedPlans = array_values(array_unique(array_map('intval', (array)($form_data['subscription_plan_ids'] ?? $rule['plan_ids'] ?? []))));
?>
<div class="subscriptions-post-settings border-top mt-3 pt-3">
    <h3 class="h6"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_title')) ?></h3>
    <label class="fb-editor-field"><span><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_mode')) ?></span><select class="form-select" name="subscription_access_mode" data-subscriptions-post-access-mode><?php foreach (['public', 'authenticated', 'subscribers', 'plans', 'permission'] as $item): ?><option value="<?= $item ?>" <?= $mode === $item ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_access_mode_' . $item)) ?></option><?php endforeach; ?></select><small class="form-text text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_hint')) ?></small></label>
    <div class="alert alert-info py-2 px-3 small" role="note"><i class="ci-video me-2" aria-hidden="true"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_video_access_independent_hint')) ?></div>
    <fieldset class="subscriptions-plan-picker" data-subscriptions-post-plans <?= $mode === 'plans' ? '' : 'hidden' ?>>
        <legend><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_allowed_plans')) ?></legend>
        <?php if (!$plans): ?>
            <p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_no_public_plans')) ?></p>
        <?php else: ?>
            <div class="subscriptions-plan-picker__options">
                <?php foreach ($plans as $plan): ?>
                    <?php $planId = (int)$plan['id']; ?>
                    <label class="subscriptions-plan-choice">
                        <input class="form-check-input" type="checkbox" name="subscription_plan_ids[]" value="<?= $planId ?>" <?= in_array($planId, $selectedPlans, true) ? 'checked' : '' ?>>
                        <span class="subscriptions-plan-choice__name"><?= htmlSC((string)$plan['name']) ?></span>
                        <span class="subscriptions-plan-choice__mark" aria-hidden="true"><i class="ci-check"></i></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>
    <label class="fb-editor-field" data-subscriptions-post-permission <?= $mode === 'permission' ? '' : 'hidden' ?>><span><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_required_permission')) ?></span><input class="form-control" name="subscription_required_permission" value="<?= htmlSC((string)($form_data['subscription_required_permission'] ?? $rule['required_permission'] ?? 'posts.view_paid')) ?>"></label>
    <?php foreach ([['subscription_show_title', 'show_title', 'subscriptions_show_title'], ['subscription_show_excerpt', 'show_excerpt', 'subscriptions_show_excerpt'], ['subscription_show_image', 'show_image', 'subscriptions_show_image']] as [$name, $column, $label]): ?>
        <?php $checked = !empty($form_data[$name] ?? $rule[$column] ?? true); ?>
        <label class="fb-editor-check"><input type="checkbox" name="<?= $name ?>" value="1" <?= $checked ? 'checked' : '' ?>><span><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></span></label>
    <?php endforeach; ?>
</div>
