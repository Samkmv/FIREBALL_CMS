<?php
// Execute the real list queries against isolated fixtures; never connect to the CMS database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Moscow');
foreach (['ProvisioningStatus', 'SubscriptionListFilter', 'AdminTableState', 'TrafficFormatter', 'AdminActionDropdown'] as $class) {
    require dirname(__DIR__) . '/src/Support/' . $class . '.php';
}
require dirname(__DIR__) . '/src/Repositories/SubscriptionRepository.php';
function htmlSC(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return ''; }
class FireballPluginVpnManagerV2 {
    public static function t(string $key): string {
        static $lang;
        $lang ??= require dirname(__DIR__) . '/lang/ru.php';
        return $lang[$key] ?? $key;
    }
}
function db(): object {
    static $adapter;
    return $adapter ??= new class {
        public PDO $pdo;
        public function __construct() { $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
        public function query(string $sql, array $params = []): object {
            $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
            return new class($stmt) {
                public function __construct(private PDOStatement $stmt) {}
                public function get(): array { return $this->stmt->fetchAll(PDO::FETCH_ASSOC); }
                public function getColumn(): mixed { return $this->stmt->fetchColumn(); }
            };
        }
    };
}
$pdo = db()->pdo;
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, login TEXT, email TEXT);
 CREATE TABLE vpn_v2_plans (id INTEGER PRIMARY KEY, name TEXT);
 CREATE TABLE vpn_v2_subscription_nodes (id INTEGER PRIMARY KEY, subscription_id INTEGER, status TEXT);
 CREATE TABLE vpn_v2_subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, plan_id INTEGER, status TEXT,
 starts_at TEXT, expires_at TEXT, traffic_limit_bytes INTEGER, device_limit INTEGER DEFAULT 3, ip_limit INTEGER DEFAULT 0,
 revision INTEGER DEFAULT 1, config_updated_at TEXT, created_by INTEGER, internal_comment TEXT, last_error TEXT,
 created_at TEXT, updated_at TEXT, manual_customer_name TEXT, client_display_name TEXT);
 INSERT INTO vpn_v2_plans VALUES (1, \'Тариф\');
 INSERT INTO users VALUES (1, \'Пользователь\', \'fixture\', \'fixture@example.test\');');
$past = date('Y-m-d H:i:s', time() - 86400);
$future = date('Y-m-d H:i:s', time() + 86400);
$fixtures = [
    ['active', $past], ['active', $future], ['active', null], ['suspended', $future],
    ['suspended', $past], ['partial_sync', $past], ['partial_sync', $future],
    ['traffic_exceeded', $future], ['cancelled', $past], ['deleted', $past],
    ['provisioning_failed', $future], ['deleting', $past], ['sync_error', $future],
];
$stmt = $pdo->prepare('INSERT INTO vpn_v2_subscriptions (id,user_id,plan_id,status,starts_at,expires_at,created_at,manual_customer_name) VALUES (?, ?, 1, ?, ?, ?, ?, ?)');
foreach ($fixtures as $index => [$status, $expiry]) {
    $stmt->execute([$index + 1, $index === 0 ? null : 1, $status, $past, $expiry, $past, $index === 0 ? 'Ручной клиент' : null]);
}
$repository = new Fireball\VpnManagerV2\Repositories\SubscriptionRepository();
$assert = static function (bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } };
$ids = static fn(array $rows): array => array_map('intval', array_column($rows, 'id'));
$assert($repository->countAdminList() === 12, 'Deleted subscriptions are excluded');
$assert($repository->countAdminList('', 'expired') === 3, 'Expiration is calculated before the worker updates stored statuses');
$assert($ids($repository->adminPage('', 20, 0, 'expired')) === [6, 5, 1], 'Expired filter matches displayed statuses');
$assert($ids($repository->adminPage('', 20, 0, 'active')) === [3, 2], 'Lifetime remains active and past active subscriptions are excluded');
$assert($ids($repository->adminPage('', 20, 0, 'inactive')) === [9, 8, 4], 'Inactive excludes expired and groups suspended, cancelled and exhausted traffic');
$assert($ids($repository->adminPage('Ручной', 20, 0, 'expired')) === [1], 'Search and status filter compose for manual customers');
$assert($repository->countAdminList('fixture@example.test', 'active') === 2, 'Search and status counts compose');
$assert($ids($repository->adminPage('', 1, 1, 'expired')) === [5], 'Pagination operates on the filtered result');
$assert($repository->countAdminList('', "expired' OR 1=1 --") === 12, 'Unknown filters safely use the unfiltered list');
$counts = $repository->adminStatusCounts();
$assert($counts['expired'] === 3 && $counts['active'] === 2, 'Counters use actual expiration and include lifetime');
$assert(Fireball\VpnManagerV2\Support\SubscriptionListFilter::inactiveCount($counts) === 3, 'Inactive counter matches the filter');
$assert($repository->countAdminList('', 'partial_sync') === 1 && $repository->countAdminList('', 'deleting') === 1, 'Individual error and deletion statuses remain available');
$assert(Fireball\VpnManagerV2\Support\AdminTableState::sanitize('q=fixture&status=expired&page=2&token=secret') === 'page=2&q=fixture&status=expired', 'Returning from actions retains search and filter, excluding secrets');
$accessRequests = [[
    'id' => 17, 'user_id' => 1, 'user_name' => 'Тестовая заявка',
    'user_login' => 'fixture', 'user_email' => 'fixture@example.test',
    'requested_at' => '2026-10-08 12:00:00',
]];
ob_start();
require dirname(__DIR__) . '/views/admin/subscriptions.php';
$accessRequestMarkup = ob_get_clean();
$assert((bool)preg_match('~<form[^>]+action="/admin/plugins/vpn-manager-v2/subscriptions/access-requests/17/dismiss"[^>]*>.*?<i class="ci-close me-1" aria-hidden="true"></i>~s', $accessRequestMarkup), 'Closing a VPN access request uses the native ci-close icon');
$assert(!str_contains($accessRequestMarkup, 'class="ci-x me-1"'), 'Access-request card does not retain the old icon');
if (($argv[1] ?? '') !== 'render') { echo "PASS subscription status filters, counters, search, pagination, return state and access-request icon\n"; exit; }
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            if ($name === 'admin/shell_open') { return '<main class="container py-4"><h1 class="h3">Подписки</h1>'; }
            if ($name === 'admin/partials/table') { return '<p>Таблица подписок</p>'; }
            return '</main>';
        }
    };
}
$statusFilter = $argv[2] ?? '';
$searchQuery = $argv[3] ?? '';
$statusCounts = $counts;
$subscriptions = $repository->adminPage($searchQuery, 20, 0, $statusFilter);
$subscriptionsTotal = $repository->countAdminList($searchQuery, $statusFilter);
echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta name="viewport" content="width=device-width,initial-scale=1">';
foreach (['css/theme.min.css', 'css/admin-ui.css', 'icons/cartzilla-icons.min.css'] as $asset) {
    echo '<link rel="stylesheet" href="/assets/default/' . $asset . '">';
}
echo '</head><body class="fb-admin-body">';
require dirname(__DIR__) . '/views/admin/subscriptions.php';
echo '</body></html>';
