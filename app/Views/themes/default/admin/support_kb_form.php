<?php
$formData = session()->get('form_data') ?: [];
$value = static fn(string $key, string $default = ''): string => (string)($formData[$key] ?? ($article[$key] ?? $default));
$categoryId = (int)($formData['category_id'] ?? ($article['category_id'] ?? 0));
$isPublished = array_key_exists('is_published', $formData) ? (int)$formData['is_published'] === 1 : (int)($article['is_published'] ?? 1) === 1;
$action = $is_edit ? base_href('/admin/support/knowledge-base/edit/' . (int)$article['id']) : base_href('/admin/support/knowledge-base/create');
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation($is_edit ? 'admin_support_kb_edit_heading' : 'admin_support_kb_create_heading'),
    'subtitle' => return_translation('admin_support_kb_form_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'kb']) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= htmlSC($action) ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="kb-title"><?= print_translation('admin_support_title') ?> *</label>
                <input id="kb-title" class="form-control <?= get_validation_class('title') ?>" type="text" name="title" value="<?= htmlSC($value('title')) ?>" maxlength="255" required>
                <?= get_errors('title') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="kb-slug">Slug</label>
                <input id="kb-slug" class="form-control" type="text" name="slug" value="<?= htmlSC($value('slug')) ?>" maxlength="190">
            </div>
            <div class="col-12">
                <label class="form-label" for="kb-excerpt"><?= print_translation('admin_support_excerpt') ?></label>
                <textarea id="kb-excerpt" class="form-control" name="excerpt" rows="3"><?= htmlSC($value('excerpt')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="kb-content"><?= print_translation('admin_support_content') ?> *</label>
                <textarea id="kb-content" class="form-control <?= get_validation_class('content') ?>" name="content" rows="12" required><?= htmlSC($value('content')) ?></textarea>
                <?= get_errors('content') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="kb-category"><?= print_translation('admin_support_category') ?></label>
                <select id="kb-category" class="form-select" name="category_id">
                    <option value="0"><?= print_translation('admin_support_without_category') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_published" value="0">
                    <input id="kb-published" class="form-check-input" type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>>
                    <label class="form-check-label" for="kb-published"><?= print_translation('admin_support_published') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/support/knowledge-base') ?>"><?= print_translation('admin_btn_cancel') ?></a>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
