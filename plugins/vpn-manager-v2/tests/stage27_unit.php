<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$pluginSource = (string)file_get_contents($root . '/Plugin.php');
$adminRoutes = (string)file_get_contents($root . '/routes/admin.php');
$publicRoutes = (string)file_get_contents($root . '/routes/public.php');
$overview = (string)file_get_contents($root . '/views/admin/overview.php');
$servers = (string)file_get_contents($root . '/views/admin/servers.php');
$profile = (string)file_get_contents($root . '/views/public/my-vpn.php');
$serverService = (string)file_get_contents($root . '/src/Services/ServerManagerService.php');
$accessService = (string)file_get_contents($root . '/src/Services/VpnAccessRequestService.php');
$provisioning = (string)file_get_contents($root . '/src/Services/SubscriptionProvisioningService.php');
$overviewRepository = (string)file_get_contents($root . '/src/Repositories/OverviewRepository.php');
$migration = (string)file_get_contents($root . '/migrations/013_add_access_requests.sql');

$quickActions = strpos($overview, 'vpnV2QuickActionsTitle');
$mainState = strpos($overview, 'vpnV2MainStateTitle');
$assert($quickActions !== false && $mainState !== false && $quickActions < $mainState,
    'Quick actions were not moved above the overview status cards.');
$assert(str_contains($adminRoutes, '/servers/delete')
    && str_contains($servers, 'vpn_manager_v2_action_delete_server')
    && str_contains($servers, 'data-admin-delete-form')
    && str_contains($servers, 'data-delete-message')
    && str_contains($serverService, "['plan_nodes']")
    && str_contains($serverService, "['subscription_nodes']"),
    'Safe server deletion is incomplete.');
$assert(str_contains($publicRoutes, '/profile/vpn-v2/request')
    && str_contains($profile, 'vpn_manager_v2_access_request_action')
    && !str_contains($pluginSource, 'hasSubscriptionsForUser($userId)'),
    'My VPN is still hidden or cannot create an access request without a subscription.');
$assert(str_contains($accessService, 'NotificationService::createForAdmins')
    && str_contains($accessService, 'MailService')
    && str_contains($provisioning, 'fulfillForUser'),
    'VPN requests are not delivered to administrators or fulfilled with a subscription.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS vpn_v2_access_requests')
    && str_contains($migration, 'ON UPDATE CASCADE ON DELETE CASCADE'),
    'Persistent VPN access requests are not migrated safely.');
$assert(str_contains($overviewRepository, "array_fill(0, count(self::TABLES), '?')"),
    'Overview schema checks do not scale with the registered VPN tables.');

$requiredTranslations = [
    'vpn_manager_v2_action_delete_server',
    'vpn_manager_v2_access_request_action',
    'vpn_manager_v2_access_request_admin_title',
    'vpn_manager_v2_access_requests_title',
];
foreach (glob($root . '/lang/*.php') ?: [] as $languageFile) {
    $dictionary = require $languageFile;
    foreach ($requiredTranslations as $key) {
        $assert(isset($dictionary[$key]) && trim((string)$dictionary[$key]) !== '',
            basename($languageFile) . ' is missing ' . $key . '.');
    }
}
$assert((require $root . '/lang/ru.php')['vpn_manager_v2_menu'] === 'Управление VPN',
    'The Russian plugin name was not changed to Управление VPN.');

$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['name_i18n']['ru'] ?? '') === 'Управление VPN'
    && version_compare((string)($manifest['version'] ?? '0.0.0'), '0.24.0', '>='),
    'The renamed plugin release was not published.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'quick_actions_first',
        'safe_server_deletion',
        'persistent_my_vpn_menu',
        'vpn_access_request_delivery',
        'request_schema',
        'localized_interface',
        'renamed_release',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
