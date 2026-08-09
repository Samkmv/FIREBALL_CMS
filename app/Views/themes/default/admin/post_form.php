<?php
$editorMode = (string)($editor_mode ?? 'post');
$isPageEditor = $editorMode === 'page';
$entity = $isPageEditor ? (array)($page ?? []) : (array)($post ?? []);
$formData = (array)(session()->get('form_data') ?: []);
$formErrors = (array)(session()->get('form_errors') ?: []);
$entityId = (int)($entity['id'] ?? 0);
$listUrl = $isPageEditor ? base_href('/admin/pages') : base_href('/admin/posts');
$formAction = $isPageEditor
    ? ($is_edit ? base_href('/admin/pages/edit/' . $entityId) : base_href('/admin/pages/create'))
    : ($is_edit ? base_href('/admin/posts/edit/' . $entityId) : base_href('/admin/posts/create'));
$autosaveUrl = $isPageEditor ? base_href('/admin/pages/autosave') : base_href('/admin/posts/autosave');
$previewUrl = $entityId > 0
    ? ($isPageEditor ? base_href('/admin/pages/preview/' . $entityId) : base_href('/admin/posts/preview/' . $entityId))
    : '';
$contentValue = array_key_exists('content', $formData)
    ? (string)$formData['content']
    : (string)($entity['content'] ?? '');
$titleValue = array_key_exists('title', $formData)
    ? (string)$formData['title']
    : (string)($entity['title'] ?? '');
$slugValue = array_key_exists('slug', $formData)
    ? (string)$formData['slug']
    : (string)($entity['slug'] ?? '');
$isPublished = array_key_exists('is_published', $formData)
    ? (int)$formData['is_published']
    : (int)($entity['is_published'] ?? ($isPageEditor ? 0 : 1));
$publishedAtSource = (string)($formData['published_at'] ?? ($entity['published_at'] ?? ''));
$publishedAtValue = $publishedAtSource !== '' && strtotime($publishedAtSource)
    ? date('Y-m-d H:i:s', strtotime($publishedAtSource))
    : date('Y-m-d H:i:s');
$currentImage = trim((string)($formData['image'] ?? ($entity['image'] ?? '')));
$currentImageUrl = array_key_exists('image_url', $formData)
    ? trim((string)$formData['image_url'])
    : (filter_var($currentImage, FILTER_VALIDATE_URL) ? $currentImage : '');
$selectedFileField = trim((string)request()->get('fireball_file_field', ''));
$selectedFileValue = trim((string)request()->get('fireball_file_value', ''));
if ($selectedFileField !== '' && $selectedFileValue !== '') {
    if ($selectedFileField === 'post_image') {
        $currentImage = $selectedFileValue;
        $currentImageUrl = filter_var($selectedFileValue, FILTER_VALIDATE_URL) ? $selectedFileValue : '';
    }
}
$translateOrFallback = static function (string $key, string $fallback): string {
    $value = return_translation($key);
    return $value === '' || $value === $key ? $fallback : $value;
};
$workspaceTitle = $isPageEditor
    ? ($is_edit ? return_translation('admin_page_edit_heading') : return_translation('admin_page_create_heading'))
    : ($is_edit ? return_translation('admin_post_edit_heading') : return_translation('admin_post_create_heading'));
$requiredSummary = $translateOrFallback('admin_form_required_summary', 'Заполните обязательные поля:');
?>

<div
    class="fb-editor-workspace"
    data-editor-workspace
    data-editor-unsaved-confirm="<?= htmlSC($translateOrFallback('editor_unsaved_confirm', 'Есть несохранённые изменения. Покинуть редактор?')) ?>"
