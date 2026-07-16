<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SettingsRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

final class SettingsService
{
    private const CONFIG_KEYS = ['service_name', 'server_name_template', 'global_show_flags'];
    private const SECRET_MARKERS = ['password', 'secret', 'token', 'cookie', 'authorization'];

    public function __construct(
        private readonly ?SettingsRepository $repository = null,
        private readonly ?SettingsValidator $validator = null,
        private readonly ?SubscriptionConfigRepository $subscriptionRepository = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?VpnSubscriptionCache $subscriptionCache = null,
        private readonly ?QrCodeService $qrCode = null,
    ) {
    }

    public static function defaults(): array
    {
        return [
            'service_name' => 'VPN V2',
            'server_name_template' => '{flag} {service} · {country} {city} · {server} · {protocol}',
            'global_show_flags' => true,
            'support_name' => '',
            'support_url' => '',
            'logo' => '',
            'expired_subscription_behavior' => 'gone',
            'subscription_cache_ttl_seconds' => 300,
            'qr_cache_ttl_seconds' => 3600,
            'settings_cache_ttl_seconds' => 300,
            'sync_enabled' => true,
            'sync_interval_minutes' => 15,
            'server_check_interval_minutes' => 10,
            'retry_failed_operations' => true,
            'notifications_profile_enabled' => true,
            'notifications_email_enabled' => false,
            'notify_expiration_3_days' => true,
            'notify_expiration_day' => true,
            'notify_traffic_80' => true,
            'notify_traffic_100' => true,
            'notify_provisioned' => true,
            'notify_critical_errors' => false,
            'hide_sensitive_data' => true,
            'mask_subscription_links' => true,
            'public_account_enabled' => true,
            'show_qr_in_profile' => true,
        ];
    }

    public function current(): array
    {
        if (!function_exists('db')) {
            return self::defaults();
        }

        try {
            return $this->normalize($this->repository()->read(self::defaults()));
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 settings read failed: ' . get_class($exception));

            return self::defaults();
        }
    }

    public function ensureDefaults(): array
    {
        if (!function_exists('db')) {
            return ['settings' => self::defaults(), 'inserted_keys' => [], 'revisions_touched' => 0];
        }
        $repository = $this->repository();
        $repository->assertStorageReady();
        $missing = $repository->missing(self::defaults());
        if ($missing === []) {
            $repository->invalidateCache();

            return ['settings' => $this->fresh(), 'inserted_keys' => [], 'revisions_touched' => 0];
        }

        $ownsTransaction = !db()->inTransaction();
        $revisions = 0;
        if ($ownsTransaction) {
            db()->beginTransaction();
        }
        try {
            $repository->write($missing);
            $this->assertReadBack($repository->stored(), $missing);
            if ($this->intersects(array_keys($missing), self::CONFIG_KEYS)) {
                $revisions = $this->touchGlobalConfig();
            }
            if ($ownsTransaction) {
                db()->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && db()->inTransaction()) {
                db()->rollBack();
            }
            $repository->invalidateCache();
            throw $exception;
        }

        $repository->invalidateCache();
        $stored = $repository->stored();
        $this->assertReadBack($stored, self::defaults());
        $settings = $this->normalize(array_replace(self::defaults(), $stored));

        return [
            'settings' => $settings,
            'inserted_keys' => array_keys($missing),
            'revisions_touched' => $revisions,
        ];
    }

    public function save(array $input): array
    {
        $repository = $this->repository();
        $repository->assertStorageReady();
        $ownsTransaction = !db()->inTransaction();
        $revisions = 0;
        $changedKeys = [];
        $metadata = [];
        $expected = [];
        if ($ownsTransaction) {
            db()->beginTransaction();
        }

        try {
            $before = $this->normalize(array_replace(self::defaults(), $repository->stored(true)));
            $expected = ($this->validator ?? new SettingsValidator())->validate($input, $before)->toArray();
            $changedKeys = $this->changedKeys($before, $expected);
            $metadata = $this->subscriptionRepository()->activeRevisionMetadata();
            $repository->write($expected);
            $insideStored = $repository->stored();
            $this->assertReadBack($insideStored, $expected);
            $inside = $this->normalize(array_replace(self::defaults(), $insideStored));
            if ($this->intersects($changedKeys, self::CONFIG_KEYS)) {
                $revisions = $this->touchGlobalConfig();
            }
            if ($ownsTransaction) {
                db()->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && db()->inTransaction()) {
                db()->rollBack();
            }
            $repository->invalidateCache();
            throw $exception;
        }

        $repository->invalidateCache();
        $afterStored = $repository->stored();
        $this->assertReadBack($afterStored, $expected);
        $after = $this->normalize(array_replace(self::defaults(), $afterStored));
        $this->invalidateDependentCaches($changedKeys, $metadata);

        return [
            'settings' => $after,
            'changed_keys' => $changedKeys,
            'revisions_touched' => $revisions,
        ];
    }

    public static function safeFieldNames(array $input): array
    {
        $fields = array_filter(array_keys($input), static function (mixed $field): bool {
            $field = strtolower((string)$field);
            foreach (self::SECRET_MARKERS as $marker) {
                if (str_contains($field, $marker)) {
                    return false;
                }
            }

            return $field !== '';
        });
        sort($fields);

        return array_values($fields);
    }

    private function fresh(): array
    {
        return $this->normalize($this->repository()->read(self::defaults(), false));
    }

    private function normalize(array $settings): array
    {
        return ($this->validator ?? new SettingsValidator())
            ->validateStored(array_replace(self::defaults(), $settings))
            ->toArray();
    }

    private function touchGlobalConfig(): int
    {
        $touched = 0;
        $service = $this->revisionService ?? new VpnSubscriptionRevisionService(
            $this->subscriptionRepository(),
            $this->subscriptionCache ?? new VpnSubscriptionCache()
        );
        foreach ($this->subscriptionRepository()->subscriptionIdsForGlobalConfig() as $subscriptionId) {
            $service->touchConfig($subscriptionId);
            $touched++;
        }

        return $touched;
    }

    private function invalidateDependentCaches(array $changedKeys, array $metadata): void
    {
        $subscriptionCache = $this->subscriptionCache ?? new VpnSubscriptionCache();
        $qrCode = $this->qrCode ?? new QrCodeService();
        foreach ($metadata as $row) {
            $token = trim((string)($row['subscription_token'] ?? ''));
            $revision = max(1, (int)($row['revision'] ?? 1));
            if ($token === '') {
                continue;
            }
            if (in_array('subscription_cache_ttl_seconds', $changedKeys, true)) {
                $subscriptionCache->invalidate($token, $revision);
            }
            if (in_array('qr_cache_ttl_seconds', $changedKeys, true)) {
                $qrCode->invalidateToken($token);
            }
        }
    }

    private function assertReadBack(array $actual, array $expected): void
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual) || $actual[$key] !== $value) {
                throw new VpnManagerV2Exception(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_settings_read_after_write')
                );
            }
        }
    }

    private function changedKeys(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before) || $before[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function intersects(array $left, array $right): bool
    {
        return array_intersect($left, $right) !== [];
    }

    private function repository(): SettingsRepository
    {
        return $this->repository ?? new SettingsRepository();
    }

    private function subscriptionRepository(): SubscriptionConfigRepository
    {
        return $this->subscriptionRepository ?? new SubscriptionConfigRepository();
    }
}
