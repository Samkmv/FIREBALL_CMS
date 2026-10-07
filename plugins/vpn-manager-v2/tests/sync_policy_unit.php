<?php
// Real synchronization policy/repository, with an in-memory database boundary.
namespace Fireball\VpnManagerV2\Repositories {
    function db(): object { return $GLOBALS['syncFixture']; }
}
namespace {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Fireball\\VpnManagerV2\\';
        if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });
    class FireballPluginVpnManagerV2 {
        public static function t(string $key): string { return $key; }
    }
    $GLOBALS['syncFixture'] = new class {
        public array $node = [];
        public array $current = [];
        public string $sql = '';
        public ?string $savedStatus = null;
        private bool $transaction = false;
        public function beginTransaction(): void { $this->transaction = true; }
        public function commit(): void { $this->transaction = false; }
        public function rollBack(): void { $this->transaction = false; }
        public function inTransaction(): bool { return $this->transaction; }
        public function query(string $sql, array $params = []): self {
            $this->sql = $sql;
            if (str_contains($sql, 'UPDATE vpn_v2_subscription_nodes')) {
                $this->savedStatus = str_contains($sql, 'protocol = ?') ? $params[9] : $params[3];
            }
            return $this;
        }
        public function get(): array {
            $node = $this->node;
            // The fixture only supplies aggregate usage if production SQL selects it.
            if (str_contains($this->sql, 'sub.traffic_used_bytes AS subscription_traffic_used_bytes')) {
                $node['subscription_traffic_used_bytes'] = $GLOBALS['subscription']['traffic_used_bytes'];
            }
            return [$node];
        }
        public function getOne(): array { return $this->current; }
    };
    $checks = 0;
    $check = static function (bool $condition, string $message) use (&$checks): void {
        $checks++; if (!$condition) throw new \RuntimeException($message);
    };
    $repository = new \Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository();
    $service = new \Fireball\VpnManagerV2\Services\ConfigurationSyncService();
    $mismatch = new \ReflectionMethod($service, 'remotePolicyMismatch');
    $factory = new \Fireball\VpnManagerV2\Services\ClientPayloadFactory();
    foreach ([0, 20, 99, 100, 150] as $used) {
        foreach (['active', 'expired', 'suspended', 'traffic_exceeded', 'future_start', 'lifetime', 'disabled_node'] as $case) {
            $subscription = ['status' => 'active', 'starts_at' => date('Y-m-d H:i:s', time() - 3600),
                'expires_at' => date('Y-m-d H:i:s', time() + 86400), 'device_limit' => 2, 'ip_limit' => 0,
                'traffic_limit_bytes' => 100, 'traffic_used_bytes' => $used];
            if (in_array($case, ['expired', 'suspended', 'traffic_exceeded'], true)) $subscription['status'] = $case;
            if ($case === 'expired') $subscription['expires_at'] = date('Y-m-d H:i:s', time() - 1);
            if ($case === 'future_start') $subscription['starts_at'] = date('Y-m-d H:i:s', time() + 3600);
            if ($case === 'lifetime') $subscription['expires_at'] = null;
            $node = ['id' => 7, 'subscription_id' => 14, 'server_id' => 1, 'inbound_id' => 2,
                'protocol' => 'vless', 'client_uuid' => '00000000-0000-4000-8000-000000000007',
                'client_email' => 'fixture', 'desired_enabled' => (int)($case !== 'disabled_node'),
                'traffic_limit_bytes' => 100, 'traffic_used_bytes' => 10, // Not aggregate usage.
                'subscription_status' => $subscription['status'], 'starts_at' => $subscription['starts_at'],
                'expires_at' => $subscription['expires_at'], 'device_limit' => 2, 'ip_limit' => 0,
                'subscription_traffic_limit_bytes' => 100];
            $GLOBALS['subscription'] = $subscription; $GLOBALS['syncFixture']->node = $node;
            $node = $repository->nodesForServer(1)[0];
            $remote = $factory->build($subscription, $node); // The worker's full subscription state.
            $check(!$mismatch->invoke($service, $node, $remote, 'fixture'), "$case/$used: inventory and worker disagree, causing repeated updates");
            $remote['enable'] = !$remote['enable'];
            $check($mismatch->invoke($service, $node, $remote, 'fixture'), 'Real access mismatch was ignored');
        }
    }
    foreach ([true, false] as $changed) {
        foreach ([0, 1] as $desired) {
            foreach ([false, true] as $enabled) {
                $node = $GLOBALS['syncFixture']->node;
                $GLOBALS['syncFixture']->current = $node + ['lkg_snapshot_version' => 1,
                    'lkg_snapshot_hash' => $changed ? 'previous' : 'same', 'lkg_snapshot_json' => '{}'];
                $GLOBALS['syncFixture']->current['desired_enabled'] = $desired;
                $repository->storeSnapshot($node, ['protocol' => 'vless', 'enable' => $enabled], 'same', 'fixture');
                $check($GLOBALS['syncFixture']->savedStatus === ($enabled ? 'active' : 'disabled'), 'Snapshot stores manual intent instead of factual panel enable');
                $check($GLOBALS['syncFixture']->current['desired_enabled'] === $desired, 'Factual snapshot changed desired enable');
            }
        }
    }
    echo "PASS inventory/worker policy and factual snapshots: $checks checks, no real database or panels\n";
}
