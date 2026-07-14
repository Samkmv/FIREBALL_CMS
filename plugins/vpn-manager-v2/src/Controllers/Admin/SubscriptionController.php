<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\DTO\ProvisioningResult;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;
use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionUrlService;

final class SubscriptionController
{
    public function index(): string
    {
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscriptions', \FireballPluginVpnManagerV2::viewData('subscriptions', [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_title'),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscriptions_subtitle'),
            'subscriptions' => (new SubscriptionRepository())->all(),
        ]));
    }

    public function create(): string
    {
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

    public function show(): string
    {
        $repository = new SubscriptionRepository();
        $subscription = $repository->find((int)get_route_param('id'));
        if (!$subscription) {
            abort('', 404);
        }
        $subscriptionUrl = '';
        $subscriptionQr = '';
        $expiresAt = strtotime((string)($subscription['expires_at'] ?? ''));
        $startsAt = strtotime((string)($subscription['starts_at'] ?? ''));
        if ((string)$subscription['status'] === 'active'
            && ($startsAt === false || $startsAt <= time())
            && ($expiresAt === false || $expiresAt > time())) {
            $token = (new SubscriptionConfigRepository())->tokenForSubscription((int)$subscription['id']);
            if ($token !== null) {
                $subscriptionUrl = (new VpnSubscriptionUrlService())->forToken($token);
                $subscriptionQr = (new QrCodeService())->renderForToken($token);
            }
        }

        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/subscription-show', \FireballPluginVpnManagerV2::viewData('subscriptions', [
            'title' => sprintf(\FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_show_title'), (int)$subscription['id']),
            'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_show_subtitle'),
            'subscription' => $subscription,
            'nodes' => $repository->nodesForSubscription((int)$subscription['id']),
            'subscriptionUrl' => $subscriptionUrl,
            'subscriptionQr' => $subscriptionQr,
        ]));
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
