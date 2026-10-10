<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\Repositories\ServerHealthRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;

/** Infrastructure diagnostics only. Never changes remote clients or subscription membership. */
final class ServerHealthMonitorService
{
    public function __construct(private readonly ?\Closure $panelProbe = null, private readonly ?\Closure $portProbe = null) {}

    public function runDue(array $settings): array
    {
        if (empty($settings['smart_connect_health_enabled'])) {
            return ['checked' => 0, 'disabled' => true];
        }
        // One bounded server per job invocation; serialize workers without waiting.
        $lock = 'vpn-v2:global-health';
        if ((int)db()->query('SELECT GET_LOCK(?, 0)', [$lock])->getColumn() !== 1) {
            return ['checked' => 0, 'busy' => true];
        }
        try {
            $repository = new ServerHealthRepository();
            $due = $repository->nextDue();
            if (!$due) {
                return ['checked' => 0];
            }
            $server = (new ServerRepository())->findWithSecrets((int)$due['id']);
            if (!$server) {
                return ['checked' => 0];
            }
            $snapshot = $this->inspect($server, $repository->inbounds((int)$due['id']));
            $failures = $snapshot['state'] === 'healthy' ? 0 : min(20, (int)$due['consecutive_failures'] + 1);
            $base = max(60, min(86400, (int)($settings['server_check_interval_minutes'] ?? 10) * 60));
            $repository->save((int)$due['id'], $snapshot, $failures, $this->retryDelay($base, $failures));
            return ['checked' => 1, 'server_id' => (int)$due['id'], 'state' => $snapshot['state']];
        } finally {
            db()->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    public function inspect(array $server, array $inbounds): array
    {
        $snapshot = ['state' => 'unknown', 'scope' => 'infrastructure', 'panel' => 'unavailable',
            'xray' => 'unknown', 'ports' => [], 'load' => [], 'error' => '', 'checked_at' => date('Y-m-d H:i:s')];
        try {
            $raw = $this->panelProbe !== null ? ($this->panelProbe)($server)
                : (new ThreeXuiClient((new ServerSecretService())->clientConfig($server, 2, 4)))->serverStatus();
            $metrics = (new ServerMetricsService())->normalize((array)$raw);
            $snapshot['panel'] = 'online';
            $snapshot['xray'] = in_array(strtolower($metrics['xray']['state']), ['running', 'stopped', 'error'], true)
                ? strtolower($metrics['xray']['state']) : 'unknown';
            $snapshot['load'] = array_intersect_key($metrics, array_flip(['cpu', 'memory', 'load', 'network']));
        } catch (\Throwable) {
            // Exception messages may contain remote paths or credentials; save a fixed code only.
            $snapshot['error'] = 'panel_probe_failed';
        }
        $host = trim((string)parse_url((string)($server['panel_url'] ?? ''), PHP_URL_HOST), '[]');
        foreach (array_slice($inbounds, 0, 8) as $inbound) {
            $network = strtolower((string)($inbound['network'] ?? 'tcp'));
            $port = (int)($inbound['port'] ?? 0);
            $state = 'unknown';
            // UDP and transport handshakes cannot be established by a TCP socket test.
            if ($port > 0 && $port <= 65535 && in_array($network, ['tcp', 'raw', 'ws', 'grpc', 'httpupgrade', 'xhttp', 'splithttp'], true)) {
                try {
                    $open = $this->portProbe !== null ? ($this->portProbe)($host, $port, $server)
                        : $this->tcp($host, $port, !empty($server['allow_private_network']));
                    $state = $open ? 'open' : 'closed';
                } catch (\Throwable) {
                    $state = 'unknown';
                }
            }
            $snapshot['ports'][] = ['inbound_id' => (int)($inbound['id'] ?? 0), 'port' => $port,
                'transport' => $network, 'tcp' => $state, 'tunnel' => 'unverified'];
        }
        $states = array_column($snapshot['ports'], 'tcp');
        $snapshot['state'] = $snapshot['panel'] !== 'online' ? 'unavailable'
            : ($snapshot['xray'] === 'running' && $states !== [] && count(array_filter($states, static fn(string $s): bool => $s !== 'open')) === 0
                ? 'healthy' : 'degraded');
        return $snapshot;
    }

    public function retryDelay(int $baseSeconds, int $failures): int
    {
        $baseSeconds = max(60, min(86400, $baseSeconds));
        return (int)min(max(3600, $baseSeconds), $baseSeconds * (2 ** min(6, max(0, $failures - 1))));
    }

    private function tcp(string $host, int $port, bool $allowPrivate): bool
    {
        $addresses = (new NetworkTargetGuard())->validatedRequestAddresses('https://' . (str_contains($host, ':') ? '[' . $host . ']' : $host), $allowPrivate);
        // Pin the validated IP; a second DNS lookup cannot bypass the guard.
        $ip = $addresses[0];
        $target = 'tcp://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . ':' . $port;
        $socket = @stream_socket_client($target, $errno, $error, 1, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            return false;
        }
        fclose($socket);
        return true;
    }
}
