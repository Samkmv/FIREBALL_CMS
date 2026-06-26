<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};
$canManageRoles = check_creator();
?>
<?php ob_start(); ?>
<?php if ($canManageRoles): ?>
    <a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/roles/create') ?>"><i class="ci-plus"></i><?= print_translation('admin_roles_create') ?></a>
<?php endif; ?>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_roles_heading'),
    'subtitle' => return_translation('admin_roles_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="roles">
        <form method="get" class="position-relative mb-3" style="max-width: 280px" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="search" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>" autocomplete="off" data-admin-table-search>
        </form>

        <?php if (empty($roles)): ?>
            <div class="admin-table-state" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php else: ?>
            <?php ob_start(); ?>
                <thead class="position-sticky top-0">
                <tr>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_roles_col_name') ?><?= $sortIndicator('name') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('slug', (string)$sort, (string)$direction) ?>">Slug<?= $sortIndicator('slug') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('users_count', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_roles_col_users') ?><?= $sortIndicator('users_count') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('type', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_roles_col_type') ?><?= $sortIndicator('type') ?></a></th>
                    <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                </tr>
                </thead>
                <tbody class="table-list">
                    <?php foreach ($roles as $role): ?>
                        <?php
                        $isSystemRole = (int)($role['is_system'] ?? 0) === 1;
                        $isProtectedCreatorRole = ($role['slug'] ?? '') === 'creator';
                        $hasAssignedUsers = (int)($role['users_count'] ?? 0) > 0;
                        $deleteBlockedMessage = $isProtectedCreatorRole
                            ? return_translation('admin_roles_creator_protected')
                            : ($isSystemRole
                            ? return_translation('admin_roles_delete_system_blocked')
                            : return_translation('admin_roles_delete_assigned_blocked'));
                        ?>
                        <tr data-admin-live-table-row>
                            <th class="text-nowrap" scope="row"><?= (int)$role['id'] ?></th>
                            <td><?= htmlSC(get_user_role_label($role['slug'])) ?></td>
                            <td><?= htmlSC($role['slug']) ?></td>
                            <td class="text-nowrap"><?= (int)$role['users_count'] ?></td>
                            <td>
                                <?php if ($isSystemRole): ?>
                                    <span class="badge fs-xs text-secondary bg-secondary-subtle rounded-pill"><?= print_translation('admin_roles_type_system') ?></span>
                                <?php else: ?>
                                    <span class="badge fs-xs text-dark bg-light rounded-pill"><?= print_translation('admin_roles_type_custom') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap text-end">
                                <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
                                    <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
                                        <i class="ci-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                    <?php if ($canManageRoles && !$isProtectedCreatorRole): ?>
                                        <a
                                            class="dropdown-item d-flex align-items-center gap-2"
                                            href="<?= base_href('/admin/roles/edit/' . (int)$role['id']) ?>"
                                        >
                                            <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
                                        </a>
                                    <?php else: ?>
                                        <button
                                            class="dropdown-item d-flex align-items-center gap-2 disabled"
                                            type="button"
                                            aria-disabled="true"
                                        ><i class="ci-lock"></i><span><?= htmlSC(return_translation('admin_roles_creator_protected')) ?></span></button>
                                    <?php endif; ?>
                                    <?php if ($canManageRoles && !$isSystemRole && !$hasAssignedUsers && !$isProtectedCreatorRole): ?>
                                        <form
                                            action="<?= base_href('/admin/roles/delete') ?>"
                                            method="post"
                                            data-admin-delete-form
                                            data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_role')) ?>"
                                            data-delete-item="<?= htmlSC(get_user_role_label($role['slug'])) ?>"
                                        >
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$role['id'] ?>">
                                            <button
                                                class="dropdown-item d-flex align-items-center gap-2 text-danger"
                                                type="submit"
                                            ><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
                                        </form>
                                    <?php else: ?>
                                        <button
                                            class="dropdown-item d-flex align-items-center gap-2 disabled"
                                            type="button"
                                            aria-disabled="true"
                                        ><i class="ci-lock"></i><span><?= htmlSC($deleteBlockedMessage) ?></span></button>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php $adminTableContent = ob_get_clean(); ?>
            <?= view()->renderPartial('admin/partials/table', [
                'content' => $adminTableContent,
                'wrapper_attributes' => ['data-admin-live-table-wrap' => true],
            ]) ?>

            <?= view()->renderPartial('admin/partials/table_footer', [
                'visible' => count($roles),
                'total' => (int)$total,
                'pagination' => $pagination,
                'visible_attributes' => ['data-admin-live-table-visible' => true],
            ]) ?>
            <div class="admin-table-state d-none" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php endif; ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
