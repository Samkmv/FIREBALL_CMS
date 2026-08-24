<?php

namespace Fireball\VpnManagerV2\Controllers\Public;

use Fireball\VpnManagerV2\Services\ProfileVpnService;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\VpnAccessRequestService;
use Fireball\VpnManagerV2\Support\ProfileVpnInstructions;

final class ProfileVpnController
{
    public function index(): string
    {
        return $this->render();
    }

    public function show(): string
    {
        return $this->render((int)get_route_param('id'));
    }

    public function requestAccess(): void
    {
        $user = get_user();
        $userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        if ($userId <= 0) {
            abort('', 403);
        }
        if (empty((new SettingsService())->current()['public_account_enabled'])) {
            abort('', 404);
        }

        try {
            $result = (new VpnAccessRequestService())->requestForUser($userId);
            if (!empty($result['active_subscription'])) {
                session()->setFlash('info', \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_active'));
            } elseif (!empty($result['created'])) {
                session()->setFlash('success', \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_sent'));
            } else {
                session()->setFlash('info', \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_already_pending'));
            }
        } catch (\Throwable $exception) {
            log_error_details('VPN access request failed', ['User' => $userId], $exception);
            session()->setFlash('error', \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_error'));
        }

        response()->redirect(base_href('/profile/vpn-v2'));
    }

    public function instructions(): string
    {
        $platform = trim((string)get_route_param('platform'));
        if (!ProfileVpnInstructions::supports($platform)) {
            abort('', 404);
        }

        return $this->render(null, $platform);
    }

    private function render(?int $selectedId = null, ?string $platform = null): string
    {
        $user = get_user();
        $userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        if ($userId <= 0) {
            abort('', 403);
        }
        if (empty((new SettingsService())->current()['public_account_enabled'])) {
            abort('', 404);
        }
        $data = (new ProfileVpnService())->dashboard($userId, $selectedId, $platform);
        if ($selectedId !== null && !$data['requestedSubscriptionFound']) {
            abort('', 404);
        }

        return plugin_view(
            \FireballPluginVpnManagerV2::SLUG,
            'public/my-vpn',
            \FireballPluginVpnManagerV2::publicViewData(array_merge($data, [
                'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_title'),
                'subtitle' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_profile_subtitle'),
            ]))
        );
    }
}
