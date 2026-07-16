<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\VpnSettingsData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class SettingsValidator
{
    public const TEMPLATE_VARIABLES = [
        'flag',
        'service',
        'country',
        'country_code',
        'city',
        'server',
        'protocol',
    ];

    private const CHECKBOXES = [
        'global_show_flags',
        'sync_enabled',
        'retry_failed_operations',
        'notifications_profile_enabled',
        'notifications_email_enabled',
        'notify_expiration_3_days',
        'notify_expiration_day',
        'notify_traffic_80',
        'notify_traffic_100',
        'notify_provisioned',
        'notify_critical_errors',
        'hide_sensitive_data',
        'mask_subscription_links',
        'public_account_enabled',
        'show_qr_in_profile',
    ];

    public function validate(array $input, array $current): VpnSettingsData
    {
        $data = array_replace($current, $input);
        foreach (self::CHECKBOXES as $key) {
            $data[$key] = array_key_exists($key, $input) ? $input[$key] : false;
        }

        return $this->make($data, true);
    }

    public function validateStored(array $stored): VpnSettingsData
    {
        return $this->make($stored, false);
    }

    private function make(array $data, bool $strict): VpnSettingsData
    {
        $serviceName = $this->text(
            $data['service_name'] ?? '',
            120,
            true,
            'vpn_manager_v2_error_settings_service_name',
            $strict
        );
        $template = $this->template((string)($data['server_name_template'] ?? ''), $strict);

        return new VpnSettingsData(
            $serviceName,
            $template,
            $this->boolean($data['global_show_flags'] ?? false),
            $this->text($data['support_name'] ?? '', 120, false, 'vpn_manager_v2_error_settings_support_name', $strict),
            $this->supportUrl((string)($data['support_url'] ?? ''), $strict),
            $this->logo((string)($data['logo'] ?? ''), $strict),
            $this->choice(
                (string)($data['expired_subscription_behavior'] ?? 'gone'),
                ['gone', 'not_found'],
                'gone',
                'vpn_manager_v2_error_settings_expired_behavior',
                $strict
            ),
            $this->integer($data['subscription_cache_ttl_seconds'] ?? 300, 30, 3600, 300, 'vpn_manager_v2_error_settings_subscription_cache', $strict),
            $this->integer($data['qr_cache_ttl_seconds'] ?? 3600, 60, 86400, 3600, 'vpn_manager_v2_error_settings_qr_cache', $strict),
            $this->integer($data['settings_cache_ttl_seconds'] ?? 300, 30, 1800, 300, 'vpn_manager_v2_error_settings_settings_cache', $strict),
            $this->boolean($data['sync_enabled'] ?? false),
            $this->integer($data['sync_interval_minutes'] ?? 15, 1, 1440, 15, 'vpn_manager_v2_error_settings_sync_interval', $strict),
            $this->integer($data['server_check_interval_minutes'] ?? 10, 1, 1440, 10, 'vpn_manager_v2_error_settings_check_interval', $strict),
            $this->boolean($data['retry_failed_operations'] ?? false),
            $this->boolean($data['notifications_profile_enabled'] ?? false),
            $this->boolean($data['notifications_email_enabled'] ?? false),
            $this->boolean($data['notify_expiration_3_days'] ?? false),
            $this->boolean($data['notify_expiration_day'] ?? false),
            $this->boolean($data['notify_traffic_80'] ?? false),
            $this->boolean($data['notify_traffic_100'] ?? false),
            $this->boolean($data['notify_provisioned'] ?? false),
            $this->boolean($data['notify_critical_errors'] ?? false),
            $this->boolean($data['hide_sensitive_data'] ?? false),
            $this->boolean($data['mask_subscription_links'] ?? false),
            $this->boolean($data['public_account_enabled'] ?? false),
            $this->boolean($data['show_qr_in_profile'] ?? false),
        );
    }

    private function template(string $value, bool $strict): string
    {
        $value = trim($value);
        $fallback = '{flag} {service} · {country} {city} · {server} · {protocol}';
        if ($value === '' || mb_strlen($value) > 240 || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            return $this->invalid($strict, 'vpn_manager_v2_error_settings_template', $fallback);
        }

        $withoutAllowed = preg_replace(
            '/\{(?:' . implode('|', array_map('preg_quote', self::TEMPLATE_VARIABLES)) . ')\}/u',
            '',
            $value
        );
        if (!is_string($withoutAllowed) || str_contains($withoutAllowed, '{') || str_contains($withoutAllowed, '}')) {
            return $this->invalid($strict, 'vpn_manager_v2_error_settings_template_variable', $fallback);
        }

        return $value;
    }

    private function supportUrl(string $value, bool $strict): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $this->invalid($strict, 'vpn_manager_v2_error_settings_support_url', '');
        }
        $scheme = strtolower((string)(parse_url($value, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $this->invalid($strict, 'vpn_manager_v2_error_settings_support_url', '');
        }

        return $value;
    }

    private function logo(string $value, bool $strict): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) > 255 || str_contains($value, '..')
            || preg_match('~^/uploads/[A-Za-z0-9/_.,%+\-=]+\.(?:png|jpe?g|webp|gif)$~i', $value) !== 1) {
            return $this->invalid($strict, 'vpn_manager_v2_error_settings_logo', '');
        }

        return $value;
    }

    private function text(
        mixed $value,
        int $maxLength,
        bool $required,
        string $errorKey,
        bool $strict
    ): string {
        $value = trim((string)$value);
        if (($required && $value === '') || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            return $this->invalid($strict, $errorKey, $required ? 'VPN V2' : '');
        }

        return $value;
    }

    private function integer(
        mixed $value,
        int $minimum,
        int $maximum,
        int $fallback,
        string $errorKey,
        bool $strict
    ): int {
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < $minimum || $validated > $maximum) {
            return $this->invalid($strict, $errorKey, $fallback);
        }

        return (int)$validated;
    }

    private function choice(
        string $value,
        array $allowed,
        string $fallback,
        string $errorKey,
        bool $strict
    ): string {
        $value = strtolower(trim($value));
        if (!in_array($value, $allowed, true)) {
            return $this->invalid($strict, $errorKey, $fallback);
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function invalid(bool $strict, string $errorKey, mixed $fallback): mixed
    {
        if ($strict) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t($errorKey));
        }

        return $fallback;
    }
}
