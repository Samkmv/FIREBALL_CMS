<?php
/** Standalone regression checks; no database or real account changes. */
namespace FBL {
    class Auth { public static function setUser(): void {} }
}
namespace App\Models {
    class User {
        public array $saved = [];
        public array $errors = [];
        public array $user = ['id' => 7, 'name' => 'Example', 'login' => 'example', 'email' => 'example@example.test', 'role' => 'user'];
        public function findById(int $id): array { return $this->user; }
        public function validateProfileUpdate(array $data, int $id): array { return $this->errors; }
        public function updateProfile(int $id, array $data): void { $this->saved = $data; }
    }
    class SiteSetting {}
    class SecurityLog {}
}
namespace App\Services {
    class TwoFactorService {
        public function provisioningUri(...$args): string { return 'otpauth://test'; }
        public function qrCodeDataUri(string $uri): string { return ''; }
    }
    class PwaService { public function pushStatusForUser(int $id): array { return []; } }
}
namespace App\Controllers {
    class BaseController {}
}
namespace {
    require __DIR__ . '/../app/Controllers/AuthController.php';
    class Redirect extends RuntimeException {}
    class TestSession {
        public array $data = [];
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value): void { $this->data[$key] = $value; }
        public function remove($key): void { unset($this->data[$key]); }
        public function setFlash(...$args): void {}
        public function regenerateId(): void {}
    }
    class TestRequest {
        public array $data = [];
        public array $query = [];
        public bool $post = false;
        public function isPost(): bool { return $this->post; }
        public function post($key, $default = null) { return $this->data[$key] ?? $default; }
        public function get($key, $default = null) { return $this->query[$key] ?? $default; }
    }
    class TestController extends \App\Controllers\AuthController {
        public int $csrfChecks = 0;
        public function __construct(public \App\Models\User $model) { $this->users = $model; }
        protected function assertValidCsrfToken(string $redirectPath, array $oldData = []): void { $this->csrfChecks++; }
    }
    function session() { return $GLOBALS['session']; }
    function request() { return $GLOBALS['request']; }
    function get_user() { return ['id' => 7]; }
    function response() { return new class { public function redirect($path) { throw new Redirect($path); } }; }
    function app() { return new class { public function regenerateCSRFToken(): void {} }; }
    function base_href($path) { return $path; }
    function site_setting($key, $default = '') { return $default; }
    function return_translation($key) { return $GLOBALS['translations'][$key] ?? $key; }
    function print_translation($key) { return htmlSC(return_translation($key)); }
    function htmlSC($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function check_admin() { return false; }
    function get_user_avatar(...$args) { return '/avatar.png'; }
    function get_user_role_label($role) { return $role; }
    function render_public_verified_badge($role) { return ''; }
    function get_csrf_field() { return '<input type="hidden" name="needCSRFToken" value="test">'; }
    function get_validation_class($field) { return ''; }
    function get_errors($field) { return ''; }
    function old($field) { return ''; }
    function apply_filters($hook, $items, $user) { return $GLOBALS['menu']; }
    function view($name = null, $data = []) {
        if ($name !== null) { return $data; }
        return new class {
            public function renderPartial($templatePath, $data) {
                extract($data);
                ob_start();
                require __DIR__ . '/../app/Views/themes/default/' . $templatePath . '.php';
                return ob_get_clean();
            }
        };
    }
    function expect($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
    function renderProfile($data) {
        extract($data);
        ob_start();
        require __DIR__ . '/../app/Views/themes/default/auth/profile.php';
        return ob_get_clean();
    }
    define('SITE_NAME', 'Test');
    $session = new TestSession();
    $request = new TestRequest();
    $model = new \App\Models\User();
    $controller = new TestController($model);
    $translations = require __DIR__ . '/../app/Languages/ru/auth/profile.php';
    $menu = [['label' => 'Test VPN', 'href' => '/my-vpn', 'icon' => 'ci-server']];
    $session->set('auth.two_factor_recovery_codes', ['recovery-test']);
    $overview = $controller->profile();
    expect($session->get('auth.two_factor_recovery_codes') === ['recovery-test'], 'Overview must not consume recovery codes');
    $html = renderProfile($overview);
    expect(!str_contains($html, 'name="profile_action"'), 'Overview must contain no editing forms');
    expect(str_contains($html, '/my-vpn'), 'Plugin service links must remain accessible');
    foreach (['information', 'security', 'notifications'] as $section) {
        $request->query = ['section' => $section];
        $data = $controller->settings();
        $html = renderProfile($data);
        expect(substr_count($html, 'aria-current="page"') === 1, 'Exactly one active navigation item');
        expect(substr_count($html, '<form ') === substr_count($html, '</form>'), 'Balanced forms');
        expect(preg_match_all('/<div\b/', $html) === substr_count($html, '</div>'), 'Balanced layout: ' . $section);
        expect(str_contains($html, 'name="avatar_file"') === ($section === 'information'), 'Avatar isolation');
        expect(str_contains($html, 'name="password"') === ($section === 'security'), 'Password isolation');
        expect(str_contains($html, 'data-pwa-enable-push') === ($section === 'notifications'), 'Push isolation');
        if ($section === 'security') { expect(str_contains($html, 'recovery-test'), 'Recovery codes displayed on security page'); }
    }
    foreach ([['two_factor_setup' => ['secret' => 'test']], ['user' => $model->user + ['two_factor_secret' => 'test', 'two_factor_enabled_at' => '2026-01-01']]] as $state) {
        $html = renderProfile(array_replace($overview, ['is_settings' => true, 'settings_section' => 'security'], $state));
        expect(substr_count($html, '<form ') === substr_count($html, '</form>'), '2FA state forms balanced');
    }
    $request->query = ['section' => '../../bad'];
    expect($controller->settings()['settings_section'] === 'information', 'Unknown section fallback');
    $request->post = true;
    foreach (['password', 'details'] as $action) {
        $model->saved = [];
        $request->data = ['profile_action' => $action, 'name' => 'Changed', 'login' => 'changed', 'email' => 'changed@example.test', 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'];
        try { $controller->settings(); } catch (Redirect $e) {
            expect($e->getMessage() === '/profile/settings?section=' . ($action === 'password' ? 'security' : 'information'), 'Redirect to matching settings section');
        }
        expect($model->saved['name'] === ($action === 'password' ? 'Example' : 'Changed'), 'Password form cannot overwrite identity');
        expect($model->saved['password'] === ($action === 'password' ? 'NewPassword1' : ''), 'Information form cannot change password');
    }
    $request->data = ['profile_action' => 'password', 'password' => ''];
    $model->saved = [];
    try { $controller->settings(); } catch (Redirect $e) {}
    expect($model->saved === [], 'Empty password must not save');
    expect(!empty($session->get('form_errors')['password']), 'Empty password error');
    $model->errors = ['current_password' => ['Wrong password']];
    $request->data['password'] = 'NewPassword1';
    try { $controller->settings(); } catch (Redirect $e) { expect(str_contains($e->getMessage(), 'security'), 'Validation returns to security'); }
    expect($model->saved === [], 'Validation failure must not save');
    expect($controller->csrfChecks === 4, 'Every mutation checks CSRF');
    echo "Profile settings regression checks passed.\n";
}
