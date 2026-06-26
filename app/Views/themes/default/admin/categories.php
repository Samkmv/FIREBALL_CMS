<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};
?>
<?php ob_start(); ?>
<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/categories/create') ?>"><i class="ci-plus"></i><?= print_translation('admin_categories_create') ?></a>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_categories_heading'),
    'subtitle' => return_translation('admin_categories_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="categories">
        <form method="get" class="position-relative mb-3" style="max-width: 280px" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="search" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>" autocomplete="off" data-admin-table-search>
        </form>

        <?php if (empty($categories)): ?>
            <div class="admin-table-state" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php else: ?>
            <?php ob_start(); ?>
                <thead class="position-sticky top-0">
                <tr>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name_ru', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_categories_col_name_ru') ?><?= $sortIndicator('name_ru') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name_en', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_categories_col_name_en') ?><?= $sortIndicator('name_en') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('slug', (string)$sort, (string)$direction) ?>">Slug<?= $sortIndicator('slug') ?></a></th>
                    <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('posts_count', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_categories_col_posts') ?><?= $sortIndicator('posts_count') ?></a></th>
                    <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                </tr>
                </thead>
                <tbody class="table-list">
                    <?php foreach ($categories as $category): ?>
                        <tr data-admin-live-table-row>
                            <th class="text-nowrap" scope="row"><?= (int)$category['id'] ?></th>
                            <td><?= htmlSC($category['name_ru'] ?? $category['name']) ?></td>
                            <td><?= htmlSC($category['name_en'] ?? $category['name']) ?></td>
                            <td><?= htmlSC($category['slug']) ?></td>
                            <td class="text-nowrap"><?= (int)$category['posts_count'] ?></td>
                            <td class="text-nowrap text-end">
                                <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
                                    <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
                                        <i class="ci-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/categories/edit/' . (int)$category['id']) ?>">
                                            <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
                                        </a>
                                        <form
                                            action="<?= base_href('/admin/categories/delete') ?>"
                                            method="post"
                                            data-admin-delete-form
                                            data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_category')) ?>"
                                            data-delete-item="<?= htmlSC((string)($category['name_ru'] ?? $category['name'] ?? '')) ?>"
                                        >
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
                                        </form>
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
                'visible' => count($categories),
                'total' => (int)$total,
                'pagination' => $pagination,
                'visible_attributes' => ['data-admin-live-table-visible' => true],
            ]) ?>
            <div class="admin-table-state d-none" data-admin-live-table-empty><?= print_translation('admin_table_empty') ?></div>
        <?php endif; ?>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
