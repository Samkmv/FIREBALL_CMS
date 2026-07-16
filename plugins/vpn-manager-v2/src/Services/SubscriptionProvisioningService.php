<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\DTO\ProvisioningResult;
use Fireball\VpnManagerV2\Exceptions\ClientVerificationException;
use Fireball\VpnManagerV2\Exceptions\ProvisioningException;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\SubscriptionToken;
use Fireball\VpnManagerV2\Support\Uuid;
use Fireball\VpnManagerV2\Validators\SubscriptionValidator;

final class SubscriptionProvisioningService
{
    public function __construct(
        private readonly ?SubscriptionRepository $repository = null,
        private readonly ?SubscriptionValidator $validator = null,
        private readonly ?VpnFlowResolver $flowResolver = null,
        private readonly ?ClientPayloadFactory $payloadFactory = null,
        private readonly ?ClientVerifier $clientVerifier = null,
        private readonly ?\Closure $clientFactory = null,
        private readonly ?VpnSubscriptionRevisionService $revisionService = null,
        private readonly ?\Closure $notificationCallback = null,
    ) {
    }

    public function create(array $input, ?int $adminId = null): ProvisioningResult
    {
        $repository = $this->repository();
        $request = ($this->validator ?? new SubscriptionValidator())->validate($input);
        $user = $repository->findUser($request->userId);
        if (!$user) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_user_not_found'));
        }

        $adminId = $adminId ?? $this->currentAdminId();
        $admin = $repository->findUser($adminId);
        if (!$admin || !in_array((string)$admin['role'], ['creator', 'admin'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_admin_required'));
        }

        $plan = $repository->activePlan($request->planId);
        if (!$plan) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_plan_inactive'));
        }
        $planNodes = $repository->activePlanNodes((int)$plan['id']);
        if ($planNodes === []) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_plan_nodes_empty'));
        }

        $startsAt = new \DateTimeImmutable($request->startsAt);
        $expiresAt = $startsAt->add(new \DateInterval('P' . max(1, (int)$plan['duration_days']) . 'D'));
        $localNodes = $this->prepareLocalNodes($planNodes);
        $subscriptionId = $repository->createLocal([
            'user_id' => (int)$user['id'],
            'plan_id' => (int)$plan['id'],
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'traffic_limit_bytes' => $plan['traffic_limit_bytes'] !== null ? (int)$plan['traffic_limit_bytes'] : null,
            'device_limit' => (int)$plan['device_limit'],
            'subscription_token' => $this->uniqueToken($repository),
            'created_by' => $adminId,
        ], $localNodes);

