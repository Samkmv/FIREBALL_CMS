<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\DTO\PlanNodeData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class ServerPresetRepository
{
    public function all(): array
    {
        return db()->query(
            'SELECT p.id, p.name,
                    (SELECT COUNT(*) FROM vpn_v2_server_preset_nodes n WHERE n.preset_id = p.id) AS node_count,
                    (SELECT COUNT(*) FROM vpn_v2_plans t WHERE t.server_preset_id = p.id AND t.deleted_at IS NULL) AS plan_count
             FROM vpn_v2_server_presets p ORDER BY p.name, p.id'
        )->get() ?: [];
    }

    public function find(int $id, bool $forUpdate = false): ?array
    {
        $row = db()->query(
            'SELECT id, name FROM vpn_v2_server_presets WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : ''),
            [$id]
        )->getOne();
        return is_array($row) ? $row : null;
    }

    public function nodes(int $id, bool $forUpdate = false): array
    {
        return db()->query(
            'SELECT server_id, inbound_id, flow_override, sort_order
             FROM vpn_v2_server_preset_nodes WHERE preset_id = ? ORDER BY sort_order, id'
             . ($forUpdate ? ' FOR UPDATE' : ''),
            [$id]
        )->get() ?: [];
    }

    public function plans(int $id, bool $forUpdate = false): array
    {
        return db()->query(
            'SELECT id, name FROM vpn_v2_plans WHERE server_preset_id = ? AND deleted_at IS NULL ORDER BY id'
             . ($forUpdate ? ' FOR UPDATE' : ''),
            [$id]
        )->get() ?: [];
    }

    public function requirePreset(int $id): array
    {
        return $this->find($id, forUpdate: true)
            ?? throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_missing'));
    }

    public function lockedNodesForPlan(int $id): array
    {
        $this->requirePreset($id);
        return array_map(PlanNodeData::fromRow(...), $this->nodes($id, forUpdate: true));
    }

    public function create(string $name, string $now): int
    {
        $database = db();
        $database->query(
            'INSERT INTO vpn_v2_server_presets (name, created_at, updated_at) VALUES (?, ?, ?)',
            [$name, $now, $now]
        );
        return (int)$database->getInsertId();
    }

    public function rename(int $id, string $name, string $now): void
    {
        db()->query('UPDATE vpn_v2_server_presets SET name = ?, updated_at = ? WHERE id = ?', [$name, $now, $id]);
    }

    public function replaceNodes(int $id, array $nodes): void
    {
        $database = db();
        $database->query('DELETE FROM vpn_v2_server_preset_nodes WHERE preset_id = ?', [$id]);
        foreach ($nodes as $node) {
            $database->query(
                'INSERT INTO vpn_v2_server_preset_nodes (preset_id, server_id, inbound_id, flow_override, sort_order)
                 VALUES (?, ?, ?, ?, ?)',
                [$id, $node->serverId, $node->inboundId, $node->flowOverride, $node->sortOrder]
            );
        }
    }

    public function delete(int $id): void
    {
        db()->query('DELETE FROM vpn_v2_server_presets WHERE id = ?', [$id]);
    }
}
