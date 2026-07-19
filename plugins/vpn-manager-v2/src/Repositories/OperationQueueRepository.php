<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Support\Uuid;

final class OperationQueueRepository
{
    private const ACTIVE = ['pending', 'retry', 'running'];

    public function enqueue(
        string $type,
        string $source = 'cms',
        ?int $serverId = null,
        ?int $subscriptionId = null,
        ?int $connectionId = null,
        array $payload = [],
        ?int $initiatedBy = null,
        int $maxAttempts = 8
    ): array {
        $type = $this->operationType($type);
        $payload = $this->sanitize($payload);
        $payloadJson = $payload !== []
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : null;
        $idempotencyKey = $this->idempotencyKey(
            $type,
            $serverId,
            $subscriptionId,
            $connectionId,
            $payloadJson
        );

        $database = db();
        $database->beginTransaction();
        try {
            $active = $database->query(
                "SELECT id, operation_id, operation_type, status, created_at
                 FROM vpn_v2_operations
                 WHERE idempotency_key = ? AND status IN ('pending', 'retry', 'running')
                 LIMIT 1 FOR UPDATE",
                [$idempotencyKey]
            )->getOne();
            if (is_array($active)) {
                $database->commit();

                return $active + ['created' => false];
            }

            $operationId = Uuid::v4();
            $now = date('Y-m-d H:i:s');
            $database->query(
                'INSERT INTO vpn_v2_operations
                    (operation_id, idempotency_key, operation_type, source, server_id, subscription_id,
                     connection_id, payload_json, status, attempts, max_attempts, next_attempt_at,
                     initiated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\', 0, ?, ?, ?, ?, ?)',
                [
                    $operationId,
                    $idempotencyKey,
                    $type,
                    $this->source($source),
                    $serverId,
                    $subscriptionId,
                    $connectionId,
                    $payloadJson,
                    max(1, min(50, $maxAttempts)),
                    $now,
                    $initiatedBy !== null && $initiatedBy > 0 ? $initiatedBy : null,
                    $now,
                    $now,
                ]
            );
            $id = (int)$database->getInsertId();
            $database->commit();

            return [
                'id' => $id,
                'operation_id' => $operationId,
                'operation_type' => $type,
                'status' => 'pending',
                'created_at' => $now,
                'created' => true,
            ];
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function claimNext(?array $types = null, int $leaseSeconds = 300): ?array
    {
        $database = db();
        $database->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $database->query(
                "UPDATE vpn_v2_operations
                 SET status = 'retry', lease_until = NULL, heartbeat_at = NULL,
                     next_attempt_at = ?, updated_at = ?
                 WHERE status = 'running' AND lease_until IS NOT NULL AND lease_until < ?",
                [$now, $now, $now]
            );

            $sql = "SELECT * FROM vpn_v2_operations
                    WHERE status IN ('pending', 'retry') AND next_attempt_at <= ?";
            $params = [$now];
            if (is_array($types) && $types !== []) {
                $types = array_values(array_unique(array_map([$this, 'operationType'], $types)));
                $sql .= ' AND operation_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
                array_push($params, ...$types);
            }
            $operation = $database->query($sql . ' ORDER BY next_attempt_at ASC, id ASC LIMIT 1 FOR UPDATE', $params)->getOne();
            if (!is_array($operation)) {
                $database->commit();

                return null;
            }
            $leaseUntil = date('Y-m-d H:i:s', time() + max(30, min(1800, $leaseSeconds)));
            $database->query(
                "UPDATE vpn_v2_operations
                 SET status = 'running', attempts = attempts + 1, lease_until = ?, heartbeat_at = ?,
                     updated_at = ? WHERE id = ? AND status IN ('pending', 'retry')",
                [$leaseUntil, $now, $now, (int)$operation['id']]
            );
            if ($database->rowCount() !== 1) {
                $database->rollBack();

                return null;
            }
            $database->commit();
            $operation['status'] = 'running';
            $operation['attempts'] = (int)$operation['attempts'] + 1;
            $operation['lease_until'] = $leaseUntil;

            return $operation;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function heartbeat(int $id, int $processed = 0, int $total = 0): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_operations
             SET heartbeat_at = ?, lease_until = ?, processed_count = GREATEST(processed_count, ?),
                 total_count = GREATEST(total_count, ?), updated_at = ?
             WHERE id = ? AND status = 'running'",
            [$now, date('Y-m-d H:i:s', time() + 300), max(0, $processed), max(0, $total), $now, $id]
        );
    }

    public function complete(int $id, int $processed = 1, int $total = 1, string $status = 'completed'): void
    {
        $status = $status === 'completed_partial' ? 'completed_partial' : 'completed';
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_operations
             SET status = ?, idempotency_key = NULL, lease_until = NULL,
                 processed_count = ?, total_count = ?, last_error = NULL,
                 finished_at = ?, updated_at = ? WHERE id = ?",
            [$status, max(0, $processed), max(0, $total), $now, $now, $id]
        );
    }

    public function fail(int $id, string $safeError): string
    {
        $operation = db()->query(
            'SELECT attempts, max_attempts FROM vpn_v2_operations WHERE id = ? LIMIT 1',
            [$id]
        )->getOne() ?: [];
        $attempts = (int)($operation['attempts'] ?? 1);
        $terminal = $attempts >= max(1, (int)($operation['max_attempts'] ?? 8));
        $status = $terminal ? 'failed' : 'retry';
        $delay = min(21600, 30 * (2 ** min(10, max(0, $attempts - 1))));
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE vpn_v2_operations
             SET status = ?, idempotency_key = CASE WHEN ? = \'failed\' THEN NULL ELSE idempotency_key END,
                 next_attempt_at = ?, lease_until = NULL, heartbeat_at = NULL, last_error = ?,
                 finished_at = CASE WHEN ? = \'failed\' THEN ? ELSE NULL END, updated_at = ? WHERE id = ?',
            [
                $status,
                $status,
                date('Y-m-d H:i:s', time() + $delay),
                mb_substr(trim($safeError), 0, 1000),
                $status,
                $now,
                $now,
                $id,
            ]
        );

        return $status;
    }

