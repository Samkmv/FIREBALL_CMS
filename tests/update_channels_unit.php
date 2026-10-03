<?php
declare(strict_types=1);

// Real services/templates; isolate settings, plugin discovery, HTTP and notification storage.
// No live GitHub requests, CMS database writes or package installations.
namespace App\Models {
    class SiteSetting {
        public function all(): array { return $GLOBALS['fixtureSettings']; }
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
    $fixtureSettings = ['updater_github_repository' => 'fixture/repo', 'update_channel' => 'dev'];
    $fixtureRole = 'creator';
    $fixtureLocalVersion = '1.0.0';
    $fixtureRequests = [];
    $fixtureFailure = false;
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
    function session(): object { return new class { public function get(string $key): mixed { return null; } }; }
    function request(): object { return new class { public function get(string $key, mixed $default = null): mixed { return $default; } }; }
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
        if (preg_match('~/contents/plugins/([^/]+)/plugin.json\?ref=~', $url, $match)) {
            if ($match[1] === 'failed') return ['status_code' => 500, 'body' => '{"message":"Fixture failure"}'];
            return ['status_code' => 200, 'body' => json_encode(['content' => base64_encode(json_encode(['slug' => $match[1], 'version' => '1.1.0']))])];
        }
        throw new RuntimeException('Unexpected HTTP request: ' . $url);
    }
    require ROOT . '/app/Services/UpdateCenter.php';
    require ROOT . '/app/Services/PluginUpdateService.php';
    require ROOT . '/app/Models/NotificationCenter.php';
    class CoreUpdateFixture extends \App\Services\UpdateCenter {
        public bool $git = false;
        protected function reloadEngineRelease(): void { $this->engineRelease = ['version' => $GLOBALS['fixtureLocalVersion'], 'released_at' => '2026-09-01']; }
        protected function getLocalGitState(): array { return ['is_git_repo' => $this->git, 'origin_url' => 'fixture/repo', 'branch' => 'main', 'commit_hash' => '', 'short_commit' => '', 'git_tag' => '', 'git_describe' => '', 'git_available' => true, 'is_clean' => true, 'is_update_clean' => true, 'dirty_files' => [], 'blocking_dirty_files' => [], 'ignored_dirty_files' => []]; }
        protected function buildUpdateBlockers(string $repo, array $git, ?string $channel = null): array { return []; }
        protected function fetchRemoteBranch(string $branch): void { throw new RuntimeException('A published release check must not fetch a branch'); }
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
    expect($core->getLastCheckPayload() === null, 'Administrator never reads creator beta cache');
    $admin = $core->checkForUpdatesIfStale();
    expect($admin['channel'] === 'stable' && $admin['remote_version'] === 'v1.1.0', 'Administrator checks stable despite saved Dev setting');
    expect(count($fixtureRequests) === $count + 1, 'Admin only requests /releases/latest');
    $fixtureRole = 'creator';
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
        foreach (['creator', 'admin'] as $role) {
            $fixtureRole = $role; $core = new CoreUpdateFixture();
            $update_center = $core->getDashboardData(); $settings = $fixtureSettings;
            expect($update_center['config']['channel'] === ($role === 'creator' ? 'beta' : 'stable'), 'Dashboard automatically checks role-specific channel');
            ob_start(); require ROOT . '/app/Views/themes/default/admin/updates.php'; $html = ob_get_clean();
            expect(!str_contains($html, 'admin_update_channel_beta') && !str_contains($html, 'admin_update_role_policy'), "$locale/$role uses translated role labels");
            expect(!str_contains($html, '<select') && !str_contains($html, 'name="update_channel"'), 'Role channel cannot be overridden by dropdown');
        }
    }
    if (in_array('--render', $argv, true)) {
        $fixtureRole = in_array('--admin', $argv, true) ? 'admin' : 'creator';
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
