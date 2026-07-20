<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionItemRepository;

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

    public function touchConnection(int $connectionId): int
    {
        $row = db()->query(
            'SELECT subscription_id FROM vpn_v2_subscription_nodes WHERE id = ? LIMIT 1',
            [$connectionId]
        )->getOne();
        $touched = 0;
        if (is_array($row) && (int)$row['subscription_id'] > 0) {
            $this->touchConfig((int)$row['subscription_id']);
            $touched++;
        }

        return $touched;
    }

    private function bump(int $subscriptionId, bool $touchConfig): int
    {
        $revision = $this->bumpSingle($subscriptionId, $touchConfig, true);
        $this->propagateToParents($subscriptionId, $touchConfig);

        return $revision;
    }

    private function bumpSingle(int $subscriptionId, bool $touchConfig, bool $required): int
    {
        $repository = $this->repository();
        $before = $repository->revisionMetadata($subscriptionId);
        if (!$before) {
            if (!$required) {
                return 0;
            }
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

    private function propagateToParents(int $subscriptionId, bool $touchConfig): void
    {
        $items = new SubscriptionItemRepository();
        $queue = [[$subscriptionId, 0]];
        $visited = [$subscriptionId => true];
        while ($queue !== []) {
            [$childId, $depth] = array_shift($queue);
            if ($depth >= 32) {
                break;
            }
            $parentIds = array_values(array_unique(array_merge(
                $items->parentIdsForChildSubscription($childId),
                $items->parentIdsForConnectionsInSubscription($childId)
            )));
            foreach ($parentIds as $parentId) {
                if (isset($visited[$parentId])) {
                    continue;
                }
                $visited[$parentId] = true;
                $this->bumpSingle($parentId, $touchConfig, false);
                $queue[] = [$parentId, $depth + 1];
            }
        }
    }

    private function repository(): SubscriptionConfigRepository
    {
        return $this->repository ?? new SubscriptionConfigRepository();
    }
}
