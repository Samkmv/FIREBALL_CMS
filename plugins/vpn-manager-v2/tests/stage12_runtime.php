<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

$app = new FBL\Application();
require CONFIG . '/routes.php';

use Fireball\VpnManagerV2\Jobs\VpnV2CheckExpirationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2CheckTrafficLimitsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2RetryFailedOperationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2SendExpirationNotificationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob;
use Fireball\VpnManagerV2\Repositories\SettingsRepository;
use Fireball\VpnManagerV2\Services\SettingsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$plugin = db()->query(
    'SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1',
    ['vpn-manager-v2']
)->getOne();
$assert(is_array($plugin) && $plugin['version'] === '0.12.0' && $plugin['status'] === 'active',
    'Plugin metadata is not active at Stage 12.');

$oldPlugin = db()->query(
    'SELECT slug, status FROM plugins WHERE slug = ? LIMIT 1',
    ['vpn-manager']
)->getOne();
$assert(is_array($oldPlugin), 'The old VPN Manager installation record is missing.');

$migrationFiles = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
sort($migrationFiles);
$applied = db()->query(
    'SELECT migration FROM plugin_migrations WHERE plugin_slug = ? ORDER BY migration',
    ['vpn-manager-v2']
)->get() ?: [];
$assert(count($migrationFiles) === 4 && array_column($applied, 'migration') === $migrationFiles,
    'Stage 12 migration journal is inconsistent.');

$tables = db()->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'vpn_v2_%'
     ORDER BY TABLE_NAME"
)->get() ?: [];
$assert(count($tables) === 8 && in_array('vpn_v2_notifications', array_column($tables, 'TABLE_NAME'), true),
    'Stage 12 V2 table set is incomplete.');

$trafficColumn = db()->query(
    "SHOW COLUMNS FROM vpn_v2_subscriptions LIKE 'traffic_used_bytes'"
)->getOne();
$assert(is_array($trafficColumn) && str_contains(strtolower((string)$trafficColumn['Type']), 'bigint')
    && (string)$trafficColumn['Default'] === '0', 'Subscription traffic aggregate column is invalid.');

$dedupeIndex = db()->query(
    "SHOW INDEX FROM vpn_v2_notifications WHERE Key_name = 'uq_vpn_v2_notifications_dedupe'"
)->get() ?: [];
$assert(array_column($dedupeIndex, 'Column_name') === [
    'subscription_id', 'notification_type', 'occurrence_key', 'channel',
], 'Notification dedupe unique index is invalid.');

$foreignKeys = db()->query(
    "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_notifications'
       AND REFERENCED_TABLE_NAME IS NOT NULL"
)->get() ?: [];
$foreignKeyMap = [];
foreach ($foreignKeys as $foreignKey) {
    $foreignKeyMap[(string)$foreignKey['COLUMN_NAME']] = (string)$foreignKey['REFERENCED_TABLE_NAME'];
}
$assert(($foreignKeyMap['subscription_id'] ?? '') === 'vpn_v2_subscriptions'
    && ($foreignKeyMap['user_id'] ?? '') === 'users', 'Notification foreign keys are invalid.');

$jobs = apply_filters('vpn_manager_v2_jobs', []);
$expectedClasses = [
    VpnV2SyncTrafficJob::class,
    VpnV2CheckExpirationsJob::class,
    VpnV2CheckTrafficLimitsJob::class,
    VpnV2SendExpirationNotificationsJob::class,
    VpnV2RetryFailedOperationsJob::class,
];
$assert(count($jobs) === 5 && array_values(array_diff($expectedClasses, array_column($jobs, 'class'))) === [],
    'The five Stage 12 jobs are not registered through the plugin hook.');
foreach ($jobs as $job) {
    $assert(isset($job['schedule']) && method_exists((string)$job['class'], 'handle'),
        'A registered job has no schedule or handle method.');
}

$settings = (new SettingsService())->current();
$stored = (new SettingsRepository())->stored();
foreach ([
    'retry_failed_operations',
    'notifications_profile_enabled',
    'notifications_email_enabled',
    'notify_expiration_3_days',
    'notify_expiration_day',
    'notify_traffic_80',
    'notify_traffic_100',
    'notify_provisioned',
    'notify_critical_errors',
] as $key) {
    $assert(array_key_exists($key, $settings) && array_key_exists($key, $stored),
        'Stage 12 setting is not persistent: ' . $key);
}

$settingsHtml = plugin_view('vpn-manager-v2', 'admin/settings', FireballPluginVpnManagerV2::viewData('settings', [
    'title' => FireballPluginVpnManagerV2::t('vpn_manager_v2_settings_title'),
    'settings' => $settings,
    'templateVariables' => Fireball\VpnManagerV2\Validators\SettingsValidator::TEMPLATE_VARIABLES,
]), false);
$assert(str_contains($settingsHtml, 'name="notify_traffic_80"')
    && str_contains($settingsHtml, 'name="notifications_email_enabled"')
    && !str_contains(strtolower($settingsHtml), 'cdn.'),
    'Stage 12 settings controls are missing or reference a CDN.');

echo json_encode([
    'status' => 'ok',
    'plugin' => $plugin,
    'old_plugin_status' => (string)$oldPlugin['status'],
    'migration_count' => count($migrationFiles),
    'table_count' => count($tables),
    'jobs' => array_keys($jobs),
    'settings_count' => count($settings),
    'dedupe_index' => array_column($dedupeIndex, 'Column_name'),
    'foreign_keys' => $foreignKeyMap,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
