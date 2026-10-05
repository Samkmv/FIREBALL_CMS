<?php
declare(strict_types=1);
// CLI-only fixture. Renders the real CMS table, cards, pagination and confirmation modal.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$page = max(1, (int)($argv[2] ?? 1));
$removed = json_decode($argv[3] ?? '[]', true) ?: [];
$translations = require $root . '/app/Languages/' . $locale . '.php';
define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function base_href(string $path): string { return ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $path; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function abort(string $error = '', int $code = 404): never { throw new RuntimeException($error ?: 'Aborted', $code); }
function request(): object { return new class {
    public string $uri;
    public function __construct() { $this->uri = base_href('/admin/settings/pwa') . '?devices_page=' . $GLOBALS['page']; }
    public function get(string $key, mixed $default = null): mixed { return $key === 'devices_page' ? $GLOBALS['page'] : $default; }
}; }
function view(): object { return new class {
    public function renderPartial(string $name, array $data = []): string {
        extract($data, EXTR_SKIP);
        ob_start();
        require $GLOBALS['root'] . '/app/Views/themes/default/' . $name . '.php';
        return ob_get_clean();
    }
}; }
$devices = [];
for ($id = 1; $id <= 126; $id++) {
    if (in_array($id, $removed, true)) continue;
    $devices[] = [
        'id' => $id, 'user_id' => 7 + intdiv($id, 20), 'is_active' => $id % 3 === 0 ? 0 : 1,
        'platform' => $id % 2 ? 'iOS' : 'Android', 'browser' => $id % 2 ? 'Safari' : 'Chrome',
        'user_agent' => $id % 2 ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148' : 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36',
        'last_seen_at' => '2026-10-05 12:00:00', 'revoked_at' => $id % 3 === 0 ? '2026-10-01 12:00:00' : null,
    ];
}
$pagination = new FBL\Pagination(count($devices), 20, 2, 7, 'pagination/base', 'devices_page');
echo view()->renderPartial('admin/partials/pwa_devices', [
    'devices' => array_slice($devices, $pagination->getOffset(), 20),
    'devices_total' => count($devices), 'devices_pagination' => $pagination,
]);
$layout = file_get_contents($root . '/themes/default/templates/layout.php');
preg_match('/<div class="modal fade" id="adminDeleteModal".*?(?=<\?php endif;)/s', $layout, $matches);
echo preg_replace_callback("/<\?= print_translation\('([^']+)'\) \?>/", static fn(array $m): string => htmlSC(return_translation($m[1])), $matches[0]);
