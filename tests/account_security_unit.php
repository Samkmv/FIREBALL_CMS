<?php
declare(strict_types=1);
// Reuse isolated SQLite, never the CMS bootstrap/production database.
session_id('security-fixture');
require __DIR__ . '/favorites_unit.php';
define('MULTILANGS', true);
function get_user(): array { return ['id' => $GLOBALS['owner'] ?? 1]; }
function check_auth(): bool { return false; }
function session(): object { return $GLOBALS['session'] ??= new class {
    public array $data = ['needCSRFToken' => 'correct'];
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function setFlash(string $key, mixed $value): void { $this->data['flash.' . $key] = $value; }
}; }
final class AccountFixtureResponse extends RuntimeException {
    public function __construct(public mixed $body, int $status) { parent::__construct('', $status); }
}
function response(): FBL\Response { return $GLOBALS['response'] ??= new class extends FBL\Response {
    public function json($data, $code = 200): never { throw new AccountFixtureResponse($data, $code); }
    public function redirect($url = ''): never { throw new AccountFixtureResponse($url, 302); }
}; }
function setRequest(array $post = [], bool $ajax = true): void {
    $_GET = []; $_POST = $post; $_FILES = [];
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
    $GLOBALS['request'] = new FBL\Request('/profile/favorites/add');
}
function expectResponse(callable $fn, int $code): AccountFixtureResponse {
    try { $fn(); check(false, 'Missing response'); }
    catch (AccountFixtureResponse $response) { check($response->getCode() === $code, 'HTTP ' . $code); return $response; }
    throw new RuntimeException('Unreachable');
}
$checks = 0;
setRequest();
$router = new FBL\Router(request(), response());
$app = new class($router) { public function __construct(public FBL\Router $router) {} public function isInstalled(): bool { return false; } };
require dirname(__DIR__) . '/config/routes.php';
$newRoutes = array_filter($router->getRoutes(), static fn(array $r): bool => str_starts_with($r['path'], '/profile/sessions/') || str_starts_with($r['path'], '/profile/favorites/'));
check(count($newRoutes) === 5, 'All mutation routes registered');
foreach ($newRoutes as $route) check($route['needCSRFToken'] && $route['method'] === ['POST'] && $route['middleware'] === ['auth'], 'Default CSRF/auth and POST');
check(!$router->checkCSRFToken(), 'Empty token rejected');
request()->post['needCSRFToken'] = 'wrong'; check(!$router->checkCSRFToken(), 'Wrong token rejected');
request()->post['needCSRFToken'] = 'correct'; check($router->checkCSRFToken(), 'Correct token accepted');
FBL\Language::$lang_data = ['tpl_auth_required_login' => 'Sign in'];
expectResponse(static fn() => (new FBL\Middleware\Auth())->handle(), 401);
$controller = new App\Controllers\FavoritesController();
setRequest(['entity_id' => 1, 'entity_type' => 'page']); expectResponse(fn() => $controller->add(), 422);
setRequest(['entity_id' => 9999, 'entity_type' => 'post']); expectResponse(fn() => $controller->add(), 404);
setRequest(['entity_id' => 1, 'entity_type' => 'post']);
$result = expectResponse(fn() => $controller->add(), 200); check($result->body['saved'] === true, 'AJAX saved state');
$GLOBALS['owner'] = 2; expectResponse(fn() => $controller->remove(), 200);
check((new App\Models\UserFavorite())->ids(1, 'post', [1]) === [1], 'Controller cannot remove another owner favorite');
$GLOBALS['owner'] = 1;
setRequest(['entity_id' => 1, 'entity_type' => 'post', 'return_to' => 'https://evil.example'], false);
$result = expectResponse(fn() => $controller->remove(), 302); check($result->body === '/cms/profile/favorites', 'Untrusted return URL ignored');
check(isset(session()->data['flash.success']), 'Native form flash UX');
setRequest(['entity_id' => 1, 'entity_type' => 'post', 'return_to' => 'post'], false);
$result = expectResponse(fn() => $controller->add(), 302); check($result->body === '/cms/posts/post-1', 'Native post fallback');
db()->pdo->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY, user_id INTEGER, session_hash TEXT, revoked_at TEXT);
    INSERT INTO user_sessions VALUES (1, 2, "foreign", NULL)');
setRequest(['id' => 1]);
expectResponse(static fn() => (new App\Controllers\UserSessionsController())->revoke(), 404);
check(db()->query('SELECT revoked_at FROM user_sessions WHERE id = 1')->getColumn() === null, 'Foreign session untouched');
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require dirname(__DIR__) . '/app/Languages/' . $locale . '.php';
    foreach (['sessions', 'favorites'] as $view) check((require dirname(__DIR__) . '/app/Languages/' . $locale . '/auth/' . $view . '.php') === (require dirname(__DIR__) . '/app/Languages/' . $locale . '/auth/profile.php'), 'Actual route translations ' . $locale . $view);
    foreach (glob(dirname(__DIR__) . '/app/Languages/ru.php') as $file) {
        foreach (array_keys(require $file) as $key) if (str_starts_with($key, 'account_') || str_starts_with($key, 'upload_')) check(isset($translations[$key]) && trim($translations[$key]) !== '', 'New translation ' . $locale . $key);
    }
}
echo "$checks account security/translation checks passed\n";
