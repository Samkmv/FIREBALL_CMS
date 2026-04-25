<?php
$formAction = $is_edit
    ? base_href('/admin/posts/edit/' . (int)$post['id'])
    : base_href('/admin/posts/create');
$formData = session()->get('form_data') ?: [];
$currentImage = $formData['image'] ?? ($post['image'] ?? '');
$hidePlaceholderImage = array_key_exists('hide_placeholder_image', $formData)
    ? (int)$formData['hide_placeholder_image']
    : (int)($post['hide_placeholder_image'] ?? 0);
$showOnHome = array_key_exists('show_on_home', $formData)
    ? (int)$formData['show_on_home']
    : (int)($post['show_on_home'] ?? 0);
$publishedAtSource = old('published_at') ?: ($post['published_at'] ?? '');
$publishedAtValue = $publishedAtSource !== '' && strtotime($publishedAtSource)
    ? date('Y-m-d H:i:S', strtotime($publishedAtSource))
    : date('Y-m-d H:i:S');
?>

<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= $is_edit ? print_translation('admin_post_edit_heading') : print_translation('admin_post_create_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_post_form_subtitle') ?></p>
        </div>
        <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts') ?>"><i class="ci-arrow-left"></i><?= print_translation('admin_btn_back') ?></a>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= $formAction ?>" method="post" enctype="multipart/form-data">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_posts_col_title') ?></label>
                <input class="form-control <?= get_validation_class('title') ?>" type="text" name="title" value="<?= old('title') ?: htmlSC($post['title'] ?? '') ?>" data-slug-source="#post_slug" required>
                <?= get_errors('title') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input class="form-control <?= get_validation_class('slug') ?>" type="text" id="post_slug" name="slug" value="<?= old('slug') ?: htmlSC($post['slug'] ?? '') ?>" data-slug-input required>
                <?= get_errors('slug') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_posts_col_category') ?></label>
                <?php $selectedCategoryId = (int)(old('category_id') ?: ($post['category_id'] ?? 0)); ?>
                <select class="form-select <?= get_validation_class('category_id') ?>" name="category_id" required>
                    <option value=""><?= print_translation('admin_posts_select_category') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= $selectedCategoryId === (int)$category['id'] ? 'selected' : '' ?>>
                            <?= htmlSC($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= get_errors('category_id') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= print_translation('admin_posts_col_date') ?></label>
                <input
                    class="form-control"
                    type="text"
                    name="published_at"
                    value="<?= htmlSC($publishedAtValue) ?>"
                    placeholder="YYYY-MM-DD HH:MM:SS"
                    autocomplete="off"
                    data-post-datepicker
                >
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_post_excerpt') ?></label>
                <textarea class="form-control" name="excerpt" rows="2"><?= old('excerpt') ?: htmlSC($post['excerpt'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_post_content') ?></label>
                <textarea
                    class="form-control <?= get_validation_class('content') ?>"
                    id="post_content"
                    name="content"
                    rows="10"
                    data-post-editor
                ><?= old('content') ?: htmlSC($post['content'] ?? '') ?></textarea>
                <?= get_errors('content') ?>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_post_image') ?></label>
                <div class="input-group mb-2">
                    <input
                        class="form-control"
                        type="text"
                        id="post_image"
                        name="image"
                        value="<?= htmlSC($currentImage) ?>"
                        placeholder="/uploads/posts/cover.jpg"
                        data-file-preview-image="#post_image_preview"
                        data-file-preview-text="#post_image_path"
                    >
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        data-file-manager-open
                        data-file-manager-input="post_image"
                        data-file-manager-dir="posts"
                        data-file-manager-url="<?= base_href('/admin/files') ?>"
                    ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                </div>
                <input class="form-control <?= get_validation_class('image_file') ?>" type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
                <?= get_errors('image_file') ?>
                <div class="form-text"><?= print_translation('admin_files_picker_hint') ?></div>
                <div class="d-flex align-items-center gap-3 mt-3 <?= $currentImage ? '' : 'd-none' ?>" id="post_image_preview_wrap">
                    <img
                        src="<?= $currentImage ? get_image($currentImage) : '' ?>"
                        alt=""
                        id="post_image_preview"
                        class="rounded-3 object-fit-cover"
                        style="width: 96px; height: 72px;"
                    >
                    <div class="small text-body-secondary" id="post_image_path"><?= htmlSC($currentImage) ?></div>
                </div>
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
                            <input class="form-control" type="text" name="seo_title" value="<?= old('seo_title') ?: htmlSC($post['seo_title'] ?? '') ?>">
                            <div class="form-text"><?= print_translation('admin_seo_title_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_seo_image') ?></label>
                            <div class="input-group">
                                <input class="form-control <?= get_validation_class('seo_image') ?>" type="text" id="post_seo_image" name="seo_image" value="<?= old('seo_image') ?: htmlSC($post['seo_image'] ?? '') ?>" placeholder="/uploads/posts/cover.jpg">
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-file-manager-open
                                    data-file-manager-input="post_seo_image"
                                    data-file-manager-dir="posts"
                                    data-file-manager-url="<?= base_href('/admin/files') ?>"
                                ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                            </div>
                            <div class="form-text"><?= print_translation('admin_seo_image_hint') ?></div>
                            <?= get_errors('seo_image') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_seo_description') ?></label>
                            <textarea class="form-control" name="seo_description" rows="3"><?= old('seo_description') ?: htmlSC($post['seo_description'] ?? '') ?></textarea>
                            <div class="form-text"><?= print_translation('admin_seo_description_hint') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_seo_keywords') ?></label>
                            <textarea class="form-control" name="seo_keywords" rows="2"><?= old('seo_keywords') ?: htmlSC($post['seo_keywords'] ?? '') ?></textarea>
                            <div class="form-text"><?= print_translation('admin_seo_keywords_hint') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="hide_placeholder_image" name="hide_placeholder_image" value="1" <?= $hidePlaceholderImage === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="hide_placeholder_image"><?= print_translation('admin_post_hide_placeholder_image') ?></label>
                </div>
                <div class="form-text"><?= print_translation('admin_post_hide_placeholder_image_hint') ?></div>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="show_on_home" name="show_on_home" value="1" <?= $showOnHome === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_on_home"><?= print_translation('admin_post_show_on_home') ?></label>
                </div>
                <div class="form-text"><?= print_translation('admin_post_show_on_home_hint') ?></div>
            </div>
            <div class="col-12">
                <?php $isPublished = (int)(old('is_published') ?: ($post['is_published'] ?? 1)); ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= $isPublished === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_published"><?= print_translation('admin_posts_status_published') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts') ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
            </div>
        </div>
    </form>
</section>
