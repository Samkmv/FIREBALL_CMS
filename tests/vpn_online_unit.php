<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');
require dirname(__DIR__) . '/core/Plugins/PluginInterface.php';
require dirname(__DIR__) . '/core/Plugins/PluginMigrationInterface.php';
require dirname(__DIR__) . '/plugins/vpn-manager-v2/Plugin.php';

function return_translation(string $key): string {
    static $language;
    $language ??= require dirname(__DIR__) . '/plugins/vpn-manager-v2/lang/ru.php';
    return $language[$key] ?? $key;
}
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_user(): array { return ['role' => $GLOBALS['test_role'] ?? 'creator']; }
function abort(string $message = '', int $status = 404): never { throw new RuntimeException($message, $status); }
function view(): object {
    return new class {
        public function renderPartial(string $path, array $data = []): string {
            if ($path === 'admin/shell_open') return '<main><h1>' . htmlSC($data['title']) . '</h1>';
            if ($path === 'admin/shell_close') return '</main>';
            extract($data);
            ob_start();
            require dirname(__DIR__) . '/app/views/themes/default/' . $path . '.php';
            return (string)ob_get_clean();
        }
    };
}
function plugin_view(string $slug, string $path, array $data = [], bool $layout = true): string {
    extract($data);
    ob_start();
    require dirname(__DIR__) . '/plugins/' . $slug . '/views/' . $path . '.php';
    return (string)ob_get_clean();
}
function cache(): object {
    static $cache;
    return $cache ??= new class {
        public array $data = [];
        public function get(string $key): mixed { return $this->data[$key] ?? null; }
        public function set(string $key, mixed $value, int $seconds): void { $this->data[$key] = $value; }
    };
}
function db(): object {
    static $database;
    return $database ??= new class {
        public PDO $pdo;
        public function __construct() {
            $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
        }
        public function query(string $sql, array $parameters = []): object {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            return new class($statement) {
                public function __construct(private PDOStatement $statement) {}
                public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
                public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            };
        }
    };
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$pdo = db()->pdo;
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, login TEXT);
CREATE TABLE vpn_v2_plans (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE vpn_v2_servers (id INTEGER PRIMARY KEY, name TEXT, is_enabled INTEGER, maintenance_mode INTEGER);
CREATE TABLE vpn_v2_inbounds (id INTEGER PRIMARY KEY, server_id INTEGER, name TEXT, protocol TEXT, port INTEGER, is_enabled INTEGER, status TEXT);
CREATE TABLE vpn_v2_subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, manual_customer_name TEXT, client_display_name TEXT, plan_id INTEGER, status TEXT, starts_at TEXT, expires_at TEXT);
CREATE TABLE vpn_v2_subscription_nodes (id INTEGER PRIMARY KEY, subscription_id INTEGER, server_id INTEGER, inbound_id INTEGER, client_email TEXT, status TEXT, is_obsolete INTEGER, presence_last_online_at TEXT);
INSERT INTO users VALUES (1, 'Кирилл <script>unsafe</script>', 'client7876');
INSERT INTO vpn_v2_plans VALUES (1, 'Start 30');
INSERT INTO vpn_v2_servers VALUES (1, 'Germany', 1, 0), (2, 'Netherlands', 1, 0), (3, 'Backup', 1, 0), (4, 'Maintenance', 1, 1);
INSERT INTO vpn_v2_inbounds VALUES (1, 1, 'DE 443', 'vless', 443, 1, 'active'), (2, 2, 'NL 443', 'vless', 443, 1, 'active'),
 (3, 3, 'Backup 443', 'vless', 443, 1, 'active'), (4, 4, 'Maintenance 443', 'vless', 443, 1, 'active');
