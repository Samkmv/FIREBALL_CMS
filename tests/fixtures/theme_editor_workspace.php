<?php
/** Render the production workspace and shell without database or theme writes. */
$GLOBALS['translations'] = require dirname(__DIR__, 2) . '/app/Languages/ru.php';
function htmlSC($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation($key) { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation($key) { return return_translation($key); }
function base_href($path = '') { return $path; }
function base_url($path = '') { return $path; }
function get_csrf_field() { return '<input type="hidden" name="csrf" value="fixture">'; }
function get_alerts() {}
function view() { return new class {
    public function renderPartial($name, $data = []) {
        if ($name === 'admin/sidebar') return '<div class="p-4"><strong>FIREBALL CMS</strong><hr><p>Обзор</p><p>Внешний вид</p><p>Редактор тем</p></div>';
        if ($name === 'admin/topbar') return '<div class="fb-topbar px-4">FIREBALL CMS · Панель управления</div>';
        if (!in_array($name, ['admin/shell_open', 'admin/shell_close'], true)) return '';
        extract($data); ob_start(); require dirname(__DIR__, 2) . '/app/Views/themes/default/' . $name . '.php'; return ob_get_clean();
    }
}; }
$theme_item = ['slug' => 'default', 'name' => 'Default', 'description' => 'Стандартная тема FIREBALL CMS'];
$themes = [$theme_item, ['slug' => 'custom', 'name' => 'Custom']];
$selected_path = 'templates/layout.php';
$selected_file = ['type' => 'file', 'name' => 'layout.php', 'path' => $selected_path, 'extension' => 'php', 'language' => 'php', 'protected' => true, 'content' => "<?php\n/** Основной шаблон темы */\n?>\n<!DOCTYPE html>\n<html lang=\"ru\">\n<head>\n    <meta charset=\"utf-8\">\n    <title><?= htmlSC(\$title) ?></title>\n</head>\n<body>\n    <?= \$this->partial('header') ?>\n    <main><?= \$this->content ?></main>\n    <?= \$this->partial('footer') ?>\n</body>\n</html>"];
$tree = [['type' => 'directory', 'name' => 'templates', 'path' => 'templates', 'children' => [$selected_file, ['type' => 'file', 'name' => 'home.php', 'path' => 'templates/home.php', 'extension' => 'php']]], ['type' => 'directory', 'name' => 'assets', 'path' => 'assets', 'children' => [['type' => 'directory', 'name' => 'css', 'path' => 'assets/css', 'children' => [['type' => 'file', 'name' => 'style.css', 'path' => 'assets/css/style.css', 'extension' => 'css']]]]], ['type' => 'file', 'name' => 'theme.json', 'path' => 'theme.json', 'extension' => 'json']];
$editor_error = ''; $history = [];
?>
<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php foreach (['admin-theme-editor.css', 'theme.min.css', 'style.css', 'admin-ui.css'] as $css): ?><link rel="stylesheet" href="/assets/default/css/<?= $css ?>"><?php endforeach; ?>
<link rel="stylesheet" href="/assets/default/vendor/monaco-0.55.1/editor.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css">
</head><body>
<?php require dirname(__DIR__, 2) . '/app/Views/themes/default/admin/theme_files.php'; ?>
<script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/admin-theme-editor.js"></script>
</body></html>
