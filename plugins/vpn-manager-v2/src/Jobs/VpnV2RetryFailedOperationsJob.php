<?php

namespace Fireball\VpnManagerV2\Jobs;

use Fireball\VpnManagerV2\Services\RetryFailedOperationsService;
use Fireball\VpnManagerV2\Services\RemoteOperationProcessor;

final class VpnV2RetryFailedOperationsJob
{
    public function handle(): array
    {
        return [
            'legacy' => (new RetryFailedOperationsService())->retry(),
            'queue' => (new RemoteOperationProcessor())->processNext([
                'delete_client', 'reset_traffic',
                'cascade_disable_children', 'cascade_enable_children',
                'recalculate_effective_status', 'detach_child_subscription', 'detach_child_connection',
            ]),
        ];
    }
}
