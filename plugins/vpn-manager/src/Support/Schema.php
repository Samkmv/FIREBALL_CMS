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
}