>
    <form
        class="fb-editor-workspace__form"
        action="<?= htmlSC($formAction) ?>"
        method="post"
        enctype="multipart/form-data"
        data-post-form
        data-post-autosave
        data-autosave-url="<?= htmlSC($autosaveUrl) ?>"
        data-autosave-post-id="<?= $entityId ?>"
        data-autosave-saving="<?= htmlSC($translateOrFallback('editor_saving', 'Сохранение…')) ?>"
        data-autosave-saved="<?= htmlSC($translateOrFallback('editor_saved', 'Сохранено')) ?>"
        data-autosave-error="<?= htmlSC($translateOrFallback('editor_save_error', 'Ошибка сохранения')) ?>"
        data-preview-url="<?= htmlSC($previewUrl) ?>"
        data-required-summary="<?= htmlSC($requiredSummary) ?>"
    >
        <?= get_csrf_field() ?>
        <input type="hidden" name="autosave_post_id" value="<?= $entityId ?>" data-editor-entity-id>
        <input type="hidden" name="is_published" value="<?= $isPublished ?>" data-editor-published-input>

        <header class="fb-editor-workspace__topbar">
            <div class="fb-editor-workspace__topbar-start">
                <a class="fb-editor-workspace__icon-button" href="<?= htmlSC($listUrl) ?>" data-editor-back aria-label="<?= htmlSC(return_translation('admin_btn_back')) ?>">
                    <i class="ci-arrow-left"></i>
                </a>
                <div class="fb-editor-workspace__brand" aria-hidden="true"><i class="ci-edit-3"></i></div>
                <div class="fb-editor-workspace__identity">
                    <strong><?= htmlSC($workspaceTitle) ?></strong>
                    <span><?= htmlSC($translateOrFallback('editor_title_in_document', 'Название — во вкладке «Документ»')) ?></span>
                </div>
                <div class="fb-editor-workspace__save-state" data-editor-save-state data-state="saved" aria-live="polite">
                    <span></span>
                    <span data-editor-save-state-label><?= htmlSC($translateOrFallback('editor_saved', 'Сохранено')) ?></span>
                </div>
            </div>

            <div class="fb-editor-workspace__topbar-center">
                <div class="fb-editor-workspace__mode-switch" role="group" aria-label="<?= htmlSC($translateOrFallback('editor_mode_label', 'Режим редактора')) ?>">
                    <button type="button" class="is-active" data-editor-mode="document"><?= htmlSC($translateOrFallback('editor_mode_document', 'Документ')) ?></button>
                    <button type="button" data-editor-mode="structure"><?= htmlSC($translateOrFallback('editor_mode_structure', 'Структура')) ?></button>
                </div>
            </div>

            <div class="fb-editor-workspace__topbar-actions">
                <button type="button" class="fb-editor-workspace__icon-button" data-editor-undo disabled aria-label="<?= htmlSC($translateOrFallback('editor_undo', 'Отменить')) ?>" title="<?= htmlSC($translateOrFallback('editor_undo', 'Отменить')) ?> (⌘Z)">
                    <i class="ci-undo"></i>
                </button>
                <button type="button" class="fb-editor-workspace__icon-button" data-editor-redo disabled aria-label="<?= htmlSC($translateOrFallback('editor_redo', 'Повторить')) ?>" title="<?= htmlSC($translateOrFallback('editor_redo', 'Повторить')) ?> (⇧⌘Z)">
                    <i class="ci-redo"></i>
                </button>
                <button type="button" class="fb-editor-workspace__button fb-editor-workspace__button--ghost" data-editor-preview>
                    <i class="ci-eye"></i><span><?= htmlSC($translateOrFallback('editor_preview', 'Предпросмотр')) ?></span>
                </button>
                <button type="submit" class="fb-editor-workspace__button fb-editor-workspace__button--ghost d-none d-md-inline-flex" data-editor-submit="draft">
                    <?= htmlSC($translateOrFallback('editor_save_draft', 'Сохранить черновик')) ?>
                </button>
                <button type="submit" class="fb-editor-workspace__button fb-editor-workspace__button--primary" data-editor-submit="publish">
                    <?= htmlSC($translateOrFallback('editor_publish', 'Опубликовать')) ?>
                </button>
                <div class="dropdown">
                    <button type="button" class="fb-editor-workspace__icon-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= htmlSC($translateOrFallback('editor_more', 'Ещё')) ?>">
                        <i class="ci-more-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2">
                        <button type="button" class="dropdown-item rounded-3" data-editor-focus><i class="ci-maximize-2 me-2"></i><?= htmlSC($translateOrFallback('editor_focus_mode', 'Режим фокуса')) ?></button>
                        <button type="button" class="dropdown-item rounded-3" data-editor-fullscreen><i class="ci-maximize me-2"></i><?= htmlSC($translateOrFallback('editor_fullscreen', 'Полный экран')) ?></button>
                        <button type="button" class="dropdown-item rounded-3" data-editor-preview-right><i class="ci-sidebar me-2"></i><?= htmlSC($translateOrFallback('editor_preview_right', 'Предпросмотр справа')) ?></button>
                        <button type="button" class="dropdown-item rounded-3" data-editor-preview-new><i class="ci-external-link me-2"></i><?= htmlSC($translateOrFallback('editor_preview_new', 'Предпросмотр в новой вкладке')) ?></button>
                        <button type="button" class="dropdown-item rounded-3" data-editor-command-palette><i class="ci-command me-2"></i><?= htmlSC($translateOrFallback('editor_command_search', 'Команды')) ?></button>
                        <button type="button" class="dropdown-item rounded-3" data-editor-document-settings><i class="ci-settings me-2"></i><?= htmlSC($translateOrFallback('editor_document_settings', 'Настройки документа')) ?></button>
                    </div>
                </div>
                <button type="button" class="fb-editor-workspace__icon-button fb-editor-workspace__mobile-panels" data-editor-mobile-panels aria-label="<?= htmlSC($translateOrFallback('editor_panels', 'Панели')) ?>">
                    <i class="ci-sidebar"></i>
                </button>
            </div>
        </header>

        <div class="fb-editor-workspace__alerts">
            <?php get_alerts(); ?>
            <?php if ($formErrors !== []): ?>
                <div class="alert alert-danger" role="alert" data-post-form-errors>
                    <strong><?= htmlSC($requiredSummary) ?></strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($formErrors as $fieldErrors): ?>
                            <?php foreach ((array)$fieldErrors as $fieldError): ?>
                                <li><?= htmlSC((string)$fieldError) ?></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="fb-editor-workspace__body">
            <aside class="fb-editor-workspace__outline-panel" data-editor-outline-panel>
                <div class="fb-editor-workspace__panel-head">
                    <strong><?= htmlSC($translateOrFallback('editor_outline', 'Структура')) ?></strong>
                    <button type="button" data-editor-panel-close aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"><i class="ci-close"></i></button>
                </div>
                <label class="fb-editor-workspace__outline-filter">
                    <i class="ci-search" aria-hidden="true"></i>
                    <span class="visually-hidden"><?= htmlSC($translateOrFallback('editor_outline_filter', 'Фильтр структуры')) ?></span>
                    <input type="search" data-editor-outline-filter placeholder="<?= htmlSC($translateOrFallback('editor_outline_filter', 'Фильтр структуры')) ?>" autocomplete="off">
                </label>
                <div class="fb-editor-workspace__outline" data-editor-outline></div>
                <button type="button" class="fb-editor-workspace__add-block" data-editor-add-block>
                    <i class="ci-plus"></i><?= htmlSC($translateOrFallback('admin_post_builder_add_block', 'Добавить блок')) ?>
                </button>
            </aside>

            <main class="fb-editor-workspace__document">
                <div class="fb-editor-workspace__document-inner">
                    <?= \App\Modules\BlockEditor\BlockEditor::render([
                        'entity_type' => $isPageEditor ? 'page' : 'post',
                        'entity_id' => $entityId,
                        'field_name' => 'content',
                        'field_id' => 'post_content',
                        'content' => $contentValue,
                        'validation_class' => get_validation_class('content'),
                        'editor_id' => $isPageEditor ? 'pageBlockEditor' : 'postBlockEditor',
                        'default_directory' => $isPageEditor ? 'pages' : 'posts',
                    ]) ?>
                    <?= get_errors('content') ?>
                </div>
            </main>

            <section class="fb-editor-workspace__split-preview" data-editor-split-preview hidden>
                <header>
                    <strong><?= htmlSC($translateOrFallback('editor_preview', 'Предпросмотр')) ?></strong>
                    <button type="button" data-editor-preview-right-close aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"><i class="ci-close"></i></button>
                </header>
                <iframe title="<?= htmlSC($translateOrFallback('editor_preview', 'Предпросмотр')) ?>" sandbox="allow-same-origin" data-editor-split-preview-frame></iframe>
            </section>

            <aside class="fb-editor-workspace__inspector-panel" data-editor-inspector-panel>
                <div class="fb-editor-workspace__panel-head fb-editor-workspace__inspector-tabs" role="tablist">
                    <button type="button" class="is-active" data-editor-inspector-tab="block"><?= htmlSC($translateOrFallback('editor_tab_block', 'Блок')) ?></button>
                    <button type="button" data-editor-inspector-tab="document"><?= htmlSC($translateOrFallback('editor_tab_document', 'Документ')) ?></button>
                    <button type="button" data-editor-panel-close aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"><i class="ci-close"></i></button>
                </div>

                <div class="fb-editor-workspace__inspector" data-editor-inspector-tab-panel="block">
                    <div data-editor-inspector></div>
                </div>

                <div class="fb-editor-workspace__document-settings" data-editor-inspector-tab-panel="document" hidden>
                    <section>
                        <h2><?= htmlSC($translateOrFallback('editor_document_settings', 'Настройки документа')) ?></h2>
                        <label class="fb-editor-field fb-editor-field--document-title">
                            <span><?= htmlSC($isPageEditor ? return_translation('admin_page_field_title') : return_translation('admin_posts_col_title')) ?></span>
                            <input
                                class="form-control <?= get_validation_class('title') ?>"
                                type="text"
                                name="title"
                                value="<?= htmlSC($titleValue) ?>"
                                placeholder="<?= htmlSC($isPageEditor ? return_translation('admin_page_field_title') : return_translation('admin_posts_col_title')) ?>"
                                autocomplete="off"
                                data-editor-document-title
                                data-slug-source="#post_slug"
                                required
                            >
                            <?= get_errors('title') ?>
                        </label>
                        <label class="fb-editor-field">
                            <span><?= htmlSC($isPageEditor ? return_translation('admin_page_field_slug') : 'URL') ?></span>
                            <input class="form-control <?= get_validation_class('slug') ?>" type="text" id="post_slug" name="slug" value="<?= htmlSC($slugValue) ?>" pattern="[a-z0-9-]+" autocomplete="off" data-slug-input required>
                            <?= get_errors('slug') ?>
                        </label>

                        <?php if (!$isPageEditor): ?>
                            <?php $selectedCategoryId = (int)($formData['category_id'] ?? ($entity['category_id'] ?? 0)); ?>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_posts_col_category')) ?></span>
                                <select class="form-select <?= get_validation_class('category_id') ?>" name="category_id" required>
                                    <option value=""><?= htmlSC(return_translation('admin_posts_select_category')) ?></option>
                                    <?php foreach ((array)($categories ?? []) as $category): ?>
                                        <option value="<?= (int)$category['id'] ?>" <?= $selectedCategoryId === (int)$category['id'] ? 'selected' : '' ?>><?= htmlSC((string)$category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= get_errors('category_id') ?>
                            </label>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_posts_col_date')) ?></span>
                                <input class="form-control" type="text" name="published_at" value="<?= htmlSC($publishedAtValue) ?>" data-post-datepicker>
                            </label>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_posts_col_priority')) ?></span>
                                <input class="form-control" type="number" name="priority" value="<?= (int)($formData['priority'] ?? ($entity['priority'] ?? 0)) ?>" min="0" step="1">
                            </label>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_post_excerpt')) ?></span>
                                <textarea class="form-control" name="excerpt" rows="4"><?= htmlSC((string)($formData['excerpt'] ?? ($entity['excerpt'] ?? ''))) ?></textarea>
                            </label>
                        <?php else: ?>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_page_field_menu_title')) ?></span>
                                <input class="form-control" type="text" name="menu_title" value="<?= htmlSC((string)($formData['menu_title'] ?? ($entity['menu_title'] ?? ''))) ?>">
                            </label>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_page_field_menu_order')) ?></span>
                                <input class="form-control" type="number" name="menu_order" value="<?= (int)($formData['menu_order'] ?? ($entity['menu_order'] ?? 0)) ?>" min="0">
                            </label>
                            <?php
                            $showInHeader = (int)($formData['show_in_header'] ?? ($entity['show_in_header'] ?? 0));
                            $showInFooter = (int)($formData['show_in_footer'] ?? ($entity['show_in_footer'] ?? 0));
                            $menuVisibility = (string)($formData['menu_visibility'] ?? match (true) {
                                $showInHeader === 1 && $showInFooter === 1 => 'both',
                                $showInHeader === 1 => 'header',
                                $showInFooter === 1 => 'footer',
                                default => 'none',
                            });
                            ?>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_page_field_menu_visibility')) ?></span>
                                <select class="form-select" name="menu_visibility">
                                    <option value="none" <?= $menuVisibility === 'none' ? 'selected' : '' ?>><?= htmlSC(return_translation('admin_page_visibility_none')) ?></option>
                                    <option value="header" <?= $menuVisibility === 'header' ? 'selected' : '' ?>><?= htmlSC(return_translation('admin_page_visibility_header')) ?></option>
                                    <option value="footer" <?= $menuVisibility === 'footer' ? 'selected' : '' ?>><?= htmlSC(return_translation('admin_page_visibility_footer')) ?></option>
                                    <option value="both" <?= $menuVisibility === 'both' ? 'selected' : '' ?>><?= htmlSC(return_translation('admin_page_visibility_both')) ?></option>
                                </select>
                            </label>
                            <label class="fb-editor-check">
                                <input type="checkbox" name="show_in_legal_information" value="1" <?= (int)($formData['show_in_legal_information'] ?? ($entity['show_in_legal_information'] ?? 0)) === 1 ? 'checked' : '' ?>>
                                <span><?= htmlSC(return_translation('admin_page_show_in_legal_information')) ?></span>
                            </label>
                        <?php endif; ?>
                    </section>

                    <?php if (!$isPageEditor): ?>
                        <section>
                            <h2><?= htmlSC(return_translation('admin_post_image')) ?></h2>
                            <div class="fb-editor-cover" data-editor-cover>
                                <img src="<?= $currentImage !== '' ? htmlSC(get_image($currentImage)) : '' ?>" alt="" data-editor-cover-preview <?= $currentImage === '' ? 'hidden' : '' ?>>
                                <div data-editor-cover-empty <?= $currentImage !== '' ? 'hidden' : '' ?>><i class="ci-image"></i></div>
                                <input type="hidden" id="post_image" name="image" value="<?= htmlSC($currentImage) ?>" data-editor-cover-value>
                                <input class="form-control" type="url" id="post_image_url" name="image_url" value="<?= htmlSC($currentImageUrl) ?>" placeholder="https://…" data-editor-cover-url>
                                <input class="form-control" type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-editor-cover-file>
                                <button type="button" class="btn btn-outline-secondary w-100" data-file-manager-open data-file-manager-input="post_image" data-file-manager-dir="posts" data-file-manager-url="<?= htmlSC(base_href('/admin/files')) ?>">
                                    <i class="ci-folder me-2"></i><?= htmlSC(return_translation('admin_nav_files')) ?>
                                </button>
                            </div>
                            <label class="fb-editor-check">
                                <input type="checkbox" name="hide_placeholder_image" value="1" <?= (int)($formData['hide_placeholder_image'] ?? ($entity['hide_placeholder_image'] ?? 0)) === 1 ? 'checked' : '' ?>>
                                <span><?= htmlSC(return_translation('admin_post_hide_placeholder_image')) ?></span>
                            </label>
                            <label class="fb-editor-check">
                                <input type="checkbox" name="show_on_home" value="1" <?= (int)($formData['show_on_home'] ?? ($entity['show_on_home'] ?? 0)) === 1 ? 'checked' : '' ?>>
                                <span><?= htmlSC(return_translation('admin_post_show_on_home')) ?></span>
                            </label>
                            <?php do_action('admin_post_document_settings', $entity, $formData); ?>
                        </section>
                    <?php endif; ?>

                    <section>
                        <h2>SEO</h2>
                        <label class="fb-editor-field">
                            <span><?= htmlSC(return_translation('admin_seo_title')) ?></span>
                            <input class="form-control" type="text" name="<?= $isPageEditor ? 'meta_title' : 'seo_title' ?>" value="<?= htmlSC((string)($formData[$isPageEditor ? 'meta_title' : 'seo_title'] ?? ($entity[$isPageEditor ? 'meta_title' : 'seo_title'] ?? ''))) ?>">
                        </label>
                        <label class="fb-editor-field">
                            <span><?= htmlSC(return_translation('admin_seo_description')) ?></span>
                            <textarea class="form-control" name="<?= $isPageEditor ? 'meta_description' : 'seo_description' ?>" rows="4"><?= htmlSC((string)($formData[$isPageEditor ? 'meta_description' : 'seo_description'] ?? ($entity[$isPageEditor ? 'meta_description' : 'seo_description'] ?? ''))) ?></textarea>
                        </label>
                        <?php if (!$isPageEditor): ?>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_seo_keywords')) ?></span>
                                <textarea class="form-control" name="seo_keywords" rows="3"><?= htmlSC((string)($formData['seo_keywords'] ?? ($entity['seo_keywords'] ?? ''))) ?></textarea>
                            </label>
                            <label class="fb-editor-field">
                                <span><?= htmlSC(return_translation('admin_seo_image')) ?></span>
                                <input class="form-control" type="text" id="post_seo_image" name="seo_image" value="<?= htmlSC((string)($formData['seo_image'] ?? ($entity['seo_image'] ?? ''))) ?>">
                            </label>
                        <?php endif; ?>
                    </section>
                </div>
            </aside>
        </div>

        <footer class="fb-editor-workspace__statusbar">
            <span data-editor-status-blocks>0 <?= htmlSC($translateOrFallback('editor_block_count', 'блоков')) ?></span>
            <span data-editor-status-words>0 <?= htmlSC($translateOrFallback('editor_word_count', 'слов')) ?></span>
            <span data-editor-status-characters>0 <?= htmlSC($translateOrFallback('editor_character_count', 'символов')) ?></span>
            <span data-editor-status-reading>0 <?= htmlSC($translateOrFallback('editor_reading_minutes', 'мин. чтения')) ?></span>
            <span data-editor-status-current>—</span>
            <span class="ms-auto" data-editor-status-saved-at><?= htmlSC($translateOrFallback('editor_last_saved', 'Последнее сохранение')) ?>: —</span>
        </footer>
    </form>
</div>
