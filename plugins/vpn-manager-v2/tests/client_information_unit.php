<?php
// Isolated database and panel boundaries. No CMS bootstrap, real credentials or HTTP.
namespace Fireball\VpnManagerV2\Repositories {
    function db(): object { return $GLOBALS['inspectionDatabase']; }
}
namespace {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Fireball\\VpnManagerV2\\';
        if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });
    class FireballPluginVpnManagerV2 {
        public static function t(string $key): string {
            static $translations;
            $translations ??= require dirname(__DIR__) . '/lang/ru.php';
            return $translations[$key] ?? $key;
        }
    }
    function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function base_href(string $path): string { return $path; }
    function get_user(): array { return ['id' => 1, 'role' => $GLOBALS['fixtureRole'] ?? 'creator']; }
    function abort(string $message, int $code): never { throw new \RuntimeException('HTTP ' . $code); }
    function get_route_param(string $name): int { return $name === 'id' ? 15 : 7; }
    function db(): object { return $GLOBALS['inspectionDatabase']; }
    $GLOBALS['inspectionDatabase'] = new class {
        public bool $transaction = false;
        public ?array $node;
        public ?array $server = ['id' => 1, 'is_enabled' => 1, 'encrypted_password' => 'secret-panel-password'];
        public ?array $inbound = ['id' => 2, 'server_id' => 1, 'remote_inbound_id' => 42];
        public string $sql = '';
        public array $queries = [];
        public function inTransaction(): bool { return $this->transaction; }
        public function query(string $sql, array $params = []): self {
            if (!str_starts_with(ltrim($sql), 'SELECT')) throw new \RuntimeException('Database write attempted');
            $this->queries[] = $this->sql = $sql;
            return $this;
        }
        public function getOne(): array|false {
            if (str_contains($this->sql, 'FROM vpn_v2_subscription_nodes')) return $this->node ?: false;
            if (str_contains($this->sql, 'FROM vpn_v2_servers')) return $this->server ?: false;
            if (str_contains($this->sql, 'FROM vpn_v2_inbounds')) return $this->inbound ?: false;
            throw new \RuntimeException('Unexpected database read');
        }
    };
    class InspectionPanel implements \Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface {
        public array $remote = ['id' => 'fixture-credential', 'email' => 'fixture-client', 'enable' => true,
            'totalGB' => 1024, 'expiryTime' => 1791410400000, 'limitIp' => 1, 'limitHwid' => 2,
            'subId' => 'secret-subscription-token'];
        public array $traffic = ['obj' => ['email' => 'fixture-client', 'up' => 100, 'down' => 300, 'lastOnline' => 1791324000000]];
        public array $calls = [];
        public bool $offline = false;
        public bool $missing = false;
        public function authenticate(): void { $this->calls[] = 'authenticate'; }
        public function testConnection(): \Fireball\VpnManagerV2\DTO\ConnectionTestResult { throw new \RuntimeException('Unused'); }
        public function listInbounds(): array { throw new \RuntimeException('Unexpected broad panel read'); }
        public function getInbound(int $id): array {
            if ($id !== 42) throw new \RuntimeException('Incorrect remote inbound');
            $this->calls[] = 'inbound';
            if ($this->offline) throw new \RuntimeException('secret-panel-password: panel unavailable');
            return ['settings' => json_encode(['clients' => $this->missing ? [] : [$this->remote]])];
        }
        public function getClientTraffic(string $email): array {
            if ($email !== $this->remote['email']) throw new \RuntimeException('Incorrect client traffic query');
            $this->calls[] = 'traffic'; return $this->traffic;
        }
        public function findClient(int $id, string $clientId = '', string $email = ''): ?array { throw new \RuntimeException('Unused'); }
        public function addClient(int $id, array $client): array { throw new \RuntimeException('Panel write attempted'); }
        public function updateClient(int $id, string $clientId, array $client): array { throw new \RuntimeException('Panel write attempted'); }
        public function deleteClient(int $id, string $clientId, ?string $email = null): array { throw new \RuntimeException('Panel write attempted'); }
        public function resetClientTraffic(int $id, string $email): array { throw new \RuntimeException('Traffic reset attempted'); }
    }
    class ModernInspectionPanel extends InspectionPanel {
        public ?array $global = null;
        public bool $devicesUnsupported = false;
        public function findGlobalClient(string $email): ?array { $this->calls[] = 'global'; return $this->global; }
        public function listClientDevices(string $email): array {
            $this->calls[] = 'devices';
            if ($this->devicesUnsupported) throw new \RuntimeException('HWID unsupported');
            return [['id' => 1, 'hwid' => 'secret-device-1'], ['id' => 2], ['id' => 0]];
        }
    }
    $node = ['id' => 7, 'subscription_id' => 14, 'subscription_status' => 'active', 'status' => 'active',
        'server_id' => 1, 'inbound_id' => 2, 'protocol' => 'vless', 'client_uuid' => 'fixture-credential',
        'client_email' => 'fixture-client', 'remote_client_id' => 'private-remote-id', 'client_sub_id' => 'private-sub-id'];
    db()->node = $node;
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'client_information_render.php') { return; }
    // Exercise the actual HTTP controller's foreign-connection response without ever creating a panel client.
    if (in_array('--foreign-endpoint', $argv, true)) {
        register_shutdown_function(static fn() => fwrite(STDERR, 'HTTP_STATUS=' . http_response_code()));
        (new \Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController())->clientInfo();
    }
    $checks = 0;
    $check = static function (bool $condition, string $message) use (&$checks): void {
        $checks++; if (!$condition) throw new \RuntimeException($message);
    };
    $expectError = static function (callable $action, string $class) use ($check): void {
        try { $action(); } catch (\Throwable $error) { $check($error instanceof $class, 'Incorrect exception: ' . get_class($error)); return; }
        throw new \RuntimeException('Expected ' . $class);
    };
    $panel = new InspectionPanel(); $factories = 0;
    $service = new \Fireball\VpnManagerV2\Services\SubscriptionClientInfoService(clientFactory:
        static function () use (&$panel, &$factories): InspectionPanel { $factories++; return $panel; });
    $fields = static function (array $result): array {
        return array_column($result['fields'], 'value', 'label');
    };
    $t = static fn(string $key): string => FireballPluginVpnManagerV2::t('vpn_manager_v2_' . $key);
    $value = static fn(array $values, string $key): string => $values[$t($key)];
    $result = $service->read(14, 7); $values = $fields($result);
    $check($result['subscription_id'] === 14 && $result['connection_id'] === 7, 'Correct response association');
    $check($result['server_id'] === 1 && $result['traffic'] === ['upload' => 100, 'download' => 300, 'total' => 400], 'Read-only counters explicitly associated with the owning server');
    $check($value($values, 'profile_traffic_used') === '400 Б', 'Actual upload + download, not local counters');
    $check($value($values, 'client_upload') === '100 Б' && $value($values, 'client_download') === '300 Б', 'Separate traffic directions');
    $check($value($values, 'profile_traffic_remaining') === '624 Б', 'Actual remaining allowance');
    $check($value($values, 'client_panel_access') === $t('overview_enabled'), 'Access is not an online claim');
    $check($value($values, 'client_device_count') === '—', 'Unsupported HWID is unknown');
    $check($value($values, 'client_panel_expiry') === date('Y-m-d H:i:s', 1791410400), 'Millisecond expiry normalized');
    $check($value($values, 'client_last_activity') === date('Y-m-d H:i:s', 1791324000), 'Millisecond activity normalized');
    $encoded = json_encode($result);
    foreach (['fixture-credential', 'private-remote-id', 'private-sub-id', 'secret-subscription-token', 'secret-client-password', 'secret-panel-password'] as $secret) {
        $check(!str_contains($encoded, $secret), 'Response leaked credential');
    }
    $check($panel->calls === ['inbound', 'traffic'], 'No modifying or broad panel requests');
    foreach (['false', false, 0] as $disabled) {
        $panel->remote['enable'] = $disabled;
        $check($value($fields($service->read(14, 7)), 'client_panel_access') === $t('provisioning_status_disabled'), 'Disabled client displayed as enabled');
    }
    foreach ([null, '', 'invalid'] as $unknown) {
        $panel->remote['enable'] = $unknown;
        $panel->remote['expiryTime'] = $unknown;
        $result = $fields($service->read(14, 7));
        $check($value($result, 'client_panel_access') === '—' && $value($result, 'client_panel_expiry') === '—', 'Missing values invented access or lifetime');
    }
    $panel->remote['expiryTime'] = 0; $panel->remote['totalGB'] = 0;
    $result = $fields($service->read(14, 7));
    $check($value($result, 'client_panel_expiry') === $t('lifetime_short'), 'Explicit zero expiry is lifetime');
    $check($value($result, 'profile_traffic_remaining') === $t('unlimited'), 'Unlimited allowance');
    unset($panel->remote['totalGB'], $panel->remote['limitIp'], $panel->remote['limitHwid']);
    $result = $fields($service->read(14, 7));
    foreach (['profile_traffic_limit', 'profile_traffic_remaining', 'field_ip_limit', 'field_device_limit'] as $key) {
        $check($value($result, $key) === '—', 'Missing limit must be unknown');
    }
    $panel = new ModernInspectionPanel();
    $panel->global = ['client' => ['id' => 'fixture-credential', 'enable' => false, 'totalGB' => 200, 'expiryTime' => 0, 'limitHwid' => 8]];
    $result = $fields($service->read(14, 7));
    $check($value($result, 'client_panel_access') === $t('provisioning_status_disabled'), 'Global client access overrides stale inbound projection');
    $check($value($result, 'field_device_limit') === '8', 'Global HWID limit used');
    $check($value($result, 'profile_traffic_remaining') === '0 Б', 'Exceeded traffic cannot become negative');
    $check($value($result, 'client_device_count') === '2', 'Registered valid HWIDs counted');
    $check(!str_contains(json_encode($result), 'secret-device'), 'HWID identifiers not exposed');
    $panel->devicesUnsupported = true;
    $check($value($fields($service->read(14, 7)), 'client_device_count') === '—', 'HWID failure does not make client inspection fail or fabricate zero');
    $panel->global['client']['id'] = 'other-client';
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ClientVerificationException::class);
    foreach (['vless', 'vmess', 'trojan', 'shadowsocks'] as $protocol) {
        $panel = new InspectionPanel();
        db()->node = array_replace($node, ['protocol' => $protocol]);
        if (in_array($protocol, ['trojan', 'shadowsocks'], true)) {
            unset($panel->remote['id']); $panel->remote['password'] = 'fixture-credential';
        }
        $result = $service->read(14, 7);
        $check(!str_contains(json_encode($result), 'fixture-credential'), $protocol . ' credential exposed');
    }
    db()->node = $node;
    $panel = new InspectionPanel();
    $panel->traffic['obj']['email'] = 'foreign-client';
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ProvisioningException::class);
    $panel->traffic = ['obj' => [['email' => 'foreign-client', 'up' => 900, 'down' => 900], ['email' => 'fixture-client', 'up' => 1, 'down' => 2]]];
    $check($value($fields($service->read(14, 7)), 'profile_traffic_used') === '3 Б', 'Multi-client traffic response selects only this client');
    $panel->traffic = ['obj' => ['up' => 'corrupt', 'down' => 0]];
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException::class);
    $panel->missing = true;
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ProvisioningException::class);
    $panel->missing = false; $panel->offline = true;
    $expectError(static fn() => $service->read(14, 7), \RuntimeException::class);
    $panel->offline = false; $panel->remote['id'] = 'foreign-credential';
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ClientVerificationException::class);
    $before = $factories;
    foreach ([null, $node + [], array_replace($node, ['subscription_status' => 'deleted'])] as $invalid) {
        db()->node = $invalid;
        $expectError(static fn() => $service->read(15, 7), \Fireball\VpnManagerV2\Exceptions\ValidationException::class);
    }
    foreach (['deleted', 'deleting', 'pending_remote_delete', 'delete_failed'] as $status) {
        db()->node = array_replace($node, ['status' => $status]);
        $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ValidationException::class);
    }
    db()->node = $node; db()->transaction = true;
    $expectError(static fn() => $service->read(14, 7), \LogicException::class);
    db()->transaction = false; db()->server['is_enabled'] = 0;
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ProvisioningException::class);
    db()->server['is_enabled'] = 1; db()->inbound['server_id'] = 99;
    $expectError(static fn() => $service->read(14, 7), \Fireball\VpnManagerV2\Exceptions\ProvisioningException::class);
    $check($factories === $before, 'Invalid ownership/topology/deletion/transaction must stop before panel access');
    $GLOBALS['fixtureRole'] = 'user';
    $expectError(static fn() => (new \Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController())->clientInfo(), \RuntimeException::class);
    $check($factories === $before, 'Unauthorized user reached panel inspection');
    $GLOBALS['fixtureRole'] = 'creator';
    $router = new class {
        public array $routes = [];
        public function get(string $path, mixed $handler): self { $this->routes[] = ['path' => $path, 'method' => 'GET', 'handler' => $handler]; return $this; }
        public function post(string $path, mixed $handler): self { $this->routes[] = ['path' => $path, 'method' => 'POST', 'handler' => $handler]; return $this; }
        public function middleware(array $middleware): self { $this->routes[array_key_last($this->routes)]['middleware'] = $middleware; return $this; }
    };
    require dirname(__DIR__) . '/routes/admin.php';
    $route = array_values(array_filter($router->routes, static fn($route): bool => str_contains($route['path'], '/client-info')));
    $check(count($route) === 1 && $route[0]['method'] === 'GET' && $route[0]['middleware'] === ['auth', 'admin'], 'Inspection route must remain read-only and admin-protected');
    $summary = \Fireball\VpnManagerV2\Support\SubscriptionUsageSummary::class;
    $now = strtotime('2026-10-07 12:00:00');
    $subscription = ['id' => 14, 'traffic_used_bytes' => 90, 'traffic_limit_bytes' => 100, 'expires_at' => '2026-10-09 12:00:00'];
    $nodes = [array_replace($node, ['traffic_used_bytes' => 1, 'upload_bytes' => 20, 'download_bytes' => 30,
        'traffic_synced_at' => '2026-10-07 09:00:00', 'traffic_sync_status' => 'synced']),
        array_replace($node, ['id' => 8, 'status' => 'disabled', 'traffic_synced_at' => '2026-10-07 10:00:00', 'traffic_sync_status' => 'synced']),
        array_replace($node, ['id' => 9, 'status' => 'deleted']), array_replace($node, ['id' => 10, 'subscription_id' => 99])];
    $usage = $summary::from($subscription, $nodes, $now);
    $serverUsage = $summary::byServer($subscription, $nodes);
    $check(count($serverUsage) === 1 && $serverUsage[0]['id'] === 1 && $serverUsage[0]['connections'] === 2, 'Server grouping excludes foreign and deleted nodes');
    $check($serverUsage[0]['used'] === 1 && $serverUsage[0]['upload'] === 20 && $serverUsage[0]['download'] === 30, 'Sum only owned server counters');
    $check($serverUsage[0]['checked_at'] === '2026-10-07 09:00:00' && !$serverUsage[0]['partial'], 'Oldest sample and completeness per server');
    $serverUsage = $summary::byServer($subscription, [
        array_replace($nodes[0], ['server_name' => 'Same name']),
        array_replace($nodes[0], ['id' => 8, 'server_id' => 2, 'server_name' => 'Same name', 'traffic_used_bytes' => 80]),
        array_replace($node, ['id' => 9, 'server_id' => 3, 'upload_bytes' => 900, 'download_bytes' => 900]),
    ]);
    $check(count($serverUsage) === 3 && $serverUsage[1]['used'] === 80, 'Servers with identical display names remain separate');
    $check(!$serverUsage[2]['known'] && !$serverUsage[2]['sample_known'] && $serverUsage[2]['partial']
        && $serverUsage[2]['upload'] === 0, 'Unverified counters stay unknown, not factual zero or corrupt sample');
    $overflow = $summary::byServer($subscription, [array_replace($nodes[0], ['traffic_used_bytes' => PHP_INT_MAX]), $nodes[0]]);
    $check($overflow[0]['used'] === PHP_INT_MAX, 'Per-server integer overflow is bounded');
    $check($usage['used'] === 90 && $usage['remaining'] === 10 && $usage['percent'] === 90.0, 'Use aggregate CMS accounting, not per-node sum');
    $check($usage['checked_at'] === '2026-10-07 09:00:00' && !$usage['partial'], 'Oldest sample represents combined data age');
    $check($usage['connections'] === 2 && $usage['active_connections'] === 1, 'Foreign/deleted connections excluded');
    $check($usage['days_remaining'] === 2 && !$usage['lifetime'], 'Remaining days');
    $unknown = $summary::from(array_replace($subscription, ['traffic_used_bytes' => 0]), [$node], $now);
    $check(!$unknown['known'] && $unknown['percent'] === null && $unknown['partial'], 'Unverified zero must not become factual usage');
    $zero = $summary::from(array_replace($subscription, ['traffic_used_bytes' => 0]), [$nodes[0]], $now);
    $check($zero['known'] && $zero['percent'] === 0.0, 'Verified zero usage');
    foreach ([101, PHP_INT_MAX] as $used) {
        $usage = $summary::from(array_replace($subscription, ['traffic_used_bytes' => $used, 'expires_at' => '2026-10-01']), $nodes, $now);
        $check($usage['percent'] === 100 && $usage['remaining'] === 0 && $usage['days_remaining'] === 0, 'Over-limit and expired values clamp safely');
    }
    $usage = $summary::from(array_replace($subscription, ['traffic_limit_bytes' => null, 'expires_at' => null]), [], $now);
    $check($usage['percent'] === null && $usage['remaining'] === null && $usage['lifetime'], 'Lifetime/unlimited and no nodes');
    $nodes[1]['traffic_sync_status'] = 'failed';
    $check($summary::from($subscription, $nodes)['partial'], 'Failed sample must display saved/partial state');
    foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
        $translations = require dirname(__DIR__) . '/lang/' . $language . '.php';
        foreach (array_keys(require dirname(__DIR__) . '/lang/ru.php') as $key) {
            if (str_starts_with($key, 'vpn_manager_v2_client_')) $check(isset($translations[$key]) && $translations[$key] !== '', 'Missing client-info translation: ' . $language . '/' . $key);
        }
    }
    echo "PASS client information: $checks checks; read-only panel/database boundaries, ownership, metadata, traffic, missing data and translations\n";
}
