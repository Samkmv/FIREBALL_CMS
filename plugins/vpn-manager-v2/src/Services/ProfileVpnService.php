<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Repositories\ProfileVpnRepository;
use Fireball\VpnManagerV2\Support\ProfileVpnFormatter;
use Fireball\VpnManagerV2\Support\ProfileVpnInstructions;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

final class ProfileVpnService
{
    public function __construct(
        private readonly ?ProfileVpnRepository $repository = null,
        private readonly ?VpnSubscriptionUrlService $urls = null,
        private readonly ?QrCodeService $qrCode = null,
        private readonly ?SettingsService $settings = null,
        private readonly ?CountryFlagService $flags = null,
        private readonly ?VpnV2SubscriptionDependencyService $dependencies = null,
    ) {
    }

    public function hasSubscriptions(int $userId): bool
    {
        return ($this->repository ?? new ProfileVpnRepository())->hasSubscriptionsForUser($userId);
    }

    public function dashboard(int $userId, ?int $selectedId = null, ?string $platform = null): array
    {
        $repository = $this->repository ?? new ProfileVpnRepository();
        $settings = ($this->settings ?? new SettingsService())->current();
        $flags = $this->flags ?? new CountryFlagService();
        $subscriptions = array_map([$this, 'presentSubscription'], $repository->subscriptionsForUser($userId));
        $selected = null;
        $requestedFound = true;
        if ($selectedId !== null) {
            $selected = $repository->subscriptionForUser($selectedId, $userId);
            $requestedFound = $selected !== null && (int)$selected['user_id'] === $userId;
        } elseif ($subscriptions !== []) {
            foreach ($subscriptions as $subscription) {
                if (in_array((string)$subscription['effective_status'], ['active', 'partial_sync', 'sync_error'], true)) {
                    $selected = $subscription;
                    break;
                }
            }
            $selected ??= $subscriptions[0];
        }
        if ($selected !== null && !isset($selected['effective_status'])) {
            $selected = $this->presentSubscription($selected);
        }

        $servers = [];
        $subscriptionUrl = '';
        $subscriptionQr = '';
        $linkReady = false;
        $localUrl = false;
        if ($selected !== null) {
            $dependencyService = $this->dependencies ?? new VpnV2SubscriptionDependencyService();
            $externalSources = new ExternalVpnSourceService();
            $effectiveNodes = $dependencyService->isDependentChild((int)$selected['id'])
                ? []
                : $dependencyService->collectEffectiveConnections((int)$selected['id']);
            $serverRows = [];
            foreach ($effectiveNodes as $node) {
                $serverId = (int)($node['server_id'] ?? 0);
                if ($serverId > 0 && !isset($serverRows[$serverId])) {
                    $serverRows[$serverId] = [
                        'name' => (string)($node['server_name'] ?? ''),
                        'country_code' => (string)($node['country_code'] ?? ''),
                        'country_name' => (string)($node['country_name'] ?? ''),
                        'city' => (string)($node['city'] ?? ''),
                        'show_flag' => (int)($node['show_flag'] ?? 0),
                        'status' => 'active',
                    ];
                }
            }
            $servers = array_map(static function (array $server) use ($settings, $flags): array {
                $showFlag = (int)($server['show_flag'] ?? 0) === 1;
                $country = trim((string)($server['country_name'] ?? ''));
                $city = trim((string)($server['city'] ?? ''));

                return [
                    'name' => (string)($server['name'] ?? ''),
                    'country' => $country,
                    'city' => $city,
                    'flag' => $flags->forServer(
                        (string)($server['country_code'] ?? ''),
                        $showFlag,
                        !empty($settings['global_show_flags'])
                    ),
                    'status' => (string)($server['status'] ?? 'active'),
                ];
            }, array_values($serverRows));
            $selected['connection_count'] = count($effectiveNodes)
                + $externalSources->configCountForSubscriptions(
                    $dependencyService->collectEffectiveSubscriptionIds((int)$selected['id'])
                );
            $linkReady = in_array((string)$selected['effective_status'], ['active', 'partial_sync', 'sync_error'], true)
                && !$dependencyService->isDependentChild((int)$selected['id'])
                && (int)$selected['connection_count'] > 0;
            if ($linkReady) {
                $token = $repository->tokenForUserSubscription((int)$selected['id'], $userId);
                if ($token !== null) {
                    try {
                        $subscriptionUrl = ($this->urls ?? new VpnSubscriptionUrlService())->forToken($token);
                        $localUrl = $this->isLocalOnlyUrl($subscriptionUrl);
                    } catch (\Throwable $exception) {
                        error_log('VPN Manager V2 profile URL generation failed: ' . get_class($exception));
                        $subscriptionUrl = '';
                        $linkReady = false;
                    }
                    if ($subscriptionUrl !== '' && !empty($settings['show_qr_in_profile'])) {
                        try {
                            $subscriptionQr = ($this->qrCode ?? new QrCodeService())->renderForToken($token);
                        } catch (\Throwable $exception) {
                            error_log('VPN Manager V2 profile QR generation failed: ' . get_class($exception));
                            $subscriptionQr = '';
                        }
                    }
                } else {
                    $linkReady = false;
                }
            }
        }

        return [
            'subscriptions' => $subscriptions,
            'selectedSubscription' => $selected,
            'requestedSubscriptionFound' => $requestedFound,
            'servers' => $servers,
            'subscriptionUrl' => $subscriptionUrl,
            'subscriptionQr' => $subscriptionQr,
            'linkReady' => $linkReady && $subscriptionUrl !== '',
            'localSubscriptionUrl' => $localUrl,
            'serviceName' => (string)$settings['service_name'],
            'logo' => (string)$settings['logo'],
            'supportName' => (string)$settings['support_name'],
            'supportUrl' => (string)$settings['support_url'],
            'showQrInProfile' => !empty($settings['show_qr_in_profile']),
            'instructions' => ProfileVpnInstructions::all($platform),
        ];
    }

