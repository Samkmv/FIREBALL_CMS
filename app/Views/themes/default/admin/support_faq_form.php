<?php
$formData = session()->get('form_data') ?: [];
$value = static fn(string $key, string $default = ''): string => (string)($formData[$key] ?? ($item[$key] ?? $default));
$categoryId = (int)($formData['category_id'] ?? ($item['category_id'] ?? 0));
$isPublished = array_key_exists('is_published', $formData) ? (int)$formData['is_published'] === 1 : (int)($item['is_published'] ?? 1) === 1;
$action = $is_edit ? base_href('/admin/support/faq/edit/' . (int)$item['id']) : base_href('/admin/support/faq/create');
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation($is_edit ? 'admin_support_faq_edit_heading' : 'admin_support_faq_create_heading'),
    'subtitle' => return_translation('admin_support_faq_form_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'faq']) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="faq-question"><?= print_translation('admin_support_question') ?> *</label>
                <input id="faq-question" class="form-control <?= get_validation_class('question') ?>" type="text" name="question" value="<?= htmlSC($value('question')) ?>" maxlength="255" required>
                <?= get_errors('question') ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="faq-answer"><?= print_translation('admin_support_answer') ?> *</label>
                <textarea id="faq-answer" class="form-control <?= get_validation_class('answer') ?>" name="answer" rows="8" required><?= htmlSC($value('answer')) ?></textarea>
                <?= get_errors('answer') ?>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="faq-category"><?= print_translation('admin_support_category') ?></label>
                <select id="faq-category" class="form-select" name="category_id">
                    <option value="0"><?= print_translation('admin_support_without_category') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="faq-sort-order"><?= print_translation('admin_support_sort_order') ?></label>
                <input id="faq-sort-order" class="form-control" type="number" name="sort_order" value="<?= htmlSC($value('sort_order', '0')) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_published" value="0">
                    <input id="faq-published" class="form-check-input" type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>>
                    <label class="form-check-label" for="faq-published"><?= print_translation('admin_support_published') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/support/faq') ?>"><?= print_translation('admin_btn_cancel') ?></a>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
