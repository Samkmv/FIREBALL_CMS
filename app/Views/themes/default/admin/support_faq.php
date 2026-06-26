<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    return (($sort ?? '') === $column) ? (strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓') : '';
};
$actions = '<div class="d-flex flex-wrap gap-2">'
    . '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/support/faq/categories')) . '"><i class="ci-folder"></i>' . htmlSC(return_translation('admin_support_categories')) . '</a>'
    . '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/support/faq/create')) . '"><i class="ci-plus"></i>' . htmlSC(return_translation('admin_support_faq_create')) . '</a>'
    . '</div>';
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_support_faq_heading'),
    'subtitle' => return_translation('admin_support_faq_subtitle'),
    'actions' => $actions,
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'faq']) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="support-faq">
        <form method="get" class="row g-2 align-items-end mb-3" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <div class="col-md-4">
                <label class="form-label" for="faq-search"><?= print_translation('admin_table_search_placeholder') ?></label>
                <input id="faq-search" class="form-control" type="search" name="search" value="<?= htmlSC((string)$search) ?>" data-admin-table-search>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="faq-category"><?= print_translation('admin_support_category') ?></label>
                <select id="faq-category" class="form-select" name="category_id">
                    <option value="0"><?= print_translation('admin_support_all_categories') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= (int)$category_id === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="faq-published"><?= print_translation('admin_support_publication_status') ?></label>
                <select id="faq-published" class="form-select" name="published">
                    <option value=""><?= print_translation('admin_support_all_statuses') ?></option>
                    <option value="1" <?= (string)$published === '1' ? 'selected' : '' ?>><?= print_translation('admin_support_published') ?></option>
                    <option value="0" <?= (string)$published === '0' ? 'selected' : '' ?>><?= print_translation('admin_support_unpublished') ?></option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= print_translation('admin_btn_apply') ?></button>
            </div>
        </form>

        <?php ob_start(); ?>
            <thead class="position-sticky top-0">
                <tr>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">ID<?= $sortIndicator('id') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('question', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_question') ?><?= $sortIndicator('question') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('category', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_category') ?><?= $sortIndicator('category') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('sort_order', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_sort_order') ?><?= $sortIndicator('sort_order') ?></a></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('is_published', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_support_publication_status') ?><?= $sortIndicator('is_published') ?></a></th>
                    <th><?= print_translation('admin_posts_col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="text-nowrap fw-semibold"><?= (int)$item['id'] ?></td>
                            <td class="fw-medium text-break"><?= htmlSC($item['question']) ?></td>
                            <td><?= htmlSC((string)($item['category_name'] ?? '')) ?></td>
                            <td><?= (int)$item['sort_order'] ?></td>
                            <td><span class="badge fs-xs rounded-pill <?= (int)$item['is_published'] === 1 ? 'text-success bg-success-subtle' : 'text-secondary bg-secondary-subtle' ?>"><?= print_translation((int)$item['is_published'] === 1 ? 'admin_support_published' : 'admin_support_unpublished') ?></span></td>
                            <td class="text-nowrap text-end">
                                <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
                                    <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
                                        <i class="ci-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/support/faq/edit/' . (int)$item['id']) ?>">
                                            <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
                                        </a>
                                        <form action="<?= base_href('/admin/support/faq/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_support_confirm_delete_faq')) ?>" data-delete-item="<?= htmlSC($item['question']) ?>">
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        <?php $adminTableContent = ob_get_clean(); ?>
        <?= view()->renderPartial('admin/partials/table', ['content' => $adminTableContent]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', [
            'visible' => count($items),
            'total' => (int)$total,
            'pagination' => $pagination,
        ]) ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
