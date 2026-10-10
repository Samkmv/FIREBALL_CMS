<?php
// Real settings/access templates with isolated data; no DB or network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fixtureLang = in_array($argv[2] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[2] : 'ru';
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $value): string { return $value; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            return $name === 'admin/shell_open' ? '<main class="container py-4"><h1 class="h3">' . htmlSC($data['title']) . '</h1>' : '</main>';
        }
    };
}
final class FireballPluginVpnManagerV2 {
    public static function t(string $key): string {
        static $translations;
        $translations ??= require dirname(__DIR__) . '/lang/' . $GLOBALS['fixtureLang'] . '.php';
        return $translations[$key] ?? $key;
    }
}
spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
$settings = array_replace(\Fireball\VpnManagerV2\Services\SettingsService::defaults(), [
    'smart_connect_enabled' => true, 'smart_connect_happ_enabled' => true,
    'smart_connect_happ_provider_id' => 'fixture-provider', 'smart_connect_singbox_enabled' => true,
    'smart_connect_mode' => 'failover', 'smart_connect_health_enabled' => true,
]);
$smartServers = [['id' => 1, 'name' => 'Нидерланды · Основной сервер с длинным названием'], ['id' => 2, 'name' => '<img src=x onerror="window.injected=true">']];
$serverHealth = [['server_id' => 1, 'server_name' => 'Нидерланды', 'state' => 'healthy',
    'last_check_at' => '2026-10-10 12:00:00', 'last_success_at' => '2026-10-10 12:00:00', 'consecutive_failures' => 0,
    'snapshot_json' => '{"panel":"online","xray":"running","load":{"cpu":{"percent":35}},"ports":[{"port":443,"transport":"ws","tcp":"open"}]}']];
$templateVariables = \Fireball\VpnManagerV2\Validators\SettingsValidator::TEMPLATE_VARIABLES;
$healthReady = true;
$mailEnabled = true;
echo '<!doctype html><html lang="' . htmlSC($fixtureLang) . '" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
foreach (['css/theme.min.css', 'css/style.css', 'css/admin-ui.css', 'icons/cartzilla-icons.min.css'] as $asset) {
    echo '<link rel="stylesheet" href="/assets/default/' . $asset . '">';
}
echo '</head><body class="fb-admin-body">';
if (($argv[1] ?? 'settings') === 'settings') {
    require dirname(__DIR__) . '/views/admin/settings.php';
} else {
    $smartConnectSettings = $settings;
    if (($argv[1] ?? '') === 'disabled') { $smartConnectSettings['smart_connect_enabled'] = false; }
    $subscriptionUrl = 'https://vpn.example.test/vpn-v2/subscription/' . str_repeat('a', 64);
    echo '<main class="container py-4"><div class="mx-auto" style="max-width:720px">';
    require dirname(__DIR__) . '/views/partials/smart-connect-access.php';
    echo '</div></main>';
}
echo '<script src="/plugins/vpn-manager-v2/assets/vpn-manager-v2.js"></script></body></html>';
