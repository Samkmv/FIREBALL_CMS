<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--access-only', '--notifications-only', '--reconcile-only']) !== []
    || count($arguments) > 1) {
    fwrite(STDERR, "Usage: php cron.php [--access-only|--notifications-only|--reconcile-only]\n");
    exit(2);
}

require_once __DIR__ . '/../../config/config.php';
require_once ROOT . '/vendor/autoload.php';
require_once HELPERS . '/helpers.php';

$app = new \FBL\Application();
require_once __DIR__ . '/Plugin.php';
\FBL\Language::registerPluginLanguage('vpn-manager-v2', __DIR__ . '/lang');

$cronScope = substr(hash('sha256', defined('ROOT') ? (string)ROOT : __DIR__), 0, 16);
$lockPath = sys_get_temp_dir() . '/fireball-vpn-v2-notifications-' . $cronScope . '-cron.lock';
$lock = @fopen($lockPath, 'c');
if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, json_encode(['status' => 'skipped', 'reason' => 'already_running']) . PHP_EOL);
    exit(0);
}

try {
    $result = [];
    $failed = false;
    if ($arguments === [] || in_array('--access-only', $arguments, true)) {
        $result['expiration'] = (new \Fireball\VpnManagerV2\Services\SubscriptionAutomationService())->checkExpirations();
        $failed = (int)($result['expiration']['failed'] ?? 0) > 0;
    }
    if ($arguments === [] || in_array('--reconcile-only', $arguments, true)) {
        $result['reconciliation'] = (new \Fireball\VpnManagerV2\Jobs\VpnV2ReconcilePlanSubscriptionsJob())->handle();
        $failed = $failed || (int)($result['reconciliation']['failure'] ?? 0) > 0;
    }
    if ($arguments === [] || in_array('--notifications-only', $arguments, true)) {
        $result['notifications'] = (new \Fireball\VpnManagerV2\Services\NotificationMaintenanceService())->runDue(true);
        foreach ($result['notifications']['steps'] ?? [] as $step) {
            $failed = $failed || ($step['status'] ?? 'error') !== 'ok'
                || (int)($step['result']['failed'] ?? 0) > 0;
        }
    }
    fwrite(STDOUT, json_encode([
        'status' => $failed ? 'partial_failure' : 'ok',
        'result' => $result,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($failed ? 1 : 0);
} catch (\Throwable $exception) {
    log_error_details('VPN Manager V2 maintenance failed', [], $exception);
    fwrite(STDERR, json_encode([
        'status' => 'error',
        'error' => get_class($exception),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
