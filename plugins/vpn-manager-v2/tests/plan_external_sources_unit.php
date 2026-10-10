<?php
// Real services/repositories/cipher, isolated in-memory SQL boundary. Never boots the CMS.
namespace Fireball\VpnManagerV2\Repositories {
    function db(): object { return $GLOBALS['planExternalDb']; }
}
namespace {
    define('CHAT_ENCRYPTION_KEY', 'isolated-vpn-plan-external-test-key-not-used-by-any-site');
    require dirname(__DIR__, 3) . '/app/Services/ChatCipher.php';
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Fireball\\VpnManagerV2\\';
        if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });
    class FireballPluginVpnManagerV2 {
        const SLUG = 'vpn-manager-v2';
        public static function t(string $key): string {
            static $lang; $lang ??= require dirname(__DIR__) . '/lang/ru.php'; return $lang[$key] ?? $key;
        }
    }
    function db(): object { return $GLOBALS['planExternalDb']; }
    function cache(): object { return $GLOBALS['planExternalCache']; }
    function get_user(): array { return ['id' => 1, 'role' => $GLOBALS['fixtureRole'] ?? 'creator']; }
    function abort(string $message, int $code): never { throw new \RuntimeException('HTTP ' . $code); }
    function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function base_href(string $path): string { return $path; }
    function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
    $GLOBALS['planExternalCache'] = new class {
        public array $removed = [];
        private array $values = [];
        public function get(string $key): mixed { return $this->values[$key] ?? null; }
        public function set(string $key, mixed $value, int $ttl): void { $this->values[$key] = $value; }
        public function remove(string $key): void { $this->removed[] = $key; unset($this->values[$key]); }
    };
    $GLOBALS['planExternalDb'] = new class {
        public array $sources = [], $subscriptions = [], $parents = [], $events = [], $queries = [];
        public array $plans = [1 => ['id' => 1], 2 => ['id' => 2], 3 => ['id' => 3]];
        public mixed $result = null;
        private int $insertId = 0, $affected = 0;
        private ?array $backup = null;
        public function beginTransaction(): void { if ($this->backup !== null) throw new \RuntimeException('Nested transaction'); $this->backup = $this->sources; }
        public function commit(): void { $this->backup = null; }
        public function rollBack(): void { $this->sources = $this->backup ?? []; $this->backup = null; }
        public function inTransaction(): bool { return $this->backup !== null; }
        public function rowCount(): int { return $this->affected; }
        public function getInsertId(): int { return $this->insertId; }
        public function getOne(): mixed { return $this->result; }
        public function get(): array { return is_array($this->result) ? $this->result : []; }
        public function getColumn(): mixed { return $this->result; }
        public function query(string $query, array $params = []): self {
            $sql = preg_replace('/\s+/', ' ', trim($query));
            $this->queries[] = [$sql, $params]; $this->affected = 0; $this->result = false;
            if (str_contains($sql, 'FROM vpn_v2_plans WHERE')) { $this->result = $this->plans[(int)$params[0]] ?? false; }
            elseif (str_contains($sql, 'INSERT INTO vpn_v2_events')) { $this->events[] = $params; }
            elseif (str_starts_with($sql, 'UPDATE vpn_v2_subscriptions SET revision')) {
                $id = (int)$params[array_key_last($params)];
                if (isset($this->subscriptions[$id])) { $this->subscriptions[$id]['revision']++; $this->affected = 1; }
            } elseif (str_contains($sql, 'FROM vpn_v2_subscription_items')) {
                // Revision propagation, plus empty dependencies for builder tests.
                $this->result = str_contains($sql, ' AS id') ? array_map(static fn($id) => ['id' => $id], $this->parents[(int)$params[0]] ?? []) : [];
                if (str_contains($sql, 'LIMIT 1')) $this->result = false;
            } elseif (str_contains($sql, 'FROM vpn_v2_subscription_nodes')) { $this->result = []; }
            elseif (str_contains($sql, 'FROM plugin_settings')) { $this->result = []; }
            elseif (str_contains($sql, 'FROM vpn_v2_subscriptions')) {
                if (str_contains($sql, 'WHERE plan_id = ?')) {
                    $this->result = array_values(array_filter($this->subscriptions, static fn($row) => $row['plan_id'] === (int)$params[0] && !in_array($row['status'], ['deleted', 'deleting'], true)));
                } else {
                    $row = $this->subscriptions[(int)$params[0]] ?? false;
                    if (str_starts_with($sql, 'SELECT plan_id')) $this->result = $row && !in_array($row['status'], ['deleted', 'deleting'], true) ? $row['plan_id'] : 0;
                    else $this->result = $row;
                }
            } elseif (str_starts_with($sql, 'INSERT INTO vpn_v2_external_sources')) {
                $owner = str_contains($sql, '(plan_id,') ? 'plan_id' : 'parent_subscription_id';
                $id = ++$this->insertId;
                $this->sources[$id] = ['id' => $id, 'plan_id' => null, 'parent_subscription_id' => null,
                    $owner => (int)$params[0], 'source_type' => $params[1], 'name' => $params[2],
                    'encrypted_source' => $params[3], 'source_hash' => $params[4], 'source_preview' => $params[5],
                    'encrypted_snapshot' => $params[6], 'snapshot_hash' => $params[7], 'config_count' => $params[8],
                    'is_enabled' => 1, 'sort_order' => $params[9], 'sync_status' => 'synced', 'last_sync_at' => $params[10],
                    'relation_key' => $params[11], 'created_by' => $params[12], 'created_at' => $params[13],
                    'updated_at' => $params[14], 'deleted_at' => null, 'last_error' => null];
                $this->affected = 1;
            } elseif (str_starts_with($sql, 'SELECT') && str_contains($sql, 'FROM vpn_v2_external_sources')) {
                $rows = array_values($this->sources);
                if (preg_match('/WHERE (plan_id|parent_subscription_id) = \?/', $sql, $match)) {
                    $rows = array_values(array_filter($rows, static fn($row) => (int)($row[$match[1]] ?? 0) === (int)$params[0]));
                } elseif (preg_match('/WHERE id = \? AND (plan_id|parent_subscription_id) = \?/', $sql, $match)) {
                    $rows = array_values(array_filter($rows, static fn($row) => $row['id'] === (int)$params[0] && (int)($row[$match[1]] ?? 0) === (int)$params[1]));
                }
                if (str_contains($sql, 'deleted_at IS NULL')) $rows = array_values(array_filter($rows, static fn($row) => $row['deleted_at'] === null));
                if (str_contains($sql, 'relation_key = ?')) $rows = array_values(array_filter($rows, static fn($row) => $row['relation_key'] === $params[1]));
                if (str_contains($sql, 'is_enabled = 1')) $rows = array_values(array_filter($rows, static fn($row) => $row['is_enabled'] === 1));
                if (str_contains($sql, "source_type = 'subscription_url'")) $rows = array_values(array_filter($rows, static fn($row) => $row['source_type'] === 'subscription_url'));
                if (str_contains($sql, 'encrypted_snapshot IS NOT NULL')) $rows = array_values(array_filter($rows, static fn($row) => !empty($row['encrypted_snapshot'])));
                usort($rows, static fn($left, $right) => [$left['sort_order'], $left['id']] <=> [$right['sort_order'], $right['id']]);
                if (str_contains($sql, 'MAX(sort_order)')) $this->result = $rows ? max(array_column($rows, 'sort_order')) + 10 : 10;
                elseif (str_contains($sql, 'SUM(config_count)')) $this->result = array_sum(array_column($rows, 'config_count'));
                elseif (str_contains($sql, 'LIMIT 1')) $this->result = $rows[0] ?? false;
                elseif (str_starts_with($sql, 'SELECT encrypted_snapshot')) $this->result = array_map(static fn($row) => ['encrypted_snapshot' => $row['encrypted_snapshot']], $rows);
                elseif (str_starts_with($sql, 'SELECT *')) $this->result = $rows;
                else $this->result = array_map(static fn($row) => array_diff_key($row, array_flip(['encrypted_source', 'encrypted_snapshot'])), $rows);
            } elseif (str_starts_with($sql, 'UPDATE vpn_v2_external_sources')) {
                if (str_contains($sql, 'SET encrypted_snapshot')) {
                    $id = (int)$params[5]; if (isset($this->sources[$id])) $this->sources[$id] = array_replace($this->sources[$id], ['encrypted_snapshot' => $params[0], 'snapshot_hash' => $params[1], 'config_count' => $params[2], 'sync_status' => 'synced', 'last_sync_at' => $params[3], 'last_error' => null]);
                } elseif (str_contains($sql, "SET sync_status = 'sync_error'")) {
                    $id = (int)$params[2]; if (isset($this->sources[$id])) $this->sources[$id] = array_replace($this->sources[$id], ['sync_status' => 'sync_error', 'last_error' => $params[0]]);
                } else {
                    $owner = str_contains($sql, 'AND plan_id = ?') ? 'plan_id' : 'parent_subscription_id';
                    if (str_contains($sql, 'SET sort_order')) { $id = (int)$params[2]; $ownerId = (int)$params[3]; $changes = ['sort_order' => $params[0]]; }
                    elseif (str_contains($sql, 'SET is_enabled = ?')) { $id = (int)$params[3]; $ownerId = (int)$params[4]; $changes = ['is_enabled' => $params[0], 'sync_status' => $params[0] ? 'synced' : 'disabled']; }
                    else { $id = (int)$params[2]; $ownerId = (int)$params[3]; $changes = ['is_enabled' => 0, 'sync_status' => 'detached', 'relation_key' => null, 'deleted_at' => $params[0]]; }
                    if (isset($this->sources[$id]) && (int)($this->sources[$id][$owner] ?? 0) === $ownerId && $this->sources[$id]['deleted_at'] === null) {
                        $this->sources[$id] = array_replace($this->sources[$id], $changes); $this->affected = 1;
                    }
                }
            } else { throw new \RuntimeException('Unexpected SQL in isolated test: ' . $sql); }
            return $this;
        }
    };
    foreach ([14 => 1, 15 => 1, 16 => 1, 17 => 2, 30 => 3, 1 => 2] as $id => $planId) {
        db()->subscriptions[$id] = ['id' => $id, 'plan_id' => $planId, 'status' => $id === 16 ? 'deleted' : 'active', 'user_id' => 1,
            'subscription_token' => 'fixture-token-' . $id, 'revision' => 1, 'traffic_limit_bytes' => null,
            'starts_at' => date('Y-m-d H:i:s', time() - 3600), 'expires_at' => date('Y-m-d H:i:s', time() + 86400)];
    }
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'plan_external_sources_render.php') { return; }
    $checks = 0;
    $check = static function (bool $ok, string $message) use (&$checks): void { $checks++; if (!$ok) throw new \RuntimeException($message); };
    $expectError = static function (callable $action) use ($check): void {
        try { $action(); } catch (\InvalidArgumentException|\Fireball\VpnManagerV2\Exceptions\ValidationException) { $check(true, 'Rejected'); return; }
        throw new \RuntimeException('Expected rejection');
    };
    $uri = 'vless://00000000-0000-4000-8000-000000000017@vpn.example.test:443?encryption=none&security=tls&type=tcp#Shared';
    $personalUri = str_replace('000017', '000018', $uri);
    $fetches = 0; $payload = base64_encode($uri); $failFetch = false;
    $fetcher = static function () use (&$fetches, &$payload, &$failFetch): string {
        $fetches++; if (db()->inTransaction()) throw new \RuntimeException('Network inside transaction');
        if ($failFetch) throw new \Fireball\VpnManagerV2\Exceptions\ValidationException('fixture fetch failure'); return $payload;
    };
    $planService = new \Fireball\VpnManagerV2\Services\ExternalVpnSourceService(forPlan: true, fetcher: $fetcher);
    $personalService = new \Fireball\VpnManagerV2\Services\ExternalVpnSourceService(fetcher: $fetcher);
    $shared = $planService->attachConnection(1, $uri, 'Shared connection', 1);
    $check(db()->sources[$shared]['plan_id'] === 1 && db()->sources[$shared]['parent_subscription_id'] === null, 'Plan source has exactly its plan owner');
    $check(db()->subscriptions[14]['revision'] === 2 && db()->subscriptions[15]['revision'] === 2, 'Plan source invalidates all subscribers');
    $check(db()->subscriptions[16]['revision'] === 1 && db()->subscriptions[17]['revision'] === 1, 'Other plan and deleted subscriber unaffected');
    $check(count(cache()->removed) === 12, 'Old and new public cache formats invalidated');
    $check(!str_contains(db()->sources[$shared]['encrypted_source'], $uri) && !str_contains(db()->sources[$shared]['encrypted_snapshot'], $uri), 'Encrypted credentials and snapshot at rest');
    $check(!str_contains(json_encode($planService->itemsForParent(1)), '00000000-0000-4000'), 'Admin list hides credentials');
    $check($personalService->urisForParent(14) === [$uri] && $personalService->urisForParent(15) === [$uri], 'Existing subscriptions inherit shared source');
    $check($personalService->urisForParent(17) === [], 'Other plan cannot inherit source');
    $check($personalService->itemsForParent(14) === [] && count($personalService->planItemsForParent(14)) === 1, 'Inherited and personal admin lists remain distinct');
    $personal = $personalService->attachConnection(14, $personalUri, 'Personal connection', 1);
    $check($personalService->urisForParent(14) === [$personalUri, $uri] && $personalService->urisForParent(15) === [$uri], 'Personal sources retain priority and remain private to subscription');
    $check($personalService->configCountForSubscriptions([14, 15, 14]) === 2, 'Shared plan counted once across subscriptions');
    $expectError(static fn() => $planService->attachConnection(1, $uri, 'Duplicate'));
    $check(!$planService->toggle(2, $shared, false) && !$personalService->toggle(1, $shared, false), 'Wrong plan and same-number subscription cannot toggle plan source');
    $check(!$planService->detach(2, $shared) && !$personalService->detach(1, $shared), 'Wrong scope cannot detach shared source');
    $expectError(static fn() => $personalService->sync(1, $shared));
    $expectError(static fn() => $planService->sync(2, $shared));
    $expectError(static fn() => $planService->attachConnection(999, $uri));
    $check($fetches === 0, 'Invalid ownership and direct URI never fetch a URL');
    $planService->toggle(1, $shared, false);
    $check($personalService->urisForParent(14) === [$personalUri] && $personalService->urisForParent(15) === [], 'Disable affects every subscriber, not personal source');
    $planService->toggle(1, $shared, true);
    $sourceUrl = 'https://subscriptions.example.test/private-token';
    $remote = $planService->attachSubscription(1, $sourceUrl, 'External subscription', 1);
    $check($fetches === 1 && !str_contains(db()->sources[$remote]['source_preview'], 'private-token'), 'URL fetched through existing parser, token masked');
    $expectError(static fn() => $planService->reorder(1, [$remote, $personal]));
    $check($planService->reorder(1, [$remote, $shared]), 'Plan source order updated');
    $check(array_column($planService->itemsForParent(1), 'id') === [$remote, $shared], 'Stable plan order');
    $check(!$planService->reorder(1, [$remote, $shared]), 'Identical order is no-op');
    $expectError(static fn() => $planService->reorder(1, [$remote, $remote]));
    $payload = base64_encode(str_replace('000017', '000019', $uri));
    $planService->sync(1, $remote);
    $check(str_contains(implode('', $personalService->urisForParent(15)), '000019'), 'Refreshing plan URL updates all subscribers without copying sources');
    $failFetch = true; $saved = $personalService->urisForParent(15);
    $expectError(static fn() => $planService->sync(1, $remote));
    $check($personalService->urisForParent(15) === $saved && db()->sources[$remote]['sync_status'] === 'sync_error', 'Failed refresh retains last confirmed snapshot, never substitutes another source');
    $failFetch = false;
    db()->parents[14] = [30]; $parentRevision = db()->subscriptions[30]['revision'];
    $planService->toggle(1, $shared, false);
    $check(db()->subscriptions[30]['revision'] > $parentRevision, 'Inherited plan changes invalidate dependent parents too');
    db()->parents = []; $planService->toggle(1, $shared, true);
    db()->subscriptions[15]['plan_id'] = 2;
    $check($personalService->urisForParent(15) === [], 'Changing a subscription plan immediately stops inheritance from old plan');
    db()->subscriptions[15]['plan_id'] = 1;
    $builder = new \Fireball\VpnManagerV2\Services\VpnSubscriptionBuilder();
    $check(count($builder->build(db()->subscriptions[14])) === 3, 'Real public builder merges own and plan external configurations with technical deduplication');
    foreach (['expired', 'suspended', 'traffic_exceeded', 'deleted'] as $inactive) {
        $check($builder->build(array_replace(db()->subscriptions[14], ['status' => $inactive])) === [], 'Inactive subscription must not expose inherited configs');
    }
    $firstParty = str_replace('#Shared', '#First-party', $uri);
    $check($builder->mergeUris([$firstParty], [$uri]) === [$firstParty], 'First-party config wins technical duplicates');
    $planService->detach(1, $shared);
    $check(!in_array($uri, $personalService->urisForParent(15), true) && in_array($personalUri, $personalService->urisForParent(14), true), 'Detaching plan source affects all subscribers, preserves personal source');
    $readded = $planService->attachConnection(1, $uri, 'Re-added', 1);
    $check($readded !== $shared, 'Detached source can be safely re-added');
    $personalRemote = $personalService->attachSubscription(14, $sourceUrl, 'Personal URL', 1);
    $check($personalRemote !== $remote, 'Same URL allowed in separate scopes');
    $before = $fetches; $batch = $personalService->syncAll(20);
    $check($batch['synced'] === 2 && $batch['failed'] === 0 && $fetches === $before + 2, 'Existing scheduler refreshes both plan and subscription URLs');
    $check(count(db()->sources) === 5, 'No credential copies per subscriber or per refresh');
    foreach (db()->events as $event) {
        $check(!str_contains((string)$event[6], 'private-token') && !str_contains((string)$event[6], 'vless://'), 'Audit event contains no credentials');
        if (str_starts_with($event[0], 'plan.')) $check($event[1] === null && json_decode($event[6], true)['plan_id'] === 1, 'Plan audit event must not point to same-number subscription');
    }
    $router = new class {
        public array $routes = [];
        public function get(string $path, mixed $handler): self { $this->routes[] = ['method' => 'GET', 'path' => $path]; return $this; }
        public function post(string $path, mixed $handler): self { $this->routes[] = ['method' => 'POST', 'path' => $path]; return $this; }
        public function middleware(array $middleware): self { $this->routes[array_key_last($this->routes)]['middleware'] = $middleware; return $this; }
    };
    require dirname(__DIR__) . '/routes/admin.php';
    $routes = array_values(array_filter($router->routes, static fn($route) => str_contains($route['path'], '/plans/') && str_contains($route['path'], '/external/')));
    $check(count($routes) === 6, 'All plan external actions registered');
    foreach ($routes as $route) $check($route['method'] === 'POST' && $route['middleware'] === ['auth', 'admin'], 'Mutations remain admin/auth protected POSTs');
    $GLOBALS['fixtureRole'] = 'user';
    foreach (['attachExternalSubscription', 'attachExternalConnection', 'syncExternalSource', 'toggleExternalSource', 'detachExternalSource', 'updateExternalSourceOrder'] as $method) {
        try { (new \Fireball\VpnManagerV2\Controllers\Admin\PlanController())->$method(); throw new \LogicException('Access granted'); }
        catch (\RuntimeException $error) { $check($error->getMessage() === 'HTTP 403', 'Unauthorized plan action blocked before side effects'); }
    }
    $sql = file_get_contents(dirname(__DIR__) . '/migrations/017_add_plan_external_sources.sql');
    $check(str_contains($sql, 'MODIFY parent_subscription_id BIGINT UNSIGNED NULL') && str_contains($sql, '(plan_id, relation_key)') && !preg_match('/\b(DROP|DELETE|TRUNCATE)\b/i', $sql), 'Migration preserves subscription sources and adds scoped uniqueness');
    foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
        $lang = require dirname(__DIR__) . '/lang/' . $language . '.php';
        foreach (['title', 'help', 'save_first', 'create_help', 'inherited_title', 'inherited_help'] as $key) $check(!empty($lang['vpn_manager_v2_plan_external_' . $key]), 'Missing translation');
    }
    echo "PASS plan external sources: $checks checks; actual cipher, CRUD scopes, inheritance, revisions/cache/parents, builder access guard/deduplication, scheduler, ownership and routes; no live database or HTTP\n";
}
