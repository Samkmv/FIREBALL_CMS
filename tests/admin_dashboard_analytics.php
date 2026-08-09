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

echo json_encode([
    'status' => 'ok',
    'charts' => ['traffic', 'sources', 'devices', 'countries'],
    'range_persistence' => true,
    'traffic_summary' => true,
    'popular_pages' => true,
    'latest_visits' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
