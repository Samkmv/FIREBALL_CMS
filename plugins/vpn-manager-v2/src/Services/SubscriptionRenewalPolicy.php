<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\SubscriptionEditData;

final class SubscriptionRenewalPolicy
{
    public function normalize(array $current, SubscriptionEditData $edit, ?int $now = null): SubscriptionEditData
    {
        if ($edit->status !== 'expired') {
            return $edit;
        }

        $now ??= time();
        $currentExpiry = $this->timestamp($current['expires_at'] ?? null);
        $wasExpired = (string)($current['status'] ?? '') === 'expired'
            || ($currentExpiry !== null && $currentExpiry <= $now);
        $renewedExpiry = $this->timestamp($edit->expiresAt);
        $hasValidRenewal = $edit->expiresAt === null || ($renewedExpiry !== null && $renewedExpiry > $now);

        if (!$wasExpired || !$hasValidRenewal) {
            return $edit;
        }

        return new SubscriptionEditData(
            $edit->expiresAt,
            $edit->trafficLimitBytes,
            'active',
            $edit->internalComment,
        );
    }

    private function timestamp(mixed $value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? $timestamp : null;
    }
}