    public function retryFailed(?int $id = null): int
    {
        $sql = "SELECT id, operation_type, server_id, subscription_id, connection_id, payload_json
                FROM vpn_v2_operations WHERE status = 'failed'";
        $params = [];
        if ($id !== null) {
            $sql .= ' AND id = ?';
            $params[] = $id;
        }
        $database = db();
        $database->beginTransaction();
        try {
            $rows = $database->query($sql . ' ORDER BY id ASC FOR UPDATE', $params)->get() ?: [];
            $count = 0;
            $now = date('Y-m-d H:i:s');
            foreach ($rows as $row) {
                $key = $this->idempotencyKey(
                    (string)$row['operation_type'],
                    isset($row['server_id']) ? (int)$row['server_id'] : null,
                    isset($row['subscription_id']) ? (int)$row['subscription_id'] : null,
                    isset($row['connection_id']) ? (int)$row['connection_id'] : null,
                    isset($row['payload_json']) ? (string)$row['payload_json'] : null
                );
                $duplicate = $database->query(
                    "SELECT id FROM vpn_v2_operations
                     WHERE idempotency_key = ? AND id <> ? AND status IN ('pending', 'retry', 'running') LIMIT 1",
                    [$key, (int)$row['id']]
                )->getOne();
                if (is_array($duplicate)) {
                    continue;
                }
                $database->query(
                    "UPDATE vpn_v2_operations
                     SET status = 'retry', idempotency_key = ?, attempts = 0, next_attempt_at = ?,
                         finished_at = NULL, last_error = NULL, updated_at = ? WHERE id = ? AND status = 'failed'",
                    [$key, $now, $now, (int)$row['id']]
                );
                $count += $database->rowCount();
            }
            $database->commit();

            return $count;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function progress(string $operationId): ?array
    {
        $row = db()->query(
            'SELECT operation_id, operation_type, status, attempts, max_attempts, processed_count,
                    total_count, next_attempt_at, last_error, created_at, updated_at, finished_at
             FROM vpn_v2_operations WHERE operation_id = ? LIMIT 1',
            [$operationId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function cancel(string $operationId): bool
    {
        $operationId = strtolower(trim($operationId));
        if (preg_match('/^[a-f0-9-]{36}$/', $operationId) !== 1) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_operations
             SET status = 'cancelled', idempotency_key = NULL, lease_until = NULL,
                 heartbeat_at = NULL, finished_at = ?, updated_at = ?
             WHERE operation_id = ? AND status IN ('pending', 'retry')",
            [$now, $now, $operationId]
        );

        return db()->rowCount() === 1;
    }

    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            'SELECT operation_id, operation_type, source, server_id, subscription_id, connection_id,
                    status, attempts, max_attempts, processed_count, total_count, next_attempt_at,
                    last_error, created_at, updated_at, finished_at
             FROM vpn_v2_operations ORDER BY id DESC LIMIT ' . $limit
        )->get() ?: [];
    }

    private function operationType(string $type): string
    {
        $type = strtolower(trim($type));
        $allowed = [
            'create_client', 'update_client', 'rename_client', 'enable_client', 'disable_client',
            'delete_client', 'move_client', 'sync_client', 'sync_inbound', 'sync_server',
            'sync_subscription', 'full_reconcile', 'reset_traffic',
        ];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported VPN operation type.');
        }

        return $type;
    }

    private function source(string $source): string
    {
        $source = strtolower(trim($source));

        return in_array($source, ['cms', 'three_x_ui', 'reconciliation', 'retry', 'manual_sync'], true)
            ? $source
            : 'cms';
    }

    private function idempotencyKey(
        string $type,
        ?int $serverId,
        ?int $subscriptionId,
        ?int $connectionId,
        ?string $payloadJson
    ): string {
        return hash('sha256', implode('|', [
            $type,
            (string)($serverId ?? 0),
            (string)($subscriptionId ?? 0),
            (string)($connectionId ?? 0),
            (string)$payloadJson,
        ]));
    }

    private function sanitize(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string)$key);
            if (str_contains($normalized, 'password') || str_contains($normalized, 'token')
                || str_contains($normalized, 'cookie') || str_contains($normalized, 'authorization')
                || str_contains($normalized, 'secret') || str_contains($normalized, 'private_key')) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }
}
