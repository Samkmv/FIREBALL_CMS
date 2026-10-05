<?php
declare(strict_types=1);

// php tests/profile_push_unit.php — isolated SQLite, never uses deployment credentials.
require dirname(__DIR__) . '/vendor/autoload.php';
define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
function request(): object { return $GLOBALS['testRequest'] ??= new class {
    public string $uri = '/admin/settings/pwa';
    public array $get = [];
    public array $post = [];
    public function get(string $key, mixed $default = null): mixed { return $this->get[$key] ?? $default; }
    public function post(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
}; }
function abort(string $error = '', int $code = 404): never { throw new RuntimeException($error ?: 'Aborted', $code); }
function session(): object { return new class { public function setFlash(string $key, mixed $value): void {} }; }
function response(): object { return new class { public function redirect(string $url): never { throw new RuntimeException($url, 302); } }; }
function base_href(string $path): string { return $path; }
function return_translation(string $key): string { return $key; }
final class ProfilePushTestDb
{
    public PDO $pdo;
    private ?PDOStatement $lastStatement = null;
    public bool $failPreferenceSync = false;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE pwa_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, is_active INTEGER, endpoint_hash TEXT UNIQUE, endpoint TEXT, p256dh TEXT, auth TEXT, platform TEXT, browser TEXT, user_agent TEXT, subscription_json TEXT, last_seen_at TEXT, last_used_at TEXT, revoked_at TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE notification_settings (user_id INTEGER PRIMARY KEY, push_enabled INTEGER, created_at TEXT, updated_at TEXT)');
    }
    public function query(string $sql, array $params = []): object {
        if ($this->failPreferenceSync && str_contains($sql, 'INSERT INTO notification_settings')) {
            $this->failPreferenceSync = false;
            throw new RuntimeException('Simulated preference sync failure');
        }
        $sql = str_replace(' FOR UPDATE', '', $sql); // SQLite locks the test transaction.
        if (str_contains($sql, 'ON DUPLICATE KEY UPDATE')) {
            $conflict = str_contains($sql, 'INSERT INTO pwa_subscriptions') ? 'endpoint_hash' : 'user_id';
            $sql = str_replace('ON DUPLICATE KEY UPDATE', 'ON CONFLICT (' . $conflict . ') DO UPDATE SET', $sql);
            $sql = preg_replace('/VALUES\((\w+)\)/', 'excluded.$1', $sql);
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $this->lastStatement = $statement;
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
        };
    }
    public function rowCount(): int { return $this->lastStatement->rowCount(); }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
}
function db(): ProfilePushTestDb { static $db; return $db ??= new ProfilePushTestDb(); }
function request_is_secure(): bool { return true; }
$settings = new class extends App\Models\SiteSetting {
    public function get(string $key, string $default = ''): string {
        return ['pwa_enabled' => '1', 'pwa_push_enabled' => '1', 'pwa_vapid_public_key' => 'fixture', 'pwa_vapid_private_key' => 'fixture'][$key] ?? $default;
    }
};
$service = new App\Services\PwaService($settings);
$checks = 0;
function check(bool $value, string $label): void {
    $GLOBALS['checks']++;
    if (!$value) throw new RuntimeException($label);
}
$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
$details = openssl_pkey_get_details($key)['ec'];
$encode = static fn(string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
$subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/first', 'keys' => ['p256dh' => $encode("\x04" . $details['x'] . $details['y']), 'auth' => $encode(random_bytes(16))]];
check($service->saveSubscription(7, $subscription), 'Save current endpoint');
$second = array_replace($subscription, ['endpoint' => 'https://web.push.apple.com/second']);
check($service->saveSubscription(7, $second), 'Save second endpoint');
$firstHash = hash('sha256', $subscription['endpoint']);
$secondHash = hash('sha256', $second['endpoint']);
check($service->pushStatusForUser(7, $firstHash)['current_device_active'] === true, 'Current endpoint is active');
check($service->pushStatusForUser(8, $firstHash)['current_device_active'] === false, 'Endpoint is user-scoped');
check($service->pushStatusForUser(7)['current_device_active'] === null, 'Aggregate status cannot imply device state');
check($service->pushStatusForUser(7, str_repeat('0', 64))['current_device_active'] === false, 'Other devices do not imply current endpoint');
$service->deleteSubscription(8, $subscription['endpoint']);
check($service->pushStatusForUser(7, $firstHash)['current_device_active'] === true, 'Other user cannot revoke endpoint');
$service->deleteSubscription(7, $subscription['endpoint']);
check($service->pushStatusForUser(7, $firstHash)['current_device_active'] === false, 'Revoked current endpoint');
check($service->pushStatusForUser(7, $secondHash)['current_device_active'] === true, 'Second device remains active');
check($service->isUserPushEnabled(7), 'Delivery remains enabled for the second device');
$service->deleteSubscription(7, '');
check($service->pushStatusForUser(7)['active_subscriptions'] === 1, 'Empty endpoint never revokes all devices');
$service->deleteSubscription(7, $second['endpoint']);
check(!$service->isUserPushEnabled(7), 'Last endpoint disabled updates aggregate setting');
check(!$service->saveSubscription(0, $subscription), 'Guests cannot subscribe');
check(!$service->saveSubscription(7, array_replace($subscription, ['endpoint' => 'http://127.0.0.1/test'])), 'Endpoint policy remains enforced');

check($service->saveSubscription(7, $subscription) && $service->saveSubscription(7, $second), 'Prepare active admin-managed devices');
$third = array_replace($subscription, ['endpoint' => 'https://web.push.apple.com/third']);
check($service->saveSubscription(8, $third), 'Prepare another user');
for ($i = 0; $i < 123; $i++) {
    check($service->saveSubscription(9 + intdiv($i, 20), array_replace($subscription, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/page-' . $i])), 'Seed devices within the per-user limit');
}
$firstPage = $service->paginatedDevices();
check($firstPage['total'] === 126 && count($firstPage['items']) === 20, 'Devices: 20 per page, total is not capped at 100');
check($firstPage['pagination']['total_pages'] === 7, 'Devices use CMS Pagination');
check(!array_key_exists('endpoint', $firstPage['items'][0]) && !array_key_exists('auth', $firstPage['items'][0]), 'Device list never exposes Push credentials');
request()->get['devices_page'] = 7;
request()->uri = '/en/admin/settings/pwa?devices_page=7';
$lastPage = $service->paginatedDevices();
check(count($lastPage['items']) === 6 && $lastPage['pagination']['current_page'] === 7, 'Last page has remaining devices');
check(count(array_unique(array_column(array_merge($firstPage['items'], $lastPage['items']), 'id'))) === 26, 'First and last page do not duplicate devices');
check($lastPage['pagination']['prev_url'] === '/en/admin/settings/pwa?devices_page=6', 'Pagination preserves locale');
request()->get['devices_page'] = 2;
check($service->paginatedDevices()['pagination']['prev_url'] === '/en/admin/settings/pwa', 'Page-one link removes pagination query');
request()->get = [];
request()->uri = '/admin/settings/pwa';
$firstId = (int)db()->query('SELECT id FROM pwa_subscriptions WHERE endpoint_hash = ?', [$firstHash])->getColumn();
db()->failPreferenceSync = true;
try { $service->detachDevices($firstId); check(false, 'Expected sync failure'); }
catch (RuntimeException $exception) { check($exception->getMessage() === 'Simulated preference sync failure', 'Sync failure propagated'); }
check($service->pushStatusForUser(7, $firstHash)['current_device_active'] === true && $service->isUserPushEnabled(7), 'Detach rolls back binding and preferences together');
check($service->detachDevices($firstId) === 1, 'Admin detaches one exact binding');
check($service->isUserPushEnabled(7) && $service->isUserPushEnabled(8), 'One detach preserves other devices and users');
check($service->pushStatusForUser(7, $firstHash)['current_device_active'] === false, 'Removed endpoint is not active on return');
check($service->detachDevices($firstId) === 0, 'Already detached ID is harmless');
try { $service->detachDevices(0); check(false, 'Expected invalid ID rejection'); }
catch (InvalidArgumentException) { check(true, 'Zero ID cannot mean all devices'); }

$controllerReflection = new ReflectionClass(App\Controllers\AdminController::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
$controllerReflection->getProperty('siteSettings')->setValue($controller, $settings);
foreach ([[], ['scope' => 'one'], ['scope' => 'one', 'id' => 0], ['scope' => 'alll'], ['scope' => ['all']], ['scope' => 'one', 'id' => [1]]] as $post) {
    request()->post = $post;
    try { $controller->detachPwaDevices(); check(false, 'Malformed request must fail'); }
    catch (RuntimeException $exception) { check($exception->getCode() === 422, 'Malformed detach returns 422'); }
}
check($service->paginatedDevices()['total'] === 125, 'Malformed requests never delete all');
request()->post = ['scope' => 'all'];
try { $controller->detachPwaDevices(); check(false, 'Expected redirect'); }
catch (RuntimeException $exception) { check($exception->getCode() === 302 && str_ends_with($exception->getMessage(), '#pwa-devices'), 'Detach resets pagination and returns to table'); }
check($service->paginatedDevices()['total'] === 0, 'Detach all removes records, not just current page');
check(!$service->isUserPushEnabled(7) && !$service->isUserPushEnabled(8) && !$service->isUserPushEnabled(9) && !$service->isUserPushEnabled(15), 'Detach all recomputes all affected user flags');
check($service->detachDevices(null) === 0, 'Empty-table detach is safe');
check($service->saveSubscription(7, $subscription), 'Explicit enable can bind browser again');
request()->post = ['scope' => 'one', 'id' => db()->query('SELECT id FROM pwa_subscriptions')->getColumn()];
try { $controller->detachPwaDevices(); check(false, 'Expected one-device redirect'); }
catch (RuntimeException $exception) { check($exception->getCode() === 302, 'Controller one-device action'); }
check($service->paginatedDevices()['total'] === 0 && !$service->isUserPushEnabled(7), 'Last binding is removed and delivery disabled');
$routes = file_get_contents(dirname(__DIR__) . '/config/routes.php');
check(str_contains($routes, "->post('/admin/settings/pwa/devices/detach', [AdminController::class, 'detachPwaDevices'])->middleware(['auth', 'admin'])"), 'Detach route is POST-only and admin-protected');
check(!str_contains($routes, "'detachPwaDevices'])->middleware(['auth', 'admin'])->withoutCSRFToken"), 'CSRF is not exempted');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $baseTranslations = require dirname(__DIR__) . '/app/Languages/' . $locale . '.php';
    foreach (['device_detach', 'devices_detach_all', 'devices_detach_title', 'device_detach_confirm', 'devices_detach_all_confirm', 'devices_detach_hint', 'devices_detached', 'devices_detach_error', 'device_linked', 'device_inactive', 'device_user', 'device_details'] as $key) {
        check(!empty($baseTranslations['admin_pwa_' . $key]), 'Device table translations: ' . $locale . ' ' . $key);
    }
    $translations = require dirname(__DIR__) . '/app/Languages/' . $locale . '/auth/profile.php';
    foreach (['checking', 'enabled', 'disabled', 'unavailable', 'unsupported', 'permission', 'install', 'error'] as $state) {
        foreach (['auth_profile_push_status_', 'auth_profile_push_hint_'] as $prefix) {
            check(!empty($translations[$prefix . $state]), $locale . ' ' . $prefix . $state);
            check(!preg_match('/maxipapa/i', $translations[$prefix . $state]), 'No site branding in core translation');
        }
    }
    foreach (['overview', 'information', 'security', 'notifications'] as $section) {
        foreach (['theme', 'fallback'] as $variant) {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/profile.php') . ' ' . $locale . ' ' . $section . ' ' . $variant, $output, $exitCode);
            check($exitCode === 0, 'Fixture renders without PHP errors');
            $html = implode("\n", $output);
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            check($xpath->query('//*[@data-profile-route-nav]//a[@aria-current="page"]')->length === 1, 'One active route');
            check($xpath->query('//*[@data-profile-route-nav]//a[contains(concat(" ",normalize-space(@class)," ")," active ")]')->length === 1, 'One active class');
            $active = $xpath->query('//*[@data-profile-route-nav]//a[@aria-current="page"]')->item(0);
            check(str_ends_with($active->getAttribute('href'), $section === 'overview' ? '/profile' : '/profile/settings?section=' . $section), 'Active real href');
            check($xpath->query('//*[@data-profile-route-nav]//*[@data-bs-toggle or @role="tab"]')->length === 0, 'Not JS tabs');
            check(trim($xpath->query('//*[@data-pwa-push-status]')->item(0)?->textContent ?? '') === $translations['auth_profile_push_status_checking'] || $section === 'information' || $section === 'security', 'No false enabled SSR from aggregate');
        }
    }
}
foreach (['themes/default/templates/layout.php', 'app/Views/layouts/default.php'] as $layout) {
    $source = file_get_contents(dirname(__DIR__) . '/' . $layout);
    check(str_contains($source, 'data-pwa-auth-user-id='), 'Auth passed to PWA in ' . $layout);
    check(str_contains($source, 'data-pwa-status-url='), 'Device status URL in ' . $layout);
}
echo 'Profile / push unit checks passed: ' . $checks . PHP_EOL;
