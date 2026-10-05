<?php
declare(strict_types=1);

// CLI-only fixture: real profile partials and translations, no application DB/session.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$locale = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$section = $argv[2] ?? 'overview';
$fallback = ($argv[3] ?? '') === 'fallback';
$translations = array_replace(require $root . '/app/Languages/' . $locale . '.php', require $root . '/app/Languages/' . $locale . '/auth/profile.php');
$_SERVER['REQUEST_URI'] = ($locale === 'ru' ? '' : '/' . $locale) . ($argv[4] ?? '/profile');
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function base_href(string $path): string { return ($GLOBALS['locale'] === 'ru' ? '' : '/' . $GLOBALS['locale']) . $path; }
function get_user_avatar(mixed $avatar, string $size): string { return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="92" height="92"><circle cx="46" cy="46" r="46" fill="gray"/></svg>'); }
function get_user_role_label(string $role): string { return 'User'; }
function render_public_verified_badge(string $role): string { return ''; }
function apply_filters(string $name, mixed $value, mixed ...$args): mixed {
    if ($name !== 'profile_menu') return $value;
    return [
        ['href' => base_href('/account/subscription'), 'label' => return_translation('auth_profile_active_subscriptions'), 'icon' => 'ci-credit-card'],
        ['href' => base_href('/profile/vpn-v2'), 'label' => 'VPN', 'icon' => 'ci-server'],
    ];
}
function check_admin(): bool { return true; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function old(string $key): string { return ''; }
function get_validation_class(string $key): string { return ''; }
function get_errors(string $key): string { return ''; }
function view(): object { return new class { public function renderPartial(string $name, array $data): string { return ''; } }; }
$renderer = new class($root, $fallback) {
    public function __construct(private string $root, private bool $fallback) {}
    public function partial(string $name, array $data): string {
        if ($name === 'password_field') return ''; // Unrelated form widget needs a real session.
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->root . ($this->fallback ? '/app/Views/themes/default/' : '/themes/default/partials/') . $name . '.php';
        return ob_get_clean();
    }
};
$isSettings = $section !== 'overview';
$settingsSection = in_array($section, ['information', 'security', 'notifications'], true) ? $section : 'information';
echo $renderer->partial('auth/profile', [
    'is_settings' => $isSettings, 'settings_section' => $settingsSection,
    'user' => ['id' => 7, 'name' => 'Fixture User', 'login' => 'fixture', 'email' => 'fixture@example.test', 'role' => 'user', 'created_at' => '2026-01-01'],
    'push_status' => ['pwa_enabled' => true, 'global_enabled' => true, 'vapid_ready' => true, 'secure_context' => true, 'user_enabled' => true, 'active_subscriptions' => 2],
]);
