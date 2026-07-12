<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Support\Crypto;
use FireballPluginVpnManager;

final class SettingsService
{
    public static function defaults(): array
    {
        return [
            'service_name' => 'My VPN',
            'config_name_prefix' => '',
            'subscription_title' => 'VPN subscription',
            'server_name_template' => '{service} — {server}',
            'logo_path' => '',
            'support_email' => '',
            'support_url' => '',
            'subscription_public_base_url' => '',
            'notify_3_days_before_expire' => true,
            'notify_on_expire_day' => true,
            'notify_traffic_80' => true,
            'notify_traffic_100' => true,
            'use_push_notifications' => true,
            'use_account_notifications' => true,
            'use_email_notifications' => false,
            'traffic_sync_enabled' => true,
            'traffic_sync_interval_minutes' => 10,
            'auto_disable_expired_subscriptions' => true,
            'auto_disable_traffic_exceeded' => true,
            'sync_enabled' => true,
            'sync_interval_minutes' => 15,
            'server_check_interval_minutes' => 10,
            'retry_failed_jobs' => true,
            'max_retry_attempts' => 3,
            'hide_sensitive_data' => true,
            'log_admin_actions' => true,
            'mask_subscription_links' => true,
            'allow_user_reset_config' => false,
            'allow_user_view_qr' => true,
            'public_account_enabled' => true,
        ];
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaults() as $key => $value) {
            if (plugin_setting(FireballPluginVpnManager::SLUG, $key, null) === null) {
                plugin_setting_set(FireballPluginVpnManager::SLUG, $key, $value);
            }
        }
    }

    public static function settings(): array
    {
        self::ensureDefaults();
        $settings = [];
        foreach (self::defaults() as $key => $default) {
            $settings[$key] = plugin_setting(FireballPluginVpnManager::SLUG, $key, $default);
        }

        foreach ([
            'notify_3_days_before_expire',
            'notify_on_expire_day',
            'notify_traffic_80',
            'notify_traffic_100',
            'use_push_notifications',
            'use_account_notifications',
            'use_email_notifications',
            'traffic_sync_enabled',
            'auto_disable_expired_subscriptions',
            'auto_disable_traffic_exceeded',
            'sync_enabled',
            'retry_failed_jobs',
            'hide_sensitive_data',
            'log_admin_actions',
            'mask_subscription_links',
            'allow_user_reset_config',
            'allow_user_view_qr',
            'public_account_enabled',
        ] as $key) {
            $settings[$key] = (bool)$settings[$key];
        }

        $settings['service_name'] = trim((string)$settings['service_name']) ?: 'My VPN';
        $settings['server_name_template'] = trim((string)$settings['server_name_template']) ?: '{service} — {server}';
        $settings['subscription_public_base_url'] = self::normalizeBaseUrl((string)($settings['subscription_public_base_url'] ?? ''));
        $settings['traffic_sync_interval_minutes'] = max(1, min(1440, (int)$settings['traffic_sync_interval_minutes']));
        $settings['sync_interval_minutes'] = max(1, min(1440, (int)$settings['sync_interval_minutes']));
        $settings['server_check_interval_minutes'] = max(1, min(1440, (int)$settings['server_check_interval_minutes']));
        $settings['max_retry_attempts'] = max(1, min(20, (int)$settings['max_retry_attempts']));

        return $settings;
    }

    public static function save(array $data): void
    {
        $settings = [
            'service_name' => mb_substr(trim((string)($data['service_name'] ?? 'My VPN')), 0, 120) ?: 'My VPN',
            'config_name_prefix' => mb_substr(trim((string)($data['config_name_prefix'] ?? '')), 0, 120),
            'subscription_title' => mb_substr(trim((string)($data['subscription_title'] ?? 'VPN subscription')), 0, 190),
            'server_name_template' => mb_substr(trim((string)($data['server_name_template'] ?? '{service} — {server}')), 0, 190) ?: '{service} — {server}',
            'logo_path' => mb_substr(trim((string)($data['logo_path'] ?? '')), 0, 255),
            'support_email' => mb_substr(trim((string)($data['support_email'] ?? '')), 0, 190),
            'support_url' => mb_substr(trim((string)($data['support_url'] ?? '')), 0, 255),
            'subscription_public_base_url' => mb_substr(self::normalizeBaseUrl((string)($data['subscription_public_base_url'] ?? '')), 0, 255),
            'notify_3_days_before_expire' => !empty($data['notify_3_days_before_expire']),
            'notify_on_expire_day' => !empty($data['notify_on_expire_day']),
            'notify_traffic_80' => !empty($data['notify_traffic_80']),
            'notify_traffic_100' => !empty($data['notify_traffic_100']),
            'use_push_notifications' => !empty($data['use_push_notifications']),
            'use_account_notifications' => !empty($data['use_account_notifications']),
            'use_email_notifications' => !empty($data['use_email_notifications']),
            'traffic_sync_enabled' => !empty($data['traffic_sync_enabled']),
            'traffic_sync_interval_minutes' => max(1, min(1440, (int)($data['traffic_sync_interval_minutes'] ?? 10))),
            'auto_disable_expired_subscriptions' => !empty($data['auto_disable_expired_subscriptions']),
            'auto_disable_traffic_exceeded' => !empty($data['auto_disable_traffic_exceeded']),
            'sync_enabled' => !empty($data['sync_enabled']),
            'sync_interval_minutes' => max(1, min(1440, (int)($data['sync_interval_minutes'] ?? 15))),
            'server_check_interval_minutes' => max(1, min(1440, (int)($data['server_check_interval_minutes'] ?? 10))),
            'retry_failed_jobs' => !empty($data['retry_failed_jobs']),
            'max_retry_attempts' => max(1, min(20, (int)($data['max_retry_attempts'] ?? 3))),
            'hide_sensitive_data' => !empty($data['hide_sensitive_data']),
            'log_admin_actions' => !empty($data['log_admin_actions']),
            'mask_subscription_links' => !empty($data['mask_subscription_links']),
            'allow_user_reset_config' => !empty($data['allow_user_reset_config']),
            'allow_user_view_qr' => !empty($data['allow_user_view_qr']),
            'public_account_enabled' => !empty($data['public_account_enabled']),
        ];

        foreach ($settings as $key => $value) {
            plugin_setting_set(FireballPluginVpnManager::SLUG, $key, $value);
        }

        self::refreshSubscriptionUrls();
    }

    private static function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $value)) {
            $value = 'https://' . $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $host = (string)$parts['host'];
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = trim((string)($parts['path'] ?? ''), '/');

        return rtrim($scheme . '://' . $host . $port . ($path !== '' ? '/' . $path : ''), '/');
    }

    private static function refreshSubscriptionUrls(): void
    {
        try {
            $link = new SubscriptionLinkService();
            $rows = db()->query(
                'SELECT id, subscription_token, subscription_token_encrypted
                 FROM vpn_subscriptions
                 WHERE (subscription_token IS NOT NULL AND subscription_token <> "")
                    OR (subscription_token_encrypted IS NOT NULL AND subscription_token_encrypted <> "")'
            )->get() ?: [];

            foreach ($rows as $row) {
                $token = Crypto::decrypt((string)($row['subscription_token_encrypted'] ?? ''));
                if ($token === '') {
                    $token = trim((string)($row['subscription_token'] ?? ''));
                }
                if ($token === '') {
                    continue;
                }

                db()->query(
                    'UPDATE vpn_subscriptions SET subscription_url = ? WHERE id = ?',
                    [$link->subscriptionUrlForToken($token), (int)$row['id']]
                );
            }
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager subscription URL refresh failed', [], $exception);
        }
    }
}
