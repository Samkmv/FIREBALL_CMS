<?php
$formAction = $is_edit
    ? base_href('/admin/posts/edit/' . (int)$post['id'])
    : base_href('/admin/posts/create');
$formData = session()->get('form_data') ?: [];
$currentImage = $formData['image'] ?? ($post['image'] ?? '');
$currentImageUrl = $formData['image_url'] ?? $currentImage;
$selectedFileField = trim((string)request()->get('fireball_file_field', ''));
$selectedFileValue = trim((string)request()->get('fireball_file_value', ''));
if ($selectedFileField === 'post_image' && $selectedFileValue !== '') {
    $currentImage = $selectedFileValue;
    $currentImageUrl = $selectedFileValue;
}
$hidePlaceholderImage = array_key_exists('hide_placeholder_image', $formData)
    ? (int)$formData['hide_placeholder_image']
    : (int)($post['hide_placeholder_image'] ?? 0);
$showOnHome = array_key_exists('show_on_home', $formData)
    ? (int)$formData['show_on_home']
    : (int)($post['show_on_home'] ?? 0);
$priorityValue = array_key_exists('priority', $formData)
    ? (int)$formData['priority']
    : (int)($post['priority'] ?? 0);
$publishedAtSource = old('published_at') ?: ($post['published_at'] ?? '');
$publishedAtValue = $publishedAtSource !== '' && strtotime($publishedAtSource)
    ? date('Y-m-d H:i:S', strtotime($publishedAtSource))
    : date('Y-m-d H:i:S');
$excerptValue = array_key_exists('excerpt', $formData)
    ? (string)$formData['excerpt']
    : (string)($post['excerpt'] ?? '');
$contentValue = array_key_exists('content', $formData)
    ? (string)$formData['content']
    : (string)($post['content'] ?? '');
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
$editorStyleLabel = $translateOrFallback('admin_post_builder_style', 'Style');
$editorMoreStylesLabel = $translateOrFallback('admin_post_builder_more_styles', 'Font, size and colors');
$editorInlineSettingsHint = $translateOrFallback('admin_post_builder_inline_settings_hint', 'This block is edited directly in the content area.');
$editorHtmlHint = $translateOrFallback('admin_post_builder_html_hint', 'Unsafe tags and inline handlers will be removed on save.');
$editorDeleteModalTitle = $translateOrFallback('admin_post_builder_delete_confirm_title', 'Remove block?');
$editorDeleteModalText = $translateOrFallback('admin_post_builder_delete_confirm_text', 'This action cannot be undone. The block will be removed from the post content.');
$closeLabel = $translateOrFallback('admin_btn_close', 'Close');
$formErrors = session()->get('form_errors') ?: [];
$requiredSummaryLabel = $translateOrFallback('admin_form_required_summary', 'Заполните обязательные поля:');
$editorConfig = [
    'fileManagerUrl' => base_href('/admin/files'),
    'defaultDirectory' => 'posts',
    'fonts' => [
        ['value' => '"Helvetica Neue", Arial, sans-serif', 'label' => 'Helvetica'],
        ['value' => 'Georgia, serif', 'label' => 'Georgia'],
        ['value' => '"Times New Roman", serif', 'label' => 'Times'],
        ['value' => '"Trebuchet MS", sans-serif', 'label' => 'Trebuchet'],
        ['value' => 'Verdana, sans-serif', 'label' => 'Verdana'],
        ['value' => '"Courier New", monospace', 'label' => 'Courier'],
    ],
    'sizes' => [
        ['value' => '14px', 'label' => '14'],
        ['value' => '16px', 'label' => '16'],
        ['value' => '18px', 'label' => '18'],
        ['value' => '20px', 'label' => '20'],
        ['value' => '24px', 'label' => '24'],
        ['value' => '32px', 'label' => '32'],
    ],
    'labels' => [
        'builderHint' => return_translation('admin_post_builder_hint'),
        'addText' => return_translation('admin_post_builder_add_text'),
        'addHeading' => return_translation('admin_post_builder_add_heading'),
        'addImage' => return_translation('admin_post_builder_add_image'),
        'addVideo' => return_translation('admin_post_builder_add_video'),
        'addHtml' => return_translation('admin_post_builder_add_html'),
        'addSocial' => return_translation('admin_post_builder_add_social'),
        'addSlider' => return_translation('admin_post_builder_add_slider'),
        'addNewsletter' => return_translation('admin_post_builder_add_newsletter'),
        'addCode' => return_translation('admin_post_builder_add_code'),
        'textBlock' => return_translation('admin_post_builder_block_text'),
        'headingBlock' => return_translation('admin_post_builder_block_heading'),
        'imageBlock' => return_translation('admin_post_builder_block_image'),
        'videoBlock' => return_translation('admin_post_builder_block_video'),
        'htmlBlock' => return_translation('admin_post_builder_block_html'),
        'socialBlock' => return_translation('admin_post_builder_block_social'),
        'sliderBlock' => return_translation('admin_post_builder_block_slider'),
        'newsletterBlock' => return_translation('admin_post_builder_block_newsletter'),
        'codeBlock' => return_translation('admin_post_builder_block_code'),
        'moveUp' => return_translation('admin_post_builder_move_up'),
        'moveDown' => return_translation('admin_post_builder_move_down'),
        'remove' => return_translation('admin_post_builder_remove'),
        'hide' => return_translation('admin_post_builder_hide'),
        'show' => return_translation('admin_post_builder_show'),
        'hidden' => return_translation('admin_post_builder_hidden'),
        'duplicate' => return_translation('admin_post_builder_duplicate'),
        'drag' => return_translation('admin_post_builder_drag'),
        'chooseFile' => return_translation('admin_post_builder_choose_file'),
        'sourceLink' => return_translation('admin_post_builder_source_link'),
        'imageAlt' => return_translation('admin_post_builder_image_alt'),
        'imageCaption' => return_translation('admin_post_builder_image_caption'),
        'imageLink' => return_translation('admin_post_builder_image_link'),
        'videoPoster' => return_translation('admin_post_builder_video_poster'),
        'videoCaption' => return_translation('admin_post_builder_video_caption'),
        'headingLevel' => return_translation('admin_post_builder_heading_level'),
        'codeLanguage' => return_translation('admin_post_builder_code_language'),
        'htmlPlaceholder' => return_translation('admin_post_builder_html_placeholder'),
        'htmlPreview' => return_translation('admin_post_builder_html_preview'),
        'socialNetwork' => return_translation('admin_post_builder_social_network'),
        'socialIcon' => return_translation('admin_post_builder_social_icon'),
        'socialCustom' => return_translation('admin_post_builder_social_custom'),
        'socialGlobe' => return_translation('admin_post_builder_social_globe'),
        'socialShare' => return_translation('admin_post_builder_social_share'),
        'socialExternalLink' => return_translation('admin_post_builder_social_external_link'),
        'socialPhone' => return_translation('admin_post_builder_social_phone'),
        'socialMessage' => return_translation('admin_post_builder_social_message'),
        'socialLabel' => return_translation('admin_post_builder_social_label'),
        'socialUrl' => return_translation('admin_post_builder_social_url'),
        'socialAddItem' => return_translation('admin_post_builder_social_add_item'),
        'socialRemoveItem' => return_translation('admin_post_builder_social_remove_item'),
        'socialItemsHint' => return_translation('admin_post_builder_social_items_hint'),
        'codePlaceholder' => return_translation('admin_post_builder_code_placeholder'),
        'textPlaceholder' => return_translation('admin_post_builder_text_placeholder'),
        'headingPlaceholder' => return_translation('admin_post_builder_heading_placeholder'),
        'font' => return_translation('admin_post_builder_font'),
        'size' => return_translation('admin_post_builder_size'),
        'textColor' => return_translation('admin_post_builder_text_color'),
        'background' => return_translation('admin_post_builder_background'),
        'bold' => return_translation('admin_post_builder_bold'),
        'italic' => return_translation('admin_post_builder_italic'),
        'underline' => return_translation('admin_post_builder_underline'),
        'alignLeft' => return_translation('admin_post_builder_align_left'),
        'alignCenter' => return_translation('admin_post_builder_align_center'),
        'alignRight' => return_translation('admin_post_builder_align_right'),
        'link' => return_translation('admin_post_builder_link'),
        'unlink' => return_translation('admin_post_builder_unlink'),
        'linkPrompt' => return_translation('admin_post_builder_link_prompt'),
        'empty' => return_translation('admin_post_builder_empty'),
        'bulletList' => return_translation('admin_post_builder_bullet_list'),
        'quote' => return_translation('admin_post_builder_quote'),
        'inserter' => return_translation('admin_post_builder_inserter'),
        'inspector' => return_translation('admin_post_builder_inspector'),
        'canvasTitle' => return_translation('admin_post_builder_canvas_title'),
        'addBlock' => return_translation('admin_post_builder_add_block'),
        'outline' => return_translation('admin_post_builder_outline'),
        'selectBlock' => return_translation('admin_post_builder_select_block'),
        'blockSettings' => return_translation('admin_post_builder_block_settings'),
        'contentSettings' => return_translation('admin_post_builder_content_settings'),
        'mediaSettings' => return_translation('admin_post_builder_media_settings'),
        'sliderItems' => return_translation('admin_post_builder_slider_items'),
        'sliderAddItem' => return_translation('admin_post_builder_slider_add_item'),
        'sliderRemoveItem' => return_translation('admin_post_builder_slider_remove_item'),
        'sliderImage' => return_translation('admin_post_builder_slider_image'),
        'sliderAspectRatio' => return_translation('admin_post_builder_slider_aspect_ratio'),
        'sliderShowBullets' => return_translation('admin_post_builder_slider_show_bullets'),
        'sliderShowArrows' => return_translation('admin_post_builder_slider_show_arrows'),
        'sliderTitle' => return_translation('admin_post_builder_slider_title'),
        'sliderText' => return_translation('admin_post_builder_slider_text'),
        'sliderAlt' => return_translation('admin_post_builder_slider_alt'),
        'sliderSlide' => return_translation('admin_post_builder_slider_slide'),
        'sliderPrev' => return_translation('admin_post_builder_slider_prev'),
        'sliderNext' => return_translation('admin_post_builder_slider_next'),
        'newsletterTitle' => return_translation('admin_post_builder_newsletter_title'),
        'newsletterText' => return_translation('admin_post_builder_newsletter_text'),
        'newsletterButton' => return_translation('admin_post_builder_newsletter_button'),
        'newsletterUrl' => return_translation('admin_post_builder_newsletter_url'),
        'newsletterIcon' => return_translation('admin_post_builder_newsletter_icon'),
        'blockCount' => return_translation('admin_post_builder_block_count'),
        'style' => $editorStyleLabel,
        'moreStyles' => $editorMoreStylesLabel,
        'settings' => return_translation('admin_post_builder_block_settings'),
        'inlineSettingsHint' => $editorInlineSettingsHint,
        'htmlHint' => $editorHtmlHint,
        'deleteModalTitle' => $editorDeleteModalTitle,
        'deleteModalText' => $editorDeleteModalText,
    ],
];
?>

