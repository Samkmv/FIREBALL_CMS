<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;

final class SubscriptionNamingService
{
    public function rename(int $subscriptionId, mixed $inputName, ?int $adminId = null): array
    {
        if (!is_string($inputName) || mb_strlen(trim($inputName)) > 160
            || preg_match('/[\x00-\x1f\x7f]/u', $inputName) !== 0) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_invalid'));
        }
        $name = trim($inputName);
        $repository = new SubscriptionRepository();
        $subscription = $repository->findForProvisioning($subscriptionId);
        if (!$subscription || in_array((string)$subscription['status'], ['deleted', 'deleting', 'delete_failed'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }
        $userId = (int)($subscription['user_id'] ?? 0);
        $user = $userId > 0 ? $repository->findUser($userId) : null;
        $baseName = $name !== '' ? $name : trim((string)($user['name'] ?? $subscription['manual_customer_name'] ?? ''));
        $login = $userId > 0 ? (string)($user['login'] ?? '') : 'manual-' . (int)($subscription['profile_id'] ?? 0);
        $names = new RemoteClientNameGenerator();
        if ($names->normalize($baseName) === '' || ($userId > 0 && !$user)
            || ($userId === 0 && (int)($subscription['profile_id'] ?? 0) <= 0)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_invalid'));
        }

        // Validate all targets before changing any desired names. No new UUIDs/profiles/clients.
        $targets = [];
        foreach ($repository->nodeIdsForSubscription($subscriptionId) as $nodeId) {
            $node = $repository->connectionForProvisioning($nodeId);
            if (!$node || (int)$node['subscription_id'] !== $subscriptionId
                || in_array((string)$node['status'], ['deleted', 'deleting', 'pending_remote_delete'], true)) {
                continue;
            }
            $expected = $names->forConnection(
                $names->generate($baseName, $login, (string)$node['country_code']),
                (int)$node['server_id'], (int)$node['inbound_id']
            );
            $targets[] = [$node, $expected];
        }
        $changed = trim((string)($subscription['client_display_name'] ?? '')) !== $name;
        $repository->setClientDisplayName($subscriptionId, $name !== '' ? $name : null);
        $result = ['queued' => 0, 'failed' => 0, 'changed' => $changed];
        $queue = new OperationQueueRepository();
        foreach ($targets as [$node, $expected]) {
            $nodeId = (int)$node['id'];
            $different = (string)$node['client_email'] !== $expected || (string)$node['remote_client_name'] !== $expected;
            try {
                if ($different && !$repository->setExpectedClientName($subscriptionId, $nodeId, $expected)) {
                    throw new \RuntimeException('VPN rename target changed.');
                }
                $result['changed'] = $result['changed'] || $different;
                // Unprovisioned rows already carry the new desired name; their normal creation/retry will use it.
                if (!in_array((string)$node['status'], ['active', 'disabled'], true)
                    || (!$different && (string)($node['sync_status'] ?? '') === 'synced')) {
                    continue;
                }
                // No stale name in the payload: every retry reads the newest desired label and access policy.
                $queue->enqueue('rename_client', 'cms', (int)$node['server_id'], $subscriptionId, $nodeId, [], $adminId);
                $result['queued']++;
            } catch (\Throwable) {
                $result['failed']++;
                $repository->markClientNameQueueFailure($subscriptionId, $nodeId, \FireballPluginVpnManagerV2::t('vpn_manager_v2_client_name_queue_error'));
            }
        }
        if ($result['changed']) { (new VpnSubscriptionRevisionService())->touchConfig($subscriptionId); }
        $repository->logEvent('subscription.client_name_updated', $subscriptionId, null, null, $userId ?: null, $adminId,
            ['queued' => $result['queued'], 'failed' => $result['failed'], 'automatic_name' => $name === '']);

        return $result;
    }
}
