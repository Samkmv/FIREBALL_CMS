<?php
// Isolated HTTP response fixture: the worker is an in-memory stub.
namespace Fireball\VpnManagerV2\Services {
    class RemoteOperationProcessor {
        public function processOperation(string $id): void {
            if ($id !== '00000000-0000-4000-8000-000000000001') throw new \RuntimeException('Wrong operation');
        }
        public function processDue(int $limit): array {
            if ($limit !== 10) throw new \RuntimeException('Unexpected batch limit');
            return $GLOBALS['batch'];
        }
    }
}
namespace Fireball\VpnManagerV2\Support {
    class Permissions {
        const RECONCILE = 'reconcile';
        public static function authorize(string $permission): void {
            if ($permission !== self::RECONCILE) throw new \RuntimeException('Wrong permission');
        }
    }
}
namespace Fireball\VpnManagerV2\Repositories {
    class OperationQueueRepository {
        public function retryFailed(): int { return $GLOBALS['retryCount']; }
        public function enqueue(...$arguments): array {
            if ($arguments !== ['full_reconcile', 'manual_sync', null, null, null, [], 1]) throw new \RuntimeException('Wrong queue scope');
            return ['operation_id' => '00000000-0000-4000-8000-000000000001', 'status' => 'pending', 'created' => true];
        }
        public function progress(string $id): array { return $GLOBALS['singleProgress']; }
    }
}
namespace {
    function get_user(): array { return ['id' => 1]; }
    function base_href(string $path): string { return $path; }
    class FireballPluginVpnManagerV2 {
        public static function t(string $key): string {
            static $language;
            $language ??= require dirname(__DIR__) . '/lang/ru.php';
            return $language[$key] ?? $key;
        }
    }
    require dirname(__DIR__) . '/src/Support/LocalizedValue.php';
    require dirname(__DIR__) . '/src/Controllers/Admin/SyncController.php';
    $cases = [
        'success' => ['counts' => [2, 2, 0, 0], 'status' => 'completed'],
        'cancelled' => ['counts' => [1, 0, 0, 1], 'status' => 'cancelled'],
        'failure' => ['counts' => [1, 0, 1, 0], 'status' => 'completed_partial'],
        'mixed' => ['counts' => [3, 1, 1, 1], 'status' => 'completed_partial'],
        'success_cancelled' => ['counts' => [2, 1, 0, 1], 'status' => 'completed_partial'],
        'empty' => ['counts' => [0, 0, 0, 0], 'status' => 'completed'],
    ];
    $case = $argv[1] ?? '';
    $singleStatuses = ['pending', 'running', 'completed', 'completed_partial', 'retry', 'failed', 'cancelled'];
    if (str_starts_with($case, 'single:') && in_array(substr($case, 7), $singleStatuses, true)) {
        $GLOBALS['singleProgress'] = ['status' => substr($case, 7), 'processed_count' => 2, 'total_count' => 3, 'last_error' => 'Безопасная причина ошибки'];
        (new \Fireball\VpnManagerV2\Controllers\Admin\SyncController())->full();
    }
    if (isset($cases[$case])) {
        $GLOBALS['batch'] = array_combine(['processed', 'success', 'failure', 'cancelled'], $cases[$case]['counts']);
        if (($argv[2] ?? '') === 'retry') {
            $GLOBALS['retryCount'] = $case === 'empty' ? 0 : 11;
            (new \Fireball\VpnManagerV2\Controllers\Admin\SyncController())->retry();
        }
        (new \Fireball\VpnManagerV2\Controllers\Admin\SyncController())->processPending();
    }
    $checks = 0;
    foreach ($singleStatuses as $status) {
        $process = proc_open([PHP_BINARY, __FILE__, 'single:' . $status], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Cannot start single-operation fixture');
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        if ($code !== 0) throw new \RuntimeException($error);
        $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $checks++; if ($response['status'] !== $status) throw new \RuntimeException('Actual single-operation status lost');
        $checks++; if ($response['processed_count'] !== 2 || $response['total_count'] !== 3) throw new \RuntimeException('Actual progress counts lost');
        $checks++; if ($response['last_error'] !== 'Безопасная причина ошибки') throw new \RuntimeException('Safe cause not returned');
        $checks++; if ($response['progress_url'] !== '/admin/plugins/vpn-manager-v2/operations/00000000-0000-4000-8000-000000000001') throw new \RuntimeException('Wrong progress owner');
        $checks++; if (in_array($status, ['failed', 'retry'], true) && $response['message'] !== $response['status_label']) throw new \RuntimeException('Failed operation incorrectly reported as queued');
    }
    foreach (['process', 'retry'] as $action) foreach ($cases as $name => $expected) {
        $process = proc_open([PHP_BINARY, __FILE__, $name, $action], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Cannot start isolated response fixture');
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        if ($code !== 0) throw new \RuntimeException($error);
        $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        foreach (array_combine(['processed', 'success', 'failure', 'cancelled'], $expected['counts']) as $key => $count) {
            $checks++; if (($response[$key] ?? null) !== $count) throw new \RuntimeException("$name: incorrect $key count");
        }
        $checks++; if ($response['status'] !== $expected['status']) throw new \RuntimeException("$name: misleading result status");
        $checks++; if (str_contains($response['message'], 'vpn_manager_v2_') || !str_contains($response['message'], 'Отменено устаревших:')) {
            throw new \RuntimeException("$name: untranslated/missing batch summary");
        }
        if ($action === 'retry') {
            $checks++; if ($response['retried'] !== ($name === 'empty' ? 0 : 11)) throw new \RuntimeException('Incorrect retry count');
        }
    }
    echo "PASS bulk operation responses: $checks checks, no real database or panels\n";
}
