<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
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
    $result = (new \Fireball\VpnManagerV2\Services\NotificationMaintenanceService())->runDue(true);
    fwrite(STDOUT, json_encode([
        'status' => 'ok',
        'result' => $result,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
} catch (\Throwable $exception) {
    log_error_details('VPN Manager V2 notification maintenance failed', [], $exception);
    fwrite(STDERR, json_encode([
        'status' => 'error',
        'error' => get_class($exception),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
