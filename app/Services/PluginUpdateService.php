<?php

namespace App\Services;

use FBL\Plugins\PluginManager;
use RuntimeException;
use Throwable;

/**
 * Проверяет и устанавливает обновления каждого плагина независимо от ядра CMS.
 */
final class PluginUpdateService extends UpdateCenter
{
    private const STATE_KEY = '__plugin_update_state';
    private const MAX_MANIFEST_BYTES = 1048576;
    private const MAX_ARCHIVE_BYTES = 157286400;
    private const MAX_EXTRACTED_BYTES = 536870912;
    private const MAX_ARCHIVE_ENTRIES = 20000;
    private const BACKUP_RETENTION = 3;

    private PluginManager $pluginManager;
    private string $pluginsPath;
    private string $workspaceRoot;

    public function __construct(
        ?PluginManager $pluginManager = null,
        ?string $pluginsPath = null,
        ?string $workspaceRoot = null,
    ) {
        parent::__construct();
        $this->pluginManager = $pluginManager ?? plugin_manager();
        $this->pluginsPath = rtrim($pluginsPath ?? PLUGINS, '/');
        $this->workspaceRoot = rtrim($workspaceRoot ?? STORAGE . '/plugin-updates', '/');
    }

    public function decoratePlugins(array $plugins): array
    {
        foreach ($plugins as &$plugin) {
            $plugin['update'] = $this->emptyDisplayState();
            if (empty($plugin['installed']) || empty($plugin['valid'])) {
                continue;
            }

            $slug = (string)($plugin['slug'] ?? '');
            try {
                $metadata = $this->pluginManager->metadata($slug);
                $config = $this->normalizeUpdateConfig($metadata);
                if ($config === null) {
                    continue;
                }
                $state = $this->storedState($slug);
                if ($this->shouldRefreshState($state)) {
                    try {
                        $state = $this->check($slug);
                    } catch (Throwable) {
                        $state = $this->storedState($slug);
                    }
                }
                $remoteVersion = trim((string)($state['remote_version'] ?? ''));
                $localVersion = (string)($metadata['version'] ?? '0.0.0');
                $comparison = $remoteVersion !== '' ? $this->compareVersions($localVersion, $remoteVersion) : null;
                $stateIsValid = ($state['status'] ?? '') !== 'error' && $comparison !== null;
                $localizedReleaseNotes = $this->normalizeReleaseNotes(
                    !empty($state['release_notes_i18n'])
                        ? $state['release_notes_i18n']
                        : ($state['release_notes'] ?? [])
                );
                $plugin['update'] = array_merge($this->emptyDisplayState(), $state, [
                    'configured' => true,
                    'repository' => $config['repository'],
                    'branch' => $config['branch'],
                    'update_available' => $stateIsValid && $comparison < 0,
                    'source_older' => $stateIsValid && $comparison > 0,
                    'release_notes' => $localizedReleaseNotes,
                ]);
            } catch (Throwable $exception) {
                $plugin['update'] = array_merge($this->emptyDisplayState(), [
                    'status' => 'error',
                    'message' => return_translation('admin_plugin_updates_config_invalid'),
                ]);
            }
        }
        unset($plugin);

        return $plugins;
    }

