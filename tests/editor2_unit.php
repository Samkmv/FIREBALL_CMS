<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/config/config.php';
require $root . '/vendor/autoload.php';
require $root . '/helpers/helpers.php';

function editor2_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$state = [
    'version' => 2,
    'blocks' => [
        [
            'id' => 'block-a',
            'type' => 'heading',
            'data' => ['level' => 'h2', 'html' => 'Безопасный заголовок'],
            'settings' => ['anchor' => 'intro'],
        ],
        [
            'id' => 'block-b',
            'type' => 'text',
            'data' => ['html' => '<strong>Текст</strong>'],
            'settings' => [],
        ],
    ],
];
$snapshot = base64_encode((string)json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$content = '<div data-fb-block="heading"><h2>Безопасный заголовок</h2></div>'
    . '<template data-fb-editor-state="2">' . $snapshot . '</template>'
    . '<script>alert(1)</script>'
    . '<img src="javascript:alert(2)" onerror="alert(3)">';
$sanitized = sanitize_content_html($content);

editor2_assert(!str_contains($sanitized, '<script'), 'Server sanitizer kept a script element.');
editor2_assert(!str_contains($sanitized, 'javascript:'), 'Server sanitizer kept an unsafe URL.');
editor2_assert(!str_contains($sanitized, 'onerror'), 'Server sanitizer kept an event handler.');
editor2_assert(str_contains($sanitized, 'data-fb-editor-state="2"'), 'Versioned editor snapshot was removed.');

$repository = new App\Modules\BlockEditor\BlockRepository();
$decodedSnapshot = $repository->decodeBlocks($sanitized);
editor2_assert(count($decodedSnapshot) === 2, 'HTML snapshot did not decode to the expected blocks.');
editor2_assert(($decodedSnapshot[0]['data']['html'] ?? '') === 'Безопасный заголовок', 'Snapshot data changed during decode.');

$decodedJson = $repository->decodeBlocks((string)json_encode($state, JSON_UNESCAPED_UNICODE));
editor2_assert(count($decodedJson) === 2, 'Legacy JSON document did not decode.');

$application = (new ReflectionClass(FBL\Application::class))->newInstanceWithoutConstructor();
$application->hooks = new FBL\Plugins\HookManager();
FBL\Application::$app = $application;

$renderDocument = [
    'version' => 2,
    'blocks' => [
        [
            'id' => 'render-heading',
            'type' => 'heading',
            'data' => ['level' => 'h2', 'html' => 'Renderer'],
            'settings' => ['width' => 'wide', 'hiddenOn' => ['mobile'], 'anchor' => 'render'],
        ],
        [
            'id' => 'render-list',
            'type' => 'bulletList',
            'data' => ['items' => ['One', '<strong>Two</strong>']],
        ],
        [
            'id' => 'render-embed',
            'type' => 'embed',
            'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'caption' => 'Video'],
        ],
    ],
];
$rendered = (new App\Modules\BlockEditor\BlockRenderer())->renderPublicContent(
    (string)json_encode($renderDocument, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
editor2_assert(str_contains($rendered, '<h2>Renderer</h2>'), 'Heading block did not render.');
editor2_assert(str_contains($rendered, '<ul><li>One</li>'), 'List block did not render.');
editor2_assert(str_contains($rendered, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'Trusted embed did not render.');
editor2_assert(str_contains($rendered, 'fb-content-block--width-wide'), 'Public width setting did not render.');
editor2_assert(str_contains($rendered, 'fb-hide-mobile'), 'Public visibility setting did not render.');

$embedWithoutCaption = (new App\Modules\BlockEditor\BlockRenderer())->renderBlock([
    'id' => 'localized-embed',
    'type' => 'embed',
    'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'caption' => ''],
]);
$expectedEmbedTitle = return_translation('editor_block_embed');
$expectedEmbedTitle = $expectedEmbedTitle === 'editor_block_embed' ? 'Embedded content' : $expectedEmbedTitle;
editor2_assert(
    str_contains($embedWithoutCaption, 'title="' . htmlSC($expectedEmbedTitle) . '"'),
    'The public embed accessibility title is not localized.'
);

$newsletterRendered = (new App\Modules\BlockEditor\BlockRenderer())->renderBlock([
    'id' => 'newsletter',
    'type' => 'newsletter',
    'data' => [
        'title' => 'Sign up to our newsletter',
        'text' => 'Receive our latest updates about our products & promotions',
        'buttonText' => 'Subscribe',
        'buttonIcon' => 'ci-mail',
    ],
]);
editor2_assert(
    str_contains($newsletterRendered, 'd-sm-flex align-items-center justify-content-between bg-body-tertiary rounded-4 py-5 px-4 px-md-5'),
    'Newsletter block lost the Cartzilla layout classes.'
);
editor2_assert(str_contains($newsletterRendered, '<h3 class="h5 mb-2"'), 'Newsletter title does not use the public template markup.');
editor2_assert(str_contains($newsletterRendered, '<button type="button" class="btn btn-dark"'), 'Newsletter action does not match the button template.');
editor2_assert(str_contains($newsletterRendered, 'ci-mail fs-base ms-n1 me-2'), 'Newsletter button icon is missing.');
$sanitizedNewsletter = sanitize_content_html($newsletterRendered);
editor2_assert(str_contains($sanitizedNewsletter, 'bg-body-tertiary rounded-4 py-5 px-4 px-md-5'), 'Newsletter template classes were removed by the server sanitizer.');
editor2_assert(str_contains($sanitizedNewsletter, '<button type="button" class="btn btn-dark"'), 'Newsletter button was removed by the server sanitizer.');
$sanitizedUnsafeButton = sanitize_content_html('<button type="submit" formaction="https://example.com" formmethod="post" onclick="alert(1)" name="payload" value="secret">Safe label</button>');
editor2_assert(str_contains($sanitizedUnsafeButton, '<button type="button">Safe label</button>'), 'Content buttons are not normalized to an inert type.');
editor2_assert(!str_contains($sanitizedUnsafeButton, 'formaction'), 'Content button kept a form action.');
editor2_assert(!str_contains($sanitizedUnsafeButton, 'onclick'), 'Content button kept an event handler.');

$socialRendered = (new App\Modules\BlockEditor\BlockRenderer())->renderBlock([
    'id' => 'social',
    'type' => 'social',
    'data' => [
        'items' => [
            ['network' => 'telegram', 'icon' => 'ci-telegram', 'label' => 'Telegram', 'url' => 'https://t.me/fireball'],
            ['network' => 'phone', 'icon' => 'ci-phone', 'label' => 'Позвонить', 'url' => '+7 900 000-00-00'],
            ['network' => 'custom', 'icon' => 'ci-does-not-exist', 'label' => 'Своя сеть', 'url' => 'https://example.com'],
        ],
    ],
]);
editor2_assert(str_contains($socialRendered, 'class="fb-social-buttons"'), 'Social block lost the public theme wrapper.');
editor2_assert(str_contains($socialRendered, 'class="fb-social-buttons__item"'), 'Social links are not rendered as theme buttons.');
editor2_assert(str_contains($socialRendered, 'fb-social-buttons__icon ci-telegram'), 'Social button icon is missing.');
editor2_assert(str_contains($socialRendered, 'target="_blank" rel="noopener noreferrer"'), 'External social button attributes are missing.');
editor2_assert(str_contains($socialRendered, 'href="tel:+79000000000"'), 'Phone social button was not normalized.');
editor2_assert(str_contains($socialRendered, 'fb-social-buttons__icon--svg'), 'Missing social icon did not use the vector fallback.');
$sanitizedSocial = sanitize_content_html($socialRendered);
editor2_assert(str_contains($sanitizedSocial, 'class="fb-social-buttons__item"'), 'Social button theme classes were removed by the server sanitizer.');

$htmlRendered = (new App\Modules\BlockEditor\BlockRenderer())->renderPublicContent((string)json_encode([
    'version' => 2,
    'blocks' => [[
        'id' => 'custom-html',
        'type' => 'html',
        'data' => ['html' => '<div class="custom-card" data-card="1"><button type="submit" onclick="alert(1)">Открыть</button><script>alert(2)</script></div>'],
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
editor2_assert(str_contains($htmlRendered, 'class="custom-card" data-card="1"'), 'HTML block lost safe classes or data attributes.');
editor2_assert(str_contains($htmlRendered, '<button type="button">Открыть</button>'), 'HTML block button was not made inert.');
editor2_assert(!str_contains($htmlRendered, '<script'), 'HTML block kept a script element.');

add_filter('fireball_editor_render_block', static function (mixed $html, array $block): mixed {
    return ($block['type'] ?? '') === 'testPlugin'
        ? '<strong data-plugin-block="1">Plugin</strong>'
        : $html;
});
$pluginRendered = (new App\Modules\BlockEditor\BlockRenderer())->renderBlock([
    'id' => 'plugin',
    'type' => 'testPlugin',
    'data' => [],
]);
editor2_assert(str_contains($pluginRendered, 'data-plugin-block="1"'), 'Server plugin renderer filter did not run.');

$editorSource = (string)file_get_contents($root . '/public/assets/default/js/editor2/editor.js');
$importerSource = (string)file_get_contents($root . '/public/assets/default/js/editor2/importer.js');
$sanitizerSource = (string)file_get_contents($root . '/public/assets/default/js/editor2/sanitizer.js');
$registrySource = (string)file_get_contents($root . '/public/assets/default/js/editor2/registry.js');
$serviceSource = (string)file_get_contents($root . '/app/Modules/BlockEditor/BlockEditorService.php');
$postFormSource = (string)file_get_contents($root . '/app/Views/themes/default/admin/post_form.php');
$editorViewSource = (string)file_get_contents($root . '/app/Modules/BlockEditor/views/editor.php');
$editorStylesSource = (string)file_get_contents($root . '/public/assets/default/css/block-editor.css');
$editorIconFiles = [
    'bold', 'button', 'cursor', 'eraser', 'grip-vertical', 'history', 'inline-code', 'italic',
    'ordered-list', 'paint-bucket', 'placeholder', 'quote', 'redo', 'strikethrough',
    'subscript', 'superscript', 'underline', 'undo',
];

foreach ($editorIconFiles as $editorIconFile) {
    $editorIconSource = (string)file_get_contents($root . '/public/assets/default/icons/editor/' . $editorIconFile . '.svg');
    editor2_assert(str_contains($editorIconSource, '<svg') && str_contains($editorIconSource, 'viewBox="0 0 24 24"'), 'Editor SVG icon is missing or invalid: ' . $editorIconFile);
}

$embedTranslations = [
    'ru' => 'Встраиваемый контент',
    'en' => 'Embedded content',
    'de' => 'Eingebetteter Inhalt',
    'zh-cn' => '嵌入内容',
];
$embedUrlHintTranslations = [
    'ru' => 'Вставьте поддерживаемую ссылку в настройках блока',
    'en' => 'Paste a supported URL in block settings',
    'de' => 'Fügen Sie in den Blockeinstellungen eine unterstützte URL ein',
    'zh-cn' => '请在区块设置中粘贴受支持的 URL',
];
foreach ($embedTranslations as $locale => $expectedEmbedTranslation) {
    $localeTranslations = require $root . '/app/Languages/' . $locale . '.php';
    editor2_assert(
        ($localeTranslations['editor_block_embed'] ?? '') === $expectedEmbedTranslation,
        'Embedded content is not translated for locale: ' . $locale
    );
    editor2_assert(
        ($localeTranslations['editor_embed_url_hint'] ?? '') === $embedUrlHintTranslations[$locale],
        'Embedded content URL hint is not translated for locale: ' . $locale
    );
}

editor2_assert(!str_contains($editorSource, 'execCommand'), 'Editor 2.0 must not use document.execCommand.');
editor2_assert(str_contains($editorSource, 'fireball:post-editor-sync'), 'Legacy editor sync event is missing.');
editor2_assert(str_contains($editorSource, 'fireball:post-editor-serialize'), 'Legacy serialize event is missing.');
editor2_assert(str_contains($importerSource, 'application/x-fireball-blocks+json'), 'Internal block clipboard MIME is missing.');
editor2_assert(str_contains($importerSource, 'isInlineHtmlFragment'), 'Inline rich-text paste detection is missing.');
editor2_assert(str_contains($editorSource, '!isStructuredPlain'), 'Single-line HTML or Markdown can be mistaken for plain inline text.');
editor2_assert(str_contains($editorSource, "(block.type === 'html' || block.type === 'code') && event.target.matches('textarea[data-editor-field]')"), 'HTML and code textareas do not keep native paste behavior.');
editor2_assert(substr_count($editorSource, 'sanitizer.sanitizeHtmlBlock') >= 3, 'HTML block does not use its safe source-preserving sanitizer.');
editor2_assert(str_contains($sanitizerSource, 'function sanitizeHtmlBlock'), 'HTML block sanitizer is missing.');
editor2_assert(str_contains($sanitizerSource, "name.indexOf('data-') === 0"), 'HTML block data attributes are stripped.');
editor2_assert(str_contains($editorSource, 'splitEditableHtmlAtSelection'), 'Smart Paste cannot split a block at the caret.');
editor2_assert(str_contains($editorSource, "this.commit('list-exit'"), 'Empty list items cannot exit to a text block.');
editor2_assert(str_contains($editorSource, "this.commit('list-to-text'"), 'Backspace cannot convert an empty list to text.');
editor2_assert(str_contains($sanitizerSource, "'data-hls-src'"), 'Legacy HLS source attributes are removed before import.');
editor2_assert(str_contains($importerSource, "getAttribute('data-hls-src')"), 'Legacy HLS video URLs are not imported.');
editor2_assert(str_contains($importerSource, "'hlsUrl'"), 'Legacy JSON video URL aliases are not normalized.');
editor2_assert(substr_count($postFormSource, 'data-editor-document-title') === 1, 'The document title field must have a single source of truth.');
editor2_assert(str_contains($postFormSource, 'fb-editor-field--document-title'), 'The document title was not moved to the Document inspector tab.');
editor2_assert(str_contains($editorSource, 'handleFormInvalid'), 'Hidden Document-tab validation cannot reveal invalid fields.');
editor2_assert(str_contains($editorViewSource, 'data-editor-command-close'), 'The editor command palette has no explicit close button.');
editor2_assert(str_contains($editorSource, 'syncCommandPaletteSelection'), 'The editor command palette cannot synchronize keyboard selection.');
editor2_assert(str_contains($editorSource, 'activateCommandPaletteSelection'), 'The selected editor command cannot be activated consistently.');
editor2_assert(str_contains($editorSource, "this.label('embedUrlHint'"), 'The embedded content URL hint still bypasses editor localization.');
editor2_assert(!str_contains($editorSource, "<small>' + escapeAttr(command.keywords || '')"), 'Internal command search keywords are exposed in the command palette UI.');
editor2_assert(str_contains($editorStylesSource, '.fb-editor2__command-list::-webkit-scrollbar'), 'The command palette scrollbar is not integrated with the admin style.');
editor2_assert(str_contains($editorStylesSource, 'Mobile Safari zooms the page'), 'The iOS focus zoom guard is missing.');
editor2_assert(str_contains($editorStylesSource, 'font-size: 16px !important;'), 'Mobile editor controls can still trigger Safari focus zoom.');
editor2_assert(str_contains($editorStylesSource, '.fb-editor2-inspector-field > input'), 'Nested inspector checkboxes can inherit full-width text-input styles.');
editor2_assert(str_contains($editorStylesSource, '.fb-editor2-inspector-field > select'), 'Inspector select styles must target direct controls only.');
editor2_assert(str_contains($editorViewSource, 'fb-editor2__tool-icon--bold'), 'Text formatting still depends on a raw letter instead of the editor SVG set.');
editor2_assert(str_contains($editorViewSource, 'fb-editor2__tool-icon--superscript'), 'Superscript has no semantic vector icon.');
editor2_assert(str_contains($editorStylesSource, '../icons/editor/ordered-list.svg'), 'The missing ordered-list font glyph has no SVG fallback.');
editor2_assert(str_contains($editorStylesSource, '../icons/editor/quote.svg'), 'The missing quote font glyph has no SVG fallback.');
editor2_assert(str_contains($serviceSource, "'button', 'editor_block_button', 'ci-button'"), 'The button block still uses a generic missing icon.');
editor2_assert(str_contains($editorStylesSource, '.fb-editor2__tool > .fb-editor2__tool-icon'), 'Text toolbar icons do not share one normalized size.');
editor2_assert(str_contains($editorStylesSource, 'background-color: var(--fb-color-surface-elevated, var(--fb-editor-panel));'), 'The command palette can become transparent over editor content.');
editor2_assert(str_contains($editorStylesSource, '.fb-editor2__dialog-head:focus-within'), 'The command search has no admin-style rounded focus state.');
editor2_assert(str_contains($registrySource, 'registerBlockType'), 'Public Block API is missing.');
editor2_assert(str_contains($serviceSource, 'fireball_editor_block_types'), 'Server block-type extension filter is missing.');
editor2_assert(str_contains($serviceSource, 'fireball_editor_script_assets'), 'Editor asset extension filter is missing.');
editor2_assert(str_contains($serviceSource, 'fireball_editor_preview_style_assets'), 'Preview style extension filter is missing.');
editor2_assert(str_contains($editorSource, 'document.body.appendChild(this.ui.contextMenu)'), 'The block menu is not portaled out of the scrolling editor.');
editor2_assert(str_contains($editorSource, 'positionContextMenu()'), 'The block menu has no viewport-aware positioning.');
editor2_assert(str_contains($editorSource, 'this.renderNewsletterBlock(block, true)'), 'The editor does not use the shared newsletter renderer.');
editor2_assert(str_contains($editorSource, 'this.renderNewsletterBlock(block, false)'), 'The preview serializer does not use the shared newsletter renderer.');
editor2_assert(str_contains($editorSource, 'this.config.previewStyleAssets'), 'Preview does not load the public theme styles.');
$videoPlanHandlerPosition = strpos($editorSource, "if (target.hasAttribute('data-editor-video-plan'))");
$settingReadPosition = $videoPlanHandlerPosition === false
    ? false
    : strpos($editorSource, "const setting = target.getAttribute('data-editor-setting');", $videoPlanHandlerPosition);
editor2_assert($videoPlanHandlerPosition !== false && $settingReadPosition !== false && $videoPlanHandlerPosition < $settingReadPosition, 'Video plan checkboxes are ignored before their values can be saved.');
editor2_assert(str_contains($editorSource, 'data-editor-video-plans') && str_contains($editorSource, "accessMode === 'plans'"), 'Allowed video plans must only be shown for selected-plan access.');

echo json_encode([
    'status' => 'ok',
    'snapshot_roundtrip' => true,
    'legacy_json' => true,
    'sanitizer' => true,
    'public_renderer' => true,
    'compatibility_events' => true,
    'smart_paste_caret_split' => true,
    'rich_inline_paste' => true,
    'list_keyboard' => true,
    'legacy_video_url' => true,
    'document_title_panel' => true,
    'command_palette_mobile' => true,
    'ios_focus_zoom_guard' => true,
    'semantic_svg_icons' => true,
    'plugin_api' => true,
    'anchored_block_menu' => true,
    'wysiwyg_newsletter' => true,
    'themed_preview' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
