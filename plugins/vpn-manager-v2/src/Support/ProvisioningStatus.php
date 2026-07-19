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
            'partial_sync' => 'text-bg-warning',
            'disabled' => 'text-bg-secondary',
            'suspended' => 'text-bg-secondary',
            'expired' => 'text-bg-secondary',
            'traffic_exceeded' => 'text-bg-danger',
            'cancelled' => 'text-bg-secondary',
            'deleting' => 'text-bg-info',
            'deleted' => 'text-bg-secondary',
            'delete_failed' => 'text-bg-danger',
            'missing_remote' => 'text-bg-warning',
            'invalid_snapshot' => 'text-bg-danger',
            'pending_remote_delete' => 'text-bg-warning',
        ];
        $class = $classes[$status] ?? 'text-bg-light border text-body-secondary';
        $key = array_key_exists($status, $classes) ? $status : 'unknown';

        return '<span class="badge rounded-pill ' . $class . '">'
            . htmlSC(\FireballPluginVpnManagerV2::t('vpn_manager_v2_provisioning_status_' . $key))
            . '</span>';
    }

    public static function canRetry(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['create_failed', 'sync_error', 'missing_remote'], true);
    }

    private function __construct()
    {
    }
}
