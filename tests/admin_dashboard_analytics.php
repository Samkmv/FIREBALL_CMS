<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function dashboard_analytics_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$dashboard = (string)file_get_contents($root . '/app/Views/themes/default/admin/dashboard.php');
$service = (string)file_get_contents($root . '/app/Services/AnalyticsService.php');
$script = (string)file_get_contents($root . '/public/assets/default/js/admin-analytics.js');
$styles = (string)file_get_contents($root . '/public/assets/default/css/admin-ui.css');

foreach (['traffic', 'sources', 'devices', 'countries'] as $chart) {
    dashboard_analytics_assert(
        str_contains($dashboard, 'data-analytics-chart="' . $chart . '"'),
        'Dashboard chart is missing: ' . $chart
    );
}

dashboard_analytics_assert(str_contains($dashboard, 'fb-traffic-stat-grid'), 'Traffic summary cards are missing.');
dashboard_analytics_assert(str_contains($dashboard, 'admin_analytics_pages_title'), 'Popular pages summary is missing.');
dashboard_analytics_assert(str_contains($dashboard, 'admin_analytics_latest_title'), 'Latest visits summary is missing.');
dashboard_analytics_assert(str_contains($dashboard, 'data-analytics-range-label'), 'The selected range has no visible label target.');
dashboard_analytics_assert(str_contains($script, 'rangeStorageKey'), 'The selected analytics range is not persisted.');
dashboard_analytics_assert(str_contains($script, 'syncRangeUi'), 'The range control is not synchronized with the chart.');
dashboard_analytics_assert(str_contains($styles, '.fb-traffic-stat-grid'), 'Traffic summary cards have no responsive layout.');
dashboard_analytics_assert(str_contains($service, 'DASHBOARD_TABLE_LIMIT = 15'), 'Dashboard analytics tables are not limited to 15 records.');
dashboard_analytics_assert(str_contains($dashboard, 'fb-dashboard-recent-pages'), 'Recent pages card has no dashboard layout class.');
dashboard_analytics_assert(str_contains($dashboard, 'fb-dashboard-status-pages-row'), 'System status and recent pages have no shared dashboard row.');
dashboard_analytics_assert(str_contains($styles, '.fb-dashboard-system-card'), 'System status card has no narrow-column dashboard style.');
dashboard_analytics_assert(str_contains($styles, '.fb-dashboard-recent-pages'), 'Recent pages card has no wide dashboard style.');
dashboard_analytics_assert(str_contains($styles, 'max-height: none'), 'Dashboard analytics tables still hide rows behind a height limit.');

echo json_encode([
    'status' => 'ok',
    'charts' => ['traffic', 'sources', 'devices', 'countries'],
    'range_persistence' => true,
    'traffic_summary' => true,
    'popular_pages' => true,
    'latest_visits' => true,
    'table_limit' => 15,
    'wide_recent_pages' => true,
    'narrow_system_status' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
