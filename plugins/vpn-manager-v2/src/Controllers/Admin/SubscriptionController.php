<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\DTO\ProvisioningResult;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\SubscriptionEditingService;
use Fireball\VpnManagerV2\Services\SubscriptionDeletionService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionUrlService;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;
use Fireball\VpnManagerV2\Support\AdminTableState;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

final class SubscriptionController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscriptions', \FireballPluginVpnManagerV2::viewData('subscriptions', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_subtitle'),
            'subscriptions' => (new SubscriptionRepository())->all(),
            'returnQuery' => AdminTableState::capture(),
        ]));
    }

    public function create(): string
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $repository = new SubscriptionRepository();

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscription-form', \FireballPluginVpnManagerV2::viewData('subscriptions', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_create_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_create_subtitle'),
            'users' => $repository->usersForForm(),
            'plans' => $repository->activePlansForForm(),
            'defaultStartsAt' => date('Y-m-d\\TH:i'),
        ]));
    }

    public function store(): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        try {
            $result = (new SubscriptionProvisioningService())->create(request()->getData(), $this->adminId());
            $this->flashResult($result, true);
            $this->redirect('/admin/plugins/vpn-manager-v2/subscriptions/' . $result->subscriptionId);
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 subscription create failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_create_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/subscriptions/create');
    }

    public function edit(): string
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $subscription = (new SubscriptionRepository())->find((int)get_route_param('id'));
        if (!$subscription) {
            abort('', 404);
        }

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscription-edit', \FireballPluginVpnManagerV2::viewData('subscriptions', [
            'title' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_edit_title'), (int)$subscription['id']),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_edit_subtitle'),
            'subscription' => $subscription,
            'trafficInput' => TrafficFormatter::inputParts(
                isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : null
            ),
            'returnQuery' => AdminTableState::sanitize(request()->get('return_query', '')),
        ]));
    }

    public function update(): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $subscriptionId = (int)get_route_param('id');
        $returnQuery = AdminTableState::sanitize(request()->post('return_query', ''));
        try {
            $result = (new SubscriptionEditingService())->update($subscriptionId, request()->getData(), $this->adminId());
            if ($result->failed > 0) {
                session()->setFlash('warning', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_sync_partial'));
            } elseif (!$result->remoteRequest && $result->changed) {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_comment_saved'));
            } elseif ($result->changed) {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_updated'));
            } else {
                session()->setFlash('info', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_no_changes'));
            }
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            response()->redirect(AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/edit/' . $subscriptionId, $returnQuery));
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 subscription update failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic'));
            response()->redirect(AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/edit/' . $subscriptionId, $returnQuery));
        }

        response()->redirect(AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId, $returnQuery));
    }

    public function show(): string
    {
        Permissions::authorize(Permissions::VIEW);
        $repository = new SubscriptionRepository();
        $subscription = $repository->find((int)get_route_param('id'));
        if (!$subscription) {
            abort('', 404);
        }
        $subscriptionUrl = '';
        $subscriptionQr = '';
        $expiresAt = strtotime((string)($subscription['expires_at'] ?? ''));
        $startsAt = strtotime((string)($subscription['starts_at'] ?? ''));
        if (in_array((string)$subscription['status'], ['active', 'partial_sync', 'sync_error'], true)
            && ($startsAt === false || $startsAt <= time())
            && ($expiresAt === false || $expiresAt > time())) {
            $token = (new SubscriptionConfigRepository())->tokenForSubscription((int)$subscription['id']);
            if ($token !== null) {
                $subscriptionUrl = (new VpnSubscriptionUrlService())->forToken($token);
                $subscriptionQr = (new QrCodeService())->renderForToken($token);
            }
        }

        $reconciler = new VpnPlanSubscriptionReconciler();
        $planRepository = new PlanReconciliationRepository();
        $nodes = $repository->nodesForSubscription((int)$subscription['id']);
        $missing = $reconciler->findMissingNodes((int)$subscription['id']);
        $obsolete = $reconciler->findObsoleteNodes((int)$subscription['id']);
        $planCount = count($planRepository->activePlanNodes((int)$subscription['plan_id']));
        $createdCount = count(array_filter($nodes, static fn(array $node): bool =>
            in_array((string)$node['status'], ['active', 'disabled'], true)
        ));
        $missingCount = max(count($missing), max(0, $planCount - $createdCount));

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscription-show', \FireballPluginVpnManagerV2::viewData('subscriptions', [
            'title' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_show_title'), (int)$subscription['id']),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_show_subtitle'),
            'subscription' => $subscription,
            'nodes' => $nodes,
            'reconciliationSummary' => [
                'plan_count' => $planCount,
                'created_count' => $createdCount,
                'missing_count' => $missingCount,
                'obsolete_count' => count($obsolete),
                'matches' => $missingCount === 0 && $obsolete === [],
                'checked_at' => date('Y-m-d H:i:s'),
            ],
            'subscriptionUrl' => $subscriptionUrl,
            'subscriptionQr' => $subscriptionQr,
            'returnQuery' => AdminTableState::sanitize(request()->get('return_query', '')),
        ]));
    }

    public function suspend(): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $subscriptionId = (int)get_route_param('id');
        $returnQuery = AdminTableState::sanitize(request()->post('return_query', ''));
        try {
            $result = (new SubscriptionDeletionService())->suspend($subscriptionId, $this->adminId());
            if ($result->failed > 0) {
                session()->setFlash('warning', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_sync_partial'));
            } else {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_suspended'));
            }
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 subscription suspend failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_sync_generic'));
        }

        response()->redirect(AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId, $returnQuery));
    }

    public function delete(): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $subscriptionId = (int)get_route_param('id');
        $returnQuery = AdminTableState::sanitize(request()->post('return_query', ''));
        try {
            $result = (new SubscriptionDeletionService())->deleteForever($subscriptionId, $this->adminId());
            if ($result->successful()) {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_deleted'));
                response()->redirect(AdminTableState::append('/admin/plugins/vpn-manager-v2/subscriptions', $returnQuery));
            }
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_delete_failed'));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 subscription delete failed: ' . get_class($exception));
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_delete_failed'));
        }

        response()->redirect(AdminTableState::asParameter('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId, $returnQuery));
    }

    public function createMissing(): void
    {
        Permissions::authorize(Permissions::CREATE_CONNECTIONS);
        Permissions::authorize(Permissions::RECONCILE);
        $subscriptionId = (int)get_route_param('id');
        try {
            $result = (new VpnPlanSubscriptionReconciler())->reconcileSubscription(
                $subscriptionId,
                ['initiated_by' => $this->adminId()]
            );
            if (!$result->successful()) {
                session()->setFlash('warning', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_create_missing_partial'));
            } elseif ($result->noChanges()) {
                session()->setFlash('info', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_reconcile_already_matches'));
            } else {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_create_missing_success'));
            }
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 missing connection creation failed', ['Subscription' => $subscriptionId], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_create_missing'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId);
    }

    public function updateConnectionOrder(): void
    {
        Permissions::authorize(Permissions::MANAGE_SUBSCRIPTIONS);
        $subscriptionId = (int)get_route_param('id');
        $data = request()->getData();
        $returnQuery = AdminTableState::sanitize($data['return_query'] ?? '');
        $orderedNodeIds = is_array($data['connection_order'] ?? null)
            ? $data['connection_order']
            : [];

        try {
            $repository = new SubscriptionRepository();
            $before = (new SubscriptionConfigRepository())->revisionMetadata($subscriptionId);
            $changed = $repository->reorderNodes($subscriptionId, $orderedNodeIds, $this->adminId());
            if ($changed && is_array($before)) {
                (new \Fireball\VpnManagerV2\Services\VpnSubscriptionCache())->invalidate(
                    (string)$before['subscription_token'],
                    (int)$before['revision']
                );
            }
            session()->setFlash(
                $changed ? 'success' : 'info',
                \FireballPluginVpnManagerV2::t($changed
                    ? 'vpn_manager_v2_flash_connection_order_saved'
                    : 'vpn_manager_v2_flash_no_changes')
            );
        } catch (\InvalidArgumentException $exception) {
            $key = $exception->getMessage() === 'subscription_not_found'
                ? 'vpn_manager_v2_error_subscription_not_found'
                : 'vpn_manager_v2_error_connection_order_invalid';
            session()->setFlash('error', \FireballPluginVpnManagerV2::t($key));
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 connection order update failed', [
                'Subscription' => $subscriptionId,
                'User ID' => $this->adminId(),
                'Error Class' => get_class($exception),
            ], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_order_save'));
        }

        response()->redirect(AdminTableState::asParameter(
            '/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId,
            $returnQuery
        ));
    }

    private function flashResult(ProvisioningResult $result, bool $created): void
    {
        if ($result->flowError) {
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_flow_not_saved'));
            return;
        }
        if ($result->successful()) {
            session()->setFlash('success', sprintf(
                \FireballPluginVpnManagerV2::t($created
                    ? 'vpn_manager_v2_flash_subscription_created'
                    : 'vpn_manager_v2_flash_connection_retry_success'),
                $result->created,
                $result->reused
            ));
            return;
        }

        session()->setFlash('error', sprintf(
            \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_subscription_partial'),
            $result->created,
            $result->reused,
            $result->failed + $result->syncErrors
        ));
    }

    private function adminId(): int
    {
        $user = get_user();

        return is_array($user) ? (int)($user['id'] ?? 0) : 0;
    }

    private function redirect(string $path): never
    {
        response()->redirect(base_href($path));
    }
}
