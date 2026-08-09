<?php

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$plans = is_array($plans ?? null) ? $plans : [];
$rows = [];
$mobileCards = [];

foreach ($subscriptions as $subscription) {
    $status = '<span class="badge rounded-pill text-bg-secondary">' . htmlSC((string)$subscription['status']) . '</span>';
    $user = htmlSC((string)$subscription['user_name'])
        . '<div class="small text-body-secondary">' . htmlSC((string)$subscription['user_email']) . '</div>';
    $period = htmlSC((string)$subscription['starts_at']) . '<br>' . htmlSC((string)$subscription['ends_at']);

    $rows[] = [
        'cells' => [
            ['value' => '#' . (int)$subscription['id']],
            ['html' => $user],
            ['value' => (string)$subscription['plan_name']],
            ['html' => $status],
            ['html' => $period],
        ],
    ];

    $mobileCards[] = [
        'id' => (string)(int)$subscription['id'],
        'title' => (string)$subscription['user_name'],
        'icon' => 'ci-user',
        'status' => [['html' => $status]],
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_user'), 'value' => (string)$subscription['user_email']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_plan'), 'value' => (string)$subscription['plan_name']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_period'), 'html' => $period],
        ],
    ];
}
?>

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

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginSubscriptions::t('subscriptions_user')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_plan')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_status')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_period')],
            ],
            'rows' => $rows,
            'mobile_cards' => $mobileCards,
            'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
