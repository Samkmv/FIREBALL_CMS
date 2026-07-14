<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

if (!function_exists('return_translation')) {
    function return_translation(string $key): string
    {
        return $key;
    }
}

require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Exceptions\ClientVerificationException;
use Fireball\VpnManagerV2\Services\ClientPayloadFactory;
use Fireball\VpnManagerV2\Services\ClientVerifier;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Support\SubscriptionToken;
use Fireball\VpnManagerV2\Support\Uuid;
use Fireball\VpnManagerV2\Validators\SubscriptionValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$request = (new SubscriptionValidator())->validate([
    'user_id' => 9,
    'plan_id' => 4,
    'starts_at' => '2026-07-14T12:30',
]);
$assert($request->startsAt === '2026-07-14 12:30:00', 'datetime-local normalization failed.');

$payload = (new ClientPayloadFactory())->build([
    'expires_at' => '2026-08-13 12:30:00',
    'device_limit' => 3,
    'traffic_limit_bytes' => 107374182400,
], [
    'protocol' => 'vless',
    'client_uuid' => '9fd7c35e-256a-4b5c-9c10-df3f36f08b6b',
    'client_email' => 'vpn-v2-u9-s1-p1',
    'client_sub_id' => 'a1b2c3d4e5f60708',
    'flow' => VpnFlowResolver::VISION,
    'traffic_limit_bytes' => 107374182400,
]);
$assert($payload['id'] === '9fd7c35e-256a-4b5c-9c10-df3f36f08b6b', 'VLESS UUID is missing from payload.');
$assert($payload['flow'] === VpnFlowResolver::VISION, 'Vision is missing from payload.');
$assert($payload['limitIp'] === 3 && $payload['totalGB'] === 107374182400, 'Limits are invalid.');
$assert($payload['enable'] === true && $payload['subId'] === 'a1b2c3d4e5f60708', 'Client flags are invalid.');
$assert($payload['tgId'] === 0 && $payload['security'] === 'auto', '3x-ui universal client fields have invalid types.');

$verifier = new ClientVerifier();
$remote = $payload;
$verifier->verify($remote, $payload);
$inbound = ['settings' => json_encode(['clients' => [$remote]], JSON_UNESCAPED_SLASHES)];
$assert($verifier->findInInbound($inbound, (string)$payload['id'], (string)$payload['email']) !== null, 'Client lookup failed.');

$flowMismatch = false;
try {
    $withoutFlow = $remote;
    $withoutFlow['flow'] = '';
    $verifier->verify($withoutFlow, $payload);
} catch (ClientVerificationException $exception) {
    $flowMismatch = $exception->isFlowMismatch();
}
$assert($flowMismatch, 'Flow mismatch was not classified separately.');

$genericMismatch = false;
try {
    $wrongLimit = $remote;
    $wrongLimit['limitIp'] = 1;
    $verifier->verify($wrongLimit, $payload);
} catch (ClientVerificationException $exception) {
    $genericMismatch = !$exception->isFlowMismatch();
}
$assert($genericMismatch, 'Generic client mismatch was not rejected.');

$uuidA = Uuid::v4();
$uuidB = Uuid::v4();
$assert($uuidA !== $uuidB && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuidA) === 1, 'UUID v4 generation failed.');
$tokenA = SubscriptionToken::generate();
$tokenB = SubscriptionToken::generate();
$assert(strlen($tokenA) === 64 && $tokenA !== $tokenB, 'Subscription token generation failed.');
$assert(!str_contains(SubscriptionToken::preview($tokenA), $tokenA), 'Token preview exposes the full token.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'subscription_input_validation',
        'vless_payload',
        'client_confirmation',
        'flow_mismatch_classification',
        'generic_mismatch_rejection',
        'uuid_v4',
        'subscription_token',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
