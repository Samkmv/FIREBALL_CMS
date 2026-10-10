<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
function return_translation(string $key): string { return $key; }
require dirname(__DIR__) . '/Plugin.php';
$uris = [];
foreach (array_slice($argv, 1) as $index => $port) {
    $port = filter_var($port, FILTER_VALIDATE_INT);
    if ($port === false || $port < 1024 || $port > 65535) { exit(2); }
    $uris[] = 'vless://81111111-1111-4111-8111-111111111111@127.0.0.1:' . $port . '?encryption=none&security=none&type=tcp#Fixture' . $index;
}
echo (new \Fireball\VpnManagerV2\Services\SingBoxSubscriptionBuilder())->build($uris,
    array_replace(\Fireball\VpnManagerV2\Services\SettingsService::defaults(), [
        'smart_connect_enabled' => true, 'smart_connect_singbox_enabled' => true,
        'smart_connect_mode' => 'failover', 'smart_connect_interval_seconds' => 30,
        'smart_connect_tolerance_ms' => 100,
    ]));
