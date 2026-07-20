<?php

namespace Fireball\VpnManagerV2\Support;

final class LocalizedValue
{
    public static function operationType(mixed $value): string
    {
        return self::translate('vpn_manager_v2_operation_type_value_', $value);
    }

    public static function operationSource(mixed $value): string
    {
        return self::translate('vpn_manager_v2_operation_source_value_', $value);
    }

    public static function operationStatus(mixed $value): string
    {
        return self::translate('vpn_manager_v2_operation_status_value_', $value);
    }

    public static function syncStatus(mixed $value): string
    {
        return self::translate('vpn_manager_v2_sync_status_value_', $value);
    }

    public static function dependencyType(mixed $value): string
    {
        return self::translate('vpn_manager_v2_dependency_type_value_', $value);
    }

    public static function ownershipType(mixed $value): string
    {
        return self::translate('vpn_manager_v2_ownership_', $value);
    }

    public static function externalSourceType(mixed $value): string
    {
        return self::translate('vpn_manager_v2_external_source_type_value_', $value);
    }

    public static function inactiveReason(mixed $value): string
    {
        $normalized = self::normalize($value);

        return $normalized === '' ? '—' : self::translate('vpn_manager_v2_inactive_reason_value_', $normalized);
    }

    public static function conflictType(mixed $value): string
    {
        return self::translate('vpn_manager_v2_conflict_type_value_', $value);
    }

    public static function conflictAction(mixed $value): string
    {
        $normalized = self::normalize($value);

        return $normalized === '' ? '—' : self::translate('vpn_manager_v2_conflict_action_value_', $normalized);
    }

    public static function conflictStatus(mixed $value): string
    {
        return self::translate('vpn_manager_v2_conflict_status_value_', $value);
    }

    public static function changedField(mixed $value): string
    {
        return self::translate('vpn_manager_v2_changed_field_value_', $value);
    }

    private static function translate(string $prefix, mixed $value): string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return \FireballPluginVpnManagerV2::t('vpn_manager_v2_value_unknown');
        }
        $key = $prefix . $normalized;
        $translated = \FireballPluginVpnManagerV2::t($key);

        return $translated !== $key
            ? $translated
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_value_unknown');
    }

    private static function normalize(mixed $value): string
    {
        $value = strtolower(trim((string)$value));

        return trim((string)preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    private function __construct()
    {
    }
}
