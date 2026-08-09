<?php

$plans = is_array($plans ?? null) ? $plans : [];

$planActions = static function (array $plan): array {
    $id = (int)$plan['id'];

    return [
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_edit'),
            'href' => base_href('/admin/subscriptions/plans/edit/' . $id),
            'icon' => 'ci-edit',
        ],
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_toggle'),
            'type' => 'form',
            'action' => base_href('/admin/subscriptions/plans/action'),
            'hidden' => ['id' => $id, 'action' => 'toggle_active'],
            'icon' => 'ci-power',
        ],
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_visibility'),
            'type' => 'form',
            'action' => base_href('/admin/subscriptions/plans/action'),
            'hidden' => ['id' => $id, 'action' => 'toggle_public'],
            'icon' => 'ci-eye',
        ],
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_clone'),
            'type' => 'form',
            'action' => base_href('/admin/subscriptions/plans/action'),
            'hidden' => ['id' => $id, 'action' => 'clone'],
            'icon' => 'ci-copy',
        ],
    ];
};

$rows = [];
$mobileCards = [];
foreach ($plans as $plan) {
    $id = (int)$plan['id'];
    $duration = (int)$plan['duration_value'] . ' '
        . FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit']);
    $status = '<span class="badge rounded-pill '
        . (!empty($plan['is_active']) ? 'text-bg-success' : 'text-bg-secondary') . '">'
        . htmlSC(FireballPluginSubscriptions::t(!empty($plan['is_active'])
            ? 'subscriptions_status_active'
            : 'subscriptions_status_disabled'))
        . '</span>';

    ob_start();
    ?>
    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
        <a class="btn btn-sm btn-outline-secondary" href="<?= base_href('/admin/subscriptions/plans/edit/' . $id) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_edit')) ?></a>
        <?php foreach ([['toggle_active', 'subscriptions_toggle'], ['toggle_public', 'subscriptions_visibility'], ['clone', 'subscriptions_clone']] as [$action, $label]): ?>
            <form action="<?= base_href('/admin/subscriptions/plans/action') ?>" method="post">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="<?= htmlSC($action) ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
    <?php
    $desktopActions = (string)ob_get_clean();

    $rows[] = [
        'cells' => [
            ['html' => '<strong>' . htmlSC((string)$plan['name']) . '</strong><div class="small text-body-secondary">' . htmlSC((string)$plan['slug']) . '</div>'],
            ['value' => (string)$plan['price_display']],
            ['value' => $duration],
            ['html' => $status],
            ['html' => $desktopActions, 'class' => 'text-end'],
        ],
    ];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$plan['name'],
        'icon' => 'ci-package',
        'slug' => (string)$plan['slug'],
        'status' => [['html' => $status]],
        'actions' => $planActions($plan),
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_field_price'), 'value' => (string)$plan['price_display']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_field_duration'), 'value' => $duration],
        ],
    ];
}
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-dark rounded-pill" href="<?= base_href('/admin/subscriptions/plans/create') ?>"><i class="ci-plus me-2"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_create')) ?></a>
    </div>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_name')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_price')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_duration')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_status')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_actions'), 'class' => 'text-end'],
            ],
            'rows' => $rows,
            'mobile_cards' => $mobileCards,
            'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
