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
        $userId = (int)($subscription['user_id'] ?? 0);

        if ($userId > 0) {
            $user = ($this->subscriptions ?? new SubscriptionRepository())
                ->findUser($userId);

            if (!$user) {
                throw new ValidationException(
                    \FireballPluginVpnManagerV2::t(
                        'vpn_manager_v2_error_subscription_user_not_found'
                    )
                );
            }

            $identity = $this->forTarget($user, $target);
        } else {
            $profile = ($this->profiles ?? new VpnProfileRepository())->find(
                (int)($subscription['profile_id'] ?? 0)
            );
            $manualName = trim(
                (string)($subscription['manual_customer_name'] ?? '')
            );

            if (!$profile || $manualName === '') {
                throw new ValidationException(
                    \FireballPluginVpnManagerV2::t(
                        'vpn_manager_v2_error_manual_customer_identity'
                    )
                );
            }

            $identity = $this->forManualTarget(
                $profile,
                $manualName,
                $target
            );
        }

        ($this->subscriptions ?? new SubscriptionRepository())->assignProfile(
            (int)($subscription['id'] ?? 0),
            (int)$identity['profile_id']
        );

        return $identity;
    }

    public function forManualProfile(
        array $profile,
        string $name,
        string $countryCode,
        string $protocol
    ): array {
        $profileId = (int)($profile['id'] ?? 0);
        $name = trim($name);
        $sharedUuid = trim((string)($profile['shared_uuid'] ?? ''));

        if ($profileId <= 0 || $name === '' || $sharedUuid === '') {
            throw new ValidationException(
                \FireballPluginVpnManagerV2::t(
                    'vpn_manager_v2_error_manual_customer_identity'
                )
            );
        }

        $protocol = strtolower(trim($protocol));
        $login = 'manual-' . $profileId;

        return [
            'profile_id' => $profileId,
            'cms_user_id' => null,
            'cms_user_name' => $name,
            'cms_user_login' => $login,
            'country_code' => strtoupper(trim($countryCode)),
            'remote_client_name' => (
                $this->names ?? new RemoteClientNameGenerator()
            )->generate($name, $login, $countryCode),
            'client_uuid' => $sharedUuid,
            'client_password' => in_array(
                $protocol,
                ['trojan', 'shadowsocks'],
                true
            )
                ? ($this->profiles ?? new VpnProfileRepository())
                    ->sharedPassword($profile)
                : null,
        ];
    }

    public function forManualTarget(
        array $profile,
        string $name,
        array $target
    ): array {
        $identity = $this->forManualProfile(
            $profile,
            $name,
            (string)($target['country_code'] ?? ''),
            (string)($target['protocol'] ?? '')
        );

        $identity['remote_client_name'] = (
            $this->names ?? new RemoteClientNameGenerator()
        )->forConnection(
            (string)$identity['remote_client_name'],
            (int)($target['server_id'] ?? 0),
            (int)($target['inbound_id'] ?? 0)
        );

        return $identity;
    }

    public function forTarget(array $user, array $target): array
    {
        $identity = $this->forUser(
            $user,
            (string)($target['country_code'] ?? ''),
            (string)($target['protocol'] ?? '')
        );
        $identity['remote_client_name'] = ($this->names ?? new RemoteClientNameGenerator())->forConnection(
            (string)$identity['remote_client_name'],
            (int)($target['server_id'] ?? 0),
            (int)($target['inbound_id'] ?? 0)
        );

        return $identity;
    }
}
