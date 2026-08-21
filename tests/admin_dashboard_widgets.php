<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function dashboard_widgets_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'admin_dashboard_widgets: ' . $message . PHP_EOL);
        exit(1);
    }
}

$controller = (string)file_get_contents($root . '/app/Controllers/AdminController.php');
$requests = (string)file_get_contents($root . '/app/Models/ContactRequest.php');
$dashboard = (string)file_get_contents($root . '/app/Views/themes/default/admin/dashboard.php');
$styles = (string)file_get_contents($root . '/public/assets/default/css/admin-ui.css');
$pluginFiles = [
    (string)file_get_contents($root . '/plugins/subscriptions/Plugin.php'),
    (string)file_get_contents($root . '/plugins/toy-car-rental/Plugin.php'),
    (string)file_get_contents($root . '/plugins/vpn-manager-v2/Plugin.php'),
];

dashboard_widgets_assert(str_contains($controller, 'getDashboardSummary(6)'), 'controller does not load the request summary');
dashboard_widgets_assert(str_contains($requests, 'public function getDashboardSummary'), 'contact request summary method is missing');
dashboard_widgets_assert(str_contains($requests, "SUM(status = 'new')"), 'request statuses are not aggregated in one query');
dashboard_widgets_assert(str_contains($dashboard, 'fb-dashboard-requests-card'), 'recent requests widget is missing');
dashboard_widgets_assert(str_contains($dashboard, 'fb-dashboard-content-card'), 'content summary widget is missing');
dashboard_widgets_assert(str_contains($dashboard, 'fb-dashboard-plugin-grid'), 'plugin widget area is missing');
dashboard_widgets_assert(str_contains($dashboard, "is_array(\$pluginWidget['metrics']"), 'structured plugin metrics are not supported');
dashboard_widgets_assert(str_contains($dashboard, "'plugins' => (array)(\$active_plugins ?? [])"), 'plugin widget context has no active plugin list');
dashboard_widgets_assert(
    preg_match('/\$statCards = \[(.*?)\];\s*\$statCards =/s', $dashboard, $statMatch) === 1,
    'top stat card configuration was not found'
);
$topStats = (string)($statMatch[1] ?? '');
dashboard_widgets_assert(str_contains($topStats, 'admin_stat_new_contacts'), 'new request counter is missing from the top row');
dashboard_widgets_assert(str_contains($topStats, 'admin_stat_contacts'), 'request counter is missing from the top row');
dashboard_widgets_assert(str_contains($topStats, 'admin_stat_users'), 'user counter is missing from the top row');
dashboard_widgets_assert(str_contains($topStats, 'admin_dashboard_update_version'), 'update version card is missing from the top row');
foreach (['admin_stat_pages', 'admin_stat_posts', 'admin_dashboard_active_plugins', 'admin_stat_support_kb_articles', 'admin_stat_support_faq'] as $duplicateKey) {
    dashboard_widgets_assert(!str_contains($topStats, $duplicateKey), "duplicate top card remains: {$duplicateKey}");
}
dashboard_widgets_assert(str_contains($styles, '.fb-dashboard-focus-grid'), 'work summary has no responsive layout');
dashboard_widgets_assert(str_contains($styles, '.fb-plugin-metric-grid'), 'plugin metrics have no layout styles');

foreach ($pluginFiles as $pluginFile) {
    dashboard_widgets_assert(
        str_contains($pluginFile, "add_filter('admin_dashboard_widgets'"),
        'an installed plugin does not register a dashboard widget'
    );
}

$translationKeys = [
    'admin_dashboard_requires_attention',
    'admin_dashboard_all_requests',
    'admin_dashboard_work_summary',
    'admin_dashboard_recent_requests',
    'admin_dashboard_content_summary',
    'admin_dashboard_plugin_widgets',
    'admin_dashboard_plugin_widgets_subtitle',
    'admin_dashboard_open_widget',
    'admin_dashboard_update_version',
    'admin_dashboard_update_available_version',
    'admin_dashboard_version_current',
    'admin_dashboard_check_updates',
];
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $language = require $root . '/app/Languages/' . $locale . '.php';
    foreach ($translationKeys as $key) {
        dashboard_widgets_assert(
            isset($language[$key]) && trim((string)$language[$key]) !== '',
            "missing {$key} translation for {$locale}"
        );
    }
}

echo json_encode([
    'status' => 'ok',
    'request_summary' => true,
    'content_summary' => true,
    'plugin_widgets' => 3,
    'locales' => 4,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
