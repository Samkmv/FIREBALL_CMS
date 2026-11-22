<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\DTO\PlanData;
use Fireball\VpnManagerV2\DTO\PlanNodeData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\VpnSubscriptionRevisionService;

final class PlanRepository
{
    public function all(): array
    {
        $rows = db()->query(
            'SELECT p.id, p.name, p.description, p.duration_days, p.traffic_limit_bytes,
                    p.device_limit, p.ip_limit, p.is_active, p.created_at, p.updated_at,
                    preset.name AS server_preset_name, p.server_preset_id,
                    COUNT(DISTINCT n.server_id) AS server_count, COUNT(n.id) AS node_count
             FROM vpn_v2_plans p
             LEFT JOIN vpn_v2_server_presets preset ON preset.id = p.server_preset_id
             LEFT JOIN vpn_v2_plan_nodes n ON n.plan_id = p.id AND n.is_enabled = 1
             WHERE p.deleted_at IS NULL
             GROUP BY p.id, p.name, p.description, p.duration_days, p.traffic_limit_bytes,
                      p.device_limit, p.ip_limit, p.is_active, p.created_at, p.updated_at, preset.name, p.server_preset_id
             ORDER BY p.id ASC'
        )->get() ?: [];
        $reconciliation = new PlanReconciliationRepository();
        foreach ($rows as &$row) {
            $planId = (int)$row['id'];
            $row['subscription_count'] = $reconciliation->eligibleSubscriptionCount($planId);
            $row['mismatch_subscription_count'] = $reconciliation->mismatchSubscriptionCount($planId);
            $latest = $reconciliation->latestOperation($planId);
            $row['last_reconcile_at'] = $latest['finished_at'] ?? $latest['started_at'] ?? null;
            $row['last_reconcile_status'] = $latest['status'] ?? null;
        }
        unset($row);

        return $rows;
    }

