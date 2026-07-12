<?php

namespace Fireball\VpnManager\Support;

final class Schema
{
    private static bool $ready = false;

    public static function ensure(): void
    {
        if (self::$ready) {
            return;
        }

        $sql = (string)file_get_contents(dirname(__DIR__, 2) . '/migrations/001_create_vpn_manager_tables.sql');
        if (trim($sql) !== '') {
            (new \App\Services\SqlFileRunner())->executeDatabase($sql);
        }

        self::ensureRuntimeColumns();
        self::$ready = true;
    }

    public static function seedPermissions(): void
    {
        self::ensure();
        $now = date('Y-m-d H:i:s');
        $permissions = [
            'vpn.view' => 'View VPN Manager',
            'vpn.manage' => 'Manage VPN Manager',
            'vpn.servers' => 'Manage VPN servers',
            'vpn.plans' => 'Manage VPN plans',
            'vpn.subscriptions' => 'Manage VPN subscriptions',
            'vpn.connections' => 'Manage VPN connections',
            'vpn.settings' => 'Manage VPN settings',
            'vpn.logs' => 'View VPN logs',
        ];

        foreach ($permissions as $permission => $description) {
            db()->query(
                'INSERT INTO vpn_permissions (permission, description, created_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE description = VALUES(description)',
                [$permission, $description, $now]
            );
        }
    }

    private static function ensureRuntimeColumns(): void
    {
        self::addColumnIfMissing('vpn_inbounds', 'status', "VARCHAR(40) NOT NULL DEFAULT 'active' AFTER is_enabled");
        self::addColumnIfMissing('vpn_servers', 'public_host', 'VARCHAR(255) NULL AFTER panel_url');
        self::addColumnIfMissing('vpn_subscriptions', 'subscription_token', 'VARCHAR(128) NULL AFTER source_order_id');
        self::addColumnIfMissing('vpn_subscriptions', 'subscription_url', 'VARCHAR(700) NULL AFTER source_order_id');
        self::addColumnIfMissing('vpn_subscriptions', 'subscription_token_encrypted', 'MEDIUMTEXT NULL AFTER source_order_id');
        self::addColumnIfMissing('vpn_subscriptions', 'subscription_token_hash', 'VARCHAR(128) NULL AFTER subscription_token_encrypted');
        self::addColumnIfMissing('vpn_subscriptions', 'subscription_token_preview', 'VARCHAR(32) NULL AFTER subscription_token_hash');
        self::addColumnIfMissing('vpn_traffic_snapshots', 'inbound_id', 'INT(10) UNSIGNED NULL AFTER server_id');
        self::addColumnIfMissing('vpn_traffic_snapshots', 'upload_bytes', 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER inbound_id');
        self::addColumnIfMissing('vpn_traffic_snapshots', 'download_bytes', 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER upload_bytes');
        self::addColumnIfMissing('vpn_traffic_snapshots', 'total_bytes', 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER download_bytes');
        self::addColumnIfMissing('vpn_traffic_snapshots', 'captured_at', 'DATETIME NULL AFTER traffic_used_bytes');
        self::addIndexIfMissing('vpn_inbounds', 'status', 'KEY status (status)');
        self::addIndexIfMissing('vpn_subscriptions', 'subscription_token', 'KEY subscription_token (subscription_token)');
        self::addIndexIfMissing('vpn_subscriptions', 'subscription_token_hash', 'KEY subscription_token_hash (subscription_token_hash)');
        self::addIndexIfMissing('vpn_subscription_nodes', 'subscription_server_inbound', 'UNIQUE KEY subscription_server_inbound (subscription_id, server_id, inbound_id)');
        self::addIndexIfMissing('vpn_traffic_snapshots', 'inbound_id', 'KEY inbound_id (inbound_id)');
        self::addIndexIfMissing('vpn_traffic_snapshots', 'captured_at', 'KEY captured_at (captured_at)');
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $exists = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$table, $column]
        )->getColumn();

        if ((int)$exists === 0) {
            db()->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private static function addIndexIfMissing(string $table, string $index, string $definition): void
    {
        $exists = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [$table, $index]
        )->getColumn();

        if ((int)$exists > 0) {
            return;
        }

        try {
            db()->query("ALTER TABLE {$table} ADD {$definition}");
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager index migration skipped', [
                'Table' => $table,
                'Index' => $index,
            ], $exception);
        }
    }
}
