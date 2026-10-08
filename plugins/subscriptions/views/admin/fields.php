<?php

$fields = is_array($fields ?? null) ? $fields : [];
$renderActions = require __DIR__ . '/action-dropdown.php';
$headerActions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/subscriptions/profile-fields/create')) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
    . htmlSC(FireballPluginSubscriptions::t('subscriptions_field_create')) . '</span></a>';
$rows = [];
$mobileCards = [];

foreach ($fields as $field) {
    $id = (int)$field['id'];
    $fieldName = htmlSC((string)$field['label'])
        . (!empty($field['is_required']) ? ' <span class="text-danger">*</span>' : '');
    $status = (!empty($field['is_system'])
            ? '<span class="badge rounded-pill text-bg-info">' . htmlSC(FireballPluginSubscriptions::t('subscriptions_system')) . '</span> '
            : '')
        . '<span class="badge rounded-pill '
        . (!empty($field['is_active']) ? 'text-bg-success' : 'text-bg-secondary') . '">'
        . htmlSC(FireballPluginSubscriptions::t(!empty($field['is_active'])
            ? 'subscriptions_status_active'
            : 'subscriptions_status_disabled'))
        . '</span>';

    $actions = [
        [
            'label' => FireballPluginSubscriptions::t('subscriptions_edit'),
            'href' => base_href('/admin/subscriptions/profile-fields/edit/' . $id),
            'icon' => 'ci-edit',
        ],
    ];
    if (empty($field['is_system'])) {
        $actions[] = ['type' => 'divider'];
        $actions[] = [
            'label' => FireballPluginSubscriptions::t('subscriptions_delete'),
            'type' => 'form',
            'action' => base_href('/admin/subscriptions/profile-fields/delete'),
            'hidden' => ['id' => $id],
            'icon' => 'ci-trash',
            'class' => 'text-danger',
        ];
    }

    $desktopActions = $renderActions($actions);

    $rows[] = [
        'cells' => [
            ['html' => $fieldName],
            ['html' => '<code>' . htmlSC((string)$field['field_key']) . '</code>'],
            ['value' => (string)$field['field_type']],
            ['html' => $status],
            ['html' => $desktopActions, 'class' => 'text-end'],
        ],
    ];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => ['html' => $fieldName],
        'icon' => 'ci-list',
        'slug' => (string)$field['field_key'],
        'slug_label' => FireballPluginSubscriptions::t('subscriptions_field_key'),
        'status' => [['html' => $status]],
        'actions' => $actions,
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_field_type'), 'value' => (string)$field['field_type']],
        ],
    ];
}
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_name')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_key')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_type')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_status')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_actions'), 'class' => 'text-end'],
            ],
            'rows' => $rows,
            'mobile_cards' => $mobileCards,
            'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
