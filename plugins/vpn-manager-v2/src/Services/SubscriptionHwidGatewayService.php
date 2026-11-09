<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Support\ServerConfigurationSignature;

/**
 * FIREBALL_VPN_HWID_GATEWAY_V1
 *
 * FIREBALL keeps building the final subscription itself, but delegates device
 * registration and limitHwid enforcement to the native 3x-ui subscription
 * server before any active configuration is returned.
 */
final class SubscriptionHwidGatewayService
{
    private const CONFIRMATION_TTL = 86400;

    public function __construct(
        private readonly ?\Closure $clientFactory = null,
    ) {
    }

    /**
     * @return array{allowed: bool, status: int, headers: array<string,string>, verified_targets?: list<string>}
     */
    public function enforce(array $subscription, array $nodes, array $requestHeaders): array
    {
        $deviceLimit = max(0, (int)($subscription['device_limit'] ?? 0));
        if ($deviceLimit <= 0) {
            return [
                'allowed' => true,
                'status' => 200,
                'headers' => ['X-Fireball-HWID-Gate' => 'inactive'],
            ];
        }

        $hwid = $this->header($requestHeaders, 'X-HWID');
        if (strlen($hwid) < 6) {
            return [
                'allowed' => false,
                'status' => 404,
                'headers' => [
                    'X-Hwid-Active' => 'true',
                    'X-Hwid-Not-Supported' => 'true',
                    'X-Fireball-HWID-Gate' => 'not-supported',
                    'Cache-Control' => 'private, no-store, must-revalidate',
                ],
            ];
        }

        $targets = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $subId = trim((string)($node['client_sub_id'] ?? ''));
            $serverId = (int)($node['server_id'] ?? 0);
            if ($serverId <= 0 || $subId === '') {
                continue;
            }

            $targets[$serverId . ':' . $subId] = [
                'server_id' => $serverId,
                'sub_id' => $subId,
            ];
        }

        if ($targets === []) {
            return $this->result(false, 503, 'unavailable');
        }

        $servers = $clients = $offline = [];
        $verified = [];
        $degraded = $unavailable = false;
        foreach ($targets as $targetKey => $target) {
            $serverId = $target['server_id'];
            $subId = $target['sub_id'];
            $server = $servers[$serverId] ??= (new ServerRepository())->findWithSecrets($serverId);
            if (!$server) {
                $unavailable = true;
                continue;
            }

            $scope = $this->deviceScope($serverId, $subId);
            $key = 'vpn-v2:hwid:confirmed:' . hash('sha256', serialize([
                $scope, cache()->get($scope), ServerConfigurationSignature::hash($server),
                (int)$subscription['id'], (string)($subscription['subscription_token'] ?? ''), $deviceLimit, $hwid,
            ]));
            $status = 503;
            try {
                if (!isset($offline[$serverId])) {
                    $client = $clients[$serverId] ??= $this->clientFactory !== null
                        ? ($this->clientFactory)($server)
                        : new ThreeXuiClient((new ServerSecretService())->clientConfig($server));
                    $status = (int)$client->probeSubscriptionHwid($subId, $requestHeaders);
                }
            } catch (ThreeXuiTransportException) {
                $offline[$serverId] = true;
            } catch (\Throwable $exception) {
                error_log('VPN Manager HWID gateway: ' . $exception::class);
                $unavailable = true;
                continue;
            }

            if ($status >= 200 && $status < 300) {
                cache()->set($key, true, self::CONFIRMATION_TTL);
                $verified[] = $targetKey;
                continue;
            }
            if ($status === 404) {
                cache()->set($key, false, self::CONFIRMATION_TTL);
                return $this->result(false, 404, 'denied');
            }
            if (($status >= 500 || $status === 429) && cache()->get($key) === true) {
                $verified[] = $targetKey;
                $degraded = true;
                continue;
            }
            $unavailable = true;
        }

        if ($unavailable) {
            return $verified === [] ? $this->result(false, 503, 'unavailable')
                : $this->result(true, 200, 'partial') + ['verified_targets' => $verified];
        }

        return $this->result(true, 200, $degraded ? 'previously-confirmed' : 'allowed');
    }

    public function invalidateDevices(int $serverId, string $subId): void
    {
        if ($subId !== '') {
            cache()->set($this->deviceScope($serverId, $subId), bin2hex(random_bytes(16)), 2 * self::CONFIRMATION_TTL);
        }
    }

    private function deviceScope(int $serverId, string $subId): string
    {
        return 'vpn-v2:hwid:scope:' . hash('sha256', $serverId . ':' . $subId);
    }

    private function header(array $headers, string $name): string
    {
        foreach ($headers as $candidate => $value) {
            if (strcasecmp((string)$candidate, $name) === 0) {
                $clean = preg_replace('/[\r\n]+/', ' ', trim((string)$value)) ?? '';
                return mb_substr($clean, 0, 512);
            }
        }

        return '';
    }

    /**
     * @return array{allowed: bool, status: int, headers: array<string,string>}
     */
    private function result(bool $allowed, int $status, string $state): array
    {
        $headers = [
            'X-Hwid-Active' => 'true',
            'X-Fireball-HWID-Gate' => $state,
        ];

        if (!$allowed) {
            $headers['Cache-Control'] = 'private, no-store, must-revalidate';
        }

        return [
            'allowed' => $allowed,
            'status' => $status,
            'headers' => $headers,
        ];
    }
}
