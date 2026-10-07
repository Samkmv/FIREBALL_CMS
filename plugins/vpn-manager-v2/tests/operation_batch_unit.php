<?php
// Isolated HTTP response fixture: the worker is an in-memory stub.
namespace Fireball\VpnManagerV2\Services {
    class RemoteOperationProcessor {
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
namespace {
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
    if (isset($cases[$case])) {
        $GLOBALS['batch'] = array_combine(['processed', 'success', 'failure', 'cancelled'], $cases[$case]['counts']);
        (new \Fireball\VpnManagerV2\Controllers\Admin\SyncController())->processPending();
    }
    $checks = 0;
    foreach ($cases as $name => $expected) {
        $process = proc_open([PHP_BINARY, __FILE__, $name], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
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
    }
    echo "PASS bulk operation responses: $checks checks, no real database or panels\n";
}
