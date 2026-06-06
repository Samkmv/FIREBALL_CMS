<?php
$items = (array)($items ?? []);
$tableKey = (string)($table_key ?? 'published');
$emptyText = (string)($empty_text ?? return_translation('admin_pages_empty'));
$pagination = $pagination ?? null;
$total = (int)($total ?? count($items));
$sort = (string)($sort ?? 'menu_order');
$direction = (string)($direction ?? 'asc');
$status = $tableKey === 'drafts' ? 'drafts' : 'published';
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if ($sort !== $column) {
        return '';
    }

    return strtolower($direction) === 'asc' ? ' ↑' : ' ↓';
};
$sortUrl = static function (string $column) use ($sort, $direction, $status): string {
    $nextDirection = ($sort === $column && strtolower($direction) === 'asc') ? 'desc' : 'asc';

    return current_url_with_query([
        'sort' => $column,
        'direction' => $nextDirection,
        'status' => $status,
        'page' => 1,
        'published_page' => null,
        'draft_page' => null,
        'q' => null,
    ]);
};
$pageVisibilityLabel = static function (array $page): string {
    return match (true) {
        (int)$page['show_in_header'] === 1 && (int)$page['show_in_footer'] === 1 => return_translation('admin_page_visibility_both'),
        (int)$page['show_in_header'] === 1 => return_translation('admin_page_visibility_header'),
        (int)$page['show_in_footer'] === 1 => return_translation('admin_page_visibility_footer'),
        default => return_translation('admin_page_visibility_none'),
    };
};
?>
<div data-admin-posts-pane-content="<?= htmlSC($tableKey) ?>">
    <div data-admin-posts-table-shell="<?= htmlSC($tableKey) ?>">
        <?php if (empty($items)): ?>
            <p class="text-body-secondary mb-0" data-admin-posts-empty><?= htmlSC($emptyText) ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0 admin-pages-table">
                    <colgroup>
                        <col class="admin-pages-table__col-id">
                        <col class="admin-pages-table__col-title">
                        <col class="admin-pages-table__col-menu">
                        <col class="admin-pages-table__col-slug">
                        <col class="admin-pages-table__col-order">
                        <col class="admin-pages-table__col-status">
                        <col class="admin-pages-table__col-updated">
                        <col class="admin-pages-table__col-actions">
                    </colgroup>
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('id') ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('title') ?>"><?= print_translation('admin_pages_col_title') ?><?= $sortIndicator('title') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('menu_title') ?>"><?= print_translation('admin_pages_col_menu_title') ?><?= $sortIndicator('menu_title') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('slug') ?>"><?= print_translation('admin_pages_col_slug') ?><?= $sortIndicator('slug') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('menu_order') ?>"><?= print_translation('admin_pages_col_order') ?><?= $sortIndicator('menu_order') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('status') ?>"><?= print_translation('admin_posts_col_status') ?><?= $sortIndicator('status') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('updated_at') ?>"><?= print_translation('admin_pages_col_updated_at') ?><?= $sortIndicator('updated_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($items as $page): ?>
                        <?php $visibilityLabel = $pageVisibilityLabel($page); ?>
                        <tr data-admin-post-row>
                            <th class="text-nowrap" scope="row"><?= (int)$page['id'] ?></th>
                            <td>
                                <div class="fw-medium"><?= htmlSC($page['title']) ?></div>
                                <div class="text-body-tertiary small"><?= htmlSC($visibilityLabel) ?></div>
                                <?php if ((int)($page['show_in_legal_information'] ?? 0) === 1): ?>
                                    <span class="badge fs-xs text-info bg-info-subtle rounded-pill mt-1"><?= print_translation('footer_heading_legal_information') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlSC($page['menu_label']) ?></td>
                            <td class="text-nowrap">/<?= htmlSC($page['slug']) ?></td>
                            <td class="text-nowrap"><?= (int)$page['menu_order'] ?></td>
                            <td>
                                <?php if ((int)$page['is_published'] === 1): ?>
                                    <span class="badge fs-xs text-success bg-success-subtle rounded-pill"><?= print_translation('admin_posts_status_published') ?></span>
                                <?php else: ?>
                                    <span class="badge fs-xs text-secondary bg-secondary-subtle rounded-pill"><?= print_translation('admin_posts_status_draft') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $page['updated_at'] !== '' ? date('d.m.Y H:i', strtotime($page['updated_at'])) : '-' ?></td>
                            <td class="text-nowrap">
                                <div class="dropdown admin-post-actions-dropdown" data-admin-post-actions-dropdown>
                                    <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?= htmlSC(print_translation('admin_posts_col_actions')) ?>">
                                        <i class="ci-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= (int)$page['is_published'] === 1 ? base_href('/' . $page['slug']) : base_href('/admin/pages/preview/' . (int)$page['id']) ?>" target="_blank" rel="noopener noreferrer">
                                            <i class="ci-external-link"></i><span><?= print_translation('admin_btn_view') ?></span>
                                        </a>
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/pages/edit/' . (int)$page['id']) ?>">
                                            <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
                                        </a>
                                        <form action="<?= base_href('/admin/pages/toggle-published') ?>" method="post">
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                                            <?php if ((int)$page['is_published'] === 1): ?>
                                                <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-eye-off"></i><span><?= print_translation('admin_btn_unpublish') ?></span></button>
                                            <?php else: ?>
                                                <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-check"></i><span><?= print_translation('admin_btn_publish') ?></span></button>
                                            <?php endif; ?>
                                        </form>
                                        <form action="<?= base_href('/admin/pages/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_page')) ?>" data-delete-item="<?= htmlSC($page['title']) ?>">
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="d-flex align-items-center justify-content-between pt-4 gap-3">
        <div class="fs-sm">
            <?= print_translation('admin_table_showing') ?>
            <span class="fw-semibold" data-admin-posts-visible="<?= htmlSC($tableKey) ?>"><?= count($items) ?></span>
            <?= print_translation('admin_table_of') ?>
            <span class="fw-semibold" data-admin-posts-total="<?= htmlSC($tableKey) ?>"><?= $total ?></span>
            <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
        </div>
        <nav aria-label="Pagination" data-admin-posts-pagination="<?= htmlSC($tableKey) ?>">
            <?= !empty($pagination) ? $pagination : '' ?>
        </nav>
    </div>
</div>
