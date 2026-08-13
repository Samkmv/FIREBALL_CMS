<?php
$posts = is_array($posts ?? null) ? $posts : [];
$plans = is_array($plans ?? null) ? $plans : [];
$accessLabels = [
    'public' => FireballPluginSubscriptions::t('subscriptions_access_mode_public'),
    'authenticated' => FireballPluginSubscriptions::t('subscriptions_access_mode_authenticated'),
    'subscribers' => FireballPluginSubscriptions::t('subscriptions_access_mode_subscribers'),
    'plans' => FireballPluginSubscriptions::t('subscriptions_access_mode_plans'),
    'permission' => FireballPluginSubscriptions::t('subscriptions_access_mode_permission'),
];

$renderAccessFields = static function (array $post, array $plans, array $accessLabels, bool $mobile = false): string {
    $fieldSuffix = $mobile ? 'mobile' : 'desktop';
    $postId = (int)$post['id'];
    $selectedPlans = array_values(array_unique(array_map('intval', (array)($post['plan_ids'] ?? []))));
    $plansVisible = (string)($post['access_mode'] ?? 'public') === 'plans';
    $disabled = $mobile ? 'disabled' : '';
    ob_start();
    ?>
    <div class="subscriptions-content-access-form<?= $mobile ? ' subscriptions-content-access-form--mobile' : ' row g-2 align-items-center' ?>" data-subscriptions-content-entry data-subscriptions-content-id="<?= $postId ?>" data-subscriptions-content-layout="<?= $mobile ? 'mobile' : 'desktop' ?>">
        <div class="<?= $mobile ? '' : 'col-md-5' ?>">
            <?php if ($mobile): ?><label class="form-label small fw-semibold" for="subscriptions-content-mode-<?= $fieldSuffix ?>-<?= $postId ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_mode')) ?></label><?php endif; ?>
            <select class="form-select form-select-sm" id="subscriptions-content-mode-<?= $fieldSuffix ?>-<?= $postId ?>" name="content[<?= $postId ?>][subscription_access_mode]" data-subscriptions-content-mode <?= $disabled ?>>
                <?php foreach ($accessLabels as $key => $label): ?>
                    <option value="<?= htmlSC($key) ?>" <?= (string)$post['access_mode'] === $key ? 'selected' : '' ?>><?= htmlSC($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <fieldset class="subscriptions-plan-picker subscriptions-content-plan-picker <?= $mobile ? '' : 'col-md-7' ?>" data-subscriptions-content-plans <?= $plansVisible ? '' : 'hidden' ?>>
            <legend class="<?= $mobile ? 'form-label small fw-semibold' : 'visually-hidden' ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_allowed_plans')) ?></legend>
            <?php if (!$plans): ?>
                <p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_no_public_plans')) ?></p>
            <?php else: ?>
                <div class="subscriptions-plan-picker__options subscriptions-plan-picker__options--compact">
                    <?php foreach ($plans as $plan): ?>
                        <?php $planId = (int)$plan['id']; ?>
                        <label class="subscriptions-plan-choice">
                            <input class="form-check-input" type="checkbox" name="content[<?= $postId ?>][subscription_plan_ids][]" value="<?= $planId ?>" <?= in_array($planId, $selectedPlans, true) ? 'checked' : '' ?> <?= $disabled ?>>
                            <span class="subscriptions-plan-choice__name"><?= htmlSC((string)$plan['name']) ?></span>
                            <span class="subscriptions-plan-choice__mark" aria-hidden="true"><i class="ci-check"></i></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>
    </div>
    <?php

    return trim((string)ob_get_clean());
};
?>
<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="row g-2 align-items-end mb-3 subscriptions-filter-form" method="get">
        <div class="col-md-5"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_search')) ?></label><input class="form-control" type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_mode')) ?></label><select class="form-select" name="access"><option value=""><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_filter_all')) ?></option><?php foreach ($accessLabels as $key => $label): ?><option value="<?= htmlSC($key) ?>" <?= (string)($access_filter ?? '') === $key ? 'selected' : '' ?>><?= htmlSC($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-auto"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_apply')) ?></button></div>
    </form>

    <form method="post" action="<?= base_href('/admin/subscriptions/content') ?>" data-subscriptions-content-batch-form>
        <?= get_csrf_field() ?>
        <div class="subscriptions-content-save-bar d-flex justify-content-end mb-3">
            <button class="btn btn-dark rounded-pill" type="submit" <?= !$posts ? 'disabled' : '' ?>>
                <i class="ci-save me-2" aria-hidden="true"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?>
            </button>
        </div>
        <div class="border rounded-5 p-3 p-md-4 admin-table-card subscriptions-content-table" data-admin-table>
        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_content_post')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_mode')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_allowed_plans')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>#<?= (int)$post['id'] ?></td>
                        <td><strong><?= htmlSC((string)$post['title']) ?></strong><div class="small text-body-secondary">/posts/<?= htmlSC((string)$post['slug']) ?></div></td>
                        <td colspan="2">
                            <?= $renderAccessFields($post, $plans, $accessLabels) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$posts): ?><tr><td colspan="4" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_empty')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="subscriptions-content-cards d-md-none" data-admin-mobile-table-cards>
            <?php if (!$posts): ?>
                <div class="card subscriptions-content-card shadow-sm">
                    <div class="card-body p-4 text-center text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_empty')) ?></div>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="card subscriptions-content-card shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="subscriptions-content-card__icon rounded-circle border bg-body-tertiary d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true"><i class="ci-file-text text-body-secondary"></i></span>
                                <div class="min-w-0">
                                    <div class="small text-body-secondary mb-1">ID: <span class="fw-semibold text-body">#<?= (int)$post['id'] ?></span></div>
                                    <h3 class="h6 mb-1 text-break"><?= htmlSC((string)$post['title']) ?></h3>
                                    <div class="small text-body-secondary text-break">/posts/<?= htmlSC((string)$post['slug']) ?></div>
                                </div>
                            </div>
                            <?= $renderAccessFields($post, $plans, $accessLabels, true) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?= view()->renderPartial('admin/partials/table_footer', ['visible' => count($posts), 'total' => (int)($total ?? 0), 'pagination' => $pagination ?? null, 'show_results_label' => false]) ?>
        </div>
    </form>

    <script>
        (() => {
            const batchForm = document.querySelector('[data-subscriptions-content-batch-form]');
            if (!(batchForm instanceof HTMLFormElement)) {
                return;
            }

            batchForm.querySelectorAll('[data-subscriptions-content-entry]').forEach((entry) => {
                const mode = entry.querySelector('[data-subscriptions-content-mode]');
                const plans = entry.querySelector('[data-subscriptions-content-plans]');
                if (!(mode instanceof HTMLSelectElement) || !(plans instanceof HTMLFieldSetElement)) {
                    return;
                }

                const syncPlanVisibility = () => {
                    plans.hidden = mode.value !== 'plans';
                };

                mode.addEventListener('change', syncPlanVisibility);
                syncPlanVisibility();
            });

            batchForm.addEventListener('change', (event) => {
                const source = event.target;
                if (!(source instanceof HTMLInputElement) && !(source instanceof HTMLSelectElement)) {
                    return;
                }

                const sourceEntry = source.closest('[data-subscriptions-content-entry]');
                if (!sourceEntry || !source.name) {
                    return;
                }

                const contentId = sourceEntry.getAttribute('data-subscriptions-content-id');
                const sourceLayout = sourceEntry.getAttribute('data-subscriptions-content-layout');
                const targetLayout = sourceLayout === 'mobile' ? 'desktop' : 'mobile';
                const targetEntry = Array.from(batchForm.querySelectorAll('[data-subscriptions-content-entry]')).find((entry) => (
                    entry.getAttribute('data-subscriptions-content-id') === contentId
                    && entry.getAttribute('data-subscriptions-content-layout') === targetLayout
                ));
                if (!targetEntry) {
                    return;
                }

                const target = Array.from(targetEntry.querySelectorAll('input, select')).find((control) => (
                    control.name === source.name
                    && (!(source instanceof HTMLInputElement) || source.type !== 'checkbox' || control.value === source.value)
                ));
                if (!target) {
                    return;
                }

                if (source instanceof HTMLInputElement && source.type === 'checkbox' && target instanceof HTMLInputElement) {
                    target.checked = source.checked;
                } else {
                    target.value = source.value;
                    const targetPlans = targetEntry.querySelector('[data-subscriptions-content-plans]');
                    if (target instanceof HTMLSelectElement && targetPlans instanceof HTMLFieldSetElement) {
                        targetPlans.hidden = target.value !== 'plans';
                    }
                }
            });

            const mobileViewport = window.matchMedia('(max-width: 767.98px)');
            const syncActiveLayout = () => {
                const activeLayout = mobileViewport.matches ? 'mobile' : 'desktop';
                batchForm.querySelectorAll('[data-subscriptions-content-layout]').forEach((layout) => {
                    const isActive = layout.getAttribute('data-subscriptions-content-layout') === activeLayout;
                    layout.querySelectorAll('input, select, textarea, button').forEach((control) => {
                        control.disabled = !isActive;
                    });
                });
            };

            if (typeof mobileViewport.addEventListener === 'function') {
                mobileViewport.addEventListener('change', syncActiveLayout);
            } else if (typeof mobileViewport.addListener === 'function') {
                mobileViewport.addListener(syncActiveLayout);
            }
            syncActiveLayout();
        })();
    </script>
<?php require __DIR__ . '/shell-close.php'; ?>
