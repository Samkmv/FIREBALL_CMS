<?php
// Isolated fixtures: no CMS database, panel credentials or remote requests.
namespace {
    function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function base_href(string $path): string { return $path; }
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
                if ($name === 'admin/shell_open' || $name === 'admin/shell_close') return '';
                extract($data);
                ob_start();
                require dirname(__DIR__, 3) . '/app/Views/themes/default/' . $name . '.php';
                return ob_get_clean();
            }
        };
    }
    class FireballPluginVpnManagerV2 {
        const SLUG = 'vpn-manager-v2';
        public static function t(string $key): string {
            static $translations;
            $translations ??= require dirname(__DIR__) . '/lang/ru.php';
            return $translations[$key] ?? $key;
        }
        public static function viewData(string $tab, array $data): array { return $data; }
    }

    require dirname(__DIR__) . '/src/Support/LocalizedValue.php';
    $title = 'Замена сервера'; $subtitle = 'Проверка и восстановление'; $tabs=[];
    $server=['id'=>1,'name'=>'Netherlands','last_error'=>null];
    $inbounds=[['id'=>20,'name'=>'Новая панель','remote_inbound_id'=>2,'protocol'=>'vless','port'=>443,'network'=>'ws','security'=>'none','status'=>'active','is_enabled'=>1]];
    $targets=[['id'=>10,'name'=>'Прежнее подключение','protocol'=>'vless','port'=>443,'network'=>'ws','security'=>'none','plan_count'=>2]];
    $subscriptions=[['id'=>11,'customer_name'=>'Ручной клиент','plan_name'=>'Основной','last_error'=>''], ['id'=>12,'customer_name'=>'Второй клиент','plan_name'=>'Основной','last_error'=>'']];
    $dependencies=['plan_nodes'=>2,'subscription_nodes'=>2];$recoveryOperations=[];
    $inspected=!in_array('--unchecked',$argv,true);
    ob_start(); require dirname(__DIR__) . '/views/admin/server-recovery.php'; $content=ob_get_clean();
    echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head><body class="fb-admin-body"><main class="container py-4">'.$content.'</main></body></html>';
}