    public function check(string $slug): array
    {
        $plugin = $this->installedPlugin($slug);
        $metadata = $this->pluginManager->metadata($slug);
        $config = $this->normalizeUpdateConfig($metadata);
        if ($config === null) {
            throw new RuntimeException(return_translation('admin_plugin_updates_not_configured'));
        }

        try {
            $remote = $this->fetchRemotePluginMetadata($config, $slug);
            $remoteVersion = trim((string)$remote['metadata']['version']);
            $localVersion = trim((string)($metadata['version'] ?? $plugin['version'] ?? '0.0.0'));
            $comparison = $this->compareVersions($localVersion, $remoteVersion);
            if ($comparison === null) {
                throw new RuntimeException(return_translation('admin_plugin_updates_version_invalid'));
            }

            $previousState = $this->storedState($slug);
            $releaseNotesFallback = $remote['metadata']['release_notes'] ?? [];
            $releaseNotesI18n = $this->normalizeReleaseNoteTranslations(
                $remote['metadata']['release_notes_i18n'] ?? $releaseNotesFallback
            );
            $releaseNotes = $releaseNotesI18n !== [] ? $releaseNotesI18n : $releaseNotesFallback;
            $messageKey = $comparison < 0
                ? 'admin_plugin_updates_available'
                : ($comparison > 0
                    ? 'admin_plugin_updates_source_older'
                    : 'admin_plugin_updates_current');
            $state = [
                'status' => $comparison > 0 ? 'source_older' : 'ok',
                'message' => return_translation($messageKey),
                'local_version' => $localVersion,
                'remote_version' => $remoteVersion,
                'remote_commit' => $remote['commit'],
                'update_available' => $comparison < 0,
                'source_older' => $comparison > 0,
                'checked_at' => date('Y-m-d H:i:s'),
                'last_updated_at' => (string)($previousState['last_updated_at'] ?? ''),
                'backup_file' => (string)($previousState['backup_file'] ?? ''),
                'release_notes' => $this->normalizeReleaseNotes($releaseNotes),
                'release_notes_i18n' => $releaseNotesI18n,
            ];
            $this->persistState($slug, $state);

            return $state;
        } catch (Throwable $exception) {
            $state = array_merge($this->storedState($slug), [
                'status' => 'error',
                'message' => $this->safeError($exception, 'admin_plugin_updates_check_failed'),
                'checked_at' => date('Y-m-d H:i:s'),
                'update_available' => false,
                'source_older' => false,
            ]);
            $this->persistStateSafely($slug, $state);
            throw new RuntimeException((string)$state['message'], 0, $exception);
        }
    }

