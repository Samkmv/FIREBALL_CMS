<?php

namespace Fireball\VpnManagerV2\Support;

use Fireball\VpnManagerV2\Exceptions\ProvisioningException;

final class VpnReconciliationLock
{
    public function plan(int $planId, callable $callback, int $timeout = 0): mixed
    {
        return $this->within('vpn_v2:plan_reconcile:' . $planId, $callback, $timeout);
    }

    public function subscriptionNode(
        int $subscriptionId,
        int $serverId,
        int $inboundId,
        callable $callback,
        int $timeout = 2
    ): mixed {
        return $this->within(
            'vpn_v2:subscription_node_create:' . $subscriptionId . ':' . $serverId . ':' . $inboundId,
            $callback,
            $timeout
        );
    }

    private function within(string $name, callable $callback, int $timeout): mixed
    {
        $timeout = max(0, min(15, $timeout));
        if ((int)db()->query('SELECT GET_LOCK(?, ?)', [$name, $timeout])->getColumn() !== 1) {
            throw new ProvisioningException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_locked')
            );
        }

        try {
            return $callback();
        } finally {
            try {
                db()->query('SELECT RELEASE_LOCK(?)', [$name]);
            } catch (\Throwable) {
                // MySQL releases advisory locks when the connection closes.
            }
        }
    }
}
