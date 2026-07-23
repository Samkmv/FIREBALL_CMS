<?php

namespace Fireball\VpnManagerV2\Repositories;

final class MigrationStatusRepository
{
    private const EXPECTED_TABLES = [
        'vpn_v2_servers',
        'vpn_v2_inbounds',
        'vpn_v2_plans',
        'vpn_v2_plan_nodes',
        'vpn_v2_subscriptions',
        'vpn_v2_subscription_nodes',
        'vpn_v2_subscription_items',
        'vpn_v2_external_sources',
        'vpn_v2_events',
        'vpn_v2_notifications',
        'vpn_v2_reconcile_operations',
        'vpn_v2_profiles',
        'vpn_v2_operations',
        'vpn_v2_connection_snapshots',
        'vpn_v2_sync_conflicts',
        'vpn_v2_sync_logs',
        'vpn_v2_remote_clients',
    ];

    public function expectedTables(): array
    {
        return self::EXPECTED_TABLES;
    }

    public function presentTables(): array
    {
        $rows = db()->query('SHOW TABLES')->get() ?: [];
        $tables = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $table = (string)(array_values($row)[0] ?? '');
            if (in_array($table, self::EXPECTED_TABLES, true)) {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }

    public function missingTables(): array
    {
        return array_values(array_diff(self::EXPECTED_TABLES, $this->presentTables()));
    }

    public function migrations(): array
    {
        return db()->query(
            'SELECT id, migration, executed_at
             FROM plugin_migrations
             WHERE plugin_slug = ?
             ORDER BY id ASC',
            [\FireballPluginVpnManagerV2::SLUG]
        )->get() ?: [];
    }
}
