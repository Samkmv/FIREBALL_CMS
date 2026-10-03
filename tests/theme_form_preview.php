<?php
/** Render both actual forms without sessions/database or writes. */
function session() { return new class { public function get($key) { return $GLOBALS['formData'] ?? []; } }; }
function base_href($path = '') { return $path; }
function htmlSC($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation($key) { return $key; }
function print_translation($key) { return $key; }
function get_csrf_field() { return '<input type="hidden" name="csrf" value="fixture">'; }
function get_errors($key) { return ''; }
function get_validation_class($key) { return ''; }
function view() { return new class { public function renderPartial($name, $data = []) { return ''; } }; }
foreach ([false, true] as $is_edit) {
    $theme_item = ['slug' => 'test', 'preview' => 'custom.webp', 'preview_url' => '/themes/test/custom.webp'];
    $formData = [];
    ob_start();
    require dirname(__DIR__) . '/app/Views/themes/default/admin/theme_form.php';
    $html = ob_get_clean();
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    foreach ([
        '//fieldset[@data-theme-preview]' => 1,
        '//input[@name="preview" and @type="hidden" and @value="custom.webp"]' => 1,
        '//input[@name="preview" and @type="text"]' => 0,
        '//input[@name="preview_source"]' => 1,
        '//input[@name="preview_upload"]' => 1,
        '//input[@name="preview_method" and @checked and @value="upload"]' => 1,
        '//input[@name="csrf"]' => 1,
    ] as $query => $expected) {
        if ($xpath->query($query)->length !== $expected) { throw new RuntimeException('Preview form: ' . $query); }
    }
    if (str_contains($html, 'image/svg+xml')) { throw new RuntimeException('Upload must match server format restrictions.'); }
}
echo "Theme preview create/edit forms: one selector, retained filename and CSRF passed.\n";
