<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;

final class ServerMetricsService
{
    public function __construct(
        private readonly ?ServerRepository $repository = null,
        private readonly ?ServerSecretService $secrets = null,
        private readonly ?\Closure $clientFactory = null,
    ) {
    }

    public function fetch(int $serverId): array
    {
        $server = ($this->repository ?? new ServerRepository())->findWithSecrets($serverId);
        if (!$server) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
        }
        if (empty($server['is_enabled'])) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_metrics_disabled'));
        }

        $config = ($this->secrets ?? new ServerSecretService())->clientConfig($server, 3, 8);
        $client = $this->clientFactory !== null
            ? ($this->clientFactory)($server, $config)
            : new ThreeXuiClient($config);
        if (!is_object($client) || !method_exists($client, 'serverStatus')) {
            throw new \RuntimeException('VPN server metrics client is unavailable.');
        }

        return array_replace($this->normalize((array)$client->serverStatus()), [
            'server' => [
                'id' => (int)$server['id'],
                'name' => (string)$server['name'],
                'status' => (string)$server['status'],
            ],
            'checked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function normalize(array $raw): array
    {
        $loads = $this->loads($raw);

        return [
            'cpu' => [
                'percent' => $this->percent($raw['cpu'] ?? null),
                'cores' => $this->integer($raw['cpuCores'] ?? $raw['cpu_cores'] ?? null),
                'speed_mhz' => $this->integer($raw['cpuSpeedMhz'] ?? $raw['cpu_speed_mhz'] ?? null),
            ],
            'memory' => $this->usage($raw['mem'] ?? $raw['memory'] ?? []),
            'swap' => $this->usage($raw['swap'] ?? []),
            'disk' => $this->usage($raw['disk'] ?? []),
            'load' => $loads,
            'uptime_seconds' => $this->integer($raw['uptime'] ?? null),
            'network' => [
                'up' => $this->integer($raw['netIO']['up'] ?? $raw['netIo']['up'] ?? null),
                'down' => $this->integer($raw['netIO']['down'] ?? $raw['netIo']['down'] ?? null),
                'sent' => $this->integer($raw['netTraffic']['sent'] ?? null),
                'received' => $this->integer($raw['netTraffic']['recv'] ?? $raw['netTraffic']['received'] ?? null),
            ],
            'connections' => [
                'tcp' => $this->integer($raw['tcpCount'] ?? null),
                'udp' => $this->integer($raw['udpCount'] ?? null),
            ],
            'xray' => [
                'state' => mb_substr(trim((string)($raw['xray']['state'] ?? 'unknown')), 0, 40),
                'version' => mb_substr(trim((string)($raw['xray']['version'] ?? '')), 0, 80),
            ],
        ];
    }

    private function usage(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $current = $this->integer($value['current'] ?? $value['used'] ?? null);
        $total = $this->integer($value['total'] ?? null);

        return [
            'current' => $current,
            'total' => $total,
            'percent' => $current !== null && $total !== null && $total > 0
                ? round(min(100, ($current / $total) * 100), 1)
                : null,
        ];
    }

    private function loads(array $raw): array
    {
        $load = $raw['load'] ?? $raw['loads'] ?? [];
        if (!is_array($load)) {
            return ['one' => null, 'five' => null, 'fifteen' => null];
        }

        return [
            'one' => $this->decimal($load['load1'] ?? $load[0] ?? null),
            'five' => $this->decimal($load['load5'] ?? $load[1] ?? null),
            'fifteen' => $this->decimal($load['load15'] ?? $load[2] ?? null),
        ];
    }

    private function percent(mixed $value): ?float
    {
        $value = $this->decimal($value);

        return $value !== null ? round(max(0, min(100, $value)), 1) : null;
    }

    private function decimal(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float)$value) ? (float)$value : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) && (float)$value >= 0 ? (int)round((float)$value) : null;
    }
}
