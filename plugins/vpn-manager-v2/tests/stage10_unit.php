<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Services\VpnSubscriptionUrlService;
use Fireball\VpnManagerV2\Support\ProfileVpnFormatter;
use Fireball\VpnManagerV2\Support\ProfileVpnInstructions;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$now = strtotime('2026-07-15 12:00:00');
$assert(ProfileVpnFormatter::effectiveStatus([
    'status' => 'active',
    'starts_at' => '2026-07-01 00:00:00',
    'expires_at' => '2026-07-15 11:59:59',
], $now) === 'expired', 'A physically present expired subscription was not classified as expired.');
$assert(ProfileVpnFormatter::effectiveStatus([
    'status' => 'active',
    'starts_at' => '2026-07-16 00:00:00',
    'expires_at' => '2026-08-16 00:00:00',
], $now) === 'provisioning', 'A future subscription was exposed as active.');
$assert(ProfileVpnFormatter::bytes(0) === '0 B' && ProfileVpnFormatter::bytes(500) === '500 B'
    && ProfileVpnFormatter::bytes(1024 ** 3) === '1 GB', 'Traffic formatting is incorrect.');
$assert(str_contains(ProfileVpnFormatter::remaining('2026-07-17 14:00:00', $now), '2'),
    'Remaining time was not calculated.');

$instructions = ProfileVpnInstructions::all('android');
$assert(count($instructions) === 4 && count($instructions[0]['steps']) === 4,
    'All four platform instruction sets are required.');
$assert(count(array_filter($instructions, static fn(array $item): bool => $item['selected'])) === 1
    && $instructions[1]['selected'], 'Selected instruction platform is incorrect.');

$token = str_repeat('a', 64);
$url = (new VpnSubscriptionUrlService())->forToken($token);
$assert(str_contains($url, '/vpn-v2/subscription/') && str_ends_with($url, $token),
    'Profile subscription URL is not the V2 public endpoint.');

$javascript = (string)file_get_contents(dirname(__DIR__) . '/assets/vpn-manager-v2.js');
$assert(str_contains($javascript, 'navigator.clipboard') && str_contains($javascript, "execCommand('copy')")
    && str_contains($javascript, 'setSelectionRange') && str_contains($javascript, 'data-vpn-v2-manual-copy')
    && str_contains($javascript, 'data-vpn-v2-copy-input'),
    'Clipboard support is incomplete for iPhone and manual fallback.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'effective_expiration',
        'future_subscription_not_active',
        'traffic_formatting',
        'remaining_time',
        'four_platform_instructions',
        'v2_subscription_url',
        'iphone_clipboard_fallback',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
