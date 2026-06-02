<?php
$editorMode = (string)($editor_mode ?? 'post');
$isPageEditor = $editorMode === 'page';
$entity = $isPageEditor ? (array)($page ?? []) : (array)($post ?? []);
$post = $entity;
$listUrl = $isPageEditor ? base_href('/admin/pages') : base_href('/admin/posts');
$formAction = $isPageEditor
    ? ($is_edit ? base_href('/admin/pages/edit/' . (int)($entity['id'] ?? 0)) : base_href('/admin/pages/create'))
    : ($is_edit ? base_href('/admin/posts/edit/' . (int)($entity['id'] ?? 0)) : base_href('/admin/posts/create'));
$autosaveUrl = $isPageEditor ? base_href('/admin/pages/autosave') : base_href('/admin/posts/autosave');
$editorDefaultDirectory = $isPageEditor ? 'pages' : 'posts';
$formData = session()->get('form_data') ?: [];
$currentImage = $formData['image'] ?? ($entity['image'] ?? '');
$currentImageUrl = $formData['image_url'] ?? $currentImage;
$selectedFileField = trim((string)request()->get('fireball_file_field', ''));
$selectedFileValue = trim((string)request()->get('fireball_file_value', ''));
if ($selectedFileField === 'post_image' && $selectedFileValue !== '') {
    $currentImage = $selectedFileValue;
    $currentImageUrl = $selectedFileValue;
}
$hidePlaceholderImage = array_key_exists('hide_placeholder_image', $formData)
    ? (int)$formData['hide_placeholder_image']
    : (int)($entity['hide_placeholder_image'] ?? 0);
$showOnHome = array_key_exists('show_on_home', $formData)
    ? (int)$formData['show_on_home']
    : (int)($entity['show_on_home'] ?? 0);
$priorityValue = array_key_exists('priority', $formData)
    ? (int)$formData['priority']
    : (int)($entity['priority'] ?? 0);
$publishedAtSource = old('published_at') ?: ($entity['published_at'] ?? '');
$publishedAtValue = $publishedAtSource !== '' && strtotime($publishedAtSource)
    ? date('Y-m-d H:i:S', strtotime($publishedAtSource))
    : date('Y-m-d H:i:S');
$excerptValue = array_key_exists('excerpt', $formData)
    ? (string)$formData['excerpt']
    : (string)($post['excerpt'] ?? '');
$contentValue = array_key_exists('content', $formData)
    ? (string)$formData['content']
    : (string)($entity['content'] ?? '');
$translateOrFallback = static function (string $key, string $fallback = ''): string {
    $value = return_translation($key);

    if ($value === $key) {
        return $fallback !== '' ? $fallback : $key;
    }

    return $value;
};
$postImagePreviewLabel = return_translation('admin_post_image_preview');
$postImageDropTitle = return_translation('admin_post_image_drop_title');
$postImageDropHint = return_translation('admin_post_image_drop_hint');
$postImagePickerHint = return_translation('admin_files_picker_hint');
$postImageLocalHint = return_translation('admin_post_image_local_hint');
$postImageUrlHint = return_translation('admin_post_image_url_hint');
$postImageLinkActionLabel = $translateOrFallback('admin_post_image_pick_url', 'Image link');
$clearLabel = $translateOrFallback('admin_btn_clear', 'Clear');
$formErrors = session()->get('form_errors') ?: [];
$requiredSummaryLabel = $translateOrFallback('admin_form_required_summary', 'Заполните обязательные поля:');
?>

<?php ob_start(); ?>
<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= $listUrl ?>">
    <i class="ci-arrow-left"></i>
    <span><?= print_translation('admin_btn_back') ?></span>
