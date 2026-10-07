<?php
// Actual rename/identity/queue/sync services, isolated SQL and panel boundaries only.
namespace Fireball\VpnManagerV2\Repositories { function db(): object { return $GLOBALS['namingDb']; } }
namespace {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Fireball\\VpnManagerV2\\';
        if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });
    class FireballPluginVpnManagerV2 { public static function t(string $key): string { static $lang; $lang ??= require dirname(__DIR__) . '/lang/ru.php'; return $lang[$key] ?? $key; } }
    function db(): object { return $GLOBALS['namingDb']; }
    function cache(): object { static $cache; return $cache ??= new class { public function remove(string $key): void {} }; }
    function get_user(): array { return ['id' => 1, 'role' => 'user']; }
    function abort(string $message, int $code): never { throw new \RuntimeException('HTTP ' . $code); }
    $GLOBALS['namingDb'] = new class {
        public array $subscriptions = [], $nodes = [], $operations = [], $events = [], $queries = [];
        public array $user = ['id' => 1, 'name' => 'Кирилл', 'login' => 'creator'];
        public bool $failQueue = false, $transaction = false;
        public mixed $result = null;
        private int $lastId = 0, $affected = 0;
        public function beginTransaction(): void { if ($this->transaction) throw new \RuntimeException('Nested transaction'); $this->transaction = true; }
        public function commit(): void { $this->transaction = false; }
        public function rollBack(): void { $this->transaction = false; }
        public function inTransaction(): bool { return $this->transaction; }
        public function getOne(): mixed { return $this->result; }
        public function get(): array { return is_array($this->result) ? $this->result : []; }
        public function getColumn(): mixed { return $this->result; }
        public function rowCount(): int { return $this->affected; }
        public function getInsertId(): int { return $this->lastId; }
        public function query(string $query, array $params = []): self {
            $sql = preg_replace('/\s+/', ' ', trim($query)); $this->queries[] = [$sql, $params]; $this->affected = 0; $this->result = false;
            if (str_contains($sql, 'FROM users')) $this->result = $this->user;
            elseif (str_contains($sql, 'FROM vpn_v2_operations')) $this->result = $this->operations[$params[0]] ?? false;
            elseif (str_starts_with($sql, 'INSERT INTO vpn_v2_operations')) {
                if ($this->failQueue) throw new \RuntimeException('Fixture queue unavailable');
                $this->lastId++; $this->operations[$params[1]] = ['id' => $this->lastId, 'operation_id' => $params[0], 'operation_type' => $params[2], 'status' => 'pending', 'created_at' => $params[9], 'params' => $params];
            } elseif (str_starts_with($sql, 'INSERT INTO vpn_v2_events')) $this->events[] = $params;
            elseif (str_contains($sql, 'FROM vpn_v2_subscription_items')) $this->result = [];
            elseif (str_contains($sql, 'FROM vpn_v2_profiles')) $this->result = ['id' => 1, 'cms_user_id' => 1, 'shared_uuid' => $this->nodes[7]['client_uuid']];
            elseif (str_contains($sql, 'FROM vpn_v2_subscription_nodes')) {
                if (str_starts_with($sql, 'SELECT n.client_uuid')) $this->result = $this->nodes[7]['client_uuid'];
                elseif (str_contains($sql, 'WHERE subscription_id = ?')) $this->result = array_values(array_filter($this->nodes, static fn($node) => $node['subscription_id'] === (int)$params[0]));
                else $this->result = $this->nodes[(int)$params[0]] ?? false;
            } elseif (str_contains($sql, 'FROM vpn_v2_subscriptions')) $this->result = $this->subscriptions[(int)$params[0]] ?? false;
            elseif (str_starts_with($sql, 'UPDATE vpn_v2_subscriptions')) {
                $id = (int)$params[array_key_last($params)];
                if (str_contains($sql, 'SET client_display_name')) $this->subscriptions[$id]['client_display_name'] = $params[0];
                elseif (str_contains($sql, 'SET revision')) { $this->subscriptions[$id]['revision']++; $this->affected = 1; }
                elseif (!str_contains($sql, 'SET profile_id')) throw new \RuntimeException('Unexpected subscription mutation');
            } elseif (str_starts_with($sql, 'UPDATE vpn_v2_subscription_nodes')) {
                $id = (int)$params[3]; $owner = (int)$params[4]; $node = $this->nodes[$id] ?? null;
                if ($node && $node['subscription_id'] === $owner && !in_array($node['status'], ['deleted', 'deleting', 'pending_remote_delete'], true)) {
                    if (str_contains($sql, 'SET client_email')) { $this->nodes[$id]['client_email'] = $params[0]; $this->nodes[$id]['remote_client_name'] = $params[1]; $this->nodes[$id]['sync_status'] = 'identity_mismatch'; }
                    else { $this->nodes[$id]['sync_status'] = 'failed'; $this->nodes[$id]['sync_error'] = $params[0]; }
                    $this->affected = 1;
                }
            } else throw new \RuntimeException('Unexpected isolated SQL: ' . $sql);
            return $this;
        }
    };
    $checks = 0;
    $check = static function (bool $ok, string $message) use (&$checks): void { $checks++; if (!$ok) throw new \RuntimeException($message); };
    $service = new \Fireball\VpnManagerV2\Services\SubscriptionNamingService();
    $subscription = ['id' => 14, 'user_id' => 1, 'profile_id' => 1, 'plan_id' => 1, 'status' => 'active', 'revision' => 1,
        'manual_customer_name' => null, 'client_display_name' => null, 'starts_at' => date('Y-m-d H:i:s', time() - 3600),
        'expires_at' => date('Y-m-d H:i:s', time() + 86400), 'subscription_token' => 'fixture-not-real-token',
        'device_limit' => 2, 'ip_limit' => 1, 'traffic_limit_bytes' => 10000, 'traffic_used_bytes' => 300];
    $node = ['id' => 7, 'subscription_id' => 14, 'server_id' => 1, 'inbound_id' => 2, 'country_code' => 'DE',
        'protocol' => 'vless', 'client_uuid' => '00000000-0000-4000-8000-000000000017', 'client_sub_id' => 'fixture-private-sub',
        'remote_client_id' => 'fixture-stable-remote', 'client_email' => 'legacy', 'remote_client_name' => 'legacy',
        'flow' => null, 'traffic_limit_bytes' => 10000, 'traffic_used_bytes' => 300, 'desired_enabled' => 1, 'status' => 'active', 'sync_status' => 'synced'];
    db()->subscriptions = [14 => $subscription, 15 => array_replace($subscription, ['id' => 15])];
    db()->nodes = [7 => $node, 8 => array_replace($node, ['id' => 8, 'inbound_id' => 3, 'status' => 'disabled', 'desired_enabled' => 0]),
        9 => array_replace($node, ['id' => 9, 'status' => 'deleted']), 10 => array_replace($node, ['id' => 10, 'subscription_id' => 15])];
    $initialNodes = db()->nodes;
    $immutableSubscription = static fn(array $row) => array_diff_key($row, array_flip(['client_display_name', 'revision']));
    $immutableNode = static fn(array $row) => array_diff_key($row, array_flip(['client_email', 'remote_client_name', 'sync_status', 'sync_error']));
    $result = $service->rename(14, 'Новый клиент', 1);
    $check($result['queued'] === 2 && $result['failed'] === 0, 'Active and disabled owned connections queued');
    $check(db()->subscriptions[14]['client_display_name'] === 'Новый клиент', 'Separate display name persisted');
    $check(db()->nodes[7]['client_email'] === 'novyi-klient-creator-DE-s1-i2', 'Same established transliteration and unique target suffix');
    $check(db()->nodes[8]['client_email'] !== db()->nodes[7]['client_email'], 'Names unique across targets');
    $check(db()->nodes[9]['client_email'] === 'legacy' && db()->nodes[10]['client_email'] === 'legacy', 'Deleted and other subscribers untouched');
    $check($immutableSubscription(db()->subscriptions[14]) === $immutableSubscription($subscription), 'Term, access, traffic and subscription URL remain unchanged');
    foreach (array_keys($initialNodes) as $id) $check($immutableNode(db()->nodes[$id]) === $immutableNode($initialNodes[$id]), 'Credential/access/counters stay unchanged');
    $check(db()->user['name'] === 'Кирилл' && db()->user['login'] === 'creator', 'CMS account never renamed');
    foreach (db()->operations as $operation) {
        $check($operation['operation_type'] === 'rename_client' && $operation['params'][5] === 14 && $operation['params'][7] === null, 'Native rename queue, correct owner, no stale-name payload');
    }
    $service->rename(14, 'Другое имя', 1);
    $check(count(db()->operations) === 2 && str_starts_with(db()->nodes[7]['client_email'], 'drugoe-imya-'), 'Rapid rename reuses pending operations and latest desired label wins');
    db()->nodes[7]['sync_status'] = db()->nodes[8]['sync_status'] = 'synced';
    $check($service->rename(14, 'Другое имя', 1)['queued'] === 0, 'Confirmed unchanged name no-op');
    $identity = (new \Fireball\VpnManagerV2\Services\RemoteClientIdentityService())->forSubscription(db()->subscriptions[14],
        ['server_id' => 1, 'inbound_id' => 2, 'country_code' => 'DE', 'protocol' => 'vless']);
    $check($identity['remote_client_name'] === db()->nodes[7]['client_email'] && $identity['client_uuid'] === $node['client_uuid'], 'Future provisioning/recovery inherits alias without credential rotation');
    $service->rename(14, '', 1);
    $check(db()->subscriptions[14]['client_display_name'] === null && str_starts_with(db()->nodes[7]['client_email'], 'kirill-creator-'), 'Blank restores automatic name');
    foreach ([str_repeat('a', 161), "bad\nname", '!!!', [], "\xff"] as $invalid) {
        $before = db()->subscriptions;
        try { $service->rename(14, $invalid); throw new \LogicException('Accepted invalid'); }
        catch (\Fireball\VpnManagerV2\Exceptions\ValidationException) { $check(db()->subscriptions === $before, 'Invalid name rejected before mutation'); }
    }
    db()->subscriptions[14]['status'] = 'deleted';
    try { $service->rename(14, 'Blocked'); throw new \LogicException('Deleted renamed'); }
    catch (\Fireball\VpnManagerV2\Exceptions\ValidationException) { $check(true, 'Deleted subscription rejected'); }
    db()->subscriptions[14]['status'] = 'active'; db()->operations = []; db()->failQueue = true;
    $check($service->rename(14, 'Повтор')['failed'] === 2 && db()->nodes[7]['status'] === 'active' && db()->nodes[8]['status'] === 'disabled', 'Queue failure never disables or activates clients');
    db()->failQueue = false;
    $check($service->rename(14, 'Повтор')['queued'] === 2, 'Same-name save retries failed queue submission');
    db()->subscriptions[14]['user_id'] = null; db()->subscriptions[14]['manual_customer_name'] = 'Ручной клиент';
    $service->rename(14, 'Ручное имя');
    $check(str_starts_with(db()->nodes[7]['client_email'], 'ruchnoe-imya-manual-1-'), 'Manual subscribers supported too');

    // Actual read/merge/update/read path: only the client label changes, not its credential or panel metadata.
    $factory = new \Fireball\VpnManagerV2\Services\ClientPayloadFactory();
    foreach (['suspended', 'expired', 'traffic_exceeded'] as $status) {
        $check(!$factory->build(array_replace($subscription, ['status' => $status]), array_replace($node, ['client_email' => 'new-panel-name']))['enable'], 'Rename never reactivates inactive subscription');
    }
    $check(!$factory->build($subscription, array_replace($node, ['desired_enabled' => 0, 'client_email' => 'new-panel-name']))['enable'], 'Rename never enables an intentionally disabled connection');
    $expected = $factory->build($subscription, array_replace($node, ['client_email' => 'new-panel-name']));
    $panel = new class($factory->build($subscription, $node)) implements \Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface {
        public array $remote; public array $updates = [];
        public function __construct(array $record) { $this->remote = $record + ['up' => 100, 'down' => 200]; $this->remote['comment'] = 'preserve'; $this->remote['resetDay'] = 10; }
        public function authenticate(): void {}
        public function testConnection(): \Fireball\VpnManagerV2\DTO\ConnectionTestResult { throw new \LogicException('Unused'); }
        public function listInbounds(): array { throw new \LogicException('Broad panel read'); }
        public function getInbound(int $id): array { return ['settings' => json_encode(['clients' => [$this->remote]]), 'clientStats' => [['email' => $this->remote['email'], 'up' => 100, 'down' => 200]]]; }
        public function getClientTraffic(string $email): array { throw new \LogicException('Unused'); }
        public function findClient(int $id, string $clientId = '', string $email = ''): ?array { throw new \LogicException('Unused'); }
        public function addClient(int $id, array $client): array { throw new \LogicException('Credential replacement forbidden'); }
        public function updateClient(int $id, string $clientId, array $client): array { if ($clientId !== $this->remote['id']) throw new \LogicException('Credential changed'); $this->updates[] = $client; $this->remote = $client; return ['success' => true]; }
        public function deleteClient(int $id, string $clientId, ?string $email = null): array { throw new \LogicException('Delete forbidden'); }
        public function resetClientTraffic(int $id, string $email): array { throw new \LogicException('Counter reset forbidden'); }
    };
    $before = $panel->remote;
    $sync = (new \Fireball\VpnManagerV2\Services\RemoteClientSyncService())->applyClientState($panel, 42, array_replace($node, ['client_email' => 'new-panel-name']), $expected);
    $check($sync['changed_fields'] === ['email'] && count($panel->updates) === 1 && $panel->remote['email'] === 'new-panel-name', 'Actual panel update changes only email label and confirms it');
    $check(array_diff_key($before, ['email' => true]) === array_diff_key($panel->remote, ['email' => true]), 'Panel credential, metadata, automatic resets and policy preserved');
    $check($sync['enable'] === true && $sync['traffic_used_bytes'] === 300, 'Confirmed active client and counters after rename');
    try { (new \Fireball\VpnManagerV2\Controllers\Admin\SubscriptionController())->rename(); throw new \LogicException('Unauthorized'); }
    catch (\RuntimeException $error) { $check($error->getMessage() === 'HTTP 403', 'Unauthorized rename blocked before mutations'); }
    foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
        $lang = require dirname(__DIR__) . '/lang/' . $language . '.php';
        foreach (['title', 'action', 'help', 'reset_help', 'invalid', 'queued', 'saved', 'queue_error'] as $key) $check(!empty($lang['vpn_manager_v2_client_name_' . $key]), 'Rename translation missing');
    }
    echo "PASS subscription names: $checks checks; real name generation, persistence, queue dedup/retry, ownership, future identity, read/merge/update/confirm and unchanged credentials/access/limits/traffic; no live database or HTTP\n";
}
