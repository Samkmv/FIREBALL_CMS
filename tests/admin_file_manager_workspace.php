<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function file_manager_workspace_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'admin_file_manager_workspace: ' . $message . PHP_EOL);
        exit(1);
    }
}

$shell = (string)file_get_contents($root . '/app/Views/themes/default/admin/shell_open.php');
$page = (string)file_get_contents($root . '/app/Views/themes/default/admin/files.php');
$browser = (string)file_get_contents($root . '/app/Views/themes/default/admin/file_manager_browser.php');
$controller = (string)file_get_contents($root . '/app/Controllers/FileManagerController.php');
$model = (string)file_get_contents($root . '/app/Models/FileManager.php');
$styles = (string)file_get_contents($root . '/public/assets/default/css/admin-ui.css');
$scripts = (string)file_get_contents($root . '/public/assets/default/js/admin-file-manager.js');

file_manager_workspace_assert(str_contains($shell, '$adminShellContentClass'), 'the admin shell cannot opt into an edge-to-edge workspace');
file_manager_workspace_assert(str_contains($shell, '$adminShellShowHeader'), 'the admin shell cannot hide a redundant page header');
file_manager_workspace_assert(str_contains($page, "'content_class' => 'fb-content--edge-workspace'"), 'the file manager does not use the edge-to-edge shell');
file_manager_workspace_assert(str_contains($styles, '.fb-content--edge-workspace'), 'the edge-to-edge shell has no layout styles');
file_manager_workspace_assert(str_contains($browser, 'data-file-manager-folder-tree'), 'the folder navigation tree is missing');
file_manager_workspace_assert(str_contains($browser, 'data-file-manager-view="list"') && str_contains($browser, 'data-file-manager-view="grid"'), 'list/grid view controls are missing');
file_manager_workspace_assert(str_contains($browser, 'data-file-manager-upload-drop'), 'the upload drop zone is missing');
file_manager_workspace_assert(str_contains($browser, "['type' => 'directory']") && str_contains($browser, "['type' => 'file']"), 'type filter links are missing');
file_manager_workspace_assert(str_contains($page, 'flex-wrap: nowrap !important;') && str_contains($page, 'overflow-x: hidden;'), 'desktop controls or table can overflow the workspace');
file_manager_workspace_assert(str_contains($page, '[data-file-manager-table] col:nth-child(6)') && str_contains($page, 'width: 7rem;'), 'the actions column is not wide enough to align its heading and controls');
file_manager_workspace_assert(str_contains($controller, "'per_page' => 10") && str_contains($controller, 'normalizeTableType'), 'the controller does not preserve the expanded filtered list state');
file_manager_workspace_assert(str_contains($model, "'type_filter' => \$type"), 'the model does not apply the selected type filter');
file_manager_workspace_assert(str_contains($scripts, 'applyViewMode') && str_contains($scripts, 'data-file-manager-upload-drop'), 'workspace interactions are not connected');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require $root . '/app/Languages/' . $locale . '.php';
    foreach (['admin_files_quick_access', 'admin_files_filter_all', 'admin_files_view_grid', 'admin_files_drop_title'] as $key) {
        file_manager_workspace_assert(isset($translations[$key]) && trim((string)$translations[$key]) !== '', "missing {$key} translation for {$locale}");
    }
}

echo json_encode([
    'status' => 'ok',
    'edge_workspace' => true,
    'type_filter' => true,
    'view_modes' => ['list', 'grid'],
    'drop_upload' => true,
    'locales' => 4,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