</a>
<?php $adminPageActions = ob_get_clean(); ?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $isPageEditor
        ? ($is_edit ? return_translation('admin_page_edit_heading') : return_translation('admin_page_create_heading'))
        : ($is_edit ? return_translation('admin_post_edit_heading') : return_translation('admin_post_create_heading')),
    'subtitle' => $isPageEditor ? return_translation('admin_page_form_subtitle') : return_translation('admin_post_form_subtitle'),
    'actions' => $adminPageActions,
    'sidebar_col_class' => 'col-lg-4 col-xl-3',
    'main_col_class' => 'col-lg-8 col-xl-9',
]) ?>

    <form
        class="border rounded-5 p-3 p-md-4"
        action="<?= $formAction ?>"
        method="post"
        enctype="multipart/form-data"
        data-post-form
        data-post-autosave
        data-autosave-url="<?= $autosaveUrl ?>"
        data-autosave-post-id="<?= $is_edit ? (int)($entity['id'] ?? 0) : 0 ?>"
        data-autosave-saving="<?= htmlSC(return_translation('admin_post_autosave_saving')) ?>"
        data-autosave-saved="<?= htmlSC(return_translation('admin_post_autosave_saved')) ?>"
        data-autosave-error="<?= htmlSC(return_translation('admin_post_autosave_error')) ?>"
        data-required-summary="<?= htmlSC($requiredSummaryLabel) ?>"
    >
        <?= get_csrf_field() ?>
        <div class="alert alert-danger<?= empty($formErrors) ? ' d-none' : '' ?>" data-post-form-errors <?= empty($formErrors) ? 'hidden' : '' ?>>
            <?php if (!empty($formErrors)): ?>
                <strong><?= htmlSC($requiredSummaryLabel) ?></strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($formErrors as $fieldErrors): ?>
                        <?php foreach ((array)$fieldErrors as $fieldError): ?>
                            <li><?= htmlSC($fieldError) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= $isPageEditor ? print_translation('admin_page_field_title') : print_translation('admin_posts_col_title') ?></label>
                <input class="form-control <?= get_validation_class('title') ?>" type="text" name="title" value="<?= old('title') ?: htmlSC($entity['title'] ?? '') ?>" data-slug-source="#post_slug" required>
                <?= get_errors('title') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= $isPageEditor ? print_translation('admin_page_field_slug') : 'URL' ?></label>
                <input class="form-control <?= get_validation_class('slug') ?>" type="text" id="post_slug" name="slug" value="<?= old('slug') ?: htmlSC($entity['slug'] ?? '') ?>" inputmode="url" autocomplete="off" spellcheck="false" autocapitalize="off" lang="en" pattern="[a-z0-9-]+" data-slug-input required>
                <?= get_errors('slug') ?>
            </div>
            <?php if ($isPageEditor): ?>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_page_field_menu_title') ?></label>
                    <input class="form-control <?= get_validation_class('menu_title') ?>" type="text" name="menu_title" value="<?= old('menu_title') ?: htmlSC($entity['menu_title'] ?? '') ?>">
                    <div class="form-text"><?= print_translation('admin_page_field_menu_title_hint') ?></div>
                    <?= get_errors('menu_title') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= print_translation('admin_page_field_menu_order') ?></label>
                    <input class="form-control <?= get_validation_class('menu_order') ?>" type="number" name="menu_order" value="<?= array_key_exists('menu_order', $formData) ? (int)$formData['menu_order'] : (int)($entity['menu_order'] ?? 0) ?>" min="0" step="1">
                    <div class="form-text"><?= print_translation('admin_page_field_menu_order_hint') ?></div>
                    <?= get_errors('menu_order') ?>
                </div>
                <div class="col-md-6">
                    <?php
                    $menuVisibility = (string)($formData['menu_visibility'] ?? '');
                    if ($menuVisibility === '') {
                        $showInHeader = (int)($entity['show_in_header'] ?? 0);
                        $showInFooter = (int)($entity['show_in_footer'] ?? 0);
                        $menuVisibility = match (true) {
                            $showInHeader === 1 && $showInFooter === 1 => 'both',
                            $showInHeader === 1 => 'header',
                            $showInFooter === 1 => 'footer',
                            default => 'none',
                        };
                    }
                    ?>
                    <label class="form-label"><?= print_translation('admin_page_field_menu_visibility') ?></label>
                    <select class="form-select <?= get_validation_class('menu_visibility') ?>" name="menu_visibility" data-select='{"removeItemButton":false,"position":"auto"}' data-select-floating data-select-clear="false" aria-label="<?= htmlSC(return_translation('admin_page_field_menu_visibility')) ?>">
                        <option value="none" <?= $menuVisibility === 'none' ? 'selected' : '' ?>><?= print_translation('admin_page_visibility_none') ?></option>
                        <option value="header" <?= $menuVisibility === 'header' ? 'selected' : '' ?>><?= print_translation('admin_page_visibility_header') ?></option>
                        <option value="footer" <?= $menuVisibility === 'footer' ? 'selected' : '' ?>><?= print_translation('admin_page_visibility_footer') ?></option>
                        <option value="both" <?= $menuVisibility === 'both' ? 'selected' : '' ?>><?= print_translation('admin_page_visibility_both') ?></option>
                    </select>
                    <div class="form-text"><?= print_translation('admin_page_field_menu_visibility_hint') ?></div>
                    <?= get_errors('menu_visibility') ?>
                </div>
            <?php else: ?>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_posts_col_category') ?></label>
                <?php $selectedCategoryId = (int)(old('category_id') ?: ($post['category_id'] ?? 0)); ?>
                <select class="form-select <?= get_validation_class('category_id') ?>" name="category_id" data-select='{"removeItemButton":false,"position":"auto"}' data-select-floating data-select-clear="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_category')) ?>" required>
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
                <label class="form-label"><?= print_translation('admin_posts_col_priority') ?></label>
                <input
                    class="form-control"
                    type="number"
                    name="priority"
                    value="<?= $priorityValue ?>"
                    min="0"
                    step="1"
                >
                <div class="form-text"><?= print_translation('admin_post_priority_hint') ?></div>
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
                <textarea class="form-control" name="excerpt" rows="2"><?= htmlSC($excerptValue) ?></textarea>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <div class="mb-3">
                    <h2 class="h5 mb-1"><?= $isPageEditor ? print_translation('admin_page_content') : print_translation('admin_post_content') ?></h2>
                    <p class="text-body-secondary mb-0"><?= print_translation('admin_post_builder_hint') ?></p>
                </div>
                <?= \App\Modules\BlockEditor\BlockEditor::render([
                    'entity_type' => $isPageEditor ? 'page' : 'post',
                    'entity_id' => (int)($entity['id'] ?? 0),
                    'field_name' => 'content',
                    'field_id' => 'post_content',
                    'content' => $contentValue,
                    'validation_class' => get_validation_class('content'),
                    'editor_id' => $isPageEditor ? 'pageBlockEditor' : 'postBlockEditor',
                    'default_directory' => $editorDefaultDirectory,
                ]) ?>
                <?= get_errors('content') ?>
            </div>
            <?php if (!$isPageEditor): ?>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_post_image') ?></label>
                <input
                    class="d-none"
                    type="text"
                    id="post_image"
                    name="image"
                    value="<?= htmlSC($currentImage) ?>"
                    data-file-preview-image="#post_image_preview"
                    data-file-preview-text="#post_image_path"
                    data-file-preview-note="#post_image_note"
                    data-file-preview-placeholder="#post_image_placeholder"
                    data-file-preview-root="#post_image_media_picker"
                    data-file-preview-empty-text="<?= htmlSC($postImageDropTitle) ?>"
                    data-file-preview-empty-note="<?= htmlSC($postImageDropHint) ?>"
                    data-file-preview-base="<?= htmlSC(rtrim(base_url('/'), '/')) ?>"
                    data-file-picker-note="<?= htmlSC($postImagePickerHint) ?>"
                    data-file-upload-input="#post_image_file"
                    data-file-url-input="#post_image_url"
                    data-file-url-note="<?= htmlSC($postImageUrlHint) ?>"
                >
                <div
                    class="fb-post-media-picker<?= get_validation_class('image_file') ? ' is-invalid' : '' ?>"
                    id="post_image_media_picker"
                    data-file-dropzone
                    data-file-input="#post_image_file"
                    data-file-path-input="#post_image"
                    data-file-empty-text="<?= htmlSC($postImageDropTitle) ?>"
                    data-file-empty-note="<?= htmlSC($postImageDropHint) ?>"
                    data-file-local-note="<?= htmlSC($postImageLocalHint) ?>"
                    data-media-url-open="<?= old('image_url') || $currentImageUrl ? 'true' : 'false' ?>"
                >
                    <div class="fb-post-media-picker__dropzone" tabindex="0" role="button" aria-label="<?= htmlSC($postImageDropTitle) ?>">
                        <div class="fb-post-media-picker__figure">
                            <img
                                src="<?= $currentImage ? get_image($currentImage) : '' ?>"
                                alt=""
                                id="post_image_preview"
                                class="<?= $currentImage ? '' : 'd-none' ?>"
                            >
                            <div class="fb-post-media-picker__placeholder <?= $currentImage ? 'd-none' : '' ?>" id="post_image_placeholder">
                                <i class="ci-image"></i>
                            </div>
                        </div>
                        <div class="fb-post-media-picker__body">
                            <div class="fb-post-media-picker__head">
                                <div class="fb-post-media-picker__heading"><?= print_translation('admin_post_image') ?></div>
                                <div class="fb-post-media-picker__note" id="post_image_note"><?= htmlSC($currentImage ? $postImagePickerHint : $postImageDropHint) ?></div>
                                <div class="fb-post-media-picker__meta" id="post_image_path"><?= htmlSC($currentImage ?: $postImageDropTitle) ?></div>
                            </div>
                            <div class="fb-post-media-picker__actions">
                                <label class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" for="post_image_file">
                                    <i class="ci-upload"></i><?= print_translation('admin_btn_choose_file') ?>
                                </label>
                                <a
                                    class="btn btn-dark d-inline-flex align-items-center gap-2"
                                    href="<?= base_href('/admin/files') ?>?picker=1&amp;field=post_image&amp;dir=posts&amp;return_url=<?= rawurlencode(current_url_with_query()) ?>"
                                    target="fireball_file_manager"
                                ><i class="ci-folder"></i><?= print_translation('admin_nav_files') ?></a>
                                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" data-media-toggle-url aria-expanded="<?= old('image_url') || $currentImageUrl ? 'true' : 'false' ?>">
                                    <i class="ci-link-2"></i><?= htmlSC($postImageLinkActionLabel) ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-danger-subtle d-inline-flex align-items-center gap-2" data-media-clear>
                                    <i class="ci-trash"></i><?= htmlSC($clearLabel) ?>
                                </button>
                            </div>
                            <div class="fb-post-media-picker__source-inline" data-media-url-panel<?= old('image_url') || $currentImageUrl ? '' : ' hidden' ?>>
                                <div class="fb-post-media-picker__source-head">
                                    <span class="fb-post-media-picker__source-icon"><i class="ci-link-2"></i></span>
                                    <div>
                                        <div class="fb-post-media-picker__source-title"><?= print_translation('admin_post_image_url') ?></div>
                                        <div class="fb-post-media-picker__source-note"><?= print_translation('admin_post_image_url_hint') ?></div>
                                    </div>
                                </div>
                                <input
                                    class="form-control <?= get_validation_class('image_url') ?>"
                                    type="text"
                                    id="post_image_url"
                                    name="image_url"
                                    value="<?= old('image_url') ?: htmlSC($currentImageUrl) ?>"
                                    placeholder="https://example.com/image.jpg"
                                    inputmode="url"
                                    autocomplete="off"
                                    spellcheck="false"
                                    autocapitalize="off"
                                    lang="en"
                                >
                                <?= get_errors('image_url') ?>
                            </div>
                        </div>
                    </div>
                    <div class="fb-post-media-picker__file-input-wrap d-none">
                        <input
                            class="form-control fb-post-media-picker__file-input <?= get_validation_class('image_file') ?>"
                            type="file"
                            id="post_image_file"
                            name="image_file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                        >
                    </div>
                </div>
                <?= get_errors('image_file') ?>
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
                                <input class="form-control <?= get_validation_class('seo_image') ?>" type="text" id="post_seo_image" name="seo_image" value="<?= old('seo_image') ?: htmlSC($post['seo_image'] ?? '') ?>" placeholder="/uploads/posts/cover.jpg" inputmode="url" autocomplete="off" spellcheck="false" autocapitalize="off" lang="en">
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
            <?php else: ?>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_seo_section_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_seo_section_subtitle') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_seo_title') ?></label>
                            <input class="form-control" type="text" name="meta_title" value="<?= old('meta_title') ?: htmlSC($entity['meta_title'] ?? '') ?>">
                            <div class="form-text"><?= print_translation('admin_seo_title_hint') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_seo_description') ?></label>
                            <textarea class="form-control" name="meta_description" rows="3"><?= old('meta_description') ?: htmlSC($entity['meta_description'] ?? '') ?></textarea>
                            <div class="form-text"><?= print_translation('admin_seo_description_hint') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <?php $isPublished = array_key_exists('is_published', $formData) ? (int)$formData['is_published'] : (int)($entity['is_published'] ?? ($isPageEditor ? 0 : 1)); ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= $isPublished === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_published"><?= print_translation('admin_posts_status_published') ?></label>
                </div>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= $listUrl ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
                </div>
            </div>
            <div class="col-12">
                <div class="alert d-flex alert-success d-none mb-0" role="alert" data-post-autosave-card>
                    <i class="ci-check-circle fs-lg pe-1 mt-1 me-2"></i>
                    <div>
                        <span class="fw-semibold"><?= print_translation('admin_post_autosave_title') ?>:</span>
                        <span data-post-autosave-status aria-live="polite"></span>
                    </div>
                </div>
            </div>
        </div>
    </form>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileSelectionStorageKey = 'fireball:file:selected';
    const popupName = 'fireball_file_manager';
    let localPreviewUrl = null;

    function clearLocalPreviewUrl() {
        if (localPreviewUrl) {
            URL.revokeObjectURL(localPreviewUrl);
            localPreviewUrl = null;
        }
    }

    function setPickerPreview(input, options) {
        if (!input) {
            return;
        }

        const value = String(options && options.value ? options.value : '');
        const imageSrc = String(options && options.imageSrc ? options.imageSrc : value);
        const noteText = String(options && options.note ? options.note : '');
        const imageSelector = input.getAttribute('data-file-preview-image');
        const textSelector = input.getAttribute('data-file-preview-text');
        const noteSelector = input.getAttribute('data-file-preview-note');
        const placeholderSelector = input.getAttribute('data-file-preview-placeholder');
        const rootSelector = input.getAttribute('data-file-preview-root');
        const emptyText = String(input.getAttribute('data-file-preview-empty-text') || '');
        const emptyNote = String(input.getAttribute('data-file-preview-empty-note') || '');
        const previewBase = String(input.getAttribute('data-file-preview-base') || '');
        const image = imageSelector ? document.querySelector(imageSelector) : null;
        const text = textSelector ? document.querySelector(textSelector) : null;
        const note = noteSelector ? document.querySelector(noteSelector) : null;
        const placeholder = placeholderSelector ? document.querySelector(placeholderSelector) : null;
        const root = rootSelector ? document.querySelector(rootSelector) : null;
        const hasValue = value !== '';
        const resolvedImageSrc = hasValue && imageSrc !== '' && !/^(?:https?:)?\/\//i.test(imageSrc) && !/^(?:blob:|data:)/i.test(imageSrc)
            ? (imageSrc.charAt(0) === '/' ? previewBase + imageSrc : previewBase + '/' + imageSrc.replace(/^\/+/, ''))
            : imageSrc;

        if (text) {
            text.textContent = hasValue ? value : emptyText;
        }

        if (note) {
            note.textContent = hasValue ? noteText : emptyNote;
        }

        if (image) {
            image.classList.toggle('d-none', !hasValue);
            image.setAttribute('src', hasValue ? resolvedImageSrc : '');
        }

        if (placeholder) {
            placeholder.classList.toggle('d-none', hasValue);
        }

        if (root) {
            root.classList.toggle('is-empty', !hasValue);
            root.classList.toggle('is-local-file', Boolean(options && options.isLocalFile));
        }
    }

    function applySelectedFile(payload) {
        if (!payload || payload.type !== 'fireball:file:selected') {
            return;
        }

        const input = document.getElementById(String(payload.field || ''));
        if (!input) {
            return;
        }

        input.value = String(payload.value || '');
        const urlInputSelector = input.getAttribute('data-file-url-input');
        const urlInput = urlInputSelector ? document.querySelector(urlInputSelector) : null;
        if (urlInput) {
            urlInput.value = String(payload.value || '');
        }

        const rootSelector = input.getAttribute('data-file-preview-root');
        const root = rootSelector ? document.querySelector(rootSelector) : null;
        const urlPanel = root ? root.querySelector('[data-media-url-panel]') : null;
        const urlToggle = root ? root.querySelector('[data-media-toggle-url]') : null;
        if (root) {
            root.setAttribute('data-media-url-open', 'false');
        }
        if (urlPanel) {
            urlPanel.hidden = true;
        }
        if (urlToggle) {
            urlToggle.setAttribute('aria-expanded', 'false');
        }

        const uploadInputSelector = input.getAttribute('data-file-upload-input');
        const pickerNote = String(input.getAttribute('data-file-picker-note') || '');
        const uploadInput = uploadInputSelector ? document.querySelector(uploadInputSelector) : null;
        if (uploadInput) {
            uploadInput.value = '';
            clearLocalPreviewUrl();
        }

        setPickerPreview(input, {
            value: String(payload.value || ''),
            imageSrc: String(payload.value || ''),
            note: pickerNote
        });

        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));

        try {
            localStorage.removeItem(fileSelectionStorageKey);
        } catch (error) {
        }
    }

    function consumeStoredFileSelection() {
        let payload = null;

        try {
            payload = JSON.parse(String(localStorage.getItem(fileSelectionStorageKey) || ''));
        } catch (error) {
            payload = null;
        }

        applySelectedFile(payload);
    }

    function consumeQueryFileSelection() {
        const url = new URL(window.location.href);
        const field = String(url.searchParams.get('fireball_file_field') || '');
        const value = String(url.searchParams.get('fireball_file_value') || '');

        if (!field || !value) {
            return;
        }

        applySelectedFile({
            type: 'fireball:file:selected',
            field: field,
            value: value
        });

        url.searchParams.delete('fireball_file_field');
        url.searchParams.delete('fireball_file_value');
        window.history.replaceState({}, document.title, url.toString());
    }

    document.querySelectorAll('[data-file-manager-open]').forEach(function (button) {
        if (button.dataset.fileManagerPickerBound === 'true') {
            return;
        }

        button.dataset.fileManagerPickerBound = 'true';
        button.addEventListener('click', function () {
            const inputId = String(button.getAttribute('data-file-manager-input') || '');
            const baseUrl = String(button.getAttribute('data-file-manager-url') || '');
            const directory = String(button.getAttribute('data-file-manager-dir') || '');

            if (!inputId || !baseUrl) {
                return;
            }

            const url = new URL(baseUrl, window.location.origin);
            url.searchParams.set('picker', '1');
            url.searchParams.set('field', inputId);

            if (directory) {
                url.searchParams.set('dir', directory);
            }

            window.open(url.toString(), popupName, 'width=1280,height=860,resizable=yes,scrollbars=yes');
        });
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'fireball:file:selected') {
            return;
        }
        applySelectedFile(event.data);
    });

    window.addEventListener('storage', function (event) {
        if (event.key !== fileSelectionStorageKey || !event.newValue) {
            return;
        }

        let payload = null;

        try {
            payload = JSON.parse(String(event.newValue || ''));
        } catch (error) {
            payload = null;
        }

        applySelectedFile(payload);
    });

    window.addEventListener('pageshow', function () {
        consumeStoredFileSelection();
    });

    window.addEventListener('focus', function () {
        consumeStoredFileSelection();
    });

    document.querySelectorAll('[data-file-dropzone]').forEach(function (dropzone) {
        const inputSelector = String(dropzone.getAttribute('data-file-input') || '');
        const pathInputSelector = String(dropzone.getAttribute('data-file-path-input') || '');
        const fileInput = inputSelector ? document.querySelector(inputSelector) : null;
        const pathInput = pathInputSelector ? document.querySelector(pathInputSelector) : null;
        const urlInputSelector = pathInput ? String(pathInput.getAttribute('data-file-url-input') || '') : '';
        const urlInput = urlInputSelector ? document.querySelector(urlInputSelector) : null;
        const urlPanel = dropzone.querySelector('[data-media-url-panel]');
        const urlToggle = dropzone.querySelector('[data-media-toggle-url]');
        const clearButton = dropzone.querySelector('[data-media-clear]');
        const pickerNote = pathInput ? String(pathInput.getAttribute('data-file-picker-note') || '') : '';
        const urlNote = pathInput ? String(pathInput.getAttribute('data-file-url-note') || '') : '';
        const emptyNote = String(dropzone.getAttribute('data-file-empty-note') || '');
        const localNote = String(dropzone.getAttribute('data-file-local-note') || '');
        const initialUrlOpen = String(dropzone.getAttribute('data-media-url-open') || '') === 'true';

        if (!fileInput || !pathInput) {
            return;
        }

        function setUrlPanelOpen(shouldOpen) {
            if (!urlPanel) {
                return;
            }

            urlPanel.hidden = !shouldOpen;
            dropzone.setAttribute('data-media-url-open', shouldOpen ? 'true' : 'false');

            if (urlToggle) {
                urlToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            }
        }

        function showPathPreview() {
            clearLocalPreviewUrl();
            setPickerPreview(pathInput, {
                value: String(pathInput.value || ''),
                imageSrc: String(pathInput.value || ''),
                note: urlInput && String(urlInput.value || '').trim() !== '' ? urlNote : pickerNote
            });
        }

        function syncUrlToPath() {
            if (!urlInput) {
                return;
            }

            pathInput.value = String(urlInput.value || '').trim();
            fileInput.value = '';
            showPathPreview();
        }

        function showLocalFilePreview(file) {
            if (!file) {
                showPathPreview();
                return;
            }

            clearLocalPreviewUrl();
            if (urlInput) {
                urlInput.value = '';
            }
            setUrlPanelOpen(false);
            localPreviewUrl = file.type && file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : null;
            setPickerPreview(pathInput, {
                value: file.name,
                imageSrc: localPreviewUrl || '',
                note: localNote,
                isLocalFile: true
            });
        }

        function clearPicker() {
            clearLocalPreviewUrl();
            pathInput.value = '';
            fileInput.value = '';

            if (urlInput) {
                urlInput.value = '';
            }

            setUrlPanelOpen(false);
            setPickerPreview(pathInput, {
                value: '',
                imageSrc: '',
                note: emptyNote
            });

            pathInput.dispatchEvent(new Event('input', { bubbles: true }));
            pathInput.dispatchEvent(new Event('change', { bubbles: true }));

            if (urlInput) {
                urlInput.dispatchEvent(new Event('input', { bubbles: true }));
                urlInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            showLocalFilePreview(file);
        });

        if (urlInput) {
            urlInput.addEventListener('input', syncUrlToPath);
            urlInput.addEventListener('change', syncUrlToPath);
        }

        if (urlToggle && urlPanel && urlInput) {
            urlToggle.addEventListener('click', function () {
                const shouldOpen = urlPanel.hidden;
                setUrlPanelOpen(shouldOpen);

                if (shouldOpen) {
                    window.requestAnimationFrame(function () {
                        urlInput.focus();
                        urlInput.select();
                    });
                }
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                clearPicker();
            });
        }

        dropzone.addEventListener('click', function (event) {
            if (event.target.closest('button, label, a, input')) {
                return;
            }
            if (!event.target.closest('.fb-post-media-picker__figure, .fb-post-media-picker__placeholder')) {
                return;
            }
            fileInput.click();
        });

        dropzone.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('button, label, a, input')) {
                event.preventDefault();
                fileInput.click();
            }
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                if (event.target === dropzone) {
                    dropzone.classList.remove('is-dragover');
                }
            });
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');

            const files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
            if (!files || !files.length) {
                return;
            }

            if (typeof DataTransfer !== 'undefined') {
                const transfer = new DataTransfer();
                Array.from(files).forEach(function (file) {
                    transfer.items.add(file);
                });
                fileInput.files = transfer.files;
            } else {
                fileInput.files = files;
            }

            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        if (!String(pathInput.value || '').trim()) {
            setPickerPreview(pathInput, {
                value: '',
                imageSrc: '',
                note: emptyNote
            });
        } else {
            setPickerPreview(pathInput, {
                value: String(pathInput.value || ''),
                imageSrc: String(pathInput.value || ''),
                note: urlInput && String(urlInput.value || '').trim() !== '' ? urlNote : pickerNote
            });
        }

        if (urlInput && !String(urlInput.value || '').trim() && String(pathInput.value || '').trim()) {
            urlInput.value = String(pathInput.value || '');
        }

        setUrlPanelOpen(initialUrlOpen);
    });

    consumeStoredFileSelection();
    consumeQueryFileSelection();
});
</script>
<?= view()->renderPartial('admin/shell_close') ?>
