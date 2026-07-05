<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    return (($sort ?? '') === $column) ? (strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓') : '';
};
$renderActions = static function (array $article): string {
    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
            <i class="ci-more-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/support/knowledge-base/edit/' . (int)$article['id']) ?>">
                <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
            </a>
            <form action="<?= base_href('/admin/support/knowledge-base/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_support_confirm_delete_article')) ?>" data-delete-item="<?= htmlSC($article['title']) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
            </form>
        </div>
    </div>
    <?php

    return trim((string)ob_get_clean());
};
$actions = '<div class="d-flex flex-wrap gap-2">'
    . '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/support/knowledge-base/categories')) . '"><i class="ci-folder"></i>' . htmlSC(return_translation('admin_support_categories')) . '</a>'
    . '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/support/knowledge-base/create')) . '"><i class="ci-plus"></i>' . htmlSC(return_translation('admin_support_kb_create')) . '</a>'
    . '</div>';
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_support_kb_heading'),
    'subtitle' => return_translation('admin_support_kb_subtitle'),
    'actions' => $actions,
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'kb']) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="support-kb">
        <form method="get" class="row g-2 align-items-end mb-3" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <div class="col-md-4">
                <label class="form-label" for="kb-search"><?= print_translation('admin_table_search_placeholder') ?></label>
                <input id="kb-search" class="form-control" type="search" name="search" value="<?= htmlSC((string)$search) ?>" data-admin-table-search>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="kb-category"><?= print_translation('admin_support_category') ?></label>
                <select id="kb-category" class="form-select" name="category_id">
                    <option value="0"><?= print_translation('admin_support_all_categories') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= (int)$category_id === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="kb-published"><?= print_translation('admin_support_publication_status') ?></label>
                <select id="kb-published" class="form-select" name="published">
                    <option value=""><?= print_translation('admin_support_all_statuses') ?></option>
                    <option value="1" <?= (string)$published === '1' ? 'selected' : '' ?>><?= print_translation('admin_support_published') ?></option>
                    <option value="0" <?= (string)$published === '0' ? 'selected' : '' ?>><?= print_translation('admin_support_unpublished') ?></option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= print_translation('admin_btn_apply') ?></button>
            </div>
        </form>

        <?php $mobileCards = []; ?>
        <?php ob_start(); ?>
            <thead class="position-sticky top-0">
                <tr>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">ID<?= $sortIndicator('id') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('title', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_title') ?><?= $sortIndicator('title') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('category', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_category') ?><?= $sortIndicator('category') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('is_published', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_publication_status') ?><?= $sortIndicator('is_published') ?></a></th>
                    <th><?= print_translation('admin_support_kb_stats') ?></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('updated_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_updated_at') ?><?= $sortIndicator('updated_at') ?></a></th>
                    <th><?= print_translation('admin_posts_col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($articles)): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <?php
                        $helpfulCount = (int)($article['helpful_count'] ?? 0);
                        $notHelpfulCount = (int)($article['not_helpful_count'] ?? 0);
                        $feedbackTotal = $helpfulCount + $notHelpfulCount;
                        $helpfulPercent = $feedbackTotal > 0 ? (int)round((float)$article['helpful_percent']) : null;
                        $isPublished = (int)$article['is_published'] === 1;
                        $statusBadges = [[
                            'label' => return_translation($isPublished ? 'admin_support_published' : 'admin_support_unpublished'),
                            'class' => $isPublished ? 'text-success bg-success-subtle' : 'text-secondary bg-secondary-subtle',
                        ]];
                        if ($helpfulPercent !== null && $helpfulPercent < 50) {
                            $statusBadges[] = [
                                'label' => return_translation('admin_support_kb_low_helpfulness'),
                                'class' => 'text-danger bg-danger-subtle',
                            ];
                        }
                        $actionsHtml = $renderActions($article);
                        $mobileCards[] = [
                            'id' => (int)$article['id'],
                            'title' => (string)$article['title'],
                            'slug' => (string)$article['slug'],
                            'category' => (string)($article['category_name'] ?? ''),
                            'category_label' => return_translation('admin_support_category'),
                            'views' => (int)($article['views_count'] ?? 0),
                            'views_label' => return_translation('admin_support_kb_views'),
                            'status' => $statusBadges,
                            'status_label' => return_translation('admin_support_publication_status'),
                            'published_at' => date('d.m.Y H:i', strtotime((string)$article['updated_at'])),
                            'published_at_label' => return_translation('admin_support_updated_at'),
                            'actions' => $actionsHtml,
                            'extra_fields' => [
                                [
                                    'label' => return_translation('admin_support_kb_helpful'),
                                    'value' => $helpfulCount,
                                ],
                                [
                                    'label' => return_translation('admin_support_kb_not_helpful'),
                                    'value' => $notHelpfulCount,
                                ],
                                [
                                    'label' => return_translation('admin_support_kb_helpfulness'),
                                    'value' => $helpfulPercent !== null ? $helpfulPercent . '%' : '-',
                                ],
                            ],
                        ];
                        ?>
                        <tr>
                            <td class="text-nowrap fw-semibold"><?= (int)$article['id'] ?></td>
                            <td class="fw-medium text-break">
                                <?= htmlSC($article['title']) ?>
                                <span class="d-block small text-body-secondary fw-normal mt-1"><?= htmlSC($article['slug']) ?></span>
                            </td>
                            <td><?= htmlSC((string)($article['category_name'] ?? '')) ?></td>
                            <td><span class="badge fs-xs rounded-pill <?= htmlSC($statusBadges[0]['class']) ?>"><?= htmlSC($statusBadges[0]['label']) ?></span></td>
                            <td class="text-nowrap">
                                <div class="support-kb-stats">
                                    <span title="<?= htmlSC(return_translation('admin_support_kb_views')) ?>"><i class="ci-eye"></i><?= (int)($article['views_count'] ?? 0) ?></span>
                                    <span title="<?= htmlSC(return_translation('admin_support_kb_helpful')) ?>"><i class="ci-thumbs-up"></i><?= $helpfulCount ?></span>
                                    <span title="<?= htmlSC(return_translation('admin_support_kb_not_helpful')) ?>"><i class="ci-thumbs-down"></i><?= $notHelpfulCount ?></span>
                                    <span title="<?= htmlSC(return_translation('admin_support_kb_helpfulness')) ?>"><?= $helpfulPercent !== null ? $helpfulPercent . '%' : '&mdash;' ?></span>
                                </div>
                                <?php if ($helpfulPercent !== null && $helpfulPercent < 50): ?>
                                    <span class="badge fs-xs rounded-pill text-danger bg-danger-subtle mt-2"><?= print_translation('admin_support_kb_low_helpfulness') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime((string)$article['updated_at']))) ?></td>
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
        <?= view()->renderPartial('admin/partials/table_footer', [
            'visible' => count($articles),
            'total' => (int)$total,
            'pagination' => $pagination,
        ]) ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
