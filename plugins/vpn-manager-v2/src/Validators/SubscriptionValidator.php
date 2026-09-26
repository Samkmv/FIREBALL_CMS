<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\SubscriptionRequestData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class SubscriptionValidator
{
    public function validate(array $input): SubscriptionRequestData
    {
        $ownerType = strtolower(trim((string)($input['owner_type'] ?? 'registered')));
        if (!in_array($ownerType, ['registered', 'manual'], true)) {
            $ownerType = 'registered';
        }

        if ($ownerType === 'manual') {
            $userId = null;
            $manualCustomerName = $this->manualCustomerName(
                $input['manual_customer_name'] ?? null
            );
        } else {
            $userId = $this->positiveInteger(
                $input['user_id'] ?? null,
                'vpn_manager_v2_error_subscription_user_required'
            );
            $manualCustomerName = null;
        }

        $planId = $this->positiveInteger($input['plan_id'] ?? null, 'vpn_manager_v2_error_subscription_plan_required');
        $startsAt = $this->dateTime(
            $input['starts_at'] ?? null,
            'vpn_manager_v2_error_subscription_starts_at'
        );

        $lifetime = !empty($input['lifetime']);
        $expiresAt = null;
        $rawExpiresAt = trim((string)($input['expires_at'] ?? ''));

        if (!$lifetime && $rawExpiresAt !== '') {
            $expiresAt = $this->dateTime(
                $rawExpiresAt,
                'vpn_manager_v2_error_subscription_expires_at'
            );

            $startsTimestamp = strtotime($startsAt);
            $expiresTimestamp = strtotime($expiresAt);
            if ($startsTimestamp === false
                || $expiresTimestamp === false
                || $expiresTimestamp <= $startsTimestamp) {
                throw new ValidationException(
                    \FireballPluginVpnManagerV2::t(
                        'vpn_manager_v2_error_subscription_expiry_order'
                    )
                );
            }
        }

        return new SubscriptionRequestData(
            $userId,
            $manualCustomerName,
            $planId,
            $startsAt,
            $expiresAt,
            $lifetime
        );
    }

    private function manualCustomerName(mixed $value): string
    {
        $value = trim((string)$value);

        if ($value === '' || mb_strlen($value) < 2 || mb_strlen($value) > 190) {
            throw new ValidationException(
                \FireballPluginVpnManagerV2::t(
                    'vpn_manager_v2_error_manual_customer_name'
                )
            );
        }

        return $value;
    }

    private function positiveInteger(mixed $value, string $errorKey): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value <= 0) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t($errorKey));
        }

        return (int)$value;
    }

    private function dateTime(mixed $value, string $errorKey): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
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

        throw new ValidationException(\FireballPluginVpnManagerV2::t($errorKey));
    }
}
