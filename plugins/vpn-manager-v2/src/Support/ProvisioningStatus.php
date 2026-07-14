<?php

namespace Fireball\VpnManagerV2\Support;

final class ProvisioningStatus
{
    public static function badge(string $status): string
    {
        $status = strtolower(trim($status));
        $classes = [
            'active' => 'text-bg-success',
            'provisioning' => 'text-bg-info',
            'creating' => 'text-bg-info',
            'provisioning_failed' => 'text-bg-danger',
            'create_failed' => 'text-bg-danger',
            'sync_error' => 'text-bg-warning',
            'suspended' => 'text-bg-secondary',
            'expired' => 'text-bg-secondary',
            'cancelled' => 'text-bg-secondary',
        ];
        $class = $classes[$status] ?? 'text-bg-light border text-body-secondary';
        $key = array_key_exists($status, $classes) ? $status : 'unknown';

        return '<span class="badge rounded-pill ' . $class . '">'
            . htmlSC(\FireballPluginVpnManagerV2::t('vpn_manager_v2_provisioning_status_' . $key))
            . '</span>';
    }

    public static function canRetry(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['create_failed', 'sync_error'], true);
    }

    private function __construct()
    {
    }
}
