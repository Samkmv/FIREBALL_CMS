<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class DiagnosticJobService
{
    public const CAPABILITY = 'diagnostics_v1';
    public const OPERATIONS = [
        'wg_peer',
        'route_check',
        'ping_check',
        'tcp_check',
        'port_discovery',
        'rtsp_probe',
        'hls_check',
    ];

    public const ERROR_CODES = [
        '',
        'network_unreachable',
        'timeout',
        'connection_refused',
        '401_unauthorized',
        '404_not_found',
        'invalid_rtsp',
        'unsupported_codec',
        'unknown',
    ];

    public function queueSiteCheck(int $siteId, ?int $userId = null): string
    {
        $site = \FireballPluginCameraManager::site($siteId);
        if ($site === null) {
            throw new RuntimeException('Объект не найден.');
        }
        if (\FireballPluginCameraManager::settings()['connection_mode'] !== 'pull') {
            throw new RuntimeException('Удалённая диагностика доступна только в режиме HTTPS pull.');
        }
        $inFlight = (int)db()->query(
            "SELECT COUNT(*) FROM camera_manager_diagnostic_jobs WHERE site_id = ? AND status IN ('pending', 'running')",
            [$siteId]
        )->getColumn();
        if ($inFlight > 0) {
            throw new RuntimeException('Для этого объекта уже выполняется диагностика. Дождитесь ответа RTSP-агента.');
        }

        $batchId = bin2hex(random_bytes(16));
        $jobs = [];
        if (trim((string)($site['wireguard_public_key'] ?? '')) !== '') {
            $jobs[] = ['wg_peer', ['label' => 'WireGuard peer']];
        }
        $jobs[] = ['route_check', ['label' => 'Маршрут до регистратора']];
        foreach ([
            'VPN IP роутера' => $site['vpn_ip'] ?? '',
            'LAN IP роутера' => $site['router_ip'] ?? '',
            'Регистратор' => $site['recorder_ip'] ?? '',
        ] as $label => $target) {
            if (trim((string)$target) !== '') {
                $jobs[] = ['ping_check', ['label' => $label, 'target' => (string)$target]];
            }
        }
        $jobs[] = ['tcp_check', ['label' => 'RTSP TCP', 'port_kind' => 'rtsp']];
        if (InputValidator::nullablePort($site['management_port'] ?? null, 'Порт управления') !== null) {
            $jobs[] = ['tcp_check', ['label' => 'Management TCP', 'port_kind' => 'management']];
        }
        $jobs[] = ['port_discovery', ['label' => 'Типовые порты']];
        $camera = db()->query(
            'SELECT channel_number, subtype, stream_key FROM camera_manager_cameras WHERE site_id = ? ORDER BY enabled DESC, channel_number ASC, id ASC LIMIT 1',
            [$siteId]
        )->getOne();
        $jobs[] = ['rtsp_probe', [
            'label' => 'RTSP probe',
            'channel' => is_array($camera) ? (int)$camera['channel_number'] : 1,
            'subtype' => is_array($camera) ? (int)$camera['subtype'] : 0,
        ]];
        if (is_array($camera) && trim((string)$camera['stream_key']) !== '') {
            $jobs[] = ['hls_check', ['label' => 'HLS и постер', 'stream_key' => (string)$camera['stream_key']]];
        }

        $this->insertJobs($siteId, $batchId, $jobs, $userId);

        return $batchId;
    }

    public function queueRtspProbe(int $siteId, int $channel, int $subtype, ?int $userId = null): string
    {
        if (\FireballPluginCameraManager::site($siteId) === null) {
            throw new RuntimeException('Объект не найден.');
        }
        if ($channel < 1 || $channel > 4096 || $subtype < 0 || $subtype > 99) {
            throw new RuntimeException('Некорректный канал для RTSP-проверки.');
        }
        $batchId = bin2hex(random_bytes(16));
        $this->insertJobs($siteId, $batchId, [[
            'rtsp_probe',
            ['label' => 'RTSP auto-detect', 'channel' => $channel, 'subtype' => $subtype],
        ]], $userId);

        return $batchId;
    }

    /** @return list<array<string,mixed>> */
    public function fetchForAgent(int $limit = 12): array
    {
        $limit = max(1, min(20, $limit));
        $cutoff = date('Y-m-d H:i:s', time() - 600);
        $rows = db()->query(
            "SELECT * FROM camera_manager_diagnostic_jobs
             WHERE status = 'pending' OR (status = 'running' AND dispatched_at < ?)
             ORDER BY id ASC LIMIT " . $limit,
            [$cutoff]
        )->get() ?: [];
        $jobs = [];
        foreach ($rows as $row) {
            $operation = (string)($row['operation'] ?? '');
            if (!in_array($operation, self::OPERATIONS, true)) {
                $this->failInvalidStoredJob((int)$row['id']);
                continue;
            }
            try {
                $jobs[] = $this->agentPayload($row);
                db()->query(
                    "UPDATE camera_manager_diagnostic_jobs
                     SET status = 'running', dispatched_at = ?, updated_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), (int)$row['id']]
                );
            } catch (RuntimeException $exception) {
                db()->query(
                    "UPDATE camera_manager_diagnostic_jobs
                     SET status = 'failed', error_code = 'unknown', message = ?, completed_at = ?, updated_at = ? WHERE id = ?",
                    [$this->safeLine($exception->getMessage(), 500), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), (int)$row['id']]
                );
            }
        }

        return $jobs;
    }

    public function acceptReport(array $payload): array
    {
        $jobId = $this->positiveInteger($payload['job_id'] ?? null, 'diagnostic job id');
        $job = db()->query('SELECT * FROM camera_manager_diagnostic_jobs WHERE id = ? LIMIT 1', [$jobId])->getOne();
        if (!is_array($job)) {
            throw new RuntimeException('Diagnostic job was not found.');
        }
        $status = strtolower(trim((string)($payload['status'] ?? '')));
        if (!in_array($status, ['success', 'warning', 'failed'], true)) {
            throw new RuntimeException('Invalid diagnostic status.');
        }
        $errorCode = strtolower(trim((string)($payload['error_code'] ?? '')));
        if (!in_array($errorCode, self::ERROR_CODES, true)) {
            $errorCode = 'unknown';
        }
        $operation = (string)$job['operation'];
        $result = $this->sanitizeResult($operation, is_array($payload['result'] ?? null) ? $payload['result'] : []);
        $message = $this->safeLine((string)($payload['message'] ?? ''), 500);
        $now = date('Y-m-d H:i:s');
        db()->query(
            'UPDATE camera_manager_diagnostic_jobs
             SET status = ?, result_json = ?, error_code = ?, message = ?, completed_at = ?, updated_at = ? WHERE id = ?',
            [
                $status,
                json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $errorCode !== '' ? $errorCode : null,
                $message !== '' ? $message : null,
                $now,
                $now,
                $jobId,
            ]
        );

        return ['accepted_job_id' => $jobId];
    }

    /** @return list<array<string,mixed>> */
    public function siteJobs(int $siteId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $rows = db()->query(
            'SELECT * FROM camera_manager_diagnostic_jobs WHERE site_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$siteId]
        )->get() ?: [];
        foreach ($rows as &$row) {
            $decoded = json_decode((string)($row['result_json'] ?? ''), true);
            $parameters = json_decode((string)($row['parameters_json'] ?? ''), true);
            $row['result'] = is_array($decoded) ? $decoded : [];
            $row['parameters'] = is_array($parameters) ? $parameters : [];
            unset($row['result_json'], $row['parameters_json']);
        }
        unset($row);

        return $rows;
    }

    public function latestSuccessfulRtspProbe(int $siteId): ?array
    {
        $row = db()->query(
            "SELECT * FROM camera_manager_diagnostic_jobs
             WHERE site_id = ? AND operation = 'rtsp_probe' AND status = 'success'
             ORDER BY id DESC LIMIT 1",
            [$siteId]
        )->getOne();
        if (!is_array($row)) {
            return null;
        }
        $result = json_decode((string)($row['result_json'] ?? ''), true);

        return is_array($result) ? array_merge($row, ['result' => $result]) : null;
    }

    private function insertJobs(int $siteId, string $batchId, array $jobs, ?int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($jobs as $sequence => $job) {
            [$operation, $parameters] = $job;
            if (!in_array($operation, self::OPERATIONS, true)) {
                throw new RuntimeException('Попытка создать неподдерживаемую диагностику.');
            }
            $safeParameters = $this->sanitizeStoredParameters($operation, $parameters);
            db()->query(
                'INSERT INTO camera_manager_diagnostic_jobs
                 (batch_id, sequence_number, site_id, operation, parameters_json, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $batchId,
                    $sequence + 1,
                    $siteId,
                    $operation,
                    json_encode($safeParameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'pending',
                    $userId,
                    $now,
                    $now,
                ]
            );
        }
    }

    private function sanitizeStoredParameters(string $operation, array $parameters): array
    {
        $safe = ['label' => $this->safeLine((string)($parameters['label'] ?? $operation), 100)];
        if ($operation === 'ping_check') {
            $safe['target'] = InputValidator::requiredIp($parameters['target'] ?? '', 'IP диагностики');
        } elseif ($operation === 'tcp_check') {
            $kind = (string)($parameters['port_kind'] ?? '');
            if (!in_array($kind, ['rtsp', 'management'], true)) {
                throw new RuntimeException('Некорректный тип TCP-порта.');
            }
            $safe['port_kind'] = $kind;
        } elseif ($operation === 'rtsp_probe') {
            $safe['channel'] = max(1, min(4096, (int)($parameters['channel'] ?? 1)));
            $safe['subtype'] = max(0, min(99, (int)($parameters['subtype'] ?? 0)));
        } elseif ($operation === 'hls_check') {
            $streamKey = trim((string)($parameters['stream_key'] ?? ''));
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $streamKey) !== 1) {
                throw new RuntimeException('Некорректный ключ HLS-потока.');
            }
            $safe['stream_key'] = $streamKey;
        }

        return $safe;
    }

    private function agentPayload(array $row): array
    {
        $site = \FireballPluginCameraManager::site((int)$row['site_id']);
        if ($site === null) {
            throw new RuntimeException('Diagnostic site no longer exists.');
        }
        $settings = \FireballPluginCameraManager::settings();
        $stored = json_decode((string)($row['parameters_json'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];
        $operation = (string)$row['operation'];
        $parameters = [];

        if ($operation === 'wg_peer') {
            $parameters = [
                'interface' => InputValidator::linuxInterface($settings['wireguard_interface'] ?? 'wg0', 'Интерфейс WireGuard'),
                'public_key' => InputValidator::wireGuardPublicKey($site['wireguard_public_key'] ?? '')
                    ?? throw new RuntimeException('PublicKey WireGuard объекта не заполнен.'),
            ];
        } elseif ($operation === 'route_check') {
            $parameters = [
                'target' => InputValidator::requiredIp($site['recorder_ip'] ?? '', 'IP регистратора'),
                'interface' => InputValidator::linuxInterface($settings['wireguard_interface'] ?? 'wg0', 'Интерфейс WireGuard'),
                'expected_source' => InputValidator::nullableIp($settings['wireguard_server_ip'] ?? '', 'IP WireGuard-сервера'),
            ];
        } elseif ($operation === 'ping_check') {
            $parameters = ['target' => InputValidator::requiredIp($stored['target'] ?? '', 'IP диагностики')];
        } elseif ($operation === 'tcp_check') {
            $kind = (string)($stored['port_kind'] ?? 'rtsp');
            $port = $kind === 'management'
                ? InputValidator::nullablePort($site['management_port'] ?? null, 'Порт управления')
                : InputValidator::requiredPort($site['rtsp_port'] ?? 554, 'RTSP-порт', 554);
            if ($port === null) {
                throw new RuntimeException('Порт для диагностики не настроен.');
            }
            $parameters = [
                'host' => InputValidator::requiredIp($site['recorder_ip'] ?? '', 'IP регистратора'),
                'port' => $port,
            ];
        } elseif ($operation === 'port_discovery') {
            $parameters = [
                'host' => InputValidator::requiredIp($site['recorder_ip'] ?? '', 'IP регистратора'),
                'ports' => [80, 443, 554, 8000, 37777, 34567, 8899],
            ];
        } elseif ($operation === 'rtsp_probe') {
            $channel = max(1, min(4096, (int)($stored['channel'] ?? 1)));
            $subtype = max(0, min(99, (int)($stored['subtype'] ?? 0)));
            $parameters = [
                'host' => InputValidator::requiredIp($site['recorder_ip'] ?? '', 'IP регистратора'),
                'port' => InputValidator::requiredPort($site['rtsp_port'] ?? 554, 'RTSP-порт', 554),
                'username' => (string)$site['rtsp_username'],
                'password' => SecretCipher::decrypt((string)$site['rtsp_password_encrypted']),
                'presets' => (new RtspUrlBuilder())->presets(
                    $channel,
                    $subtype,
                    (string)($site['rtsp_path_template'] ?? '')
                ),
            ];
        } elseif ($operation === 'hls_check') {
            $streamKey = (string)($stored['stream_key'] ?? '');
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $streamKey) !== 1) {
                throw new RuntimeException('Некорректный ключ HLS-потока.');
            }
            $parameters = [
                'hls_url' => \FireballPluginCameraManager::hlsUrl($streamKey),
                'poster_url' => \FireballPluginCameraManager::posterUrl($streamKey),
            ];
        }

        return [
            'id' => (int)$row['id'],
            'operation' => $operation,
            'parameters' => $parameters,
        ];
    }

    private function sanitizeResult(string $operation, array $result): array
    {
        if ($operation === 'wg_peer') {
            $allowed = [];
            foreach (array_slice(is_array($result['allowed_ips'] ?? null) ? $result['allowed_ips'] : [], 0, 20) as $cidr) {
                $cidr = trim((string)$cidr);
                if (preg_match('/^[0-9a-fA-F:.]+\/[0-9]{1,3}$/', $cidr) === 1) {
                    $allowed[] = $cidr;
                }
            }
            return [
                'found' => !empty($result['found']),
                'allowed_ips' => $allowed,
                'latest_handshake_age' => isset($result['latest_handshake_age']) ? max(0, (int)$result['latest_handshake_age']) : null,
                'rx_bytes' => max(0, (int)($result['rx_bytes'] ?? 0)),
                'tx_bytes' => max(0, (int)($result['tx_bytes'] ?? 0)),
            ];
        }
        if ($operation === 'route_check') {
            return [
                'reachable' => !empty($result['reachable']),
                'device' => $this->token((string)($result['device'] ?? ''), 32),
                'source' => $this->ipOrEmpty($result['source'] ?? ''),
                'route' => $this->safeTechnicalLine((string)($result['route'] ?? ''), 300),
            ];
        }
        if ($operation === 'ping_check') {
            return [
                'reachable' => !empty($result['reachable']),
                'latency_ms' => isset($result['latency_ms']) ? max(0, min(60000, (float)$result['latency_ms'])) : null,
            ];
        }
        if ($operation === 'tcp_check') {
            return ['reachable' => !empty($result['reachable'])];
        }
        if ($operation === 'port_discovery') {
            $ports = [];
            foreach (array_slice(is_array($result['open_ports'] ?? null) ? $result['open_ports'] : [], 0, 20) as $port) {
                $validated = InputValidator::nullablePort($port, 'Диагностический порт');
                if ($validated !== null) {
                    $ports[] = $validated;
                }
            }
            return [
                'open_ports' => array_values(array_unique($ports)),
                'heuristic' => $this->safeLine((string)($result['heuristic'] ?? ''), 190),
            ];
        }
        if ($operation === 'rtsp_probe') {
            $profile = (string)($result['profile'] ?? '');
            if (!in_array($profile, InputValidator::RTSP_PROFILES, true)) {
                $profile = '';
            }
            $path = trim((string)($result['path'] ?? ''));
            if ($path !== '' && (!str_starts_with($path, '/') || str_contains($path, "\n") || strlen($path) > 500)) {
                $path = '';
            }
            return [
                'success' => !empty($result['success']),
                'profile' => $profile,
                'path' => $path,
                'codec_name' => $this->token((string)($result['codec_name'] ?? ''), 40),
                'codec_type' => $this->token((string)($result['codec_type'] ?? ''), 40),
                'width' => max(0, min(32768, (int)($result['width'] ?? 0))),
                'height' => max(0, min(32768, (int)($result['height'] ?? 0))),
                'fps' => max(0, min(1000, (float)($result['fps'] ?? 0))),
            ];
        }
        if ($operation === 'hls_check') {
            return [
                'hls_status' => max(0, min(599, (int)($result['hls_status'] ?? 0))),
                'poster_status' => max(0, min(599, (int)($result['poster_status'] ?? 0))),
                'hls_available' => !empty($result['hls_available']),
                'poster_available' => !empty($result['poster_available']),
            ];
        }

        throw new RuntimeException('Unsupported diagnostic result.');
    }

    private function failInvalidStoredJob(int $jobId): void
    {
        db()->query(
            "UPDATE camera_manager_diagnostic_jobs
             SET status = 'failed', error_code = 'unknown', message = 'Unsupported stored operation.', completed_at = ?, updated_at = ?
             WHERE id = ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $jobId]
        );
    }

    private function positiveInteger(mixed $value, string $label): int
    {
        if ((is_string($value) && preg_match('/^[1-9][0-9]{0,18}$/', $value) === 1) || (is_int($value) && $value > 0)) {
            return (int)$value;
        }
        throw new RuntimeException('Invalid ' . $label . '.');
    }

    private function safeLine(string $value, int $limit): string
    {
        $value = preg_replace('~(rtsp://)[^\s@]+@~i', '$1***:***@', $value) ?? '';
        $value = trim(str_replace(["\r", "\n", "\0"], ' ', $value));

        return mb_substr($value, 0, $limit);
    }

    private function safeTechnicalLine(string $value, int $limit): string
    {
        $value = $this->safeLine($value, $limit);

        return preg_match('/^[a-zA-Z0-9_.:\/ -]*$/', $value) === 1 ? $value : '';
    }

    private function token(string $value, int $limit): string
    {
        $value = trim($value);

        return preg_match('/^[a-zA-Z0-9_.-]{1,' . $limit . '}$/', $value) === 1 ? $value : '';
    }

    private function ipOrEmpty(mixed $value): string
    {
        $value = trim((string)$value);

        return $value !== '' && filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : '';
    }
}
