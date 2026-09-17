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
    $popularBadge = !empty($plan['is_popular'])
        ? ' <span class="badge rounded-pill text-primary bg-primary-subtle"><i class="ci-star-filled me-1"></i>'
            . htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_popular')) . '</span>'
        : '';
    $actions = $planActions($plan);

    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false">
            <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_actions')) ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <?php foreach ($actions as $action): ?>
                <?php if (($action['type'] ?? 'link') === 'form'): ?>
                    <form action="<?= htmlSC((string)$action['action']) ?>" method="post">
                        <?= get_csrf_field() ?>
                        <?php foreach ($action['hidden'] as $name => $value): ?><input type="hidden" name="<?= htmlSC((string)$name) ?>" value="<?= htmlSC((string)$value) ?>"><?php endforeach; ?>
                        <button class="dropdown-item d-flex align-items-center gap-2" type="submit">
                            <i class="<?= htmlSC((string)$action['icon']) ?>" aria-hidden="true"></i>
                            <span><?= htmlSC((string)$action['label']) ?></span>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= htmlSC((string)$action['href']) ?>">
                        <i class="<?= htmlSC((string)$action['icon']) ?>" aria-hidden="true"></i>
                        <span><?= htmlSC((string)$action['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $desktopActions = (string)ob_get_clean();

    $rows[] = [
        'cells' => [
            ['html' => '<div class="d-flex align-items-center flex-wrap gap-2"><strong>' . htmlSC((string)$plan['name']) . '</strong>' . $popularBadge . '</div><div class="small text-body-secondary">' . htmlSC((string)$plan['slug']) . '</div>'],
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
        'status' => array_values(array_filter([
            ['html' => $status],
            $popularBadge !== '' ? ['html' => $popularBadge] : null,
        ])),
        'actions' => $actions,
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
