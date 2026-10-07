<?php
// Isolated queue and panel fixtures: never boot the CMS or contact a real panel.
namespace Fireball\VpnManagerV2\Repositories {
    function db(): object { return $GLOBALS['queueFixture']; }
    class PlanReconciliationRepository {
        public function node(int $id): ?array { return $GLOBALS['nodes'][$id] ?? null; }
        public function subscription(int $id): ?array { return $GLOBALS['subscriptions'][$id] ?? null; }
    }
    class SubscriptionRepository {
        public function updateNodeConfirmed(int $id, mixed $flow, mixed $limit, mixed $used, string $status, mixed $desired): void {
            $GLOBALS['confirmed'][] = compact('id', 'status', 'desired');
        }
        public function markNodeDeleted(int $id): void { $GLOBALS['nodes'][$id]['status'] = 'deleted'; }
        public function allNodesFinalizable(int $id): bool { return false; }
    }
    class SyncAuditRepository {
        public function log(array $entry): void { $GLOBALS['audit'][] = $entry; }
    }
}
namespace Fireball\VpnManagerV2\Services {
    class ConfigurationSyncService {
        public function syncServer(int $id, string $source, string $operation): array {
            $GLOBALS['scans'][] = $id;
            return ['processed' => 1, 'errors' => 0];
        }
    }
    class RemoteClientSyncService {
        public function push(array $node, array $subscription): array {
            if (!empty($GLOBALS['failPanel'])) throw new \RuntimeException('fixture panel unavailable');
            $payload = (new ClientPayloadFactory())->build($subscription, $node);
            $GLOBALS['pushes'][] = $payload;
            return ['enable' => $payload['enable'], 'remote_updated' => true];
        }
    }
    class VpnV2SubscriptionDependencyService {
        public function countActiveConsumers(int $node, int $subscription): int { return 0; }
    }
    class RemoteClientDeletionService {
        public function delete(array $node): void { $GLOBALS['deletions'][] = $node['id']; }
    }
}
namespace {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Fireball\\VpnManagerV2\\';
        if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    });
    class FireballPluginVpnManagerV2 {
        public static function t(string $key): string {
            static $language;
            $language ??= require dirname(__DIR__) . '/lang/ru.php';
            return $language[$key] ?? $key;
        }
    }
    class QueueFixture {
        public array $row;
        public string $sql = '';
        public array $params = [];
        private bool $transaction = false;
        private int $affected = 0;
        public function beginTransaction(): void { $this->transaction = true; }
        public function commit(): void { $this->transaction = false; }
        public function rollBack(): void { $this->transaction = false; }
        public function inTransaction(): bool { return $this->transaction; }
        public function rowCount(): int { return $this->affected; }
        public function query(string $sql, array $params = []): self {
            $this->sql = $sql; $this->params = $params; $this->affected = 0;
            if (str_contains($sql, "SET status = 'running'")) {
                if (in_array($this->row['status'], ['pending', 'retry'], true)) {
                    $this->row['status'] = 'running'; $this->row['attempts']++; $this->affected = 1;
                }
            } elseif (str_contains($sql, "SET status = 'cancelled'")) {
                if ($this->row['status'] === 'running') {
                    $this->row['status'] = 'cancelled'; $this->row['last_error'] = $params[0];
                    $this->row['idempotency_key'] = null; $this->row['lease_until'] = null;
                    $this->row['finished_at'] = $params[1]; $this->affected = 1;
                }
            } elseif (str_contains($sql, 'SET status = ?')) {
                $this->row['status'] = $params[0];
                if (str_contains($sql, 'processed_count = ?')) {
                    $this->row['processed_count'] = $params[1]; $this->row['last_error'] = null;
                }
            }
            return $this;
        }
        public function getOne(): ?array {
            if (str_contains($this->sql, 'SELECT attempts')) return $this->row;
            return in_array($this->row['status'], ['pending', 'retry'], true) ? $this->row : null;
        }
    }
    function check(bool $condition, string $message): void {
        $GLOBALS['checks']++;
        if (!$condition) throw new \RuntimeException($message);
    }
    function fixture(string $type = 'update_client'): void {
        $GLOBALS['queueFixture'] = new QueueFixture();
        $GLOBALS['queueFixture']->row = ['id' => 1, 'operation_id' => '00000000-0000-4000-8000-000000000001',
            'operation_type' => $type, 'source' => 'reconciliation', 'server_id' => 1,
            'subscription_id' => 14, 'connection_id' => 7, 'status' => 'pending',
            'attempts' => 0, 'max_attempts' => 8, 'payload_json' => null, 'processed_count' => 0,
            'idempotency_key' => 'fixture', 'lease_until' => null];
        $GLOBALS['subscriptions'] = [14 => ['id' => 14, 'status' => 'active',
            'starts_at' => date('Y-m-d H:i:s', time() - 3600),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400),
            'traffic_limit_bytes' => 100, 'traffic_used_bytes' => 20]];
        $GLOBALS['nodes'] = [7 => ['id' => 7, 'subscription_id' => 14, 'server_id' => 1,
            'status' => 'active', 'desired_enabled' => 1, 'is_obsolete' => 0, 'protocol' => 'vless',
            'client_uuid' => '00000000-0000-4000-8000-000000000007', 'client_email' => 'fixture']];
        foreach (['pushes', 'confirmed', 'scans', 'audit', 'deletions'] as $key) $GLOBALS[$key] = [];
        $GLOBALS['failPanel'] = false;
    }
    $GLOBALS['checks'] = 0;
    $processor = new \Fireball\VpnManagerV2\Services\RemoteOperationProcessor();
    foreach (['sync_client', 'create_client', 'update_client', 'rename_client', 'enable_client',
        'disable_client', 'delete_client', 'reset_traffic'] as $type) {
        foreach (['missing_subscription', 'different_owner', 'different_server', 'missing_node'] as $case) {
            fixture($type);
            if ($case === 'missing_subscription') {
                unset($GLOBALS['subscriptions'][14]);
                $GLOBALS['nodes'][7]['subscription_id'] = 15;
                $GLOBALS['subscriptions'][15] = ['id' => 15, 'status' => 'active'];
            }
            if ($case === 'different_owner') $GLOBALS['nodes'][7]['subscription_id'] = 15;
            if ($case === 'different_server') $GLOBALS['nodes'][7]['server_id'] = 2;
            if ($case === 'missing_node') $GLOBALS['nodes'] = [];
            $result = $processor->processNext();
            check($GLOBALS['queueFixture']->row['status'] === 'cancelled', "$type/$case: stale task was not cancelled");
            check(($result['success'] ?? 0) === 0 && ($result['cancelled'] ?? 0) === 1, 'Cancellation was reported as success');
            check($GLOBALS['pushes'] === [] && $GLOBALS['scans'] === [] && $GLOBALS['confirmed'] === []
                && $GLOBALS['deletions'] === [], 'Stale task touched another connection');
            check($GLOBALS['audit'][0]['status'] === 'cancelled' && !empty($GLOBALS['queueFixture']->row['last_error']), 'Cancellation lacks a safe explanation');
        }
    }
    foreach (['deleted', 'deleting', 'pending_remote_delete', 'delete_failed'] as $status) {
        fixture(); $GLOBALS['nodes'][7]['status'] = $status;
        check($processor->processNext()['cancelled'] === 1, 'Update resurrected a removed connection');
        check($GLOBALS['pushes'] === [], 'Removed connection reached the panel');
    }
    fixture('sync_subscription'); $GLOBALS['subscriptions'] = [];
    check($processor->processNext()['cancelled'] === 1, 'Missing subscription repair entered retry');
    fixture(); $GLOBALS['subscriptions'][14]['status'] = 'deleted';
    check($processor->processNext()['cancelled'] === 1 && $GLOBALS['pushes'] === [], 'Deleted subscription reached the panel');
    fixture(); $GLOBALS['nodes'][7]['is_obsolete'] = 1;
    check($processor->processNext()['cancelled'] === 1 && $GLOBALS['pushes'] === [], 'Obsolete tariff node reached the panel');
    fixture('delete_client');
    check($processor->processNext()['cancelled'] === 1 && $GLOBALS['deletions'] === [], 'Old deletion removed a restored active connection');
    foreach (['deleting', 'pending_remote_delete', 'delete_failed'] as $status) {
        fixture('delete_client'); $GLOBALS['nodes'][7]['status'] = $status;
        $GLOBALS['subscriptions'][14]['status'] = 'pending_remote_delete';
        check($processor->processNext()['success'] === 1 && $GLOBALS['deletions'] === [7], 'Legitimate cleanup retry was cancelled');
    }
    fixture('sync_client');
    check($processor->processNext()['success'] === 1 && $GLOBALS['scans'] === [1], 'Valid client inventory scan was cancelled');
    fixture('sync_server'); $GLOBALS['subscriptions'] = []; $GLOBALS['nodes'] = [];
    check($processor->processNext()['success'] === 1 && $GLOBALS['scans'] === [1], 'Server inventory scan needs a subscription');
    foreach (['active', 'expired', 'suspended', 'traffic_exceeded', 'disabled_node', 'lifetime', 'renewed'] as $case) {
        fixture();
        if (in_array($case, ['expired', 'suspended', 'traffic_exceeded'], true)) $GLOBALS['subscriptions'][14]['status'] = $case;
        if ($case === 'expired') $GLOBALS['subscriptions'][14]['expires_at'] = date('Y-m-d H:i:s', time() - 1);
        if ($case === 'disabled_node') $GLOBALS['nodes'][7]['desired_enabled'] = 0;
        if ($case === 'lifetime') $GLOBALS['subscriptions'][14]['expires_at'] = null;
        if ($case === 'renewed') {
            // Old payload must not win over the current subscription at execution.
            $GLOBALS['queueFixture']->row['payload_json'] = json_encode(['enable' => false, 'status' => 'expired']);
        }
        $result = $processor->processNext();
        $enabled = in_array($case, ['active', 'lifetime', 'renewed'], true);
        check($result['success'] === 1 && $GLOBALS['pushes'][0]['enable'] === $enabled, "$case: current access policy was not used");
        check($GLOBALS['confirmed'][0]['status'] === ($enabled ? 'active' : 'disabled'), "$case: stored status contradicts confirmed panel enable");
        check($GLOBALS['nodes'][7]['desired_enabled'] === (int)($case !== 'disabled_node'), 'Factual sync rewrote manual intent');
    }
    fixture(); $GLOBALS['failPanel'] = true;
    check($processor->processNext()['failure'] === 1 && $GLOBALS['queueFixture']->row['status'] === 'retry', 'Temporary panel failure was cancelled');
    check($GLOBALS['confirmed'] === [], 'Unconfirmed panel failure was saved');
    fixture(); $GLOBALS['subscriptions'] = [];
    $batch = $processor->processDue(10);
    check($batch['processed'] === 1 && $batch['cancelled'] === 1 && $batch['success'] === 0 && $batch['failure'] === 0, 'Bulk processing hides cancellations');
    echo 'PASS operation targets and confirmed state: ' . $GLOBALS['checks'] . " checks, no real database or panels\n";
}
