<?php

namespace Fireball\VpnManagerV2\Support;

final class Permissions
{
    public const VIEW = 'vpn_v2.view';
    public const MANAGE = 'vpn_v2.manage';
    public const MANAGE_SERVERS = 'vpn_v2.servers.manage';
    public const MANAGE_INBOUNDS = 'vpn_v2.inbounds.manage';
    public const MANAGE_PLANS = 'vpn_v2.plans.manage';
    public const MANAGE_SUBSCRIPTIONS = 'vpn_v2.subscriptions.manage';
    public const VIEW_EVENTS = 'vpn_v2.events.view';

    public static function definitions(): array
    {
        return [
            self::VIEW => 'vpn_manager_v2_permission_view',
            self::MANAGE => 'vpn_manager_v2_permission_manage',
            self::MANAGE_SERVERS => 'vpn_manager_v2_permission_servers',
            self::MANAGE_INBOUNDS => 'vpn_manager_v2_permission_inbounds',
            self::MANAGE_PLANS => 'vpn_manager_v2_permission_plans',
            self::MANAGE_SUBSCRIPTIONS => 'vpn_manager_v2_permission_subscriptions',
            self::VIEW_EVENTS => 'vpn_manager_v2_permission_events',
        ];
    }

    private function __construct()
    {
    }
}
