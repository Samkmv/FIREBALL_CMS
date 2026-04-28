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
            <h1 class="h3 mb-1"><?= print_translation('admin_users_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_users_subtitle') ?></p>
        </div>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <div class="border rounded-5 p-3 p-md-4">
        <form method="get" class="position-relative mb-3" style="max-width: 280px">
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
        </form>

        <?php if (empty($users)): ?>
            <p class="text-body-secondary mb-0"><?= ($search ?? '') !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_users_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0">
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_name') ?><?= $sortIndicator('name') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('login', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_login') ?><?= $sortIndicator('login') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('email', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_email') ?><?= $sortIndicator('email') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('role', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_role') ?><?= $sortIndicator('role') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('created_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_created_at') ?><?= $sortIndicator('created_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($users as $item): ?>
                        <?php
                        $isCurrentUser = (int)$item['id'] === (int)(get_user()['id'] ?? 0);
                        $isProtectedCreator = ($item['role'] ?? 'user') === 'creator';
                        $isLastAdmin = in_array(($item['role'] ?? 'user'), ['creator', 'admin'], true) && (int)($item['other_admins_count'] ?? 0) === 0;
                        $deleteBlockedMessage = $isCurrentUser
                            ? return_translation('admin_users_delete_self_blocked')
                            : ($isProtectedCreator
                                ? return_translation('admin_users_creator_protected')
                                : return_translation('admin_users_delete_last_admin_blocked'));
                        ?>
                        <tr>
                            <th class="text-nowrap" scope="row"><?= (int)$item['id'] ?></th>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="<?= get_user_avatar($item['avatar'] ?? null, 'sm') ?>"
                                        alt="<?= htmlSC($item['name']) ?>"
                                        class="rounded-circle border object-fit-cover"
                                        style="width: 40px; height: 40px;"
                                    >
                                    <div><?= htmlSC($item['name']) ?></div>
                                </div>
                            </td>
                            <td><?= htmlSC($item['login'] ?? '') ?></td>
                            <td><?= htmlSC($item['email']) ?></td>
                            <td><?= htmlSC(get_user_role_label($item['role'] ?? 'user')) ?></td>
                            <td class="text-nowrap"><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!$isProtectedCreator): ?>
                                        <a
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                            href="<?= base_href('/admin/users/edit/' . (int)$item['id']) ?>"
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
                                            aria-label="<?= htmlSC(return_translation('admin_users_creator_protected')) ?>"
                                            title="<?= htmlSC(return_translation('admin_users_creator_protected')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-lock"></i></button>
                                    <?php endif; ?>
                                    <?php if (!$isCurrentUser && !$isLastAdmin && !$isProtectedCreator): ?>
                                        <form
                                            action="<?= base_href('/admin/users/delete') ?>"
                                            method="post"
                                            data-admin-delete-form
                                            data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_user')) ?>"
                                            data-delete-item="<?= htmlSC($item['name']) ?>"
                                        >
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
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
                    <span class="fw-semibold"><?= count($users) ?></span>
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