        // The local subscription and every local node are committed before this method performs HTTP.
        return $this->provision($subscriptionId);
    }

    public function provision(int $subscriptionId): ProvisioningResult
    {
        $repository = $this->repository();
        $subscription = $repository->findForProvisioning($subscriptionId);
        if (!$subscription) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }

        $repository->logEvent(
            'subscription.provisioning_started',
            $subscriptionId,
            null,
            null,
            (int)$subscription['user_id'],
            (int)$subscription['created_by'],
            ['node_count' => count($repository->nodesForSubscription($subscriptionId))]
        );

        $created = 0;
        $reused = 0;
        $failed = 0;
        $syncErrors = 0;
        $flowError = false;
        $configChanged = false;
        foreach ($repository->nodesForSubscription($subscriptionId) as $node) {
            if ((string)$node['status'] === 'active') {
                $reused++;
                continue;
            }
            if ((string)$node['status'] !== 'creating') {
                continue;
            }

            $outcome = $this->provisionNode((int)$node['id']);
            if ($outcome['status'] === 'created') {
                $created++;
                $configChanged = true;
            } elseif ($outcome['status'] === 'reused') {
                $reused++;
                $configChanged = true;
            } elseif ($outcome['status'] === 'sync_error') {
                $syncErrors++;
            } else {
                $failed++;
            }
            $flowError = $flowError || !empty($outcome['flow_error']);
        }

        $status = $repository->recalculateSubscriptionStatus($subscriptionId);
        if ($configChanged && $status === 'active') {
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
        }
        $repository->logEvent(
            $status === 'active' ? 'subscription.provisioning_completed' : 'subscription.provisioning_failed',
            $subscriptionId,
            null,
            null,
            (int)$subscription['user_id'],
            (int)$subscription['created_by'],
            [
                'created' => $created,
                'reused' => $reused,
                'failed' => $failed,
                'sync_errors' => $syncErrors,
                'flow_error' => $flowError,
                'status' => $status,
            ]
        );
        if ($configChanged && $status === 'active') {
            $this->notifyProvisioned($subscriptionId);
        } elseif ($status !== 'active') {
            $this->notifyCritical($subscriptionId, 'provisioning');
        }

        return new ProvisioningResult($subscriptionId, $created, $reused, $failed, $syncErrors, $flowError, $status);
    }

    public function retryNode(int $nodeId): ProvisioningResult
    {
        $repository = $this->repository();
        $node = $repository->connectionForProvisioning($nodeId);
        if (!$node) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found'));
        }
        if (!$repository->claimRetry($nodeId)) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_retry_status'));
        }

        $subscriptionId = (int)$node['subscription_id'];
        $repository->setSubscriptionProvisioning($subscriptionId);
        $repository->logEvent(
            'node.provisioning_retry',
            $subscriptionId,
            $nodeId,
            (int)$node['server_id'],
            (int)$node['user_id'],
            (int)$node['created_by'],
            ['previous_status' => (string)$node['status']]
        );

        $outcome = $this->provisionNode($nodeId);
        $status = $repository->recalculateSubscriptionStatus($subscriptionId);
        if (in_array($outcome['status'], ['created', 'reused'], true) && $status === 'active') {
            ($this->revisionService ?? new VpnSubscriptionRevisionService())->touchConfig($subscriptionId);
            $this->notifyProvisioned($subscriptionId);
        } elseif ($status !== 'active') {
            $this->notifyCritical($subscriptionId, 'provisioning-retry');
        }

        return new ProvisioningResult(
            $subscriptionId,
            $outcome['status'] === 'created' ? 1 : 0,
            $outcome['status'] === 'reused' ? 1 : 0,
            $outcome['status'] === 'create_failed' ? 1 : 0,
            $outcome['status'] === 'sync_error' ? 1 : 0,
            !empty($outcome['flow_error']),
            $status,
        );
    }

    /**
     * Provisions one local-first node without changing the subscription revision.
     * Reconciliation aggregates confirmed node changes and bumps the revision once.
     */
    public function provisionNodeForReconciliation(int $nodeId): array
    {
        return $this->provisionNode($nodeId);
    }

    private function provisionNode(int $nodeId): array
    {
        $repository = $this->repository();
        $node = $repository->connectionForProvisioning($nodeId);
        if (!$node) {
            throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_not_found'));
        }

        try {
            if (db()->inTransaction()) {
                throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_http_inside_transaction'));
            }

            $server = (new ServerRepository())->findWithSecrets((int)$node['server_id']);
            $inbound = $repository->inbound((int)$node['inbound_id']);
            if (!$server || empty($server['is_enabled'])) {
                throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_provisioning_server_unavailable'));
            }
            if (!$inbound
                || (int)$inbound['server_id'] !== (int)$server['id']
                || empty($inbound['is_enabled'])
                || (string)$inbound['status'] !== 'active') {
                throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_provisioning_inbound_unavailable'));
            }

            $client = $this->client($server, $inbound, $node);
            $subscriptionState = [
                'expires_at' => $node['expires_at'] ?? null,
                'status' => $node['subscription_status'] ?? 'active',
                'device_limit' => $node['device_limit'] ?? 0,
                'traffic_limit_bytes' => $node['subscription_traffic_limit_bytes'] ?? null,
            ];
            $payload = ($this->payloadFactory ?? new ClientPayloadFactory())->build($subscriptionState, $node);
            $verifier = $this->clientVerifier ?? new ClientVerifier($this->flowResolver ?? new VpnFlowResolver());
            $remoteInboundId = (int)$inbound['remote_inbound_id'];
            $currentInbound = $client->getInbound($remoteInboundId);
            $existing = $verifier->findInInbound(
                $currentInbound,
                (string)$node['client_uuid'],
                (string)$node['client_email'],
                (string)($node['remote_client_id'] ?? ''),
                (string)($node['client_sub_id'] ?? '')
            );
            if ($existing !== null) {
                $verifier->verify($existing, $payload);
                $repository->markNodeActive(
                    $nodeId,
                    (string)$node['client_uuid'],
                    empty($node['desired_enabled']) ? 'disabled' : 'active'
                );
                $repository->logEvent(
                    'node.client_reused',
                    (int)$node['subscription_id'],
                    $nodeId,
                    (int)$node['server_id'],
                    (int)$node['user_id'],
                    (int)$node['created_by'],
                    ['inbound_id' => (int)$node['inbound_id']]
                );

                return ['status' => 'reused', 'flow_error' => false];
            }

            $client->addClient($remoteInboundId, $payload);
            $freshInbound = $client->getInbound($remoteInboundId);
            $confirmed = $verifier->findInInbound(
                $freshInbound,
                (string)$node['client_uuid'],
                (string)$node['client_email'],
                (string)($node['remote_client_id'] ?? ''),
                (string)($node['client_sub_id'] ?? '')
            );
            if ($confirmed === null) {
                throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_not_confirmed'));
            }
            $verifier->verify($confirmed, $payload);

            $repository->markNodeActive(
                $nodeId,
                (string)$node['client_uuid'],
                empty($node['desired_enabled']) ? 'disabled' : 'active'
            );
            $repository->logEvent(
                'node.client_created',
                (int)$node['subscription_id'],
                $nodeId,
                (int)$node['server_id'],
                (int)$node['user_id'],
                (int)$node['created_by'],
                ['inbound_id' => (int)$node['inbound_id']]
            );

            return ['status' => 'created', 'flow_error' => false];
        } catch (ClientVerificationException $exception) {
            $safeError = $this->safeError($exception);
            $repository->markNodeFailure($nodeId, 'sync_error', $safeError);
            $repository->logEvent(
                'node.sync_error',
                (int)$node['subscription_id'],
                $nodeId,
                (int)$node['server_id'],
                (int)$node['user_id'],
                (int)$node['created_by'],
                ['error_type' => 'client_verification', 'flow_mismatch' => $exception->isFlowMismatch()]
            );

            return ['status' => 'sync_error', 'flow_error' => $exception->isFlowMismatch()];
        } catch (\Throwable $exception) {
            $safeError = $this->safeError($exception);
            $repository->markNodeFailure($nodeId, 'create_failed', $safeError);
            $repository->logEvent(
                'node.create_failed',
                (int)$node['subscription_id'],
                $nodeId,
                (int)$node['server_id'],
                (int)$node['user_id'],
                (int)$node['created_by'],
                ['error_type' => $this->errorType($exception)]
            );

            return ['status' => 'create_failed', 'flow_error' => false];
        }
    }

    private function prepareLocalNodes(array $planNodes): array
    {
        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $payloadFactory = $this->payloadFactory ?? new ClientPayloadFactory();
        $nodes = [];
        foreach ($planNodes as $planNode) {
            $storedFlow = $planNode['flow_override'] ?? null;
            if ($storedFlow === null) {
                $flow = $resolver->resolveDefaultFlow($planNode);
            } elseif (trim((string)$storedFlow) === '') {
                $flow = null;
            } else {
                $flow = $resolver->normalizeFlow((string)$storedFlow);
            }
            if (!$resolver->isFlowCompatible($flow, $planNode)) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'));
            }

            $protocol = strtolower(trim((string)$planNode['protocol']));
            $nodes[] = [
                'plan_node_id' => (int)$planNode['plan_node_id'],
                'server_id' => (int)$planNode['server_id'],
                'inbound_id' => (int)$planNode['inbound_id'],
                'client_uuid' => Uuid::v4(),
                'client_sub_id' => $payloadFactory->requiresSubId($protocol) ? bin2hex(random_bytes(8)) : null,
                'protocol' => $protocol,
                'network' => $this->nullableDimension($planNode['network'] ?? null),
                'security' => $this->nullableDimension($planNode['security'] ?? null),
                'flow' => $flow,
            ];
        }

        return $nodes;
    }

    private function uniqueToken(SubscriptionRepository $repository): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = SubscriptionToken::generate();
            if (!$repository->tokenExists($token)) {
                return $token;
            }
        }

        throw new ProvisioningException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_token'));
    }

    private function notifyProvisioned(int $subscriptionId): void
    {
        try {
            if ($this->notificationCallback !== null) {
                ($this->notificationCallback)('provisioned', $subscriptionId, null);
                return;
            }
            (new VpnNotificationService())->notifyProvisioned($subscriptionId);
        } catch (\Throwable) {
            // Provisioning is already confirmed; a notification transport cannot roll it back.
        }
    }

    private function notifyCritical(int $subscriptionId, string $operation): void
    {
        try {
            if ($this->notificationCallback !== null) {
                ($this->notificationCallback)('critical_error', $subscriptionId, $operation);
                return;
            }
            (new VpnNotificationService())->notifyCritical($subscriptionId, $operation);
        } catch (\Throwable) {
            // A notification transport cannot alter the recoverable provisioning state.
        }
    }

    private function client(array $server, array $inbound, array $node): ThreeXuiClientInterface
    {
        if ($this->clientFactory !== null) {
            $client = ($this->clientFactory)($server, $inbound, $node);
            if (!$client instanceof ThreeXuiClientInterface) {
                throw new \LogicException('Invalid ThreeXuiClient factory result.');
            }

            return $client;
        }

        return new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
    }

    private function repository(): SubscriptionRepository
    {
        return $this->repository ?? new SubscriptionRepository();
    }

    private function currentAdminId(): int
    {
        $user = get_user();

        return is_array($user) ? (int)($user['id'] ?? 0) : 0;
    }

    private function nullableDimension(mixed $value): ?string
    {
        $value = strtolower(trim((string)$value));

        return $value !== '' ? mb_substr($value, 0, 40) : null;
    }

    private function safeError(\Throwable $exception): string
    {
        if ($exception instanceof VpnManagerV2Exception) {
            return mb_substr(trim($exception->getMessage()), 0, 1000);
        }

        return \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_provisioning_generic');
    }

    private function errorType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $position = strrpos($class, '\\');

        return mb_substr($position === false ? $class : substr($class, $position + 1), 0, 120);
    }
}
