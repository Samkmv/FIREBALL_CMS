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

        return '<span class="badge rounded-pill ' . $class . '">'
            . htmlSC(self::label($status))
            . '</span>';
    }

    public static function label(string $status): string
    {
        $status = strtolower(trim($status));
        $known = [
            'active', 'provisioning', 'creating', 'provisioning_failed', 'create_failed',
            'sync_error', 'partial_sync', 'disabled', 'inactive', 'suspended', 'expired',
            'traffic_exceeded', 'cancelled', 'deleting', 'deleted', 'delete_failed',
            'missing_remote', 'invalid_snapshot', 'pending_remote_delete',
        ];
        $key = in_array($status, $known, true) ? $status : 'unknown';

        return \FireballPluginVpnManagerV2::t('vpn_manager_v2_provisioning_status_' . $key);
    }

    public static function canRetry(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['create_failed', 'sync_error', 'missing_remote'], true);
    }

    private function __construct()
    {
    }
}
