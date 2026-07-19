<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Services\ConfigurationSyncService;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;

final class VpnV2FullReconcileJob
{
    public function handle(): array
    {
        $manual = (new RemoteOperationProcessor())->processNext(['full_reconcile']);
        $configuration = (new ConfigurationSyncService())->syncAllPages(1000, 100, 'reconciliation');
        $plans = db()->query('SELECT id FROM vpn_v2_plans WHERE is_active = 1 ORDER BY id ASC')->get() ?: [];
        $result = ['plans' => 0, 'changed' => 0, 'errors' => 0];
        foreach ($plans as $plan) {
            $result['plans']++;
            try {
                $reconcile = (new VpnPlanSubscriptionReconciler())->reconcilePlan(
                    (int)$plan['id'],
                    ['authorized' => true]
                );
                $result['changed'] += $reconcile->changedSubscriptions;
            } catch (\Throwable) {
                $result['errors']++;
            }
        }

        return ['manual' => $manual, 'configuration' => $configuration, 'plans' => $result];
    }
}
