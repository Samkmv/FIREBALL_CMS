<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Services\PlanManagerService;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;

final class PlanController
{
    public function index(): string
    {
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/plans', \FireballPluginVpnManagerV2::viewData('plans', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_plans_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_plans_subtitle'),
            'plans' => (new PlanRepository())->all(),
        ]));
    }

    public function create(): string
    {
        return $this->form(null);
    }

    public function store(): void
    {
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
        $repository = new PlanRepository();
        $plan = $repository->find((int)get_route_param('id'));
        if (!$plan) {
            abort('', 404);
        }

        return $this->form($plan, $repository->nodes((int)$plan['id']));
    }

    public function update(): void
    {
        $id = (int)get_route_param('id');
        try {
            (new PlanManagerService())->update($id, request()->getData());
            session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_flash_plan_updated'));
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

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/plan-form', \FireballPluginVpnManagerV2::viewData('plans', [
            'title' => \FireballPluginVpnManagerV2::t($plan ? 'vpn_manager_v2_plan_edit_title' : 'vpn_manager_v2_plan_create_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_form_subtitle'),
            'plan' => $plan,
            'selectedNodes' => $selectedNodes,
            'servers' => $repository->serversForForm(),
            'inbounds' => $inbounds,
        ]));
    }

    private function redirect(string $path): never
    {
        response()->redirect(base_href($path));
    }
}
