<?php

declare(strict_types=1);

// No live database, network, updates, uploads or filesystem cleanup in this test.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

use App\Controllers\AdminController;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\UpdateCenter;

final class PermissionSession
{
    public array $data = [];
    public function get($key, $default = null) { return $this->data[$key] ?? $default; }
    public function set($key, $value, ...$unused): void { $this->data[$key] = $value; }
    public function has($key): bool { return isset($this->data[$key]); }
    public function remove($key): void { unset($this->data[$key]); }
    public function setFlash($key, $value): void { $this->set('flash.' . $key, $value); }
    public function refreshCookie(): void {}
}
final class PermissionStop extends RuntimeException {}
function session(): PermissionSession { return $GLOBALS['permissionSession']; }
function cache(): PermissionSession { return $GLOBALS['permissionCache']; }
function get_user(): array { return session()->get('user', []); }
function check_auth(): bool { return FBL\Auth::isAuth(); }
function check_admin(): bool { return FBL\Auth::isAdmin(); }
function check_creator(): bool { return FBL\Auth::hasRole('creator'); }
function request(): FBL\Request { return $GLOBALS['permissionRequest']; }
function response(): object { return new class { public function redirect($url): never { throw new PermissionStop($url, 302); } }; }
function abort($message = '', $code = 404): never { throw new PermissionStop($message, $code); }
function get_route_param($key, $default = null) { return $GLOBALS['permissionRouteParams'][$key] ?? $default; }
function return_translation(string $key): string { return FBL\Language::$lang_data[$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function htmlSC($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path = ''): string { return $path; }
function make_slug(string $value, string $fallback = ''): string { return strtolower(trim($value)); }
function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="test-only">'; }
function get_user_avatar(...$args): string { return '/test-avatar.png'; }
function get_user_role_label(string $role): string { return $role; }
function get_validation_class(...$args): string { return ''; }
function get_errors(...$args): string { return ''; }
function old(string $key): string { return ''; }
function current_url_with_query(...$args): string { return '/admin/users'; }
function admin_table_sort_url(...$args): string { return '/admin/users'; }
function apply_filters($name, $value, ...$args) { return $name === 'admin_user_delete_blockers' ? ($GLOBALS['permissionDeleteBlockers'] ?? []) : $value; }
function do_action(...$args): void {}
function view(?string $name = null, array $data = []): mixed
{
    if ($name !== null) return ['view' => $name, 'data' => $data];
    return new class {
        public function renderPartial(string $name, array $data = []): string
        {
            if ($name === 'admin/shell_open') return '<main>' . ($data['actions'] ?? '');
            if ($name === 'admin/shell_close') return '</main>';
            return permissionRender($name, $data);
        }
    };
}
function permissionRender(string $templateName, array $data): string
{
    extract($data);
    ob_start();
    require ROOT . '/app/Views/themes/default/' . $templateName . '.php';
    return (string)ob_get_clean();
}
function permissionAssert(bool $condition, string $message): void
{
    $GLOBALS['permissionAssertions']++;
    if (!$condition) throw new RuntimeException($message);
}
function permissionStops(callable $callback, int $code): void
{
    try { $callback(); } catch (PermissionStop $e) {
        permissionAssert($e->getCode() === $code, 'Unexpected response: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected response ' . $code);
}
function permissionRequest(string $method, array $post = [], int $id = 0): void
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $post; $_GET = []; $_FILES = [];
    $GLOBALS['permissionRequest'] = new FBL\Request('/admin/users');
    $GLOBALS['permissionRouteParams'] = ['id' => $id];
}

final class PermissionDatabase
{
    public PDO $pdo;
    private PDOStatement $statement;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public function query(string $sql, array $params = []): self
    {
        $this->statement = $this->pdo->prepare($sql);
        $this->statement->execute($params);
        return $this;
    }
    public function getColumn() { return $this->statement->fetchColumn(); }
    public function getOne() { return $this->statement->fetch(PDO::FETCH_ASSOC); }
    public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
    public function rowCount(): int { return $this->statement->rowCount(); }
    public function findOne($table, $value, $field = 'id') { return $this->query("SELECT * FROM {$table} WHERE {$field} = ?", [$value])->getOne(); }
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { $this->pdo->rollBack(); }
}
function db(): PermissionDatabase { return $GLOBALS['permissionDatabase']; }
final class PermissionUser extends User
{
    protected function translate(string $key): string { return return_translation($key); }
    public function ensureUsersTableExists(): void {}
    public function findEditableUserById(int $id): array|false
    {
        $user = $this->findById($id);
        return $user ? $user + ['other_admins_count' => $this->countAdmins($id)] : false;
    }
    public function getRoles(): array { return array_map(fn($role) => ['slug' => $role], ['creator', 'admin', 'user']); }
    public function findRoleBySlug(string $slug): array|false { return in_array($slug, ['creator', 'admin', 'user'], true) ? ['slug' => $slug] : false; }
    protected function syncUserContent(int $id, string $name, string $role): void {}
    protected function collectUserChatAttachmentPaths(int $id): array { return []; }
    protected function deleteUserRelatedData(int $id): void {}
    protected function removeStoredAvatar(?string $path): void {}
    protected function removeStoredFiles(array $paths): void {}
}
final class PermissionSettings extends SiteSetting
{
    public array $values = ['update_channel' => 'dev', 'updater_github_repository' => 'test/cms', 'updater_github_branch' => 'main'];
    public function all(): array { return $this->values; }
    public function setMany(array $values): void { $this->values = array_replace($this->values, $values); }
}
final class PermissionUpdater extends UpdateCenter
{
    public bool $git = false;
    public array $httpResponse = ['status_code' => 404, 'body' => '{}'];
    public array $httpUrls = [];
    public array $installPaths = [];
    public function channel(array $settings): string { return $this->resolveUpdateChannel($settings); }
    protected function getLocalGitState(): array
    {
        return ['is_git_repo' => $this->git, 'git_available' => true, 'origin_url' => '', 'branch' => 'main',
            'commit_hash' => '', 'short_commit' => '', 'git_tag' => '', 'git_describe' => '', 'is_clean' => true,
            'is_update_clean' => true, 'dirty_files' => [], 'blocking_dirty_files' => [], 'ignored_dirty_files' => []];
    }
    protected function buildUpdateBlockers(string $repository, array $state, ?string $channel = null): array { return []; }
    protected function httpGet(string $url, array $headers = []): array { $this->httpUrls[] = $url; return $this->httpResponse; }
    protected function runCommand(string $command, string $cwd): array { throw new RuntimeException('Unexpected shell command: ' . $command); }
    protected function acquireUpdateLock() { return null; }
    protected function releaseUpdateLock($handle): void {}
    protected function enableMaintenanceMode(array $user): void {}
    protected function disableMaintenanceMode(): void {}
    protected function writeUpdateLog(array $user, string $fromVersion, ?string $toVersion, string $result, ?string $error = null): void {}
    protected function runStableReleaseGitUpdate(string $repo, string $branch, string $token = ''): array { $this->installPaths[] = 'stable-git'; return []; }
    protected function runStableReleaseUpdate(string $repo, string $token = ''): array { $this->installPaths[] = 'stable-zip'; return []; }
}
final class PermissionController extends AdminController
{
    public function __construct(User $users, SiteSetting $settings, UpdateCenter $updater)
    {
        $this->users = $users; $this->siteSettings = $settings; $this->updateCenter = $updater;
    }
}

$permissionAssertions = 0;
$permissionSession = new PermissionSession();
$permissionCache = new PermissionSession();
$permissionDatabase = new PermissionDatabase();
FBL\Language::$lang_data = require ROOT . '/app/Languages/ru.php';
// Auth::setUser uses the real settings class; keep that read in memory as well.
(new ReflectionProperty(SiteSetting::class, 'cache'))->setValue(null, ['admin_session_lifetime_hours' => '12']);
db()->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, login TEXT, email TEXT, avatar TEXT, role TEXT, password TEXT, session_version INTEGER DEFAULT 1)');
foreach ([1 => 'creator', 2 => 'admin', 3 => 'user', 4 => 'admin', 5 => 'user'] as $id => $role) {
    db()->query('INSERT INTO users (id, name, login, email, role) VALUES (?, ?, ?, ?, ?)', [$id, 'Person ' . $id, 'person-' . $id, 'person' . $id . '@example.test', $role]);
}
$users = new PermissionUser();
$settings = new PermissionSettings();
$updater = new PermissionUpdater($settings);
$controller = new PermissionController($users, $settings, $updater);
session()->set('user', $users->findById(2));
permissionRequest('GET');

// Register actual routes: management actions remain authenticated and CSRF-protected.
$app = new class {
    public FBL\Router $router;
    public function __construct() { $this->router = new FBL\Router(request(), new FBL\Response()); }
    public function isInstalled(): bool { return false; }
};
require ROOT . '/config/routes.php';
$expected = [
    ['GET', '/admin/users/edit/(?P<id>\d+)/?', false],
    ['POST', '/admin/users/edit/(?P<id>\d+)/?', false],
    ['POST', '/admin/users/delete', false],
    ['GET', '/admin/updates', false],
    ['POST', '/admin/settings/update-center/check', false],
    ['POST', '/admin/settings/update-center/update', false],
    ['POST', '/admin/updates', true],
    ['POST', '/admin/settings/update-center/rollback', true],
    ['POST', '/admin/users/create', true],
];
foreach ($expected as [$method, $path, $creatorOnly]) {
    $matches = array_values(array_filter($app->router->getRoutes(), fn($r) => $r['path'] === $path && in_array($method, $r['method'], true)));
    permissionAssert(count($matches) === 1, 'Missing or duplicate route: ' . $path);
    $route = $matches[0];
    permissionAssert($route['middleware'] === ($creatorOnly ? ['auth', 'admin', 'creator'] : ['auth', 'admin']), 'Incorrect permissions: ' . $path);
    permissionAssert($route['needCSRFToken'], 'CSRF disabled: ' . $path);
}

// Exercise real controller + user validation/update/delete against isolated SQL.
foreach ([2, 3, 4] as $id) {
    permissionRequest('GET', [], $id);
    permissionAssert($controller->userForm()['view'] === 'admin/user_form', 'Admin cannot open edit form');
    $data = $users->findById($id);
    $data['name'] = 'Edited person ' . $id;
    permissionRequest('POST', $data, $id);
    permissionStops(fn() => $controller->userForm(), 302);
    permissionAssert($users->findById($id)['name'] === $data['name'], 'Admin update not persisted');
}
permissionAssert(get_user()['name'] === 'Edited person 2', 'Own admin session did not refresh');
$creatorBefore = $users->findById(1);
permissionRequest('POST', $creatorBefore + ['password' => 'Changed123!'], 1);
permissionStops(fn() => $controller->userForm(), 302);
permissionAssert($users->findById(1) === $creatorBefore, 'Creator was changed');
permissionRequest('POST', array_replace($users->findById(2), ['role' => 'creator']), 2);
permissionStops(fn() => $controller->userForm(), 302);
permissionAssert($users->findById(2)['role'] === 'admin', 'Admin promoted self to creator');
session()->remove('form_data'); session()->remove('form_errors');
foreach ([1, 2] as $id) {
    permissionRequest('POST', ['id' => $id]);
    permissionStops(fn() => $controller->userDelete(), 302);
    permissionAssert((bool)$users->findById($id), 'Creator or current admin was deleted');
}
$permissionDeleteBlockers = ['subscriptions'];
permissionRequest('POST', ['id' => 5]);
permissionStops(fn() => $controller->userDelete(), 302);
permissionAssert((bool)$users->findById(5), 'Plugin financial/dependency guard was bypassed');
$permissionDeleteBlockers = [];
foreach ([3, 4] as $id) {
    permissionRequest('POST', ['id' => $id]);
    permissionStops(fn() => $controller->userDelete(), 302);
    permissionAssert(!$users->findById($id), 'Admin cannot delete another unprotected user');
}

// Admin cannot save Dev/repository/token or invoke rollback through forged requests.
permissionRequest('POST', ['update_channel' => 'dev', 'updater_github_repository' => 'attacker/repo']);
$settingsBefore = $settings->all();
permissionStops(fn() => $controller->updates(), 403);
permissionStops(fn() => $controller->rollbackUpdate(), 403);
permissionAssert($settings->all() === $settingsBefore, 'Admin changed updater settings');
permissionAssert($updater->channel(['update_channel' => 'dev']) === 'stable', 'Saved Dev bypassed admin restriction');
permissionAssert($updater->channel([]) === 'stable', 'Default channel bypassed admin restriction');
foreach ([false, true] as $git) {
    $updater->git = $git;
    permissionRequest('POST', ['update_channel' => 'dev', 'channel' => 'dev', 'branch' => 'unsafe']);
    permissionStops(fn() => $controller->runUpdate(), 302);
    permissionAssert(end($updater->installPaths) === ($git ? 'stable-git' : 'stable-zip'), 'Non-stable install path selected');
}
permissionAssert($settings->all() === $settingsBefore, 'Stable enforcement rewrote creator preferences');
permissionRequest('GET');
permissionAssert($controller->updates()['data']['update_center']['config']['channel'] === 'stable', 'Admin cannot open stable update center');
permissionRequest('POST', ['channel' => 'dev']);
permissionStops(fn() => $controller->checkForUpdates(), 302);
permissionAssert($updater->getLastCheckPayload()['channel'] === 'stable', 'Check endpoint did not enforce Stable');
$updater->httpUrls = [];

// Dev cache is hidden and not reused by admin checks. No fallback to tags/branches.
$settings->setMany(['updater_last_check_payload' => json_encode(['channel' => 'dev', 'update_available' => true]), 'updater_last_check_payload_stable' => '', 'updater_last_checked_at' => date('Y-m-d H:i:s')]);
permissionAssert($updater->getLastCheckPayload() === null, 'Admin received Dev cache');
permissionAssert($updater->getDashboardData()['last_check'] === null, 'Dashboard received Dev cache');
$check = $updater->checkForUpdatesIfStale();
permissionAssert($check['channel'] === 'stable' && !$check['update_available'], 'Dev cache bypassed fresh stable check');
permissionAssert(count($updater->httpUrls) === 1 && str_contains($updater->httpUrls[0], '/releases/latest'), 'Missing release fell back to dev/tags');
$updater->checkForUpdatesIfStale();
permissionAssert(count($updater->httpUrls) === 1, 'Stable cache was not reused');
foreach (['draft', 'prerelease'] as $flag) {
    $updater->httpResponse = ['status_code' => 200, 'body' => json_encode([$flag => true, 'tag_name' => 'v999.0.0'])];
    permissionAssert(!$updater->checkForUpdates()['update_available'], 'Draft/prerelease offered to admin');
}
$updater->httpResponse = ['status_code' => 200, 'body' => json_encode(['tag_name' => 'v999.0.0', 'name' => '999.0.0'])];
permissionAssert($updater->checkForUpdates()['update_available'], 'Published stable release not offered');

// Render real desktop/mobile tables and forms, not just source-string assertions.
permissionRequest('GET');
$list = [];
foreach ([1 => 'creator', 2 => 'admin', 3 => 'user', 4 => 'admin'] as $id => $role) {
    $list[] = ['id' => $id, 'role' => $role, 'name' => 'Person ' . $id, 'login' => 'person-' . $id,
        'email' => 'person' . $id . '@example.test', 'other_admins_count' => 2, 'created_at' => '2026-09-01 12:00:00'];
}
$html = permissionRender('admin/users', ['users' => $list, 'sort' => 'name', 'direction' => 'asc', 'search' => '', 'total' => 4, 'pagination' => null]);
$dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); $xpath = new DOMXPath($dom);
foreach ([2, 3, 4] as $id) permissionAssert($xpath->query('//a[@href="/admin/users/edit/' . $id . '"]')->length === 2, 'Desktop/mobile edit missing');
foreach ([1, 2] as $id) permissionAssert($xpath->query('//form[@action="/admin/users/delete"][input[@name="id" and @value="' . $id . '"]]')->length === 0, 'Unsafe delete button visible');
foreach ([3, 4] as $id) permissionAssert($xpath->query('//form[@action="/admin/users/delete"][input[@name="id" and @value="' . $id . '"]][input[@name="csrf"]][@data-admin-delete-form]')->length === 2, 'Desktop/mobile delete or confirmation missing');
permissionAssert($xpath->query('//a[@href="/admin/users/create"]')->length === 0, 'Unrequested create access exposed');
permissionAssert($xpath->query('//a[@href="/admin/users/edit/1"]')->length === 0, 'Creator edit action visible');
$html = permissionRender('admin/user_form', ['user_item' => $users->findEditableUserById(2), 'roles' => $users->getRoles(), 'is_edit' => true]);
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); $xpath = new DOMXPath($dom);
permissionAssert($xpath->query('//input[@name="name" and not(@readonly)]')->length === 1, 'Own profile is read-only');
permissionAssert($xpath->query('//button[@type="submit" and @form="userForm"]')->length === 1, 'Own save button missing');
permissionAssert($xpath->query('//option[@value="creator"]')->length === 0, 'Creator assignment option exposed');
permissionAssert($xpath->query('//form[@action="/admin/users/delete"]')->length === 0, 'Own edit form permits deletion');
$html = permissionRender('admin/updates', ['settings' => $settings->all(), 'update_center' => $updater->getDashboardData(), 'engine_release' => []]);
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); $xpath = new DOMXPath($dom);
foreach (['check', 'update'] as $action) permissionAssert($xpath->query('//form[@action="/admin/settings/update-center/' . $action . '"][input[@name="csrf"]]')->length === 1, 'Admin update action missing');
permissionAssert($xpath->query('//select[@name="update_channel"]|//form[@action="/admin/updates"]|//form[@action="/admin/settings/update-center/rollback"]')->length === 0, 'Creator update controls exposed');
permissionAssert(!str_contains($html, return_translation('admin_update_dev_warning_text')), 'Admin UI advertises Dev');

