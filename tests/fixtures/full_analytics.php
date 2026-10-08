<?php
declare(strict_types=1);
// Real analytics view and shared table/pagination partials, with isolated fake data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
parse_str($argv[2] ?? '', $_GET);
$translations = require $root . '/app/Languages/' . $locale . '.php';
define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function base_href(string $path): string { return ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $path; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function abort(string $error = '', int $code = 404): never { throw new RuntimeException($error ?: 'Aborted', $code); }
function request(): object { return new class {
    public string $uri;
    public function __construct() { $this->uri = base_href('/admin/analytics') . '?' . http_build_query($_GET); }
    public function get(string $key, mixed $default = null): mixed { return $_GET[$key] ?? $default; }
}; }
function view(): object { return new class {
    public function renderPartial(string $name, array $data = []): string {
        if ($name === 'admin/shell_open') {
            return '<div class="fb-admin" data-admin-shell><aside class="fb-sidebar d-none d-lg-flex"></aside><div class="fb-admin-main"><main class="fb-content"><header class="fb-page-header"><div class="fb-page-heading"><h1 class="fb-page-title">' . htmlSC($data['title']) . '</h1><p class="fb-page-subtitle">' . htmlSC($data['subtitle']) . '</p></div><div class="fb-page-actions">' . $data['actions'] . '</div></header><div class="fb-page-content ' . htmlSC($data['container_class'] ?? '') . '">';
        }
        if ($name === 'admin/shell_close') return '</div></main></div></div>';
        extract($data, EXTR_SKIP);
        ob_start();
        require $GLOBALS['root'] . '/app/Views/themes/default/' . $name . '.php';
        return ob_get_clean();
    }
}; }
$rows = [];
for ($i = 0; $i < 126; $i++) {
    $page = $i % 45;
    $rows[] = [
        'id' => $i + 1, 'created_at' => date('Y-m-d H:i:s', strtotime('2026-10-08 18:00:00') - $i * 60),
        'country' => $i % 2 ? 'United Arab Emirates' : 'Netherlands',
        'device_type' => $i % 2 ? 'Mobile' : 'Desktop', 'os' => $i % 2 ? 'iPadOS' : 'macOS',
        'browser' => $i % 2 ? 'Mobile Safari' : 'Firefox', 'source' => $i % 2 ? 'Yandex' : 'Direct',
        'current_page' => '/posts/' . ($page === 0 ? '<img src=x onerror=alert(1)>' : ($page % 5 === 0 ? str_repeat('long-path-', 12) : 'sample')) . '-' . $page,
    ];
}
$filters = array_merge(['period' => '7', 'search' => '', 'country' => '', 'device_type' => '', 'browser' => '', 'source' => ''], $_GET);
$rows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
    foreach (['country', 'device_type', 'browser', 'source'] as $field) {
        if ($filters[$field] !== '' && $row[$field] !== $filters[$field]) return false;
    }
    return $filters['search'] === '' || str_contains($row['current_page'], $filters['search']);
}));
if (($argv[3] ?? '') === 'empty') $rows = [];
$pages = [];
foreach ($rows as $row) {
    $path = $row['current_page'];
    $pages[$path] ??= ['label' => $path, 'views' => 0];
    $pages[$path]['views']++;
}
$paginate = static function (array $items, string $prefix, array $sortMap, string $defaultSort): array {
    $sort = $_GET[$prefix . '_sort'] ?? $defaultSort;
    $field = $sortMap[$sort] ?? $sortMap[$defaultSort];
    $direction = ($_GET[$prefix . '_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
    usort($items, static fn(array $a, array $b): int => ($a[$field] <=> $b[$field]) * ($direction === 'asc' ? 1 : -1));
    $pagination = new FBL\Pagination(count($items), 20, 2, 7, 'pagination/base', $prefix . '_page');
    return ['items' => array_slice($items, $pagination->getOffset(), 20), 'total' => count($items), 'pagination' => $pagination, 'sort' => $sort, 'direction' => $direction];
};
$analytics = [
    'filters' => $filters,
    'filter_options' => [
        'countries' => [['value' => 'Netherlands'], ['value' => 'United Arab Emirates']],
        'devices' => [['value' => 'Desktop'], ['value' => 'Mobile']],
        'browsers' => [['value' => 'Firefox'], ['value' => 'Mobile Safari']],
        'sources' => [['value' => 'Direct'], ['value' => 'Yandex']],
    ],
    'pages' => $paginate(array_values($pages), 'pages', ['page' => 'label', 'views' => 'views'], 'views'),
    'visits' => $paginate($rows, 'visits', ['created_at' => 'created_at', 'country' => 'country', 'device' => 'device_type', 'browser' => 'browser', 'source' => 'source', 'page' => 'current_page'], 'created_at'),
];
$geoip_status = ['state' => 'enabled'];
require $root . '/app/Views/themes/default/admin/analytics.php';