INSERT INTO vpn_v2_subscriptions VALUES (10, 1, NULL, '78-76', 1, 'active', '2020-01-01', NULL),
 (11, 1, NULL, '', 1, 'active', '2020-01-01', NULL),
 (12, NULL, 'Наташа', '', 1, 'active', '2020-01-01', NULL),
 (13, NULL, 'Недоступный клиент', '', 1, 'active', '2020-01-01', NULL),
 (14, NULL, 'Истёкший клиент', '', 1, 'active', '2020-01-01', '2021-01-01');
INSERT INTO vpn_v2_subscription_nodes VALUES
 (101, 10, 1, 1, 'private-a', 'active', 0, NULL),
 (102, 11, 2, 2, 'private-b', 'active', 0, NULL),
 (103, 12, 1, 1, 'private-manual', 'active', 0, NULL),
 (104, 13, 3, 3, 'private-failed', 'active', 0, '2025-01-01 12:00:00'),
 (105, 12, 1, 1, 'private-offline', 'active', 0, '2025-01-02 12:00:00'),
 (106, 14, 1, 1, 'expired', 'active', 0, NULL),
 (107, 10, 1, 1, 'obsolete', 'active', 1, NULL),
 (108, 10, 4, 4, 'maintenance', 'active', 0, NULL),
 (109, 10, 1, 1, 'deleted', 'deleted', 0, NULL);");

$repository = new Fireball\VpnManagerV2\Repositories\SubscriptionRepository();
$candidates = $repository->presenceCandidates();
$assert(count($candidates) === 5, 'Expired, obsolete, deleted and maintenance connections are excluded.');
$calls = [];
$fail = false;
$service = new Fireball\VpnManagerV2\Services\SubscriptionPresenceService(
    nodeProvider: static fn(): array => $repository->presenceCandidates(),
    serverProvider: static fn(int $id): array => ['id' => $id, 'name' => [1 => 'Germany', 2 => 'Netherlands', 3 => 'Backup'][$id],
        'is_enabled' => 1, 'auth_type' => 'token', 'panel_url' => 'https://fixture.invalid', 'panel_path' => '',
        'encrypted_token' => '', 'encrypted_username' => '', 'encrypted_password' => ''],
    clientFactory: static function (array $server) use (&$calls, &$fail): object {
        $id = $server['id'];
        $calls[$id] = ($calls[$id] ?? 0) + 1;
        return new class($id, $fail) {
            public function __construct(private int $id, private bool $fail) {}
            public function onlineClientNames(): array {
                if ($this->id === 3 || $this->fail) throw new RuntimeException('SECRET must stay outside responses');
                return $this->id === 1 ? ['private-a', 'private-manual', 'foreign-client'] : ['private-b'];
            }
        };
    },
);
$snapshot = $service->snapshot();
$summary = $snapshot['summary'];
$assert($summary['users'] === 2 && $summary['subscriptions'] === 3, 'One account on two servers counts once; manual customers count as subscribers.');
$assert($summary['connections'] === 3 && $summary['servers'] === 2, 'Connection and server counters reflect confirmed online clients.');
$assert($summary['checked'] === 2 && $summary['unavailable'] === 1, 'Partial failures retain successful results.');
$byId = array_column($snapshot['connections'], null, 'connection_id');
$assert($byId[104]['presence'] === 'unknown' && $byId[105]['presence'] === 'offline', 'Server failures and confirmed offline clients remain distinct.');
$assert($byId[104]['last_seen_at'] === '2025-01-01 12:00:00', 'Unavailable servers preserve the previous last-seen timestamp.');
$seen = $pdo->query('SELECT presence_last_online_at FROM vpn_v2_subscription_nodes WHERE id = 101')->fetchColumn();
$assert($seen === $byId[101]['last_seen_at'], 'Confirmed presence is persisted.');
$repository->recordPresence([101], '2020-01-01 00:00:00');
$assert($pdo->query('SELECT presence_last_online_at FROM vpn_v2_subscription_nodes WHERE id = 101')->fetchColumn() === $seen, 'Concurrent older observations never move last-seen backwards.');
$filter = $service->online();
sort($filter['ids']);
$assert($filter === ['ids' => [10, 11, 12], 'unavailable' => 1], 'The existing subscription online filter preserves its public response shape.');
$assert($calls[1] === 1 && $calls[2] === 1, 'Successful online samples are reused across page and filter requests.');
$serialized = json_encode($snapshot);
$assert(!str_contains($serialized, 'private-a') && !str_contains($serialized, 'SECRET') && !str_contains($serialized, 'foreign-client'), 'Private identifiers, foreign users and raw errors stay outside the response.');

