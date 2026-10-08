<?php
declare(strict_types=1);
namespace FBL {
    class Language { public static function get(string $key): string { return $key; } }
    class Localization {
        public static function currentLocale(): string { return $GLOBALS['toyLocale'] ?? 'ru'; }
        public static function localeCandidates(string $code, array $fallback): array { return [$code]; }
    }
}
namespace App\Services {
    class NotificationService {
        public static array $messages = [];
        public static function createForAdmins(array $payload): array { self::$messages[] = $payload; return ['sent' => 1]; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require_once dirname(__DIR__, 2) . '/core/Plugins/PluginInterface.php';
    require_once dirname(__DIR__, 2) . '/plugins/toy-car-rental/Plugin.php';
    $GLOBALS['toySettings'] = [];
    function plugin_setting(string $slug, string $key, mixed $default = null): mixed { return $GLOBALS['toySettings'][$key] ?? $default; }
    function plugin_setting_set(string $slug, string $key, mixed $value): void { $GLOBALS['toySettings'][$key] = $value; }
    function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function base_href(string $path): string { return ($GLOBALS['toyLocale'] === 'ru' ? '' : '/' . $GLOBALS['toyLocale']) . $path; }
    function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="toy-fixture">'; }
    function current_url_with_query(): string { return base_href('/admin/toy-rental/rides'); }
    function return_translation(string $key): string {
        $translations = require dirname(__DIR__, 2) . '/app/Languages/' . $GLOBALS['toyLocale'] . '.php';
        return (string)($translations[$key] ?? $key);
    }
    function print_translation(string $key): void { echo htmlSC(return_translation($key)); }
    function plugin_view(string $slug, string $view, array $data = [], bool $layout = true): string {
        extract($data, EXTR_SKIP); ob_start(); require dirname(__DIR__, 2) . '/plugins/toy-car-rental/views/' . $view . '.php'; return ob_get_clean();
    }
    function view(): object { return new class {
        public function renderPartial(string $view, array $data = []): string {
            if (!in_array($view, ['admin/partials/table', 'admin/partials/responsive_table_cards'], true)) return '';
            extract($data, EXTR_SKIP); ob_start();
            require dirname(__DIR__, 2) . '/app/Views/themes/default/' . $view . '.php';
            return ob_get_clean();
        }
    }; }
    function add_filter(string $name, callable $handler, int $priority = 10): void { $GLOBALS['toyFilters'][$name] = $handler; }
    function fireball_event(string $name, array $data): void { $GLOBALS['toyEvents'][] = $name; }
    function log_error_details(string $message, array $context = [], ?Throwable $error = null): void { throw new RuntimeException($message, 0, $error); }
}
