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
$postImagePreviewLabel = return_translation('admin_post_image_preview');
$postImageDropTitle = return_translation('admin_post_image_drop_title');
$postImageDropHint = return_translation('admin_post_image_drop_hint');
$postImagePickerHint = return_translation('admin_files_picker_hint');
$postImageLocalHint = return_translation('admin_post_image_local_hint');
$postImageUrlHint = return_translation('admin_post_image_url_hint');
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
        'addCode' => return_translation('admin_post_builder_add_code'),
        'textBlock' => return_translation('admin_post_builder_block_text'),
        'headingBlock' => return_translation('admin_post_builder_block_heading'),
        'imageBlock' => return_translation('admin_post_builder_block_image'),
        'videoBlock' => return_translation('admin_post_builder_block_video'),
        'codeBlock' => return_translation('admin_post_builder_block_code'),
        'moveUp' => return_translation('admin_post_builder_move_up'),
        'moveDown' => return_translation('admin_post_builder_move_down'),
        'remove' => return_translation('admin_post_builder_remove'),
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
        'codePlaceholder' => return_translation('admin_post_builder_code_placeholder'),
        'textPlaceholder' => return_translation('admin_post_builder_text_placeholder'),
        'headingPlaceholder' => return_translation('admin_post_builder_heading_placeholder'),
        'font' => return_translation('admin_post_builder_font'),
        'size' => return_translation('admin_post_builder_size'),
        'textColor' => return_translation('admin_post_builder_text_color'),
        'background' => return_translation('admin_post_builder_background'),
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
        'blockCount' => return_translation('admin_post_builder_block_count'),
    ],
];
?>

