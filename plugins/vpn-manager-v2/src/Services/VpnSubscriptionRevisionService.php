<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;

final class VpnSubscriptionRevisionService
{
    public function __construct(
        private readonly ?SubscriptionConfigRepository $repository = null,
        private readonly ?VpnSubscriptionCache $subscriptionCache = null,
    ) {
    }

    public function incrementRevision(int $subscriptionId): int
    {
        return $this->bump($subscriptionId, false);
    }

    public function touchConfig(int $subscriptionId): int
    {
        return $this->bump($subscriptionId, true);
    }

    public function invalidateCache(int $subscriptionId, ?int $revision = null): void
    {
        $metadata = $this->repository()->revisionMetadata($subscriptionId);
        if (!$metadata) {
            return;
        }

        ($this->subscriptionCache ?? new VpnSubscriptionCache())->invalidate(
            (string)$metadata['subscription_token'],
            $revision ?? (int)$metadata['revision']
        );
    }

    public function touchByServer(int $serverId): int
    {
        $touched = 0;
        foreach ($this->repository()->subscriptionIdsForServer($serverId) as $subscriptionId) {
            $this->touchConfig($subscriptionId);
            $touched++;
        }

        return $touched;
    }

    private function bump(int $subscriptionId, bool $touchConfig): int
    {
        $repository = $this->repository();
        $before = $repository->revisionMetadata($subscriptionId);
        if (!$before) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }

        $cache = $this->subscriptionCache ?? new VpnSubscriptionCache();
        $cache->invalidate((string)$before['subscription_token'], (int)$before['revision']);
        if (!$repository->incrementRevision($subscriptionId, $touchConfig)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_revision_update'));
        }

        $after = $repository->revisionMetadata($subscriptionId);
        if (!$after) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_revision_update'));
        }
        $cache->invalidate((string)$after['subscription_token'], (int)$after['revision']);

        return (int)$after['revision'];
    }

    private function repository(): SubscriptionConfigRepository
    {
        return $this->repository ?? new SubscriptionConfigRepository();
    }
}
