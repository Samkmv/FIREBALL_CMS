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

editor2_assert(!str_contains($editorSource, 'execCommand'), 'Editor 2.0 must not use document.execCommand.');
editor2_assert(str_contains($editorSource, 'fireball:post-editor-sync'), 'Legacy editor sync event is missing.');
editor2_assert(str_contains($editorSource, 'fireball:post-editor-serialize'), 'Legacy serialize event is missing.');
editor2_assert(str_contains($importerSource, 'application/x-fireball-blocks+json'), 'Internal block clipboard MIME is missing.');
editor2_assert(str_contains($importerSource, 'isInlineHtmlFragment'), 'Inline rich-text paste detection is missing.');
editor2_assert(str_contains($editorSource, '!isStructuredPlain'), 'Single-line HTML or Markdown can be mistaken for plain inline text.');
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
editor2_assert(str_contains($editorStylesSource, 'Mobile Safari zooms the page'), 'The iOS focus zoom guard is missing.');
editor2_assert(str_contains($editorStylesSource, 'font-size: 16px !important;'), 'Mobile editor controls can still trigger Safari focus zoom.');
editor2_assert(str_contains($registrySource, 'registerBlockType'), 'Public Block API is missing.');
editor2_assert(str_contains($serviceSource, 'fireball_editor_block_types'), 'Server block-type extension filter is missing.');
editor2_assert(str_contains($serviceSource, 'fireball_editor_script_assets'), 'Editor asset extension filter is missing.');

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
    'plugin_api' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
