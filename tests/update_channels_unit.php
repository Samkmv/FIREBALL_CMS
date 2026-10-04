<?php
declare(strict_types=1);

// Real services/templates; isolate settings, plugin discovery, HTTP and notification storage.
// No live GitHub requests, CMS database writes or package installations.
namespace App\Models {
    class SiteSetting {
        public function all(): array { return $GLOBALS['fixtureSettings']; }
        public function get(string $key, mixed $default = null): mixed { return $GLOBALS['fixtureSettings'][$key] ?? $default; }
        public function setMany(array $values): void { $GLOBALS['fixtureSettings'] = array_merge($GLOBALS['fixtureSettings'], $values); }
    }
    class ChatMessage {
        public function getUnreadCountForUser(int $id): int { return 0; }
        public function getUnreadNotificationItemsForUser(int $id, int $limit): array { return []; }
    }
    class ContactRequest {
        public function countUnread(): int { return 0; }
        public function getUnreadNotificationItems(int $limit): array { return []; }
    }
}
namespace FBL {
    class Auth {
        public static function isAdmin(): bool { return in_array(\get_user()['role'], ['creator', 'admin'], true); }
        public static function hasRole(string $role): bool { return \get_user()['role'] === $role; }
    }
}
namespace FBL\Plugins {
    final class PluginManager {
        public array $plugins = [];
        public array $states = [];
        public function all(): array { return $this->plugins; }
        public function metadata(string $slug): array { return $this->plugins[$slug]; }
        public function setting(string $slug, string $key, mixed $default): mixed { return $this->states[$slug] ?? $default; }
        public function setSetting(string $slug, string $key, mixed $value): void { $this->states[$slug] = $value; }
    }
}
namespace App\Services {
    class NotificationService {
        public function unreadCountForUser(int $id): int { return 0; }
        public function unreadItemsForUser(int $id, int $limit): array { return []; }
        public function isFeedItemDismissed(int $id, array $item): bool { return false; }
    }
    function curl_init(string $url): object { return (object)['url' => $url, 'response' => null]; }
    function curl_setopt_array(object $handle, array $options): bool { return true; }
    function curl_exec(object $handle): string { $handle->response = \fixtureHttp($handle->url); return $handle->response['body']; }
    function curl_getinfo(object $handle, int $option): int { return $handle->response['status_code']; }
    function curl_error(object $handle): string { return ''; }
    function curl_close(object $handle): void {}
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    if (!extension_loaded('curl')) throw new RuntimeException('Run the fixture with PHP curl enabled');
    define('CONFIG', dirname(__DIR__) . '/config');
    define('ROOT', dirname(__DIR__));
    define('STORAGE', sys_get_temp_dir() . '/fireball-update-tests-' . bin2hex(random_bytes(6)));
    define('PLUGINS', '/unused/plugins');
    $fixtureSettings = ['updater_github_repository' => 'fixture/repo', 'update_channel' => 'beta'];
    $fixtureRole = 'creator';
    $fixtureLocalVersion = '1.0.0';
    $fixtureRequests = [];
    $fixtureFailure = false;
    $fixtureMainVersion = '1.3.0-beta.1';
    $fixturePost = null;
    $fixtureSession = [];
    function fixtureVersionPhp(): string { return "<?php return ['version' => '" . $GLOBALS['fixtureMainVersion'] . "', 'summary' => 'Main changes', 'changes' => ['Branch fix']];"; }
    $fixtureLatest = ['tag_name' => 'v1.1.0', 'name' => 'Stable release', 'published_at' => '2026-10-01T12:00:00Z', 'draft' => false, 'prerelease' => false];
    $fixtureReleases = [
        ['tag_name' => 'v9.0.0', 'name' => 'Private draft', 'published_at' => '2026-10-03T12:00:00Z', 'draft' => true],
        ['tag_name' => 'v1.2.0-beta.2', 'name' => 'New beta', 'published_at' => '2026-10-02T12:00:00Z', 'prerelease' => true],
        $fixtureLatest,
        ['tag_name' => 'v1.1.0-beta.9', 'name' => 'Old beta', 'published_at' => '2026-10-03T13:00:00Z', 'prerelease' => true],
    ];
    $fixturePluginManager = new \FBL\Plugins\PluginManager();
    foreach (['one', 'failed', 'two', 'three', 'four', 'invalid', 'uninstalled', 'unconfigured'] as $slug) {
        $fixturePluginManager->plugins[$slug] = [
            'slug' => $slug, 'version' => '1.0.0', 'valid' => $slug !== 'invalid', 'installed' => $slug !== 'uninstalled',
            'update' => ['enabled' => $slug !== 'unconfigured', 'provider' => 'github_directory', 'repository' => 'fixture/repo', 'branch' => 'experimental', 'path' => 'plugins/' . $slug],
        ];
    }
    function get_user(): array { return ['id' => 7, 'role' => $GLOBALS['fixtureRole']]; }
    function plugin_manager(): \FBL\Plugins\PluginManager { return $GLOBALS['fixturePluginManager']; }
    function return_translation(string $key): string { return $GLOBALS['fixtureTranslations'][$key] ?? $key; }
    function print_translation(string $key): void { echo return_translation($key); }
    function base_href(string $path): string { return $path; }
    function log_error_details(string $message, array $context, ?Throwable $error = null): void {}
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
    function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="fixture">'; }
    function get_validation_class(string $key): string { return ''; }
    function get_errors(string $key): string { return ''; }
    function check_admin(): bool { return in_array(get_user()['role'], ['creator', 'admin'], true); }
    class FixtureRedirect extends RuntimeException {}
    class FixtureDenied extends RuntimeException {}
    function abort(string $message, int $status): never { throw new FixtureDenied($message, $status); }
    function response(): object { return new class { public function redirect(string $url): never { throw new FixtureRedirect($url); } }; }
    function session(): object { return new class {
        public function get(string $key): mixed { return $GLOBALS['fixtureSession'][$key] ?? null; }
        public function set(string $key, mixed $value): void { $GLOBALS['fixtureSession'][$key] = $value; }
        public function remove(string $key): void { unset($GLOBALS['fixtureSession'][$key]); }
        public function setFlash(string $key, mixed $value): void { $this->set($key, $value); }
    }; }
    function request(): object { return new class {
        public function get(string $key, mixed $default = null): mixed { return $default; }
        public function isPost(): bool { return $GLOBALS['fixturePost'] !== null; }
        public function getData(): array { return $GLOBALS['fixturePost']; }
    }; }
    function view(): object { return new class { public function renderPartial(string $name, array $data = []): string { return $name === 'admin/shell_open' ? '<main>' : ($name === 'admin/shell_close' ? '</main>' : ''); } }; }
    function fixtureHttp(string $url): array {
        $GLOBALS['fixtureRequests'][] = $url;
        if ($GLOBALS['fixtureFailure']) return ['status_code' => 503, 'body' => '{"message":"Unavailable"}'];
        if (str_ends_with($url, '/releases/latest')) return ['status_code' => $GLOBALS['fixtureLatest'] === null ? 404 : 200, 'body' => json_encode($GLOBALS['fixtureLatest'])];
        if (str_contains($url, '/releases?')) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            return ['status_code' => 200, 'body' => json_encode(array_slice($GLOBALS['fixtureReleases'], ((int)$query['page'] - 1) * 100, 100))];
        }
        if (str_ends_with($url, '/commits/main')) return ['status_code' => 200, 'body' => json_encode(['sha' => str_repeat('a', 40)])];
        if (str_ends_with($url, '/main/config/version.php')) return ['status_code' => 200, 'body' => fixtureVersionPhp()];
        if (str_ends_with($url, '/contents/config/version.php?ref=main')) return ['status_code' => 200, 'body' => json_encode(['encoding' => 'base64', 'content' => base64_encode(fixtureVersionPhp())])];
        if (str_contains($url, '.git/info/refs?')) return ['status_code' => 200, 'body' => str_repeat('a', 40) . " refs/heads/main\n"];
        if (preg_match('~/contents/plugins/([^/]+)/plugin.json\?ref=~', $url, $match)) {
            if ($match[1] === 'failed') return ['status_code' => 500, 'body' => '{"message":"Fixture failure"}'];
            return ['status_code' => 200, 'body' => json_encode(['content' => base64_encode(json_encode(['slug' => $match[1], 'version' => '1.1.0']))])];
        }
        throw new RuntimeException('Unexpected HTTP request: ' . $url);
    }
    require ROOT . '/app/Services/UpdateCenter.php';
    require ROOT . '/app/Services/PluginUpdateService.php';
    require ROOT . '/app/Models/NotificationCenter.php';
    require ROOT . '/core/Controller.php';
    require ROOT . '/app/Controllers/BaseController.php';
    require ROOT . '/app/Controllers/AdminController.php';
    class UpdateSettingsFixture extends \App\Controllers\AdminController {
        public function __construct() { $this->siteSettings = new \App\Models\SiteSetting(); }
    }
    class CoreUpdateFixture extends \App\Services\UpdateCenter {
        public bool $git = false;
        public string $branchStatus = 'behind';
        public array $fetchedBranches = [];
        public array $commands = [];
        protected function reloadEngineRelease(): void { $this->engineRelease = ['version' => $GLOBALS['fixtureLocalVersion'], 'released_at' => '2026-09-01']; }
        protected function getLocalGitState(): array { return ['is_git_repo' => $this->git, 'origin_url' => 'fixture/repo', 'branch' => 'main', 'commit_hash' => '', 'short_commit' => '', 'git_tag' => '', 'git_describe' => '', 'git_available' => true, 'is_clean' => true, 'is_update_clean' => true, 'dirty_files' => [], 'blocking_dirty_files' => [], 'ignored_dirty_files' => []]; }
        protected function buildUpdateBlockers(string $repo, array $git, ?string $channel = null): array { return []; }
        protected function fetchRemoteBranch(string $branch): void {
            if ($this->resolveUpdateChannel($this->siteSettings->all()) !== 'dev') throw new RuntimeException('A published release check must not fetch a branch');
            $this->fetchedBranches[] = $branch;
        }
        protected function getBranchStateFromGit(string $branch): array { return ['status' => $this->branchStatus, 'remote_commit_hash' => str_repeat('a', 40)]; }
        protected function runCommand(string $command, string $cwd): array {
            $this->commands[] = $command;
            if (str_starts_with($command, 'git show ') && str_contains($command, 'origin/main:config/version.php')) return ['exit_code' => 0, 'stdout' => fixtureVersionPhp(), 'stderr' => ''];
            throw new RuntimeException('Unexpected command: ' . $command);
        }
        protected function runArchiveUpdate(string $repo, string $branch, string $token = ''): array { return ['source' => 'main', 'branch' => $branch]; }
        protected function loadRemoteGitManifest(string $branch): array { return []; }
        protected function validateUpdateManifest(array $manifest, ?string $packageRoot = null): void {}
        protected function refreshLastCheckAfterUpdate(): void { $this->checkForUpdates(); }
        protected function runStableReleaseGitUpdate(string $repo, string $branch, string $token = ''): array { return ['release' => $this->fetchUpdateRelease($repo, $token)]; }
        protected function runStableReleaseUpdate(string $repo, string $token = ''): array { return ['release' => $this->fetchUpdateRelease($repo, $token)]; }
        public function installDecision(): array { return $this->performUpdate(); } // dispatch only, installation replaced above
        public function holdLock(): mixed { return $this->acquireAutomaticCheckLock('core-' . $this->resolveUpdateChannel($this->siteSettings->all())); }
        public function unlock(mixed $handle): void { $this->releaseAutomaticCheckLock($handle); }
    }
    $fixtureTranslations = require ROOT . '/app/Languages/ru.php';
    $core = new CoreUpdateFixture();
    $checks = 0;
    function expect(bool $value, string $message): void { $GLOBALS['checks']++; if (!$value) throw new RuntimeException($message); }
    $creator = $core->checkForUpdatesIfStale();
    expect($creator['channel'] === 'beta' && $creator['remote_version'] === 'v1.2.0-beta.2', 'Creator checks both releases and beta, excludes draft and old beta');
    $count = count($fixtureRequests);
    $core->checkForUpdatesIfStale();
    expect(count($fixtureRequests) === $count, 'Fresh automatic CMS check uses cache');
    $fixtureRole = 'admin';
    $fixtureSettings['update_channel'] = 'dev';
    expect($core->getLastCheckPayload() === null, 'Administrator never reads creator beta cache');
    $admin = $core->checkForUpdatesIfStale();
    expect($admin['channel'] === 'stable' && $admin['remote_version'] === 'v1.1.0', 'Administrator checks stable despite saved Dev setting');
    expect(count($fixtureRequests) === $count + 1, 'Admin only requests /releases/latest');
    $fixtureRole = 'creator';
    $fixtureSettings['update_channel'] = 'beta';
    expect($core->getLastCheckPayload()['remote_version'] === $creator['remote_version'], 'Switching back retrieves independent creator cache');
    $fixtureLocalVersion = '1.2.0-beta.2'; $core = new CoreUpdateFixture();
    expect(empty($core->checkForUpdatesIfStale()['update_available']), 'Installing the beta invalidates cache without offering downgrade');
    $fixtureReleases[] = ['tag_name' => 'v1.2.0', 'name' => 'Final version', 'published_at' => '2026-10-03T14:00:00Z'];
    expect($core->checkForUpdates()['remote_version'] === 'v1.2.0', 'Final release beats beta for same version');
    foreach ([false, true] as $git) {
        $core->git = $git;
        expect($core->installDecision()['release']['tag_name'] === 'v1.2.0', 'Creator install dispatcher uses same published candidate');
        $fixtureRole = 'admin';
        expect($core->installDecision()['release']['tag_name'] === 'v1.1.0', 'Admin install dispatcher cannot select beta');
        $fixtureRole = 'creator';
    }
    $fixtureLatest = null;
    $fixtureReleases = [['tag_name' => 'v2.0.0-beta.1', 'name' => 'Beta only', 'published_at' => '2026-10-03T15:00:00Z', 'prerelease' => true]];
    expect($core->checkForUpdates()['remote_version'] === 'v2.0.0-beta.1', 'Beta is checked even with no stable releases');
    $betaOnly = $fixtureReleases[0];
    $fixtureReleases = array_fill(0, 100, ['tag_name' => 'v9.0.0', 'draft' => true]);
    $fixtureReleases[] = $betaOnly;
    expect($core->checkForUpdates()['remote_version'] === 'v2.0.0-beta.1', 'Release pagination finds eligible beta past the first 100 entries');
    $fixtureReleases = [$betaOnly];
    foreach (['updater_last_check_payload', 'updater_last_check_payload_beta'] as $key) {
        $cached = json_decode($fixtureSettings[$key], true);
        $cached['checked_at'] = date('Y-m-d H:i:s', time() - 21601);
        $fixtureSettings[$key] = json_encode($cached);
    }
    $beforeExpiryCheck = count($fixtureRequests);
    $core->checkForUpdatesIfStale();
    expect(count($fixtureRequests) === $beforeExpiryCheck + 2, 'CMS automatically rechecks after six hours');
    $fixtureRole = 'admin';
    expect(empty($core->checkForUpdates()['update_available']), 'No stable release does not fall back to main or beta');
    $fixtureLatest = $betaOnly; $fixtureLatest['prerelease'] = false;
    expect(empty($core->checkForUpdates()['update_available']), 'A beta-tagged release is rejected even if GitHub prerelease flag is incorrect');
    $fixtureLatest = null;
    $fixtureRole = 'creator';
    // Real settings POST: persisted choice, token retention and creator-only access.
    $savedSettings = $fixtureSettings;
    $controller = new UpdateSettingsFixture();
    foreach (['dev', 'beta'] as $channel) {
        $fixtureSettings['updater_github_token'] = 'fixture-token';
        $fixturePost = ['updater_github_repository' => 'fixture/repo', 'update_channel' => $channel, 'updater_github_branch' => 'experimental', 'updater_github_token' => ''];
        try { $controller->updates(); throw new RuntimeException('Expected settings redirect'); } catch (FixtureRedirect) {}
        expect($fixtureSettings['update_channel'] === $channel && !isset($fixtureSession['form_errors']), 'Creator saves selected channel');
        expect($fixtureSettings['updater_github_token'] === 'fixture-token', 'Empty token field preserves saved token');
        if ($channel === 'dev') expect($fixtureSettings['updater_github_branch'] === 'main', 'Developer channel pins main even for a forged branch');
    }
    $beforeInvalid = $fixtureSettings;
    foreach (['invalid', 'stable'] as $invalidChannel) {
        $fixturePost['update_channel'] = $invalidChannel;
        try { $controller->updates(); } catch (FixtureRedirect) {}
        expect($fixtureSettings === $beforeInvalid && isset($fixtureSession['form_errors']['update_channel']), 'Unavailable creator channel is rejected without saving');
    }
    $fixtureRole = 'admin'; $fixturePost['update_channel'] = 'dev';
    try { $controller->updates(); throw new RuntimeException('Expected forbidden settings'); } catch (FixtureDenied $error) { expect($error->getCode() === 403, 'Admin cannot submit developer settings'); }
    expect($fixtureSettings === $beforeInvalid, 'Admin POST cannot alter creator source');
    $fixtureRole = 'creator';
    $fixtureSettings['update_channel'] = 'stable'; unset($fixturePost['update_channel']);
    try { $controller->updates(); } catch (FixtureRedirect) {}
    expect($fixtureSettings['update_channel'] === 'beta', 'Legacy source form without a channel migrates stable to creator release/beta default');
    $fixturePost = null; $fixtureSession = [];
    $fixtureSettings = $savedSettings;
    // Developer source checks commits independently of release semver, on Git and ZIP.
    $fixtureLocalVersion = '1.8.2'; $fixtureMainVersion = '1.8.2-beta.1';
    $core = new CoreUpdateFixture();
    $core->checkForUpdates(); // warm release cache for the same installed version
    $fixtureSettings['update_channel'] = 'dev'; $fixtureSettings['updater_github_branch'] = 'experimental';
    $dev = $core->checkForUpdates();
    expect($dev['channel'] === 'dev' && $dev['branch'] === 'main' && $dev['remote_version'] === $fixtureMainVersion, 'ZIP developer metadata comes from main, not published release');
    expect($dev['update_available'] && !$dev['version_update_available'], 'First ZIP switch to main is offered even with lower beta version');
    expect($core->installDecision() === ['source' => 'main', 'branch' => 'main'], 'ZIP installation dispatches main');
    $fixtureSettings['updater_last_installed_commit'] = str_repeat('a', 40);
    expect(!$core->checkForUpdates()['update_available'], 'Matching installed main commit is not offered again');
    $fixtureSettings['updater_github_token'] = 'fixture-token';
    expect(!$core->checkForUpdates()['update_available'], 'Authenticated API path reads main metadata and matches installed commit');
    unset($fixtureSettings['updater_github_token']);
    $fixtureSettings['updater_last_installed_commit'] = str_repeat('b', 40);
    expect($core->checkForUpdates()['update_available'], 'New main commit is offered without a version increase');
    $core->git = true; $fixtureRequests = [];
    $devGit = $core->checkForUpdates();
    expect($devGit['update_available'] && $devGit['remote_version'] === $fixtureMainVersion && $core->fetchedBranches === ['main'], 'Git checks main and reads its version safely');
    expect($fixtureRequests === [], 'Git developer check does not consult unrelated published release');
    $core->branchStatus = 'identical';
    expect($core->installDecision()['status'] === 'success' && $core->fetchedBranches === ['main', 'main', 'main'], 'Git main installation dispatch completes safely when commit already matches');
    $cachedDev = $fixtureSettings['updater_last_check_payload_dev'];
    foreach (['updater_last_check_payload', 'updater_last_check_payload_dev'] as $key) {
        $cached = json_decode($fixtureSettings[$key], true); $cached['branch'] = 'experimental'; $fixtureSettings[$key] = json_encode($cached);
    }
    expect($core->getLastCheckPayload() === null, 'Legacy developer cache for a different branch is rejected');
    $fixtureSettings['updater_last_check_payload_dev'] = $cachedDev;
    $fixtureSettings['update_channel'] = 'beta';
    expect($core->getLastCheckPayload()['channel'] === 'beta', 'Switching to release channel retrieves its independent cache');
    $fixtureSettings['update_channel'] = 'dev'; $fixtureRole = 'admin';
    expect($core->checkForUpdates()['channel'] === 'stable', 'Admin still checks stable when creator selects main');
    $fixtureRole = 'creator'; $fixtureSettings = $savedSettings; $core = new CoreUpdateFixture();
    $fixtureSettings['updater_github_repository'] = 'fixture/another';
    expect($core->getLastCheckPayload() === null, 'Changing repository invalidates cache');
    $held = $core->holdLock(); $fixtureRequests = [];
    expect($core->checkForUpdatesIfStale() === null && $fixtureRequests === [], 'Concurrent check returns immediately without duplicate network request');
    $core->unlock($held);
    $fixtureFailure = true;
    expect($core->checkForUpdatesIfStale()['status'] === 'error', 'Failed API check persists error without a false update');
    $fixtureFailure = false;
    $fixtureRole = 'user'; $fixtureRequests = [];
    expect($core->checkForUpdatesIfStale() === null && $fixtureRequests === [], 'Non-admin user cannot start automatic CMS check');
    $plugins = new \App\Services\PluginUpdateService($fixturePluginManager);
    $plugins->checkAllIfStale();
    expect($fixtureRequests === [], 'Non-admin user cannot start plugin check');
    $fixtureRole = 'creator';
    $summary = $plugins->checkAllIfStale();
    expect($summary['checked'] === 3 && $summary['available'] === 2, 'Automatic plugin batch continues after one error');
    expect(count(array_filter($fixtureRequests, fn($url) => str_ends_with($url, '/commits/main'))) === 3, 'Plugin source is always main, not saved experimental branch');
    $summary = $plugins->checkAllIfStale();
    expect($summary['checked'] === 5 && $summary['available'] === 4, 'Next poll checks remaining eligible plugins');
    $fixtureRequests = []; $fixtureRole = 'admin';
    expect($plugins->checkAllIfStale()['available'] === 4 && $fixtureRequests === [], 'Admin shares fresh main plugin cache with creator');
    $fixturePluginManager->states['one']['checked_at'] = date('Y-m-d H:i:s', time() - 21601);
    $plugins->checkAllIfStale();
    expect(count($fixtureRequests) === 2, 'Only stale plugin gets commit and manifest requests');
    $fixturePluginManager->states['one']['branch'] = 'beta';
    expect($plugins->getStoredUpdateSummary()['available'] === 3, 'Cached non-main package is not offered');
    $plugins->checkAllIfStale();
    expect($fixturePluginManager->states['one']['branch'] === 'main', 'Legacy other-branch state is refreshed from main');
    // Notification integration: background PWA badges must remain network-free.
    $feed = (new ReflectionClass(\App\Models\NotificationCenter::class))->newInstanceWithoutConstructor();
    foreach (['chatMessages' => new \App\Models\ChatMessage(), 'contactRequests' => new \App\Models\ContactRequest(), 'updateCenter' => $core, 'notifications' => new \App\Services\NotificationService()] as $name => $value) {
        $property = new ReflectionProperty($feed, $name); $property->setValue($feed, $value);
    }
    $fixtureRequests = [];
    $feed->badgeCountForUser(7, true);
    expect($fixtureRequests === [], 'PWA background badge reads caches without remote checks');
    $fixturePluginManager->states = [];
    $feed->getFeedForUser(7, true);
    expect(count(array_filter($fixtureRequests, fn($url) => str_ends_with($url, '/commits/main'))) === 3, 'Normal notification heartbeat checks plugins without opening plugin page');
    $fixtureLatest = ['tag_name' => 'v1.3.0', 'name' => 'Final version', 'published_at' => '2026-10-03T15:00:00Z'];
    $fixtureSettings = ['updater_github_repository' => 'fixture/repo'];
    $fixtureLocalVersion = '1.0.0';
    foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
        $fixtureTranslations = require ROOT . '/app/Languages/' . $locale . '.php';
        foreach (['creator/beta', 'creator/dev', 'admin/dev'] as $scenario) {
            [$role, $channel] = explode('/', $scenario);
            $fixtureSettings['update_channel'] = $channel;
            $fixtureRole = $role; $core = new CoreUpdateFixture();
            $update_center = $core->getDashboardData(); $settings = $fixtureSettings;
            expect($update_center['config']['channel'] === ($role === 'creator' ? $channel : 'stable'), 'Dashboard checks selected creator source or stable for admin');
            ob_start(); require ROOT . '/app/Views/themes/default/admin/updates.php'; $html = ob_get_clean();
            expect(!str_contains($html, 'admin_update_channel_beta') && !str_contains($html, 'admin_update_role_policy'), "$locale/$role uses translated role labels");
            expect(str_contains($html, 'name="update_channel"') === ($role === 'creator'), 'Only creator can choose update channel');
            if ($role === 'creator') expect(str_contains($html, 'value="' . $channel . '" selected') && str_contains($html, 'value="dev"') && str_contains($html, 'value="beta"') && !str_contains($html, '<option value="stable"'), 'Creator has exactly release/beta and main options with saved selection');
        }
    }
    if (in_array('--render', $argv, true)) {
        $fixtureRole = in_array('--admin', $argv, true) ? 'admin' : 'creator';
        $fixtureSettings['update_channel'] = in_array('--dev', $argv, true) ? 'dev' : 'beta';
        $fixtureTranslations = require ROOT . '/app/Languages/ru.php';
        $core = new CoreUpdateFixture();
        $update_center = $core->getDashboardData(); $settings = $fixtureSettings;
        echo '<!doctype html><html lang="ru" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head><body class="fb-admin-body"><div class="container py-4">';
        require ROOT . '/app/Views/themes/default/admin/updates.php';
        echo '</div></body></html>';
    } else {
        echo "PASS update channels: $checks checks; role filtering, independent caches, main plugin batches, heartbeat, safe PWA badges and all 4 UI languages. No live updates performed.\n";
    }
}
