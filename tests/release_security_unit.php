<?php
declare(strict_types=1);
namespace FBL {
    class Theme {
        public static array $lastRender = [];
        public static function render($template, $data = []): string { self::$lastRender = [$template, $data]; return 'confirmation'; }
    }
}
namespace App\Services {
    function dns_get_record(string $host, int $type): array { return $GLOBALS['dnsFixture']; }
    function curl_init(string $url): object { $GLOBALS['httpRequests'][] = $url; return (object)['url' => $url, 'options' => [], 'status' => 200]; }
    function curl_setopt_array(object $handle, array $options): bool { $handle->options = $options; $GLOBALS['httpOptions'][] = $options; return true; }
    function curl_exec(object $handle): bool {
        $response = $GLOBALS['httpFixture'][$handle->url] ?? ['status' => 200, 'body' => '{}'];
        $handle->status = $response['status'];
        if (isset($response['location'])) ($handle->options[CURLOPT_HEADERFUNCTION])($handle, 'Location: ' . $response['location'] . "\r\n");
        $body = $response['body'] ?? '';
        return ($handle->options[CURLOPT_WRITEFUNCTION])($handle, $body) === strlen($body);
    }
    function curl_getinfo(object $handle, int $option): int { return $handle->status; }
    function curl_error(object $handle): string { return 'fixture'; }
    function curl_close(object $handle): void {}
}
namespace {
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$repository = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/fireball-security-' . bin2hex(random_bytes(6));
foreach (['config', 'storage', 'public/uploads', 'tmp/cache', 'package/app', 'app', 'bin'] as $dir) mkdir($fixture . '/' . $dir, 0700, true);
define('ROOT', $fixture);
define('CORE', $repository . '/core');
define('APP', $repository . '/app');
define('HELPERS', $repository . '/helpers');
define('PATH', 'https://canonical.example.test/cms');
define('TRUSTED_PROXIES', ['203.0.113.10']);
ini_set('session.save_path', sys_get_temp_dir());
require $repository . '/config/config.php';
require $repository . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();
$checks = 0;
function expectRelease(bool $condition, string $message): void { $GLOBALS['checks']++; if (!$condition) throw new RuntimeException($message); }
function rejectsRelease(callable $action, string $message): void { try { $action(); } catch (RuntimeException $exception) { expectRelease(true, $message); return; } throw new RuntimeException($message); }
function cleanRelease(string $path): void { if (is_link($path) || is_file($path)) { unlink($path); return; } foreach (scandir($path) ?: [] as $name) if ($name !== '.' && $name !== '..') cleanRelease($path . '/' . $name); rmdir($path); }
class RecoveryUserFixture extends \App\Models\User {
    public function __construct() {}
    public function findActiveTwoFactorRecoveryByToken(string $token): array|false { return ['user_id' => 1]; }
    public function resetTwoFactorByRecoveryToken(string $token): array|false { throw new RuntimeException('GET consumed recovery token'); }
}
class ReleaseUpdateFixture extends \App\Services\UpdateCenter {
    public function __construct() { $this->engineRelease = ['version' => '1.8.0']; }
    public function manifest(array $manifest): void { $this->validateUpdateManifest($manifest); }
    public function copy(string $package, string $target, array $manifest): void { $this->copyPackageToRoot($package, $target, $manifest); }
    public function archive(ZipArchive $zip): void { $this->validateArchiveEntries($zip); }
    public function diff(array $paths, array $manifest): void { $this->fixturePaths = $paths; $this->validateGitDiffAgainstRef(str_repeat('a',40), $manifest); }
    private array $fixturePaths = [];
    protected function runCommand(string $command, string $cwd): array { return ['exit_code' => 0, 'stdout' => implode("\n", $this->fixturePaths), 'stderr' => '']; }
    public function fetch(string $url, array $headers = []): array { return $this->httpGet($url, $headers); }
}
try {
    $_SERVER = ['HTTP_HOST' => 'attacker.example.test', 'REMOTE_ADDR' => '198.51.100.8', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_SSL' => 'on', 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/two-factor-recovery/reset'];
    expectRelease(app_base_url() === PATH, 'Canonical URL cannot be poisoned by Host');
    expectRelease(!request_is_secure(), 'Direct client cannot spoof HTTPS with forwarded headers');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    expectRelease(request_is_secure(), 'Trusted proxy can report HTTPS');
    $_SERVER['HTTP_HOST'] = "bad.test\r\nX-Foo: injected";
    expectRelease(detect_request_origin() === '', 'Malformed Host is rejected');
    $_GET = ['token' => str_repeat('a', 64)];
    $app = new \FBL\Application(false);
    $controller = (new ReflectionClass(\App\Controllers\AuthController::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($controller, 'users'))->setValue($controller, new RecoveryUserFixture());
    expectRelease($controller->resetTwoFactorRecovery() === 'confirmation', 'GET shows confirmation without consuming token');
    expectRelease(\FBL\Theme::$lastRender[0] === 'auth/two_factor_recovery_confirm', 'Dedicated confirmation view selected');

    expectRelease(\FBL\RateLimiter::attempt('login', 2, 60), 'First attempt is allowed');
    expectRelease(\FBL\RateLimiter::attempt('login', 2, 60), 'Second attempt is allowed');
    expectRelease(!\FBL\RateLimiter::attempt('login', 2, 60), 'Attempt beyond limit is rejected');
    $limiterPath = CACHE . '/rate-limits/' . hash('sha256', 'login') . '.json';
    $inode = fileinode($limiterPath);
    \FBL\RateLimiter::clear('login');
    clearstatcache(true, $limiterPath);
    expectRelease(fileinode($limiterPath) === $inode && \FBL\RateLimiter::attempt('login', 2, 60), 'Reset preserves the lock inode');
    file_put_contents($limiterPath, 'broken');
    expectRelease(!\FBL\RateLimiter::attempt('login', 2, 60), 'Corrupted limiter state fails closed');
    cleanRelease(CACHE . '/rate-limits');
    file_put_contents(CACHE . '/rate-limits', 'not a directory');
    expectRelease(!\FBL\RateLimiter::attempt('login', 2, 60), 'Unavailable storage fails closed');

    $safe = sanitize_content_html('<img src="x" onerror="alert(1)"><a href="javascript:alert(1)">link</a>');
    expectRelease(!str_contains($safe, 'onerror') && !str_contains($safe, 'javascript:'), 'HTML sanitizer strips executable attributes and URLs');
    // Force the no-DOM branch through the language's namespace function lookup.
    $helperSource = file_get_contents($repository . '/helpers/helpers.php');
    $functionStart = strpos($helperSource, 'function sanitize_content_html(');
    $functionEnd = strpos($helperSource, "\nfunction ", $functionStart + 1);
    $functionSource = substr($helperSource, $functionStart, $functionEnd - $functionStart);
    eval('namespace ReleaseNoDom; function class_exists($name) { return false; } ' . $functionSource);
    expectRelease(\ReleaseNoDom\sanitize_content_html('<img src=x onerror=alert(1)><a href=javascript:alert(1)>safe & text</a>') === 'safe &amp; text', 'No-DOM fallback returns escaped plain text');
    $policy = \App\Services\PushEndpointPolicy::class;
    foreach (['https://fcm.googleapis.com/fcm/send/abc', 'https://updates.push.services.mozilla.com/wpush/v2/abc', 'https://web.push.apple.com/abc', 'https://server.notify.windows.com/abc'] as $url) expectRelease($policy::accepts($url), 'Known browser push provider accepted');
    foreach (['http://fcm.googleapis.com/x', 'https://localhost/x', 'https://127.0.0.1/x', 'https://fcm.googleapis.com.evil.test/x', 'https://user@fcm.googleapis.com/x', 'https://fcm.googleapis.com:444/x', 'file:///etc/passwd', 'https://fcm.googleapis.com/x#fragment'] as $url) expectRelease(!$policy::accepts($url), 'Untrusted push URL rejected');
    foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '100.64.0.1', '::1', '::ffff:127.0.0.1', 'fc00::1', 'fe80::1', '224.0.0.1'] as $ip) expectRelease(!$policy::publicIp($ip), 'Non-public push address rejected');
    $GLOBALS['dnsFixture'] = [['ip' => '8.8.8.8']];
    expectRelease($policy::resolve('https://fcm.googleapis.com/x') === ['fcm.googleapis.com:443:8.8.8.8'], 'Resolved public IP is pinned');
    $GLOBALS['dnsFixture'] = [['ip' => '8.8.8.8'], ['ipv6' => '::1']];
    rejectsRelease(fn() => $policy::resolve('https://fcm.googleapis.com/x'), 'Mixed public/private DNS answer rejected');