$tabs = FireballPluginVpnManagerV2::tabs('online');
$keys = array_column($tabs, 'key');
$assert(array_search('online', $keys, true) === array_search('subscriptions', $keys, true) + 1, 'Online is directly after Subscriptions.');
$assert(isset(FireballPluginVpnManagerV2::jobs()['vpn_v2_presence']), 'Last-seen observations continue through the CMS scheduler.');
$page = (new Fireball\VpnManagerV2\Controllers\Admin\OnlineController())->index();
$assert(str_contains($page, 'data-vpn-online-search') && str_contains($page, 'data-vpn-online-sort-select'), 'The management page exposes search and sorting.');
$html = plugin_view('vpn-manager-v2', 'admin/partials/online-results', ['snapshot' => $snapshot], false);
$assert(!str_contains($html, '<script>unsafe</script>') && str_contains($html, '&lt;script&gt;'), 'Customer names are escaped in table and mobile cards.');
$assert(substr_count($html, 'fb-vpn-stat-card') === 4 && str_contains($html, 'admin-mobile-table-card'), 'CMS statistic cards and native mobile cards render.');
$assert(str_contains($html, '2025-01-01 12:00:00') && str_contains($html, 'Нет данных'), 'Offline history and server failure states remain visible in the All view.');
$GLOBALS['test_role'] = 'user';
try {
    (new Fireball\VpnManagerV2\Controllers\Admin\OnlineController())->index();
    throw new RuntimeException('Unprivileged access was allowed');
} catch (RuntimeException $exception) {
    $assert($exception->getCode() === 403, 'Online user details require administrator access.');
}
unset($GLOBALS['test_role']);

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require dirname(__DIR__) . '/plugins/vpn-manager-v2/lang/' . $locale . '.php';
    $assert(isset($translations['vpn_manager_v2_tab_online'], $translations['vpn_manager_v2_online_last_seen_hint']), 'Online translations are available for ' . $locale);
}
if (getenv('VPN_ONLINE_FIXTURE_DIR')) {
    $directory = realpath((string)getenv('VPN_ONLINE_FIXTURE_DIR'));
    $allowed = realpath(__DIR__ . '/.tmp');
    if (!$directory || !$allowed || !str_starts_with($directory, $allowed . '/')) throw new RuntimeException('Fixture path must be inside tests/.tmp');
    file_put_contents($directory . '/page.html', $page);
    file_put_contents($directory . '/snapshot.html', $html);
}
cache()->data = [];
$fail = true;
$failed = $service->snapshot();
$assert($failed['summary']['checked'] === 0 && $failed['summary']['unavailable'] === 3, 'Total failures have no confirmed samples.');
$failedHtml = plugin_view('vpn-manager-v2', 'admin/partials/online-results', ['snapshot' => $failed], false);
$assert(substr_count($failedHtml, 'display-6 fw-semibold lh-1 mt-3">—') === 4, 'Unavailable statistics show unknown values instead of zero users.');
$assert($pdo->query('SELECT presence_last_online_at FROM vpn_v2_subscription_nodes WHERE id = 101')->fetchColumn() === $seen, 'Failed refreshes preserve stored presence history.');
echo "PASS online identities, last-seen persistence, partial failures, cache, legacy filter, permissions, translations and CMS views.\n";
