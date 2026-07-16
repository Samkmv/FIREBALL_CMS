<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\SubscriptionEditData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class SubscriptionEditValidator
{
    public function validate(array $input): SubscriptionEditData
    {
        $expiresAt = $this->nullableDateTime($input['expires_at'] ?? null);
        $status = strtolower(trim((string)($input['status'] ?? 'active')));
        if (!in_array($status, ['active', 'suspended', 'expired'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_status'));
        }
        if ($expiresAt !== null && strtotime($expiresAt) <= time()) {
            $status = 'expired';
        }

        return new SubscriptionEditData(
            $expiresAt,
            $this->trafficBytes($input['traffic_limit_value'] ?? 0, (string)($input['traffic_unit'] ?? 'gb')),
            $status,
            $this->nullableString($input['internal_comment'] ?? null, 12000),
        );
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d\\TH:i', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date instanceof \DateTimeImmutable
                && ($errors === false || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))
                && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_expiry'));
    }

    private function trafficBytes(mixed $value, string $unit): ?int
    {
        $normalized = str_replace(',', '.', trim((string)$value));
        if ($normalized === '' || !is_numeric($normalized) || (float)$normalized < 0) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_limit'));
        }
        if ((float)$normalized === 0.0) {
            return null;
        }
        $multiplier = match (strtolower(trim($unit))) {
            'mb' => 1024 ** 2,
            'gb' => 1024 ** 3,
            'tb' => 1024 ** 4,
            default => throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_limit')),
        };
        $bytes = (float)$normalized * $multiplier;
        if (!is_finite($bytes) || $bytes > PHP_INT_MAX) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_edit_limit'));
        }

        return (int)round($bytes);
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $value = mb_substr(trim((string)$value), 0, $limit);

        return $value !== '' ? $value : null;
    }
}
