<?php
$formData = session()->get('form_data') ?: [];
$theme = $theme_item ?? [];
$isEdit = (bool)($is_edit ?? false);
$created = (bool)($created ?? false);
$slug = (string)($theme['slug'] ?? ($formData['slug'] ?? ''));
$name = (string)($formData['name'] ?? ($theme['name'] ?? ''));
$author = (string)($formData['author'] ?? ($theme['author'] ?? ''));
$description = (string)($formData['description'] ?? ($theme['description'] ?? ''));
$version = (string)($formData['version'] ?? ($theme['version'] ?? '1.0.0'));
$preview = (string)($formData['preview'] ?? ($theme['preview'] ?? 'preview.png'));
$previewSource = (string)($formData['preview_source'] ?? '');
$previewUrl = $previewSource !== '' ? $previewSource : (string)($theme['preview_url'] ?? '');
$formAction = $isEdit ? base_href('/admin/themes/edit/' . $slug) : base_href('/admin/themes/create');
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $isEdit ? return_translation('admin_themes_edit_heading') : return_translation('admin_themes_create_heading'),
    'subtitle' => $isEdit ? return_translation('admin_themes_edit_subtitle') : return_translation('admin_themes_create_subtitle'),
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/themes') . '">' . htmlSC(return_translation('admin_themes_back_to_list')) . '</a>',
]) ?>

    <?php if ($created): ?>
        <div class="alert alert-success rounded-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div><?= print_translation('admin_themes_created') ?></div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/themes') ?>"><?= print_translation('admin_themes_back_to_list') ?></a>
                <form action="<?= base_href('/admin/themes/activate') ?>" method="post">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                    <button class="btn btn-sm btn-dark rounded-pill" type="submit"><?= print_translation('admin_themes_activate') ?></button>
                </form>
                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/themes/files/' . $slug) ?>"><?= print_translation('admin_themes_files') ?></a>
            </div>
        </div>
    <?php endif; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= $formAction ?>" method="post" enctype="multipart/form-data">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_themes_field_name') ?></label>
                <input class="form-control <?= get_validation_class('name') ?>" type="text" name="name" value="<?= htmlSC($name) ?>" required>
                <?= get_errors('name') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input class="form-control <?= get_validation_class('slug') ?>" type="text" name="slug" value="<?= htmlSC($slug) ?>" <?= $isEdit ? 'readonly' : 'required' ?> placeholder="my_theme">
                <div class="form-text"><?= print_translation('admin_themes_slug_hint') ?></div>
                <?= get_errors('slug') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_themes_field_author') ?></label>
                <input class="form-control" type="text" name="author" value="<?= htmlSC($author) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_themes_field_version') ?></label>
                <input class="form-control <?= get_validation_class('version') ?>" type="text" name="version" value="<?= htmlSC($version) ?>" required>
                <?= get_errors('version') ?>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_themes_field_description') ?></label>
                <textarea class="form-control" name="description" rows="4"><?= htmlSC($description) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_themes_field_preview') ?></label>
                <input class="form-control <?= get_validation_class('preview') ?>" type="text" name="preview" value="<?= htmlSC($preview) ?>" placeholder="preview.png">
                <div class="form-text"><?= print_translation('admin_themes_preview_hint') ?></div>
                <?= get_errors('preview') ?>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_themes_field_preview_source') ?></label>
                <div class="input-group">
                    <input
                        class="form-control <?= get_validation_class('preview_source') ?>"
                        type="text"
                        id="theme_preview_source"
                        name="preview_source"
                        value="<?= htmlSC($previewSource) ?>"
                        placeholder="/uploads/themes/preview.png"
                        data-file-preview-image="#theme_preview_source_preview_image"
                        data-file-preview-text="#theme_preview_source_preview_text"
                    >
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        data-file-manager-open
                        data-file-manager-input="theme_preview_source"
                        data-file-manager-dir="themes"
                        data-file-manager-url="<?= base_href('/admin/files') ?>"
                    ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                </div>
                <div class="form-text"><?= print_translation('admin_themes_preview_source_hint') ?></div>
                <?= get_errors('preview_source') ?>
                <div class="d-flex align-items-center gap-3 mt-3 <?= $previewUrl === '' ? 'd-none' : '' ?>" id="theme_preview_source_preview_wrap">
                    <span class="border rounded-3 d-inline-flex align-items-center justify-content-center bg-body-tertiary overflow-hidden" style="width: 5rem; height: 3rem;">
                        <img id="theme_preview_source_preview_image" src="<?= htmlSC($previewUrl) ?>" alt="theme preview" style="width: 100%; height: 100%; object-fit: cover;">
                    </span>
                    <code class="small text-break" id="theme_preview_source_preview_text"><?= htmlSC($previewSource !== '' ? $previewSource : $preview) ?></code>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_themes_field_preview_upload') ?></label>
                <input class="form-control <?= get_validation_class('preview_upload') ?>" type="file" name="preview_upload" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                <div class="form-text"><?= print_translation('admin_themes_preview_upload_hint') ?></div>
                <?= get_errors('preview_upload') ?>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill" type="submit"><?= print_translation('admin_btn_save') ?></button>
                <?php if ($isEdit): ?>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/themes/files/' . $slug) ?>"><?= print_translation('admin_themes_files') ?></a>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/themes/preview/' . $slug) ?>" target="_blank" rel="noopener noreferrer"><?= print_translation('admin_themes_preview') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
