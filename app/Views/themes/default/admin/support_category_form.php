<?php
$formData = session()->get('form_data') ?: [];
$isKb = ($kind ?? '') === 'kb';
$value = static fn(string $key, string $default = ''): string => (string)($formData[$key] ?? ($category[$key] ?? $default));
$base = $isKb ? '/admin/support/knowledge-base/categories' : '/admin/support/faq/categories';
$action = $is_edit ? base_href($base . '/edit/' . (int)$category['id']) : base_href($base . '/create');
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation($is_edit ? 'admin_support_category_edit_heading' : 'admin_support_category_create_heading'),
    'subtitle' => return_translation('admin_support_category_form_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => $isKb ? 'kb' : 'faq']) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-<?= $isKb ? '6' : '8' ?>">
                <label class="form-label" for="support-category-name"><?= print_translation('admin_support_category_name') ?> *</label>
                <input id="support-category-name" class="form-control <?= get_validation_class('name') ?>" type="text" name="name" value="<?= htmlSC($value('name')) ?>" maxlength="190" required>
                <?= get_errors('name') ?>
            </div>
            <?php if ($isKb): ?>
                <div class="col-md-4">
                    <label class="form-label" for="support-category-slug">Slug</label>
                    <input id="support-category-slug" class="form-control" type="text" name="slug" value="<?= htmlSC($value('slug')) ?>" maxlength="190">
                </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label" for="support-category-sort"><?= print_translation('admin_support_sort_order') ?></label>
                <input id="support-category-sort" class="form-control" type="number" name="sort_order" value="<?= htmlSC($value('sort_order', '0')) ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href($base) ?>"><?= print_translation('admin_btn_cancel') ?></a>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
