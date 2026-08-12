<?php
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$plans = is_array($plans ?? null) ? $plans : [];
$users = is_array($users ?? null) ? $users : [];
$statusLabels = [
    'active' => FireballPluginSubscriptions::t('subscriptions_status_active'),
    'disabled' => FireballPluginSubscriptions::t('subscriptions_status_disabled'),
    'pending' => FireballPluginSubscriptions::t('subscriptions_status_pending'),
    'cancelled' => FireballPluginSubscriptions::t('subscriptions_status_cancelled'),
    'grace_period' => FireballPluginSubscriptions::t('subscriptions_status_grace_period'),
    'past_due' => FireballPluginSubscriptions::t('subscriptions_status_past_due'),
    'expired' => FireballPluginSubscriptions::t('subscriptions_status_expired'),
];
$sourceLabels = [
    'manual' => FireballPluginSubscriptions::t('subscriptions_source_manual'),
    'external' => FireballPluginSubscriptions::t('subscriptions_source_external'),
    'robokassa' => 'Robokassa',
];
$rows = [];
$mobileCards = [];

foreach ($subscriptions as $subscription) {
    $statusKey = (string)($subscription['status'] ?? '');
    $status = '<span class="badge rounded-pill ' . ($statusKey === 'active' ? 'text-bg-success' : 'text-bg-secondary') . '">' . htmlSC($statusLabels[$statusKey] ?? $statusKey) . '</span>';
    $user = htmlSC((string)$subscription['user_name']) . '<div class="small text-body-secondary">' . htmlSC((string)$subscription['user_email']) . '</div>';
    $period = htmlSC((string)$subscription['starts_at']) . '<br>' . htmlSC((string)$subscription['ends_at']);
    ob_start();
    ?>
    <details class="subscriptions-inline-editor">
        <summary class="btn btn-sm btn-outline-secondary rounded-pill"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_edit')) ?></summary>
        <form class="mt-2 d-grid gap-2" method="post" action="<?= base_href('/admin/subscriptions/subscribers/update') ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$subscription['id'] ?>">
            <select class="form-select form-select-sm" name="plan_id"><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>" <?= (int)$plan['id'] === (int)$subscription['plan_id'] ? 'selected' : '' ?>><?= htmlSC((string)$plan['name']) ?></option><?php endforeach; ?></select>
            <select class="form-select form-select-sm" name="status"><option value="active" <?= $statusKey === 'active' ? 'selected' : '' ?>><?= htmlSC($statusLabels['active']) ?></option><option value="disabled" <?= $statusKey === 'disabled' ? 'selected' : '' ?>><?= htmlSC($statusLabels['disabled']) ?></option></select>
            <input class="form-control form-control-sm" type="datetime-local" name="ends_at" value="<?= htmlSC(date('Y-m-d\TH:i', strtotime((string)$subscription['ends_at']) ?: time())) ?>" required>
            <button class="btn btn-sm btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button>
        </form>
    </details>
    <?php
    $actions = trim((string)ob_get_clean());
    $source = $sourceLabels[(string)($subscription['source'] ?? '')] ?? (string)($subscription['source'] ?? '');
    $rows[] = ['cells' => [
        ['value' => '#' . (int)$subscription['id']], ['html' => $user], ['value' => (string)$subscription['plan_name']],
        ['html' => $status], ['value' => $source], ['html' => $period], ['html' => $actions],
    ]];
    $mobileCards[] = [
        'id' => (string)(int)$subscription['id'], 'title' => (string)$subscription['user_name'], 'icon' => 'ci-user',
        'status' => [['html' => $status]], 'actions' => $actions,
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_user'), 'value' => (string)$subscription['user_email']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_plan'), 'value' => (string)$subscription['plan_name']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_source'), 'value' => $source],
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
            <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_user')) ?></label><select class="form-select" name="user_id" required><option value=""><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_choose_user')) ?></option><?php foreach ($users as $userOption): ?><option value="<?= (int)$userOption['id'] ?>"><?= htmlSC((string)$userOption['name']) ?> — <?= htmlSC((string)$userOption['email']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></label><select class="form-select" name="plan_id" required><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>"><?= htmlSC((string)$plan['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_duration')) ?></label><input class="form-control" type="number" name="duration_value" value="30" min="1"></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_unit')) ?></label><select class="form-select" name="duration_unit"><option value="days"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?></option><option value="months"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_months')) ?></option></select></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_source')) ?></label><select class="form-select" name="source"><option value="manual"><?= htmlSC($sourceLabels['manual']) ?></option><option value="external"><?= htmlSC($sourceLabels['external']) ?></option></select></div>
            <div class="col-md-9"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_comment')) ?></label><input class="form-control" name="comment"></div>
            <div class="col-12"><button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_grant')) ?></button></div>
        </form>
    </details>

    <form class="row g-2 align-items-end mb-3" method="get">
        <div class="col-md-5"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_search')) ?></label><input class="form-control" type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>"></div>
        <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_status')) ?></label><select class="form-select" name="status"><option value=""><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_filter_all')) ?></option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= htmlSC($key) ?>" <?= (string)($status_filter ?? '') === $key ? 'selected' : '' ?>><?= htmlSC($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-auto"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_apply')) ?></button></div>
    </form>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => array_map(static fn(string $label): array => ['label' => $label], ['ID', FireballPluginSubscriptions::t('subscriptions_user'), FireballPluginSubscriptions::t('subscriptions_plan'), FireballPluginSubscriptions::t('subscriptions_field_status'), FireballPluginSubscriptions::t('subscriptions_source'), FireballPluginSubscriptions::t('subscriptions_period'), FireballPluginSubscriptions::t('subscriptions_actions')]),
            'rows' => $rows, 'mobile_cards' => $mobileCards, 'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', ['visible' => count($subscriptions), 'total' => (int)($total ?? 0), 'pagination' => $pagination ?? null, 'show_results_label' => false]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
