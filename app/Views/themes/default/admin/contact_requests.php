<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};
$statusLabels = [
    'new' => return_translation('admin_support_status_new'),
    'in_work' => return_translation('admin_support_status_in_work'),
    'closed' => return_translation('admin_support_status_closed'),
    'spam' => return_translation('admin_support_status_spam'),
];
$statusClasses = [
    'new' => 'text-success bg-success-subtle',
    'in_work' => 'text-primary bg-primary-subtle',
    'closed' => 'text-secondary bg-secondary-subtle',
    'spam' => 'text-danger bg-danger-subtle',
];
$renderActions = static function (array $request, string $requestStatus) use ($statuses, $statusLabels): string {
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
            aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>"
        >
            <i class="ci-more-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <h6 class="dropdown-header"><?= print_translation('admin_support_bulk_change_status') ?></h6>
            <?php foreach ((array)$statuses as $statusKey): ?>
                <form action="<?= base_href('/admin/support/requests/status') ?>" method="post">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                    <input type="hidden" name="status" value="<?= htmlSC($statusKey) ?>">
                    <button class="dropdown-item d-flex align-items-center justify-content-between gap-3 <?= $requestStatus === $statusKey ? 'active' : '' ?>" type="submit">
                        <span><?= htmlSC($statusLabels[$statusKey] ?? $statusKey) ?></span>
                        <?php if ($requestStatus === $statusKey): ?>
                            <i class="ci-check"></i>
                        <?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
            <div class="dropdown-divider"></div>
            <form
                action="<?= base_href('/admin/support/requests/delete') ?>"
                method="post"
                data-admin-delete-form
                data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_contact')) ?>"
                data-delete-item="<?= htmlSC($request['name'] . ' <' . $request['email'] . '>') ?>"
            >
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
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
    'title' => return_translation('admin_support_requests_heading'),
    'subtitle' => return_translation('admin_support_requests_subtitle'),
    'actions' => '<span class="badge rounded-pill text-success bg-success-subtle">'
        . htmlSC(return_translation('admin_support_new_count')) . ': ' . (int)($new_count ?? 0)
        . '</span>',
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'requests']) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="contact-requests">
        <form method="get" class="row g-2 align-items-end mb-3" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <div class="col-md-5 col-lg-4">
                <label class="form-label" for="support-request-search"><?= print_translation('admin_table_search_placeholder') ?></label>
                <div class="position-relative">
                    <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                    <input id="support-request-search" type="search" name="search" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" autocomplete="off" data-admin-table-search>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="support-request-status"><?= print_translation('admin_support_filter_status') ?></label>
                <select id="support-request-status" class="form-select" name="status">
                    <option value=""><?= print_translation('admin_support_filter_all_statuses') ?></option>
                    <?php foreach ((array)$statuses as $statusKey): ?>
                        <option value="<?= htmlSC($statusKey) ?>" <?= ($status ?? '') === $statusKey ? 'selected' : '' ?>>
                            <?= htmlSC($statusLabels[$statusKey] ?? $statusKey) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= print_translation('admin_btn_apply') ?></button>
            </div>
        </form>

        <form id="support-request-bulk-form" class="d-flex flex-wrap gap-2 align-items-end mb-3" action="<?= base_href('/admin/support/requests/bulk') ?>" method="post">
            <?= get_csrf_field() ?>
            <div>
                <label class="form-label" for="support-bulk-action"><?= print_translation('admin_support_bulk_action') ?></label>
                <select id="support-bulk-action" class="form-select form-select-sm" name="action" required>
                    <option value=""><?= print_translation('admin_support_bulk_choose') ?></option>
                    <option value="mark_viewed"><?= print_translation('admin_support_bulk_mark_viewed') ?></option>
                    <option value="status"><?= print_translation('admin_support_bulk_change_status') ?></option>
                    <option value="delete"><?= print_translation('admin_support_bulk_delete') ?></option>
                </select>
            </div>
            <div>
                <label class="form-label" for="support-bulk-status"><?= print_translation('admin_contacts_col_status') ?></label>
                <select id="support-bulk-status" class="form-select form-select-sm" name="status">
                    <?php foreach ((array)$statuses as $statusKey): ?>
                        <option value="<?= htmlSC($statusKey) ?>"><?= htmlSC($statusLabels[$statusKey] ?? $statusKey) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-sm btn-dark rounded-pill" type="submit"><?= print_translation('admin_btn_apply') ?></button>
        </form>

        <?php if (empty($requests)): ?>
            <div class="admin-table-state" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php else: ?>
            <?php $mobileCards = []; ?>
            <?php ob_start(); ?>
                <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col" style="width: 40px;"></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_name') ?><?= $sortIndicator('name') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('email', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_email') ?><?= $sortIndicator('email') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('phone', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_phone') ?><?= $sortIndicator('phone') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('subject', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_subject') ?><?= $sortIndicator('subject') ?></a></th>
                        <th scope="col"><?= print_translation('admin_contacts_col_message') ?></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('status', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_status') ?><?= $sortIndicator('status') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('created_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_date') ?><?= $sortIndicator('created_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                </thead>
                <tbody class="table-list">
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $requestStatus = (string)($request['status'] ?? ((int)$request['is_viewed'] === 1 ? 'in_work' : 'new'));
                        $statusBadgeClass = $statusClasses[$requestStatus] ?? 'text-secondary bg-secondary-subtle';
                        $statusLabel = $statusLabels[$requestStatus] ?? $requestStatus;
                        $actionsHtml = $renderActions($request, $requestStatus);
                        $mobileCards[] = [
                            'selection' => [
                                'html' => '<input class="form-check-input" type="checkbox" name="ids[]" value="' . (int)$request['id'] . '" form="support-request-bulk-form">',
                            ],
                            'id' => (int)$request['id'],
                            'title' => (string)$request['name'],
                            'slug' => (string)$request['email'],
                            'slug_label' => return_translation('admin_contacts_col_email'),
                            'category' => (string)$request['subject'],
                            'category_label' => return_translation('admin_contacts_col_subject'),
                            'status' => [[
                                'label' => $statusLabel,
                                'class' => $statusBadgeClass,
                            ]],
                            'published_at' => date('d.m.Y H:i', strtotime($request['created_at'])),
                            'published_at_label' => return_translation('admin_contacts_col_date'),
                            'actions' => $actionsHtml,
                            'extra_fields' => [
                                [
                                    'label' => return_translation('admin_contacts_col_phone'),
                                    'html' => htmlSC((string)($request['phone'] ?? '')),
                                ],
                                [
                                    'label' => return_translation('admin_contacts_col_message'),
                                    'html' => nl2br(htmlSC($request['message'])),
                                ],
                            ],
                        ];
                        ?>
                        <tr data-admin-live-table-row>
                            <td>
                                <input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int)$request['id'] ?>" form="support-request-bulk-form">
                            </td>
                            <th class="text-nowrap" scope="row"><?= (int)$request['id'] ?></th>
                            <td class="fw-medium"><?= htmlSC($request['name']) ?></td>
                            <td><a href="mailto:<?= htmlSC($request['email']) ?>"><?= htmlSC($request['email']) ?></a></td>
                            <td>
                                <?php if (trim((string)($request['phone'] ?? '')) !== ''): ?>
                                    <a href="tel:<?= htmlSC(preg_replace('/[^0-9+]/', '', (string)$request['phone']) ?: (string)$request['phone']) ?>"><?= htmlSC($request['phone']) ?></a>
                                <?php else: ?>
                                    <span class="text-body-tertiary">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlSC($request['subject']) ?></td>
                            <td style="min-width: 320px;">
                                <div class="small lh-base"><?= nl2br(htmlSC($request['message'])) ?></div>
                            </td>
                            <td>
                                <span class="badge fs-xs <?= htmlSC($statusBadgeClass) ?> rounded-pill">
                                    <?= htmlSC($statusLabel) ?>
                                </span>
                            </td>
                            <td class="text-nowrap"><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <?= $actionsHtml ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php $adminTableContent = ob_get_clean(); ?>
            <?= view()->renderPartial('admin/partials/table', [
                'content' => $adminTableContent,
                'wrapper_attributes' => ['data-admin-live-table-wrap' => true],
                'mobile_cards' => $mobileCards,
            ]) ?>

            <?= view()->renderPartial('admin/partials/table_footer', [
                'visible' => count($requests),
                'total' => (int)$total,
                'pagination' => $pagination,
                'visible_attributes' => ['data-admin-live-table-visible' => true],
            ]) ?>
            <div class="admin-table-state d-none" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php endif; ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
