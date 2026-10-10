<?php

namespace Fireball\VpnManagerV2\DTO;

final class SmartConnectData
{
    public static function defaults(): array
    {
        return [
            'smart_connect_enabled' => false,
            'smart_connect_mode' => 'smart',
            'smart_connect_happ_enabled' => false,
            'smart_connect_happ_provider_id' => '',
            'smart_connect_happ_ping_on_open' => true,
            'smart_connect_happ_sort_ping' => true,
            'smart_connect_happ_autoconnect' => true,
            'smart_connect_happ_ping_type' => 'proxy',
            'smart_connect_singbox_enabled' => false,
            'smart_connect_test_url' => 'https://www.gstatic.com/generate_204',
            'smart_connect_interval_seconds' => 180,
            'smart_connect_tolerance_ms' => 100,
            'smart_connect_server_priorities' => [],
            'smart_connect_health_enabled' => false,
        ];
    }

    public static function automatic(array $settings): bool
    {
        return !empty($settings['smart_connect_enabled'])
            && in_array($settings['smart_connect_mode'] ?? '', ['smart', 'failover'], true);
    }
}
