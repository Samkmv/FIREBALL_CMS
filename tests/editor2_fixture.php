<?php
// Render the actual editor component without booting the CMS or writing to its database.
function htmlSC($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation($key): string { return (string)$key; }
$workspaceTitle = 'Редактирование записи';
$listUrl = '/admin/posts';
$translateOrFallback = static fn($key, $fallback) => $fallback;
$entity_type = 'post';
$entity_id = 7;
$field_name = 'content';
$field_id = 'test_content';
$validation_class = '';
$editor_id = 'testEditor';
$config = [
    'userId' => 1,
    'fileUploadUrl' => '/admin/block-editor/upload-file',
    'fileUploadExtensions' => ['pdf', 'docx', 'xlsx', 'txt', 'zip'],
    'fileUploadMaxSize' => 50 * 1024 * 1024,
    'labels' => [],
    'fonts' => [['value' => 'Inter', 'label' => 'Inter']],
    'sizes' => [['value' => '12px', 'label' => '12px']],
    'previewStyleAssets' => [],
    'blockTypes' => [
        ['machine_name' => 'text', 'title' => 'Text', 'default_content' => ['html' => '']],
        ['machine_name' => 'heading', 'title' => 'Heading', 'default_content' => ['level' => 'h2', 'html' => '']],
        ['machine_name' => 'gallery', 'title' => 'Gallery', 'default_content' => ['items' => []]],
        ['machine_name' => 'downloads', 'title' => 'Загрузчик', 'icon' => 'ci-download', 'default_content' => ['title' => '', 'description' => '', 'items' => [], 'showIcon' => true, 'showSize' => true]],
        ['machine_name' => 'table', 'title' => 'Table', 'default_content' => ['rows' => [['', ''], ['', '']], 'header' => true]],
        ['machine_name' => 'video', 'title' => 'Видео', 'default_content' => ['src' => '', 'caption' => '']],
    ],
];
$content = json_encode(['version' => 2, 'blocks' => [
    ['id' => 'first', 'type' => 'text', 'data' => ['html' => 'First paragraph']],
    ['id' => 'second', 'type' => 'heading', 'data' => ['level' => 'h2', 'html' => 'Second heading']],
    ['id' => 'gallery', 'type' => 'gallery', 'data' => ['items' => []]],
]]);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"></head><body style="margin:0">
<div class="fb-editor-workspace" data-editor-workspace>
<form class="fb-editor-workspace__form" data-post-autosave data-autosave-url="/save">
<header class="fb-editor-workspace__topbar">
<?php
// Exercise the production header, not a simplified pair of fixture buttons.
$view = file_get_contents(__DIR__ . '/../app/Views/themes/default/admin/post_form.php');
preg_match('/<header class="fb-editor-workspace__topbar">([\s\S]*?)<\/header>/', $view, $header);
eval('?>' . $header[1]);
?>
</header>
<div class="fb-editor-workspace__body">
<aside class="fb-editor-workspace__outline-panel" data-editor-outline-panel>
<div class="fb-editor-workspace__outline" data-editor-outline></div>
</aside>
<div class="fb-editor-workspace__document"><div class="fb-editor-workspace__document-inner">
<?php require __DIR__ . '/../app/Modules/BlockEditor/views/editor.php'; ?>
</div></div>
<aside class="fb-editor-workspace__inspector-panel" data-editor-inspector-panel>
<header class="fb-editor-workspace__panel-head"><strong>Settings</strong></header>
<div class="fb-editor-workspace__inspector-tabs">
<button type="button" data-editor-inspector-tab="block">Block</button>
<button type="button" data-editor-inspector-tab="document">Document</button>
</div>
<div class="fb-editor-workspace__inspector" data-editor-inspector-tab-panel="block" data-editor-inspector></div>
<div class="fb-editor-workspace__document-settings" data-editor-inspector-tab-panel="document" hidden>
<input name="title" data-editor-document-title value="Title">
</div>
</aside>
</div><footer class="fb-editor-workspace__statusbar">Status</footer>
</form></div>
</body></html>
