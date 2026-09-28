<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Repositories\ServerRepository;

/**
 * FIREBALL_VPN_HWID_GATEWAY_V1
 *
 * FIREBALL keeps building the final subscription itself, but delegates device
 * registration and limitHwid enforcement to the native 3x-ui subscription
 * server before any active configuration is returned.
 */
final class SubscriptionHwidGatewayService
{
    public function __construct(
        private readonly ?\Closure $clientFactory = null,
    ) {
    }

    /**
     * @return array{allowed: bool, status: int, headers: array<string,string>}
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

        $clients = [];
        foreach ($targets as $target) {
            $serverId = (int)$target['server_id'];

            try {
                if (!isset($clients[$serverId])) {
                    $server = (new ServerRepository())->findWithSecrets($serverId);
                    if (!is_array($server)) {
                        return $this->result(false, 503, 'unavailable');
                    }

                    $client = $this->clientFactory !== null
                        ? ($this->clientFactory)($server)
                        : new ThreeXuiClient((new ServerSecretService())->clientConfig($server));

                    if (!is_object($client) || !method_exists($client, 'probeSubscriptionHwid')) {
                        return $this->result(false, 503, 'unavailable');
                    }

                    $clients[$serverId] = $client;
                }

                $status = (int)$clients[$serverId]->probeSubscriptionHwid(
                    (string)$target['sub_id'],
                    $requestHeaders
                );
            } catch (\Throwable $exception) {
                // Never log HWID, subscription token, subId, credentials or native URL.
                error_log('VPN Manager V2 HWID gateway: ' . get_class($exception));
                return $this->result(false, 503, 'unavailable');
            }

            if ($status >= 200 && $status < 300) {
                continue;
            }

            // Current 3x-ui returns 404 when the native HWID gate denies access.
            if ($status === 404) {
                return $this->result(false, 404, 'denied');
            }

            return $this->result(false, 503, 'unavailable');
        }

        return $this->result(true, 200, 'allowed');
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
