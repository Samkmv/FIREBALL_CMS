<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\VpnProfileRepository;

final class RemoteClientIdentityService
{
    public function __construct(
        private readonly ?VpnProfileRepository $profiles = null,
        private readonly ?RemoteClientNameGenerator $names = null,
        private readonly ?SubscriptionRepository $subscriptions = null,
    ) {
    }

    public function forUser(array $user, string $countryCode, string $protocol): array
    {
        $userId = (int)($user['id'] ?? 0);
        $name = trim((string)($user['name'] ?? ''));
        $login = trim((string)($user['login'] ?? ''));
        if ($userId <= 0 || $name === '' || $login === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_identity_required'));
        }

        $profile = ($this->profiles ?? new VpnProfileRepository())->getOrCreate(
            $userId,
            ($this->subscriptions ?? new SubscriptionRepository())->stableCredentialForUser($userId)
        );
        $protocol = strtolower(trim($protocol));

        return [
            'profile_id' => (int)$profile['id'],
            'cms_user_id' => $userId,
            'cms_user_name' => $name,
            'cms_user_login' => $login,
            'country_code' => strtoupper(trim($countryCode)),
            'remote_client_name' => ($this->names ?? new RemoteClientNameGenerator())
                ->generate($name, $login, $countryCode),
            'client_uuid' => (string)$profile['shared_uuid'],
            'client_password' => in_array($protocol, ['trojan', 'shadowsocks'], true)
                ? ($this->profiles ?? new VpnProfileRepository())->sharedPassword($profile)
                : null,
        ];
    }

    public function forSubscription(array $subscription, array $target): array
    {
        $user = ($this->subscriptions ?? new SubscriptionRepository())->findUser((int)($subscription['user_id'] ?? 0));
        if (!$user) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_user_not_found'));
        }

        $identity = $this->forUser(
            $user,
            (string)($target['country_code'] ?? ''),
            (string)($target['protocol'] ?? '')
        );
        ($this->subscriptions ?? new SubscriptionRepository())->assignProfile(
            (int)($subscription['id'] ?? 0),
            (int)$identity['profile_id']
        );

        return $identity;
    }
}
