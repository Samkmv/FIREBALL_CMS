<?php
declare(strict_types=1);

namespace FBL {
    final class Auth { public static bool $admin = true; public static function isAdmin(): bool { return self::$admin; } }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    $locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
    $scenario = $argv[2] ?? 'available';
    $translations = require __DIR__ . '/../app/Languages/' . $locale . '.php';
    function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function base_href(string $path = ''): string { return $path; }
    function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="test-only">'; }
    function return_translation(string $key): string { global $translations; return $translations[$key] ?? $key; }
    function print_translation(string $key): string { return return_translation($key); }
    function view(): object {
        return new class {
            public function renderPartial(string $path, array $data = []): string {
                return $path === 'admin/shell_open' ? '<header class="p-3"><h1>' . htmlSC($data['title']) . '</h1>' . $data['actions'] . '</header><main class="p-3">' : '</main>';
            }
        };
    }
    \FBL\Auth::$admin = $scenario !== 'nonadmin';
    $plugins = [];
    foreach (['one', 'two', 'three', 'current', 'older', 'invalid', 'uninstalled', 'unconfigured'] as $slug) {
        $eligible = in_array($slug, ['one', 'two', 'three', 'invalid', 'uninstalled', 'unconfigured'], true) && $scenario !== 'none';
        $plugins[] = [
            'slug' => $slug, 'name' => $slug === 'one' ? 'Plugin "one" <safe> & тест' : ucfirst($slug),
            'installed' => $slug !== 'uninstalled', 'valid' => $slug !== 'invalid',
            'description' => 'Fixture', 'status' => 'active', 'version' => '1.0.0', 'author' => 'Fixture',
            'error' => '', 'load_error' => '',
            'update' => ['configured' => $slug !== 'unconfigured', 'update_available' => $eligible,
                'remote_version' => $eligible ? '2.0.0' : '1.0.0', 'source_older' => $slug === 'older'],
        ];
    }
    echo '<!doctype html><html data-bs-theme="dark"><body>';
    require __DIR__ . '/../app/Views/themes/default/admin/plugins.php';
    echo '</body></html>';
}