<style>
    .fb-post-editor {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1.25rem;
        background: linear-gradient(180deg, #f4efe7 0%, #fbfaf8 100%);
        box-shadow: 0 24px 60px rgba(24, 33, 37, .08);
        overflow: hidden;
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
        color: #173a34;
        font-size: .88rem;
        font-weight: 700;
    }

    .fb-post-editor__workspace {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr) 300px;
        min-height: 720px;
    }

    .fb-post-editor__sidebar,
    .fb-post-editor__inspector {
        background: rgba(250, 248, 244, .92);
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
        background: rgba(31, 92, 79, .12);
        color: #1f5c4f;
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
        border-color: rgba(31, 92, 79, .18);
        background: #fff;
    }

    .fb-post-editor__outline-item.is-active {
        border-color: rgba(31, 92, 79, .26);
        background: rgba(31, 92, 79, .1);
        color: #173a34;
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
        border: 1px dashed rgba(31, 92, 79, .22);
        border-radius: 1.1rem;
        padding: 2.4rem 1.4rem;
        text-align: center;
        color: #5d6668;
        background: rgba(255, 255, 255, .78);
    }

    .fb-post-editor__block {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 16px 36px rgba(28, 37, 38, .06);
        overflow: hidden;
        transition: box-shadow .15s ease, border-color .15s ease, transform .15s ease;
    }

    .fb-post-editor__block.is-selected {
        border-color: rgba(31, 92, 79, .32);
        box-shadow: 0 0 0 1px rgba(31, 92, 79, .22), 0 18px 40px rgba(31, 92, 79, .08);
        transform: translateY(-1px);
    }

    .fb-post-editor__block.is-drop-target {
        outline: 2px solid rgba(31, 92, 79, .24);
    }

    .fb-post-editor__block-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .8rem .95rem;
        background: rgba(249, 247, 243, .96);
        border-bottom: 1px solid rgba(26, 33, 36, .06);
    }

    .fb-post-editor__block-title {
        display: flex;
        align-items: center;
        gap: .6rem;
        font-weight: 600;
        color: #1e1e1e;
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
        background: rgba(248, 245, 240, .9);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .65);
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
        min-height: 180px;
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
        border: 1px dashed rgba(31, 92, 79, .18);
        border-radius: 1rem;
        background: linear-gradient(180deg, #fbf8f3 0%, #f2eee7 100%);
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
        min-height: 220px;
        font: 400 .95rem/1.65 "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        border-radius: 1rem;
        background: #111827;
        color: #f3f4f6;
        border-color: #111827;
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
        background: rgba(31, 92, 79, .1);
        color: #173a34;
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
        border-radius: 1.25rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, .98) 0%, rgba(248, 245, 240, .94) 100%);
        box-shadow: 0 18px 38px rgba(28, 37, 38, .06);
        overflow: hidden;
    }

    .fb-post-media-picker.is-invalid {
        border-color: rgba(220, 53, 69, .38);
        box-shadow: 0 0 0 1px rgba(220, 53, 69, .08), 0 16px 36px rgba(220, 53, 69, .06);
    }

    .fb-post-media-picker__dropzone {
        display: grid;
        grid-template-columns: 168px minmax(0, 1fr) auto;
        align-items: center;
        gap: 1.1rem;
        padding: 1.1rem;
        border-bottom: 1px solid rgba(26, 33, 36, .06);
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .fb-post-media-picker.is-dragover .fb-post-media-picker__dropzone {
        background: rgba(31, 92, 79, .08);
        box-shadow: inset 0 0 0 1px rgba(31, 92, 79, .18);
    }

    .fb-post-media-picker__figure {
        width: 168px;
        aspect-ratio: 4 / 3;
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid rgba(26, 33, 36, .08);
        background: linear-gradient(180deg, #f6f1e8 0%, #efe7da 100%);
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
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #8a9597;
        background: linear-gradient(180deg, rgba(255, 255, 255, .5) 0%, rgba(242, 238, 231, .95) 100%);
        font-size: 1.6rem;
    }

    .fb-post-media-picker__content {
        min-width: 0;
        display: grid;
        gap: .45rem;
    }

    .fb-post-media-picker__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-size: .76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7577;
    }

    .fb-post-media-picker__title {
        color: #182225;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        word-break: break-word;
    }

    .fb-post-media-picker__note {
        margin-top: .35rem;
        color: #5d6668;
        font-size: .88rem;
        line-height: 1.5;
    }

    .fb-post-media-picker__actions {
        display: grid;
        gap: .6rem;
        align-self: stretch;
        min-width: 12rem;
    }

    .fb-post-media-picker__actions .btn {
        min-height: 2.75rem;
        justify-content: center;
        white-space: nowrap;
        border-radius: .9rem;
    }

    .fb-post-media-picker__sources {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
        padding: 1rem;
    }

    .fb-post-media-picker__source {
        border: 1px solid rgba(26, 33, 36, .08);
        border-radius: 1rem;
        background: rgba(255, 255, 255, .82);
        padding: 1rem;
        box-shadow: 0 10px 24px rgba(28, 37, 38, .04);
    }

    .fb-post-media-picker__source-head {
        display: flex;
        align-items: flex-start;
        gap: .8rem;
        margin-bottom: .85rem;
    }

    .fb-post-media-picker__source-icon {
        width: 2.35rem;
        height: 2.35rem;
        border-radius: .8rem;
        background: rgba(31, 92, 79, .12);
        color: #1f5c4f;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-size: 1rem;
    }

    .fb-post-media-picker__source-title {
        color: #182225;
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .fb-post-media-picker__source-note {
        margin-top: .2rem;
        color: #5d6668;
        font-size: .84rem;
        line-height: 1.5;
    }

    .fb-post-media-picker__file-input-wrap {
        padding: 0;
    }

    .fb-post-media-picker__file-input,
    .fb-post-media-picker__source .form-control {
        width: 100%;
        min-height: 2.9rem;
        border-radius: .9rem;
    }

    .fb-post-media-picker__source .form-text {
        margin-top: .55rem;
    }

    .fb-post-media-picker__source .invalid-feedback,
    .fb-post-media-picker__source .is-invalid ~ .invalid-feedback {
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

    [data-bs-theme=dark] .fb-post-media-picker__dropzone {
        border-bottom-color: rgba(255, 255, 255, .08);
    }

    [data-bs-theme=dark] .fb-post-media-picker.is-dragover .fb-post-media-picker__dropzone {
        background: rgba(124, 197, 175, .08);
        box-shadow: inset 0 0 0 1px rgba(124, 197, 175, .18);
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

    [data-bs-theme=dark] .fb-post-media-picker__eyebrow,
    [data-bs-theme=dark] .fb-post-media-picker__note,
    [data-bs-theme=dark] .fb-post-media-picker__source-note {
        color: #9ca3af;
    }

    [data-bs-theme=dark] .fb-post-media-picker__title,
    [data-bs-theme=dark] .fb-post-media-picker__source-title {
        color: #eef1f6;
    }

    [data-bs-theme=dark] .fb-post-media-picker__source {
        border-color: rgba(255, 255, 255, .08);
        background: rgba(39, 46, 58, .88);
        box-shadow: 0 12px 26px rgba(8, 11, 18, .18);
    }

    @media (max-width: 767.98px) {
        .fb-post-editor {
            border-radius: 1rem;
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

        .fb-post-media-picker__dropzone {
            grid-template-columns: 1fr;
            justify-items: stretch;
            text-align: left;
        }

        .fb-post-media-picker__figure {
            width: 100%;
            max-width: none;
            aspect-ratio: 4 / 3;
        }

        .fb-post-media-picker__actions {
            width: 100%;
            min-width: 0;
        }

        .fb-post-media-picker__actions .btn {
            width: 100%;
        }

        .fb-post-media-picker__sources {
            grid-template-columns: 1fr;
        }
    }
</style>

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
                    class="fb-post-editor"
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
                                <i class="ci-upload"></i>
                            </div>
                        </div>
                        <div class="fb-post-media-picker__content">
                            <div class="fb-post-media-picker__eyebrow">
                                <i class="ci-image"></i>
                                <?= htmlSC($postImagePreviewLabel) ?>
                            </div>
                            <div class="fb-post-media-picker__title" id="post_image_path"><?= htmlSC($currentImage ?: $postImageDropTitle) ?></div>
                            <div class="fb-post-media-picker__note" id="post_image_note"><?= htmlSC($currentImage ? $postImagePickerHint : $postImageDropHint) ?></div>
                        </div>
                        <div class="fb-post-media-picker__actions">
                            <label class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" for="post_image_file">
                                <i class="ci-upload"></i><?= print_translation('admin_btn_choose_file') ?>
                            </label>
                            <a
                                class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2"
                                href="<?= base_href('/admin/files') ?>?picker=1&amp;field=post_image&amp;dir=posts&amp;return_url=<?= rawurlencode(current_url_with_query()) ?>"
                                target="fireball_file_manager"
                            ><i class="ci-folder"></i><?= print_translation('admin_nav_files') ?></a>
                        </div>
                    </div>
                    <div class="fb-post-media-picker__sources">
                        <div class="fb-post-media-picker__source">
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
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts') ?>"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></a>
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
        const pickerNote = pathInput ? String(pathInput.getAttribute('data-file-picker-note') || '') : '';
        const urlNote = pathInput ? String(pathInput.getAttribute('data-file-url-note') || '') : '';
        const emptyNote = String(dropzone.getAttribute('data-file-empty-note') || '');
        const localNote = String(dropzone.getAttribute('data-file-local-note') || '');

        if (!fileInput || !pathInput) {
            return;
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
            localPreviewUrl = file.type && file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : null;
            setPickerPreview(pathInput, {
                value: file.name,
                imageSrc: localPreviewUrl || '',
                note: localNote,
                isLocalFile: true
            });
        }

        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            showLocalFilePreview(file);
        });

        if (urlInput) {
            urlInput.addEventListener('input', syncUrlToPath);
            urlInput.addEventListener('change', syncUrlToPath);
        }

        dropzone.addEventListener('click', function (event) {
            if (event.target.closest('button, label, a, input')) {
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
    });

    consumeStoredFileSelection();
    consumeQueryFileSelection();
});
</script>
