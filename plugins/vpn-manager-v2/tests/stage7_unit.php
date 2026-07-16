<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Exceptions\ClientVerificationException;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\ClientPayloadFactory;
use Fireball\VpnManagerV2\Services\ClientVerifier;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Validators\ConnectionEditValidator;
use Fireball\VpnManagerV2\Validators\SubscriptionEditValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$subscriptionEdit = (new SubscriptionEditValidator())->validate([
    'expires_at' => date('Y-m-d\TH:i', time() + 86400),
    'traffic_limit_value' => '25',
    'traffic_unit' => 'gb',
    'status' => 'active',
    'internal_comment' => 'CMS only',
]);
$assert($subscriptionEdit->trafficLimitBytes === 25 * (1024 ** 3), 'Subscription traffic was not normalized.');
$assert($subscriptionEdit->internalComment === 'CMS only', 'Internal comment was not normalized.');

$expired = (new SubscriptionEditValidator())->validate([
    'expires_at' => date('Y-m-d\TH:i', time() - 3600),
    'traffic_limit_value' => 0,
    'traffic_unit' => 'gb',
    'status' => 'active',
]);
$assert($expired->status === 'expired', 'Past expiration did not force expired status.');

$tcpReality = ['protocol' => 'vless', 'network' => 'tcp', 'security' => 'reality'];
$connectionEdit = (new ConnectionEditValidator())->validate([
    'flow' => VpnFlowResolver::VISION,
    'traffic_limit_value' => '10',
    'traffic_unit' => 'gb',
], $tcpReality);
$assert($connectionEdit->flow === VpnFlowResolver::VISION, 'Compatible Vision Flow was rejected.');

$incompatibleRejected = false;
try {
    (new ConnectionEditValidator())->validate([
        'flow' => VpnFlowResolver::VISION,
        'traffic_limit_value' => 1,
        'traffic_unit' => 'gb',
    ], ['protocol' => 'vless', 'network' => 'xhttp', 'security' => 'reality']);
} catch (ValidationException) {
    $incompatibleRejected = true;
}
$assert($incompatibleRejected, 'Incompatible XHTTP Vision Flow was accepted.');

$factory = new ClientPayloadFactory();
$node = [
    'protocol' => 'vless',
    'client_uuid' => '11111111-1111-4111-8111-111111111111',
    'client_email' => 'stage7-unit',
    'client_sub_id' => 'sub-safe',
    'flow' => VpnFlowResolver::VISION,
    'traffic_limit_bytes' => 10 * (1024 ** 3),
];
$disabledPayload = $factory->build([
    'expires_at' => '2035-01-01 00:00:00',
    'device_limit' => 2,
    'status' => 'suspended',
], $node);
$assert($disabledPayload['enable'] === false, 'Suspended subscription payload remained enabled.');

$remote = array_replace($disabledPayload, ['reset' => 9, 'up' => 123, 'down' => 456]);
$merged = $factory->mergeForUpdate($remote, array_replace($disabledPayload, ['totalGB' => 20 * (1024 ** 3)]));
$assert($merged['reset'] === 0, 'Ordinary update did not force reset=0.');
$assert($merged['up'] === 123 && $merged['down'] === 456, 'Remote counter fields were not preserved in the update payload.');
$assert($merged['id'] === $node['client_uuid'] && $merged['email'] === $node['client_email'], 'Identity changed during payload merge.');

$verifier = new ClientVerifier();
$changed = $verifier->changedFields($remote, array_replace($disabledPayload, ['totalGB' => 20 * (1024 ** 3)]));
$assert($changed === ['totalGB'], 'Changed-field diff is not field-specific.');
$verifier->verifyFields($disabledPayload, $disabledPayload, ['enable', 'expiryTime', 'totalGB', 'flow']);

$identityRejected = false;
try {
    $verifier->assertIdentity(array_replace($disabledPayload, ['email' => 'different']), $disabledPayload);
} catch (ClientVerificationException) {
    $identityRejected = true;
}
$assert($identityRejected, 'Changed client identity was accepted.');

$endpoint = new VpnSubscriptionEndpointService();
$etag1 = $endpoint->etag(str_repeat('a', 64), 1, 'base64');
$etag2 = $endpoint->etag(str_repeat('a', 64), 2, 'base64');
$assert($etag1 !== $etag2, 'ETag did not change with revision.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'subscription_edit_validation',
        'past_expiration_status',
        'compatible_flow',
        'incompatible_flow_rejected',
        'status_to_enable',
        'reset_protection',
        'identity_preserved',
        'field_specific_diff',
        'identity_mismatch_rejected',
        'etag_revision',
    ],
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