<style>
    .fb-post-editor {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1.25rem;
        background: linear-gradient(180deg, #f8f9fb 0%, #ffffff 100%);
        box-shadow: 0 24px 60px rgba(24, 33, 37, .08);
        overflow: hidden;
        container-type: inline-size;
    }

    .fb-post-editor__topbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        background: rgba(255, 255, 255, .86);
        backdrop-filter: blur(16px);
        border-bottom: 1px solid rgba(26, 33, 36, .08);
    }

    .fb-post-editor__hint {
        color: #5d6668;
        font-size: .92rem;
        line-height: 1.55;
        margin: 0;
    }

    .fb-post-editor__topbar-meta {
        display: flex;
        align-items: center;
        gap: .75rem;
        color: #212529;
        font-size: .88rem;
        font-weight: 700;
    }

    .fb-post-editor__workspace {
        display: grid;
        grid-template-columns: 240px minmax(0, 1fr) 280px;
        min-height: 720px;
    }

    .fb-post-editor__sidebar,
    .fb-post-editor__inspector,
    .fb-post-editor__canvas {
        background: rgba(248, 249, 251, .94);
        min-width: 0;
    }

    .fb-post-editor__sidebar {
        border-right: 1px solid rgba(26, 33, 36, .08);
        padding: 1rem;
    }

    .fb-post-editor__inspector {
        border-left: 1px solid rgba(26, 33, 36, .08);
        padding: 1rem;
    }

    .fb-post-editor__panel-title {
        margin: 0 0 .85rem;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #5b6769;
    }

    .fb-post-editor__inserter-buttons {
        display: grid;
        gap: .55rem;
        margin-bottom: 1.25rem;
    }

    .fb-post-editor__inserter-btn {
        display: flex;
        align-items: center;
        gap: .7rem;
        width: 100%;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: .85rem;
        background: rgba(255, 255, 255, .9);
        padding: .8rem .9rem;
        text-align: left;
        box-shadow: 0 10px 26px rgba(28, 37, 38, .04);
        transition: border-color .15s ease, background-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }

    .fb-post-editor__inserter-btn:hover {
        border-color: rgba(31, 92, 79, .28);
        background: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(28, 37, 38, .08);
    }

    .fb-post-editor__inserter-icon {
        width: 2.2rem;
        height: 2.2rem;
        border-radius: .65rem;
        background: rgba(33, 37, 41, .08);
        color: #212529;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
        font-weight: 700;
        flex: 0 0 auto;
    }

    .fb-post-editor__outline {
        display: grid;
        gap: .4rem;
    }

    .fb-post-editor__outline-item {
        width: 100%;
        border: 1px solid rgba(26, 33, 36, .06);
        border-radius: .75rem;
        background: rgba(255, 255, 255, .78);
        padding: .65rem .8rem;
        text-align: left;
        font-size: .92rem;
        color: #1e1e1e;
        transition: border-color .15s ease, background-color .15s ease, transform .15s ease;
    }

    .fb-post-editor__outline-item:hover {
        border-color: rgba(33, 37, 41, .14);
        background: #fff;
    }

    .fb-post-editor__outline-item.is-active {
        border-color: rgba(33, 37, 41, .16);
        background: rgba(33, 37, 41, .06);
        color: #212529;
        transform: translateY(-1px);
    }

    .fb-post-editor__canvas {
        background: rgba(255, 255, 255, .72);
        padding: 1.35rem;
    }

    .fb-post-editor__canvas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding-bottom: .9rem;
        border-bottom: 1px solid rgba(26, 33, 36, .06);
    }

    .fb-post-editor__canvas-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #182225;
    }

    .fb-post-editor__list {
        display: grid;
        gap: 1rem;
    }

    .fb-post-editor__empty {
        border: 1px dashed rgba(33, 37, 41, .14);
        border-radius: 1.1rem;
        padding: 2.4rem 1.4rem;
        text-align: center;
        color: #5d6668;
        background: rgba(255, 255, 255, .78);
    }

    .fb-post-editor__block {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 16px 36px rgba(28, 37, 38, .06);
        overflow: hidden;
        transition: box-shadow .15s ease, border-color .15s ease, transform .15s ease;
    }

    .fb-post-editor__block.is-selected {
        border-color: rgba(33, 37, 41, .18);
        box-shadow: 0 0 0 1px rgba(33, 37, 41, .12), 0 18px 40px rgba(17, 24, 39, .08);
        transform: translateY(-1px);
    }

    .fb-post-editor__block.is-drop-target {
        outline: 2px solid rgba(33, 37, 41, .14);
    }

    .fb-post-editor__block-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .8rem .95rem;
        background: rgba(248, 249, 251, .96);
        border-bottom: 1px solid rgba(26, 33, 36, .06);
    }

    .fb-post-editor__block-title {
        display: flex;
        align-items: center;
        gap: .6rem;
        font-weight: 600;
        color: #1e1e1e;
        min-width: 0;
    }

    .fb-post-editor__block-title span:last-child {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .fb-post-editor__drag {
        cursor: grab;
        border: 0;
        background: transparent;
        color: #687476;
        padding: 0;
        font-size: 1rem;
    }

    .fb-post-editor__block-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }

    .fb-post-editor__block-actions .btn,
    .fb-post-editor__formatbar .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .8rem;
    }

    .fb-post-editor__block-actions .btn {
        width: 2.25rem;
        height: 2.25rem;
        padding: 0;
    }

    .fb-post-editor__body {
        padding: 1.05rem;
    }

    .fb-post-editor__body .form-label {
        font-size: .84rem;
        color: #5d6668;
    }

    .fb-post-editor__field-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }

    .fb-post-editor__grid {
        display: grid;
        gap: .85rem;
    }

    .fb-post-editor__grid--media {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .fb-post-editor__formatbar {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        align-items: center;
        margin-bottom: .95rem;
        padding: .85rem;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: rgba(248, 249, 251, .96);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .65);
    }

    .fb-post-editor__formatbar-group {
        display: inline-flex;
        flex-wrap: wrap;
        gap: .45rem;
        padding-right: .1rem;
        margin-right: .1rem;
        border-right: 1px solid rgba(26, 33, 36, .08);
    }

    .fb-post-editor__formatbar-group:last-of-type {
        border-right: 0;
        margin-right: 0;
        padding-right: 0;
    }

    .fb-post-editor__formatbar .btn,
    .fb-post-editor__formatbar .form-select,
    .fb-post-editor__formatbar .form-control-color {
        min-height: 2.35rem;
        border-radius: .85rem;
    }

    .fb-post-editor__formatbar .btn {
        min-width: 2.35rem;
        padding-inline: .7rem;
    }

    .fb-post-editor__color-control {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        min-height: 2.75rem;
        min-width: 10.75rem;
        padding: .45rem .55rem .45rem .8rem;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: .95rem;
        background: rgba(255, 255, 255, .9);
        color: #4f5b5d;
        box-shadow: 0 8px 22px rgba(28, 37, 38, .04);
    }

    .fb-post-editor__color-label {
        font-size: .8rem;
        font-weight: 600;
        line-height: 1.2;
        color: #4f5b5d;
    }

    .fb-post-editor__color-control .form-control-color {
        width: 3rem;
        min-width: 3rem;
        height: 2.2rem;
        padding: .18rem;
        border: 1px solid rgba(26, 33, 36, .12);
        background: #fff;
        cursor: pointer;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .6);
    }

    .fb-post-editor__color-control .form-control-color::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    .fb-post-editor__color-control .form-control-color::-webkit-color-swatch {
        border: 0;
        border-radius: .7rem;
    }

    .fb-post-editor__color-control .form-control-color::-moz-color-swatch {
        border: 0;
        border-radius: .7rem;
    }

    .fb-post-editor__rich {
        min-height: 280px;
        padding: 1rem;
        border: 1px solid rgba(26, 33, 36, .1);
        border-radius: 1rem;
        background: #fff;
        outline: none;
        line-height: 1.7;
        box-shadow: inset 0 1px 2px rgba(17, 24, 39, .04);
    }

    .fb-post-editor__rich:empty::before,
    .fb-post-editor__heading-input:empty::before {
        content: attr(data-placeholder);
        color: rgba(108, 117, 125, .82);
    }

    .fb-post-editor__rich:focus,
    .fb-post-editor__heading-input:focus,
    .fb-post-editor__code:focus {
        border-color: rgba(31, 92, 79, .32);
        box-shadow: 0 0 0 4px rgba(31, 92, 79, .08);
    }

    .fb-post-editor__heading-input {
        padding: .9rem 1rem;
        border: 1px solid rgba(26, 33, 36, .1);
        border-radius: 1rem;
        min-height: 3.4rem;
        outline: none;
        font-weight: 700;
        background: #fff;
    }

    .fb-post-editor__preview {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 170px;
        border: 1px dashed rgba(33, 37, 41, .12);
        border-radius: 1rem;
        background: linear-gradient(180deg, #ffffff 0%, #f5f6f8 100%);
        overflow: hidden;
    }

    .fb-post-editor__preview img,
    .fb-post-editor__preview iframe,
    .fb-post-editor__preview video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border: 0;
    }

    .fb-post-editor__preview-text {
        padding: 1rem;
        text-align: center;
        color: #5d6668;
        font-size: .92rem;
    }

    .fb-post-editor__code {
        min-height: 420px;
        resize: vertical;
        font: 400 .95rem/1.65 "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        border-radius: 1rem;
        background: #111827;
        color: #f3f4f6;
        border-color: #111827;
    }

    .fb-post-editor__code--html {
        height: 300px !important;
        min-height: 300px;
    }

    .fb-post-editor__html-preview {
        overflow-x: auto;
    }

    .fb-post-editor__html-preview * {
        box-sizing: border-box;
        max-width: 100%;
    }

    .fb-post-editor__html-preview img,
    .fb-post-editor__html-preview video,
    .fb-post-editor__html-preview canvas,
    .fb-post-editor__html-preview svg {
        display: block;
        max-width: 100% !important;
        height: auto !important;
    }

    .fb-post-editor__html-preview iframe,
    .fb-post-editor__html-preview embed,
    .fb-post-editor__html-preview object {
        display: block;
        width: 100% !important;
        max-width: 100% !important;
        border: 0;
    }

    .fb-post-editor__html-preview table {
        display: block;
        width: 100% !important;
        overflow-x: auto;
    }

    .fb-social-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
    }

    .fb-social-buttons__item {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        min-width: 0;
        padding: .8rem 1rem;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 999px;
        background: rgba(255, 255, 255, .94);
        color: #212529;
        text-decoration: none;
        line-height: 1.2;
        box-shadow: 0 10px 24px rgba(28, 37, 38, .05);
    }

    .fb-social-buttons__item.is-disabled {
        opacity: .72;
    }

    .fb-social-buttons__icon {
        font-size: 1rem;
        flex: 0 0 auto;
    }

    .fb-social-buttons__label {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .fb-post-editor__social-item {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: rgba(248, 249, 251, .94);
        padding: .9rem;
    }

    .fb-post-editor__social-item-head {
        display: flex;
        align-items: stretch;
        justify-content: flex-start;
        flex-direction: column;
        gap: .75rem;
        margin-bottom: .85rem;
    }

    .fb-post-editor__social-item-preview {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        flex: 0 0 auto;
        min-width: 0;
        min-height: 3rem;
        padding: .65rem .85rem;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: .9rem;
        background: rgba(255, 255, 255, .92);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .68);
        color: #173a34;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .fb-post-editor__social-item-preview i {
        width: 2rem;
        height: 2rem;
        border-radius: .7rem;
        background: rgba(33, 37, 41, .08);
        color: #212529;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-size: .95rem;
    }

    .fb-post-editor__social-fields {
        display: grid;
        gap: .85rem;
        grid-template-columns: 1fr;
    }

    .fb-post-editor__social-meta {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        min-width: 0;
        padding: .32rem .55rem;
        border-radius: 999px;
        background: rgba(33, 37, 41, .08);
        color: #212529;
        font-size: .76rem;
        line-height: 1;
        white-space: nowrap;
    }

    .fb-post-editor__social-meta i,
    .fb-post-editor__inserter-icon i {
        font-size: .95rem;
        line-height: 1;
    }

    .fb-post-editor__slider-preview {
        overflow-x: auto;
        padding-bottom: .25rem;
    }

    .fb-post-editor__slider-track {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(220px, 240px);
        gap: .85rem;
    }

    .fb-post-editor__slider-card {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: rgba(248, 249, 251, .94);
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(28, 37, 38, .04);
    }

    .fb-post-editor__slider-figure {
        margin: 0;
        background: linear-gradient(180deg, #ffffff 0%, #f5f6f8 100%);
        overflow: hidden;
    }

    .fb-post-editor__slider-figure img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .fb-post-editor__slider-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #7a8487;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .fb-post-editor__slider-copy {
        display: grid;
        gap: .25rem;
        padding: .8rem .9rem .95rem;
    }

    .fb-post-editor__slider-copy strong,
    .fb-post-editor__slider-copy span {
        display: block;
        overflow-wrap: anywhere;
    }

    .fb-post-editor__slider-copy strong {
        color: #182225;
        font-size: .92rem;
        line-height: 1.35;
    }

    .fb-post-editor__slider-copy span {
        color: #5d6668;
        font-size: .82rem;
        line-height: 1.45;
    }

    .fb-post-editor__media-summary {
        display: grid;
        gap: .55rem;
    }

    .fb-post-editor__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-top: .75rem;
    }

    .fb-post-editor__chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: rgba(33, 37, 41, .08);
        color: #212529;
        padding: .3rem .6rem;
        font-size: .78rem;
        line-height: 1;
    }

    .fb-post-editor__inspector-card {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 12px 30px rgba(28, 37, 38, .04);
        padding: .9rem;
        margin-bottom: .85rem;
    }

    .fb-post-editor__inspector-card:last-child {
        margin-bottom: 0;
    }

    .fb-post-editor__inspector-note {
        color: #5d6668;
        font-size: .9rem;
        line-height: 1.55;
        margin: 0;
    }

    .fb-post-editor__block-label {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-size: .78rem;
        color: #5d6668;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .fb-post-media-picker {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1.5rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, .98) 0%, rgba(246, 247, 249, .96) 100%);
        box-shadow: 0 14px 30px rgba(28, 37, 38, .06);
        overflow: hidden;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease, background-color .15s ease;
    }

    .fb-post-media-picker:hover {
        border-color: rgba(26, 33, 36, .14);
        box-shadow: 0 18px 36px rgba(17, 24, 39, .08);
    }

    .fb-post-media-picker.is-invalid {
        border-color: rgba(220, 53, 69, .38);
        box-shadow: 0 0 0 1px rgba(220, 53, 69, .08), 0 16px 36px rgba(220, 53, 69, .06);
    }

    .fb-post-media-picker__dropzone {
        display: grid;
        grid-template-columns: 140px minmax(0, 1fr);
        align-items: start;
        gap: 1rem;
        padding: 1rem;
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .fb-post-media-picker.is-dragover .fb-post-media-picker__dropzone {
        background: rgba(33, 37, 41, .035);
        box-shadow: inset 0 0 0 1px rgba(33, 37, 41, .12);
    }

    .fb-post-media-picker.is-dragover {
        border-color: rgba(31, 92, 79, .18);
        box-shadow: 0 0 0 3px rgba(31, 92, 79, .08), 0 18px 40px rgba(17, 24, 39, .08);
        transform: translateY(-1px);
    }

    .fb-post-media-picker__figure {
        width: 140px;
        aspect-ratio: 1 / 1;
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid rgba(26, 33, 36, .08);
        background: linear-gradient(180deg, #f8f9fb 0%, #eef1f4 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
    }

    .fb-post-media-picker__figure img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .fb-post-media-picker__placeholder {
        width: 100%;
        height: 100%;
        display: grid;
        gap: .35rem;
        align-items: center;
        justify-content: center;
        color: #8a9597;
        background: linear-gradient(180deg, rgba(255, 255, 255, .5) 0%, rgba(242, 238, 231, .95) 100%);
        font-size: 1.5rem;
    }

    .fb-post-media-picker__body {
        min-width: 0;
        display: grid;
        gap: .8rem;
    }

    .fb-post-media-picker__head {
        display: grid;
        gap: .35rem;
    }

    .fb-post-media-picker__heading {
        color: #182225;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .fb-post-media-picker__meta {
        color: #182225;
        font-size: .92rem;
        font-weight: 600;
        line-height: 1.35;
        word-break: break-word;
    }

    .fb-post-media-picker__note {
        color: #5d6668;
        font-size: .84rem;
        line-height: 1.5;
    }

    .fb-post-media-picker__actions {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        align-items: center;
    }

    .fb-post-media-picker__actions .btn {
        min-height: 2.5rem;
        justify-content: center;
        white-space: nowrap;
        border-radius: 999px;
        padding-inline: .9rem;
    }

    .fb-post-media-picker__actions .btn-danger-subtle {
        color: #842029;
        background: rgba(220, 53, 69, .08);
        border-color: rgba(220, 53, 69, .12);
    }

    .fb-post-media-picker__actions .btn-danger-subtle:hover {
        background: rgba(220, 53, 69, .14);
        border-color: rgba(220, 53, 69, .2);
    }

    .fb-post-media-picker__source-inline {
        border-top: 1px solid rgba(26, 33, 36, .06);
        padding-top: .85rem;
        display: grid;
        gap: .65rem;
    }

    .fb-post-media-picker__source-inline[hidden] {
        display: none !important;
    }

    .fb-post-media-picker__source-head {
        display: flex;
        align-items: flex-start;
        gap: .7rem;
    }

    .fb-post-media-picker__source-icon {
        width: 2rem;
        height: 2rem;
        border-radius: .8rem;
        background: rgba(33, 37, 41, .08);
        color: #212529;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-size: 1rem;
    }

    .fb-post-media-picker__source-title {
        color: #182225;
        font-size: .9rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .fb-post-media-picker__source-note {
        margin-top: .2rem;
        color: #5d6668;
        font-size: .8rem;
        line-height: 1.5;
    }

    .fb-post-media-picker__file-input-wrap {
        padding: 0;
    }

    .fb-post-media-picker__file-input,
    .fb-post-media-picker__source-inline .form-control {
        width: 100%;
        min-height: 2.75rem;
        border-radius: .9rem;
    }

    .fb-post-media-picker__source-inline .form-text {
        margin-top: .55rem;
    }

    .fb-post-media-picker__source-inline .invalid-feedback,
    .fb-post-media-picker__source-inline .is-invalid ~ .invalid-feedback {
        display: block;
    }

    [data-bs-theme=dark] .fb-post-editor {
        border-color: rgba(255, 255, 255, .08);
        background: linear-gradient(180deg, #1e2530 0%, #181d25 100%);
        box-shadow: 0 24px 60px rgba(6, 10, 16, .34);
    }

    [data-bs-theme=dark] .fb-post-editor__topbar {
        background: rgba(29, 36, 46, .9);
        border-bottom-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-editor__hint,
    [data-bs-theme=dark] .fb-post-editor__inspector-note,
    [data-bs-theme=dark] .fb-post-editor__note,
    [data-bs-theme=dark] .fb-post-editor__preview-text,
    [data-bs-theme=dark] .fb-post-editor__body .form-label,
    [data-bs-theme=dark] .fb-post-editor__panel-title,
    [data-bs-theme=dark] .fb-post-editor__block-label {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-editor__topbar-meta,
    [data-bs-theme=dark] .fb-post-editor__canvas-title,
    [data-bs-theme=dark] .fb-post-editor__block-title {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-editor__sidebar,
    [data-bs-theme=dark] .fb-post-editor__inspector {
        background: rgba(24, 29, 37, .92);
    }

    [data-bs-theme=dark] .fb-post-editor__sidebar {
        border-right-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-editor__inspector {
        border-left-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-editor__inserter-btn,
    [data-bs-theme=dark] .fb-post-editor__outline-item,
    [data-bs-theme=dark] .fb-post-editor__inspector-card,
    [data-bs-theme=dark] .fb-post-editor__color-control {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(39, 46, 58, .9);
        box-shadow: 0 12px 28px rgba(8, 11, 18, .22);
        color: #cad0d9;
    }

    [data-bs-theme=dark] .fb-post-editor__inserter-btn:hover,
    [data-bs-theme=dark] .fb-post-editor__outline-item:hover {
        border-color: rgba(124, 197, 175, .28);
        background: rgba(46, 57, 70, .96);
    }

    [data-bs-theme=dark] .fb-post-editor__outline-item.is-active {
        border-color: rgba(124, 197, 175, .32);
        background: rgba(31, 92, 79, .22);
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-editor__inserter-icon,
    [data-bs-theme=dark] .fb-post-editor__source-icon,
    [data-bs-theme=dark] .fb-post-editor__chip,
    [data-bs-theme=dark] .fb-post-media-picker__source-icon {
        background: rgba(124, 197, 175, .14);
        color: #9ae6d2;
    }

    [data-bs-theme=dark] .fb-post-editor__canvas {
        background: rgba(24, 29, 37, .78);
    }

    [data-bs-theme=dark] .fb-post-editor__canvas-header {
        border-bottom-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-editor__empty,
    [data-bs-theme=dark] .fb-post-editor__preview {
        border-color: rgba(124, 197, 175, .2);
        background: linear-gradient(180deg, #222934 0%, #1b222d 100%);
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-social-buttons__item,
    [data-bs-theme=dark] .fb-post-editor__social-item {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(39, 46, 58, .92);
        color: #eef1f6;
        box-shadow: 0 12px 26px rgba(8, 11, 18, .18);
    }

    [data-bs-theme=dark] .fb-post-editor__social-item-preview {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(27, 34, 45, .94);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .03);
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-editor__social-item-preview i {
        background: rgba(124, 197, 175, .14);
        color: #9ae6d2;
    }

    [data-bs-theme=dark] .fb-post-editor__social-meta {
        background: rgba(124, 197, 175, .14);
        color: #9ae6d2;
    }

    [data-bs-theme=dark] .fb-post-editor__slider-card {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(39, 46, 58, .92);
        box-shadow: 0 12px 26px rgba(8, 11, 18, .18);
    }

    [data-bs-theme=dark] .fb-post-editor__slider-figure {
        background: linear-gradient(180deg, #2a3340 0%, #222934 100%);
    }

    [data-bs-theme=dark] .fb-post-editor__slider-placeholder {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-editor__slider-copy strong {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-editor__slider-copy span {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-editor__block {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(34, 41, 52, .96);
        box-shadow: 0 18px 36px rgba(8, 11, 18, .2);
    }

    [data-bs-theme=dark] .fb-post-editor__block.is-selected {
        border-color: rgba(124, 197, 175, .34);
        box-shadow: 0 0 0 1px rgba(124, 197, 175, .18), 0 18px 40px rgba(8, 11, 18, .28);
    }

    [data-bs-theme=dark] .fb-post-editor__block-header {
        background: rgba(27, 34, 45, .96);
        border-bottom-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-editor__drag {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-editor__formatbar {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(27, 34, 45, .94);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .03);
    }

    [data-bs-theme=dark] .fb-post-editor__formatbar-group {
        border-right-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-editor__color-label {
        color: #cad0d9;
    }

    [data-bs-theme=dark] .fb-post-editor__color-control .form-control-color {
        border-color: rgba(255, 255, 255, .12);
        background: #222934;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .04);
    }

    [data-bs-theme=dark] .fb-post-editor__rich,
    [data-bs-theme=dark] .fb-post-editor__heading-input {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(24, 29, 37, .92);
        color: #eef1f6;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, .2);
    }

    [data-bs-theme=dark] .fb-post-editor__rich:empty::before,
    [data-bs-theme=dark] .fb-post-editor__heading-input:empty::before {
        color: rgba(156, 163, 175, .78);
    }

    [data-bs-theme=dark] .fb-post-editor__rich:focus,
    [data-bs-theme=dark] .fb-post-editor__heading-input:focus,
    [data-bs-theme=dark] .fb-post-editor__code:focus {
        border-color: rgba(124, 197, 175, .34);
        box-shadow: 0 0 0 4px rgba(124, 197, 175, .12);
    }

    [data-bs-theme=dark] .fb-post-editor__code {
        background: #111827;
        color: #f3f4f6;
        border-color: #111827;
    }

    [data-bs-theme=dark] .fb-post-media-picker {
        border-color: rgba(255, 255, 255, .08);
        background: linear-gradient(180deg, rgba(34, 41, 52, .98) 0%, rgba(24, 29, 37, .96) 100%);
        box-shadow: 0 20px 42px rgba(8, 11, 18, .24);
    }

    [data-bs-theme=dark] .fb-post-media-picker.is-dragover .fb-post-media-picker__dropzone {
        background: rgba(124, 197, 175, .08);
        box-shadow: inset 0 0 0 1px rgba(124, 197, 175, .18);
    }

    [data-bs-theme=dark] .fb-post-media-picker.is-dragover {
        border-color: rgba(124, 197, 175, .2);
        box-shadow: 0 0 0 3px rgba(124, 197, 175, .08), 0 18px 42px rgba(8, 11, 18, .24);
    }

    [data-bs-theme=dark] .fb-post-media-picker__figure {
        border-color: rgba(255, 255, 255, .08);
        background: linear-gradient(180deg, #2a3340 0%, #222934 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
    }

    [data-bs-theme=dark] .fb-post-media-picker__placeholder {
        color: #9ca3af;
        background: linear-gradient(180deg, rgba(43, 52, 65, .78) 0%, rgba(34, 41, 52, .96) 100%);
    }

    [data-bs-theme=dark] .fb-post-media-picker__note,
    [data-bs-theme=dark] .fb-post-media-picker__source-note {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-media-picker__heading,
    [data-bs-theme=dark] .fb-post-media-picker__meta,
    [data-bs-theme=dark] .fb-post-media-picker__source-title {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-media-picker__source-inline {
        border-top-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-media-picker__source-icon {
        background: rgba(255, 255, 255, .08);
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-media-picker__actions .btn-danger-subtle {
        color: #f8c8ce;
        background: rgba(255, 107, 130, .12);
        border-color: rgba(255, 107, 130, .18);
    }

    [data-bs-theme=dark] .fb-post-media-picker__actions .btn-danger-subtle:hover {
        background: rgba(255, 107, 130, .18);
        border-color: rgba(255, 107, 130, .24);
    }

    @container (max-width: 1240px) {
        .fb-post-editor__workspace {
            grid-template-columns: 220px minmax(0, 1fr);
            min-height: auto;
        }

        .fb-post-editor__inspector {
            grid-column: 1 / -1;
            border-left: 0;
            border-top: 1px solid rgba(26, 33, 36, .08);
        }

        [data-bs-theme=dark] .fb-post-editor__inspector {
            border-top-color: rgba(255, 255, 255, .08);
        }
    }

    @container (max-width: 960px) {
        .fb-post-editor__workspace {
            grid-template-columns: 1fr;
        }

        .fb-post-editor__sidebar,
        .fb-post-editor__canvas,
        .fb-post-editor__inspector {
            border: 0;
            border-bottom: 1px solid rgba(26, 33, 36, .08);
        }

        .fb-post-editor__canvas {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        [data-bs-theme=dark] .fb-post-editor__sidebar,
        [data-bs-theme=dark] .fb-post-editor__canvas,
        [data-bs-theme=dark] .fb-post-editor__inspector {
            border-bottom-color: rgba(255, 255, 255, .08);
        }
    }

    @media (max-width: 1199.98px) {
        .fb-post-editor__workspace {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .fb-post-editor__sidebar,
        .fb-post-editor__canvas,
        .fb-post-editor__inspector {
            border: 0;
            border-bottom: 1px solid rgba(26, 33, 36, .08);
        }

        .fb-post-editor__canvas {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
    }

    @media (max-width: 767.98px) {
        .fb-post-editor {
            border-radius: 1rem;
        }

        .fb-post-editor__rich {
            min-height: 240px;
        }

        .fb-post-editor__topbar {
            padding: .9rem;
        }

        .fb-post-editor__workspace {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .fb-post-editor__sidebar,
        .fb-post-editor__canvas,
        .fb-post-editor__inspector {
            border: 0;
            border-bottom: 1px solid rgba(26, 33, 36, .08);
            padding: .85rem;
        }

        .fb-post-editor__canvas-header {
            align-items: stretch;
            flex-direction: column;
        }

        .fb-post-editor__canvas-header .btn {
            width: 100%;
        }

        .fb-post-editor__formatbar {
            padding: .7rem;
            gap: .45rem;
        }

        .fb-post-editor__formatbar .form-select,
        .fb-post-editor__formatbar .fb-post-editor__color-control {
            width: 100%;
            min-width: 0;
        }

        .fb-post-editor__formatbar .btn {
            min-width: 2.6rem;
            min-height: 2.6rem;
        }

        .fb-post-editor__formatbar-group {
            width: 100%;
            padding-right: 0;
            margin-right: 0;
            border-right: 0;
        }

        .fb-post-editor__color-control {
            min-height: 3rem;
            padding: .5rem .65rem .5rem .8rem;
        }

        .fb-post-editor__color-control .form-control-color {
            width: 3.25rem;
            min-width: 3.25rem;
            height: 2.35rem;
        }

        .fb-post-editor__block-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .fb-post-editor__block-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .fb-post-editor__social-item-head {
            align-items: stretch;
        }

        .fb-post-editor__social-item-head .btn {
            width: 100%;
        }

        .fb-post-editor__field-label {
            align-items: flex-start;
            flex-direction: column;
        }

        .fb-post-media-picker__dropzone {
            grid-template-columns: 1fr;
            justify-items: stretch;
        }

        .fb-post-media-picker__figure {
            width: 100%;
            max-width: 140px;
            aspect-ratio: 1 / 1;
        }

        .fb-post-media-picker__actions {
            width: 100%;
            min-width: 0;
            align-items: stretch;
        }

        .fb-post-media-picker__actions .btn {
            width: 100%;
        }

        .fb-post-editor__slider-track {
            grid-auto-columns: minmax(180px, 78%);
        }
    }
    .fb-post-editor.fb-post-editor--linear {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 18px 42px rgba(17, 24, 39, .06);
        overflow: visible;
        container-type: inline-size;
    }

    .fb-post-editor--linear .fb-post-editor__bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid rgba(26, 33, 36, .08);
        background: rgba(248, 249, 251, .78);
        backdrop-filter: blur(14px);
    }

    .fb-post-editor--linear .fb-post-editor__bar-copy {
        display: grid;
        gap: .2rem;
        min-width: 0;
    }

    .fb-post-editor--linear .fb-post-editor__bar-copy strong {
        color: #182225;
        font-size: .96rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .fb-post-editor--linear .fb-post-editor__bar-copy span {
        color: #6b7577;
        font-size: .84rem;
        line-height: 1.3;
    }

    .fb-post-editor--linear .fb-post-editor__blocks {
        display: grid;
        gap: .8rem;
        padding: 1rem;
    }

    .fb-post-editor--linear .fb-post-editor__insert {
        display: flex;
        justify-content: center;
    }

    .fb-post-editor--linear .fb-post-editor__insert--tail {
        padding-top: .1rem;
    }

    .fb-post-editor--linear .fb-post-editor__add-wrap {
        position: relative;
        display: inline-flex;
        justify-content: center;
        z-index: 5;
    }

    .fb-post-editor--linear .fb-post-editor__add-wrap.is-open {
        z-index: 1080;
    }

    .fb-post-editor--linear .fb-post-editor__add-btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        min-height: 2.4rem;
        border-radius: 999px;
        padding-inline: .9rem;
        white-space: nowrap;
    }

    .fb-post-add-menu {
        position: absolute;
        top: calc(100% + .5rem);
        left: 50%;
        z-index: 1090;
        display: grid;
        gap: .25rem;
        min-width: 13rem;
        max-width: calc(100vw - 2rem);
        padding: .45rem;
        border: 1px solid rgba(26, 33, 36, .1);
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 18px 40px rgba(17, 24, 39, .14);
        transform: translateX(-50%);
    }

    .fb-post-add-menu button {
        display: flex;
        align-items: center;
        gap: .55rem;
        width: 100%;
        border: 0;
        border-radius: .7rem;
        background: transparent;
        color: #1f2937;
        padding: .6rem .75rem;
        text-align: left;
        font-size: .9rem;
        line-height: 1.3;
    }

    .fb-post-add-menu__icon {
        display: inline-flex;
        width: 1.65rem;
        height: 1.65rem;
        align-items: center;
        justify-content: center;
        border-radius: .55rem;
        background: rgba(33, 37, 41, .08);
        color: #1f2937;
        flex: 0 0 auto;
    }

    .fb-post-add-menu button:hover {
        background: rgba(33, 37, 41, .06);
    }

    .fb-post-editor--linear .fb-post-editor__empty {
        border: 1px dashed rgba(33, 37, 41, .14);
        border-radius: .95rem;
        padding: 2rem 1.2rem;
        text-align: center;
        color: #6b7577;
        background: rgba(248, 249, 251, .58);
    }

    .fb-post-block {
        position: relative;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 10px 24px rgba(17, 24, 39, .04);
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .fb-post-block.is-active {
        border-color: rgba(31, 92, 79, .28);
        box-shadow: 0 0 0 3px rgba(31, 92, 79, .08), 0 14px 28px rgba(17, 24, 39, .06);
    }

    .fb-post-block.is-dragging {
        opacity: .72;
        transform: scale(.998);
    }

    .fb-post-block.is-drop-target {
        border-color: rgba(31, 92, 79, .42);
        box-shadow: 0 0 0 3px rgba(31, 92, 79, .12), 0 14px 30px rgba(17, 24, 39, .08);
    }

    .fb-post-block.is-drop-before::before,
    .fb-post-block.is-drop-after::after {
        content: "";
        position: absolute;
        left: .8rem;
        right: .8rem;
        height: 4px;
        border-radius: 999px;
        background: #1f5c4f;
        box-shadow: 0 0 0 4px rgba(31, 92, 79, .14);
        z-index: 3;
    }

    .fb-post-block.is-drop-before::before {
        top: -3px;
    }

    .fb-post-block.is-drop-after::after {
        bottom: -3px;
    }

    .fb-post-block.is-hidden {
        border-style: dashed;
        opacity: .76;
    }

    .fb-post-block.is-hidden .fb-post-block__head {
        border-radius: 1rem;
        border-bottom: 0;
    }

    .fb-post-block.is-hidden .fb-post-block__content {
        display: none;
    }

    .fb-post-block__chip--hidden {
        background: rgba(108, 117, 125, .16);
        color: #4b5563;
    }

    .fb-post-block__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .8rem .9rem;
        border-bottom: 1px solid rgba(26, 33, 36, .06);
        background: rgba(248, 249, 251, .82);
        border-radius: 1rem 1rem 0 0;
    }

    .fb-post-block__title {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        min-width: 0;
        color: #111827;
        font-size: .92rem;
        font-weight: 600;
    }

    .fb-post-block__title span:last-child {
        overflow-wrap: anywhere;
    }

    .fb-post-block__icon,
    .fb-post-block__chip,
    .fb-post-settings__badge {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        border-radius: 999px;
        background: rgba(33, 37, 41, .08);
        color: #1f2937;
        padding: .26rem .56rem;
        font-size: .76rem;
        line-height: 1;
    }

    .fb-post-block__icon {
        min-width: 2rem;
        min-height: 2rem;
        justify-content: center;
        border-radius: .7rem;
        padding: 0;
        font-size: .9rem;
        flex: 0 0 auto;
    }

    .fb-post-block__drag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.25rem;
        min-height: 2.25rem;
        border: 1px solid rgba(26, 33, 36, .1);
        border-radius: .75rem;
        background: rgba(255, 255, 255, .86);
        color: #3f4a4d;
        padding: 0;
        cursor: grab;
        font-size: 1.15rem;
        line-height: 1;
        touch-action: none;
        user-select: none;
    }

    .fb-post-block__drag:active {
        cursor: grabbing;
    }

    body.fb-post-editor-is-dragging {
        cursor: grabbing;
        user-select: none;
    }

    .fb-post-block__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: .45rem;
        min-width: 0;
    }

    .fb-post-block__actions .btn {
        min-width: 2.1rem;
        min-height: 2.1rem;
        padding: 0;
        border-radius: .7rem;
    }

    .fb-post-block__meta {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .35rem;
        margin-right: .15rem;
    }

    .fb-post-block__content {
        display: grid;
        gap: .8rem;
        padding: .95rem;
    }

    .fb-post-block__toolbar {
        display: none;
        align-items: center;
        flex-wrap: wrap;
        gap: .4rem;
        padding: .65rem;
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: .9rem;
        background: rgba(248, 249, 251, .86);
    }

    .fb-post-block.is-active .fb-post-block__toolbar {
        display: flex;
    }

    .fb-post-block__toolbar-group {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .35rem;
    }

    .fb-post-block__toolbar .btn {
        min-width: 2.2rem;
        min-height: 2.2rem;
        border-radius: .72rem;
        padding: 0 .55rem;
    }

    .fb-post-block__style {
        position: relative;
        margin-left: auto;
    }

    .fb-post-style-menu {
        position: absolute;
        top: calc(100% + .5rem);
        right: 0;
        z-index: 25;
        display: none;
        gap: .65rem;
        min-width: 15rem;
        padding: .75rem;
        border: 1px solid rgba(26, 33, 36, .1);
        border-radius: .9rem;
        background: #fff;
        box-shadow: 0 16px 36px rgba(17, 24, 39, .14);
    }

    .fb-post-block__style.is-open .fb-post-style-menu {
        display: grid;
    }

    .fb-post-style-menu__title {
        color: #111827;
        font-size: .82rem;
        font-weight: 700;
    }

    .fb-post-style-menu__field {
        display: grid;
        gap: .35rem;
        color: #6b7577;
        font-size: .8rem;
    }

    .fb-post-style-menu__field .form-control-color {
        width: 100%;
        min-width: 0;
        height: 2.6rem;
        border-radius: .7rem;
    }

    .fb-post-block__rich,
    .fb-post-block__heading,
    .fb-post-block__code {
        width: 100%;
        border: 1px solid rgba(26, 33, 36, .1);
        border-radius: .95rem;
        background: #fff;
        color: #111827;
        outline: none;
        box-shadow: inset 0 1px 2px rgba(17, 24, 39, .03);
    }

    .fb-post-block__rich,
    .fb-post-block__heading {
        padding: .95rem 1rem;
    }

    .fb-post-block__rich {
        min-height: 180px;
        line-height: 1.7;
    }

    .fb-post-block__heading {
        min-height: 3.4rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .fb-post-block__rich:empty::before,
    .fb-post-block__heading:empty::before {
        content: attr(data-placeholder);
        color: rgba(108, 117, 125, .82);
    }

    .fb-post-block__rich:focus,
    .fb-post-block__heading:focus,
    .fb-post-block__code:focus {
        border-color: rgba(31, 92, 79, .32);
        box-shadow: 0 0 0 4px rgba(31, 92, 79, .08);
    }

    .fb-post-block__stack {
        display: grid;
        gap: .75rem;
    }

    .fb-post-block__subhead {
        color: #6b7577;
        font-size: .82rem;
        font-weight: 600;
    }

    .fb-post-block__code,
    .fb-post-block__code.form-control,
    .fb-post-block__code.form-control:focus {
        display: block;
        width: 100%;
        min-width: 0;
        max-width: none;
        min-height: 420px !important;
        resize: vertical;
        padding: .95rem 1rem;
        font: 400 .92rem/1.6 "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        background: transparent !important;
        background-color: transparent !important;
        color: transparent !important;
        -webkit-text-fill-color: transparent !important;
        caret-color: #f3f4f6;
        border-color: transparent !important;
        box-shadow: none !important;
        white-space: pre;
        overflow: auto;
        position: relative;
        z-index: 2;
        color-scheme: dark;
    }

    .fb-post-block__code--html {
        min-height: 420px !important;
    }

    .fb-post-block__code-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .fb-post-block__code-editor {
        display: grid;
        min-width: 48rem;
        border: 1px solid #111827;
        border-radius: .95rem;
        background: #111827;
        overflow: hidden;
    }

    .fb-post-block__code-editor .fb-post-block__code,
    .fb-post-block__code-highlight {
        grid-area: 1 / 1;
    }

    .fb-post-block__code-highlight {
        min-height: 420px;
        margin: 0;
        padding: .95rem 1rem;
        font: 400 .92rem/1.6 "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        white-space: pre;
        overflow: auto;
        pointer-events: none;
        background: #111827;
        color: #abb2bf;
        border-radius: .95rem;
        scrollbar-width: none;
    }

    .fb-post-block__code-highlight::-webkit-scrollbar {
        display: none;
    }

    .fb-post-block__code-highlight code.hljs {
        display: block;
        padding: 0;
        overflow: visible;
        background: transparent;
    }

    .fb-post-block__code::placeholder {
        color: rgba(209, 213, 219, .58);
        -webkit-text-fill-color: rgba(209, 213, 219, .58);
    }

    .fb-post-block__code::selection {
        background: rgba(97, 175, 239, .32);
        color: transparent;
    }

    .fb-post-block__html-preview,
    .fb-post-block__preview {
        overflow: hidden;
        border: 1px dashed rgba(33, 37, 41, .12);
        border-radius: .95rem;
        background: linear-gradient(180deg, #ffffff 0%, #f5f6f8 100%);
    }

    .fb-post-block__html-preview {
        min-height: 6rem;
        max-height: 28rem;
        padding: .9rem;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }

    .fb-post-block__html-preview * {
        box-sizing: border-box;
        max-width: 100%;
    }

    .fb-post-block__html-preview img,
    .fb-post-block__html-preview video,
    .fb-post-block__html-preview canvas,
    .fb-post-block__html-preview svg {
        display: block;
        max-width: 100% !important;
        height: auto !important;
    }

    .fb-post-block__html-preview iframe,
    .fb-post-block__html-preview embed,
    .fb-post-block__html-preview object {
        display: block;
        width: 100% !important;
        max-width: 100% !important;
        max-height: 24rem;
        border: 0;
    }

    .fb-post-block__media {
        display: grid;
        gap: .65rem;
    }

    .fb-post-block__preview {
        width: 100%;
        aspect-ratio: 16 / 9;
        min-height: 0;
        max-height: 360px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fb-post-block__preview img,
    .fb-post-block__preview iframe,
    .fb-post-block__preview video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border: 0;
    }

    .fb-post-block__preview iframe,
    .fb-post-block__preview video {
        object-fit: contain;
        background: #000;
    }

    .fb-post-block__placeholder-text {
        padding: 1rem;
        color: #6b7577;
        text-align: center;
        font-size: .9rem;
    }

    .fb-post-block__slider-track {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(220px, 240px);
        gap: .8rem;
        overflow-x: auto;
        padding-bottom: .2rem;
    }

    .fb-post-block__slider-card {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: .95rem;
        background: rgba(248, 249, 251, .78);
        overflow: hidden;
    }

    .fb-post-block__slider-figure {
        margin: 0;
        background: linear-gradient(180deg, #ffffff 0%, #f5f6f8 100%);
        overflow: hidden;
    }

    .fb-post-block__slider-figure img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .fb-post-block__slider-placeholder {
        display: flex;
        width: 100%;
        height: 100%;
        align-items: center;
        justify-content: center;
        color: #7a8487;
        font-size: 1.4rem;
        font-weight: 700;
    }

    .fb-post-block__slider-copy {
        display: grid;
        gap: .2rem;
        padding: .75rem .85rem .9rem;
    }

    .fb-post-block__slider-copy strong,
    .fb-post-block__slider-copy span {
        overflow-wrap: anywhere;
    }

    .fb-post-block__slider-copy strong {
        color: #182225;
        font-size: .9rem;
        line-height: 1.35;
    }

    .fb-post-block__slider-copy span {
        color: #6b7577;
        font-size: .82rem;
        line-height: 1.45;
    }

    .fb-post-block__newsletter {
        overflow: hidden;
    }

    .fb-post-block__newsletter h3,
    .fb-post-block__newsletter p {
        overflow-wrap: anywhere;
    }

    .fb-post-editor__settings-root {
        position: relative;
        z-index: 40;
    }

    .fb-post-editor__modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, .36);
    }

    body.fb-modal-open {
        overflow: hidden;
    }

    #postEditorDeleteModal.is-open {
        display: block;
        background: rgba(17, 24, 39, .46);
    }

    #postEditorSettingsModal.is-open {
        display: block;
        background: rgba(17, 24, 39, .46);
    }

    .fb-post-editor__modal {
        position: fixed;
        top: 50%;
        left: 50%;
        z-index: 41;
        width: min(760px, calc(100vw - 2rem));
        max-height: calc(100vh - 2rem);
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 24px 60px rgba(17, 24, 39, .24);
        transform: translate(-50%, -50%);
        overflow: hidden;
    }

    .fb-post-editor__modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid rgba(26, 33, 36, .08);
        background: rgba(248, 249, 251, .86);
    }

    .fb-post-editor__modal-head strong {
        display: block;
        color: #111827;
        font-size: .96rem;
        line-height: 1.25;
    }

    .fb-post-editor__modal-head span {
        display: block;
        margin-top: .2rem;
        color: #6b7577;
        font-size: .82rem;
    }

    .fb-post-editor__modal-head .btn {
        min-width: 2.2rem;
        min-height: 2.2rem;
        padding: 0;
        border-radius: .7rem;
    }

    .fb-post-editor__modal-body {
        overflow: auto;
        padding: 1rem 1.1rem 1.1rem;
    }

    .fb-post-settings__section + .fb-post-settings__section {
        margin-top: 1rem;
    }

    .fb-post-settings__stack {
        display: grid;
        gap: .85rem;
    }

    .fb-post-settings__grid {
        display: grid;
        gap: .85rem;
    }

    .fb-post-settings__card {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: .95rem;
        background: rgba(248, 249, 251, .64);
        padding: .9rem;
    }

    .fb-post-settings__card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .8rem;
    }

    .fb-post-settings__badge i {
        font-size: .95rem;
    }

    .fb-post-settings__note {
        margin: 0;
        color: #6b7577;
        font-size: .9rem;
        line-height: 1.55;
    }

    [data-bs-theme=dark] .fb-post-editor.fb-post-editor--linear {
        border-color: rgba(255, 255, 255, .08);
        background: #202734;
        box-shadow: 0 24px 60px rgba(8, 11, 18, .24);
    }

    [data-bs-theme=dark] .fb-post-editor--linear .fb-post-editor__bar,
    [data-bs-theme=dark] .fb-post-editor__modal-head {
        border-bottom-color: rgba(255, 255, 255, .08);
        background: rgba(28, 35, 45, .92);
    }

    [data-bs-theme=dark] .fb-post-editor--linear .fb-post-editor__bar-copy strong,
    [data-bs-theme=dark] .fb-post-block__title,
    [data-bs-theme=dark] .fb-post-block__slider-copy strong,
    [data-bs-theme=dark] .fb-post-editor__modal-head strong {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-editor--linear .fb-post-editor__bar-copy span,
    [data-bs-theme=dark] .fb-post-block__placeholder-text,
    [data-bs-theme=dark] .fb-post-block__slider-copy span,
    [data-bs-theme=dark] .fb-post-editor__empty,
    [data-bs-theme=dark] .fb-post-settings__note,
    [data-bs-theme=dark] .fb-post-editor__modal-head span,
    [data-bs-theme=dark] .fb-post-style-menu__field {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-add-menu,
    [data-bs-theme=dark] .fb-post-style-menu,
    [data-bs-theme=dark] .fb-post-editor__modal,
    [data-bs-theme=dark] .fb-post-block,
    [data-bs-theme=dark] .fb-post-settings__card {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(34, 41, 52, .96);
        box-shadow: 0 18px 36px rgba(8, 11, 18, .22);
    }

    [data-bs-theme=dark] .fb-post-add-menu button {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-add-menu__icon {
        background: rgba(124, 197, 175, .14);
        color: #9ae6d2;
    }

    [data-bs-theme=dark] .fb-post-add-menu button:hover {
        background: rgba(124, 197, 175, .12);
    }

    [data-bs-theme=dark] .fb-post-editor--linear .fb-post-editor__add-btn,
    [data-bs-theme=dark] .fb-post-block__actions .btn,
    [data-bs-theme=dark] .fb-post-block__toolbar .btn,
    [data-bs-theme=dark] .fb-post-style-menu .form-select,
    [data-bs-theme=dark] .fb-post-style-menu .form-control-color,
    [data-bs-theme=dark] #postEditorSettingsModal .btn-outline-secondary,
    [data-bs-theme=dark] #postEditorDeleteModal .btn-outline-secondary {
        border-color: rgba(255, 255, 255, .12);
        background: rgba(255, 255, 255, .03);
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-editor--linear .fb-post-editor__add-btn:hover,
    [data-bs-theme=dark] .fb-post-block__actions .btn:hover,
    [data-bs-theme=dark] .fb-post-block__toolbar .btn:hover,
    [data-bs-theme=dark] #postEditorSettingsModal .btn-outline-secondary:hover,
    [data-bs-theme=dark] #postEditorDeleteModal .btn-outline-secondary:hover {
        border-color: rgba(124, 197, 175, .22);
        background: rgba(124, 197, 175, .12);
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-block.is-active {
        border-color: rgba(124, 197, 175, .34);
        box-shadow: 0 0 0 3px rgba(124, 197, 175, .12), 0 18px 36px rgba(8, 11, 18, .22);
    }

    [data-bs-theme=dark] .fb-post-block.is-hidden {
        opacity: .7;
    }

    [data-bs-theme=dark] .fb-post-block__head,
    [data-bs-theme=dark] .fb-post-block__toolbar {
        border-bottom-color: rgba(255, 255, 255, .08);
        background: rgba(27, 34, 45, .96);
    }

    [data-bs-theme=dark] .fb-post-block__icon,
    [data-bs-theme=dark] .fb-post-block__chip,
    [data-bs-theme=dark] .fb-post-settings__badge {
        background: rgba(124, 197, 175, .14);
        color: #9ae6d2;
    }

    [data-bs-theme=dark] .fb-post-block__chip--hidden {
        background: rgba(156, 163, 175, .14);
        color: #c8ced8;
    }

    [data-bs-theme=dark] .fb-post-block__drag {
        border-color: rgba(255, 255, 255, .12);
        background: rgba(255, 255, 255, .05);
        color: #d7dde7;
    }

    [data-bs-theme=dark] .fb-post-block.is-drop-before::before,
    [data-bs-theme=dark] .fb-post-block.is-drop-after::after {
        background: #9ae6d2;
        box-shadow: 0 0 0 4px rgba(124, 197, 175, .18);
    }

    [data-bs-theme=dark] .fb-post-style-menu__title {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-block__rich,
    [data-bs-theme=dark] .fb-post-block__heading {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(24, 29, 37, .92);
        color: #eef1f6;
        color-scheme: dark;
    }

    [data-bs-theme=dark] .fb-post-block__rich [style*="color: #111827"],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:#111827"],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: rgb(17, 24, 39)"],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: rgb(0, 0, 0)" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:rgb(0,0,0)" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: black"],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:black" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:#000000" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: #000000" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:#000"],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: #000"] {
        color: #eef1f6 !important;
    }

    .fb-post-block__rich [style*="color: white" i],
    .fb-post-block__rich [style*="color:white" i],
    .fb-post-block__rich [style*="color: rgb(255, 255, 255)" i],
    .fb-post-block__rich [style*="color:rgb(255,255,255)" i],
    .fb-post-block__rich [style*="color:#fff" i],
    .fb-post-block__rich [style*="color: #fff" i],
    .fb-post-block__rich [style*="color:#ffffff" i],
    .fb-post-block__rich [style*="color: #ffffff" i] {
        color: #111827 !important;
    }

    [data-bs-theme=dark] .fb-post-block__rich [style*="color: white" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:white" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: rgb(255, 255, 255)" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:rgb(255,255,255)" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:#fff" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: #fff" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color:#ffffff" i],
    [data-bs-theme=dark] .fb-post-block__rich [style*="color: #ffffff" i] {
        color: #eef1f6 !important;
    }

    [data-bs-theme=dark] .fb-post-block__rich:empty::before,
    [data-bs-theme=dark] .fb-post-block__heading:empty::before {
        color: rgba(156, 163, 175, .78);
    }

    [data-bs-theme=dark] .fb-post-block__rich:focus,
    [data-bs-theme=dark] .fb-post-block__heading:focus,
    [data-bs-theme=dark] .fb-post-block__code:focus {
        border-color: rgba(124, 197, 175, .34);
        box-shadow: 0 0 0 4px rgba(124, 197, 175, .12);
    }

    [data-bs-theme=dark] .fb-post-block__preview,
    [data-bs-theme=dark] .fb-post-block__html-preview,
    [data-bs-theme=dark] .fb-post-editor__empty,
    [data-bs-theme=dark] .fb-post-block__slider-figure {
        border-color: rgba(124, 197, 175, .18);
        background: linear-gradient(180deg, #222934 0%, #1b222d 100%);
    }

    [data-bs-theme=dark] .fb-post-block__slider-placeholder {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-block__newsletter.bg-body-tertiary {
        background-color: rgba(24, 29, 37, .92) !important;
    }

    [data-bs-theme=dark] .fb-post-block__code,
    [data-bs-theme=dark] .fb-post-block__code.form-control,
    [data-bs-theme=dark] .fb-post-block__code.form-control:focus {
        background: transparent !important;
        background-color: transparent !important;
        color: transparent !important;
        -webkit-text-fill-color: transparent !important;
        border-color: transparent !important;
        box-shadow: none !important;
    }

    [data-bs-theme=dark] #postEditorSettingsModal .modal-content,
    [data-bs-theme=dark] #postEditorDeleteModal .modal-content {
        border-color: rgba(255, 255, 255, .08);
        background: #202734;
        box-shadow: 0 24px 60px rgba(8, 11, 18, .28);
    }

    [data-bs-theme=dark] #postEditorSettingsModal .modal-header,
    [data-bs-theme=dark] #postEditorSettingsModal .modal-footer,
    [data-bs-theme=dark] #postEditorDeleteModal .modal-header,
    [data-bs-theme=dark] #postEditorDeleteModal .modal-footer {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(27, 34, 45, .96);
    }

    [data-bs-theme=dark] #postEditorSettingsModal .modal-title,
    [data-bs-theme=dark] #postEditorDeleteModal .modal-title,
    [data-bs-theme=dark] #postEditorSettingsModal .form-label,
    [data-bs-theme=dark] #postEditorSettingsModal .form-check-label,
    [data-bs-theme=dark] #postEditorDeleteModal .modal-body {
        color: #eef1f6;
    }

    [data-bs-theme=dark] #postEditorSettingsModal .form-text,
    [data-bs-theme=dark] #postEditorSettingsModal .text-body-secondary,
    [data-bs-theme=dark] #postEditorDeleteModal .btn-close,
    [data-bs-theme=dark] #postEditorSettingsModal .btn-close {
        color: #9ca3af;
    }

    [data-bs-theme=dark] #postEditorSettingsModal .form-control,
    [data-bs-theme=dark] #postEditorSettingsModal .form-select,
    [data-bs-theme=dark] #postEditorSettingsModal .input-group-text,
    [data-bs-theme=dark] #postEditorDeleteModal .form-control,
    [data-bs-theme=dark] #postEditorDeleteModal .form-select {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(24, 29, 37, .92);
        color: #eef1f6;
    }

    [data-bs-theme=dark] #postEditorSettingsModal .form-control::placeholder {
        color: rgba(156, 163, 175, .72);
    }

    [data-bs-theme=dark] #postEditorSettingsModal .input-group > .btn-outline-secondary {
        border-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] #postEditorSettingsModal .form-check-input {
        background-color: rgba(24, 29, 37, .92);
        border-color: rgba(255, 255, 255, .16);
    }

    [data-bs-theme=dark] #postEditorSettingsModal .form-check-input:checked {
        background-color: #5bb8a0;
        border-color: #5bb8a0;
    }

    @container (max-width: 860px) {
        .fb-post-editor--linear .fb-post-editor__bar {
            align-items: stretch;
            flex-direction: column;
        }

        .fb-post-editor--linear .fb-post-editor__add-wrap,
        .fb-post-editor--linear .fb-post-editor__add-btn {
            width: 100%;
        }

        .fb-post-add-menu {
            left: 0;
            right: 0;
            min-width: 0;
            transform: none;
        }

        .fb-post-block__head {
            align-items: stretch;
            flex-direction: column;
        }

        .fb-post-block__actions {
            justify-content: flex-start;
        }

        .fb-post-block__toolbar {
            align-items: stretch;
        }

        .fb-post-block__style {
            width: 100%;
            margin-left: 0;
        }

        .fb-post-style-menu {
            left: 0;
            right: 0;
            min-width: 0;
        }

        .fb-post-editor__modal {
            width: calc(100vw - 1rem);
            max-height: calc(100vh - 1rem);
        }
    }

    @media (max-width: 767.98px) {
        .fb-post-editor.fb-post-editor--linear {
            border-radius: .9rem;
        }

        .fb-post-editor--linear .fb-post-editor__bar,
        .fb-post-editor--linear .fb-post-editor__blocks,
        .fb-post-editor__modal-head,
        .fb-post-editor__modal-body {
            padding-left: .85rem;
            padding-right: .85rem;
        }

        .fb-post-editor--linear .fb-post-editor__blocks {
            gap: .7rem;
            padding-top: .85rem;
            padding-bottom: .85rem;
        }

        .fb-post-add-menu {
            position: fixed;
            top: max(5rem, env(safe-area-inset-top));
            left: 1rem;
            right: 1rem;
            max-width: none;
            max-height: calc(100dvh - 8rem);
            overflow-y: auto;
            transform: none;
            -webkit-overflow-scrolling: touch;
        }

        .fb-post-add-menu button {
            min-height: 3rem;
            font-size: 1rem;
        }

        .fb-post-add-menu__icon {
            width: 2rem;
            height: 2rem;
        }

        .fb-post-block__content {
            padding: .85rem;
        }

        .fb-post-block__stack {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .fb-post-block__code {
            min-width: 42rem;
            min-height: 360px !important;
        }

        .fb-post-block__drag {
            min-width: 2.55rem;
            min-height: 2.55rem;
        }

        .fb-post-block__toolbar .btn {
            min-width: 2.4rem;
            min-height: 2.4rem;
        }

        .fb-post-block__slider-track {
            grid-auto-columns: minmax(190px, 78%);
        }

        .fb-post-settings__card-head {
            align-items: stretch;
            flex-direction: column;
        }
    }
</style>

<?php ob_start(); ?>
<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts') ?>">
    <i class="ci-arrow-left"></i>
    <span><?= print_translation('admin_btn_back') ?></span>
</a>
<?php $adminPageActions = ob_get_clean(); ?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $is_edit ? return_translation('admin_post_edit_heading') : return_translation('admin_post_create_heading'),
    'subtitle' => return_translation('admin_post_form_subtitle'),
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
        data-autosave-url="<?= base_href('/admin/posts/autosave') ?>"
        data-autosave-post-id="<?= $is_edit ? (int)$post['id'] : 0 ?>"
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
                <select class="form-select <?= get_validation_class('category_id') ?>" name="category_id" data-select aria-label="<?= htmlSC(return_translation('admin_posts_col_category')) ?>" required>
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
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_post_content') ?></label>
                <textarea
                    class="form-control <?= get_validation_class('content') ?> d-none"
                    id="post_content"
                    name="content"
                    rows="10"
                    data-post-editor
                ><?= htmlSC($contentValue) ?></textarea>
                <div
                    class="fb-post-editor fb-post-editor--linear"
                    data-post-editor-app
                    data-post-editor-config="<?= htmlSC(json_encode($editorConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                ></div>
                <?= get_errors('content') ?>
            </div>
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
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts') ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
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

    <div class="modal fade" id="postEditorSettingsModal" tabindex="-1" role="dialog" aria-hidden="true" data-post-editor-settings-modal>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title" data-editor-settings-title><?= print_translation('admin_post_builder_block_settings') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC($closeLabel) ?>"></button>
                </div>
                <div class="modal-body" data-editor-settings-body></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark rounded-pill" data-bs-dismiss="modal">
                        <?= print_translation('admin_btn_done') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="postEditorDeleteModal" tabindex="-1" aria-hidden="true" data-post-editor-delete-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title" data-editor-delete-title><?= htmlSC($editorDeleteModalTitle) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC($closeLabel) ?>"></button>
                </div>
                <div class="modal-body" data-editor-delete-text>
                    <?= htmlSC($editorDeleteModalText) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">
                        <?= print_translation('admin_btn_cancel') ?>
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill" data-editor-confirm-remove>
                        <?= print_translation('admin_btn_delete') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
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