// Lower roles and anonymous requests cannot call these controller actions directly.
foreach ([[], ['id' => 5, 'role' => 'user'], ['id' => 5, 'role' => 'moderator']] as $actor) {
    session()->set('user', $actor);
    foreach (['userForm', 'userDelete', 'updates', 'checkForUpdates', 'runUpdate'] as $action) permissionStops(fn() => $controller->$action(), 403);
}
session()->set('user', $users->findById(1));
permissionAssert($updater->channel(['update_channel' => 'dev']) === 'dev', 'Creator lost Dev');
permissionAssert($updater->channel(['update_channel' => 'stable']) === 'stable', 'Creator lost Stable');
permissionAssert($updater->getLastCheckPayload() === null, 'Creator Dev view reused stable cache');
permissionRequest('GET');
$settings->setMany(['updater_rollback_commit' => str_repeat('a', 40)]);
$html = permissionRender('admin/updates', ['settings' => $settings->all(), 'update_center' => $updater->getDashboardData(), 'engine_release' => []]);
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); $xpath = new DOMXPath($dom);
permissionAssert($xpath->query('//select[@name="update_channel"]/option[@value="dev" and @selected]')->length === 1, 'Creator lost Dev selector');
permissionAssert($xpath->query('//form[@action="/admin/updates"][input[@name="csrf"]]')->length === 1, 'Creator lost updater settings');
permissionAssert($xpath->query('//form[@action="/admin/settings/update-center/rollback"]')->length === 1, 'Creator lost manual rollback');
echo 'Admin permissions: ' . $permissionAssertions . " checks passed.\n";
