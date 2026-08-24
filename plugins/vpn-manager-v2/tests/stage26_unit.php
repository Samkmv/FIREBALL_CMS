<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$routes = (string)file_get_contents($root . '/routes/admin.php');
$serverController = (string)file_get_contents($root . '/src/Controllers/Admin/ServerController.php');
$syncController = (string)file_get_contents($root . '/src/Controllers/Admin/SyncController.php');
$auditRepository = (string)file_get_contents($root . '/src/Repositories/SyncAuditRepository.php');
$overviewView = (string)file_get_contents($root . '/views/admin/overview.php');
$logsView = (string)file_get_contents($root . '/views/admin/sync-logs.php');
$navigationView = (string)file_get_contents($root . '/views/admin/partials/tabs.php');
$client = (string)file_get_contents($root . '/src/Clients/ThreeXuiClient.php');

$assert(str_contains($routes, '/servers/(?P<id>\\d+)/metrics')
    && str_contains($routes, '/sync-logs/clear'),
    'The server metrics or clear-logs route is missing.');
$assert(str_contains($client, "/panel/api/server/status")
    && str_contains($serverController, 'ServerMetricsService())->fetch($id)'),
    'The overview is not connected to the current 3x-ui server status API.');
$assert(str_contains($overviewView, 'data-vpn-v2-server-metrics')
    && str_contains($overviewView, 'vpn_manager_v2_overview_swap')
    && str_contains($overviewView, 'vpn_manager_v2_overview_cpu'),
    'The overview does not render CPU, swap, and live server metric cards.');
$assert(str_contains($navigationView, '$primaryKeys')
    && str_contains($navigationView, 'vpn_manager_v2_tab_more'),
    'The administrator navigation was not simplified.');
$assert(str_contains($syncController, 'Permissions::authorize(Permissions::MANAGE_SETTINGS)')
    && str_contains($syncController, "hash_equals('clear_all_vpn_logs'")
    && str_contains($logsView, 'data-admin-delete-form')
    && str_contains($logsView, 'data-delete-message')
    && str_contains($logsView, 'get_csrf_field()'),
    'Clear-all logs lacks permission, explicit confirmation, or CSRF protection.');
$assert(str_contains($auditRepository, "DELETE FROM vpn_v2_sync_logs")
    && str_contains($auditRepository, "DELETE FROM vpn_v2_events")
    && !preg_match('/DELETE FROM vpn_v2_(servers|subscriptions|subscription_nodes|inbounds|plans)/', $auditRepository),
    'Clear-all logs can affect operational VPN records.');

$requiredTranslations = [
    'vpn_manager_v2_tab_more',
    'vpn_manager_v2_overview_servers_load_title',
    'vpn_manager_v2_overview_cpu',
    'vpn_manager_v2_overview_swap',
    'vpn_manager_v2_action_clear_logs',
    'vpn_manager_v2_confirm_clear_logs',
];
foreach (glob($root . '/lang/*.php') ?: [] as $languageFile) {
    $dictionary = require $languageFile;
    foreach ($requiredTranslations as $key) {
        $assert(isset($dictionary[$key]) && trim((string)$dictionary[$key]) !== '',
            basename($languageFile) . ' is missing ' . $key . '.');
    }
}

$plugin = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(version_compare((string)($plugin['version'] ?? '0.0.0'), '0.23.0', '>='),
    'The control-center release was not versioned.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'metrics_and_clear_routes',
        'current_3xui_server_status_api',
        'server_load_overview',
        'simplified_navigation',
        'safe_log_clear_confirmation',
        'operational_data_preserved',
        'localized_interface',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
