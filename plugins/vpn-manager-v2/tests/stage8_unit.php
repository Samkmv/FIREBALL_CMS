<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\DTO\DeletionResult;
use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\RemoteClientDeletionService;
use Fireball\VpnManagerV2\Support\AdminTableState;
use Fireball\VpnManagerV2\Support\Permissions;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$state = AdminTableState::sanitize(
    'page=3&search=alpha&filters%5Bserver%5D=2&sort=status&direction=desc&per_page=25'
    . '&return_url=https%3A%2F%2Fevil.invalid&subscription_token=secret'
);
$assert(str_contains($state, 'page=3') && str_contains($state, 'search=alpha')
    && str_contains($state, 'filters%5Bserver%5D=2') && str_contains($state, 'sort=status')
    && !str_contains($state, 'return_url') && !str_contains($state, 'token'), 'Table state was not safely sanitized.');
$assert(!str_contains(AdminTableState::append('/admin/plugins/vpn-manager-v2/subscriptions', $state), 'evil.invalid'),
    'Table state created an open redirect.');

$assert(Permissions::allows(Permissions::MANAGE_SUBSCRIPTIONS, ['role' => 'creator']), 'Creator permission was rejected.');
$assert(Permissions::allows(Permissions::MANAGE_SUBSCRIPTIONS, ['role' => 'admin']), 'Admin permission was rejected.');
$assert(!Permissions::allows(Permissions::MANAGE_SUBSCRIPTIONS, ['role' => 'user']), 'User permission was accepted.');
$assert(!Permissions::allows('vpn_v2.unknown', ['role' => 'admin']), 'Unknown permission was accepted.');

$node = [
    'client_uuid' => '81111111-1111-4111-8111-111111111111',
    'client_email' => 'stage8-unit',
    'client_sub_id' => 'stage8-sub',
];
$client = [
    'id' => $node['client_uuid'],
    'email' => $node['client_email'],
    'subId' => $node['client_sub_id'],
];
$deletion = new RemoteClientDeletionService();
$assert(count($deletion->matches(['settings' => ['clients' => [$client]]], $node)) === 1,
    'Client was not matched by deletion identities.');
$assert($deletion->matches(['settings' => json_encode(['clients' => []])], $node) === [],
    'Absent client was reported as present.');

$qrKey = (new QrCodeService())->cacheKey(str_repeat('a', 64));
$assert(str_starts_with($qrKey, 'vpn-v2:qr:') && !str_contains($qrKey, str_repeat('a', 64)),
    'QR cache key contains the raw token.');

$result = new DeletionResult(1, 2, 1, 0, true);
$assert($result->successful(), 'Successful deletion result was rejected.');
$assert(!(new DeletionResult(1, 1, 0, 1, false))->successful(), 'Failed deletion result was accepted.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'table_state_preserved',
        'open_redirect_rejected',
        'permission_guard',
        'identity_match_uuid_email_subid',
        'already_absent_detection',
        'qr_cache_token_hash',
        'deletion_result',
    ],
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
