<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\ConfigurationSyncService;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;

final class VpnV2SyncConfigurationJob
{
    public function handle(): array
    {
        $manual = (new RemoteOperationProcessor())->processDue(5, [
            'sync_server', 'sync_inbound', 'sync_client', 'sync_subscription',
            'update_client', 'rename_client', 'enable_client', 'disable_client',
            'full_reconcile',
            'cascade_disable_children', 'cascade_enable_children',
            'recalculate_effective_status', 'detach_child_subscription', 'detach_child_connection',
        ]);
        $service = new ConfigurationSyncService();
        $cursorKey = 'vpn-v2:configuration-sync-cursor';
        $cursor = max(0, (int)cache()->get($cursorKey, 0));
        $scheduled = $service->syncAll($cursor, 20, 'reconciliation');
        if ((int)$scheduled['servers'] === 0 && $cursor > 0) {
            $scheduled = $service->syncAll(0, 20, 'reconciliation');
        }
        cache()->set($cursorKey, (int)$scheduled['last_server_id'], 86400 * 30);

        return ['manual' => $manual, 'scheduled' => $scheduled];
    }
}
