<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-dark rounded-pill" href="<?= base_href('/admin/subscriptions/plans/create') ?>"><i class="ci-plus me-2"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_create')) ?></a>
    </div>
    <div class="table-responsive border rounded-4">
        <table class="table align-middle mb-0">
            <thead><tr><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_name')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_price')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_duration')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_status')) ?></th><th class="text-end"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_actions')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($plans as $plan): ?>
                <tr>
                    <td><strong><?= htmlSC((string)$plan['name']) ?></strong><div class="small text-body-secondary"><?= htmlSC((string)$plan['slug']) ?></div></td>
                    <td><?= htmlSC((string)$plan['price_display']) ?></td>
                    <td><?= (int)$plan['duration_value'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit'])) ?></td>
                    <td><span class="badge <?= $plan['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= htmlSC(FireballPluginSubscriptions::t($plan['is_active'] ? 'subscriptions_status_active' : 'subscriptions_status_disabled')) ?></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= base_href('/admin/subscriptions/plans/edit/' . (int)$plan['id']) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_edit')) ?></a>
                            <?php foreach ([['toggle_active', 'subscriptions_toggle'], ['toggle_public', 'subscriptions_visibility'], ['clone', 'subscriptions_clone']] as [$action, $label]): ?>
                                <form action="<?= base_href('/admin/subscriptions/plans/action') ?>" method="post"><?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$plan['id'] ?>"><input type="hidden" name="action" value="<?= $action ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></button></form>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$plans): ?><tr><td colspan="5" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_empty')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