    private function presentSubscription(array $subscription): array
    {
        $dependencies = $this->dependencies ?? new VpnV2SubscriptionDependencyService();
        try {
            $effective = $dependencies->calculateEffectiveStatus($subscription);
            if ($dependencies->isDependentChild((int)($subscription['id'] ?? 0))) {
                $subscription['effective_status'] = 'inactive';
                $subscription['inactive_reason'] = 'parent_subscription_required';
            } else {
                $subscription['effective_status'] = (string)$effective['effective_status'];
                $subscription['inactive_reason'] = $effective['inactive_reason'];
            }
        } catch (\Throwable) {
            $subscription['effective_status'] = ProfileVpnFormatter::effectiveStatus($subscription);
            $subscription['inactive_reason'] = null;
        }
        $subscription['starts_at_display'] = ProfileVpnFormatter::date($subscription['starts_at'] ?? null);
        $subscription['expires_at_display'] = ProfileVpnFormatter::date($subscription['expires_at'] ?? null);
        $subscription['remaining_display'] = ProfileVpnFormatter::remaining($subscription['expires_at'] ?? null);
        $subscription['traffic_used_display'] = ProfileVpnFormatter::bytes($subscription['traffic_used_bytes'] ?? 0);
        $subscription['traffic_limit_display'] = TrafficFormatter::limit(
            isset($subscription['traffic_limit_bytes']) ? (int)$subscription['traffic_limit_bytes'] : null
        );
        $limit = max(0, (int)($subscription['traffic_limit_bytes'] ?? 0));
        $used = max(0, (int)($subscription['traffic_used_bytes'] ?? 0));
        $subscription['traffic_used_display'] = TrafficFormatter::bytes($used);
        $subscription['traffic_limit_display'] = TrafficFormatter::localizedLimit($limit > 0 ? $limit : null);
        $subscription['traffic_usage_display'] = TrafficFormatter::usage($used, $limit > 0 ? $limit : null);
        $subscription['traffic_percent'] = $limit > 0 ? min(100, (int)round(($used / $limit) * 100)) : 0;

        return $subscription;
    }

    private function isLocalOnlyUrl(string $url): bool
    {
        $host = strtolower(trim((string)(parse_url($url, PHP_URL_HOST) ?? '')));

        return $host === 'localhost' || $host === '::1' || str_starts_with($host, '127.');
    }
}
