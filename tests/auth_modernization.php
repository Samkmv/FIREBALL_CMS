<?php
declare(strict_types=1);

// Isolated real-MySQL integration checks. Never load installed.php / DB_SETTINGS.
$project = dirname(__DIR__);
$httpFixture = PHP_SAPI === 'cli-server' && getenv('FBL_AUTH_FIXTURE_ROOT');
$scratch = realpath($httpFixture ? getenv('FBL_AUTH_FIXTURE_ROOT') : ($argv[1] ?? '')) ?: '';
if (!str_starts_with($scratch, __DIR__ . '/.tmp/auth-modernization-') || !is_file($scratch . '/db.pid')) {
    throw new RuntimeException('Pass the owned, running auth-modernization test database directory.');
}
require $project . '/vendor/autoload.php';
foreach (['storage', 'cache', 'sessions'] as $directory) if (!is_dir($scratch . '/' . $directory)) mkdir($scratch . '/' . $directory, 0700, true);
foreach ([
    'ROOT' => $project, 'APP' => $project . '/app', 'CONFIG' => $project . '/config',
    'CORE' => $project . '/core', 'WWW' => $project . '/public', 'VIEWS' => $project . '/app/Views',
    'STORAGE' => $scratch . '/storage', 'CACHE' => $scratch . '/cache', 'ERROR_LOGS' => $scratch . '/errors.log',
    'PATH' => $httpFixture ? 'http://127.0.0.1:18198' : 'https://example.test', 'APP_TIMEZONE' => 'UTC', 'DEFAULT_LOCALE' => 'ru',
    'THEME' => 'default', 'LAYOUT' => 'default', 'SITE_NAME' => 'Auth test',
    'CHAT_ENCRYPTION_KEY' => 'isolated-auth-test-not-a-production-key', 'PLUGINS' => $scratch . '/plugins',
    'TRUSTED_PROXIES' => [], 'MULTILANGS' => 0, 'DEBUG' => 0,
    'LANGS' => ['ru' => ['code' => 'ru'], 'en' => ['code' => 'en'], 'de' => ['code' => 'de'], 'zh-cn' => ['code' => 'zh-cn']],
    'PAGINATION_SETTINGS' => ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => ''],
] as $key => $value) if (!defined($key)) define($key, $value);
date_default_timezone_set('UTC');
ini_set('session.save_path', $scratch . '/sessions');
ini_set('session.gc_probability', '0');
ini_set('zend.exception_ignore_args', '1');
$_SERVER += ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.test', 'HTTPS' => $httpFixture ? 'off' : 'on', 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_USER_AGENT' => 'Auth integration test'];
require $project . '/helpers/helpers.php';
FBL\Language::$lang_data = require APP . '/Languages/ru.php';
foreach (glob(APP . '/Languages/ru/auth/*.php') ?: [] as $languageFile) FBL\Language::$lang_data += require $languageFile;
$app = (new ReflectionClass(FBL\Application::class))->newInstanceWithoutConstructor();
FBL\Application::$app = $app;
(new ReflectionProperty(FBL\Application::class, 'installationStatus'))->setValue($app, ['state' => 'not_installed', 'errors' => []]);
$app->cache = new FBL\Cache();
$app->hooks = new FBL\Plugins\HookManager();
$app->events = new FBL\Plugins\EventManager();
$app->set('lang', ['code' => 'ru']);
$app->cache->set('session:policy', ['session_lifetime' => '172800'], 3600);
$app->session = new FBL\Session();
$app->view = new FBL\View('default');
if (!session()->get('needCSRFToken')) session()->set('needCSRFToken', bin2hex(random_bytes(32)));
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:unix_socket=' . $scratch . '/db.sock;charset=utf8mb4', 'root', '', $options);
if (!$httpFixture) foreach (['fbl_auth_upgrade', 'fbl_auth_fresh', 'fbl_auth_conflict'] as $ownedDatabase) $server->exec('DROP DATABASE IF EXISTS ' . $ownedDatabase);
$checks = [];
function checkAuth(string $name, bool $condition): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $name);
    $checks[] = $name;
}
function useAuthDatabase(string $name): void {
    global $server, $app, $options, $scratch;
    if (!in_array($name, ['fbl_auth_upgrade', 'fbl_auth_fresh', 'fbl_auth_conflict'], true)) throw new RuntimeException('Unsafe database.');
    $server->exec('CREATE DATABASE IF NOT EXISTS ' . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = new PDO('mysql:unix_socket=' . $scratch . '/db.sock;dbname=' . $name . ';charset=utf8mb4', 'root', '', $options);
    $app->db = new FBL\Database($pdo);
    (new ReflectionProperty(App\Models\SiteSetting::class, 'cache'))->setValue(null, null);
    $app->cache->clear();
    (new ReflectionProperty(App\Services\SchemaManifest::class, 'tables'))->setValue(null, []);
    (new ReflectionProperty(App\Services\SchemaManifest::class, 'presence'))->setValue(null, []);
}
function authPolicy(array $values = []): array {
    $policy = $values + App\Services\AuthSettings::DEFAULTS;
    (new App\Models\SiteSetting())->setMany($policy + ['allow_email_password_reset' => '1']);
    return $policy;
}
function legacyAuthUser(string $login = 'legacy', string $email = 'legacy@example.test'): int {
    db()->query('INSERT INTO users (name, login, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        ['Existing user', $login, $email, password_hash('Old-Passw0rd!', PASSWORD_DEFAULT), 'user', date('Y-m-d H:i:s')]);
    return (int)db()->getInsertId();
}
function clearAuthLimits(): void {
    foreach (glob(CACHE . '/rate-limits/*.json') ?: [] as $file) unlink($file);
}
class AuthTestRedirect extends RuntimeException { public function __construct(public string $url) { parent::__construct('Test redirect'); } }
class AuthTestResponse extends FBL\Response { public function redirect($url = '') { throw new AuthTestRedirect((string)$url); } }
class AuthTestMail extends App\Services\MailService {
    public bool $deliver = true;
    public bool $enabled = true;
    public array $messages = [];
    public function __construct() {}
    public function isEnabled(): bool { return $this->enabled; }
    public function sendTemplate(array $to, string $subject, string $template, array $data = [], array $attachments = []): bool {
        $this->messages[] = compact('to', 'subject', 'template', 'data');
        return $this->deliver;
    }
    public function token(string $field): string {
        $data = end($this->messages)['data'];
        parse_str((string)parse_url($data[$field], PHP_URL_QUERY), $query);
        return (string)$query['token'];
    }
    public function code(): string { return (string)(end($this->messages)['data']['verification_code'] ?? ''); }
}
class AuthTestController extends App\Controllers\AuthController {
    public function __construct(public AuthTestMail $fakeMail) { parent::__construct(); }
    protected function mailService(): App\Services\MailService { return $this->fakeMail; }
}
class AuthTestTheme extends FBL\ThemeManager {
    public array $last = [];
    public function __construct() {}
    public function render($template, $data = []): string { $this->last = compact('template', 'data'); return view()->renderPartial($template, $data); }
    public function getLegalInformationMenu(): array { return []; }
    public function invalidateRuntime(): void {}
}
class AuthBrowserTheme extends FBL\ThemeManager {
    public function render($template, $data = []): string {
        $file = $this->resolveFile('templates', (string)$template)['path'] ?? null;
        if (!$file) return view()->renderPartial((string)$template, $data);
        return $this->evaluateFile($file, $data);
    }
    public function getLegalInformationMenu(): array { return []; }
}
class AuthBrowserMail extends AuthTestMail {
    public function sendTemplate(array $to, string $subject, string $template, array $data = [], array $attachments = []): bool {
        $sent = parent::sendTemplate($to, $subject, $template, $data, $attachments);
        file_put_contents(STORAGE . '/test-mail.json', json_encode(end($this->messages), JSON_THROW_ON_ERROR));
        return $sent;
    }
}
class AuthFailDatabase extends FBL\Database {
    public string $failAt = '';
    public function query(string $query, array $params = []): static {
        if ($this->failAt !== '' && str_contains($query, $this->failAt)) throw new RuntimeException('Injected storage failure');
        return parent::query($query, $params);
    }
    public function commit(): bool {
        if ($this->failAt === 'commit') return false;
        return parent::commit();
    }
}
function authRequest(array $post, array $get = [], string $method = 'POST'): void {
    $_GET = $get; $_POST = $post; $_FILES = [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/test' . ($get ? '?' . http_build_query($get) : '');
    app()->request = new FBL\Request($_SERVER['REQUEST_URI']);
}
function authSubmit(AuthTestController $controller, string $action, array $post, array $get = [], bool $csrf = true): string {
    if ($csrf) $post['needCSRFToken'] = session()->get('needCSRFToken');
    authRequest($post, $get);
    try { $controller->$action(); } catch (AuthTestRedirect $redirect) { return $redirect->url; }
    throw new RuntimeException('Expected redirect: ' . $action);
}
function latestAuthVerification(int $userId): array {
    return db()->query('SELECT * FROM user_email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId])->getOne() ?: [];
}
function legacyVerificationToken(int $userId, string $email): string {
    $token = bin2hex(random_bytes(32));
    db()->query('INSERT INTO user_email_verifications (user_id, email, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$userId, $email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 86400), date('Y-m-d H:i:s')]);
    return $token;
}
require __DIR__ . '/security_log_presentation.php';

if ($httpFixture) {
    useAuthDatabase('fbl_auth_fresh');
    App\Services\SchemaManifest::rebuild();
    app()->response = new FBL\Response(); app()->theme = new AuthBrowserTheme();
    app()->request = new FBL\Request($_SERVER['REQUEST_URI']);
    $controller = new AuthTestController(new AuthBrowserMail());
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '/fixture-security-logs') {
        $locale = in_array($_GET['lang'] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $_GET['lang'] : 'ru';
        FBL\Language::$lang_data = require APP . '/Languages/' . $locale . '.php';
        echo '<!doctype html><html lang="' . $locale . '" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"></head><body>' . renderSecurityLogFixture() . '</body></html>';
        exit;
    }
    if ($path === '/fixture-mail') { header('Content-Type: application/json'); echo file_get_contents(STORAGE . '/test-mail.json'); exit; }
    if ($path === '/fixture-frame') { echo '<!doctype html><html><body style="margin:0"><iframe id="mobile-frame" src="/fixture-policy" style="width:390px;height:1100px;border:0"></iframe></body></html>'; exit; }
    if ($path === '/fixture-policy') {
        authPolicy(['auth_phone_mode' => 'required', 'auth_login_mode' => 'automatic', 'auth_login_phone' => '1']);
        clearAuthLimits(); session()->clear(); app()->regenerateCSRFToken();
        response()->redirect('/register');
    }
    if ($path === '/fixture-session') { header('Content-Type: application/json'); echo json_encode(session()->get('user')); exit; }
    if ($path === '/') { echo '<!doctype html><h1 id="public-home">Public home</h1>'; exit; }
    if ($path === '/profile') {
        echo '<!doctype html><title>Signed in</title><h1>Signed in</h1><span id="logged-in">' . (int)session()->get('user.id') . '</span><div id="warning">' . htmlSC((string)session()->getFlash('warning')) . '</div><form action="/logout" method="post">' . get_csrf_field() . '<button>Sign out</button></form>'; exit;
    }
    if ($path === '/logout') {
        app()->router = new FBL\Router(request(), response());
        app()->router->post('/logout', [App\Controllers\AuthController::class, 'logout'])->withoutCSRFToken();
        app()->router->dispatch(); exit;
    }
    $actions = ['/register' => 'register', '/login' => 'login', '/verify-email' => 'verifyEmail', '/forgot-password' => 'forgotPassword', '/reset-password' => 'resetPassword'];
    if (!isset($actions[$path])) { http_response_code(404); exit; }
    $html = $controller->{$actions[$path]}();
    echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auth browser check</title><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"></head><body>';
    foreach (['error', 'success', 'warning'] as $flash) if ($message = session()->getFlash($flash)) echo '<div role="status" class="alert alert-' . $flash . ' m-3">' . htmlSC($message) . '</div>';
    echo $html . '</body></html>';
    exit;
}

try {
    useAuthDatabase('fbl_auth_upgrade');
    (new App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/schema.sql'));
    db()->query('ALTER TABLE users ADD COLUMN locale VARCHAR(12) NULL, ADD COLUMN admin_locale VARCHAR(12) NULL');
    $legacyId = legacyAuthUser();
    $snapshot = db()->findOne('users', $legacyId);
    // Real old schema + source, no mocks of token persistence.
    App\Services\SchemaMigration::run(function (): void {
        (new App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/migrations/20260620_mail_security_and_2fa_recovery.sql'));
    });
    $source = shell_exec('git -C ' . escapeshellarg(ROOT) . ' show 152127f3:app/Models/User.php');
    if (!is_string($source)) throw new RuntimeException('Unable to read baseline.');
    eval(preg_replace('/class User\b/', 'class AuthBaselineUser', substr($source, 5), 1));
    $baseline = new App\Models\AuthBaselineUser();
    $first = $baseline->createPasswordResetToken('legacy@example.test');
    checkAuth('Baseline: first reset link opens', (bool)$baseline->findActivePasswordResetByToken($first));
    $baseline->createPasswordResetToken('legacy@example.test');
    checkAuth('Reproduced: resend revokes an already opened reset link before mail delivery', !$baseline->findActivePasswordResetByToken($first));
    authRequest(['token' => $first], ['token' => '']);
    checkAuth('Reproduced: baseline GET precedence replaces valid POST token with empty query value', request()->get('token', request()->post('token', '')) === '');
    db()->query('DELETE FROM password_resets');

    $migrations = (new App\Services\MigrationRunner())->run(db(), ['20261019_auth_identities.php', '20261006_create_user_sessions.sql']);
    $previousReleaseLink = legacyVerificationToken($legacyId, 'legacy@example.test');
    $migrations = array_merge($migrations, (new App\Services\MigrationRunner())->run(db(), ['20261020_email_verification_codes.php']));
    checkAuth('Upgrade migrations executed through production runner', count($migrations) === 3 && App\Services\EmailVerification::codesAvailable());
    $after = db()->findOne('users', $legacyId);
    checkAuth('Upgrade preserves ID, username, email and password hash', array_intersect_key($after, array_flip(['id', 'login', 'email', 'password'])) === array_intersect_key($snapshot, array_flip(['id', 'login', 'email', 'password'])));
    checkAuth('Existing account not forced to verify email', (int)$after['email_verification_required'] === 0);
    checkAuth('Upgrade rerun is idempotent', (new App\Services\MigrationRunner())->run(db(), ['20261019_auth_identities.php', '20261020_email_verification_codes.php']) === []);
    authPolicy();
    checkAuth('Legacy user can sign in after migration', (bool)FBL\Auth::validateCredentials(['login' => 'legacy', 'password' => 'Old-Passw0rd!']));

    $users = new App\Models\User();
    $legacyVerification = new App\Services\EmailVerification(new AuthTestMail());
    checkAuth('Code migration preserves unconsumed links delivered before upgrade', $legacyVerification->valid($previousReleaseLink) && $legacyVerification->valid($previousReleaseLink));
    checkAuth('Legacy confirmation link remains one-use after upgrade', $legacyVerification->confirm($previousReleaseLink) && !$legacyVerification->confirm($previousReleaseLink));
    // Unsigned BIGINT is valid in the shipped schema, but not a PHP integer.
    $beforeClaimSource = shell_exec('git -C ' . escapeshellarg(ROOT) . ' show 67b8bb11:app/Models/User.php');
    eval(preg_replace('/class User\b/', 'class AuthBeforeClaimUser', substr($beforeClaimSource, 5), 1));
    db()->query('CREATE TABLE auth_claim_resets LIKE password_resets');
    $beforeClaim = new App\Models\AuthBeforeClaimUser();
    $claimFixed = new App\Models\User();
    foreach ([$beforeClaim, $claimFixed] as $model) (new ReflectionProperty($model, 'passwordResetsTable'))->setValue($model, 'auth_claim_resets');
    $largeToken = $claimFixed->createPasswordResetToken('legacy@example.test');
    db()->query('UPDATE auth_claim_resets SET id = ? WHERE token_hash = ?', ['18446744073709551614', hash('sha256', $largeToken)]);
    checkAuth('Reproduced token_claim_failed for valid unsigned BIGINT reset ID', !$beforeClaim->resetPasswordByToken($largeToken, 'Unsafe-Passw0rd!') && $beforeClaim->passwordResetFailureReason() === 'token_claim_failed');
    checkAuth('Claim failure rolls back link and password', $claimFixed->findActivePasswordResetByToken($largeToken) && password_verify('Old-Passw0rd!', $users->findById($legacyId)['password']));
    checkAuth('Unique hash claims unsigned BIGINT reset without narrowing its ID', $claimFixed->resetPasswordByToken($largeToken, 'Old-Passw0rd!') && !$claimFixed->resetPasswordByToken($largeToken, 'Replay-Passw0rd!'));
    db()->query('DROP TABLE auth_claim_resets');
    $data = ['name' => 'New User', 'login' => 'new-user', 'email' => ' USER@example.test ', 'password' => 'Test-Passw0rd!', 'password_confirmation' => 'Test-Passw0rd!'];
    checkAuth('Manual registration validates', $users->validateRegistration($data) === []);
    $id = $users->create($data);
    $row = $users->findById($id);
    checkAuth('Manual registration stores normalized email and username', $row['login'] === 'new-user' && $row['email'] === 'user@example.test');
    checkAuth('Registration stores password hash only', $row['password'] !== $data['password'] && password_verify($data['password'], $row['password']));
    checkAuth('New unconfirmed user cannot sign in', !FBL\Auth::validateCredentials(['login' => 'new-user', 'password' => $data['password']]));
    $mail = new AuthTestMail();
    $verification = new App\Services\EmailVerification($mail);
    checkAuth('Confirmation mail sent using mail service', $verification->request($id));
    $confirmCode = $mail->code();
    $confirmation = latestAuthVerification($id);
    checkAuth('Confirmation code is exactly six decimal digits', preg_match('/^[0-9]{6}$/D', $confirmCode) === 1);
    checkAuth('Confirmation code stored as salted password hash only', $confirmation['code_hash'] !== $confirmCode && password_verify($confirmCode, $confirmation['code_hash']) && !str_contains(json_encode($confirmation), '"' . $confirmCode . '"'));
    checkAuth('Confirmation code lifetime is ten minutes', abs(strtotime($confirmation['expires_at']) - time() - 600) < 3 && end($mail->messages)['data']['expires_in_minutes'] === 10);
    checkAuth('New code mail does not expose legacy link', empty(end($mail->messages)['data']['verification_url']));
    checkAuth('Email code confirms once for expected account', $verification->confirmCode('user@example.test', $confirmCode, $id) && !$verification->confirmCode('user@example.test', $confirmCode, $id));
    checkAuth('Confirmed user can sign in by email', (bool)FBL\Auth::validateCredentials(['login' => ' USER@EXAMPLE.TEST ', 'password' => $data['password']]));
    checkAuth('Normalized email uniqueness validation', isset($users->validateRegistration($data + ['phone' => ''])['email']));

    $app->response = new AuthTestResponse(); $app->theme = new AuthTestTheme();
    $controller = new AuthTestController($mail);
    $codeId = $users->create(array_replace($data, ['login' => 'code-security', 'email' => 'code-security@example.test']));
    clearAuthLimits(); $verification->request($codeId); $oldCode = $mail->code(); $oldCodeRow = latestAuthVerification($codeId);
    clearAuthLimits(); $verification->request($codeId); $newCode = $mail->code();
    checkAuth('Successful resend retires old record even if random digits happen to match', !empty(db()->query('SELECT used_at FROM user_email_verifications WHERE id = ?', [$oldCodeRow['id']])->getColumn()) && ($oldCode === $newCode || !$verification->confirmCode('code-security@example.test', $oldCode, $codeId)));
    checkAuth('Code cannot be used for a different expected user', !$verification->confirmCode('code-security@example.test', $newCode, $id));
    checkAuth('Code cannot be used for a different email', !$verification->confirmCode('other@example.test', $newCode, $codeId));
    checkAuth('Latest code remains valid for its own email and user', $verification->confirmCode(' CODE-SECURITY@EXAMPLE.TEST ', $newCode, $codeId));
    clearAuthLimits(); $verification->request($codeId); $leadingZeroRow = latestAuthVerification($codeId);
    db()->query('UPDATE user_email_verifications SET code_hash = ? WHERE id = ?', [password_hash('012345', PASSWORD_DEFAULT), $leadingZeroRow['id']]);
    checkAuth('Leading-zero confirmation remains a six-character string', $verification->confirmCode('code-security@example.test', '012345', $codeId));
    clearAuthLimits(); $verification->request($codeId); $limitedCode = $mail->code();
    foreach (['12345', '1234567', '12a456', '１２３４５６', '<img>'] as $malformed) {
        checkAuth('Malformed confirmation code safely rejected: ' . $malformed, !$verification->confirmCode('code-security@example.test', $malformed, $codeId) && (int)latestAuthVerification($codeId)['code_attempts'] === 0);
    }
    $wrongCode = $limitedCode === '999999' ? '888888' : '999999';
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        checkAuth('Incorrect code attempt ' . $attempt . ' is rejected and counted durably', !$verification->confirmCode('code-security@example.test', $wrongCode, $codeId) && (int)latestAuthVerification($codeId)['code_attempts'] === $attempt);
    }
    clearAuthLimits();
    checkAuth('Clearing IP/session limiter does not bypass five database attempts', !$verification->confirmCode('code-security@example.test', $limitedCode, $codeId));
    clearAuthLimits(); $verification->request($codeId); $expiredCode = $mail->code(); $expiredRow = latestAuthVerification($codeId);
    db()->query('UPDATE user_email_verifications SET expires_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 1), $expiredRow['id']]);
    checkAuth('Expired code cannot verify an account', !$verification->confirmCode('code-security@example.test', $expiredCode, $codeId));
    clearAuthLimits(); $verification->request($codeId); $beforeFailedMail = latestAuthVerification($codeId); $deliveredCode = $mail->code();
    clearAuthLimits(); $mail->deliver = false;
    checkAuth('Failed mail rolls back replacement code row', !$verification->request($codeId) && latestAuthVerification($codeId) === $beforeFailedMail);
    $mail->deliver = true;
    checkAuth('Delivered code survives failed resend', $verification->confirmCode('code-security@example.test', $deliveredCode, $codeId));
    clearAuthLimits(); $verification->request($codeId); $claimCode = $mail->code();
    db()->query('CREATE TRIGGER auth_verification_noop BEFORE UPDATE ON user_email_verifications FOR EACH ROW SET NEW.used_at = NULL');
    checkAuth('No-op verification claim cannot falsely succeed', !$verification->confirmCode('code-security@example.test', $claimCode, $codeId) && empty(latestAuthVerification($codeId)['used_at']));
    db()->query('DROP TRIGGER auth_verification_noop');
    checkAuth('Failed verification claim remains retryable after database repair', $verification->confirmCode('code-security@example.test', $claimCode, $codeId));

    $policyCase = 0;
    foreach (['manual', 'automatic'] as $mode) foreach (['off', 'optional', 'required'] as $phone) foreach ([true, false] as $required) {
        $policyCase++;
        $label = $mode . '/' . $phone . '/verification-' . ($required ? 'on' : 'off');
        authPolicy(['auth_login_mode' => $mode, 'auth_phone_mode' => $phone, 'auth_email_verification_required' => $required ? '1' : '0']);
        clearAuthLimits(); session()->remove('user'); session()->remove('auth.email_verification');
        $mail->enabled = $required;
        $beforeMail = count($mail->messages);
        $email = 'policy-' . $policyCase . '@example.test';
        $submitted = array_replace($data, ['login' => $mode === 'manual' ? 'policy-' . $policyCase : '', 'email' => strtoupper($email), 'phone' => $phone === 'required' ? '+7999000' . str_pad((string)$policyCase, 4, '0', STR_PAD_LEFT) : '', 'privacy_accepted' => '1']);
        $url = authSubmit($controller, 'register', $submitted);
        $registered = $users->findByEmail($email);
        checkAuth('Registration controller follows policy ' . $label, (bool)$registered && str_ends_with($url, $required ? '/verify-email' : '/profile'));
        checkAuth('Verification policy persisted without pretending email was verified ' . $label, (int)$registered['email_verification_required'] === (int)$required && empty($registered['email_verified_at']));
        checkAuth('Registration sends mail only when required ' . $label, count($mail->messages) === $beforeMail + (int)$required);
        if ($required) {
            checkAuth('Registration carries normalized email in private session ' . $label, session()->get('auth.email_verification.email') === $email && (int)session()->get('auth.email_verification.user_id') === (int)$registered['id'] && !check_auth());
            authRequest([], [], 'GET'); $verificationForm = $controller->verifyEmail();
            checkAuth('Next page displays registered email and code field ' . $label, str_contains($verificationForm, 'value="' . $email . '"') && str_contains($verificationForm, 'name="verification_code"'));
            if ($policyCase === 1) {
                $url = authSubmit($controller, 'verifyEmail', ['verification_action' => 'confirm_code', 'email' => $email, 'verification_code' => $mail->code(), 'needCSRFToken' => 'forged'], [], false);
                checkAuth('Invalid verification CSRF preserves account and code', str_ends_with($url, '/verify-email') && empty($users->findById((int)$registered['id'])['email_verified_at']) && empty(latestAuthVerification((int)$registered['id'])['used_at']));
            }
            $url = authSubmit($controller, 'verifyEmail', ['verification_action' => 'confirm_code', 'email' => $email, 'verification_code' => $mail->code()]);
            checkAuth('Same-browser code completes registration into profile ' . $label, str_ends_with($url, '/profile') && (int)session()->get('user.id') === (int)$registered['id']);
        } else {
            checkAuth('SMTP-disabled registration signs in immediately ' . $label, (int)session()->get('user.id') === (int)$registered['id'] && empty(session()->get('auth.email_verification')) && (bool)FBL\Auth::validateCredentials(['login' => $email, 'password' => $data['password']]));
        }
    }
    $mail->enabled = true; authPolicy(); clearAuthLimits(); session()->remove('user'); session()->remove('auth.email_verification');
    $pendingId = $users->create(array_replace($data, ['login' => 'login-pending', 'email' => 'login-pending@example.test']));
    $url = authSubmit($controller, 'login', ['login' => 'login-pending@example.test', 'password' => 'wrong-password']);
    checkAuth('Wrong password does not disclose unverified account', str_ends_with($url, '/login') && session()->getFlash('error') === return_translation('auth_login_invalid_credentials') && empty(session()->get('auth.email_verification')));
    clearAuthLimits();
    $url = authSubmit($controller, 'login', ['login' => 'login-pending@example.test', 'password' => $data['password']]);
    checkAuth('Correct password explains required email confirmation instead of bad credentials', str_ends_with($url, '/verify-email') && session()->getFlash('error') === return_translation('auth_email_not_verified') && !check_auth() && session()->get('auth.email_verification.email') === 'login-pending@example.test');
    $guestId = $users->create(array_replace($data, ['login' => 'guest-confirm', 'email' => 'guest-confirm@example.test']));
    session()->remove('auth.email_verification'); clearAuthLimits();
    $url = authSubmit($controller, 'verifyEmail', ['verification_action' => 'resend', 'email' => ' GUEST-CONFIRM@EXAMPLE.TEST ']);
    $guestCode = $mail->code(); $guestNotice = session()->getFlash('success');
    checkAuth('Guest resend remembers typed email without exposing it in redirect URL', str_ends_with($url, '/verify-email') && parse_url($url, PHP_URL_QUERY) === null && session()->get('auth.email_verification.email') === 'guest-confirm@example.test' && !$users->findById($guestId)['email_verified_at']);
    $url = authSubmit($controller, 'verifyEmail', ['verification_action' => 'confirm_code', 'email' => 'guest-confirm@example.test', 'verification_code' => $guestCode]);
    checkAuth('Out-of-registration email confirmation does not sign in without password', str_ends_with($url, '/login') && !check_auth() && !empty($users->findById($guestId)['email_verified_at']));
    clearAuthLimits(); $beforeMail = count($mail->messages);
    authSubmit($controller, 'verifyEmail', ['verification_action' => 'resend', 'email' => 'unknown-code@example.test']);
    checkAuth('Unknown resend does not expose account existence or send mail', count($mail->messages) === $beforeMail && session()->getFlash('success') === $guestNotice);
    authPolicy(['auth_email_verification_required' => '0']); clearAuthLimits();
    (require ROOT . '/database/migrations/20261020_email_verification_codes.php')();
    checkAuth('Retrying migration preserves administrators disabled verification choice', !(new App\Services\AuthSettings())->emailVerificationRequired());
    $url = authSubmit($controller, 'login', ['login' => 'login-pending@example.test', 'password' => $data['password']]);
    checkAuth('Disabling verification restores login for existing pending accounts', str_ends_with($url, '/profile') && (int)session()->get('user.id') === $pendingId);
    authPolicy(); clearAuthLimits(); session()->remove('user'); session()->remove('auth.email_verification');
    $mail->deliver = false;
    $url = authSubmit($controller, 'register', array_replace($data, ['login' => 'mail-retry', 'email' => 'mail-retry@example.test', 'privacy_accepted' => '1']));
    $mailRetryId = (int)$users->findByEmail('mail-retry@example.test')['id'];
    checkAuth('Signup SMTP failure retains pending email and shows honest retry warning', str_ends_with($url, '/verify-email') && $mailRetryId > 0 && !latestAuthVerification($mailRetryId) && session()->get('auth.email_verification.email') === 'mail-retry@example.test' && session()->getFlash('warning') === return_translation('auth_verify_send_failed') && !check_auth());
    $mail->deliver = true; clearAuthLimits();
    authSubmit($controller, 'verifyEmail', ['verification_action' => 'resend', 'email' => 'mail-retry@example.test']);
    $url = authSubmit($controller, 'verifyEmail', ['verification_action' => 'confirm_code', 'email' => 'mail-retry@example.test', 'verification_code' => $mail->code()]);
    checkAuth('Retrying failed signup email preserves same-browser profile completion', str_ends_with($url, '/profile') && (int)session()->get('user.id') === $mailRetryId);
    session()->remove('user'); session()->remove('auth.email_verification');
    clearAuthLimits();
    $token = $users->createPasswordResetToken('legacy@example.test');
    $resetRow = db()->query('SELECT * FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)])->getOne();
    checkAuth('Password reset lifetime is 60 minutes', abs(strtotime($resetRow['expires_at']) - time() - 3600) < 3);
    authRequest([], ['token' => $token], 'GET');
    $form = $controller->resetPassword();
    checkAuth('Real reset GET carries token and distinct CSRF hidden fields', str_contains($form, 'name="token" value="' . $token . '"') && str_contains($form, 'name="needCSRFToken"'));
    $url = authSubmit($controller, 'resetPassword', ['token' => $token, 'password' => 'New-Passw0rd!', 'password_confirmation' => 'New-Passw0rd!'], ['token' => '']);
    checkAuth('Real controller GET to POST succeeds even with empty query token', str_ends_with($url, '/login') && password_verify('New-Passw0rd!', $users->findById($legacyId)['password']));
    checkAuth('New password signs in', (bool)FBL\Auth::validateCredentials(['login' => 'legacy', 'password' => 'New-Passw0rd!']));
    checkAuth('Old password rejected', !FBL\Auth::validateCredentials(['login' => 'legacy', 'password' => 'Old-Passw0rd!']));
    checkAuth('Reset token cannot be reused', !$users->resetPasswordByToken($token, 'Another-Passw0rd!'));

    checkAuth('Replay diagnostic is distinct', $users->passwordResetFailureReason() === 'token_used');
    foreach (['+7 (999) 123-45-67', '8 999 123 45 67', '9991234567', '+79991234567'] as $phone) {
        checkAuth('Phone normalization: ' . $phone, App\Services\AuthIdentity::phone($phone) === '+79991234567');
    }
    foreach (['+1 999 123 45 67', '123', '+799912345678', 'abc79991234567', '+7 000 000-00-00'] as $phone) {
        $rejected = false;
        try { App\Services\AuthIdentity::phone($phone); } catch (InvalidArgumentException) { $rejected = true; }
        checkAuth('Invalid phone rejected: ' . $phone, $rejected);
    }
    authPolicy(['auth_login_mode' => 'automatic', 'auth_phone_mode' => 'required', 'auth_login_phone' => '1']);
    $automatic = array_replace($data, ['login' => '', 'email' => 'auto@example.test', 'phone' => '8 999 123 45 67']);
    checkAuth('Required phone missing rejected', isset($users->validateRegistration(array_replace($automatic, ['phone' => '']))['phone']));
    checkAuth('Automatic username with required phone validates', $users->validateRegistration($automatic) === []);
    $autoId = $users->create($automatic); $auto = $users->findById($autoId);
    checkAuth('Automatic username is unique nonempty slug', (bool)preg_match('/^user-[a-f0-9]{24}$/D', $auto['login']) && $auto['login'] !== $row['login']);
    checkAuth('Canonical phone stored for lookup and profile', $auto['phone'] === '+79991234567' && $auto['phone_normalized'] === '+79991234567');
    checkAuth('Duplicate phone validation', isset($users->validateRegistration(array_replace($automatic, ['email' => 'other@example.test']))['phone']));
    $conflict = false;
    try { $users->create(array_replace($automatic, ['email' => 'other@example.test'])); } catch (PDOException $e) { $conflict = $e->getCode() === '23000'; }
    checkAuth('Database enforces unique canonical phone', $conflict);
    $conflict = false;
    try { legacyAuthUser('case-conflict', ' AUTO@example.test '); } catch (PDOException $e) { $conflict = $e->getCode() === '23000'; }
    checkAuth('Database enforces unique normalized email with whitespace/case', $conflict);
    clearAuthLimits(); $verification->request($autoId); $autoCode = $mail->code();
    checkAuth('Confirmation mail includes generated username', end($mail->messages)['data']['account_login'] === $auto['login']);
    checkAuth('Automatic user confirmed', $verification->confirmCode('auto@example.test', $autoCode, $autoId));
    foreach ([$auto['login'], ' AUTO@EXAMPLE.TEST ', '+7 (999) 123-45-67', '89991234567', '9991234567'] as $identifier) {
        checkAuth('Unified login resolves ' . $identifier, (int)(FBL\Auth::validateCredentials(['login' => $identifier, 'password' => $data['password']])['id'] ?? 0) === $autoId);
    }
    foreach (['auth_login_login' => $auto['login'], 'auth_login_email' => 'auto@example.test', 'auth_login_phone' => '+79991234567'] as $setting => $identifier) {
        authPolicy(['auth_phone_mode' => 'required', 'auth_login_phone' => '1', $setting => '0']);
        checkAuth('Server enforces switch ' . $setting, !FBL\Auth::validateCredentials(['login' => $identifier, 'password' => $data['password']]));
    }
    authPolicy(['auth_phone_mode' => 'optional']);
    $optional = array_replace($data, ['login' => 'optional-user', 'email' => 'optional@example.test', 'phone' => '']);
    checkAuth('Optional phone can be omitted', $users->validateRegistration($optional) === [] && $users->findById($users->create($optional))['phone_normalized'] === null);
    authPolicy();
    $off = array_replace($data, ['login' => 'phone-off', 'email' => 'off@example.test', 'phone' => 'not-a-number']);
    checkAuth('Disabled phone input ignored and not saved', $users->validateRegistration($off) === [] && $users->findById($users->create($off))['phone_normalized'] === null);
    $settings = new App\Services\AuthSettings();
    checkAuth('All login methods off rejected', $settings->validate($settings->normalize(['auth_login_login' => 0, 'auth_login_email' => 0])) !== []);
    checkAuth('Phone login while phone off rejected', $settings->validate($settings->normalize(['auth_login_phone' => 1])) !== []);
    checkAuth('Phone-only optional registration policy rejected', $settings->validate($settings->normalize(['auth_phone_mode' => 'optional', 'auth_login_phone' => 1])) !== []);
    checkAuth('Policy cannot strand legacy users without phone', $settings->validate($settings->normalize(['auth_phone_mode' => 'required', 'auth_login_phone' => 1])) !== []);
    checkAuth('MAXIPAPA-style policy supported without site-specific logic', $settings->validate($settings->normalize(['auth_phone_mode' => 'required', 'auth_login_mode' => 'automatic', 'auth_login_login' => 1, 'auth_login_email' => 1, 'auth_login_phone' => 1])) === []);
    authPolicy(['auth_phone_mode' => 'required', 'auth_login_phone' => '1']);
    $numericId = legacyAuthUser('79991234567', 'numeric@example.test');
    checkAuth('Ambiguous username/phone never resolves wrong account', !App\Services\AuthIdentity::findForLogin('79991234567'));
    checkAuth('Formatted phone remains unambiguous', (int)App\Services\AuthIdentity::findForLogin('+79991234567')['id'] === $autoId);
    $profile = ['name' => $auto['name'], 'login' => $auto['login'], 'email' => $auto['email'], 'phone' => '+79991234568', 'current_password' => ''];
    checkAuth('Phone change requires current password', isset($users->validateProfileUpdate($profile, $autoId)['current_password']));
    $profile['current_password'] = $data['password'];
    checkAuth('Authenticated phone change validates', $users->validateProfileUpdate($profile, $autoId) === []);
    $profile['locale'] = 'en'; $users->updateProfile($autoId, $profile);
    checkAuth('Phone change and locale persist together', $users->findById($autoId)['phone_normalized'] === '+79991234568' && $users->findById($autoId)['locale'] === 'en');
    checkAuth('Legacy user not forced to add phone to edit profile', $users->validateProfileUpdate(['name' => 'Existing user', 'login' => 'legacy', 'email' => 'legacy@example.test', 'phone' => ''], $legacyId) === []);
    $profile['email'] = 'changed@example.test';
    clearAuthLimits();
    checkAuth('New email verification requested', $verification->request($autoId, $profile['email']));
    $emailChange = $mail->code();
    checkAuth('Current email stays active until confirmation', $users->findById($autoId)['email'] === 'auto@example.test' && $users->findById($autoId)['pending_email'] === $profile['email']);
    $version = (int)$users->findById($autoId)['session_version'];
    checkAuth('Confirming new email swaps address and revokes old session version', $verification->confirmCode($profile['email'], $emailChange, $autoId) && $users->findById($autoId)['email'] === $profile['email'] && (int)$users->findById($autoId)['session_version'] === $version + 1);
    checkAuth('New email uniqueness enforced before confirmation send', !$verification->request($autoId, 'legacy@example.test'));
    clearAuthLimits(); $verification->request($autoId); $existingVerification = $mail->code();
    checkAuth('Confirmation resend rate limited', !$verification->request($autoId));
    clearAuthLimits(); $mail->deliver = false;
    checkAuth('Failed confirmation send preserves delivered code and permits retry', !$verification->request($autoId) && $verification->confirmCode($profile['email'], $existingVerification, $autoId));
    $mail->deliver = true;
    checkAuth('Confirmation send can be retried after failure', $verification->request($autoId));

    clearAuthLimits(); authPolicy();
    $sentBefore = count($mail->messages);
    authSubmit($controller, 'forgotPassword', ['reset_email' => 'legacy@example.test']);
    $delivered = $mail->token('reset_url');
    checkAuth('Known email recovery sends a message', count($mail->messages) === $sentBefore + 1 && $users->findActivePasswordResetByToken($delivered));
    $publicKnown = session()->getFlash('success');
    checkAuth('Recovery success explicitly confirms actual mail delivery', $publicKnown === return_translation('auth_reset_request_success'));
    clearAuthLimits(); $sentBefore = count($mail->messages);
    authSubmit($controller, 'forgotPassword', ['reset_email' => 'unknown@example.test']);
    checkAuth('Unknown email recovery sends nothing and shows explicit localized error', count($mail->messages) === $sentBefore && session()->getFlash('error') === return_translation('auth_reset_email_not_found') && session()->get('form_errors.reset_email'));
    clearAuthLimits(); $mail->deliver = false;
    authSubmit($controller, 'forgotPassword', ['reset_email' => 'legacy@example.test']);
    checkAuth('SMTP failure preserves previously delivered recovery link', (bool)$users->findActivePasswordResetByToken($delivered));
    checkAuth('SMTP failure does not pretend a message was sent', session()->getFlash('error') === return_translation('auth_reset_request_error'));
    $mail->deliver = true; $sentBefore = count($mail->messages);
    authSubmit($controller, 'forgotPassword', ['reset_email' => 'legacy@example.test']);
    checkAuth('Immediate recovery retry after SMTP failure allowed', count($mail->messages) === $sentBefore + 1 && $users->findActivePasswordResetByToken($delivered));
    $sentBefore = count($mail->messages);
    authSubmit($controller, 'forgotPassword', ['reset_email' => 'legacy@example.test']);
    checkAuth('Repeat recovery requests rate limited with honest error', count($mail->messages) === $sentBefore && session()->getFlash('error') === return_translation('auth_reset_request_limited'));
    $retry = $mail->token('reset_url');
    $beforeHash = $users->findById($legacyId)['password'];
    authSubmit($controller, 'resetPassword', ['token' => $retry, 'password' => 'Mismatch-Passw0rd!', 'password_confirmation' => 'different']);
    checkAuth('Mismatched passwords preserve token and old password', $users->findActivePasswordResetByToken($retry) && $users->findById($legacyId)['password'] === $beforeHash && session()->get('form_errors.password_confirmation'));
    authSubmit($controller, 'resetPassword', ['token' => $retry, 'password' => 'Badcsrf-Passw0rd!', 'password_confirmation' => 'Badcsrf-Passw0rd!', 'needCSRFToken' => 'invalid'], [], false);
    checkAuth('Invalid CSRF preserves token and old password', $users->findActivePasswordResetByToken($retry) && $users->findById($legacyId)['password'] === $beforeHash && session()->get('form_errors.needCSRFToken'));
    $expired = $users->createPasswordResetToken('legacy@example.test');
    db()->query('UPDATE password_resets SET expires_at = ? WHERE token_hash = ?', [date('Y-m-d H:i:s', time() - 1), hash('sha256', $expired)]);
    checkAuth('Expired token cannot reset password', !$users->resetPasswordByToken($expired, 'Expired-Passw0rd!') && $users->passwordResetFailureReason() === 'token_expired');
    checkAuth('Malformed and unknown tokens distinguished safely', !$users->resetPasswordByToken('invalid', 'unused') && $users->passwordResetFailureReason() === 'invalid_token_format' && !$users->resetPasswordByToken(str_repeat('a', 64), 'unused') && $users->passwordResetFailureReason() === 'token_missing');
    $connection = (new ReflectionProperty(FBL\Database::class, 'connection'))->getValue(db());
    $normalDb = db(); $failureDb = new AuthFailDatabase($connection);
    db()->query('CREATE TRIGGER auth_claim_noop BEFORE UPDATE ON password_resets FOR EACH ROW SET NEW.used_at = NULL');
    authSubmit($controller, 'resetPassword', ['token' => $retry, 'password' => 'Noop-Passw0rd!', 'password_confirmation' => 'Noop-Passw0rd!']);
    $claimLog = json_decode(db()->query("SELECT reason FROM security_logs WHERE event = 'password_reset_complete' ORDER BY id DESC LIMIT 1")->getColumn(), true);
    checkAuth('No-op claim logs safe stage and exact affected row count', ($claimLog['code'] ?? '') === 'token_claim_failed' && ($claimLog['details'] ?? []) === ['stage' => 'claim', 'affected_rows' => 0]);
    checkAuth('Claim failure is not mislabeled as expired to user', session()->getFlash('error') === return_translation('auth_reset_save_error') && $users->findById($legacyId)['password'] === $beforeHash && $users->findActivePasswordResetByToken($retry));
    db()->query('DROP TRIGGER auth_claim_noop');
    db()->query("CREATE TRIGGER auth_password_reject BEFORE UPDATE ON users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected database rejection'");
    authSubmit($controller, 'resetPassword', ['token' => $retry, 'password' => 'Rejected-Passw0rd!', 'password_confirmation' => 'Rejected-Passw0rd!']);
    $storageLog = json_decode(db()->query("SELECT reason FROM security_logs WHERE event = 'password_reset_complete' ORDER BY id DESC LIMIT 1")->getColumn(), true);
    checkAuth('Real PDO rejection exposes safe SQLSTATE and driver code', $storageLog['code'] === 'storage_failure' && $storageLog['details'] === ['stage' => 'password', 'sql_state' => '45000', 'driver_code' => 1644]);
    checkAuth('Real PDO rejection rolls back both password and link', $users->findById($legacyId)['password'] === $beforeHash && $users->findActivePasswordResetByToken($retry));
    db()->query('DROP TRIGGER auth_password_reject');
    foreach (['SET password =', 'UPDATE user_sessions', 'commit'] as $point) {
        $failureDb->failAt = $point; app()->db = $failureDb;
        checkAuth('Reset fails safely at ' . $point, !$users->resetPasswordByToken($retry, 'Rollback-Passw0rd!') && $users->passwordResetFailureReason() === 'storage_failure');
        checkAuth('Safe diagnostic identifies failure stage ' . $point, $users->passwordResetFailureDetails() === ['stage' => match ($point) { 'SET password =' => 'password', 'UPDATE user_sessions' => 'sessions', 'commit' => 'commit' }]);
        app()->db = $normalDb;
        checkAuth('Reset transaction rolls back password and token at ' . $point, $users->findById($legacyId)['password'] === $beforeHash && (bool)$users->findActivePasswordResetByToken($retry) && !db()->inTransaction());
    }
    $sessions = new App\Services\UserSessionService();
    $sessionVersion = (int)$users->findById($legacyId)['session_version'];
    $sessions->register($legacyId, $sessionVersion, 'isolated-old-session', $_SERVER, 3600);
    checkAuth('Existing registered session initially valid', $sessions->validate($legacyId, $sessionVersion, 'isolated-old-session', 3600));
    checkAuth('Reset succeeds after rollback retry', $users->resetPasswordByToken($retry, 'Final-Passw0rd!'));
    checkAuth('All older reset links consumed on success', !$users->findActivePasswordResetByToken($delivered));
    checkAuth('Old session rejected and global version incremented', !$sessions->validate($legacyId, $sessionVersion, 'isolated-old-session', 3600) && (int)$users->findById($legacyId)['session_version'] === $sessionVersion + 1);
    checkAuth('Token raw value never persisted in database', db()->query('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$retry])->getColumn() == 0);
    $_SERVER['REQUEST_URI'] = '/reset-password?token=' . $retry;
    log_error_details('Injected auth diagnostic', [], new RuntimeException('Must not log ' . $retry . ' Final-Passw0rd!'));
    checkAuth('Error diagnostic does not expose raw token, password or full recovery URL', !str_contains((string)file_get_contents(ERROR_LOGS), $retry) && !str_contains((string)file_get_contents(ERROR_LOGS), 'Final-Passw0rd!'));
    checkAuth('Security journal contains no raw token or password', !str_contains(json_encode(db()->query('SELECT * FROM security_logs')->get()), $retry) && !str_contains(json_encode(db()->query('SELECT * FROM security_logs')->get()), 'Final-Passw0rd!'));
    clearAuthLimits(); session()->remove('user');
    $secretService = new App\Services\TwoFactorService(); $secret = $secretService->generateSecret();
    $users->enableTwoFactor($legacyId, $secret, ['test-recovery-code']);
    $url = authSubmit($controller, 'login', ['login' => 'legacy@example.test', 'password' => 'Final-Passw0rd!']);
    checkAuth('Email login still requires existing 2FA challenge', str_ends_with($url, '/two-factor-challenge') && !session()->get('user') && (int)session()->get('auth.two_factor_pending.user_id') === $legacyId);
    $url = authSubmit($controller, 'twoFactorChallenge', ['code' => $secretService->codeAtTimestamp($secret, time())]);
    checkAuth('Existing 2FA code completes login', str_ends_with($url, '/profile') && (int)session()->get('user.id') === $legacyId);
    $priorVersion = (int)$users->findById($legacyId)['session_version'];
    authSubmit($controller, 'settings', ['profile_action' => 'password', 'current_password' => 'Final-Passw0rd!', 'password' => 'Profile-Passw0rd!', 'password_confirmation' => 'Profile-Passw0rd!']);
    checkAuth('Profile password change preserves this authenticated device', (int)session()->get('user.id') === $legacyId && (int)session()->get('user.session_version') === $priorVersion + 1);
    clearAuthLimits();
    authSubmit($controller, 'settings', ['profile_action' => 'details', 'name' => 'Existing user', 'login' => 'legacy', 'email' => 'profile-changed@example.test', 'current_password' => 'Profile-Passw0rd!']);
    checkAuth('Actual profile controller keeps original email until confirmation', $users->findById($legacyId)['email'] === 'legacy@example.test' && $users->findById($legacyId)['pending_email'] === 'profile-changed@example.test' && end($mail->messages)['to'] === ['profile-changed@example.test']);
    authPolicy(['auth_phone_mode' => 'required', 'auth_login_phone' => '1']);
    $legacyProfile = view()->renderPartial('auth/profile_information', ['user' => $users->findById($legacyId)]);
    checkAuth('Legacy profile offers nonblocking phone completion', str_contains($legacyProfile, return_translation('auth_phone_legacy_notice')) && str_contains($legacyProfile, 'data-auth-phone'));
    foreach (['manual', 'automatic'] as $loginMode) foreach (['off', 'optional', 'required'] as $phoneMode) {
        $policy = ['auth_login_mode' => $loginMode, 'auth_phone_mode' => $phoneMode] + App\Services\AuthSettings::DEFAULTS;
        foreach (['core', 'theme'] as $surface) {
            $registration = $surface === 'core' ? view()->renderPartial('auth/register', ['auth_policy' => $policy]) : (new AuthBrowserTheme())->render('auth/register', ['auth_policy' => $policy]);
            $doc = new DOMDocument(); @$doc->loadHTML($registration); $fields = new DOMXPath($doc);
            checkAuth('Form fields match ' . $surface . '/' . $loginMode . '/' . $phoneMode, $fields->query('//input[@name="login"]')->length === (int)($loginMode === 'manual') && $fields->query('//input[@name="phone"]')->length === (int)($phoneMode !== 'off'));
            checkAuth('Required flags match ' . $surface . '/' . $loginMode . '/' . $phoneMode, $fields->query('//input[@name="email"][@required]')->length === 1 && $fields->query('//input[@name="phone"][@required]')->length === (int)($phoneMode === 'required'));
            $requiredCount = 5 + (int)($loginMode === 'manual') + (int)($phoneMode === 'required');
            checkAuth('Required stars match ' . $surface . '/' . $loginMode . '/' . $phoneMode, $fields->query('//*[@data-required-mark]')->length === $requiredCount && $fields->query('//label[@for="register-phone"]/*[@data-required-mark]')->length === (int)($phoneMode === 'required'));
            checkAuth('Registration notice removed ' . $surface . '/' . $loginMode . '/' . $phoneMode, !str_contains($registration, 'data-auth-registration-requirements'));
        }
    }
    foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
        $translations = require APP . '/Languages/' . $locale . '.php';
        foreach (array_keys(require APP . '/Languages/ru.php') as $key) {
            if (preg_match('/^auth_(?:policy|phone|verify|identifier|login_mode|schema|registration_conflict|reset_save_error)/', $key)) {
                checkAuth('Localized identity setting ' . $locale . '/' . $key, !empty($translations[$key]));
            }
        }
    }
    $app->router = new FBL\Router(request(), response());
    require CONFIG . '/routes.php';
    FBL\Auth::loginUser($users->findById($legacyId));
    $tabToken = (string)session()->get('needCSRFToken');
    $guard = new ReflectionMethod(App\Controllers\AuthController::class, 'assertValidCsrfToken');
    authRequest(['needCSRFToken' => $tabToken]);
    $guard->invoke($controller, '/profile');
    checkAuth('Saving a form does not invalidate logout in another tab', session()->get('needCSRFToken') === $tabToken && $app->router->checkCSRFToken());
    $beforeInvalidToken = (string)session()->get('needCSRFToken');
    $beforeInvalidSession = session_id();
    $redirect = authSubmit($controller, 'logout', ['needCSRFToken' => 'forged-stale-code'], [], false);
    checkAuth('Forged or stale authenticated logout never ends the session', check_auth() && session_id() === $beforeInvalidSession && session()->get('needCSRFToken') === $beforeInvalidToken && str_ends_with($redirect, '/profile'));
    checkAuth('Stale logout explains retry instead of returning 419', session()->getFlash('warning') === return_translation('auth_logout_retry'));
    $denied = db()->query("SELECT result, reason FROM security_logs WHERE event = 'logout' ORDER BY id DESC LIMIT 1")->getOne();
    checkAuth('Invalid logout recorded with localized reason code', $denied === ['result' => 'denied', 'reason' => 'csrf_invalid']);
    authRequest(['needCSRFToken' => 'forged-stale-code']);
    try { $guard->invoke($controller, '/profile'); } catch (AuthTestRedirect) {}
    checkAuth('Rejected ordinary form does not invalidate other open tabs', session()->get('needCSRFToken') === $tabToken);
    $redirect = authSubmit($controller, 'logout', ['needCSRFToken' => $tabToken], [], false);
    checkAuth('Valid logout from previously opened tab succeeds', !check_auth() && str_ends_with($redirect, '/') && session()->get('needCSRFToken') !== $tabToken);
    $guestSession = session_id(); $guestToken = session()->get('needCSRFToken');
    $redirect = authSubmit($controller, 'logout', ['needCSRFToken' => $tabToken], [], false);
    checkAuth('Repeated logout from a signed-out tab is idempotent', !check_auth() && session_id() === $guestSession && session()->get('needCSRFToken') === $guestToken && str_ends_with($redirect, '/'));
    FBL\Auth::loginUser($users->findById($legacyId));
    $newAccountToken = session()->get('needCSRFToken');
    $redirect = authSubmit($controller, 'logout', ['needCSRFToken' => $tabToken], [], false);
    checkAuth('Old authenticated token cannot sign out a newly signed-in session', check_auth() && session()->get('needCSRFToken') === $newAccountToken && str_ends_with($redirect, '/profile'));
    authSubmit($controller, 'logout', []);
    foreach ($app->router->getRoutes() as $route) {
        if ($route['path'] === '/logout') checkAuth('Logout route delegates token validation to tested controller and remains POST-only', $route['method'] === ['POST'] && !$route['needCSRFToken'] && $route['middleware'] === []);
        if (in_array($route['path'], ['/register', '/login', '/reset-password', '/forgot-password', '/verify-email', '/admin/settings/authentication'], true)) {
            checkAuth('CSRF remains enabled on ' . implode(',', $route['method']) . ' ' . $route['path'], $route['needCSRFToken']);
            if ($route['path'] === '/admin/settings/authentication') checkAuth('Auth settings require admin middleware', in_array('auth', $route['middleware'], true) && in_array('admin', $route['middleware'], true));
        }
    }

    // Full clean installer schema path, no existing application configuration.
    useAuthDatabase('fbl_auth_fresh');
    // Historical migrations use SHOW COLUMNS ... LIKE ? and rely on the
    // installer's default PDO emulation mode on MySQL 5.7.
    (new ReflectionProperty(FBL\Database::class, 'connection'))->getValue(db())->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    (new App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/schema.sql'));
    $all = (new App\Services\MigrationRunner())->run(db());
    checkAuth('Fresh install runs all migrations including new auth schema', in_array('20261019_auth_identities.php', $all, true) && in_array('20261020_email_verification_codes.php', $all, true) && App\Services\AuthIdentity::available() && App\Services\EmailVerification::codesAvailable());
    (new App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/seed.sql'), ['now' => date('Y-m-d H:i:s')]);
    checkAuth('Installer seed still applies after migration', db()->query('SELECT COUNT(*) FROM user_roles')->getColumn() >= 4);
    authPolicy();
    checkAuth('Fresh install registration validates', $users->validateRegistration(array_replace($data, ['login' => 'fresh-user', 'email' => 'fresh@example.test'])) === []);
    $freshId = $users->create(array_replace($data, ['login' => 'fresh-user', 'email' => 'fresh@example.test']));
    clearAuthLimits(); $verification->request($freshId);
    checkAuth('Fresh install registration, verification and login work', $verification->confirmCode('fresh@example.test', $mail->code(), $freshId) && (bool)FBL\Auth::validateCredentials(['login' => 'fresh@example.test', 'password' => $data['password']]));
    $emulatedToken = $users->createPasswordResetToken('fresh@example.test');
    checkAuth('Password reset also works with installer PDO emulation mode', $users->resetPasswordByToken($emulatedToken, 'Emulated-Passw0rd!') && password_verify('Emulated-Passw0rd!', $users->findById($freshId)['password']));
    $routeTranslations = FBL\Language::$lang_data;
    FBL\Language::$lang_data = require APP . '/Languages/ru.php';
    $confirmationPage = view()->renderPartial('auth/verify_email', ['token' => '']);
    checkAuth('Verification page localizes its return link without loading login translations', str_contains($confirmationPage, 'Вернуться ко входу') && !str_contains($confirmationPage, 'auth_login_submit'));
    FBL\Language::$lang_data = $routeTranslations;
    $themeRoot = $scratch . '/compat-themes';
    if (!is_dir($themeRoot . '/standalone/templates')) mkdir($themeRoot . '/standalone/templates', 0700, true);
    file_put_contents($themeRoot . '/standalone/theme.json', json_encode(['name' => 'Standalone', 'slug' => 'standalone', 'version' => '1.0.0', 'inheritDefault' => false]));
    file_put_contents($themeRoot . '/standalone/templates/layout.php', '<main data-standalone><?= $this->content ?></main>');
    file_put_contents($themeRoot . '/standalone/templates/404.php', '<p>Not found</p>');
    (new App\Models\SiteSetting())->setMany(['active_theme' => 'standalone']);
    $standalone = new FBL\ThemeManager($themeRoot);
    $confirmationPage = $standalone->render('auth/verify_email', ['token' => '']);
    checkAuth('Independent older theme keeps its layout and receives new verification fallback', str_contains($confirmationPage, 'data-standalone') && str_contains($confirmationPage, 'verification_action') && !str_contains($confirmationPage, '<p>Not found</p>'));
    (new App\Models\SiteSetting())->setMany(['active_theme' => 'default']);
    checkSecurityLogPresentation();

    useAuthDatabase('fbl_auth_conflict');
    (new App\Services\SqlFileRunner())->executeDatabase((string)file_get_contents(ROOT . '/database/schema.sql'));
    legacyAuthUser('conflict-a', 'duplicate@example.test'); legacyAuthUser('conflict-b', ' duplicate@example.test');
    $beforeConflict = db()->query('SELECT id, login, email, password FROM users ORDER BY id')->get();
    $blocked = false;
    try { (new App\Services\MigrationRunner())->run(db(), ['20261019_auth_identities.php']); } catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(), 'conflict'); }
    checkAuth('Upgrade blocks normalized duplicates before adding columns', $blocked && !in_array('email_normalized', array_column(db()->query('SHOW COLUMNS FROM users')->get(), 'Field'), true));
    checkAuth('Conflicting legacy accounts completely untouched', db()->query('SELECT id, login, email, password FROM users ORDER BY id')->get() === $beforeConflict);

    $report = ['passed' => count($checks), 'checks' => $checks];
    $artifacts = __DIR__ . '/artifacts/password-recovery';
    if (!is_dir($artifacts)) mkdir($artifacts, 0755, true);
    file_put_contents($artifacts . '/integration.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    session()->close();
    echo 'PASS ' . count($checks) . " auth integration checks\n";
} catch (Throwable $error) {
    session()->close();
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    exit(1);
}
