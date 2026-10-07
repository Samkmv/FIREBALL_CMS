<?php
// Isolated fixtures: no CMS database, panel credentials or remote requests.
namespace Fireball\VpnManagerV2\Repositories {
    function db(): object { return $GLOBALS['testDatabase']; }
    class ServerRepository {
        public array $server = ['id' => 1, 'name' => 'Test', 'status' => 'offline', 'is_enabled' => 1];
        public function findWithSecrets(int $id): array { return $this->server; }
    }
}
namespace Fireball\VpnManagerV2\Services {
    class ServerSecretService { public function clientConfig(array $server, int $connect, int $timeout): array { return []; } }
}
namespace Fireball\VpnManagerV2\Support {
    class Permissions { const VIEW = 'view'; public static function authorize(string $permission): void {} }
}
namespace FBL { function request(): object { return $GLOBALS['testRequest']; } function view(): object { return \view(); } }
namespace {
    $root = dirname(__DIR__, 3);
    define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
    function request(): object { return $GLOBALS['testRequest']; }
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
    function plugin_view(string $slug, string $name, array $data): string {
        $GLOBALS['capturedViewData'] = $data;
        return 'captured';
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
    $GLOBALS['testRequest'] = new class {
        public array $get = [];
        public string $uri = '/admin/plugins/vpn-manager-v2/operations';
        public function get(string $key, mixed $default = null): mixed { return $this->get[$key] ?? $default; }
    };
    $GLOBALS['testDatabase'] = new class {
        public int $total = 41;
        public string $status = 'completed';
        public string $sql = '';
        public function query(string $sql): self { $this->sql = $sql; return $this; }
        public function getColumn(): int { return $this->total; }
        public function get(): array {
            if (!preg_match('/ORDER BY id DESC LIMIT (\d+) OFFSET (\d+)/', $this->sql, $match)) throw new \RuntimeException('Expected stable SQL pagination');
            $rows = [];
            $end = min($this->total, (int)$match[2] + (int)$match[1]);
            for ($index = (int)$match[2]; $index < $end; $index++) {
                $rows[] = ['operation_id' => 'operation-' . ($this->total - $index), 'operation_type' => 'sync_server', 'source' => 'cms', 'status' => $this->status, 'processed_count' => 1, 'total_count' => 1, 'attempts' => 1, 'max_attempts' => 8, 'last_error' => '', 'updated_at' => '2026-10-01 23:00:00'];
            }
            return $rows;
        }
    };
    require $root . '/core/Pagination.php';
    foreach (['Repositories/OperationQueueRepository', 'Controllers/Admin/SyncController', 'Services/ServerMetricsService', 'Exceptions/VpnManagerV2Exception', 'Exceptions/ValidationException', 'Support/LocalizedValue', 'Support/AdminActionDropdown', 'Support/ProvisioningStatus'] as $class) require dirname(__DIR__) . '/src/' . $class . '.php';
    function check(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
    $controller = new \Fireball\VpnManagerV2\Controllers\Admin\SyncController();
    if (in_array('--render', $argv, true) || in_array('--overview', $argv, true)) {
        if (in_array('--empty', $argv, true)) $GLOBALS['testDatabase']->total = 0;
        if (in_array('--pending', $argv, true)) $GLOBALS['testDatabase']->status = 'pending';
        foreach ($argv as $argument) if (str_starts_with($argument, '--page=')) request()->get['page'] = substr($argument, 7);
        $controller->operations();
        $data = $GLOBALS['capturedViewData'];
        extract($data);
        if (in_array('--overview', $argv, true)) {
            $migrationStatus = ['is_ready' => true];
            $overview = ['available' => true, 'is_ready' => true, 'data' => [
                'servers' => ['active' => 3, 'total' => 3, 'errors' => 0],
                'subscriptions' => ['active' => 13, 'total' => 31, 'errors' => 0],
                'connections' => ['active' => 31, 'total' => 86, 'errors' => 2],
                'plans' => ['active' => 4, 'total' => 4],
            ]];
        }
        $servers = array_map(static fn($id) => ['id' => $id, 'name' => 'Server ' . $id, 'status' => 'online', 'is_enabled' => $id !== 3], [1, 2, 3]);
        ob_start();
        require dirname(__DIR__) . '/views/admin/' . (in_array('--overview', $argv, true) ? 'overview' : 'operations') . '.php';
        $content = ob_get_clean();
        echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head><body class="fb-admin-body"><main class="container py-4">' . $content . '</main></body></html>';
        exit;
    }
    foreach ([0, 1, 20, 21, 41, 105, 501] as $total) {
        $GLOBALS['testDatabase']->total = $total;
        $lastPage = max(1, (int)ceil($total / 20));
        foreach ([1, 2, $lastPage, 99999, -1] as $page) {
            request()->get['page'] = $page;
            request()->uri = '/admin/plugins/vpn-manager-v2/operations?page=' . $page;
            $controller->operations();
            $data = $GLOBALS['capturedViewData'];
            $current = max(1, min($lastPage, $page));
            check(count($data['operations']) === min(20, max(0, $total - ($current - 1) * 20)), 'Exactly 20 operations or the final remainder');
            check($data['pagination']['total_records'] === $total && $data['pagination']['current_page'] === $current, 'Pagination uses all records and clamps stale URLs');
            if ($data['operations']) check($data['operations'][0]['operation_id'] === 'operation-' . ($total - ($current - 1) * 20), 'Newest-first page boundary');
            $html = (string)$data['pagination'];
            check(str_contains($html, 'admin-pagination-nav'), 'Uses native CMS pagination');
        }
    }
    $repository = new \Fireball\VpnManagerV2\Repositories\ServerRepository();
    $service = new \Fireball\VpnManagerV2\Services\ServerMetricsService($repository, new \Fireball\VpnManagerV2\Services\ServerSecretService(), static fn() => new class { public function serverStatus(): array { return ['cpu' => 12, 'mem' => ['current' => 1, 'total' => 2]]; } });
    check($service->fetch(1)['server']['status'] === 'online', 'Successful live request replaces stale offline state in response');
    $failing = new \Fireball\VpnManagerV2\Services\ServerMetricsService($repository, new \Fireball\VpnManagerV2\Services\ServerSecretService(), static fn() => new class { public function serverStatus(): array { throw new \RuntimeException('unreachable'); } });
    try { $failing->fetch(1); throw new \LogicException('Failure must not return cached online data'); } catch (\RuntimeException $error) { check($error->getMessage() === 'unreachable', 'Failed request propagates for HTTP error handling'); }
    echo "PASS live metrics response and operations pagination: 0–501 records, boundaries and native CMS markup\n";
}
