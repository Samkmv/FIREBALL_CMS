<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};

$publishedPages = (array)($published_pages ?? []);
$draftPages = (array)($draft_pages ?? []);
$publishedPagination = $published_pagination ?? null;
$draftPagination = $draft_pagination ?? null;
$publishedTotal = (int)($published_total ?? count($publishedPages));
$draftTotal = (int)($draft_total ?? count($draftPages));
$searchValue = (string)($search ?? '');
$emptyText = $searchValue !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_pages_empty');
$pagesSortUrl = static function (string $column) use ($sort, $direction): string {
    $nextDirection = (($sort ?? '') === $column && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';

    return current_url_with_query([
        'sort' => $column,
        'direction' => $nextDirection,
        'page' => 1,
        'published_page' => 1,
        'draft_page' => 1,
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

$renderPagesTable = static function (array $items, string $tableKey, string $emptyText) use ($sortIndicator, $pagesSortUrl, $pageVisibilityLabel): void {
    ?>
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
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('id') ?>">#<?= $sortIndicator('id') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('title') ?>"><?= print_translation('admin_pages_col_title') ?><?= $sortIndicator('title') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('menu_title') ?>"><?= print_translation('admin_pages_col_menu_title') ?><?= $sortIndicator('menu_title') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('slug') ?>"><?= print_translation('admin_pages_col_slug') ?><?= $sortIndicator('slug') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('menu_order') ?>"><?= print_translation('admin_pages_col_order') ?><?= $sortIndicator('menu_order') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('status') ?>"><?= print_translation('admin_posts_col_status') ?><?= $sortIndicator('status') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('updated_at') ?>"><?= print_translation('admin_pages_col_updated_at') ?><?= $sortIndicator('updated_at') ?></a>
                        </th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($items as $page): ?>
                        <?php
                        $statusLabel = (int)$page['is_published'] === 1
                            ? return_translation('admin_posts_status_published')
                            : return_translation('admin_posts_status_draft');
                        $visibilityLabel = $pageVisibilityLabel($page);
                        $searchText = implode(' ', [
                            (string)($page['id'] ?? ''),
                            (string)($page['title'] ?? ''),
                            (string)($page['menu_label'] ?? ''),
                            (string)($page['slug'] ?? ''),
                            (string)($page['menu_order'] ?? ''),
                            $visibilityLabel,
                            $statusLabel,
                            (string)($page['updated_at'] ?? ''),
                        ]);
                        ?>
                        <tr data-admin-post-row data-search-text="<?= htmlSC($searchText) ?>">
                            <th class="text-nowrap" scope="row"><?= (int)$page['id'] ?></th>
                            <td>
                                <div class="fw-medium"><?= htmlSC($page['title']) ?></div>
                                <div class="text-body-tertiary small"><?= htmlSC($visibilityLabel) ?></div>
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
                                    <button
                                        class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        data-bs-display="static"
                                        aria-expanded="false"
                                        aria-label="<?= htmlSC(print_translation('admin_posts_col_actions')) ?>"
                                    >
                                        <i class="ci-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                    <a
                                        class="dropdown-item d-flex align-items-center gap-2"
                                        href="<?= (int)$page['is_published'] === 1 ? base_href('/' . $page['slug']) : base_href('/admin/pages/preview/' . (int)$page['id']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <i class="ci-external-link"></i>
                                        <span><?= print_translation('admin_btn_view') ?></span>
                                    </a>
                                    <a
                                        class="dropdown-item d-flex align-items-center gap-2"
                                        href="<?= base_href('/admin/pages/edit/' . (int)$page['id']) ?>"
                                    >
                                        <i class="ci-edit"></i>
                                        <span><?= print_translation('admin_btn_edit') ?></span>
                                    </a>
                                    <form action="<?= base_href('/admin/pages/toggle-published') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                                        <?php if ((int)$page['is_published'] === 1): ?>
                                            <button
                                                class="dropdown-item d-flex align-items-center gap-2"
                                                type="submit"
                                            >
                                                <i class="ci-eye-off"></i>
                                                <span><?= print_translation('admin_btn_unpublish') ?></span>
                                            </button>
                                        <?php else: ?>
                                            <button
                                                class="dropdown-item d-flex align-items-center gap-2"
                                                type="submit"
                                            >
                                                <i class="ci-check"></i>
                                                <span><?= print_translation('admin_btn_publish') ?></span>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form
                                        action="<?= base_href('/admin/pages/delete') ?>"
                                        method="post"
                                        data-admin-delete-form
                                        data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_page')) ?>"
                                        data-delete-item="<?= htmlSC($page['title']) ?>"
                                    >
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                                        <button
                                            class="dropdown-item d-flex align-items-center gap-2 text-danger"
                                            type="submit"
                                        >
                                            <i class="ci-trash"></i>
                                            <span><?= print_translation('admin_btn_delete') ?></span>
                                        </button>
                                    </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-body-secondary d-none mb-0" data-admin-posts-empty><?= print_translation('admin_table_empty_search') ?></p>
        <?php endif; ?>
    </div>
    <?php
};
?>
<?php ob_start(); ?>
<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/pages/create') ?>"><i class="ci-plus"></i><?= print_translation('admin_pages_create') ?></a>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_pages_heading'),
    'subtitle' => return_translation('admin_pages_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="border rounded-5 p-3 p-md-4" data-admin-posts-tabs="pages">
        <form method="get" class="position-relative mb-3" style="max-width: 320px" data-admin-posts-live-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="published_page" value="1">
            <input type="hidden" name="draft_page" value="1">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input
                type="search"
                name="q"
                value="<?= htmlSC($searchValue) ?>"
                class="table-search form-control form-icon-start"
                placeholder="<?= print_translation('admin_table_search_placeholder') ?>"
                autocomplete="off"
                data-admin-posts-live-search
            >
        </form>

        <ul class="nav nav-tabs mb-3 admin-posts-tabs" role="tablist" style="max-width: 450px">
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link active" id="published-pages-tab" data-bs-toggle="tab" data-bs-target="#published-pages-tab-pane" role="tab" aria-controls="published-pages-tab-pane" aria-selected="true" data-admin-posts-tab-button="published">
                    <?= print_translation('admin_posts_status_published') ?>
                    <span class="badge text-bg-secondary ms-2" data-admin-posts-count="published"><?= $publishedTotal ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link" id="draft-pages-tab" data-bs-toggle="tab" data-bs-target="#draft-pages-tab-pane" role="tab" aria-controls="draft-pages-tab-pane" aria-selected="false" data-admin-posts-tab-button="drafts">
                    <?= print_translation('admin_posts_status_draft') ?>
                    <span class="badge text-bg-secondary ms-2" data-admin-posts-count="drafts"><?= $draftTotal ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="published-pages-tab-pane" role="tabpanel" aria-labelledby="published-pages-tab" tabindex="0" data-admin-posts-pane="published">
                <?php $renderPagesTable($publishedPages, 'published', $emptyText); ?>
                <div class="d-flex align-items-center justify-content-between pt-4 gap-3">
                    <div class="fs-sm">
                        <?= print_translation('admin_table_showing') ?>
                        <span class="fw-semibold" data-admin-posts-visible="published"><?= count($publishedPages) ?></span>
                        <?= print_translation('admin_table_of') ?>
                        <span class="fw-semibold" data-admin-posts-total="published"><?= $publishedTotal ?></span>
                        <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
                    </div>
                    <?php if (!empty($publishedPagination)): ?>
                        <nav aria-label="Pagination">
                            <?= $publishedPagination ?>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tab-pane fade" id="draft-pages-tab-pane" role="tabpanel" aria-labelledby="draft-pages-tab" tabindex="0" data-admin-posts-pane="drafts">
                <?php $renderPagesTable($draftPages, 'drafts', $emptyText); ?>
                <div class="d-flex align-items-center justify-content-between pt-4 gap-3">
                    <div class="fs-sm">
                        <?= print_translation('admin_table_showing') ?>
                        <span class="fw-semibold" data-admin-posts-visible="drafts"><?= count($draftPages) ?></span>
                        <?= print_translation('admin_table_of') ?>
                        <span class="fw-semibold" data-admin-posts-total="drafts"><?= $draftTotal ?></span>
                        <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
                    </div>
                    <?php if (!empty($draftPagination)): ?>
                        <nav aria-label="Pagination">
                            <?= $draftPagination ?>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
