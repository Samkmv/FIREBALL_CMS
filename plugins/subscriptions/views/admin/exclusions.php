<?php
$exclusions = is_array($exclusions ?? null) ? $exclusions : [];
$rows = [];
$mobileCards = [];
foreach ($exclusions as $exclusion) {
    $id = (int)$exclusion['id'];
    $status = '<span class="badge rounded-pill ' . (!empty($exclusion['is_active']) ? 'text-bg-success' : 'text-bg-secondary') . '">'
        . htmlSC(FireballPluginSubscriptions::t(!empty($exclusion['is_active']) ? 'subscriptions_status_active' : 'subscriptions_status_disabled'))
        . '</span>';
    $comment = trim((string)($exclusion['comment'] ?? ''));
    $usersCount = (int)($exclusion['matched_users_count'] ?? 0);

    ob_start();
    ?>
    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlSC(base_href('/admin/subscriptions/exclusions/edit/' . $id)) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_edit')) ?></a>
        <form action="<?= htmlSC(base_href('/admin/subscriptions/exclusions/delete')) ?>" method="post" data-admin-delete-form data-confirm-title="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_delete_title')) ?>" data-delete-message="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_delete_confirm')) ?>" data-delete-item="<?= htmlSC((string)$exclusion['address']) ?>" data-delete-confirm-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_delete')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_delete')) ?></button>
        </form>
    </div>
    <?php
    $actions = (string)ob_get_clean();
    $mobileActions = [
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_edit'),
            'href' => base_href('/admin/subscriptions/exclusions/edit/' . $id),
            'icon' => 'ci-edit',
        ],
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_delete'),
            'icon' => 'ci-trash',
            'type' => 'form',
            'action' => base_href('/admin/subscriptions/exclusions/delete'),
            'hidden' => ['id' => $id],
            'class' => 'text-danger',
            'form_attributes' => [
                'data-admin-delete-form' => true,
                'data-confirm-title' => FireballPluginSubscriptions::t('subscriptions_exclusion_delete_title'),
                'data-delete-message' => FireballPluginSubscriptions::t('subscriptions_exclusion_delete_confirm'),
                'data-delete-item' => (string)$exclusion['address'],
                'data-delete-confirm-label' => FireballPluginSubscriptions::t('subscriptions_delete'),
            ],
        ],
    ];
    $rows[] = ['cells' => [
        ['value' => '#' . $id],
        ['value' => (string)$exclusion['address']],
        ['value' => (string)$exclusion['normalized_address']],
        ['value' => $comment !== '' ? $comment : '—'],
        ['value' => (string)$exclusion['created_at']],
        ['html' => $status],
        ['html' => '<a href="' . htmlSC(base_href('/admin/subscriptions/exclusions/edit/' . $id)) . '">' . $usersCount . '</a>'],
        ['html' => $actions, 'class' => 'text-end'],
    ]];
    $mobileCards[] = [
        'id' => (string)$id,
        'title' => (string)$exclusion['address'],
        'icon' => 'ci-map-pin',
        'status' => [['html' => $status]],
        'actions' => $mobileActions,
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_exclusion_normalized'), 'value' => (string)$exclusion['normalized_address']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_comment'), 'value' => $comment !== '' ? $comment : '—'],
            ['label' => FireballPluginSubscriptions::t('subscriptions_exclusion_matched_users'), 'value' => (string)$usersCount],
            ['label' => FireballPluginSubscriptions::t('subscriptions_date'), 'value' => (string)$exclusion['created_at']],
        ],
    ];
}
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3 subscriptions-table-toolbar">
        <form class="d-flex gap-2 subscriptions-table-search" method="get">
            <input class="form-control" type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" placeholder="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_search')) ?>">
            <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_apply')) ?></button>
        </form>
        <a class="btn btn-dark rounded-pill" href="<?= htmlSC(base_href('/admin/subscriptions/exclusions/create')) ?>"><i class="ci-plus me-2"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_create')) ?></a>
    </div>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => array_map(static fn(string $label): array => ['label' => $label], [
                'ID',
                FireballPluginSubscriptions::t('subscriptions_exclusion_address'),
                FireballPluginSubscriptions::t('subscriptions_exclusion_normalized'),
                FireballPluginSubscriptions::t('subscriptions_comment'),
                FireballPluginSubscriptions::t('subscriptions_date'),
                FireballPluginSubscriptions::t('subscriptions_field_status'),
                FireballPluginSubscriptions::t('subscriptions_exclusion_matched_users'),
                FireballPluginSubscriptions::t('subscriptions_actions'),
            ]),
            'rows' => $rows,
            'mobile_cards' => $mobileCards,
            'empty_text' => FireballPluginSubscriptions::t('subscriptions_exclusions_empty'),
        ]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', ['visible' => count($exclusions), 'total' => (int)($total ?? 0), 'pagination' => $pagination ?? null, 'show_results_label' => false]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
