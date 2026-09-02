<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Repositories\AddressExclusionRepository;
use Fireball\Subscriptions\Repositories\ProfileRepository;
use Fireball\Subscriptions\Support\AddressMatcher;
use Fireball\Subscriptions\Support\AddressNormalizer;

final class SubscriptionEligibilityService
{
    public const REASON_ADDRESS_INCLUDED_IN_UTILITIES = 'ADDRESS_INCLUDED_IN_UTILITIES';

    public function __construct(
        private readonly AddressExclusionRepository $exclusions = new AddressExclusionRepository(),
        private readonly AddressNormalizer $normalizer = new AddressNormalizer(),
        private readonly AddressMatcher $matcher = new AddressMatcher()
    ) {
    }

    public function evaluateUser(int $userId, ?array $profile = null, bool $markProfile = true): array
    {
        $profile ??= (new ProfileRepository())->profileForUser($userId, false);
        if (!$profile) {
            return ['eligible' => true, 'reason' => null, 'matched_exception_id' => null];
        }

        return $this->evaluateProfile($profile, $markProfile);
    }

    public function evaluateProfile(array $profile, bool $markProfile = true, ?array $rules = null): array
    {
        $address = $this->normalizer->normalizeProfile($profile);
        $match = $this->matcher->match($address, $rules ?? $this->exclusions->active());
        $result = [
            'eligible' => $match === null,
            'reason' => $match === null ? null : self::REASON_ADDRESS_INCLUDED_IN_UTILITIES,
            'matched_exception_id' => $match === null ? null : (int)$match['id'],
        ];

        if ($markProfile && (int)($profile['id'] ?? 0) > 0) {
            $this->markProfile((int)$profile['id'], $result);
        }

        return $result;
    }

    public function assertEligible(int $userId, string $stage, ?array $profile = null): array
    {
        $result = $this->evaluateUser($userId, $profile, true);
        if (!empty($result['eligible'])) {
            return $result;
        }

        try {
            (new SubscriptionService())->event(
                'subscription.checkout_blocked_by_address',
                null,
                null,
                $userId,
                null,
                'blocked',
                [
                    'reason' => self::REASON_ADDRESS_INCLUDED_IN_UTILITIES,
                    'stage' => mb_substr($stage, 0, 80),
                    'address_exclusion_id' => (int)$result['matched_exception_id'],
                ]
            );
        } catch (\Throwable $exception) {
            log_error_details('Subscription eligibility audit failed', [
                'user_id' => $userId,
                'address_exclusion_id' => (int)$result['matched_exception_id'],
                'stage' => mb_substr($stage, 0, 80),
            ], $exception);
        }

        throw new \DomainException(\FireballPluginSubscriptions::t('subscriptions_address_included_in_utilities'));
    }

    public function refreshAllProfiles(): int
    {
        $profiles = db()->query('SELECT * FROM subscription_profiles ORDER BY id')->get() ?: [];
        $rules = $this->exclusions->active();
        $matched = 0;
        foreach ($profiles as $profile) {
            $result = $this->evaluateProfile($profile, true, $rules);
            if (empty($result['eligible'])) {
                $matched++;
            }
        }

        return $matched;
    }

    private function markProfile(int $profileId, array $result): void
    {
        db()->query(
            'UPDATE subscription_profiles SET address_excluded = ?, matched_address_exclusion_id = ?, address_checked_at = ? WHERE id = ?',
            [
                empty($result['eligible']) ? 1 : 0,
                $result['matched_exception_id'],
                date('Y-m-d H:i:s'),
                $profileId,
            ]
        );
    }
}
