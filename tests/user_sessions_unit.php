<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
define('STORAGE', sys_get_temp_dir() . '/fireball-session-fixture-absent');
define('LANGS', ['ru' => ['code' => 'ru', 'base' => 1], 'en' => ['code' => 'en', 'base' => 0]]);
define('DEFAULT_LOCALE', 'ru'); define('DEBUG', 0);
define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
function db(): object { return $GLOBALS['sessionDb']; }
function client_ip(): string { return '192.0.2.1'; }
function request(): object { return $GLOBALS['fixtureRequest'] ??= new class {
    public string $uri = '/profile/sessions'; public array $get = [];
    public function get(string $k, mixed $default = null): mixed { return $this->get[$k] ?? $default; }
}; }
function session(): object { return $GLOBALS['fixtureSession'] ??= new class {
    public array $data = []; private int $n = 0;
    public function get(string $k, mixed $d = null): mixed { return $this->data[$k] ?? $d; }
    public function set(string $k, mixed $v): void { $this->data[$k] = $v; }
    public function has(string $k): bool { return isset($this->data[$k]); }
    public function remove(string $k): void { unset($this->data[$k]); }
    public function clear(): void { $this->data = []; }
    public function regenerateId(): void { session_id('fixture-regenerated-' . ++$this->n); }
    public function lifetimeSeconds(): int { return 43200; }
    public function setFlash(string $k, mixed $v): void { $this->set('flash.' . $k, $v); }
}; }
function app(): object { return new class {
    public function regenerateCSRFToken(): void {}
    public function get(string $key): mixed { return $key === 'lang' ? ['code' => 'ru'] : null; }
}; }
function site_setting(string $key, string $default): string { return $default; }
function abort(string $msg = '', int $code = 404): never { throw new RuntimeException($msg, $code); }
$GLOBALS['sessionDb'] = new class {
    public PDO $pdo; private PDOStatement $s; public int $activityUpdates = 0;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, session_version INTEGER); INSERT INTO users VALUES (1, "One", "one@example.test", "user", 1), (2, "Two", "two@example.test", "user", 1)');
        $this->pdo->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, session_hash TEXT UNIQUE, session_version INTEGER, device_type TEXT, browser TEXT, browser_version TEXT, os TEXT, user_agent TEXT, ip_address TEXT, created_at TEXT, last_activity_at TEXT, expires_at TEXT, revoked_at TEXT)');
        $this->pdo->exec('CREATE TABLE security_logs (id INTEGER PRIMARY KEY, actor_user_id INTEGER, target_user_id INTEGER, event TEXT, result TEXT, reason TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT)');
    }
    public function query(string $q, array $args = []): static {
        if (str_contains($q, 'SET last_activity_at')) $this->activityUpdates++;
        $q = str_replace(' LIMIT 1000', '', $q);
        $this->s = $this->pdo->prepare($q); $this->s->execute($args); return $this;
    }
    public function getOne(): array|false { return $this->s->fetch(PDO::FETCH_ASSOC); }
    public function getColumn(): mixed { return $this->s->fetchColumn(); }
    public function get(): array { return $this->s->fetchAll(PDO::FETCH_ASSOC); }
    public function rowCount(): int { return $this->s->rowCount(); }
    public function findOne(string $t, mixed $id): array|false { return $this->query("SELECT * FROM $t WHERE id = ?", [$id])->getOne(); }
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { $this->pdo->rollBack(); }
};
use App\Services\UserSessionService as S;
use FBL\Auth;
$service = new S(); $checks = 0;
function check(bool $ok, string $label): void { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($label); }
Auth::loginUser(db()->findOne('users', 1));
$first = session_id();
check(session()->has('user'), 'Authenticated');
$row = db()->query('SELECT * FROM user_sessions')->getOne();
check($row['session_hash'] === S::hash($first) && $row['session_hash'] !== $first, 'Login tracked after ID regeneration; hash only');
check(!str_contains(json_encode(db()->query('SELECT * FROM security_logs')->get()), $first), 'No raw session ID in security log');
check($service->validate(1, 1, $first, 43200), 'Valid current session');
Auth::rotateSession();
check(!$service->validate(1, 1, $first, 43200) && $service->validate(1, 1, session_id(), 43200), 'Profile security rotation replaces the registered session');
Auth::setUser();
check(Auth::isAuth(), 'Profile update does not accidentally sign out current user');
$first = session_id();
$row = db()->query('SELECT * FROM user_sessions WHERE session_hash = ?', [S::hash($first)])->getOne();
check(!$service->validate(2, 1, $first, 43200), 'Session cannot change owner');
check(db()->activityUpdates === 0, 'Activity updates throttled');
db()->query('UPDATE user_sessions SET last_activity_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 120), $row['id']]);
$service->validate(1, 1, $first, 43200); $service->validate(1, 1, $first, 43200);
check(db()->activityUpdates === 2, 'Only one service activity update plus fixture setup');
$service->register(1, 1, 'another-device', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile Safari/604.1'], 43200);
$second = db()->query('SELECT * FROM user_sessions WHERE session_hash = ?', [S::hash('another-device')])->getOne();
check($second['browser'] === 'Safari' && $second['os'] === 'iOS' && $second['device_type'] === 'Mobile', 'Reuse actual client parser');
foreach (['FxiOS/26.1' => 'Firefox', 'EdgiOS/26.1' => 'Edge', 'CriOS/26.1' => 'Chrome'] as $token => $browser) {
    $client = (new App\Services\AnalyticsService())->describeClient('Mozilla/5.0 (iPhone) ' . $token . ' Mobile Safari/604.1');
    check($client['browser'] === $browser && $client['browser_version'] === '26.1', 'iOS browser metadata ' . $browser);
}
check((new App\Services\AnalyticsService())->describeClient('Mozilla/5.0 (Macintosh) Version/26.0 Mobile Safari/604.1')['device_type'] === 'Tablet', 'Desktop-mode iPad is not mislabeled as phone');
$list = $service->paginated(1, $first);
check(count($list['items']) === 2 && $list['items'][0]['is_current'] === 1, 'Current device identified');
check(!isset($list['items'][0]['session_hash'], $list['items'][0]['user_agent']), 'Credentials not returned by list');
check(!$service->revoke(2, (int)$second['id']), 'Cannot revoke another owner');
$service->revokeOthers(1, $first);
check($service->validate(1, 1, $first, 43200) && !$service->validate(1, 1, 'another-device', 43200), 'Only other devices revoked');
$service->revoke(1, (int)$row['id']);
Auth::setUser();
check(!Auth::isAuth(), 'Revoked session logs out on next request');
check(!$service->validate(1, 1, $first, 43200, true), 'Legacy flag cannot revive known revocation');
Auth::loginUser(db()->findOne('users', 1));
$third = session_id();
$service->revokeAll(1);
check(db()->findOne('users', 1)['session_version'] === 2, 'Global logout increments existing session_version');
Auth::setUser();
check(!Auth::isAuth(), 'session_version invalidation retained');
check(!$service->validate(1, 2, $third, 43200), 'All registered sessions revoked');
check($service->validate(2, 1, 'legacy-session', 43200, true), 'Pre-migration session enrolled once');
db()->query('UPDATE user_sessions SET expires_at = ? WHERE session_hash = ?', [date('Y-m-d H:i:s', time() - 1), S::hash('legacy-session')]);
check(!$service->validate(2, 1, 'legacy-session', 43200, true), 'Expired session cannot reenroll');
for ($i = 0; $i < 22; $i++) $service->register(2, 1, 'pagination-' . $i, [], 43200);
$page1 = $service->paginated(2, 'pagination-0');
check(count($page1['items']) === 20 && $page1['pagination']['total_pages'] === 2, 'Session pagination');
request()->get['page'] = 2;
check(count($service->paginated(2, 'pagination-0')['items']) === 2, 'Second page');
db()->query('UPDATE user_sessions SET expires_at = ? WHERE session_hash = ?', [date('Y-m-d H:i:s', time() - 91 * 86400), S::hash('legacy-session')]);
check($service->cleanup() === 1, 'Only old expired/revoked records cleaned');
$routes = file_get_contents(dirname(__DIR__) . '/config/routes.php');
foreach (['revoke', 'others', 'all'] as $route) {
    check((bool)preg_match("~post\('/profile/sessions/$route'.*middleware\(\['auth'\]\)~", $routes), 'Authenticated POST ' . $route);
}
$auth = file_get_contents(dirname(__DIR__) . '/app/Controllers/AuthController.php');
check(substr_count($auth, 'Auth::loginUser($user)') >= 2, 'Normal and 2FA login share tracking hook');
echo "$checks user session checks passed\n";
