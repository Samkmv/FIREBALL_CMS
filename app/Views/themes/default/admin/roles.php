<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};
?>

<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= print_translation('admin_roles_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_roles_subtitle') ?></p>
        </div>
        <a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/roles/create') ?>"><i class="ci-plus"></i><?= print_translation('admin_roles_create') ?></a>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <div class="border rounded-5 p-3 p-md-4">
        <form method="get" class="position-relative mb-3" style="max-width: 280px">
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
        </form>

        <?php if (empty($roles)): ?>
            <p class="text-body-secondary mb-0"><?= ($search ?? '') !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_roles_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0">
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
                        <tr>
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
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!$isProtectedCreatorRole): ?>
                                        <a
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                            href="<?= base_href('/admin/roles/edit/' . (int)$role['id']) ?>"
                                            aria-label="<?= htmlSC(return_translation('admin_btn_edit')) ?>"
                                            title="<?= htmlSC(return_translation('admin_btn_edit')) ?>"
                                            data-bs-toggle="tooltip"
                                        >
                                            <i class="ci-edit"></i>
                                        </a>
                                    <?php else: ?>
                                        <button
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle disabled"
                                            type="button"
                                            aria-label="<?= htmlSC(return_translation('admin_roles_creator_protected')) ?>"
                                            title="<?= htmlSC(return_translation('admin_roles_creator_protected')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-lock"></i></button>
                                    <?php endif; ?>
                                    <?php if (!$isSystemRole && !$hasAssignedUsers && !$isProtectedCreatorRole): ?>
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
                                                class="btn btn-sm btn-outline-danger btn-icon rounded-circle"
                                                type="submit"
                                                aria-label="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                                title="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                                data-bs-toggle="tooltip"
                                            ><i class="ci-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <button
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle disabled"
                                            type="button"
                                            aria-label="<?= htmlSC($deleteBlockedMessage) ?>"
                                            title="<?= htmlSC($deleteBlockedMessage) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-lock"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between pt-4 gap-3">
                <div class="fs-sm">
                    <?= print_translation('admin_table_showing') ?>
                    <span class="fw-semibold"><?= count($roles) ?></span>
                    <?= print_translation('admin_table_of') ?>
                    <span class="fw-semibold"><?= (int)$total ?></span>
                    <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
                </div>
                <?php if ((int)$total > 15): ?>
                    <nav aria-label="Pagination">
                        <?= $pagination ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