    /**
     * Проверяет все установленные плагины с включённым источником обновлений.
     * Ошибка одного репозитория не прерывает проверку остальных плагинов.
     */
    public function checkAll(): array
    {
        $summary = [
            'configured' => 0,
            'checked' => 0,
            'available' => 0,
            'source_older' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($this->pluginManager->all() as $plugin) {
            if (empty($plugin['installed']) || empty($plugin['valid'])) {
                continue;
            }

            $slug = (string)($plugin['slug'] ?? '');
            try {
                $metadata = $this->pluginManager->metadata($slug);
                $update = $metadata['update'] ?? null;
                if (!is_array($update) || empty($update['enabled'])) {
                    continue;
                }

                $summary['configured']++;
                $state = $this->check($slug);
                $summary['checked']++;
                if (!empty($state['update_available'])) {
                    $summary['available']++;
                }
                if (!empty($state['source_older'])) {
                    $summary['source_older']++;
                }
            } catch (Throwable $exception) {
                $summary['failed']++;
                $summary['failures'][$slug] = $this->safeError(
                    $exception,
                    'admin_plugin_updates_check_failed'
                );
                log_error_details('Plugin update check failed', ['Plugin' => $slug], $exception);
            }
        }

        return $summary;
    }

    public function update(string $slug, array $user = []): array
    {
        $plugin = $this->installedPlugin($slug);
        $localMetadata = $this->pluginManager->metadata($slug);
        $config = $this->normalizeUpdateConfig($localMetadata);
        if ($config === null) {
            throw new RuntimeException(return_translation('admin_plugin_updates_not_configured'));
        }

        $checked = $this->check($slug);
        if (empty($checked['update_available'])) {
            return [
                'status' => !empty($checked['source_older']) ? 'source_older' : 'current',
                'message' => return_translation(!empty($checked['source_older'])
                    ? 'admin_plugin_updates_source_older'
                    : 'admin_plugin_updates_current'),
                'version' => (string)$localMetadata['version'],
            ];
        }

        $remoteVersion = (string)$checked['remote_version'];
        $remoteCommit = (string)$checked['remote_commit'];
        $this->assertRequirements($this->fetchRemotePluginMetadata($config, $slug, $remoteCommit)['metadata']);

        $lock = null;
        $workspace = '';
        $stagePath = '';
        $rollbackPath = '';
        $failedPath = '';
        $targetPath = $this->pluginsPath . '/' . $slug;
        $maintenanceOwned = false;
        $oldMoved = false;
        $swapped = false;
        $committed = false;
        $backupFile = '';
        $fromVersion = (string)($localMetadata['version'] ?? $plugin['version'] ?? '0.0.0');

        try {
            $lock = $this->acquireUpdateLock();
            $lockedMetadata = $this->pluginManager->metadata($slug);
            $lockedVersion = (string)($lockedMetadata['version'] ?? '0.0.0');
            $lockedComparison = $this->compareVersions($lockedVersion, $remoteVersion);
            if ($lockedComparison !== null && $lockedComparison >= 0) {
                return [
                    'status' => 'current',
                    'message' => return_translation('admin_plugin_updates_current'),
                    'version' => $lockedVersion,
                ];
            }
            if ($lockedVersion !== $fromVersion) {
                throw new RuntimeException(return_translation('admin_plugin_updates_failed'));
            }

            if (!is_file(STORAGE . '/update.maintenance')) {
                $this->enableMaintenanceMode($user);
                $maintenanceOwned = true;
            }
            $this->ensureDirectory($this->workspaceRoot . '/work');
            $workspace = $this->workspaceRoot . '/work/' . $slug . '-' . bin2hex(random_bytes(8));
            $extractPath = $workspace . '/extract';
            $archivePath = $workspace . '/repository.zip';
            $this->ensureDirectory($extractPath);

            $token = trim((string)($this->siteSettings->all()['updater_github_token'] ?? ''));
            $downloadUrl = $this->buildGithubApiUrl(
                $config['repository'],
                '/zipball/' . rawurlencode($remoteCommit)
            );
            $this->downloadFile(
                $downloadUrl,
                $archivePath,
                $this->buildGithubDownloadHeaders($token, 'repository_zipball_api')
            );
            if (!is_file($archivePath) || filesize($archivePath) <= 0
                || filesize($archivePath) > self::MAX_ARCHIVE_BYTES) {
                throw new RuntimeException(return_translation('admin_plugin_updates_archive_invalid'));
            }

            $this->extractRepositoryArchive($archivePath, $extractPath);
            $packagePath = $this->resolvePluginPackagePath($extractPath, $config['path']);
            $packageMetadata = $this->readPackageMetadata($packagePath, $slug);
            if ((string)$packageMetadata['version'] !== $remoteVersion) {
                throw new RuntimeException(return_translation('admin_plugin_updates_package_mismatch'));
            }
            $this->assertRequirements($packageMetadata);
            $this->assertNoLinks($packagePath);

            $backupFile = $this->createPluginBackup($targetPath, $slug, $fromVersion);
            $identifier = bin2hex(random_bytes(8));
            $stagePath = $this->pluginsPath . '/.' . $slug . '.update-' . $identifier;
            $rollbackPath = $this->pluginsPath . '/.' . $slug . '.rollback-' . $identifier;
            $failedPath = $this->pluginsPath . '/.' . $slug . '.failed-' . $identifier;
            $this->copyDirectory($packagePath, $stagePath);
            $this->readPackageMetadata($stagePath, $slug);

            if (!@rename($targetPath, $rollbackPath)) {
                throw new RuntimeException(return_translation('admin_plugin_updates_replace_failed'));
            }
            $oldMoved = true;
            if (!@rename($stagePath, $targetPath)) {
                if (@rename($rollbackPath, $targetPath)) {
                    $oldMoved = false;
                }
                throw new RuntimeException(return_translation('admin_plugin_updates_replace_failed'));
            }
            $swapped = true;

            $installedMetadata = $this->pluginManager->completeUpdate($slug);
            if ((string)($installedMetadata['version'] ?? '') !== $remoteVersion) {
                throw new RuntimeException(return_translation('admin_plugin_updates_package_mismatch'));
            }
            $committed = true;

            $state = [
                'status' => 'updated',
                'message' => return_translation('admin_plugin_updates_success'),
                'local_version' => $remoteVersion,
                'remote_version' => $remoteVersion,
                'remote_commit' => $remoteCommit,
                'update_available' => false,
                'source_older' => false,
                'checked_at' => date('Y-m-d H:i:s'),
                'last_updated_at' => date('Y-m-d H:i:s'),
                'backup_file' => basename($backupFile),
                'release_notes' => $checked['release_notes'] ?? [],
                'release_notes_i18n' => $checked['release_notes_i18n'] ?? [],
            ];
            $this->persistStateSafely($slug, $state);
            $this->writeUpdateLogSafely($user, $slug, $fromVersion, $remoteVersion, 'success');
            $this->invalidatePluginOpcodeCache($targetPath);
            $this->clearRuntimeCache();
            if (function_exists('fireball_event')) {
                try {
                    fireball_event('plugin.updated', [
                        'slug' => $slug,
                        'from_version' => $fromVersion,
                        'to_version' => $remoteVersion,
                    ]);
                } catch (Throwable $eventException) {
                    log_error_details('Plugin updated event failed', ['Plugin' => $slug], $eventException);
                }
            }

            return [
                'status' => 'success',
                'message' => return_translation('admin_plugin_updates_success'),
                'version' => $remoteVersion,
                'backup_file' => basename($backupFile),
            ];
        } catch (Throwable $exception) {
            if (!$committed && $swapped) {
                if (!@rename($targetPath, $failedPath)) {
                    log_error_details('Plugin update rollback failed', ['Plugin' => $slug]);
                } elseif (@rename($rollbackPath, $targetPath)) {
                    $swapped = false;
                    $oldMoved = false;
                    $this->invalidatePluginOpcodeCache($targetPath);
                    $this->deleteDirectory($failedPath);
                } else {
                    // Keep a loadable plugin directory even when the old directory
                    // cannot be restored automatically. The hidden rollback copy is
                    // deliberately retained for manual recovery.
                    @rename($failedPath, $targetPath);
                    log_error_details('Plugin update rollback failed', ['Plugin' => $slug]);
                }
            } elseif (!$committed && $oldMoved && !is_dir($targetPath)) {
                if (@rename($rollbackPath, $targetPath)) {
                    $oldMoved = false;
                    $this->invalidatePluginOpcodeCache($targetPath);
                } else {
                    log_error_details('Plugin update rollback failed', ['Plugin' => $slug]);
                }
            }

            $message = $this->safeError($exception, 'admin_plugin_updates_failed');
            $this->persistStateSafely($slug, array_merge($this->storedState($slug), [
                'status' => 'error',
                'message' => $message,
                'update_available' => true,
                'checked_at' => date('Y-m-d H:i:s'),
            ]));
            $this->writeUpdateLogSafely($user, $slug, $fromVersion, $remoteVersion, 'failed', $message);

            throw new RuntimeException($message, 0, $exception);
        } finally {
            if ($committed && is_dir($rollbackPath)) {
                $this->deleteDirectory($rollbackPath);
            }
            if ($stagePath !== '' && is_dir($stagePath)) {
                $this->deleteDirectory($stagePath);
            }
            if ($workspace !== '' && is_dir($workspace)) {
                $this->deleteDirectory($workspace);
            }
            if ($maintenanceOwned) {
                $this->disableMaintenanceMode();
            }
            if ($lock !== null) {
                $this->releaseUpdateLock($lock);
            }
            if ($backupFile !== '') {
                $this->pruneBackups($slug);
            }
        }
    }

    private function installedPlugin(string $slug): array
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1) {
            throw new RuntimeException(return_translation('admin_plugin_updates_plugin_invalid'));
        }
        foreach ($this->pluginManager->all() as $plugin) {
            if ((string)($plugin['slug'] ?? '') === $slug && !empty($plugin['installed'])) {
                return $plugin;
            }
        }

