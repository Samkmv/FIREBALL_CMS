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
$canManageUsers = check_creator();
$currentAdmin = get_user();
$currentAdminRole = (string)($currentAdmin['role'] ?? 'user');
$allowAdminResetTwoFactor = !empty($allow_admin_reset_user_2fa);
$twoFactorResetModals = [];
$renderActions = static function (array $item) use ($canManageUsers, $allowAdminResetTwoFactor, $currentAdminRole, &$twoFactorResetModals): string {
    $isCurrentUser = (int)$item['id'] === (int)(get_user()['id'] ?? 0);
    $roleSlug = (string)($item['role'] ?? 'user');
    $isProtectedCreator = $roleSlug === 'creator';
    $isLastAdmin = in_array($roleSlug, ['creator', 'admin'], true) && (int)($item['other_admins_count'] ?? 0) === 0;
    $hasTwoFactor = !empty($item['two_factor_enabled_at']);
    $canResetTwoFactor = $hasTwoFactor
        && $allowAdminResetTwoFactor
        && !$isCurrentUser
        && !$isProtectedCreator
        && (
            $currentAdminRole === 'creator'
            || ($currentAdminRole === 'admin' && !in_array($roleSlug, ['creator', 'admin'], true))
        );
    $deleteBlockedMessage = $isCurrentUser
        ? return_translation('admin_users_delete_self_blocked')
        : ($isProtectedCreator
            ? return_translation('admin_users_creator_protected')
            : return_translation('admin_users_delete_last_admin_blocked'));

    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
            <i class="ci-more-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
        <?php if ($canManageUsers && !$isProtectedCreator): ?>
            <a
                class="dropdown-item d-flex align-items-center gap-2"
                href="<?= base_href('/admin/users/edit/' . (int)$item['id']) ?>"
            >
                <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
            </a>
        <?php else: ?>
            <button
                class="dropdown-item d-flex align-items-center gap-2 disabled"
                type="button"
                aria-disabled="true"
            ><i class="ci-lock"></i><span><?= htmlSC(return_translation('admin_users_creator_protected')) ?></span></button>
        <?php endif; ?>
        <?php if ($canResetTwoFactor): ?>
            <?php $modalId = 'reset-2fa-' . (int)$item['id']; ?>
            <button
                class="dropdown-item d-flex align-items-center gap-2"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#<?= htmlSC($modalId) ?>"
            ><i class="ci-shield-off"></i><span><?= print_translation('admin_2fa_reset_button') ?></span></button>
            <?php ob_start(); ?>
            <div class="modal fade" id="<?= htmlSC($modalId) ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content rounded-5">
                        <form action="<?= base_href('/admin/users/reset-2fa') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <div class="modal-header border-0 pb-0">
                                <h2 class="modal-title h5"><?= print_translation('admin_2fa_reset_modal_title') ?></h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC(return_translation('admin_btn_cancel')) ?>"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-body-secondary mb-3"><?= htmlSC(str_replace(':name', (string)$item['name'], return_translation('admin_2fa_reset_modal_warning'))) ?></p>
                                <div class="mb-3">
                                    <label class="form-label"><?= print_translation('admin_2fa_reset_admin_password') ?></label>
                                    <input class="form-control" type="password" name="admin_password" autocomplete="current-password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= print_translation('admin_2fa_reset_admin_code') ?></label>
                                    <input class="form-control" type="text" name="admin_2fa_code" autocomplete="one-time-code">
                                </div>
                                <div>
                                    <label class="form-label"><?= print_translation('admin_2fa_reset_reason') ?></label>
                                    <textarea class="form-control" name="reason" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                                <button type="submit" class="btn btn-warning rounded-pill"><?= print_translation('admin_2fa_reset_confirm') ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php $twoFactorResetModals[] = ob_get_clean(); ?>
        <?php endif; ?>
        <?php if ($canManageUsers && !$isCurrentUser && !$isLastAdmin && !$isProtectedCreator): ?>
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
    <?php

    return trim((string)ob_get_clean());
};
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_users_heading'),
    'subtitle' => return_translation('admin_users_subtitle'),
    'actions' => $canManageUsers
        ? '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/users/create') . '"><i class="ci-plus"></i>' . htmlSC(return_translation('admin_users_create')) . '</a>'
        : '',
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="users">
        <form method="get" class="position-relative mb-3" style="max-width: 280px" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="search" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>" autocomplete="off" data-admin-table-search>
        </form>

        <?php if (empty($users)): ?>
            <div class="admin-table-state" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php else: ?>
            <?php $mobileCards = []; ?>
            <?php ob_start(); ?>
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
                        $roleSlug = (string)($item['role'] ?? 'user');
                        $isOnline = !empty($item['is_online']);
                        $roleBadgeClass = match ($roleSlug) {
                            'creator' => 'text-success bg-success-subtle',
                            'admin' => 'text-warning bg-warning-subtle',
                            default => 'text-secondary bg-secondary-subtle',
                        };
                        $actionsHtml = $renderActions($item);
                        $roleBadgeHtml = '<span class="badge fs-xs rounded-pill ' . htmlSC($roleBadgeClass) . '">' . htmlSC(get_user_role_label($roleSlug)) . '</span>';
                        $mobileCards[] = [
                            'id' => (int)$item['id'],
                            'title' => (string)$item['name'],
                            'image' => [
                                'src' => get_user_avatar($item['avatar'] ?? null, 'sm'),
                                'alt' => (string)$item['name'],
                            ],
                            'slug' => (string)($item['login'] ?? ''),
                            'slug_label' => return_translation('admin_users_col_login'),
                            'category' => (string)$item['email'],
                            'category_label' => return_translation('admin_users_col_email'),
                            'status' => [[
                                'label' => $isOnline ? return_translation('admin_user_status_online') : return_translation('admin_user_status_offline'),
                                'class' => $isOnline ? 'text-success bg-success-subtle' : 'text-secondary bg-secondary-subtle',
                            ]],
                            'published_at' => date('d.m.Y H:i', strtotime($item['created_at'])),
                            'published_at_label' => return_translation('admin_users_col_created_at'),
                            'actions' => $actionsHtml,
                            'extra_fields' => [
                                [
                                    'label' => return_translation('admin_users_col_role'),
                                    'html' => $roleBadgeHtml,
                                ],
                            ],
                        ];
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
                                <?= $roleBadgeHtml ?>
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
                            <td class="text-nowrap text-end">
                                <?= $actionsHtml ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php $adminTableContent = ob_get_clean(); ?>
            <?= view()->renderPartial('admin/partials/table', [
                'content' => $adminTableContent,
                'table_class' => 'admin-users-table',
                'table_attributes' => ['data-admin-users-table' => true],
                'wrapper_attributes' => [
                    'data-admin-users-table-wrap' => true,
                    'data-admin-live-table-wrap' => true,
                ],
                'mobile_cards' => $mobileCards,
            ]) ?>
            <?= implode('', $twoFactorResetModals) ?>

            <?= view()->renderPartial('admin/partials/table_footer', [
                'visible' => count($users),
                'total' => (int)$total,
                'pagination' => $pagination,
                'visible_attributes' => ['data-admin-live-table-visible' => true],
            ]) ?>
            <div class="admin-table-state d-none" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php endif; ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
