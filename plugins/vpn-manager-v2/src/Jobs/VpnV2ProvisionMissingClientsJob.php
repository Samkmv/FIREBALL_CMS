<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Repositories\ConfigurationSyncRepository;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;

final class VpnV2ProvisionMissingClientsJob
{
    public function handle(): array
    {
        $queued = (new RemoteOperationProcessor())->processNext(['create_client']);
        $result = ['processed' => 0, 'success' => 0, 'failure' => 0, 'queued' => $queued];
        foreach ((new ConfigurationSyncRepository())->provisionableNodes(20) as $row) {
            $result['processed']++;
            try {
                (new VpnPlanSubscriptionReconciler())->retryFailedNode((int)$row['id']);
                $result['success']++;
            } catch (\Throwable) {
                $result['failure']++;
            }
        }

        return $result;
    }
}