        throw new RuntimeException(return_translation('admin_plugin_updates_plugin_invalid'));
    }

    private function normalizeUpdateConfig(array $metadata): ?array
    {
        $raw = $metadata['update'] ?? null;
        if (!is_array($raw) || empty($raw['enabled'])) {
            return null;
        }
        $provider = trim((string)($raw['provider'] ?? 'github_directory'));
        $repository = $this->normalizeRepository((string)($raw['repository'] ?? ''));
        $branch = $this->normalizeBranch((string)($raw['branch'] ?? 'main'));
        $path = trim(str_replace('\\', '/', (string)($raw['path'] ?? '')), '/');
        $expectedPath = 'plugins/' . (string)$metadata['slug'];
        if ($provider !== 'github_directory' || $repository === '' || $branch === '' || $path !== $expectedPath) {
            throw new RuntimeException(return_translation('admin_plugin_updates_config_invalid'));
        }

        return [
            'provider' => $provider,
            'repository' => $repository,
            'branch' => $branch,
            'path' => $path,
        ];
    }

    private function fetchRemotePluginMetadata(array $config, string $slug, ?string $knownCommit = null): array
    {
        $token = trim((string)($this->siteSettings->all()['updater_github_token'] ?? ''));
        $headers = array_merge($this->buildGithubHeaders($token), [
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ]);
        $commit = trim((string)$knownCommit);
        if ($commit === '') {
            $commitResponse = $this->httpGet(
                $this->buildGithubApiUrl($config['repository'], '/commits/' . rawurlencode($config['branch'])),
                $headers
            );
            $commitPayload = $this->decodeGithubJson($commitResponse);
            $commit = trim((string)($commitPayload['sha'] ?? ''));
        }
        if (preg_match('/^[a-f0-9]{40,64}$/i', $commit) !== 1) {
            throw new RuntimeException(return_translation('admin_plugin_updates_remote_invalid'));
        }

        $manifestResponse = $this->httpGet(
            $this->buildGithubApiUrl(
                $config['repository'],
                '/contents/' . $config['path'] . '/plugin.json?ref=' . rawurlencode($commit)
            ),
            $headers
        );
        $manifestPayload = $this->decodeGithubJson($manifestResponse);
        $encoded = str_replace(["\r", "\n"], '', (string)($manifestPayload['content'] ?? ''));
        $json = base64_decode($encoded, true);
        if ($json === false || strlen($json) > self::MAX_MANIFEST_BYTES) {
            throw new RuntimeException(return_translation('admin_plugin_updates_remote_invalid'));
        }
        $metadata = json_decode($json, true);
        if (!is_array($metadata)
            || (string)($metadata['slug'] ?? '') !== $slug
            || trim((string)($metadata['version'] ?? '')) === '') {
            throw new RuntimeException(return_translation('admin_plugin_updates_remote_invalid'));
        }

        return ['metadata' => $metadata, 'commit' => $commit];
    }

    private function decodeGithubJson(array $response): array
    {
        $status = (int)($response['status_code'] ?? 0);
        $body = (string)($response['body'] ?? '');
        if ($status < 200 || $status >= 300 || $body === '' || strlen($body) > self::MAX_MANIFEST_BYTES) {
            throw new RuntimeException(return_translation('admin_plugin_updates_remote_unavailable'));
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(return_translation('admin_plugin_updates_remote_invalid'));
        }

        return $decoded;
    }

    private function extractRepositoryArchive(string $archivePath, string $extractPath): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException(return_translation('admin_plugin_updates_zip_required'));
        }
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(return_translation('admin_plugin_updates_archive_invalid'));
        }
        $this->validateArchiveEntries($zip);
        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            $zip->close();
            throw new RuntimeException(return_translation('admin_plugin_updates_archive_invalid'));
        }
        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $totalSize += (int)($stat['size'] ?? 0);
            $attributes = 0;
            $operations = 0;
            if ($zip->getExternalAttributesIndex($index, $operations, $attributes)
                && (($attributes >> 16) & 0170000) === 0120000) {
                $zip->close();
                throw new RuntimeException(return_translation('admin_plugin_updates_archive_invalid'));
            }
            if ($totalSize > self::MAX_EXTRACTED_BYTES) {
                $zip->close();
                throw new RuntimeException(return_translation('admin_plugin_updates_archive_invalid'));
            }
        }
        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new RuntimeException(return_translation('admin_plugin_updates_archive_invalid'));
        }
        $zip->close();
    }

    private function resolvePluginPackagePath(string $extractPath, string $configuredPath): string
    {
        $candidates = [$extractPath . '/' . $configuredPath];
        foreach (scandir($extractPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $candidates[] = $extractPath . '/' . $entry . '/' . $configuredPath;
        }
        $root = realpath($extractPath);
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && $root !== false && is_dir($real) && $this->isInside($real, $root)) {
                return $real;
            }
        }

        throw new RuntimeException(return_translation('admin_plugin_updates_package_missing'));
    }

    private function readPackageMetadata(string $path, string $expectedSlug): array
    {
        $file = rtrim($path, '/') . '/plugin.json';
        $size = is_file($file) ? @filesize($file) : false;
        if ($size === false || $size <= 0 || $size > self::MAX_MANIFEST_BYTES) {
            throw new RuntimeException(return_translation('admin_plugin_updates_package_invalid'));
        }
        $json = @file_get_contents($file);
        $metadata = $json === false ? null : json_decode($json, true);
        if (!is_array($metadata)
            || (string)($metadata['slug'] ?? '') !== $expectedSlug
            || trim((string)($metadata['version'] ?? '')) === '') {
            throw new RuntimeException(return_translation('admin_plugin_updates_package_invalid'));
        }

        return $metadata;
    }

    private function assertRequirements(array $metadata): void
    {
        $requires = is_array($metadata['requires'] ?? null) ? $metadata['requires'] : [];
        $engine = is_file(CONFIG . '/version.php') ? require CONFIG . '/version.php' : [];
        $cmsVersion = (string)($engine['version'] ?? '0.0.0');
        if (!$this->matchesConstraint($cmsVersion, (string)($requires['cms'] ?? ''))) {
            throw new RuntimeException(return_translation('admin_plugin_updates_cms_incompatible'));
        }
        if (!$this->matchesConstraint(PHP_VERSION, (string)($requires['php'] ?? ''))) {
            throw new RuntimeException(return_translation('admin_plugin_updates_php_incompatible'));
        }
    }

    private function matchesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '') {
            return true;
        }
        if (preg_match('/^(>=|<=|>|<|=|==)?\s*v?(\d+(?:\.\d+){1,3}(?:-[a-z0-9.-]+)?)$/i', $constraint, $matches) !== 1) {
            return false;
        }
        $operator = $matches[1] !== '' ? $matches[1] : '>=';
        if ($operator === '=') {
            $operator = '==';
        }

        return version_compare(ltrim($version, 'vV'), $matches[2], $operator);
    }

    private function createPluginBackup(string $source, string $slug, string $version): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException(return_translation('admin_plugin_updates_zip_required'));
        }
        $directory = $this->workspaceRoot . '/backups/' . $slug;
        $this->ensureDirectory($directory);
        $safeVersion = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $version) ?: 'unknown';
        $file = $directory . '/' . $slug . '-' . $safeVersion . '-' . date('Ymd-His')
            . '-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(return_translation('admin_plugin_updates_backup_failed'));
        }
        try {
            $this->addDirectoryToZip($zip, $source, $slug);
            if (!$zip->close()) {
                throw new RuntimeException(return_translation('admin_plugin_updates_backup_failed'));
            }
        } catch (Throwable $exception) {
            try {
                $zip->close();
            } catch (Throwable) {
                // The original backup error is more useful.
            }
            @unlink($file);
            throw $exception;
        }
        if (!is_file($file) || filesize($file) <= 0) {
            throw new RuntimeException(return_translation('admin_plugin_updates_backup_failed'));
        }

        return $file;
    }

    private function addDirectoryToZip(\ZipArchive $zip, string $source, string $archiveRoot): void
    {
        $zip->addEmptyDir($archiveRoot);
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === '.DS_Store') {
                continue;
            }
            $absolute = $source . '/' . $entry;
            $relative = $archiveRoot . '/' . $entry;
            if (is_link($absolute)) {
                throw new RuntimeException(return_translation('admin_plugin_updates_package_invalid'));
            }
            if (is_dir($absolute)) {
                $this->addDirectoryToZip($zip, $absolute, $relative);
            } elseif (is_file($absolute) && !$zip->addFile($absolute, $relative)) {
                throw new RuntimeException(return_translation('admin_plugin_updates_backup_failed'));
            }
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $this->ensureDirectory($destination);
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === '.DS_Store') {
                continue;
            }
            $from = $source . '/' . $entry;
            $to = $destination . '/' . $entry;
            if (is_link($from)) {
                throw new RuntimeException(return_translation('admin_plugin_updates_package_invalid'));
            }
            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
            } elseif (is_file($from) && !@copy($from, $to)) {
                throw new RuntimeException(return_translation('admin_plugin_updates_replace_failed'));
            }
        }
    }

    private function assertNoLinks(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException(return_translation('admin_plugin_updates_package_invalid'));
            }
        }
    }

    private function storedState(string $slug): array
    {
        $state = $this->pluginManager->setting($slug, self::STATE_KEY, []);

        return is_array($state) ? $state : [];
    }

    private function persistState(string $slug, array $state): void
    {
        $this->pluginManager->setSetting($slug, self::STATE_KEY, $state);
    }

    private function persistStateSafely(string $slug, array $state): void
    {
        try {
            $this->persistState($slug, $state);
        } catch (Throwable $exception) {
            log_error_details('Plugin update state write failed', ['Plugin' => $slug], $exception);
        }
    }

    private function writeUpdateLogSafely(
        array $user,
        string $slug,
        string $fromVersion,
        string $toVersion,
        string $result,
        ?string $error = null,
    ): void {
        try {
            db()->query(
                'CREATE TABLE IF NOT EXISTS plugin_update_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plugin_slug VARCHAR(120) NOT NULL,
                    user_id INT(10) UNSIGNED NULL,
                    user_name VARCHAR(255) NULL,
                    from_version VARCHAR(50) NOT NULL,
                    to_version VARCHAR(50) NULL,
                    result VARCHAR(20) NOT NULL,
                    error TEXT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_plugin_update_logs_slug (plugin_slug, created_at),
                    KEY idx_plugin_update_logs_result (result, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            db()->query(
                'INSERT INTO plugin_update_logs
                 (plugin_slug, user_id, user_name, from_version, to_version, result, error, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $slug,
                    (int)($user['id'] ?? 0) ?: null,
                    (string)($user['name'] ?? ''),
                    $fromVersion,
                    $toVersion,
                    $result,
                    $error,
                    date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $exception) {
            log_error_details('Plugin update log write failed', ['Plugin' => $slug, 'Result' => $result], $exception);
        }
    }

    private function invalidatePluginOpcodeCache(string $path): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            return;
        }
        if (!function_exists('opcache_invalidate') || !is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                @opcache_invalidate($file->getPathname(), true);
            }
        }
    }

    private function pruneBackups(string $slug): void
    {
        $directory = $this->workspaceRoot . '/backups/' . $slug;
        $files = glob($directory . '/*.zip') ?: [];
        usort($files, static fn(string $left, string $right): int => (int)@filemtime($right) <=> (int)@filemtime($left));
        foreach (array_slice($files, self::BACKUP_RETENTION) as $file) {
            @unlink($file);
        }
    }

    private function normalizeReleaseNotes(mixed $notes, ?string $locale = null): array
    {
        $translations = $this->normalizeReleaseNoteTranslations($notes);
        if ($translations !== []) {
            try {
                $candidates = \FBL\Localization::localeCandidates($locale, ['en', 'ru']);
            } catch (\Throwable) {
                $candidates = array_values(array_filter([$locale, 'en', 'ru']));
            }
            foreach ($candidates as $candidate) {
                $candidate = \FBL\Localization::normalizeLocale((string)$candidate);
                if ($candidate !== '' && isset($translations[$candidate])) {
                    return $translations[$candidate];
                }
            }

            return reset($translations) ?: [];
        }

        return $this->normalizeReleaseNoteList($notes);
    }

    private function normalizeReleaseNoteTranslations(mixed $notes): array
    {
        if (!is_array($notes) || array_is_list($notes)) {
            return [];
        }

        $translations = [];
        foreach ($notes as $locale => $localizedNotes) {
            $locale = \FBL\Localization::normalizeLocale((string)$locale);
            if ($locale === '') {
                continue;
            }
            $items = $this->normalizeReleaseNoteList($localizedNotes);
            if ($items !== []) {
                $translations[$locale] = $items;
            }
        }

        return $translations;
    }

    private function normalizeReleaseNoteList(mixed $notes): array
    {
        if (is_string($notes)) {
            $notes = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        }
        if (!is_array($notes)) {
            return [];
        }

        $normalized = [];
        foreach ($notes as $note) {
            if (!is_scalar($note)) {
                continue;
            }
            $note = mb_substr(trim((string)$note), 0, 240);
            if ($note !== '') {
                $normalized[] = $note;
            }
            if (count($normalized) >= 10) {
                break;
            }
        }

        return $normalized;
    }

    private function shouldRefreshState(array $state): bool
    {
        $checkedAt = trim((string)($state['checked_at'] ?? ''));
        $timestamp = $checkedAt !== '' ? strtotime($checkedAt) : false;
        if ($timestamp === false) {
            return true;
        }

        $age = time() - $timestamp;

        return $age < 0 || $age >= self::AUTO_CHECK_INTERVAL_SECONDS;
    }

    private function safeError(Throwable $exception, string $fallbackKey): string
    {
        $message = trim($exception->getMessage());
        if ($message === '' || str_contains(mb_strtolower($message), 'authorization')
            || str_contains(mb_strtolower($message), 'bearer')) {
            return return_translation($fallbackKey);
        }

        return mb_substr($message, 0, 500);
    }

    private function emptyDisplayState(): array
    {
        return [
            'configured' => false,
            'status' => 'never',
            'message' => '',
            'local_version' => '',
            'remote_version' => '',
            'remote_commit' => '',
            'update_available' => false,
            'source_older' => false,
            'checked_at' => '',
            'last_updated_at' => '',
            'backup_file' => '',
            'release_notes' => [],
            'release_notes_i18n' => [],
            'repository' => '',
            'branch' => '',
        ];
    }

    private function isInside(string $path, string $base): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $base = rtrim(str_replace('\\', '/', $base), '/');

        return $path === $base || str_starts_with($path, $base . '/');
    }
}
