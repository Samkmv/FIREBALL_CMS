<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\DTO\PlanUpdateResult;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\PlanReconciliationRepository;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Services\PlanManagerService;
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
            (new PlanManagerService())->create(request()->getData());
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_created'));
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

    public function preview(): void
    {
        Permissions::authorize(Permissions::RECONCILE);
        $id = (int)get_route_param('id');
        try {
            $preview = (new VpnPlanSubscriptionReconciler())->previewPlanReconciliation($id);
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
            $repository = new PlanReconciliationRepository();
            $count = $repository->eligibleSubscriptionCount($id);
            $service = new VpnPlanSubscriptionReconciler();
            $result = $count > 0
                ? $service->queuePlan($id, $this->adminId(), ['batch_size' => 20])
                : new \Fireball\VpnManagerV2\DTO\ReconcileResult($id, 0);
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
            'missingConnectionCount' => $plan ? $reconciliation->missingNodeCountForPlan((int)$plan['id']) : 0,
            'latestReconciliation' => $plan ? $reconciliation->latestOperation((int)$plan['id']) : null,
            'obsoleteConnectionCount' => $plan ? $reconciliation->obsoleteNodeCount((int)$plan['id']) : 0,
            'obsoleteSubscriptionCount' => $plan ? $reconciliation->obsoleteSubscriptionCount((int)$plan['id']) : 0,
            'obsoleteTargets' => $plan ? $reconciliation->obsoleteTargetsForPlan((int)$plan['id']) : [],
        ]));
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
