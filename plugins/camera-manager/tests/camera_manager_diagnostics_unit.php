<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/InputValidator.php';
require_once dirname(__DIR__) . '/src/RtspUrlBuilder.php';
require_once dirname(__DIR__) . '/src/SecretCipher.php';
require_once dirname(__DIR__) . '/src/DiagnosticJobService.php';
require_once dirname(__DIR__) . '/src/PullSyncService.php';

use Fireball\CameraManager\DiagnosticJobService;
use Fireball\CameraManager\PullSyncService;
use Fireball\CameraManager\SecretCipher;

if (!defined('CHAT_ENCRYPTION_KEY')) {
    define('CHAT_ENCRYPTION_KEY', 'camera-manager-diagnostic-test-key');
}

final class CameraManagerDiagnosticFakeDatabase
{
    public array $jobs = [];
    private mixed $result = null;
    private int $nextId = 1;

    public function query(string $sql, array $params = []): self
    {
        if (str_starts_with($sql, 'INSERT INTO camera_manager_diagnostic_jobs')) {
            $this->jobs[] = [
                'id' => $this->nextId++,
                'batch_id' => $params[0],
                'sequence_number' => $params[1],
                'site_id' => $params[2],
                'operation' => $params[3],
                'parameters_json' => $params[4],
                'status' => $params[5],
                'result_json' => null,
                'error_code' => null,
                'message' => null,
                'created_by' => $params[6],
                'created_at' => $params[7],
                'dispatched_at' => null,
                'completed_at' => null,
                'updated_at' => $params[8],
            ];
            $this->result = null;
            return $this;
        }
        if (str_contains($sql, 'FROM camera_manager_diagnostic_jobs') && str_contains($sql, "status = 'pending'")) {
            $this->result = array_values(array_filter($this->jobs, static fn(array $job): bool => $job['status'] === 'pending'));
            return $this;
        }
        if (str_starts_with(trim($sql), 'UPDATE camera_manager_diagnostic_jobs') && str_contains($sql, "SET status = 'running'")) {
            foreach ($this->jobs as &$job) {
                if ($job['id'] === $params[2]) {
                    $job['status'] = 'running';
                    $job['dispatched_at'] = $params[0];
                    $job['updated_at'] = $params[1];
                }
            }
            unset($job);
            return $this;
        }
        if (str_starts_with($sql, 'SELECT * FROM camera_manager_diagnostic_jobs WHERE id = ?')) {
            $this->result = null;
            foreach ($this->jobs as $job) {
                if ($job['id'] === $params[0]) {
                    $this->result = $job;
                }
            }
            return $this;
        }
        if (str_starts_with(trim($sql), 'UPDATE camera_manager_diagnostic_jobs') && str_contains($sql, 'SET status = ?, result_json = ?')) {
            foreach ($this->jobs as &$job) {
                if ($job['id'] === $params[6]) {
                    $job['status'] = $params[0];
                    $job['result_json'] = $params[1];
                    $job['error_code'] = $params[2];
                    $job['message'] = $params[3];
                    $job['completed_at'] = $params[4];
                    $job['updated_at'] = $params[5];
                }
            }
            unset($job);
            return $this;
        }
        throw new RuntimeException('Unexpected SQL in diagnostic test: ' . $sql);
    }

    public function get(): array
    {
        return is_array($this->result) ? $this->result : [];
    }

    public function getOne(): mixed
    {
        return $this->result;
    }

    public function getColumn(): mixed
    {
        return $this->result;
    }
}

$GLOBALS['camera_manager_diagnostic_db'] = new CameraManagerDiagnosticFakeDatabase();
$GLOBALS['camera_manager_diagnostic_settings'] = [
    'connection_mode' => 'pull',
    'pull_revision' => 0,
    'pull_last_revision' => 0,
    'wireguard_interface' => 'wg0',
    'wireguard_server_ip' => '10.10.0.254',
];
$diagnosticPassword = 'Secret_Not_For_Logs_123!';
$GLOBALS['camera_manager_diagnostic_site'] = [
    'id' => 34,
    'recorder_ip' => '192.168.34.100',
    'rtsp_port' => 554,
    'rtsp_username' => 'operator',
    'rtsp_password_encrypted' => SecretCipher::encrypt($diagnosticPassword),
    'rtsp_path_template' => '/cam/realmonitor?channel={channel}&subtype={subtype}',
];

function db(): CameraManagerDiagnosticFakeDatabase
{
    return $GLOBALS['camera_manager_diagnostic_db'];
}

final class FireballPluginCameraManager
{
    public static function site(int $id): ?array
    {
        return $id === 34 ? $GLOBALS['camera_manager_diagnostic_site'] : null;
    }

    public static function settings(): array
    {
        return $GLOBALS['camera_manager_diagnostic_settings'];
    }

    public static function settingValue(string $key, mixed $default = null): mixed
    {
        return $GLOBALS['camera_manager_diagnostic_settings'][$key] ?? $default;
    }

    public static function setSettingValue(string $key, mixed $value): void
    {
        $GLOBALS['camera_manager_diagnostic_settings'][$key] = $value;
    }

    public static function hlsUrl(string $key): string
    {
        return 'https://rtsp.example.test/rtsp/stream-' . rawurlencode($key) . '/index.m3u8';
    }

    public static function posterUrl(string $key): string
    {
        return 'https://rtsp.example.test/rtsp/tn-' . rawurlencode($key) . '.jpg';
    }

    public static function recordPublication(string $status, int $count, ?string $backup, string $message): void
    {
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$queue = new DiagnosticJobService();
$queue->queueRtspProbe(34, 1, 0);
$assert(count(db()->jobs) === 1, 'RTSP diagnostic job was not queued.');
$assert(!str_contains((string)db()->jobs[0]['parameters_json'], $diagnosticPassword), 'RTSP password was stored in the diagnostic queue.');

$pull = new PullSyncService();
$response = $pull->handle([
    'action' => 'fetch',
    'current_revision' => 0,
    'capabilities' => ['diagnostics_v1'],
]);
$assert(count($response['diagnostic_jobs']) === 1, 'Diagnostic job was not delivered to a capable agent.');
$agentJob = $response['diagnostic_jobs'][0];
$assert($agentJob['operation'] === 'rtsp_probe', 'Unexpected diagnostic operation was delivered.');
$assert($agentJob['parameters']['password'] === $diagnosticPassword, 'Agent did not receive the in-memory RTSP credential.');
$assert(!isset($agentJob['parameters']['command']), 'Diagnostic protocol exposed an arbitrary command field.');

$pull->handle([
    'action' => 'diagnostic_report',
    'job_id' => (int)$agentJob['id'],
    'status' => 'success',
    'error_code' => '',
    'message' => 'RTSP stream found.',
    'result' => [
        'success' => true,
        'profile' => 'hikvision_isapi',
        'path' => '/ISAPI/Streaming/Channels/101',
        'codec_name' => 'h264',
        'codec_type' => 'video',
        'width' => 2560,
        'height' => 1440,
        'fps' => 25,
        'password' => $diagnosticPassword,
        'command' => 'id',
    ],
]);
$storedResult = (string)db()->jobs[0]['result_json'];
$assert(!str_contains($storedResult, $diagnosticPassword), 'Diagnostic result stored an RTSP password.');
$assert(!str_contains($storedResult, 'command'), 'Diagnostic result stored an arbitrary command.');
$assert(str_contains($storedResult, 'hikvision_isapi'), 'Safe diagnostic result fields were discarded.');

echo "Camera Manager diagnostic protocol checks passed.\n";
