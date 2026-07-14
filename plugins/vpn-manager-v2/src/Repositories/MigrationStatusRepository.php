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
        'vpn_v2_events',
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
