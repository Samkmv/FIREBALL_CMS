<?php
// Isolated view fixtures: no database, panel credentials or external requests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Moscow');
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function return_translation(string $key): string { return $key; }
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            return $name === 'admin/shell_open' ? '<main class="container py-4"><h1 class="h3">' . htmlSC($data['title']) . '</h1>' : '</main>';
        }
    };
}
class FireballPluginVpnManagerV2 {
    public static function t(string $key): string {
        static $translations;
        $translations ??= require dirname(__DIR__) . '/lang/ru.php';
        return $translations[$key] ?? $key;
    }
}
foreach (['TrafficFormatter', 'AdminTableState', 'ProvisioningStatus'] as $class) {
    require dirname(__DIR__) . '/src/Support/' . $class . '.php';
}
$mode = $argv[1] ?? 'create';
$plans = [
    ['id'=>1, 'name'=>'Один месяц', 'duration_days'=>30, 'device_limit'=>3, 'ip_limit'=>0, 'traffic_limit_bytes'=>10*(1024**3), 'node_count'=>2],
    ['id'=>2, 'name'=>'Три месяца для всех устройств', 'duration_days'=>90, 'device_limit'=>6, 'ip_limit'=>0, 'traffic_limit_bytes'=>null, 'node_count'=>2],
];
$users = [['id'=>1,'name'=>'Тестовый пользователь','email'=>'fixture@example.test']];
$defaultStartsAt = '2026-10-03T09:00';
$preselectedUserId = 1;
$subscription = $mode === 'create' ? [] : ['id'=>1,'plan_id'=>1,'plan_name'=>'Один месяц','status'=>'active',
    'starts_at'=>'2026-10-01 10:00:00','expires_at'=>$mode==='lifetime'?null:'2026-11-02 10:00:00',
    'traffic_limit_bytes'=>10*(1024**3),'device_limit'=>3,'ip_limit'=>0,'internal_comment'=>''];
if ($mode === 'inactive-plan') {
    $plans[0]['unavailable'] = true;
    $plans[0]['duration_days'] = 0;
}
$trafficInput = ['value'=>'10','unit'=>'gb'];
$title = FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_' . ($mode==='create'?'create':'edit') . '_title');
if ($mode !== 'create') { $title = sprintf($title, 1); }
echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
foreach (['css/theme.min.css','css/style.css','css/admin-ui.css','icons/cartzilla-icons.min.css'] as $asset) {
    echo '<link rel="stylesheet" href="/assets/default/' . $asset . '">';
}
echo '</head><body class="fb-admin-body">';
require dirname(__DIR__) . '/views/admin/subscription-' . ($mode==='create'?'form':'edit') . '.php';
echo '</body></html>';
