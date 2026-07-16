<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$metadata = json_decode((string)file_get_contents(dirname(__DIR__) . '/plugin.json'), true);
$plugin = db()->query(
    'SELECT slug, version, status FROM plugins WHERE slug = ? LIMIT 1',
    ['vpn-manager-v2']
)->getOne();
$oldPlugin = db()->query(
    'SELECT slug, status FROM plugins WHERE slug = ? LIMIT 1',
    ['vpn-manager']
)->getOne();
$assert(is_array($metadata) && is_array($plugin)
    && (string)$plugin['version'] === (string)$metadata['version']
    && (string)$plugin['status'] === 'active', 'Installed V2 metadata is inconsistent.');
$assert(is_array($oldPlugin), 'The old VPN Manager installation disappeared.');

$migrationFiles = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
sort($migrationFiles);
$applied = db()->query(
    'SELECT migration FROM plugin_migrations WHERE plugin_slug = ? ORDER BY migration',
    ['vpn-manager-v2']
)->get() ?: [];
$assert(array_column($applied, 'migration') === $migrationFiles,
    'The V2 migration journal is inconsistent.');

$tables = db()->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'vpn_v2_%' ORDER BY TABLE_NAME"
)->get() ?: [];
$assert(count($tables) === 8, 'The V2 table set is incomplete.');

$uniqueIndexes = db()->query(
    "SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0
       AND TABLE_NAME IN ('vpn_v2_inbounds', 'vpn_v2_plan_nodes', 'vpn_v2_subscriptions', 'vpn_v2_notifications')
     GROUP BY TABLE_NAME, INDEX_NAME"
)->get() ?: [];
$uniqueMap = [];
foreach ($uniqueIndexes as $index) {
    $uniqueMap[(string)$index['TABLE_NAME'] . '.' . (string)$index['INDEX_NAME']] = (string)$index['columns_list'];
}
$assert(in_array('server_id,remote_inbound_id', $uniqueMap, true)
    && in_array('plan_id,server_id,inbound_id', $uniqueMap, true)
    && in_array('subscription_token', $uniqueMap, true)
    && in_array('subscription_id,notification_type,occurrence_key,channel', $uniqueMap, true),
    'A required V2 unique index is missing.');

$foreignKeys = (int)db()->query(
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'vpn_v2_%'
       AND REFERENCED_TABLE_NAME IS NOT NULL"
)->getColumn();
$assert($foreignKeys === 17, 'The V2 foreign-key set is incomplete.');

(new FireballPluginVpnManagerV2())->boot();
$menu = apply_filters('admin_menu', []);
$menuItem = array_values(array_filter($menu, static fn(array $item): bool =>
    (string)($item['href'] ?? '') === base_href('/admin/plugins/vpn-manager-v2')
));
$assert(count($menuItem) === 1 && ($menuItem[0]['icon'] ?? '') === 'ci-server'
    && ($menuItem[0]['label'] ?? '') === 'VPN V2', 'The V2 admin menu registration is invalid.');

$permissions = apply_filters('vpn_manager_v2_permissions', []);
$jobs = apply_filters('vpn_manager_v2_jobs', []);
$assert(count($permissions) === 8
    && Fireball\VpnManagerV2\Support\Permissions::allows(
        Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
        ['role' => 'admin']
    )
    && !Fireball\VpnManagerV2\Support\Permissions::allows(
        Fireball\VpnManagerV2\Support\Permissions::MANAGE_SUBSCRIPTIONS,
        ['role' => 'user']
    ), 'V2 permission registration or enforcement is invalid.');
$assert(count($jobs) === 5, 'The five V2 jobs are not registered.');

$adminRoutes = (string)file_get_contents(dirname(__DIR__) . '/routes/admin.php');
$publicRoutes = (string)file_get_contents(dirname(__DIR__) . '/routes/public.php');
$assert(!str_contains($adminRoutes, 'withoutCSRFToken')
    && substr_count($publicRoutes, 'withoutCSRFToken') === 1,
    'CSRF exemptions are broader than the public read-only endpoint.');

$v2Files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'js', 'css'], true)) {
        $v2Files[] = $file->getPathname();
    }
}
$externalCdn = false;
foreach ($v2Files as $file) {
    $source = (string)file_get_contents($file);
    if (preg_match('~https?://(?:cdn\.|cdnjs\.|unpkg\.|jsdelivr\.)~i', $source) === 1) {
        $externalCdn = true;
        break;
    }
}
$assert(!$externalCdn, 'VPN Manager V2 contains an external CDN reference.');

$settings = (new Fireball\VpnManagerV2\Services\SettingsService())->current();
$assert(count($settings) === count(Fireball\VpnManagerV2\Services\SettingsService::defaults()),
    'Persistent V2 settings are incomplete.');

echo json_encode([
    'status' => 'ok',
    'plugin' => $plugin,
    'old_plugin_status' => (string)$oldPlugin['status'],
    'migration_count' => count($migrationFiles),
    'table_count' => count($tables),
    'unique_indexes_checked' => 4,
    'foreign_key_count' => $foreignKeys,
    'permissions' => count($permissions),
    'jobs' => count($jobs),
    'external_cdn' => false,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
