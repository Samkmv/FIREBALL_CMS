<?php
// Real CMS/plan templates with isolated test data. No CMS bootstrap or live database.
require __DIR__ . '/plan_external_sources_unit.php';
function return_translation(string $key): string {
    static $lang; $lang ??= require dirname(__DIR__, 3) . '/app/Languages/ru.php'; return $lang[$key] ?? $key;
}
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
$uri = 'vless://00000000-0000-4000-8000-000000000017@vpn.example.test:443?encryption=none&security=tls&type=tcp#Shared';
$service = new \Fireball\VpnManagerV2\Services\ExternalVpnSourceService(forPlan: true, fetcher: static fn() => base64_encode($uri));
$service->attachConnection(1, $uri, 'Прямое подключение <img src=x onerror="window.leaked=true">', 1);
$service->attachSubscription(1, 'https://subscriptions.example.test/private-token', 'Внешняя подписка', 1);
$externalSources = $service->itemsForParent(1);
$externalSources[1]['sync_status'] = 'sync_error';
$externalSources[1]['last_error'] = 'Не удалось обновить источник. Используются последние проверенные конфигурации.';
$plan = ['id' => 1, 'name' => 'Тестовый тариф', 'duration_days' => 30, 'device_limit' => 2, 'ip_limit' => 1,
    'traffic_limit_bytes' => 1024 ** 3 * 10, 'is_active' => 1, 'description' => 'Существующие VPN-серверы и дополнительные источники.'];
$servers = [['id' => 1, 'name' => 'Германия', 'code' => 'de', 'is_enabled' => 1]];
$inbounds = [['id' => 2, 'server_id' => 1, 'name' => 'Reality', 'server_is_enabled' => 1, 'is_enabled' => 1,
    'status' => 'active', 'protocol' => 'vless', 'network' => 'tcp', 'security' => 'reality', 'allowed_flows' => ['xtls-rprx-vision']]];
$selectedNodes = [['server_id' => 1, 'inbound_id' => 2, 'sort_order' => 0, 'flow_override' => null]];
if (in_array('--create', $argv, true)) $plan = null;
ob_start(); require dirname(__DIR__) . '/views/admin/plan-form.php'; $content = ob_get_clean();
echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head><body class="fb-admin-body"><main class="container py-4">' . $content . '</main><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/plugins/vpn-manager-v2/assets/vpn-manager-v2.js"></script></body></html>';