    $installer = new \App\Services\InstallService();
    $validate = new ReflectionMethod($installer, 'validateInstallPayload');
    $site = ['name' => 'Fixture', 'url' => 'https://canonical.example.test', 'timezone' => 'Europe/Moscow'];
    $admin = ['login' => 'fixture', 'email' => 'fixture@example.test', 'password' => 'Strong-Fixture-2026', 'password_confirmation' => 'Strong-Fixture-2026'];
    $database = ['database' => 'fixture'];
    expectRelease($validate->invoke($installer, $database, $site, $admin, 'ru') === '', 'Valid installer payload accepted');
    expectRelease($validate->invoke($installer, $database, $site, array_replace($admin, ['password' => '1', 'password_confirmation' => '1']), 'ru') !== '', 'Weak Creator password rejected');
    foreach (['javascript:alert(1)', 'https://user:password@example.test', 'https://example.test/?x=1'] as $url) expectRelease($validate->invoke($installer, $database, array_replace($site, ['url' => $url]), $admin, 'ru') !== '', 'Unsafe site URL rejected');
    expectRelease($validate->invoke($installer, $database, array_replace($site, ['timezone' => 'invalid']), $admin, 'ru') !== '', 'Invalid timezone rejected');

    $updater = new ReleaseUpdateFixture();
    $manifest = json_decode(file_get_contents($repository . '/update.json'), true, 512, JSON_THROW_ON_ERROR);
    $updater->manifest($manifest);
    $updater->diff(['.htaccess', '.gitattributes', 'bin/cms.php', 'plugins/subscriptions/Plugin.php', 'themes/default/templates/index.php', 'docs/release.md', 'tests/check.php', 'tools/build.php', 'dist/archive.zip', 'storage/schema-manifest.json', 'config/config.local.php'], $manifest);
    expectRelease(true, 'Git validator accepts runtime, development and protected paths without overwriting protected data');
    rejectsRelease(fn() => $updater->diff(['unexpected/runtime.php'], $manifest), 'Git validator still rejects unrelated paths');
    file_put_contents($fixture . '/package/app/new.php', '<?php /* updated */');
    file_put_contents($fixture . '/package/app/fixture путь.php', '<?php /* valid filename */');
    mkdir($fixture . '/package/storage'); file_put_contents($fixture . '/package/storage/private', 'replace');
    mkdir($fixture . '/package/config'); file_put_contents($fixture . '/package/config/config.local.php', 'replace');
    file_put_contents($fixture . '/storage/private', 'keep'); file_put_contents($fixture . '/config/config.local.php', 'keep');
    $updater->copy($fixture . '/package', $fixture, $manifest);
    expectRelease(is_file($fixture . '/app/fixture путь.php'), 'Valid Unicode and space filenames are supported');
    expectRelease(file_get_contents($fixture . '/app/new.php') === '<?php /* updated */', 'Runtime file updated');
    expectRelease(file_get_contents($fixture . '/storage/private') === 'keep' && file_get_contents($fixture . '/config/config.local.php') === 'keep', 'Local data preserved');
    mkdir($fixture . '/outside');
    symlink($fixture . '/outside', $fixture . '/app/link');
    mkdir($fixture . '/package/app/link'); file_put_contents($fixture . '/package/app/link/escape.php', 'bad');
    rejectsRelease(fn() => $updater->copy($fixture . '/package', $fixture, $manifest), 'Symlink destination is rejected before writing');
    expectRelease(!file_exists($fixture . '/outside/escape.php'), 'Symlink target stays untouched');
    unlink($fixture . '/app/link'); cleanRelease($fixture . '/package/app/link');
    file_put_contents($fixture . '/app/obsolete.php', 'old');
    $removal = array_replace($manifest, ['remove' => ['app/obsolete.php']]);
    $updater->manifest($removal); $updater->copy($fixture . '/package', $fixture, $removal);
    expectRelease(!file_exists($fixture . '/app/obsolete.php'), 'Explicit obsolete runtime file removed');
    rejectsRelease(fn() => $updater->manifest(array_replace($manifest, ['remove' => ['config/config.local.php']])), 'Local config cannot be removed');
    rejectsRelease(fn() => $updater->manifest(array_replace($manifest, ['update' => ['/absolute']])), 'Absolute manifest path rejected');
    foreach (['../escape.php', '/absolute.php'] as $name) {
        $zip = new ZipArchive(); $path = $fixture . '/unsafe.zip'; $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE); $zip->addFromString($name, 'bad'); $zip->close(); $zip->open($path);
        try { rejectsRelease(fn() => $updater->archive($zip), 'Archive traversal rejected'); } finally { $zip->close(); }
    }
    $zip = new ZipArchive(); $path = $fixture . '/unsafe.zip'; $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE); $zip->addFromString('link', '/etc/passwd'); $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close(); $zip->open($path);
    try { rejectsRelease(fn() => $updater->archive($zip), 'Archive symlink rejected'); } finally { $zip->close(); }
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE); $zip->addFromString('bomb', str_repeat('x', 2097152)); $zip->close(); $zip->open($path);
    try { rejectsRelease(fn() => $updater->archive($zip), 'Excessive decompression ratio rejected'); } finally { $zip->close(); }

    $GLOBALS['httpFixture'] = ['https://api.github.com/start' => ['status' => 302, 'location' => 'https://release-assets.githubusercontent.com/file'], 'https://release-assets.githubusercontent.com/file' => ['status' => 200, 'body' => 'package']];
    $GLOBALS['httpRequests'] = $GLOBALS['httpOptions'] = [];
    expectRelease($updater->fetch('https://api.github.com/start', ['Authorization: Bearer fixture'])['body'] === 'package', 'Trusted redirect works');
    expectRelease($GLOBALS['httpOptions'][1][CURLOPT_HTTPHEADER] === [], 'Token not forwarded across redirect hosts');
    $GLOBALS['httpFixture']['https://api.github.com/start']['location'] = 'https://127.0.0.1/private';
    $GLOBALS['httpRequests'] = [];
    rejectsRelease(fn() => $updater->fetch('https://api.github.com/start'), 'Untrusted redirect refused before request');
    expectRelease(count($GLOBALS['httpRequests']) === 1, 'Untrusted destination never contacted');
    $GLOBALS['httpFixture']['https://api.github.com/large'] = ['status' => 200, 'body' => str_repeat('x', 2097153)];
    rejectsRelease(fn() => $updater->fetch('https://api.github.com/large'), 'Response size limit enforced during transfer');
    echo "Release security regressions passed: $checks checks.\n";
} finally { cleanRelease($fixture); }
}
