<?php

namespace Fireball\VpnManagerV2\DTO;

final readonly class VpnSettingsData
{
    public function __construct(
        public string $serviceName,
        public string $subscriptionName,
        public string $serverNameTemplate,
        public bool $globalShowFlags,
        public string $supportName,
        public string $supportUrl,
        public string $logo,
        public string $expiredSubscriptionBehavior,
        public int $subscriptionCacheTtlSeconds,
        public int $qrCacheTtlSeconds,
        public int $settingsCacheTtlSeconds,
        public bool $syncEnabled,
        public int $syncIntervalMinutes,
        public int $serverCheckIntervalMinutes,
        public bool $retryFailedOperations,
        public bool $notificationsProfileEnabled,
        public bool $notificationsEmailEnabled,
        public bool $notifyExpiration3Days,
        public bool $notifyExpirationDay,
        public bool $notifyTraffic80,
        public bool $notifyTraffic100,
        public bool $notifyProvisioned,
        public bool $notifyCriticalErrors,
        public bool $hideSensitiveData,
        public bool $maskSubscriptionLinks,
        public bool $publicAccountEnabled,
        public bool $showQrInProfile,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subscription_name' => $this->subscriptionName,
            'service_name' => $this->serviceName,
            'server_name_template' => $this->serverNameTemplate,
            'global_show_flags' => $this->globalShowFlags,
            'support_name' => $this->supportName,
            'support_url' => $this->supportUrl,
            'logo' => $this->logo,
            'expired_subscription_behavior' => $this->expiredSubscriptionBehavior,
            'subscription_cache_ttl_seconds' => $this->subscriptionCacheTtlSeconds,
            'qr_cache_ttl_seconds' => $this->qrCacheTtlSeconds,
            'settings_cache_ttl_seconds' => $this->settingsCacheTtlSeconds,
            'sync_enabled' => $this->syncEnabled,
            'sync_interval_minutes' => $this->syncIntervalMinutes,
            'server_check_interval_minutes' => $this->serverCheckIntervalMinutes,
            'retry_failed_operations' => $this->retryFailedOperations,
            'notifications_profile_enabled' => $this->notificationsProfileEnabled,
            'notifications_email_enabled' => $this->notificationsEmailEnabled,
            'notify_expiration_3_days' => $this->notifyExpiration3Days,
            'notify_expiration_day' => $this->notifyExpirationDay,
            'notify_traffic_80' => $this->notifyTraffic80,
            'notify_traffic_100' => $this->notifyTraffic100,
            'notify_provisioned' => $this->notifyProvisioned,
            'notify_critical_errors' => $this->notifyCriticalErrors,
            'hide_sensitive_data' => $this->hideSensitiveData,
            'mask_subscription_links' => $this->maskSubscriptionLinks,
            'public_account_enabled' => $this->publicAccountEnabled,
            'show_qr_in_profile' => $this->showQrInProfile,
        ];
    }
}
