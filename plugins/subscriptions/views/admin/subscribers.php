<?php require __DIR__ . '/shell-open.php'; ?>
    <details class="border rounded-4 p-4 mb-4">
        <summary class="fw-semibold"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_grant_title')) ?></summary>
        <form class="row g-3 mt-2" action="<?= base_href('/admin/subscriptions/subscribers/grant') ?>" method="post">
            <?= get_csrf_field() ?>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_user_id')) ?></label><input class="form-control" type="number" name="user_id" min="1" required></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></label><select class="form-select" name="plan_id" required><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>"><?= htmlSC((string)$plan['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_duration')) ?></label><input class="form-control" type="number" name="duration_value" value="30" min="1"></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_unit')) ?></label><select class="form-select" name="duration_unit"><option value="days"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?></option><option value="months"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_months')) ?></option></select></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_comment')) ?></label><input class="form-control" name="comment"></div>
            <div class="col-12"><button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_grant')) ?></button></div>
        </form>
    </details>
    <div class="table-responsive border rounded-4"><table class="table align-middle mb-0"><thead><tr><th>ID</th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_user')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_status')) ?></th><th><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_period')) ?></th></tr></thead><tbody>
        <?php foreach ($subscriptions as $subscription): ?><tr><td>#<?= (int)$subscription['id'] ?></td><td><?= htmlSC((string)$subscription['user_name']) ?><div class="small text-body-secondary"><?= htmlSC((string)$subscription['user_email']) ?></div></td><td><?= htmlSC((string)$subscription['plan_name']) ?></td><td><span class="badge text-bg-secondary"><?= htmlSC((string)$subscription['status']) ?></span></td><td><?= htmlSC((string)$subscription['starts_at']) ?><br><?= htmlSC((string)$subscription['ends_at']) ?></td></tr><?php endforeach; ?>
        <?php if (!$subscriptions): ?><tr><td colspan="5" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_empty')) ?></td></tr><?php endif; ?>
    </tbody></table></div>
<?php require __DIR__ . '/shell-close.php'; ?>
