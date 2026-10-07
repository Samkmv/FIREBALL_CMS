<?php
// Deterministic visual fixture using the real CMS template. No application bootstrap.
require __DIR__ . '/client_information_unit.php';
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function return_translation(string $key): string {
    static $translations;
    $translations ??= require dirname(__DIR__, 3) . '/app/Languages/ru.php';
    return $translations[$key] ?? $key;
}
function print_translation(string $key): void { echo return_translation($key); }
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            if (in_array($name, ['admin/shell_open', 'admin/shell_close'], true)) return '';
            extract($data);
            ob_start(); require dirname(__DIR__, 3) . '/app/Views/themes/default/' . $name . '.php';
            return ob_get_clean();
        }
    };
}
$subscription = ['id' => 14, 'user_id' => 1, 'user_name' => 'Тестовый клиент', 'user_login' => 'fixture',
    'client_display_name' => 'Новое имя клиента',
    'user_email' => 'fixture@example.test', 'plan_id' => 1, 'plan_name' => 'Тестовый тариф', 'status' => 'active',
    'starts_at' => '2026-10-01 12:00:00', 'expires_at' => '2026-11-01 12:00:00',
    'traffic_used_bytes' => 1024 ** 3 * 8, 'traffic_limit_bytes' => 1024 ** 3 * 10,
    'device_limit' => 2, 'ip_limit' => 1, 'token_preview' => 'demo…demo', 'revision' => 1];
$nodes = [[
    'id' => 7, 'subscription_id' => 14, 'status' => 'active', 'server_id' => 1, 'server_name' => 'Германия',
    'inbound_id' => 2, 'inbound_name' => 'Основной Reality', 'remote_inbound_id' => 42,
    'protocol' => 'vless', 'network' => 'tcp', 'security' => 'reality', 'flow' => 'xtls-rprx-vision',
    'client_email' => 'fixture@example.test', 'remote_client_preview' => '12345678…5678',
    'desired_enabled' => 1, 'sync_status' => 'synced', 'traffic_sync_status' => 'synced',
    'traffic_used_bytes' => 1024 ** 3 * 8, 'traffic_limit_bytes' => 1024 ** 3 * 10,
    'upload_bytes' => 1024 ** 3, 'download_bytes' => 1024 ** 3 * 7,
    'traffic_synced_at' => '2026-10-07 12:00:00', 'last_seen_remote_at' => '2026-10-07 12:00:00',
]];
if (in_array('--unknown', $argv, true)) {
    $subscription['traffic_used_bytes'] = 0;
    $nodes[0]['traffic_synced_at'] = null; $nodes[0]['traffic_sync_status'] = 'failed';
    $nodes[0]['traffic_used_bytes'] = 0;
}
if (in_array('--plan-external', $argv, true)) {
    $planExternalSources = [[
        'id' => 3, 'plan_id' => 1, 'name' => 'Общая внешняя подписка', 'source_type' => 'subscription_url',
        'source_preview' => 'https://subscriptions.example.test/…', 'config_count' => 4,
        'is_enabled' => 1, 'sync_status' => 'synced', 'last_sync_at' => '2026-10-07 12:00:00', 'last_error' => null,
    ]];
}
ob_start();
if (in_array('--partial', $argv, true)) {
    $subscriptionId = 14; require dirname(__DIR__) . '/views/admin/partials/subscription-client-info.php';
} else {
    require dirname(__DIR__) . '/views/admin/subscription-show.php';
}
$content = ob_get_clean();
echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head><body class="fb-admin-body"><main class="container py-4">' . $content . '</main><script src="/plugins/vpn-manager-v2/assets/vpn-manager-v2.js"></script></body></html>';
