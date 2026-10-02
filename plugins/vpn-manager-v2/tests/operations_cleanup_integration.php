<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Repositories\OperationQueueRepository;
use Fireball\VpnManagerV2\Support\Uuid;

$database = db();
$database->beginTransaction();
try {
    $ids = [];
    $now = date('Y-m-d H:i:s');
    foreach (['pending', 'retry', 'completed', 'completed_partial', 'failed', 'cancelled', 'running', 'unknown'] as $status) {
        $uuid = Uuid::v4();
        $database->query(
            'INSERT INTO vpn_v2_operations (operation_id, operation_type, source, status, next_attempt_at, created_at, updated_at)
             VALUES (?, \'sync_server\', \'cms\', ?, ?, ?, ?)',
            [$uuid, $status, $now, $now, $now]
        );
        $ids[$status] = $uuid;
    }
    $beforeProtected = $database->query("SELECT operation_id FROM vpn_v2_operations WHERE status NOT IN ('pending', 'retry', 'completed', 'completed_partial', 'failed', 'cancelled') ORDER BY id")->get();
    $queue = new OperationQueueRepository();
    $deleted = $queue->clearNotRunning();
    if ($deleted < 6) { throw new RuntimeException('Waiting jobs and terminal records must be removed'); }
    foreach ($ids as $status => $uuid) {
        $exists = $queue->progress($uuid) !== null;
        if ($exists !== in_array($status, ['running', 'unknown'], true)) {
            throw new RuntimeException('Unexpected cleanup outcome for ' . $status);
        }
    }
    $afterProtected = $database->query('SELECT operation_id FROM vpn_v2_operations ORDER BY id')->get();
    if ($beforeProtected !== $afterProtected) { throw new RuntimeException('Running and unrecognized states must be preserved'); }
    if ($queue->clearNotRunning() !== 0) { throw new RuntimeException('Repeated cleanup must be harmless'); }
    echo "PASS operations cleanup: waiting jobs removed, running jobs protected, repeated cleanup harmless; transaction rolled back\n";
} finally {
    // Test all actual SQL paths without changing existing operation records.
    $database->rollBack();
}
