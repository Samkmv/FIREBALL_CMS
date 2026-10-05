<?php
declare(strict_types=1);
// Real dashboard template, isolated data and shell dimensions; no application DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$translations = require $root . '/app/Languages/' . $locale . '.php';
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function base_href(string $path): string { return ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $path; }
function get_user(): array { return ['id' => 1, 'role' => 'creator', 'name' => 'Fixture']; }
function check_creator(): bool { return true; }
function apply_filters(string $name, mixed $value, mixed ...$args): mixed { return $value; }
function apply_filters_safe(string $name, mixed $value, mixed ...$args): mixed { return $value; }
function view(): object { return new class {
    public function renderPartial(string $name, array $data = []): string {
        if ($name === 'admin/shell_open') {
            return '<div class="fb-admin" data-fb-admin data-admin-shell><aside class="fb-sidebar d-none d-lg-flex"></aside><div class="fb-admin-main"><main class="fb-content"><div class="fb-page-content">';
        }
        return '</div></main></div></div>';
    }
}; }
$stats = ['users' => 3, 'posts' => 15, 'categories' => 7, 'pages' => 5];
$traffic = [];
foreach ([7, 30, 90] as $range) {
    $labels = [];
    $values = [];
    for ($i = 0; $i < $range; $i++) {
        $labels[] = date('Y-m-d', strtotime('2026-10-05') - ($range - $i - 1) * 86400);
        $values[] = 200 + ($i * 67) % 601;
    }
    $traffic[(string)$range] = ['labels' => $labels, 'values' => $values];
}
$rows = static fn(array $labels): array => array_map(static fn(string $label, int $index): array => ['label' => $label, 'total' => max(1, 1000 - $index * 80)], $labels, array_keys($labels));
$analytics_dashboard = [
    'cards' => ['today_visits' => 291, 'today_unique' => 122, 'visits_7' => 3677, 'visits_30' => 15124, 'mobile_percent' => 72.3, 'desktop_percent' => 13.6],
    'traffic' => $traffic,
    'sources' => $rows(['Direct', 'Yandex', 'Google', 'Other']),
    'devices' => $rows(['Android', 'iOS', 'Bot', 'Windows', 'macOS', 'Linux', 'Other', 'iPadOS']),
    'countries' => $rows(['Россия', 'США', 'Китай', 'Германия', 'Нидерланды', 'Грузия', 'Финляндия', 'Украина', 'Франция', 'United Kingdom', 'Bosnia and Herzegovina', 'United Arab Emirates']),
    'pages' => [], 'latest' => [],
];
for ($i = 0; $i < 15; $i++) {
    $path = '/posts/' . ($i % 2 ? str_repeat('long-path-segment-', 8) : 'sample-' . $i);
    $analytics_dashboard['pages'][] = ['label' => $path, 'views' => 1000 - $i];
    $analytics_dashboard['latest'][] = ['created_at' => '2026-10-05 18:30:00', 'country' => $i % 2 ? 'United Arab Emirates' : 'Netherlands', 'device_type' => 'iPadOS', 'browser' => 'Mobile Safari', 'current_page' => $path];
}
if (($argv[2] ?? '') === 'empty') {
    $analytics_dashboard['sources'] = [];
    $analytics_dashboard['devices'] = [];
    $analytics_dashboard['countries'] = [];
}
require $root . '/app/Views/themes/default/admin/dashboard.php';
