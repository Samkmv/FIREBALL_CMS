<?php
declare(strict_types=1);

// Real updater orchestration; substitute discovery/HTTP only. No DB or file replacement.
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
    class UpdateCenter {
        protected const AUTO_CHECK_INTERVAL_SECONDS = 21600;
        protected object $siteSettings;
        public static array $requests = [];
        public function __construct() { $this->siteSettings = new class { public function all(): array { return []; } }; }
        protected function normalizeRepository(string $value): string { return $value; }
        protected function normalizeBranch(string $value): string { return $value; }
        protected function compareVersions(string $local, string $remote): int { return version_compare($local, $remote); }
        protected function buildGithubHeaders(string $token): array { return []; }
        protected function buildGithubApiUrl(string $repo, string $path): string { return $path; }
        protected function httpGet(string $url, array $headers): array {
            self::$requests[] = $url;
            if (str_contains($url, '/commits/')) return ['status_code' => 200, 'body' => json_encode(['sha' => str_repeat('a', 40)])];
            preg_match('~/plugins/([^/]+)/plugin.json~', $url, $match);
            $slug = $match[1];
            if ($slug === 'failed') throw new \RuntimeException('Fixture package failure');
            $version = $slug === 'became-older' ? '0.9.0' : '1.0.0';
            return ['status_code' => 200, 'body' => json_encode(['content' => base64_encode(json_encode(['slug' => $slug, 'version' => $version]))])];
        }
    }
}
namespace {
    require __DIR__ . '/../app/Services/PluginUpdateService.php';
    function return_translation(string $key): string { return $key; }
    function log_error_details(string $message, array $context, ?Throwable $exception = null): void {}
    $checks = 0;
    function assertPlugin(bool $value, string $message): void {
        global $checks;
        $checks++;
        if (!$value) throw new RuntimeException($message);
    }
    $manager = new \FBL\Plugins\PluginManager();
    foreach (['current', 'failed', 'became-current', 'became-older', 'after-failure', 'older', 'uninstalled', 'invalid', 'unconfigured'] as $slug) {
        $manager->plugins[$slug] = [
            'slug' => $slug, 'version' => '1.0.0', 'valid' => $slug !== 'invalid', 'installed' => $slug !== 'uninstalled',
            'update' => ['enabled' => $slug !== 'unconfigured', 'provider' => 'github_directory', 'repository' => 'fixture/repo', 'branch' => 'main', 'path' => 'plugins/' . $slug],
        ];
        $manager->states[$slug] = [
            'status' => 'ok', 'checked_at' => date('Y-m-d H:i:s'),
            'remote_version' => $slug === 'current' ? '1.0.0' : ($slug === 'older' ? '0.8.0' : '2.0.0'),
        ];
    }
    $service = new \App\Services\PluginUpdateService($manager, '/unused/plugins', '/unused/workspace');
    $result = $service->updateAll(['id' => 7]);
    assertPlugin($result['updated'] === 0 && $result['skipped'] === 3 && $result['failed'] === 1, 'Summary separates skipped versions and package failure');
    assertPlugin($result['failures'] === ['failed' => 'Fixture package failure'], 'Failure is associated with its plugin');
    $manifestRequests = array_values(array_filter(\App\Services\UpdateCenter::$requests, static fn(string $url): bool => str_contains($url, '/plugin.json')));
    assertPlugin(count($manifestRequests) === 4 && str_contains(end($manifestRequests), '/after-failure/'), 'Only eligible plugins processed, queue continues after failure');
    assertPlugin($manager->states['became-older']['source_older'] === true, 'Fresh check prevents downgrade');
    \App\Services\UpdateCenter::$requests = [];
    // Clear the cached failure; no candidate should trigger an update now.
    $manager->states['failed']['remote_version'] = '1.0.0';
    $result = $service->updateAll();
    assertPlugin($result['updated'] + $result['failed'] + $result['skipped'] === 0, 'No available update is a safe no-op');
    assertPlugin(\App\Services\UpdateCenter::$requests === [], 'No downloads/checks when all cached states are current');

    foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
        foreach (['available', 'none', 'nonadmin'] as $scenario) {
            $html = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/plugin_updates_fixture.php') . ' ' . escapeshellarg($locale) . ' ' . escapeshellarg($scenario));
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors(); libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($dom);
            assertPlugin($xpath->query('//*[@data-plugin-update-all-refresh]')->length === 0, "$locale/$scenario: manual refresh button removed");
            $forms = $xpath->query('//form[@data-plugin-update-all]');
            assertPlugin($forms->length === ($scenario === 'available' ? 1 : 0), "$locale/$scenario: button conditional");
            if ($scenario !== 'available') continue;
            $form = $forms->item(0);
            assertPlugin(array_column(json_decode($form->getAttribute('data-plugins'), true), 'slug') === ['one', 'two', 'three'], "$locale: candidate list excludes invalid/uninstalled/unconfigured plugins");
            assertPlugin($xpath->query('input[@name="csrf"]', $form)->length === 1, "$locale: CSRF included");
            assertPlugin(!str_contains($form->textContent . $form->getAttribute('data-delete-message'), 'admin_plugin_updates_'), "$locale: bulk text translated");
        }
    }
    $routes = file_get_contents(__DIR__ . '/../config/routes.php');
    assertPlugin(str_contains($routes, "post('/admin/plugins/update-all', [PluginController::class, 'updateAll'])->middleware(['auth', 'admin'])"), 'Bulk endpoint uses authenticated admin POST routing (router CSRF enabled by default)');
    $manager->states['current'] = ['status' => 'error', 'error_stage' => 'install', 'remote_version' => '2.0.0', 'checked_at' => date('Y-m-d H:i:s')];
    $decorated = $service->decoratePlugins([$manager->plugins['current']], 0);
    assertPlugin($decorated[0]['update']['update_available'] === true, 'Installation failure keeps the checked update available for retry');
    $manager->states['current']['error_stage'] = 'check';
    $decorated = $service->decoratePlugins([$manager->plugins['current']], 0);
    assertPlugin($decorated[0]['update']['update_available'] === false, 'Checking failure still disables an unverified update');
    echo "Plugin update-all tests passed: $checks checks. No live plugin updates performed." . PHP_EOL;
}
