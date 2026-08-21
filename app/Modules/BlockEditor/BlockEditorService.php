<?php

namespace App\Modules\BlockEditor;

final class BlockEditorService
{
    public const ALLOWED_ENTITY_TYPES = ['post', 'page'];

    public static function styleAssets(): array
    {
        $assets = [
            base_url('/assets/default/css/block-editor.css?v=' . filemtime(WWW . '/assets/default/css/block-editor.css')),
        ];

        return array_values((array)apply_filters('fireball_editor_style_assets', $assets));
    }

    public static function scriptAssets(): array
    {
        $editorAssets = [
            '/assets/default/js/editor2/registry.js',
            '/assets/default/js/editor2/sanitizer.js',
            '/assets/default/js/editor2/importer.js',
            '/assets/default/js/editor2/history.js',
            '/assets/default/js/editor2/editor.js',
        ];
        $assets = [];

        foreach ($editorAssets as $asset) {
            $assets[] = base_url($asset . '?v=' . filemtime(WWW . $asset));
        }

        $assets[] = base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js'));

        return array_values((array)apply_filters('fireball_editor_script_assets', $assets));
    }

    public static function previewStyleAssets(): array
    {
        $assets = [
            base_url('/assets/default/icons/cartzilla-icons.min.css'),
            base_url('/assets/default/css/theme.min.css'),
            base_url('/assets/default/css/style.css?v=' . filemtime(WWW . '/assets/default/css/style.css')),
        ];

        return array_values((array)apply_filters('fireball_editor_preview_style_assets', $assets));
    }

    public function render(array $options): string
    {
        $entityType = $this->normalizeEntityType((string)($options['entity_type'] ?? 'post'));
        $entityId = max(0, (int)($options['entity_id'] ?? 0));
        $fieldName = (string)($options['field_name'] ?? 'content');
        $fieldId = (string)($options['field_id'] ?? 'post_content');
        $content = (string)($options['content'] ?? '');
        $validationClass = (string)($options['validation_class'] ?? '');
        $editorId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($options['editor_id'] ?? 'blockEditor'));
        $editorId = $editorId !== '' ? $editorId : 'blockEditor';
        $defaultDirectory = (string)($options['default_directory'] ?? ($entityType === 'page' ? 'pages' : 'posts'));

