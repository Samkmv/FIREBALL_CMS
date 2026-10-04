<?php
declare(strict_types=1);
// Run from the extracted NEW package, outside the installation being upgraded.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$arguments = getopt('', ['root:', 'ref:', 'confirm-offline']);
$target = realpath((string)($arguments['root'] ?? ''));
$package = dirname(__DIR__);
if ($target === false || !isset($arguments['confirm-offline']) || $target === $package
    || str_starts_with($package, $target . DIRECTORY_SEPARATOR)
    || !is_file($target . '/storage/installed.lock') || !is_file($target . '/config/config.local.php')) {
    fwrite(STDERR, "Usage: php NEW_PACKAGE/bin/upgrade-local.php --root=INSTALLATION [--ref=FETCHED_TAG] --confirm-offline\nStop public traffic and all workers before confirming. Extract the package outside the installation.\n"); exit(2);
}
try {
    $localConfig = require $target . '/config/config.local.php';
    if (!is_array($localConfig) || !is_array($localConfig['DB_SETTINGS'] ?? null) || empty($localConfig['DB_SETTINGS']['database']) || empty($localConfig['DB_SETTINGS']['host'])) throw new RuntimeException('Installation database configuration is incomplete.');
    define('FIREBALL_CLI', true);
    define('ROOT', $target);
    require $target . '/config/config.php';
    require $package . '/vendor/autoload.php';
    require $package . '/helpers/helpers.php';
    FBL\PerformanceProfiler::start();
    $app = new FBL\Application(false);
    $app->db = new FBL\Database();
    final class LocalPackageUpdater extends App\Services\UpdateCenter {
        public function __construct(private string $package, private string $ref) { parent::__construct(); }
        protected function performUpdate(): array {
            if (!(new App\Services\InstallService())->requirementsPass()) throw new RuntimeException('Server requirements are not satisfied.');
            $manifest = $this->loadUpdateManifest($this->package);
            $this->validateUpdateManifest($manifest, $this->package);
            if (version_compare((string)$manifest['version'], (string)$this->engineRelease['version'], '<=')) throw new RuntimeException('Package must be newer than the installed version.');
            $this->assertPackageComposerReady($this->package, $this->captureDependencyFingerprint());
            $git = $this->getLocalGitState();
            $commit = '';
            $previous = (string)($git['commit_hash'] ?? '');
            if (!empty($git['is_git_repo'])) {
                if ($this->ref === '' || !preg_match('~^[a-zA-Z0-9_./-]+$~D', $this->ref)) throw new RuntimeException('Git installation requires an already fetched release tag via --ref.');
                $resolved = $this->runCommand('git rev-parse --verify ' . escapeshellarg($this->ref . '^{commit}'), ROOT);
                $commit = trim($resolved['stdout']);
                if ($resolved['exit_code'] !== 0 || !preg_match('/^[a-f0-9]{40}$/D', $commit)) throw new RuntimeException('Release ref is unavailable.');
                $gitManifest = $this->loadGitManifestAtRef($commit);
                if ($gitManifest !== $manifest) throw new RuntimeException('Git release manifest and extracted package differ.');
                $this->validateGitDiffAgainstRef($commit, $manifest);
                foreach ((array)($git['blocking_dirty_files'] ?? []) as $path) {
                    if (!$this->shouldPreservePath($path, $manifest)) throw new RuntimeException('Commit or save local code changes before upgrading.');
                }
            }
            $this->createPreUpdateBackups();
            $this->installationMutated = true;
            if ($commit !== '') {
                $diff = $this->runCommand('git diff --name-only HEAD ' . escapeshellarg($commit), ROOT);
                if ($diff['exit_code'] !== 0) throw new RuntimeException('Cannot enumerate release paths.');
                foreach (preg_split('/\R/', trim($diff['stdout'])) ?: [] as $path) {
                    if ($path === '' || !$this->shouldPreservePath($path, $manifest)) continue;
                    $this->assertUpdateDestination(ROOT, $path);
                    $tracked = $this->runCommand('git ls-files --error-unmatch -- ' . escapeshellarg($path), ROOT);
                    if ($tracked['exit_code'] === 0) {
                        $clean = $this->runCommand('git restore --source=HEAD --worktree -- ' . escapeshellarg($path), ROOT);
                        if ($clean['exit_code'] !== 0) throw new RuntimeException('Cannot prepare preserved path for Git merge.');
                    }
                }
                $merge = $this->runCommand('git merge --ff-only ' . escapeshellarg($commit), ROOT);
                if ($merge['exit_code'] !== 0) throw new RuntimeException('Release cannot be applied with a fast-forward merge.');
                $this->restorePreservedGitPaths($previous, $commit, $manifest);
            }
            $this->copyPackageToRoot($this->package, ROOT, $manifest);
            (new App\Services\MigrationRunner())->run();
            app()->plugins = new FBL\Plugins\PluginManager();
            app()->plugins->migrateInstalledPlugins();
            app()->bootInstalledServices();
            $app = app();
            require CONFIG . '/routes.php';
            App\Services\SearchMaintenance::rebuild(null, search_registry());
            $this->clearRuntimeCache();
            return ['status' => 'success', 'version' => $manifest['version']];
        }
    }
    $result = (new LocalPackageUpdater($package, (string)($arguments['ref'] ?? '')))->runUpdate();
    echo 'Upgraded to ' . $result['version'] . ". Verify the site before restoring traffic.\n";
} catch (Throwable $error) {
    if (function_exists('log_error_details')) log_error_details('Offline package upgrade failed', [], $error);
    fwrite(STDERR, "Offline upgrade failed. Check the private server log and maintenance marker. Use recovery if the installation was changed.\n"); exit(1);
}
