<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\SubscriptionRequestData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class SubscriptionValidator
{
    public function validate(array $input): SubscriptionRequestData
    {
        $userId = $this->positiveInteger($input['user_id'] ?? null, 'vpn_manager_v2_error_subscription_user_required');
        $planId = $this->positiveInteger($input['plan_id'] ?? null, 'vpn_manager_v2_error_subscription_plan_required');
        $startsAt = $this->dateTime($input['starts_at'] ?? null);

        return new SubscriptionRequestData($userId, $planId, $startsAt);
    }

    private function positiveInteger(mixed $value, string $errorKey): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value <= 0) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t($errorKey));
        }

        return (int)$value;
    }

    private function dateTime(mixed $value): string
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

        throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_starts_at'));
    }
}
