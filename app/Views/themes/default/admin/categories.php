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

    <div class="border rounded-5 p-3 p-md-4">
        <form method="get" class="position-relative mb-3" style="max-width: 280px">
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
        </form>

        <?php if (empty($categories)): ?>
            <p class="text-body-secondary mb-0"><?= ($search ?? '') !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_categories_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0">
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
                        <tr>
                            <th class="text-nowrap" scope="row"><?= (int)$category['id'] ?></th>
                            <td><?= htmlSC($category['name_ru'] ?? $category['name']) ?></td>
                            <td><?= htmlSC($category['name_en'] ?? $category['name']) ?></td>
                            <td><?= htmlSC($category['slug']) ?></td>
                            <td class="text-nowrap"><?= (int)$category['posts_count'] ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                        href="<?= base_href('/admin/categories/edit/' . (int)$category['id']) ?>"
                                        aria-label="<?= htmlSC(return_translation('admin_btn_edit')) ?>"
                                        title="<?= htmlSC(return_translation('admin_btn_edit')) ?>"
                                        data-bs-toggle="tooltip"
                                    >
                                        <i class="ci-edit"></i>
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
                                        <button
                                            class="btn btn-sm btn-outline-danger btn-icon rounded-circle"
                                            type="submit"
                                            aria-label="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                            title="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-trash"></i></button>
                                    </form>
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
                    <span class="fw-semibold"><?= count($categories) ?></span>
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
<?= view()->renderPartial('admin/shell_close') ?>
