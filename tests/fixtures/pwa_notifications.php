<?php
declare(strict_types=1);
// CLI-only fixture: actual CMS partials and independent paginators, no application DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$logPage = max(1, (int)($argv[2] ?? 1));
$devicePage = max(1, (int)($argv[3] ?? 2));
$empty = ($argv[4] ?? '') === 'empty';
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
    public function __construct() {
        $this->uri = base_href('/admin/settings/pwa') . '?' . http_build_query([
            'devices_page' => $GLOBALS['devicePage'], 'notifications_page' => $GLOBALS['logPage'],
        ]);
    }
    public function get(string $key, mixed $default = null): mixed {
        return match ($key) {
            'notifications_page' => $GLOBALS['logPage'], 'devices_page' => $GLOBALS['devicePage'], default => $default,
        };
    }
}; }
function view(): object { return new class {
    public function renderPartial(string $name, array $data = []): string {
        extract($data, EXTR_SKIP);
        ob_start();
        require $GLOBALS['root'] . '/app/Views/themes/default/' . $name . '.php';
        return ob_get_clean();
    }
}; }
$notifications = [];
// Deliberately pass legacy raw values to verify the renderer's defense in depth.
for ($id = $empty ? 0 : 45; $id > 0; $id--) {
    $chat = $id % 2 !== 0;
    $notifications[] = [
        'id' => $id, 'type' => $chat ? 'chat' : 'system', 'source' => $chat ? 'chat' : 'system',
        'title' => $chat ? 'PRIVATE_SENDER_NAME' : 'Public update ' . $id,
        'body' => $chat ? 'PRIVATE_BODY_🙂_attachment.pdf' : 'Ordinary public information <script>window.leaked=true</script>',
        'payload' => $chat ? '{"image":"private-attachment.jpg"}' : '{}',
        'status' => ['sent', 'failed', 'invalid', 'no_subscriptions', 'disabled', 'user_disabled', 'queued'][$id % 7],
        'sent_count' => $id % 4, 'failed_count' => $id % 3, 'created_at' => '2026-10-05 13:30:00',
    ];
}
$logsPagination = new FBL\Pagination(count($notifications), 20, 2, 7, 'pagination/base', 'notifications_page');
echo view()->renderPartial('admin/partials/pwa_notifications', [
    'notifications' => array_slice($notifications, $logsPagination->getOffset(), 20),
    'notifications_total' => count($notifications), 'notifications_pagination' => $logsPagination,
]);
$devices = [];
for ($id = 1; $id <= 45; $id++) {
    $devices[] = ['id' => $id, 'user_id' => 7, 'is_active' => 1, 'platform' => 'iOS', 'browser' => 'Safari',
        'user_agent' => 'Mozilla/5.0 iPhone', 'last_seen_at' => '2026-10-05 13:30:00', 'revoked_at' => null];
}
$devicesPagination = new FBL\Pagination(count($devices), 20, 2, 7, 'pagination/base', 'devices_page');
echo view()->renderPartial('admin/partials/pwa_devices', [
    'devices' => array_slice($devices, $devicesPagination->getOffset(), 20),
    'devices_total' => count($devices), 'devices_pagination' => $devicesPagination,
]);
$layout = file_get_contents($root . '/themes/default/templates/layout.php');
preg_match('/<div class="modal fade" id="adminDeleteModal".*?(?=<\?php endif;)/s', $layout, $matches);
echo preg_replace_callback("/<\?= print_translation\('([^']+)'\) \?>/", static fn(array $m): string => htmlSC(return_translation($m[1])), $matches[0]);
