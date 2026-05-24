<?php
$formAction = $is_edit
    ? base_href('/admin/categories/edit/' . (int)$category['id'])
    : base_href('/admin/categories/create');
?>
<?php ob_start(); ?>
<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/categories') ?>"><i class="ci-arrow-left"></i><?= print_translation('admin_btn_back') ?></a>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $is_edit ? return_translation('admin_category_edit_heading') : return_translation('admin_category_create_heading'),
    'subtitle' => return_translation('admin_category_form_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= $formAction ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_categories_col_name_ru') ?></label>
                <input class="form-control <?= get_validation_class('name_ru') ?>" type="text" name="name_ru" value="<?= old('name_ru') ?: htmlSC($category['name_ru'] ?? $category['name'] ?? '') ?>" data-translit-source="#category_name_en" required>
                <?= get_errors('name_ru') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_categories_col_name_en') ?></label>
                <input class="form-control <?= get_validation_class('name_en') ?>" type="text" id="category_name_en" name="name_en" value="<?= old('name_en') ?: htmlSC($category['name_en'] ?? '') ?>" data-slug-source="#category_slug" required>
                <?= get_errors('name_en') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input class="form-control <?= get_validation_class('slug') ?>" type="text" id="category_slug" name="slug" value="<?= old('slug') ?: htmlSC($category['slug'] ?? '') ?>" data-slug-input>
                <?= get_errors('slug') ?>
            </div>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_seo_section_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_seo_section_subtitle') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_seo_title') ?></label>
                            <input class="form-control" type="text" name="seo_title" value="<?= old('seo_title') ?: htmlSC($category['seo_title'] ?? '') ?>">
                            <div class="form-text"><?= print_translation('admin_seo_title_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_seo_image') ?></label>
                            <div class="input-group">
                                <input class="form-control <?= get_validation_class('seo_image') ?>" type="text" id="category_seo_image" name="seo_image" value="<?= old('seo_image') ?: htmlSC($category['seo_image'] ?? '') ?>" placeholder="/uploads/categories/cover.jpg">
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-file-manager-open
                                    data-file-manager-input="category_seo_image"
                                    data-file-manager-dir="categories"
                                    data-file-manager-url="<?= base_href('/admin/files') ?>"
                                ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                            </div>
                            <div class="form-text"><?= print_translation('admin_seo_image_hint') ?></div>
                            <?= get_errors('seo_image') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_seo_description') ?></label>
                            <textarea class="form-control" name="seo_description" rows="3"><?= old('seo_description') ?: htmlSC($category['seo_description'] ?? '') ?></textarea>
                            <div class="form-text"><?= print_translation('admin_seo_description_hint') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_seo_keywords') ?></label>
                            <textarea class="form-control" name="seo_keywords" rows="2"><?= old('seo_keywords') ?: htmlSC($category['seo_keywords'] ?? '') ?></textarea>
                            <div class="form-text"><?= print_translation('admin_seo_keywords_hint') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/categories') ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
            </div>
        </div>
    </form>
<?= view()->renderPartial('admin/shell_close') ?>