    public function find(int $id): ?array
    {
        $row = db()->query(
            'SELECT id, name, description, duration_days, traffic_limit_bytes, device_limit, ip_limit,
                    is_active, created_at, updated_at, server_preset_id
             FROM vpn_v2_plans WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function nodes(int $planId, bool $forUpdate = false): array
    {
        return db()->query(
            'SELECT n.id, n.plan_id, n.server_id, n.inbound_id, n.flow_override, n.is_enabled,
                    n.sort_order, s.name AS server_name, s.code AS server_code,
                    i.name AS inbound_name, i.remote_inbound_id, i.protocol, i.network, i.security,
                    s.is_enabled AS server_is_enabled, i.is_enabled AS inbound_is_enabled, i.status AS inbound_status
             FROM vpn_v2_plan_nodes n
             INNER JOIN vpn_v2_servers s ON s.id = n.server_id
             INNER JOIN vpn_v2_inbounds i ON i.id = n.inbound_id
             WHERE n.plan_id = ?
             ORDER BY n.sort_order ASC, n.id ASC' . ($forUpdate ? ' FOR UPDATE' : ''),
            [$planId]
        )->get() ?: [];
    }

    public function hasServerSyncSince(int $planId, string $checkedAt): bool
    {
        return (bool)db()->query(
            'SELECT 1 FROM vpn_v2_plan_nodes pn
             INNER JOIN vpn_v2_servers s ON s.id = pn.server_id
             WHERE pn.plan_id = ? AND pn.is_enabled = 1
               AND (s.last_sync_at >= ? OR s.last_success_at >= ?)
             LIMIT 1',
            [$planId, $checkedAt, $checkedAt]
        )->getColumn();
    }

    public function serversForForm(): array
    {
        return db()->query(
            'SELECT id, name, code, status, is_enabled FROM vpn_v2_servers WHERE status <> \'archived\' ORDER BY id ASC'
        )->get() ?: [];
    }

    public function inboundsForForm(): array
    {
        return db()->query(
            'SELECT i.id, i.server_id, i.remote_inbound_id, i.name, i.remark, i.protocol,
                    i.port, i.network, i.security, i.default_flow, i.status, i.is_enabled,
                    s.name AS server_name, s.code AS server_code, s.is_enabled AS server_is_enabled
             FROM vpn_v2_inbounds i
             INNER JOIN vpn_v2_servers s ON s.id = i.server_id
             WHERE i.status <> \'archived\' AND s.status <> \'archived\' ORDER BY s.id ASC, i.id ASC'
        )->get() ?: [];
    }

    public function topologyForInboundIds(array $inboundIds, bool $forUpdate = false): array
    {
        $inboundIds = array_values(array_unique(array_filter(array_map('intval', $inboundIds), static fn(int $id): bool => $id > 0)));
        if ($inboundIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($inboundIds), '?'));
        $rows = db()->query(
            "SELECT i.id, i.server_id, i.protocol, i.network, i.security, i.status, i.is_enabled,
                    s.is_enabled AS server_is_enabled
             FROM vpn_v2_inbounds i
             INNER JOIN vpn_v2_servers s ON s.id = i.server_id
             WHERE i.id IN ({$placeholders})" . ($forUpdate ? ' FOR UPDATE' : ''),
            $inboundIds
        )->get() ?: [];
        $topology = [];
        foreach ($rows as $row) {
            $topology[(int)$row['id']] = $row;
        }

        return $topology;
    }

    public function create(PlanData $plan, ?int $presetId = null): int
    {
        $database = db();
        $database->beginTransaction();
        try {
            $nodes = $this->assignmentNodes($plan, $presetId);
            $now = date('Y-m-d H:i:s');
            $database->query(
                'INSERT INTO vpn_v2_plans
                    (name, description, duration_days, traffic_limit_bytes, device_limit, ip_limit, is_active, created_at, updated_at, server_preset_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $plan->name,
                    $plan->description,
                    $plan->durationDays,
                    $plan->trafficLimitBytes,
                    $plan->deviceLimit,
                    $plan->ipLimit,
                    $plan->isActive ? 1 : 0,
                    $now,
                    $now,
                    $presetId,
                ]
            );
            $planId = (int)$database->getInsertId();
            $this->insertNodes($planId, $nodes, $now);
            $this->logEvent('plan.created', $planId, count($nodes), ['server_preset_id' => $presetId]);
            $database->commit();

            return $planId;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, PlanData $plan, ?int $presetId = null): void
    {
        $database = db();
        $database->beginTransaction();
        try {
            $nodes = $presetId === null ? $plan->nodes : (new ServerPresetRepository())->lockedNodesForPlan($presetId);
            $current = $database->query(
                'SELECT id, server_preset_id FROM vpn_v2_plans WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]
            )->getOne();
            if (!$current) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
            }
            if ($presetId !== null && $nodes === [] && (int)$current['server_preset_id'] !== $presetId) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_empty'));
            }
            $now = date('Y-m-d H:i:s');
            $database->query(
                'UPDATE vpn_v2_plans
                 SET name = ?, description = ?, duration_days = ?, traffic_limit_bytes = ?,
                     device_limit = ?, ip_limit = ?, is_active = ?, updated_at = ?, server_preset_id = ?
                 WHERE id = ?',
                [
                    $plan->name,
                    $plan->description,
                    $plan->durationDays,
                    $plan->trafficLimitBytes,
                    $plan->deviceLimit,
                    $plan->ipLimit,
                    $plan->isActive ? 1 : 0,
                    $now,
                    $presetId,
                    $id,
                ]
            );
            $this->replaceNodes($id, $nodes, $now);
            (new VpnSubscriptionRevisionService())->touchByPlan($id);
            $this->logEvent('plan.updated', $id, count($nodes), ['server_preset_id' => $presetId]);
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function toggle(int $id): bool
    {
        $database = db();
        $database->beginTransaction();
        try {
            $database->query(
                'UPDATE vpn_v2_plans SET is_active = IF(is_active = 1, 0, 1), updated_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), $id]
            );
            $plan = $this->find($id);
            $active = !empty($plan['is_active']);
            $this->logEvent('plan.toggled', $id, count($this->nodes($id)), ['is_active' => $active]);
            $database->commit();

            return $active;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function archive(int $id): bool
    {
        $database = db();
        $database->beginTransaction();
        try {
            $plan = $database->query(
                'SELECT id FROM vpn_v2_plans WHERE id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                [$id]
            )->getOne();
            if (!is_array($plan)) {
                $database->rollBack();
                return false;
            }
            $now = date('Y-m-d H:i:s');
            $database->query(
                'UPDATE vpn_v2_plans SET is_active = 0, server_preset_id = NULL, deleted_at = ?, updated_at = ? WHERE id = ?',
                [$now, $now, $id]
            );
            $this->logEvent('plan.deleted', $id, count($this->nodes($id)), [
                'subscription_count' => (int)$database->query(
                    'SELECT COUNT(*) FROM vpn_v2_subscriptions WHERE plan_id = ? AND status <> \'deleted\'',
                    [$id]
                )->getColumn(),
            ]);
            $database->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function assignmentNodes(PlanData $plan, ?int $presetId): array
    {
        if ($presetId === null) {
            return $plan->nodes;
        }
        $nodes = (new ServerPresetRepository())->lockedNodesForPlan($presetId);
        if ($nodes === []) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_empty'));
        }
        return $nodes;
    }

    /** Update the effective topology within the caller's transaction. */
    public function replaceNodes(int $planId, array $nodes, string $now): void
    {
        $before = $this->nodes($planId, forUpdate: true);
        usort($nodes, static fn(PlanNodeData $left, PlanNodeData $right): int => $left->sortOrder <=> $right->sortOrder);
        if ($nodes == array_map(PlanNodeData::fromRow(...), $before)
            && count(array_filter($before, static fn(array $node): bool => !empty($node['is_enabled']))) === count($before)) {
            return;
        }
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [$planId]);
        $this->insertNodes($planId, $nodes, $now);
        $targets = [];
        foreach ($nodes as $node) {
            $targets[$node->serverId . ':' . $node->inboundId] = true;
        }
        $reconciliation = new PlanReconciliationRepository();
        foreach ($before as $node) {
            if (!isset($targets[$node['server_id'] . ':' . $node['inbound_id']])) {
                $reconciliation->markTargetObsolete($planId, (int)$node['server_id'], (int)$node['inbound_id']);
            }
        }
    }

    private function insertNodes(int $planId, array $nodes, string $now): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof PlanNodeData) {
                throw new \InvalidArgumentException('Invalid plan node DTO.');
            }
            db()->query(
                'INSERT INTO vpn_v2_plan_nodes
                    (plan_id, server_id, inbound_id, flow_override, is_enabled, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
                [$planId, $node->serverId, $node->inboundId, $node->flowOverride, $node->sortOrder, $now, $now]
            );
        }
    }

    private function logEvent(string $eventType, int $planId, int $nodeCount, array $extra = []): void
    {
        $user = get_user();
        $adminId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        $context = json_encode(array_merge([
            'plan_id' => $planId,
            'node_count' => $nodeCount,
        ], $extra), JSON_UNESCAPED_SLASHES);

        db()->query(
            'INSERT INTO vpn_v2_events (event_type, admin_id, context_json, created_at)
             VALUES (?, ?, ?, ?)',
            [$eventType, $adminId > 0 ? $adminId : null, $context ?: null, date('Y-m-d H:i:s')]
        );
    }
}
