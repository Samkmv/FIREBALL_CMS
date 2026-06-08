<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};

$onlineSortDirection = (($sort ?? '') === 'online' && strtolower((string)$direction) === 'desc') ? 'asc' : 'desc';
$onlineSortUrl = current_url_with_query([
    'sort' => 'online',
    'direction' => $onlineSortDirection,
    'page' => 1,
]);
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_users_heading'),
    'subtitle' => return_translation('admin_users_subtitle'),
    'actions' => '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/users/create') . '"><i class="ci-plus"></i>' . return_translation('admin_users_create') . '</a>',
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-live-table data-ajax-table="users">
        <form method="get" class="position-relative mb-3" style="max-width: 280px" data-admin-live-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1" data-admin-live-table-page-input>
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="search" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>" autocomplete="off" data-admin-live-table-search>
        </form>

        <?php if (empty($users)): ?>
            <p class="text-body-secondary mb-0" data-admin-live-table-empty><?= ($search ?? '') !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_users_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll admin-users-table-wrap" data-admin-users-table-wrap data-admin-live-table-wrap>
                <table class="table align-middle mb-0 admin-users-table" data-admin-users-table>
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_name') ?><?= $sortIndicator('name') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('login', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_login') ?><?= $sortIndicator('login') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('email', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_email') ?><?= $sortIndicator('email') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('role', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_role') ?><?= $sortIndicator('role') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $onlineSortUrl ?>"><?= print_translation('admin_users_col_online') ?><?= $sortIndicator('online') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('created_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_users_col_created_at') ?><?= $sortIndicator('created_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($users as $item): ?>
                        <?php
                        $isCurrentUser = (int)$item['id'] === (int)(get_user()['id'] ?? 0);
                        $roleSlug = (string)($item['role'] ?? 'user');
                        $isOnline = !empty($item['is_online']);
                        $isProtectedCreator = $roleSlug === 'creator';
                        $isLastAdmin = in_array($roleSlug, ['creator', 'admin'], true) && (int)($item['other_admins_count'] ?? 0) === 0;
                        $roleBadgeClass = match ($roleSlug) {
                            'creator' => 'text-success bg-success-subtle',
                            'admin' => 'text-warning bg-warning-subtle',
                            default => 'text-secondary bg-secondary-subtle',
                        };
                        $deleteBlockedMessage = $isCurrentUser
                            ? return_translation('admin_users_delete_self_blocked')
                            : ($isProtectedCreator
                                ? return_translation('admin_users_creator_protected')
                                : return_translation('admin_users_delete_last_admin_blocked'));
                        ?>
                        <tr data-admin-live-table-row>
                            <th class="text-nowrap" scope="row">
                                <span><?= (int)$item['id'] ?></span>
                            </th>
                            <td>
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <span class="flex-shrink-0">
                                        <img
                                            src="<?= get_user_avatar($item['avatar'] ?? null, 'sm') ?>"
                                            alt="<?= htmlSC($item['name']) ?>"
                                            class="rounded-circle border object-fit-cover"
                                            style="width: 40px; height: 40px;"
                                        >
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-truncate"><?= htmlSC($item['name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <span><?= htmlSC($item['login'] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="text-break"><?= htmlSC($item['email']) ?></span>
                            </td>
                            <td>
                                <span class="badge fs-xs rounded-pill <?= $roleBadgeClass ?>"><?= htmlSC(get_user_role_label($roleSlug)) ?></span>
                            </td>
                            <td class="text-nowrap">
                                <span
                                    class="badge fs-xs rounded-pill d-inline-flex align-items-center gap-1 <?= $isOnline ? 'text-success bg-success-subtle' : 'text-secondary bg-secondary-subtle' ?>"
                                    title="<?= htmlSC($isOnline ? return_translation('admin_user_status_online') : return_translation('admin_user_status_offline')) ?>"
                                    data-bs-toggle="tooltip"
                                >
                                    <span class="rounded-circle d-inline-block flex-shrink-0 <?= $isOnline ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                    <?= $isOnline ? print_translation('admin_user_status_online') : print_translation('admin_user_status_offline') ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <span><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></span>
                            </td>
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

            <?= view()->renderPartial('admin/partials/table_footer', [
                'visible' => count($users),
                'total' => (int)$total,
                'pagination' => $pagination,
                'visible_attributes' => ['data-admin-live-table-visible' => true],
            ]) ?>
            <p class="text-body-secondary d-none mb-0" data-admin-live-table-empty><?= print_translation('admin_table_empty_search') ?></p>
        <?php endif; ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
