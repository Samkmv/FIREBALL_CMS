<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

if (!function_exists('return_translation')) {
    function return_translation(string $key): string { return $key; }
}
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\ClientPayloadFactory;
use Fireball\VpnManagerV2\Support\SubscriptionAccessPolicy;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$now = time();
$active = ['status' => 'active', 'starts_at' => date('Y-m-d H:i:s', $now - 3600),
    'expires_at' => date('Y-m-d H:i:s', $now + 3600), 'traffic_limit_bytes' => 100,
    'traffic_used_bytes' => 20, 'device_limit' => 2, 'ip_limit' => 0];
$node = ['protocol' => 'vless', 'client_uuid' => '00000000-0000-4000-8000-000000000012',
    'client_email' => 'access-fixture', 'client_sub_id' => 'access-fixture', 'desired_enabled' => 1];
$factory = new ClientPayloadFactory();
$assert(SubscriptionAccessPolicy::enabled($active, $node, $now), 'Active subscription was denied.');
foreach ([
    ['expires_at' => date('Y-m-d H:i:s', $now)],
    ['expires_at' => date('Y-m-d H:i:s', $now - 1)],
    ['starts_at' => date('Y-m-d H:i:s', $now + 1)],
    ['status' => 'suspended'], ['status' => 'expired'], ['status' => 'traffic_exceeded'],
    ['status' => 'pending_remote_delete'], ['status' => 'deleted'],
    ['traffic_used_bytes' => 100],
] as $change) {
    $subscription = array_replace($active, $change);
    $assert(!SubscriptionAccessPolicy::enabled($subscription, $node, $now), 'Stale node enable bypassed access policy.');
    $assert($factory->build($subscription, $node)['enable'] === false, 'Remote payload re-enabled denied access.');
}
$assert(!SubscriptionAccessPolicy::enabled($active, ['desired_enabled' => 0], $now), 'Disabled node was re-enabled.');
$assert(!SubscriptionAccessPolicy::enabled(array_replace($active, ['expires_at' => 'invalid']), $node, $now),
    'Malformed expiration granted access.');
$rejected = false;
try { $factory->build(array_replace($active, ['expires_at' => 'invalid']), $node); }
catch (ValidationException) { $rejected = true; }
$assert($rejected, 'Malformed expiration became unlimited access.');
$lifetime = $factory->build(array_replace($active, ['expires_at' => null]), $node);
$assert($lifetime['enable'] === true && $lifetime['expiryTime'] === 0, 'Lifetime subscription was denied.');
$renewed = $factory->build($active, $node);
$assert($renewed['enable'] === true && $renewed['expiryTime'] === ($now + 3600) * 1000,
    'Renewed subscription did not receive a finite future expiration.');
$updated = $factory->mergeForUpdate(['reset' => 1, 'resetDay' => 15, 'limitHwid' => 2], $renewed);
$assert($updated['reset'] === 0 && $updated['resetDay'] === 0
    && $updated['limitHwid'] === 2 && $updated['limitIp'] === 0,
    'Ordinary synchronization changed counters or did not apply the CMS HWID/IP limits.');

echo json_encode(['status' => 'ok', 'cases' => ['expiration_boundary', 'stale_enable', 'suspension',
    'traffic_limit', 'malformed_expiration', 'lifetime', 'renewal', 'reset_and_device_limit_preserved']]), PHP_EOL;
