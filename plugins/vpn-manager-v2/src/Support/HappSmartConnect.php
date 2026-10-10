<?php

namespace Fireball\VpnManagerV2\Support;

use Fireball\VpnManagerV2\DTO\SmartConnectData;
use Fireball\VpnManagerV2\Validators\SmartConnectValidator;

final class HappSmartConnect
{
    public function headers(array $settings, bool $active = true): array
    {
        $settings = (new SmartConnectValidator())->validate($settings, false);
        if (!SmartConnectData::automatic($settings) || !$settings['smart_connect_happ_enabled']) {
            return [];
        }
        $headers = [
            'providerid' => $settings['smart_connect_happ_provider_id'],
            'subscription-ping-onopen-enabled' => $active && $settings['smart_connect_happ_ping_on_open'] ? '1' : '0',
            'subscription-autoconnect' => $active && $settings['smart_connect_happ_autoconnect'] ? '1' : '0',
            'subscriptions-sort-type' => $active && $settings['smart_connect_happ_sort_ping'] ? 'ping' : 'without',
        ];
        if ($active) {
            $headers['ping-type'] = $settings['smart_connect_happ_ping_type'];
            $headers['check-url-via-proxy'] = $settings['smart_connect_test_url'];
            if ($settings['smart_connect_happ_autoconnect']) {
                $headers['subscription-autoconnect-type'] = 'lowestdelay';
            }
        }
        return $headers;
    }
}
