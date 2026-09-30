<?php
// Render the actual editor component without booting the CMS or writing to its database.
function htmlSC($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$entity_type = 'post';
$entity_id = 7;
$field_name = 'content';
$field_id = 'test_content';
$validation_class = '';
$editor_id = 'testEditor';
$config = [
    'userId' => 1,
    'labels' => [],
    'previewStyleAssets' => [],
    'blockTypes' => [
        ['machine_name' => 'text', 'title' => 'Text', 'default_content' => ['html' => '']],
        ['machine_name' => 'heading', 'title' => 'Heading', 'default_content' => ['level' => 'h2', 'html' => '']],
        ['machine_name' => 'gallery', 'title' => 'Gallery', 'default_content' => ['items' => []]],
        ['machine_name' => 'table', 'title' => 'Table', 'default_content' => ['rows' => [['', ''], ['', '']], 'header' => true]],
    ],
];
$content = json_encode(['version' => 2, 'blocks' => [
    ['id' => 'first', 'type' => 'text', 'data' => ['html' => 'First paragraph']],
    ['id' => 'second', 'type' => 'heading', 'data' => ['level' => 'h2', 'html' => 'Second heading']],
    ['id' => 'gallery', 'type' => 'gallery', 'data' => ['items' => []]],
]]);
?>
<!doctype html><html><body style="margin:0">
<div class="fb-editor-workspace" data-editor-workspace>
<form class="fb-editor-workspace__form" data-post-autosave data-autosave-url="/save">
<header class="fb-editor-workspace__topbar">
    <button type="button" data-editor-undo>Undo</button>
    <button type="button" data-editor-redo>Redo</button>
</header>
<div class="fb-editor-workspace__body">
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
