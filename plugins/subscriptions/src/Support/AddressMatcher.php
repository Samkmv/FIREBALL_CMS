<?php

namespace Fireball\Subscriptions\Support;

final class AddressMatcher
{
    public function match(array $address, array $rules): ?array
    {
        $rules = array_values(array_filter($rules, static fn(mixed $rule): bool => is_array($rule) && !empty($rule['is_active'])));
        usort($rules, static function (array $left, array $right): int {
            $specificity = static fn(array $rule): int => (!empty($rule['normalized_apartment']) ? 100 : 0)
                + (!empty($rule['normalized_house']) ? 10 : 0)
                + (!empty($rule['street_type']) ? 1 : 0);

            return $specificity($right) <=> $specificity($left) ?: ((int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0));
        });

        foreach ($rules as $rule) {
            if ($this->matches($address, $rule)) {
                return $rule;
            }
        }

        return null;
    }

    public function matches(array $address, array $rule): bool
    {
        if (empty($rule['is_active'])) {
            return false;
        }

        $addressStreet = trim((string)($address['normalized_street'] ?? ''));
        $ruleStreet = trim((string)($rule['normalized_street'] ?? ''));
        if ($addressStreet === '' || $ruleStreet === '' || $addressStreet !== $ruleStreet) {
            return false;
        }

        $addressType = trim((string)($address['street_type'] ?? ''));
        $ruleType = trim((string)($rule['street_type'] ?? ''));
        if ($addressType !== '' && $ruleType !== '' && $addressType !== $ruleType) {
            return false;
        }

        $ruleHouse = trim((string)($rule['normalized_house'] ?? ''));
        if ($ruleHouse !== '' && $ruleHouse !== trim((string)($address['normalized_house'] ?? ''))) {
            return false;
        }

        $ruleApartment = trim((string)($rule['normalized_apartment'] ?? ''));
        if ($ruleApartment !== '' && $ruleApartment !== trim((string)($address['normalized_apartment'] ?? ''))) {
            return false;
        }

        return true;
    }
}
