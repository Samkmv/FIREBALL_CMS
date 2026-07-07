<?php
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/support/subjects/create'))
    . '"><i class="ci-plus"></i>'
    . htmlSC(return_translation('admin_contact_subject_create'))
    . '</a>';
$renderActions = static function (array $subject, bool $isActive): string {
    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
        <button
            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
            type="button"
            data-bs-toggle="dropdown"
            data-bs-display="static"
            data-bs-boundary="viewport"
            aria-expanded="false"
            aria-label="<?= htmlSC(return_translation('admin_contact_subject_col_actions')) ?>"
        >
            <i class="ci-more-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <a
                class="dropdown-item d-flex align-items-center gap-2"
                href="<?= base_href('/admin/support/subjects/edit/' . (int)$subject['id']) ?>"
            >
                <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
            </a>
            <form action="<?= base_href('/admin/support/subjects/toggle') ?>" method="post">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$subject['id'] ?>">
                <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                <button class="dropdown-item d-flex align-items-center gap-2" type="submit">
                    <i class="<?= $isActive ? 'ci-eye-off' : 'ci-check' ?>"></i>
                    <span><?= print_translation($isActive
                            ? 'admin_contact_subject_disable'
                            : 'admin_contact_subject_enable') ?></span>
                </button>
            </form>
            <form
                action="<?= base_href('/admin/support/subjects/delete') ?>"
                method="post"
                data-admin-delete-form
                data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_contact_subject')) ?>"
                data-delete-item="<?= htmlSC($subject['name']) ?>"
            >
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$subject['id'] ?>">
                <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit">
                    <i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span>
                </button>
            </form>
        </div>
    </div>
    <?php

    return trim((string)ob_get_clean());
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_contact_subjects_heading'),
    'subtitle' => return_translation('admin_contact_subjects_subtitle'),
    'actions' => $actions,
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'subjects']) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="contact-subjects">
        <?php $mobileCards = []; ?>
        <?php ob_start(); ?>
            <thead class="position-sticky top-0">
                <tr>
                    <th scope="col"><?= print_translation('admin_contact_subject_col_id') ?></th>
                    <th scope="col"><?= print_translation('admin_contact_subject_col_name') ?></th>
                    <th scope="col"><?= print_translation('admin_contact_subject_col_status') ?></th>
                    <th scope="col"><?= print_translation('admin_contact_subject_col_sort_order') ?></th>
                    <th scope="col"><?= print_translation('admin_contact_subject_col_created_at') ?></th>
                    <th scope="col" class="text-end"><?= print_translation('admin_contact_subject_col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <?= print_translation('admin_table_empty') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $subject): ?>
                        <?php
                        $isActive = (int)$subject['is_active'] === 1;
                        $statusLabel = return_translation($isActive
                            ? 'admin_contact_subject_status_active'
                            : 'admin_contact_subject_status_inactive');
                        $statusClass = $isActive ? 'text-success bg-success-subtle' : 'text-secondary bg-secondary-subtle';
                        $actionsHtml = $renderActions($subject, $isActive);
                        $mobileCards[] = [
                            'id' => (int)$subject['id'],
                            'title' => (string)$subject['name'],
                            'order' => (int)$subject['sort_order'],
                            'order_label' => return_translation('admin_contact_subject_col_sort_order'),
                            'status' => [[
                                'label' => $statusLabel,
                                'class' => $statusClass,
                            ]],
                            'status_label' => return_translation('admin_contact_subject_col_status'),
                            'published_at' => date('d.m.Y H:i', strtotime((string)$subject['created_at'])),
                            'published_at_label' => return_translation('admin_contact_subject_col_created_at'),
                            'actions' => $actionsHtml,
                        ];
                        ?>
                        <tr>
                            <td class="text-body-secondary">#<?= (int)$subject['id'] ?></td>
                            <td class="fw-medium text-break"><?= htmlSC($subject['name']) ?></td>
                            <td>
                                <span class="badge fs-xs <?= htmlSC($statusClass) ?> rounded-pill">
                                    <?= htmlSC($statusLabel) ?>
                                </span>
                            </td>
                            <td><?= (int)$subject['sort_order'] ?></td>
                            <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime((string)$subject['created_at']))) ?></td>
                            <td class="text-nowrap text-end">
                                <?= $actionsHtml ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        <?php $adminTableContent = ob_get_clean(); ?>
        <?= view()->renderPartial('admin/partials/table', [
            'content' => $adminTableContent,
            'mobile_cards' => $mobileCards,
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
