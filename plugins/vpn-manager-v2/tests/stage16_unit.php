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

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$defaults = SettingsService::defaults();
$assert(($defaults['subscription_name'] ?? '') === 'VPN V2', 'The subscription name has no safe default.');

$settings = (new SettingsValidator())->validate(array_replace($defaults, [
    'subscription_name' => 'Fireball Personal',
]), $defaults)->toArray();
$assert(($settings['subscription_name'] ?? '') === 'Fireball Personal', 'The subscription name was not normalized.');

$invalidNameRejected = false;
try {
    (new SettingsValidator())->validate(array_replace($defaults, [
        'subscription_name' => "Broken\nHeader",
    ]), $defaults);
} catch (ValidationException) {
    $invalidNameRejected = true;
}
$assert($invalidNameRejected, 'A control character was accepted in the subscription name.');

$root = dirname(__DIR__);
$migration = (string)file_get_contents($root . '/migrations/008_add_subscription_connection_order.sql');
$subscriptionRepository = (string)file_get_contents($root . '/src/Repositories/SubscriptionRepository.php');
$configRepository = (string)file_get_contents($root . '/src/Repositories/SubscriptionConfigRepository.php');
$endpoint = (string)file_get_contents($root . '/src/Services/VpnSubscriptionEndpointService.php');
$route = (string)file_get_contents($root . '/routes/admin.php');
$view = (string)file_get_contents($root . '/views/admin/subscription-show.php');
$javascript = (string)file_get_contents($root . '/assets/vpn-manager-v2.js');
$plugin = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

$assert(str_contains($migration, 'ADD COLUMN sort_order')
    && str_contains($migration, 'idx_vpn_v2_sub_nodes_order')
    && str_contains($migration, 'SET sort_order = id'),
    'The order migration is not upgrade-safe or does not backfill existing connections.');
$assert(str_contains($configRepository, 'ORDER BY n.sort_order ASC, n.id ASC'),
    'Public subscription nodes do not use the saved order.');
$assert(str_contains($subscriptionRepository, 'revision = revision + 1')
    && str_contains($subscriptionRepository, 'subscription.connection_order_changed'),
    'Reordering does not atomically update revision and audit state.');
$assert(str_contains($endpoint, "'profile-title'")
    && str_contains($endpoint, "'base64:' . base64_encode(\$subscriptionName)"),
    'The subscription name is not returned as a safe profile-title header.');
$assert(str_contains($route, '/connections/order/')
    && str_contains($view, 'data-vpn-v2-connection-order')
    && str_contains($javascript, 'setupConnectionOrder'),
    'The administrative connection-order workflow is incomplete.');
$assert(($plugin['version'] ?? '') === '0.15.0', 'The plugin version was not bumped for migration 008.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'subscription_name_default',
        'subscription_name_validation',
        'subscription_name_header_safety',
        'connection_order_migration',
        'public_connection_order',
        'atomic_revision_and_audit',
        'profile_title_header',
        'admin_order_workflow',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
