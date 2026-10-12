<?php
$project = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) return false;
if ($path === '/notifications/feed') { header('Content-Type: application/json'); echo '{"items":[],"unread":0,"unread_total":0}'; return; }
if (!in_array($path, ['/admin/ui-kit', '/admin/docs'], true)) { http_response_code(404); echo 'Preview only'; return; }
$locale = in_array($_GET['lang'] ?? 'ru', ['ru','en','de','zh-cn'], true) ? ($_GET['lang'] ?? 'ru') : 'ru';
$translations = array_merge(require $project . '/app/Languages/' . $locale . '.php', require $project . '/app/Languages/' . $locale . '/adminui/index.php');
define('VIEWS', $project . '/app/Views');
define('THEME', 'default');
define('MULTILANGS', false);
define('LANGS', ['ru' => ['code' => 'ru'], 'en' => ['code' => 'en']]);
require $project . '/core/View.php';
require $project . '/core/Menu.php';
function htmlSC($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function return_translation($key): string { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation($key): void { echo htmlSC(return_translation($key)); }
function base_href($path = ''): string { return $path ?: '/'; }
function base_url($path = ''): string { return $path ?: '/'; }
function current_locale(): string { return $GLOBALS['locale']; }
function current_path(): string { return $GLOBALS['path']; }
function uri_without_lang(): string { return $GLOBALS['path']; }
function get_user(): array { return ['name' => 'UI Preview', 'login' => 'demo', 'role' => 'admin']; }
function get_user_avatar($avatar, $size): string { return '/assets/img/avatar.jpg'; }
function get_user_role_label($role): string { return 'Demo'; }
function check_admin(): bool { return true; }
function check_creator(): bool { return false; }
function apply_filters($hook, $value, ...$args) { return $value; }
function get_csrf_field(): string { return ''; }
function get_alerts(): void {}
function get_validation_class($field): string { return ''; }
function session() { return new class { function get($key) { return []; } }; }
function view(): FBL\View { static $view; return $view ??= new FBL\View('default'); }
?><!doctype html><html lang="<?= htmlSC($locale) ?>" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin UI catalogue preview</title><script src="/assets/default/js/theme-switcher.js"></script><?php foreach (['/assets/default/icons/cartzilla-icons.min.css', '/assets/default/vendor/choices.js/choices.min.css', '/assets/default/vendor/simplebar/simplebar.min.css', '/assets/default/css/theme.min.css', '/assets/default/css/style.css', '/assets/default/css/admin-ui.css', '/assets/default/css/admin-ui-kit.css'] as $asset): ?><link rel="stylesheet" href="<?= $asset ?>"><?php endforeach; ?></head><body class="fb-admin-body"><script>var baseUrl = "";</script>
<?= view()->render($path === '/admin/docs' ? 'admin/docs_index' : 'admin/ui_kit', [], false) ?>
<?php foreach (['jquery-3.7.1.min.js', '../vendor/choices.js/choices.min.js', '../vendor/simplebar/simplebar.min.js', 'bootstrap-guard.js', 'theme.min.js', 'password-field.js', 'select-init.js', 'datatable.js', 'main.js', 'admin-ui.js', 'admin-ui-kit.js'] as $asset): ?><script src="/assets/default/js/<?= $asset ?>"></script><?php endforeach; ?>
</body></html>
