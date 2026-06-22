<?php
$actions = '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/support/knowledge-base/categories/create'))
    . '"><i class="ci-plus"></i>' . htmlSC(return_translation('admin_support_category_create')) . '</a>';
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_support_kb_categories_heading'),
    'subtitle' => return_translation('admin_support_kb_categories_subtitle'),
    'actions' => $actions,
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'kb']) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <div class="table-responsive overflow-auto admin-table-scroll">
            <table class="table align-middle mb-0">
                <thead><tr><th>#</th><th><?= print_translation('admin_support_category') ?></th><th>Slug</th><th><?= print_translation('admin_support_sort_order') ?></th><th><?= print_translation('admin_posts_col_actions') ?></th></tr></thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>#<?= (int)$category['id'] ?></td>
                            <td class="fw-medium"><?= htmlSC($category['name']) ?></td>
                            <td><?= htmlSC($category['slug']) ?></td>
                            <td><?= (int)$category['sort_order'] ?></td>
                            <td class="text-nowrap text-end">
                                <div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>
                                    <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
                                        <i class="ci-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/support/knowledge-base/categories/edit/' . (int)$category['id']) ?>">
                                            <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
                                        </a>
                                        <form action="<?= base_href('/admin/support/knowledge-base/categories/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_support_confirm_delete_category')) ?>" data-delete-item="<?= htmlSC($category['name']) ?>">
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
