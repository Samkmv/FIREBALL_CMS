<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\DTO\PlanUpdateResult;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Services\PlanManagerService;
use Fireball\VpnManagerV2\Services\ExternalVpnSourceService;
use Fireball\VpnManagerV2\Services\VpnPlanSubscriptionReconciler;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Support\Permissions;

final class PlanController
{
    public function index(): string
    {
        Permissions::authorize(Permissions::VIEW);
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/plans', \FireballPluginVpnManagerV2::viewData('plans', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_plans_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_plans_subtitle'),
            'plans' => (new PlanRepository())->all(),
        ]));
    }

    public function create(): string
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        return $this->form(null);
    }

    public function store(): void
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        try {
            $id = (new PlanManagerService())->create(request()->getData());
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_created'));
            if (!empty(request()->post('manage_external'))) {
                $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id . '#external-sources');
            }
            $this->redirect('/admin/plugins/vpn-manager-v2/plans');
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            $this->redirect('/admin/plugins/vpn-manager-v2/plans/create');
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 plan create failed', [], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_save_generic'));
            $this->redirect('/admin/plugins/vpn-manager-v2/plans/create');
        }
    }

    public function edit(): string
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        $repository = new PlanRepository();
        $plan = $repository->find((int)get_route_param('id'));
        if (!$plan) {
            abort('', 404);
        }

        return $this->form($plan, $repository->nodes((int)$plan['id']));
    }

    public function update(): void
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        $id = (int)get_route_param('id');
        try {
            $result = (new PlanManagerService())->update($id, request()->getData());
            $this->flashUpdate($result);
            $this->redirect('/admin/plugins/vpn-manager-v2/plans');
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
            $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 plan update failed', ['Plan' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_save_generic'));
            $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
        }
    }

    public function toggle(): void
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        $id = (int)request()->post('id');
        try {
            $active = (new PlanManagerService())->toggle($id);
            session()->setFlash('success', \FireballPluginVpnManagerV2::t(
                $active ? 'vpn_manager_v2_flash_plan_enabled' : 'vpn_manager_v2_flash_plan_disabled'
            ));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 plan toggle failed', ['Plan' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_toggle_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/plans');
    }

    public function delete(): void
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        $id = (int)get_route_param('id');
        try {
            if (!(new PlanManagerService())->delete($id)) {
                throw new \RuntimeException('Plan was not archived.');
            }
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_deleted'));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 plan delete failed', ['Plan' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_delete_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/plans');
    }

    public function preview(): void
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        try {
            $preview = (new VpnPlanSubscriptionReconciler())->previewPlanReconciliation($id);
            // FIREBALL_VPN_HWID_RECONCILIATION_FINISH_V1: persist sanitized preview for the edit page.
            cache()->set('vpn-v2:plan-preview:' . $id, [
                'checked_at' => $preview->checkedAt,
                'subscriptions_checked' => $preview->subscriptionsChecked,
                'matching_subscriptions' => $preview->matchingSubscriptions,
                'missing_connections' => $preview->missingConnections,
                'obsolete_connections' => $preview->obsoleteConnections,
                'parameter_counts' => $preview->parameterCounts,
                'error_count' => count($preview->errors),
                // FIREBALL_VPN_RECONCILIATION_UI_V141
                'has_differences' => $preview->hasDifferences(),
                'unavailable_server_count' => count($preview->unavailableServers),
                'disabled_inbound_count' => count($preview->disabledInbounds),
                'conflict_count' => count($preview->conflicts),
            ], 600);
            $message = sprintf(
                \FireballPluginVpnManagerV2::t($preview->hasDifferences()
                    ? 'vpn_manager_v2_flash_reconcile_preview'
                    : 'vpn_manager_v2_flash_reconcile_no_changes'),
                $preview->subscriptionsChecked,
                $preview->missingConnections,
                $preview->obsoleteConnections,
                $preview->matchingSubscriptions,
                count($preview->unavailableServers),
                count($preview->disabledInbounds),
                count($preview->conflicts)
            );
            if ($preview->unavailableServers !== []) {
                $message .= ' ' . \FireballPluginVpnManagerV2::t('vpn_manager_v2_unavailable_servers')
                    . ': ' . implode(', ', array_values($preview->unavailableServers)) . '.';
            }
            if ($preview->disabledInbounds !== []) {
                $message .= ' ' . \FireballPluginVpnManagerV2::t('vpn_manager_v2_disabled_inbounds')
                    . ': ' . implode(', ', array_values($preview->disabledInbounds)) . '.';
            }
            if ($preview->conflicts !== []) {
                $items = array_map(static fn(array $conflict): string => sprintf(
                    '#%d / #%d / #%d',
                    (int)$conflict['subscription_id'],
                    (int)$conflict['server_id'],
                    (int)$conflict['inbound_id']
                ), $preview->conflicts);
                $message .= ' ' . \FireballPluginVpnManagerV2::t('vpn_manager_v2_conflicting_targets')
                    . ': ' . implode(', ', $items) . '.';
            }
            session()->setFlash($preview->hasDifferences() ? 'warning' : 'info', $message);
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 reconciliation preview failed', ['Plan' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
    }

    public function reconcile(): void
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        try {
            // FIREBALL_VPN_RECONCILIATION_UI_V141
            // Это ограничение относится только к ручной кнопке.
            // Автосинхронизация после сохранения тарифа работает как раньше.
            $previewState = cache()->get('vpn-v2:plan-preview:' . $id, null);

            if (!is_array($previewState)) {
                session()->setFlash(
                    'warning',
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_reconcile_check_first')
                );
                $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
            }

            if (empty($previewState['has_differences'])) {
                session()->setFlash(
                    'info',
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_reconcile_all_matches')
                );
                $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
            }

            $repository = new PlanReconciliationRepository();
            $count = $repository->eligibleSubscriptionCount($id);
            $service = new VpnPlanSubscriptionReconciler();
            $result = $count > 0
                ? $service->queuePlan($id, $this->adminId(), ['batch_size' => 20])
                : new \Fireball\VpnManagerV2\DTO\ReconcileResult($id, 0);
            cache()->remove('vpn-v2:plan-preview:' . $id);
            if ($result->queued) {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_reconcile_queued'));
            } elseif (!$result->successful()) {
                session()->setFlash('warning', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_reconcile_partial'));
            } elseif ($result->noChanges()) {
                session()->setFlash('info', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_reconcile_already_matches'));
            } else {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_reconcile_success'));
            }
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 reconciliation failed', ['Plan' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_reconcile_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
    }

    public function removeObsolete(): void
    {
        Permissions::authorize(Permissions::DELETE_CONNECTIONS);
        $id = (int)get_route_param('id');
        try {
            $repository = new PlanReconciliationRepository();
            $count = $repository->obsoleteSubscriptionCount($id);
            $service = new VpnPlanSubscriptionReconciler();
            $result = $count > VpnPlanSubscriptionReconciler::SYNC_THRESHOLD
                ? $service->queueObsoleteRemoval($id, $this->adminId(), ['batch_size' => 20])
                : $service->removeObsoleteNodes($id, $this->adminId());
            if ($result->queued) {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_remove_obsolete_queued'));
            } elseif ($result->failed > 0) {
                session()->setFlash('warning', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_remove_obsolete_partial'));
            } elseif ($result->removed === 0) {
                session()->setFlash('info', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_remove_obsolete_none'));
            } else {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_remove_obsolete_success'));
            }
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 obsolete connection removal failed', ['Plan' => $id], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_delete_generic'));
        }

        $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id);
    }

    private function form(?array $plan, array $selectedNodes = []): string
    {
        $repository = new PlanRepository();
        $resolver = new VpnFlowResolver();
        $inbounds = $repository->inboundsForForm();
        foreach ($inbounds as &$inbound) {
            $inbound['allowed_flows'] = array_values(array_filter(
                $resolver->allowedFlows($inbound),
                static fn(?string $flow): bool => $flow !== null
            ));
        }
        unset($inbound);

        $reconciliation = new PlanReconciliationRepository();

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/plan-form', \FireballPluginVpnManagerV2::viewData('plans', [
            'title' => \FireballPluginVpnManagerV2::t($plan ? 'vpn_manager_v2_plan_edit_title' : 'vpn_manager_v2_plan_create_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_form_subtitle'),
            'plan' => $plan,
            'selectedNodes' => $selectedNodes,
            'servers' => $repository->serversForForm(),
            'inbounds' => $inbounds,
            'affectedSubscriptions' => $plan ? $reconciliation->eligibleSubscriptionCount((int)$plan['id']) : 0,
            'reconciliationPreview' => $plan ? cache()->get('vpn-v2:plan-preview:' . (int)$plan['id'], null) : null,
            'missingConnectionCount' => $plan ? $reconciliation->missingNodeCountForPlan((int)$plan['id']) : 0,
            'latestReconciliation' => $plan ? $reconciliation->latestOperation((int)$plan['id']) : null,
            'obsoleteConnectionCount' => $plan ? $reconciliation->obsoleteNodeCount((int)$plan['id']) : 0,
            'obsoleteSubscriptionCount' => $plan ? $reconciliation->obsoleteSubscriptionCount((int)$plan['id']) : 0,
            'obsoleteTargets' => $plan ? $reconciliation->obsoleteTargetsForPlan((int)$plan['id']) : [],
            'externalSources' => $plan ? (new ExternalVpnSourceService(forPlan: true))->itemsForParent((int)$plan['id']) : [],
        ]));
    }

    public function attachExternalSubscription(): void { $this->externalAction('subscription', 'vpn_manager_v2_flash_external_attached'); }
    public function attachExternalConnection(): void { $this->externalAction('connection', 'vpn_manager_v2_flash_external_attached'); }
    public function toggleExternalSource(): void { $this->externalAction('toggle', 'vpn_manager_v2_flash_external_updated'); }
    public function detachExternalSource(): void { $this->externalAction('detach', 'vpn_manager_v2_flash_external_detached'); }
    public function syncExternalSource(): void { $this->externalAction('sync', 'vpn_manager_v2_flash_external_synced'); }
    public function updateExternalSourceOrder(): void { $this->externalAction('order', 'vpn_manager_v2_flash_external_order_saved'); }

    private function externalAction(string $action, string $successKey): void
    {
        Permissions::authorize(Permissions::MANAGE_PLANS);
        $id = (int)get_route_param('id');
        $sourceId = (int)get_route_param('source');
        $data = request()->getData();
        try {
            if (!(new PlanRepository())->find($id)) {
                throw new \Fireball\VpnManagerV2\Exceptions\ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
            }
            $service = new ExternalVpnSourceService(forPlan: true);
            $result = match ($action) {
                'subscription' => $service->attachSubscription($id, (string)($data['source_url'] ?? ''), (string)($data['name'] ?? ''), $this->adminId()),
                'connection' => $service->attachConnection($id, (string)($data['connection_uri'] ?? ''), (string)($data['name'] ?? ''), $this->adminId()),
                'toggle' => $service->toggle($id, $sourceId, !empty($data['is_enabled']), $this->adminId()),
                'detach' => $service->detach($id, $sourceId, $this->adminId()),
                'sync' => $service->sync($id, $sourceId, $this->adminId()),
                'order' => $service->reorder($id, is_array($data['external_source_order'] ?? null) ? $data['external_source_order'] : [], $this->adminId()),
            };
            if (in_array($action, ['toggle', 'detach'], true) && !$result) {
                throw new \InvalidArgumentException('external_source_missing');
            }
            session()->setFlash($action === 'order' && !$result ? 'info' : 'success', \FireballPluginVpnManagerV2::t(
                $action === 'order' && !$result ? 'vpn_manager_v2_flash_no_changes' : $successKey
            ));
        } catch (VpnManagerV2Exception $exception) {
            session()->setFlash('error', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            session()->setFlash('error', \FireballPluginVpnManagerV2::t(match ($exception->getMessage()) {
                'external_source_duplicate' => 'vpn_manager_v2_error_external_duplicate',
                'external_source_order_invalid' => 'vpn_manager_v2_error_external_order_invalid',
                default => 'vpn_manager_v2_error_external_missing',
            }));
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager V2 plan external source action failed', ['Plan' => $id, 'Error Class' => get_class($exception)], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_generic'));
        }
        $this->redirect('/admin/plugins/vpn-manager-v2/plans/edit/' . $id . '#external-sources');
    }

    private function flashUpdate(PlanUpdateResult $result): void
    {
        $reconciliation = $result->reconciliation;
        if ($reconciliation?->queued) {
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_saved_reconcile_queued'));
        } elseif ($reconciliation !== null && !$reconciliation->successful()) {
            session()->setFlash('warning', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_saved_reconcile_partial'));
        } elseif ($reconciliation !== null && $reconciliation->changed()) {
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_saved_server_added'));
        } else {
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_updated'));
        }
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