        return $this->renderModuleView('editor', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_name' => $fieldName,
            'field_id' => $fieldId,
            'content' => $content,
            'validation_class' => $validationClass,
            'editor_id' => $editorId,
            'config' => $this->buildConfig($entityType, $defaultDirectory),
            'close_label' => $this->translateOrFallback('admin_btn_close', 'Close'),
            'delete_title' => $this->translateOrFallback('admin_post_builder_delete_confirm_title', 'Remove block?'),
            'delete_text' => $this->translateOrFallback('admin_post_builder_delete_confirm_text', 'This action cannot be undone. The block will be removed from the content.'),
        ]);
    }

    public function buildConfig(string $entityType = 'post', string $defaultDirectory = 'posts'): array
    {
        $entityType = $this->normalizeEntityType($entityType);
        $currentUser = get_user();
        $currentUser = is_array($currentUser) ? $currentUser : [];

        $config = [
            'version' => 2,
            'entityType' => $entityType,
            'userId' => (int)($currentUser['id'] ?? 0),
            'fileManagerUrl' => base_href('/admin/files'),
            'defaultDirectory' => $defaultDirectory,
            'previewStyleAssets' => self::previewStyleAssets(),
            'blockTypes' => array_values($this->blockTypes()),
            'socialNetworks' => array_map(
                static fn(array $option, string $network): array => [
                    'value' => $network,
                    'label' => (string)($option['label'] ?? $network),
                    'icon' => (string)($option['icon'] ?? 'ci-globe'),
                    'placeholder' => (string)($option['placeholder'] ?? ''),
                ],
                site_social_network_options(),
                array_keys(site_social_network_options())
            ),
            'fonts' => [
                ['value' => 'Inter', 'label' => 'Inter'],
                ['value' => 'Arial', 'label' => 'Arial'],
                ['value' => 'Roboto', 'label' => 'Roboto'],
                ['value' => 'Helvetica', 'label' => 'Helvetica'],
                ['value' => 'Georgia', 'label' => 'Georgia'],
                ['value' => 'Times New Roman', 'label' => 'Times New Roman'],
                ['value' => 'Courier New', 'label' => 'Courier New'],
                ['value' => 'monospace', 'label' => 'Monospace'],
            ],
            'sizes' => [
                ['value' => '12px', 'label' => '12px'],
                ['value' => '14px', 'label' => '14px'],
                ['value' => '16px', 'label' => '16px'],
                ['value' => '18px', 'label' => '18px'],
                ['value' => '20px', 'label' => '20px'],
                ['value' => '24px', 'label' => '24px'],
                ['value' => '28px', 'label' => '28px'],
                ['value' => '32px', 'label' => '32px'],
                ['value' => '40px', 'label' => '40px'],
            ],
            'labels' => $this->labels(),
            'features' => [
                'smartPaste' => true,
                'localRecovery' => true,
                'commandPalette' => true,
                'documentMode' => true,
                'structureMode' => true,
            ],
        ];

        return (array)apply_filters('fireball_editor_config', $config, $entityType);
    }

    public function blockTypes(): array
    {
        $types = [
            'text' => $this->blockType('text', 'admin_post_builder_block_text', 'ci-type', ['html' => '']),
            'heading' => $this->blockType('heading', 'admin_post_builder_block_heading', 'ci-hash', ['level' => 'h2', 'html' => '']),
            'bulletList' => $this->blockType('bulletList', 'editor_block_bullet_list', 'ci-list', ['items' => ['']]),
            'orderedList' => $this->blockType('orderedList', 'editor_block_ordered_list', 'ci-list-ordered', ['items' => ['']]),
            'checklist' => $this->blockType('checklist', 'editor_block_checklist', 'ci-check-square', ['items' => [['text' => '', 'checked' => false]]]),
            'quote' => $this->blockType('quote', 'editor_block_quote', 'ci-quote', ['html' => '', 'cite' => '']),
            'image' => $this->blockType('image', 'admin_post_builder_block_image', 'ci-image', [
                'src' => '',
                'alt' => '',
                'title' => '',
                'caption' => '',
                'link' => '',
                'lazy' => true,
                'width' => 0,
                'height' => 0,
                'crop' => 'none',
            ]),
            'gallery' => $this->blockType('gallery', 'editor_block_gallery', 'ci-grid', ['items' => []]),
            'video' => $this->blockType('video', 'admin_post_builder_block_video', 'ci-video', [
                'src' => '',
                'poster' => '',
                'caption' => '',
                'autoplay' => false,
                'muted' => false,
                'controls' => true,
                'loop' => false,
                'hls' => false,
                'aspectRatio' => '16:9',
            ]),
            'audio' => $this->blockType('audio', 'admin_post_builder_block_audio', 'ci-music', ['src' => '', 'caption' => '']),
            'table' => $this->blockType('table', 'editor_block_table', 'ci-table', [
                'rows' => [['', ''], ['', '']],
                'header' => true,
                'bordered' => true,
                'responsive' => true,
            ]),
            'html' => $this->blockType('html', 'admin_post_builder_block_html', 'ci-layout', ['html' => '']),
            'social' => $this->blockType('social', 'admin_post_builder_block_social', 'ci-share-2', ['items' => []]),
            'slider' => $this->blockType('slider', 'admin_post_builder_block_slider', 'ci-sliders', ['items' => []]),
            'alert' => $this->blockType('alert', 'admin_post_builder_block_alert', 'ci-bell', [
                'variant' => 'primary',
                'icon' => 'ci-bell',
                'title' => $this->translateOrFallback('admin_post_builder_alert_default_title', 'Notice'),
                'text' => $this->translateOrFallback('admin_post_builder_alert_default_text', 'Add the notification text.'),
            ]),
            'newsletter' => $this->blockType('newsletter', 'admin_post_builder_block_newsletter', 'ci-mail', [
                'title' => $this->translateOrFallback('admin_post_builder_newsletter_default_title', 'Sign up to our newsletter'),
                'text' => $this->translateOrFallback('admin_post_builder_newsletter_default_text', 'Receive our latest updates about our products & promotions'),
                'buttonText' => $this->translateOrFallback('admin_post_builder_newsletter_default_button', 'Subscribe'),
                'buttonUrl' => '',
                'buttonIcon' => 'ci-mail',
            ]),
            'code' => $this->blockType('code', 'admin_post_builder_block_code', 'ci-code', ['language' => 'html', 'code' => '']),
            'divider' => $this->blockType('divider', 'editor_block_divider', 'ci-minus', ['style' => 'solid']),
            'faq' => $this->blockType('faq', 'editor_block_faq', 'ci-help-circle', ['items' => [['question' => '', 'answer' => '']]]),
            'button' => $this->blockType('button', 'editor_block_button', 'ci-button', ['text' => 'Button', 'url' => '', 'style' => 'primary', 'align' => 'left']),
            'embed' => $this->blockType('embed', 'editor_block_embed', 'ci-external-link', ['url' => '', 'caption' => '']),
        ];

        $filtered = apply_filters('fireball_editor_block_types', $types);

        return is_array($filtered) ? $filtered : $types;
    }

    public function normalizeEntityType(string $entityType): string
    {
        return in_array($entityType, self::ALLOWED_ENTITY_TYPES, true) ? $entityType : 'post';
    }

    public function isAllowedBlockType(string $blockType): bool
    {
        return array_key_exists($blockType, $this->blockTypes());
    }

    public function validateContentJson(string $content): bool
    {
        $content = trim($content);
        if ($content === '' || $content[0] !== '{') {
            return true;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return false;
        }

        if (!isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return false;
        }

        foreach ($decoded['blocks'] as $block) {
            if (!is_array($block)) {
                return false;
            }
            $type = (string)($block['type'] ?? '');
            if ($type === '' || !$this->isAllowedBlockType($type)) {
                return false;
            }
        }

        return true;
    }

    protected function labels(): array
    {
        $keys = [
            'builderHint' => 'admin_post_builder_hint',
            'addText' => 'admin_post_builder_add_text',
            'addHeading' => 'admin_post_builder_add_heading',
            'addImage' => 'admin_post_builder_add_image',
            'addVideo' => 'admin_post_builder_add_video',
            'addAudio' => 'admin_post_builder_add_audio',
            'addHtml' => 'admin_post_builder_add_html',
            'addSocial' => 'admin_post_builder_add_social',
            'addSlider' => 'admin_post_builder_add_slider',
            'addAlert' => 'admin_post_builder_add_alert',
            'addNewsletter' => 'admin_post_builder_add_newsletter',
            'addCode' => 'admin_post_builder_add_code',
            'textBlock' => 'admin_post_builder_block_text',
            'headingBlock' => 'admin_post_builder_block_heading',
            'imageBlock' => 'admin_post_builder_block_image',
            'videoBlock' => 'admin_post_builder_block_video',
            'audioBlock' => 'admin_post_builder_block_audio',
            'htmlBlock' => 'admin_post_builder_block_html',
            'socialBlock' => 'admin_post_builder_block_social',
            'sliderBlock' => 'admin_post_builder_block_slider',
            'alertBlock' => 'admin_post_builder_block_alert',
            'newsletterBlock' => 'admin_post_builder_block_newsletter',
            'codeBlock' => 'admin_post_builder_block_code',
            'moveUp' => 'admin_post_builder_move_up',
            'moveDown' => 'admin_post_builder_move_down',
            'remove' => 'admin_post_builder_remove',
            'hide' => 'admin_post_builder_hide',
            'show' => 'admin_post_builder_show',
            'hidden' => 'admin_post_builder_hidden',
            'duplicate' => 'admin_post_builder_duplicate',
            'drag' => 'admin_post_builder_drag',
            'chooseFile' => 'admin_post_builder_choose_file',
            'sourceLink' => 'admin_post_builder_source_link',
            'imageAlt' => 'admin_post_builder_image_alt',
            'imageCaption' => 'admin_post_builder_image_caption',
            'imageLink' => 'admin_post_builder_image_link',
            'videoPoster' => 'admin_post_builder_video_poster',
            'videoCaption' => 'admin_post_builder_video_caption',
            'audioCaption' => 'admin_post_builder_audio_caption',
            'headingLevel' => 'admin_post_builder_heading_level',
            'codeLanguage' => 'admin_post_builder_code_language',
            'htmlPlaceholder' => 'admin_post_builder_html_placeholder',
            'htmlPreview' => 'admin_post_builder_html_preview',
            'socialNetwork' => 'admin_post_builder_social_network',
            'socialIcon' => 'admin_post_builder_social_icon',
            'socialCustom' => 'admin_post_builder_social_custom',
            'socialGlobe' => 'admin_post_builder_social_globe',
            'socialShare' => 'admin_post_builder_social_share',
            'socialExternalLink' => 'admin_post_builder_social_external_link',
            'socialPhone' => 'admin_post_builder_social_phone',
            'socialMessage' => 'admin_post_builder_social_message',
            'socialLabel' => 'admin_post_builder_social_label',
            'socialUrl' => 'admin_post_builder_social_url',
            'socialAddItem' => 'admin_post_builder_social_add_item',
            'socialRemoveItem' => 'admin_post_builder_social_remove_item',
            'socialItemsHint' => 'admin_post_builder_social_items_hint',
            'codePlaceholder' => 'admin_post_builder_code_placeholder',
            'textPlaceholder' => 'admin_post_builder_text_placeholder',
            'headingPlaceholder' => 'admin_post_builder_heading_placeholder',
            'font' => 'admin_post_builder_font',
            'size' => 'admin_post_builder_size',
            'textColor' => 'admin_post_builder_text_color',
            'background' => 'admin_post_builder_background',
            'bold' => 'admin_post_builder_bold',
            'italic' => 'admin_post_builder_italic',
            'underline' => 'admin_post_builder_underline',
            'alignLeft' => 'admin_post_builder_align_left',
            'alignCenter' => 'admin_post_builder_align_center',
            'alignRight' => 'admin_post_builder_align_right',
            'link' => 'admin_post_builder_link',
            'unlink' => 'admin_post_builder_unlink',
            'linkPrompt' => 'admin_post_builder_link_prompt',
            'empty' => 'admin_post_builder_empty',
            'bulletList' => 'admin_post_builder_bullet_list',
            'orderedList' => 'admin_post_builder_ordered_list',
            'clearFormatting' => 'admin_post_builder_clear_formatting',
            'quote' => 'admin_post_builder_quote',
            'inserter' => 'admin_post_builder_inserter',
            'inspector' => 'admin_post_builder_inspector',
            'canvasTitle' => 'admin_post_builder_canvas_title',
            'addBlock' => 'admin_post_builder_add_block',
            'outline' => 'admin_post_builder_outline',
            'selectBlock' => 'admin_post_builder_select_block',
            'blockSettings' => 'admin_post_builder_block_settings',
            'contentSettings' => 'admin_post_builder_content_settings',
            'mediaSettings' => 'admin_post_builder_media_settings',
            'sliderItems' => 'admin_post_builder_slider_items',
            'sliderAddItem' => 'admin_post_builder_slider_add_item',
            'sliderRemoveItem' => 'admin_post_builder_slider_remove_item',
            'sliderImage' => 'admin_post_builder_slider_image',
            'sliderAspectRatio' => 'admin_post_builder_slider_aspect_ratio',
            'sliderShowBullets' => 'admin_post_builder_slider_show_bullets',
            'sliderShowArrows' => 'admin_post_builder_slider_show_arrows',
            'sliderTitle' => 'admin_post_builder_slider_title',
            'sliderText' => 'admin_post_builder_slider_text',
            'sliderAlt' => 'admin_post_builder_slider_alt',
            'sliderSlide' => 'admin_post_builder_slider_slide',
            'sliderPrev' => 'admin_post_builder_slider_prev',
            'sliderNext' => 'admin_post_builder_slider_next',
            'alertVariant' => 'admin_post_builder_alert_variant',
            'alertIcon' => 'admin_post_builder_alert_icon',
            'alertTitle' => 'admin_post_builder_alert_title',
            'alertText' => 'admin_post_builder_alert_text',
            'alertPrimary' => 'admin_post_builder_alert_primary',
            'alertSecondary' => 'admin_post_builder_alert_secondary',
            'alertSuccess' => 'admin_post_builder_alert_success',
            'alertDanger' => 'admin_post_builder_alert_danger',
            'alertWarning' => 'admin_post_builder_alert_warning',
            'alertInfo' => 'admin_post_builder_alert_info',
            'alertLight' => 'admin_post_builder_alert_light',
            'alertDark' => 'admin_post_builder_alert_dark',
            'alertDefaultTitle' => 'admin_post_builder_alert_default_title',
            'alertDefaultText' => 'admin_post_builder_alert_default_text',
            'newsletterTitle' => 'admin_post_builder_newsletter_title',
            'newsletterText' => 'admin_post_builder_newsletter_text',
            'newsletterButton' => 'admin_post_builder_newsletter_button',
            'newsletterUrl' => 'admin_post_builder_newsletter_url',
            'newsletterIcon' => 'admin_post_builder_newsletter_icon',
            'newsletterDefaultTitle' => 'admin_post_builder_newsletter_default_title',
            'newsletterDefaultText' => 'admin_post_builder_newsletter_default_text',
            'newsletterDefaultButton' => 'admin_post_builder_newsletter_default_button',
            'blockCount' => 'admin_post_builder_block_count',
            'settings' => 'admin_post_builder_block_settings',
        ];

        $labels = [];
        foreach ($keys as $name => $translationKey) {
            $labels[$name] = return_translation($translationKey);
        }

        $labels['style'] = $this->translateOrFallback('admin_post_builder_style', 'Style');
        $labels['moreStyles'] = $this->translateOrFallback('admin_post_builder_more_styles', 'Font, size and colors');
        $labels['inlineSettingsHint'] = $this->translateOrFallback('admin_post_builder_inline_settings_hint', 'This block is edited directly in the content area.');
        $labels['htmlHint'] = $this->translateOrFallback('admin_post_builder_html_hint', 'Unsafe tags and inline handlers will be removed on save.');
        $labels['deleteModalTitle'] = $this->translateOrFallback('admin_post_builder_delete_confirm_title', 'Remove block?');
        $labels['deleteModalText'] = $this->translateOrFallback('admin_post_builder_delete_confirm_text', 'This action cannot be undone. The block will be removed from the content.');
        $labels['manageOrder'] = $this->translateOrFallback('admin_post_builder_manage_order', 'Управление порядком');
        $labels['orderModalTitle'] = $this->translateOrFallback('admin_post_builder_order_modal_title', 'Порядок блоков');
        $labels['orderEmpty'] = $this->translateOrFallback('admin_post_builder_order_empty', 'Блоков пока нет.');
        $labels['orderSave'] = $this->translateOrFallback('admin_btn_save', 'Save');
        $labels['close'] = $this->translateOrFallback('admin_btn_close', 'Close');
        $labels += [
            'documentMode' => $this->translateOrFallback('editor_mode_document', 'Document'),
            'structureMode' => $this->translateOrFallback('editor_mode_structure', 'Structure'),
            'outline' => $this->translateOrFallback('editor_outline', 'Outline'),
            'blockTab' => $this->translateOrFallback('editor_tab_block', 'Block'),
            'documentTab' => $this->translateOrFallback('editor_tab_document', 'Document'),
            'saved' => $this->translateOrFallback('editor_saved', 'Saved'),
            'saving' => $this->translateOrFallback('editor_saving', 'Saving…'),
            'saveError' => $this->translateOrFallback('editor_save_error', 'Could not save'),
            'preview' => $this->translateOrFallback('editor_preview', 'Preview'),
            'saveDraft' => $this->translateOrFallback('editor_save_draft', 'Save draft'),
            'publish' => $this->translateOrFallback('editor_publish', 'Publish'),
            'focusMode' => $this->translateOrFallback('editor_focus_mode', 'Focus mode'),
            'fullscreen' => $this->translateOrFallback('editor_fullscreen', 'Fullscreen'),
            'documentSettings' => $this->translateOrFallback('editor_document_settings', 'Document settings'),
            'commandSearch' => $this->translateOrFallback('editor_command_search', 'Search commands…'),
            'slashHint' => $this->translateOrFallback('editor_slash_hint', 'Type / for commands'),
            'searchReplace' => $this->translateOrFallback('editor_search_replace', 'Search and replace'),
            'wordCount' => $this->translateOrFallback('editor_word_count', 'words'),
            'blockCountLabel' => $this->translateOrFallback('editor_block_count', 'blocks'),
            'recoveryTitle' => $this->translateOrFallback('editor_recovery_title', 'Restore local version?'),
            'recoveryText' => $this->translateOrFallback('editor_recovery_text', 'A newer local version of this document was found.'),
            'restore' => $this->translateOrFallback('editor_restore', 'Restore'),
            'discard' => $this->translateOrFallback('editor_discard', 'Discard'),
            'unsavedConfirm' => $this->translateOrFallback('editor_unsaved_confirm', 'There are unsaved changes. Leave the editor?'),
            'noBlocks' => $this->translateOrFallback('editor_no_blocks', 'Start writing or press / to add a block.'),
            'copy' => $this->translateOrFallback('editor_copy', 'Copy'),
            'cut' => $this->translateOrFallback('editor_cut', 'Cut'),
            'paste' => $this->translateOrFallback('editor_paste', 'Paste'),
            'more' => $this->translateOrFallback('editor_more', 'More'),
            'undo' => $this->translateOrFallback('editor_undo', 'Undo'),
            'redo' => $this->translateOrFallback('editor_redo', 'Redo'),
            'strikethrough' => $this->translateOrFallback('editor_strikethrough', 'Strikethrough'),
            'superscript' => $this->translateOrFallback('editor_superscript', 'Superscript'),
            'subscript' => $this->translateOrFallback('editor_subscript', 'Subscript'),
            'inlineCode' => $this->translateOrFallback('editor_inline_code', 'Inline code'),
            'outdent' => $this->translateOrFallback('editor_outdent', 'Decrease indent'),
            'indent' => $this->translateOrFallback('editor_indent', 'Increase indent'),
            'checklist' => $this->translateOrFallback('editor_block_checklist', 'Checklist'),
            'quotePlaceholder' => $this->translateOrFallback('editor_quote_placeholder', 'Quote'),
            'authorPlaceholder' => $this->translateOrFallback('editor_author_placeholder', 'Author'),
            'advanced' => $this->translateOrFallback('editor_advanced', 'Advanced'),
            'contentWidth' => $this->translateOrFallback('editor_content_width', 'Content width'),
            'widthDefault' => $this->translateOrFallback('editor_width_default', 'Default'),
            'widthWide' => $this->translateOrFallback('editor_width_wide', 'Wide'),
            'widthFull' => $this->translateOrFallback('editor_width_full', 'Full width'),
            'topSpacing' => $this->translateOrFallback('editor_top_spacing', 'Top spacing'),
            'bottomSpacing' => $this->translateOrFallback('editor_bottom_spacing', 'Bottom spacing'),
            'alignment' => $this->translateOrFallback('editor_alignment', 'Alignment'),
            'alignJustify' => $this->translateOrFallback('editor_align_justify', 'Justify'),
            'lineHeight' => $this->translateOrFallback('editor_line_height', 'Line height'),
            'firstLineIndent' => $this->translateOrFallback('editor_first_line_indent', 'First line indent'),
            'cssClass' => $this->translateOrFallback('editor_css_class', 'CSS class'),
            'anchor' => $this->translateOrFallback('editor_anchor', 'Anchor'),
            'hideOnDevices' => $this->translateOrFallback('editor_hide_on_devices', 'Hide on devices'),
            'desktop' => $this->translateOrFallback('editor_device_desktop', 'Desktop'),
            'tablet' => $this->translateOrFallback('editor_device_tablet', 'Tablet'),
            'mobile' => $this->translateOrFallback('editor_device_mobile', 'Mobile'),
            'caption' => $this->translateOrFallback('editor_caption', 'Caption'),
            'embedTitle' => $this->translateOrFallback('editor_block_embed', 'Embedded content'),
            'embedUrlHint' => $this->translateOrFallback('editor_embed_url_hint', 'Paste a supported URL in block settings'),
            'rows' => $this->translateOrFallback('editor_rows', 'Rows'),
            'columns' => $this->translateOrFallback('editor_columns', 'Columns'),
            'headerRow' => $this->translateOrFallback('editor_header_row', 'Header row'),
            'variant' => $this->translateOrFallback('editor_variant', 'Variant'),
            'buttonText' => $this->translateOrFallback('editor_button_text', 'Button text'),
            'contentBlock' => $this->translateOrFallback('editor_content_block', 'Content block'),
            'noCommands' => $this->translateOrFallback('editor_no_commands', 'No commands found'),
            'addAbove' => $this->translateOrFallback('editor_add_above', 'Add block above'),
            'addBelow' => $this->translateOrFallback('editor_add_below', 'Add block below'),
            'saveTemplate' => $this->translateOrFallback('editor_save_template', 'Save as template'),
            'templateName' => $this->translateOrFallback('editor_template_name', 'Template name'),
            'deleteConfirm' => $this->translateOrFallback('editor_delete_confirm', 'Delete selected blocks?'),
            'insertBlock' => $this->translateOrFallback('editor_insert_block', 'Insert'),
            'goToBlock' => $this->translateOrFallback('editor_go_to_block', 'Go to block'),
            'importMarkdown' => $this->translateOrFallback('editor_import_markdown', 'Import Markdown'),
            'exportMarkdown' => $this->translateOrFallback('editor_export_markdown', 'Export Markdown'),
            'importMarkdownPrompt' => $this->translateOrFallback('editor_import_markdown_prompt', 'Paste Markdown'),
            'markdownCopied' => $this->translateOrFallback('editor_markdown_copied', 'Markdown copied'),
            'previewRight' => $this->translateOrFallback('editor_preview_right', 'Preview on the right'),
            'previewNew' => $this->translateOrFallback('editor_preview_new', 'Preview in a new tab'),
            'search' => $this->translateOrFallback('editor_search', 'Search'),
            'replace' => $this->translateOrFallback('editor_replace', 'Replace'),
            'replaceAll' => $this->translateOrFallback('editor_replace_all', 'Replace all'),
            'previous' => $this->translateOrFallback('editor_previous', 'Previous'),
            'next' => $this->translateOrFallback('editor_next', 'Next'),
            'matchCase' => $this->translateOrFallback('editor_match_case', 'Match case'),
            'characterCount' => $this->translateOrFallback('editor_character_count', 'characters'),
            'readingMinutes' => $this->translateOrFallback('editor_reading_minutes', 'min read'),
            'lastSaved' => $this->translateOrFallback('editor_last_saved', 'Last saved'),
            'editorLoadError' => $this->translateOrFallback('editor_load_error', 'The editor could not open this document. The original content is preserved below.'),
            'imageTitle' => $this->translateOrFallback('editor_image_title', 'Image title'),
            'lazyLoading' => $this->translateOrFallback('editor_lazy_loading', 'Lazy loading'),
            'imageWidth' => $this->translateOrFallback('editor_image_width', 'Width'),
            'imageHeight' => $this->translateOrFallback('editor_image_height', 'Height'),
            'imageCrop' => $this->translateOrFallback('editor_image_crop', 'Crop'),
            'videoAutoplay' => $this->translateOrFallback('editor_video_autoplay', 'Autoplay'),
            'videoMuted' => $this->translateOrFallback('editor_video_muted', 'Muted'),
            'videoControls' => $this->translateOrFallback('editor_video_controls', 'Show controls'),
            'videoLoop' => $this->translateOrFallback('editor_video_loop', 'Loop'),
            'videoHls' => $this->translateOrFallback('editor_video_hls', 'HLS stream'),
            'aspectRatio' => $this->translateOrFallback('editor_aspect_ratio', 'Aspect ratio'),
            'tableBordered' => $this->translateOrFallback('editor_table_bordered', 'Borders'),
            'tableResponsive' => $this->translateOrFallback('editor_table_responsive', 'Responsive'),
            'moveUp' => $this->translateOrFallback('admin_post_builder_move_up', 'Move up'),
            'moveDown' => $this->translateOrFallback('admin_post_builder_move_down', 'Move down'),
            'duplicate' => $this->translateOrFallback('admin_post_builder_duplicate', 'Duplicate'),
            'remove' => $this->translateOrFallback('admin_post_builder_remove', 'Remove'),
            'hide' => $this->translateOrFallback('admin_post_builder_hide', 'Hide'),
            'show' => $this->translateOrFallback('admin_post_builder_show', 'Show'),
        ];

        return $labels;
    }

    protected function blockType(string $name, string $titleKey, string $icon, array $defaultContent): array
    {
        $title = $this->translateOrFallback($titleKey, ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name));

        return [
            'machine_name' => $name,
            'title' => $title,
            'icon' => $icon,
            'template_admin' => $name,
            'template_public' => $name,
            'default_content' => $defaultContent,
            'validation_rules' => [],
            'supports' => [
                'anchor' => true,
                'className' => true,
                'visibility' => true,
                'spacing' => true,
            ],
        ];
    }

    protected function renderModuleView(string $view, array $data): string
    {
        extract($data);
        $viewFile = __DIR__ . '/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            return '';
        }

        ob_start();
        require $viewFile;
        return ob_get_clean();
    }

    protected function translateOrFallback(string $key, string $fallback): string
    {
        $value = return_translation($key);
        return $value === $key ? $fallback : $value;
    }
}
