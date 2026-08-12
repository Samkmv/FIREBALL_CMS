<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "user_deletion_plugin_regression: {$message}\n");
        exit(1);
    }
};

$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$userModel = $read('app/Models/User.php');
$adminController = $read('app/Controllers/AdminController.php');
$vpnPlugin = $read('plugins/vpn-manager-v2/Plugin.php');
$manifest = json_decode(
    $read('plugins/vpn-manager-v2/plugin.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$assert(str_contains($userModel, "apply_filters('admin_user_delete_blockers'"),
    'the user model does not ask plugins for deletion blockers');
$assert(str_contains($userModel, "return 'vpn-manager-v2'"),
    'foreign-key errors do not identify VPN Manager V2');
$assert(str_contains($adminController, 'admin_users_delete_related_data_vpn_manager_v2'),
    'the administrator does not receive a VPN-specific deletion message');
$assert(str_contains($vpnPlugin, "add_filter('admin_user_delete_blockers'"),
    'VPN Manager V2 does not register its active-subscription blocker');
$assert(str_contains($vpnPlugin, "add_action('admin_user_deleting'"),
    'VPN Manager V2 does not register cleanup for deleted subscriptions');
$assert(str_contains($vpnPlugin, "status <> 'deleted'"),
    'active VPN subscriptions do not block CMS user deletion');
$assert(str_contains($vpnPlugin, 'DELETE FROM vpn_v2_subscriptions WHERE user_id = ?'),
    'archived VPN subscriptions are not removed before CMS user deletion');
$assert(str_contains($vpnPlugin, 'UPDATE vpn_v2_subscriptions SET created_by = ?'),
    'VPN subscriptions created for other users are not reassigned');
$assert(version_compare((string)($manifest['version'] ?? '0.0.0'), '0.19.6', '>='),
    'VPN Manager V2 user-deletion fix was not versioned');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require $root . '/app/Languages/' . $locale . '.php';
    $assert(isset($translations['admin_users_delete_related_data_vpn_manager_v2']),
        "VPN deletion guidance is missing for {$locale}");
    $assert(isset($translations['admin_users_delete_related_data_subscriptions']),
        "subscription deletion guidance is missing for {$locale}");
}

echo json_encode([
    'status' => 'ok',
    'plugin_detection' => true,
    'vpn_active_subscription_guard' => true,
    'vpn_deleted_data_cleanup' => true,
    'localized_guidance' => ['ru', 'en', 'de', 'zh-cn'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
