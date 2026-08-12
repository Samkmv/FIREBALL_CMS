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
?>
<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="row g-2 align-items-end mb-3" method="get">
        <div class="col-md-5"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_search')) ?></label><input class="form-control" type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_mode')) ?></label><select class="form-select" name="access"><option value=""><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_filter_all')) ?></option><?php foreach ($accessLabels as $key => $label): ?><option value="<?= htmlSC($key) ?>" <?= (string)($access_filter ?? '') === $key ? 'selected' : '' ?>><?= htmlSC($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-auto"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_apply')) ?></button></div>
    </form>
    <div class="border rounded-5 p-3 p-md-4 admin-table-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_content_post')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_post_access_mode')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_allowed_plans')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>#<?= (int)$post['id'] ?></td>
                        <td><strong><?= htmlSC((string)$post['title']) ?></strong><div class="small text-body-secondary">/posts/<?= htmlSC((string)$post['slug']) ?></div></td>
                        <td colspan="3">
                            <form class="row g-2 align-items-center" method="post" action="<?= base_href('/admin/subscriptions/content') ?>">
                                <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                <div class="col-md-4"><select class="form-select form-select-sm" name="subscription_access_mode"><?php foreach ($accessLabels as $key => $label): ?><option value="<?= htmlSC($key) ?>" <?= (string)$post['access_mode'] === $key ? 'selected' : '' ?>><?= htmlSC($label) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-5"><select class="form-select form-select-sm" name="subscription_plan_ids[]" multiple size="2" aria-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_allowed_plans')) ?>"><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>" <?= in_array((int)$plan['id'], (array)$post['plan_ids'], true) ? 'selected' : '' ?>><?= htmlSC((string)$plan['name']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-3"><button class="btn btn-sm btn-dark rounded-pill w-100" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button></div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$posts): ?><tr><td colspan="5" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_empty')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= view()->renderPartial('admin/partials/table_footer', ['visible' => count($posts), 'total' => (int)($total ?? 0), 'pagination' => $pagination ?? null, 'show_results_label' => false]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
