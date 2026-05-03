<?php

namespace App\Services;

use App\Models\SiteSetting;
use RuntimeException;

/**
 * Проверяет удалённую ветку Git-репозитория и обновляет CMS из source code.
 */
class UpdateCenter
{
    protected const AUTO_CHECK_INTERVAL_SECONDS = 21600;

    protected SiteSetting $siteSettings;
    protected array $engineRelease;

    public function __construct(?SiteSetting $siteSettings = null)
    {
        $this->siteSettings = $siteSettings ?? new SiteSetting();
        $this->reloadEngineRelease();
    }

    /**
     * Собирает данные для карточки центра обновлений в админке.
     */
    public function getDashboardData(): array
    {
        $this->reloadEngineRelease();

        $settings = $this->siteSettings->all();
        $localGitState = $this->getLocalGitState();
        $repository = $this->resolveRepository($settings, $localGitState['origin_url'] ?? '');
        $branch = $this->resolveBranch($settings, $localGitState['branch'] ?? '');
        $lastCheck = $this->decodeLastCheck((string)($settings['updater_last_check_payload'] ?? ''));
        $storedCommit = $this->getStoredInstalledCommit($settings);
        $displayCommit = (string)($localGitState['commit_hash'] ?? '') !== ''
            ? (string)$localGitState['commit_hash']
            : $storedCommit;
        $displayShortCommit = (string)($localGitState['short_commit'] ?? '') !== ''
            ? (string)$localGitState['short_commit']
            : ($storedCommit !== '' ? substr($storedCommit, 0, 7) : '');

        return [
            'config' => [
                'repository' => $repository,
                'branch' => $branch,
                'has_token' => trim((string)($settings['updater_github_token'] ?? '')) !== '',
                'last_checked_at' => trim((string)($settings['updater_last_checked_at'] ?? '')),
                'last_updated_at' => trim((string)($settings['updater_last_updated_at'] ?? '')),
            ],
            'local' => [
                'name' => (string)($this->engineRelease['name'] ?? 'FIREBALL_CMS'),
                'version' => (string)($this->engineRelease['version'] ?? '0.0.0'),
                'released_at' => (string)($this->engineRelease['released_at'] ?? ''),
                'summary' => (string)($this->engineRelease['summary'] ?? ''),
                'changes' => is_array($this->engineRelease['changes'] ?? null) ? $this->engineRelease['changes'] : [],
                'origin_url' => $localGitState['origin_url'],
                'branch' => $branch,
                'commit_hash' => $displayCommit,
                'short_commit' => $displayShortCommit,
                'git_tag' => $localGitState['git_tag'],
                'git_describe' => $localGitState['git_describe'],
                'is_git_repo' => $localGitState['is_git_repo'],
                'git_available' => $localGitState['git_available'],
                'is_clean' => $localGitState['is_clean'],
                'is_update_clean' => $localGitState['is_update_clean'],
                'dirty_files' => $localGitState['dirty_files'],
                'blocking_dirty_files' => $localGitState['blocking_dirty_files'],
                'ignored_dirty_files' => $localGitState['ignored_dirty_files'],
            ],
            'last_check' => $lastCheck,
            'update_blockers' => $this->buildUpdateBlockers($repository, $localGitState),
        ];
    }

    /**
     * Проверяет наличие новой версии в удалённой ветке и сохраняет результат.
     */
    public function checkForUpdates(): array
    {
        $this->reloadEngineRelease();

        $settings = $this->siteSettings->all();
        $localGitState = $this->getLocalGitState();
        $repository = $this->resolveRepository($settings, $localGitState['origin_url'] ?? '');
        $branch = $this->resolveBranch($settings, $localGitState['branch'] ?? '');
        $storedCommit = $this->getStoredInstalledCommit($settings);
        $localCommit = !empty($localGitState['is_git_repo'])
            ? (string)($localGitState['commit_hash'] ?? '')
            : $storedCommit;

        $basePayload = [
            'status' => 'error',
            'checked_at' => date('Y-m-d H:i:s'),
            'message' => '',
            'repository' => $repository,
            'branch' => $branch,
            'local_version' => (string)($this->engineRelease['version'] ?? '0.0.0'),
            'local_commit' => $localCommit,
            'remote_version' => '',
            'remote_commit' => '',
            'branch_status' => 'not_applicable',
            'git_update_available' => false,
            'version_update_available' => false,
            'update_available' => false,
            'release' => null,
        ];

        try {
            if ($repository === '') {
                throw new RuntimeException(return_translation('admin_update_repository_required'));
            }

            $release = null;
            $branchState = [
                'status' => 'not_applicable',
                'remote_commit_hash' => '',
            ];
            $remoteVersion = '';
            $versionUpdateAvailable = false;
            $gitUpdateAvailable = false;

            if (!empty($localGitState['is_git_repo'])) {
                $this->fetchRemoteBranch($branch);
                $branchState = $this->getBranchStateFromGit($branch);
                $gitUpdateAvailable = in_array((string)($branchState['status'] ?? 'unknown'), ['behind', 'no_local_commit'], true);

                $release = $this->tryFetchLatestRelease($repository, (string)($settings['updater_github_token'] ?? ''), $branch);
                $remoteVersion = $this->resolveRemoteVersion($release, $branch, (string)($branchState['remote_commit_hash'] ?? ''));

                if ($remoteVersion !== '') {
                    $comparison = $this->compareVersions(
                        (string)($this->engineRelease['version'] ?? '0.0.0'),
                        $remoteVersion
                    );
                    $versionUpdateAvailable = $comparison !== null && $comparison < 0;
                }

                $updateAvailable = $gitUpdateAvailable;
            } else {
                $remoteVersionFile = $this->fetchRemoteVersionMetadata(
                    $repository,
                    $branch,
                    (string)($settings['updater_github_token'] ?? '')
                );
                $branchState = $this->fetchBranchState(
                    $repository,
                    $branch,
                    $storedCommit,
                    (string)($settings['updater_github_token'] ?? '')
                );
                if ($storedCommit === '') {
                    $branchState['status'] = 'not_applicable';
                }

                $release = $this->buildBranchRelease(
                    $repository,
                    $branch,
                    $remoteVersionFile,
                    (string)($branchState['remote_commit_hash'] ?? '')
                );
                $remoteVersion = trim((string)($remoteVersionFile['version'] ?? ''));
                $comparison = $this->compareVersions(
                    (string)($this->engineRelease['version'] ?? '0.0.0'),
                    $remoteVersion
                );
                $versionUpdateAvailable = $comparison === null
                    ? $this->fallbackReleaseComparison(
                        (string)($this->engineRelease['released_at'] ?? ''),
                        (string)($remoteVersionFile['released_at'] ?? '')
                    )
                    : $comparison < 0;

                $branchUpdateAvailable = $storedCommit !== ''
                    && in_array((string)($branchState['status'] ?? 'unknown'), ['behind', 'no_local_commit'], true);
                $updateAvailable = $versionUpdateAvailable || $branchUpdateAvailable;
            }

            $payload = array_merge($basePayload, [
                'status' => 'ok',
                'message' => $this->resolveCheckMessage((string)($branchState['status'] ?? 'unknown'), $updateAvailable),
                'remote_version' => $remoteVersion,
                'remote_commit' => (string)($branchState['remote_commit_hash'] ?? ''),
                'branch_status' => (string)($branchState['status'] ?? 'unknown'),
                'git_update_available' => $gitUpdateAvailable,
                'version_update_available' => $versionUpdateAvailable,
                'update_available' => $updateAvailable,
                'release' => $release,
            ]);

            $this->persistLastCheck($payload);

            return $payload;
        } catch (\Throwable $exception) {
            $payload = array_merge($basePayload, [
                'message' => $exception->getMessage(),
            ]);
            $this->persistLastCheck($payload);
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Возвращает сохранённый результат последней проверки обновлений.
     */
    public function getLastCheckPayload(): ?array
    {
        $settings = $this->siteSettings->all();

        return $this->decodeLastCheck((string)($settings['updater_last_check_payload'] ?? ''));
    }

    /**
     * Автоматически проверяет обновления, только если прошлый результат устарел.
     */
    public function checkForUpdatesIfStale(int $intervalSeconds = self::AUTO_CHECK_INTERVAL_SECONDS): ?array
    {
        $settings = $this->siteSettings->all();
        $localGitState = $this->getLocalGitState();
        $repository = $this->resolveRepository($settings, $localGitState['origin_url'] ?? '');
        $lastCheck = $this->decodeLastCheck((string)($settings['updater_last_check_payload'] ?? ''));
        $lastCheckedAt = trim((string)($settings['updater_last_checked_at'] ?? ''));
        $intervalSeconds = max(300, $intervalSeconds);

        if ($repository === '') {
            return null;
        }

        $lastCheckedTimestamp = $lastCheckedAt !== '' ? strtotime($lastCheckedAt) : false;
        if (is_array($lastCheck) && $lastCheckedTimestamp !== false && (time() - $lastCheckedTimestamp) < $intervalSeconds) {
            return $lastCheck;
        }

        try {
            return $this->checkForUpdates();
        } catch (\Throwable) {
            return $this->getLastCheckPayload();
        }
    }

    /**
     * Обновляет код сайта через git pull или branch ZIP fallback.
     */
    public function runUpdate(): array
    {
        $settings = $this->siteSettings->all();
        $localGitState = $this->getLocalGitState();
        $repository = $this->resolveRepository($settings, $localGitState['origin_url'] ?? '');
        $branch = $this->resolveBranch($settings, $localGitState['branch'] ?? '');

        foreach ($this->buildUpdateBlockers($repository, $localGitState) as $blocker) {
            throw new RuntimeException($blocker);
        }

        if (empty($localGitState['is_git_repo'])) {
            return $this->runArchiveUpdate(
                $repository,
                $branch,
                (string)($settings['updater_github_token'] ?? '')
            );
        }

        $previousCommit = (string)($localGitState['commit_hash'] ?? '');
        $this->fetchRemoteBranch($branch);
        $branchState = $this->getBranchStateFromGit($branch);

        if (($branchState['status'] ?? '') === 'identical') {
            $this->refreshLastCheckAfterUpdate();

            return [
                'status' => 'success',
                'message' => return_translation('admin_update_already_latest'),
                'release' => $this->tryFetchLatestRelease($repository, (string)($settings['updater_github_token'] ?? ''), $branch),
            ];
        }

        if (($branchState['status'] ?? '') === 'ahead') {
            throw new RuntimeException(return_translation('admin_update_branch_ahead'));
        }

        if (($branchState['status'] ?? '') === 'diverged') {
            throw new RuntimeException(return_translation('admin_update_branch_diverged'));
        }

        $pullResult = $this->runCommand(
            'git pull --ff-only origin ' . escapeshellarg($branch),
            ROOT
        );

        if ($pullResult['exit_code'] !== 0) {
            throw new RuntimeException($this->formatCommandError($pullResult, return_translation('admin_update_pull_failed')));
        }

        $currentCommit = trim($this->runCommand('git rev-parse HEAD', ROOT)['stdout']);
        $composerResult = null;

        if ($previousCommit !== '' && $currentCommit !== '' && $previousCommit !== $currentCommit) {
            $composerResult = $this->runComposerIfNeeded($previousCommit, $currentCommit);
        }

        $this->siteSettings->setMany([
            'updater_last_updated_at' => date('Y-m-d H:i:s'),
            'updater_last_installed_commit' => $currentCommit,
        ]);
        $this->refreshLastCheckAfterUpdate();

        if (is_array($composerResult)) {
            return $composerResult;
        }

        return [
            'status' => 'success',
            'message' => return_translation('admin_update_success'),
            'release' => $this->tryFetchLatestRelease($repository, (string)($settings['updater_github_token'] ?? ''), $branch),
        ];
    }

    /**
     * Возвращает список причин, по которым запуск обновления нужно заблокировать.
     */
    protected function buildUpdateBlockers(string $repository, array $localGitState): array
    {
        $blockers = [];
        $canRunProcesses = $this->canRunProcesses();
        $isGitRepo = !empty($localGitState['is_git_repo']);

        if ($repository === '') {
            $blockers[] = return_translation('admin_update_repository_required');
        }

        if ($isGitRepo) {
            if (!$canRunProcesses) {
                $blockers[] = return_translation('admin_update_process_disabled');
            }
            if ($canRunProcesses && empty($localGitState['git_available'])) {
                $blockers[] = return_translation('admin_update_git_missing');
            }

            $localBranch = trim((string)($localGitState['branch'] ?? ''));
            $targetBranch = $this->resolveBranch($this->siteSettings->all(), $localBranch);
            if ($localBranch === '' || $localBranch !== $targetBranch) {
                $blockers[] = str_replace(
                    [':local', ':target'],
                    [$localBranch !== '' ? $localBranch : 'HEAD', $targetBranch],
                    return_translation('admin_update_branch_mismatch')
                );
            }
            if (!($localGitState['is_update_clean'] ?? false)) {
                $dirtyPreview = implode(', ', array_slice($localGitState['blocking_dirty_files'] ?? [], 0, 5));
                $message = return_translation('admin_update_dirty_worktree');
                if ($dirtyPreview !== '') {
                    $message .= ' ' . $dirtyPreview;
                }
                $blockers[] = $message;
            }

            return $blockers;
        }

        if (!$this->supportsZipUpdates()) {
            $blockers[] = return_translation('admin_update_zip_missing');
        }
        if (!$this->isWritablePath(ROOT . '/tmp')) {
            $blockers[] = return_translation('admin_update_tmp_not_writable');
        }
        if (!$this->isWritablePath(ROOT)) {
            $blockers[] = return_translation('admin_update_root_not_writable');
        }
        if (!$this->isWritablePath(CONFIG)) {
            $blockers[] = return_translation('admin_update_config_not_writable');
        }

        return $blockers;
    }

    /**
     * Обновляет ZIP-установку архивом исходников целевой ветки.
     */
    protected function runArchiveUpdate(string $repository, string $branch, string $token = ''): array
    {
        $workspace = ROOT . '/tmp/update-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $archivePath = $workspace . '/package.zip';
        $extractPath = $workspace . '/extract';
        $remoteCommit = '';
        $composerFingerprintBefore = $this->captureDependencyFingerprint();
        $storedCommit = $this->getStoredInstalledCommit($this->siteSettings->all());

        try {
            $this->ensureDirectory($workspace);
            $this->ensureDirectory($extractPath);

            $branchState = $this->fetchBranchState(
                $repository,
                $branch,
                $storedCommit,
                $token
            );
            $remoteCommit = (string)($branchState['remote_commit_hash'] ?? '');
            $release = $this->buildBranchRelease(
                $repository,
                $branch,
                $this->fetchRemoteVersionMetadata($repository, $branch, $token),
                $remoteCommit
            );

            if ($storedCommit !== '' && ($branchState['status'] ?? '') === 'identical') {
                $this->refreshLastCheckAfterUpdate();

                return [
                    'status' => 'success',
                    'message' => return_translation('admin_update_already_latest'),
                    'release' => $release,
                ];
            }

            $archive = $this->resolveReleaseArchive($release, $repository);

            $this->downloadFile(
                (string)($archive['download_url'] ?? ''),
                $archivePath,
                $this->buildGithubDownloadHeaders($token, (string)($archive['download_mode'] ?? 'direct'))
            );

            $packageRoot = $this->extractArchive($archivePath, $extractPath);
            $this->ensureLocalConfigExists();
            $this->copyPackageToRoot($packageRoot, ROOT);

            $composerResult = $this->runComposerIfDependencyFilesChanged($composerFingerprintBefore);

            $this->siteSettings->setMany([
                'updater_last_updated_at' => date('Y-m-d H:i:s'),
                'updater_last_installed_commit' => $remoteCommit,
            ]);
            $this->refreshLastCheckAfterUpdate();

            if (is_array($composerResult)) {
                return $composerResult;
            }

            return [
                'status' => 'success',
                'message' => return_translation('admin_update_success'),
                'release' => $release,
            ];
        } finally {
            $this->deleteDirectory($workspace);
        }
    }

    /**
     * Пытается получить релиз GitHub, но не валит git-based проверку, если API недоступен.
     */
    protected function tryFetchLatestRelease(string $repository, string $token, string $branch): ?array
    {
        if ($repository === '') {
            return null;
        }

        try {
            return $this->fetchLatestRelease($repository, $token);
        } catch (\Throwable) {
            return [
                'name' => $branch,
                'tag_name' => '',
                'html_url' => 'https://github.com/' . $repository . '/tree/' . rawurlencode($branch),
                'published_at' => '',
                'body' => '',
                'excerpt' => return_translation('admin_update_source_repo_fallback'),
                'zipball_url' => '',
                'assets' => [],
            ];
        }
    }

    /**
     * Обновляет сохранённый результат последней проверки после попытки обновления.
     */
    protected function refreshLastCheckAfterUpdate(): void
    {
        try {
            $this->checkForUpdates();
        } catch (\Throwable) {
            $this->siteSettings->setMany([
                'updater_last_check_payload' => '',
                'updater_last_checked_at' => '',
            ]);
        }
    }

    /**
     * Перечитывает локальный metadata-файл движка после обновления файлов.
     */
    protected function reloadEngineRelease(): void
    {
        $payload = require CONFIG . '/version.php';

        $this->engineRelease = is_array($payload) ? $payload : [];
    }

    /**
     * Получает информацию о последнем релизе репозитория через GitHub API.
     */
    protected function fetchLatestRelease(string $repository, string $token = ''): array
    {
        $response = $this->httpGet(
            $this->buildGithubApiUrl($repository, '/releases/latest'),
            $this->buildGithubHeaders($token)
        );
        $payload = json_decode($response['body'], true);

        if ($response['status_code'] === 404) {
            return $this->fetchLatestTag($repository, $token);
        }

        if (!is_array($payload)) {
            throw new RuntimeException(return_translation('admin_update_release_invalid'));
        }

        if ($response['status_code'] < 200 || $response['status_code'] >= 300) {
            throw new RuntimeException(return_translation('admin_update_release_fetch_failed') . ' ' . $payload['message']);
        }

        return [
            'name' => trim((string)($payload['name'] ?? '')),
            'tag_name' => trim((string)($payload['tag_name'] ?? '')),
            'html_url' => trim((string)($payload['html_url'] ?? '')),
            'published_at' => trim((string)($payload['published_at'] ?? '')),
            'body' => trim((string)($payload['body'] ?? '')),
            'excerpt' => $this->buildReleaseExcerpt((string)($payload['body'] ?? '')),
            'zipball_url' => trim((string)($payload['zipball_url'] ?? '')),
            'assets' => $this->normalizeReleaseAssets($payload['assets'] ?? []),
        ];
    }

    /**
     * Если GitHub Release не опубликован, берёт последний доступный тег.
     */
    protected function fetchLatestTag(string $repository, string $token = ''): array
    {
        $response = $this->httpGet(
            $this->buildGithubApiUrl($repository, '/tags'),
            $this->buildGithubHeaders($token)
        );
        $payload = json_decode($response['body'], true);

        if ($response['status_code'] === 404) {
            throw new RuntimeException(return_translation('admin_update_repository_not_found'));
        }

        if ($response['status_code'] < 200 || $response['status_code'] >= 300 || !is_array($payload)) {
            $message = is_array($payload) ? (string)($payload['message'] ?? '') : '';
            throw new RuntimeException(trim(return_translation('admin_update_release_fetch_failed') . ' ' . $message));
        }

        $latestTag = $payload[0] ?? null;
        if (!is_array($latestTag) || trim((string)($latestTag['name'] ?? '')) === '') {
            throw new RuntimeException(return_translation('admin_update_no_releases_or_tags'));
        }

        $tagName = trim((string)($latestTag['name'] ?? ''));

        return [
            'name' => $tagName,
            'tag_name' => $tagName,
            'html_url' => 'https://github.com/' . $repository . '/releases/tag/' . rawurlencode($tagName),
            'published_at' => '',
            'body' => '',
            'excerpt' => return_translation('admin_update_using_tag_fallback'),
            'zipball_url' => $this->buildGithubApiUrl($repository, '/zipball/' . rawurlencode($tagName)),
            'assets' => [],
        ];
    }

    /**
     * Загружает metadata из config/version.php выбранной ветки GitHub.
     */
    protected function fetchRemoteVersionMetadata(string $repository, string $branch, string $token = ''): array
    {
        $response = $this->httpGet(
            $this->buildGithubApiUrl($repository, '/contents/config/version.php?ref=' . rawurlencode($branch)),
            $this->buildGithubHeaders($token)
        );
        $payload = json_decode($response['body'], true);

        if ($response['status_code'] === 404) {
            throw new RuntimeException(return_translation('admin_update_repository_not_found'));
        }

        if ($response['status_code'] < 200 || $response['status_code'] >= 300 || !is_array($payload)) {
            $message = is_array($payload) ? (string)($payload['message'] ?? '') : '';
            throw new RuntimeException(trim(return_translation('admin_update_release_fetch_failed') . ' ' . $message));
        }

        $content = (string)($payload['content'] ?? '');
        $encoding = trim((string)($payload['encoding'] ?? ''));
        if ($content === '' || $encoding !== 'base64') {
            throw new RuntimeException(return_translation('admin_update_release_invalid'));
        }

        $decodedContent = base64_decode(str_replace(["\r", "\n"], '', $content), true);
        if ($decodedContent === false || trim($decodedContent) === '') {
            throw new RuntimeException(return_translation('admin_update_release_invalid'));
        }

        return $this->parseVersionPhpPayload($decodedContent);
    }

    /**
     * Преобразует содержимое config/version.php в массив metadata.
     */
    protected function parseVersionPhpPayload(string $contents): array
    {
        $tmpDirectory = is_dir(ROOT . '/tmp') ? ROOT . '/tmp' : sys_get_temp_dir();
        $tmpFile = tempnam($tmpDirectory, 'fb-version-');
        if ($tmpFile === false) {
            throw new RuntimeException(return_translation('admin_update_workspace_failed'));
        }

        try {
            if (@file_put_contents($tmpFile, $contents) === false) {
                throw new RuntimeException(return_translation('admin_update_workspace_failed'));
            }

            $payload = require $tmpFile;
        } finally {
            @unlink($tmpFile);
        }

        if (!is_array($payload)) {
            throw new RuntimeException(return_translation('admin_update_release_invalid'));
        }

        return [
            'name' => trim((string)($payload['name'] ?? 'FIREBALL_CMS')),
            'version' => trim((string)($payload['version'] ?? '')),
            'released_at' => trim((string)($payload['released_at'] ?? '')),
            'summary' => trim((string)($payload['summary'] ?? '')),
            'changes' => is_array($payload['changes'] ?? null) ? array_values($payload['changes']) : [],
        ];
    }

    /**
     * Собирает псевдо-release для обновления напрямую из ветки репозитория.
     */
    protected function buildBranchRelease(string $repository, string $branch, array $versionFile, string $remoteCommitHash = ''): array
    {
        $changes = array_values(array_filter(array_map('trim', is_array($versionFile['changes'] ?? null) ? $versionFile['changes'] : [])));
        $body = implode("\n", $changes);
        $excerpt = trim((string)($versionFile['summary'] ?? ''));

        if ($excerpt === '' && $body !== '') {
            $excerpt = $this->buildReleaseExcerpt($body);
        }
        if ($excerpt === '') {
            $excerpt = return_translation('admin_update_source_repo_fallback');
        }

        return [
            'name' => trim((string)($versionFile['version'] ?? '')) !== ''
                ? trim((string)$versionFile['version'])
                : ($remoteCommitHash !== '' ? substr($remoteCommitHash, 0, 7) : $branch),
            'tag_name' => '',
            'html_url' => 'https://github.com/' . $repository . '/tree/' . rawurlencode($branch),
            'published_at' => trim((string)($versionFile['released_at'] ?? '')),
            'body' => $body,
            'excerpt' => $excerpt,
            'zipball_url' => $this->buildGithubApiUrl($repository, '/zipball/' . rawurlencode($branch)),
            'assets' => [],
        ];
    }

    /**
     * Получает состояние локальной ветки относительно head выбранной ветки на GitHub.
     */
    protected function fetchBranchState(string $repository, string $branch, string $localCommitHash, string $token = ''): array
    {
        $headers = $this->buildGithubHeaders($token);
        $branchResponse = $this->httpGet(
            $this->buildGithubApiUrl($repository, '/commits/' . rawurlencode($branch)),
            $headers
        );
        $branchPayload = json_decode($branchResponse['body'], true);

        if ($branchResponse['status_code'] === 404) {
            throw new RuntimeException(return_translation('admin_update_repository_not_found'));
        }

        if ($branchResponse['status_code'] < 200 || $branchResponse['status_code'] >= 300 || !is_array($branchPayload)) {
            $message = is_array($branchPayload) ? (string)($branchPayload['message'] ?? '') : '';
            throw new RuntimeException(trim(return_translation('admin_update_branch_fetch_failed') . ' ' . $message));
        }

        $remoteCommitHash = trim((string)($branchPayload['sha'] ?? ''));
        if ($remoteCommitHash === '') {
            throw new RuntimeException(return_translation('admin_update_branch_fetch_failed'));
        }

        if ($localCommitHash === '') {
            return [
                'status' => 'no_local_commit',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        if ($localCommitHash === $remoteCommitHash) {
            return [
                'status' => 'identical',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        $compareResponse = $this->httpGet(
            $this->buildGithubApiUrl($repository, '/compare/' . rawurlencode($localCommitHash) . '...' . rawurlencode($branch)),
            $headers
        );
        $comparePayload = json_decode($compareResponse['body'], true);

        if ($compareResponse['status_code'] >= 200 && $compareResponse['status_code'] < 300 && is_array($comparePayload)) {
            return [
                'status' => trim((string)($comparePayload['status'] ?? 'unknown')) ?: 'unknown',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        return [
            'status' => 'unknown',
            'remote_commit_hash' => $remoteCommitHash,
        ];
    }

    /**
     * Обновляет локальные refs для целевой ветки и тегов.
     */
    protected function fetchRemoteBranch(string $branch): void
    {
        $fetchResult = $this->runCommand(
            'git fetch --tags origin ' . escapeshellarg($branch),
            ROOT
        );

        if ($fetchResult['exit_code'] !== 0) {
            throw new RuntimeException($this->formatCommandError($fetchResult, return_translation('admin_update_branch_fetch_failed')));
        }
    }

    /**
     * Определяет состояние локальной ветки относительно origin/branch после git fetch.
     */
    protected function getBranchStateFromGit(string $branch): array
    {
        $remoteRef = 'refs/remotes/origin/' . $branch;
        $remoteCommitResult = $this->runCommand(
            'git rev-parse --verify ' . escapeshellarg($remoteRef),
            ROOT
        );

        if ($remoteCommitResult['exit_code'] !== 0) {
            throw new RuntimeException(return_translation('admin_update_branch_fetch_failed'));
        }

        $remoteCommitHash = trim($remoteCommitResult['stdout']);
        $localCommitHash = trim($this->runCommand('git rev-parse HEAD', ROOT)['stdout']);

        if ($localCommitHash === '') {
            return [
                'status' => 'no_local_commit',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        if ($localCommitHash === $remoteCommitHash) {
            return [
                'status' => 'identical',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        $mergeBaseResult = $this->runCommand(
            'git merge-base HEAD ' . escapeshellarg($remoteRef),
            ROOT
        );
        $mergeBase = trim($mergeBaseResult['stdout']);

        if ($mergeBase !== '' && $mergeBase === $localCommitHash) {
            return [
                'status' => 'behind',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        if ($mergeBase !== '' && $mergeBase === $remoteCommitHash) {
            return [
                'status' => 'ahead',
                'remote_commit_hash' => $remoteCommitHash,
            ];
        }

        return [
            'status' => 'diverged',
            'remote_commit_hash' => $remoteCommitHash,
        ];
    }

    /**
     * Возвращает список zip-asset'ов релиза в удобном формате.
     */
    protected function normalizeReleaseAssets($assets): array
    {
        if (!is_array($assets)) {
            return [];
        }

        $normalized = [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $normalized[] = [
                'name' => trim((string)($asset['name'] ?? '')),
                'download_url' => trim((string)($asset['browser_download_url'] ?? '')),
                'api_url' => trim((string)($asset['url'] ?? '')),
                'content_type' => trim((string)($asset['content_type'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * Выбирает ZIP-архив, который будет использоваться для обновления.
     */
    protected function resolveReleaseArchive(array $release, string $repository): array
    {
        foreach ($release['assets'] ?? [] as $asset) {
            $name = trim((string)($asset['name'] ?? ''));
            $downloadUrl = trim((string)($asset['download_url'] ?? ''));
            $apiUrl = trim((string)($asset['api_url'] ?? ''));

            if ($name !== '' && preg_match('/\.zip$/i', $name) === 1) {
                return [
                    'name' => $name,
                    'download_url' => $downloadUrl !== '' ? $downloadUrl : $apiUrl,
                    'download_mode' => $downloadUrl === '' && $apiUrl !== '' ? 'release_asset_api' : 'direct',
                ];
            }
        }

        $zipballUrl = trim((string)($release['zipball_url'] ?? ''));
        if ($zipballUrl !== '') {
            $tagName = trim((string)($release['tag_name'] ?? 'release'));

            return [
                'name' => $tagName . '.zip',
                'download_url' => $zipballUrl,
                'download_mode' => 'repository_zipball_api',
            ];
        }

        throw new RuntimeException(return_translation('admin_update_release_asset_missing'));
    }

    /**
     * Выполняет GET-запрос и возвращает тело и HTTP-код.
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 20,
            ]);

            $body = curl_exec($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new RuntimeException(return_translation('admin_update_http_failed') . ' ' . $error);
            }

            return [
                'body' => (string)$body,
                'status_code' => $statusCode,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $statusCode = $this->extractHttpStatusCode($http_response_header ?? []);

        if ($body === false) {
            throw new RuntimeException(return_translation('admin_update_http_failed'));
        }

        return [
            'body' => (string)$body,
            'status_code' => $statusCode,
        ];
    }

    /**
     * Скачивает бинарный файл по URL в локальный путь.
     */
    protected function downloadFile(string $url, string $destination, array $headers = []): void
    {
        if ($url === '') {
            throw new RuntimeException(return_translation('admin_update_release_asset_missing'));
        }

        if (function_exists('curl_init')) {
            $handle = fopen($destination, 'wb');
            if ($handle === false) {
                throw new RuntimeException(return_translation('admin_update_workspace_failed'));
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $handle,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FAILONERROR => false,
            ]);

            $success = curl_exec($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($handle);

            if ($success === false || $statusCode < 200 || $statusCode >= 300) {
                @unlink($destination);
                $details = $error !== '' ? $error : 'HTTP ' . $statusCode;
                throw new RuntimeException(return_translation('admin_update_download_failed') . ' ' . $details);
            }

            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 120,
                'ignore_errors' => true,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);
        $statusCode = $this->extractHttpStatusCode($http_response_header ?? []);

        if ($contents === false) {
            throw new RuntimeException(return_translation('admin_update_download_failed'));
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(return_translation('admin_update_download_failed') . ' HTTP ' . $statusCode);
        }

        if (@file_put_contents($destination, $contents) === false) {
            throw new RuntimeException(return_translation('admin_update_workspace_failed'));
        }
    }

    /**
     * Распаковывает ZIP-архив релиза и возвращает корневую папку пакета.
     */
    protected function extractArchive(string $archivePath, string $extractPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(return_translation('admin_update_extract_failed'));
        }

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new RuntimeException(return_translation('admin_update_extract_failed'));
        }

        $zip->close();

        return $this->resolvePackageRoot($extractPath);
    }

    /**
     * Определяет корневую директорию распакованного релиза.
     */
    protected function resolvePackageRoot(string $extractPath): string
    {
        $currentPath = $extractPath;

        for ($depth = 0; $depth < 5; $depth++) {
            if ($this->looksLikeApplicationRoot($currentPath)) {
                return $currentPath;
            }

            $items = $this->listExtractedItems($currentPath);
            if (count($items) !== 1) {
                break;
            }

            $candidate = $currentPath . '/' . $items[0];
            if (!is_dir($candidate)) {
                break;
            }

            $currentPath = $candidate;
        }

        if ($this->looksLikeApplicationRoot($currentPath)) {
            return $currentPath;
        }

        return $extractPath;
    }

    /**
     * Возвращает значимые элементы распакованного архива без служебного мусора macOS.
     */
    protected function listExtractedItems(string $path): array
    {
        return array_values(array_filter(scandir($path) ?: [], function (string $item): bool {
            if ($item === '.' || $item === '..') {
                return false;
            }

            return !$this->shouldSkipExtractedPath($item);
        }));
    }

    /**
     * Определяет, похожа ли директория на корень пакета CMS.
     */
    protected function looksLikeApplicationRoot(string $path): bool
    {
        $requiredPaths = [
            'app',
            'config',
            'core',
            'public',
        ];

        foreach ($requiredPaths as $requiredPath) {
            if (!is_dir($path . '/' . $requiredPath)) {
                return false;
            }
        }

        return is_file($path . '/composer.json') || is_file($path . '/README.md');
    }

    /**
     * Обеспечивает наличие локального конфига до замены файлов релиза.
     */
    protected function ensureLocalConfigExists(): void
    {
        $localConfigPath = CONFIG . '/config.local.php';
        if (is_file($localConfigPath)) {
            return;
        }

        $payload = [
            'DEBUG' => DEBUG,
            'LAYOUT' => LAYOUT,
            'THEME' => THEME,
            'PATH' => PATH,
            'UPLOADS' => UPLOADS,
            'SITE_NAME' => SITE_NAME,
            'CHAT_ENCRYPTION_KEY' => CHAT_ENCRYPTION_KEY,
            'APP_TIMEZONE' => APP_TIMEZONE,
            'MULTILANGS' => MULTILANGS,
            'LANGS' => LANGS,
            'DB_SETTINGS' => DB_SETTINGS,
            'MAIL_SETTINGS' => MAIL_SETTINGS,
            'PAGINATION_SETTINGS' => PAGINATION_SETTINGS,
        ];

        $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
        if (@file_put_contents($localConfigPath, $content) === false) {
            throw new RuntimeException(return_translation('admin_update_local_config_write_failed'));
        }
    }

    /**
     * Копирует файлы из распакованного релиза в корень приложения, сохраняя локальные данные.
     */
    protected function copyPackageToRoot(string $packageRoot, string $targetRoot): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($packageRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = str_replace('\\', '/', substr($sourcePath, strlen($packageRoot) + 1));

            if ($relativePath === '' || $this->shouldSkipExtractedPath($relativePath) || $this->shouldPreservePath($relativePath)) {
                continue;
            }

            $targetPath = $targetRoot . '/' . $relativePath;

            if ($item->isDir()) {
                $this->ensureDirectory($targetPath);
                continue;
            }

            $this->ensureDirectory(dirname($targetPath));
            if (!@copy($sourcePath, $targetPath)) {
                throw new RuntimeException(return_translation('admin_update_copy_failed') . ' ' . $relativePath);
            }
        }
    }

    /**
     * Возвращает true для путей, которые нельзя перезаписывать релизом.
     */
    protected function shouldPreservePath(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        $preservePrefixes = [
            '.git',
            'tmp',
            'public/uploads',
        ];

        foreach ($preservePrefixes as $prefix) {
            if ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/')) {
                return true;
            }
        }

        return in_array($relativePath, ['config/config.local.php'], true);
    }

    /**
     * Определяет служебные пути из архива, которые нельзя переносить в проект.
     */
    protected function shouldSkipExtractedPath(string $relativePath): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return false;
        }

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '__MACOSX' || $segment === '.DS_Store' || str_starts_with($segment, '._')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Создаёт директорию, если её ещё нет.
     */
    protected function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException(return_translation('admin_update_workspace_failed'));
        }
    }

    /**
     * Удаляет временную директорию рекурсивно.
     */
    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    /**
     * Проверяет, что PHP может работать с ZIP-архивами.
     */
    protected function supportsZipUpdates(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /**
     * Проверяет, можно ли писать в путь или в его родительскую директорию.
     */
    protected function isWritablePath(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        return is_writable(dirname($path));
    }

    /**
     * Возвращает локальное состояние git-репозитория.
     */
    protected function getLocalGitState(): array
    {
        $defaults = [
            'git_available' => false,
            'is_git_repo' => false,
            'origin_url' => '',
            'branch' => '',
            'commit_hash' => '',
            'short_commit' => '',
            'git_tag' => '',
            'git_describe' => '',
            'is_clean' => false,
            'is_update_clean' => true,
            'dirty_files' => [],
            'blocking_dirty_files' => [],
            'ignored_dirty_files' => [],
        ];

        $gitCheck = $this->runCommand('command -v git', ROOT);
        if ($gitCheck['exit_code'] !== 0) {
            return $defaults;
        }

        $repoCheck = $this->runCommand('git rev-parse --is-inside-work-tree', ROOT);
        if ($repoCheck['exit_code'] !== 0 || trim($repoCheck['stdout']) !== 'true') {
            return array_merge($defaults, [
                'git_available' => true,
            ]);
        }

        $originUrl = trim($this->runCommand('git config --get remote.origin.url', ROOT)['stdout']);
        $branch = trim($this->runCommand('git branch --show-current', ROOT)['stdout']);
        $commitHash = trim($this->runCommand('git rev-parse HEAD', ROOT)['stdout']);
        $exactTag = trim($this->runCommand('git describe --tags --exact-match', ROOT)['stdout']);
        $describe = trim($this->runCommand('git describe --tags --always', ROOT)['stdout']);
        $statusOutput = trim($this->runCommand('git status --porcelain', ROOT)['stdout']);
        $dirtyFiles = $statusOutput === '' ? [] : $this->normalizeDirtyFiles($statusOutput);
        $blockingDirtyFiles = $this->filterBlockingDirtyFiles($dirtyFiles);
        $ignoredDirtyFiles = array_values(array_diff($dirtyFiles, $blockingDirtyFiles));

        return [
            'git_available' => true,
            'is_git_repo' => true,
            'origin_url' => $originUrl,
            'branch' => $branch,
            'commit_hash' => $commitHash,
            'short_commit' => $commitHash !== '' ? substr($commitHash, 0, 7) : '',
            'git_tag' => $exactTag,
            'git_describe' => $describe !== '' ? $describe : ($commitHash !== '' ? substr($commitHash, 0, 7) : ''),
            'is_clean' => $statusOutput === '',
            'is_update_clean' => $blockingDirtyFiles === [],
            'dirty_files' => $dirtyFiles,
            'blocking_dirty_files' => $blockingDirtyFiles,
            'ignored_dirty_files' => $ignoredDirtyFiles,
        ];
    }

    /**
     * Определяет, нужен ли запуск Composer после обновления кода.
     */
    protected function runComposerIfNeeded(string $previousCommit, string $currentCommit): ?array
    {
        $diffResult = $this->runCommand(
            'git diff --name-only ' . escapeshellarg($previousCommit) . ' ' . escapeshellarg($currentCommit) . ' -- composer.json composer.lock',
            ROOT
        );

        if ($diffResult['exit_code'] !== 0 || trim($diffResult['stdout']) === '') {
            return null;
        }

        $composerBinary = $this->detectComposerBinary();
        if ($composerBinary === '') {
            return [
                'status' => 'warning',
                'message' => return_translation('admin_update_composer_required'),
            ];
        }

        $installResult = $this->runCommand(
            $composerBinary . ' install --no-interaction --prefer-dist --optimize-autoloader',
            ROOT
        );

        if ($installResult['exit_code'] !== 0) {
            return [
                'status' => 'warning',
                'message' => $this->formatCommandError($installResult, return_translation('admin_update_composer_failed')),
            ];
        }

        return [
            'status' => 'success',
            'message' => return_translation('admin_update_success'),
        ];
    }

    /**
     * Фиксирует текущее состояние composer-файлов для ZIP-обновления.
     */
    protected function captureDependencyFingerprint(): array
    {
        return [
            'composer_json' => is_file(ROOT . '/composer.json') ? sha1_file(ROOT . '/composer.json') : '',
            'composer_lock' => is_file(ROOT . '/composer.lock') ? sha1_file(ROOT . '/composer.lock') : '',
        ];
    }

    /**
     * При изменении composer-файлов запускает Composer либо возвращает warning.
     */
    protected function runComposerIfDependencyFilesChanged(array $before): ?array
    {
        $after = $this->captureDependencyFingerprint();
        if (($before['composer_json'] ?? '') === ($after['composer_json'] ?? '')
            && ($before['composer_lock'] ?? '') === ($after['composer_lock'] ?? '')
        ) {
            return null;
        }

        if (!$this->canRunProcesses()) {
            return [
                'status' => 'warning',
                'message' => return_translation('admin_update_composer_required'),
            ];
        }

        $composerBinary = $this->detectComposerBinary();
        if ($composerBinary === '') {
            return [
                'status' => 'warning',
                'message' => return_translation('admin_update_composer_required'),
            ];
        }

        $installResult = $this->runCommand(
            $composerBinary . ' install --no-interaction --prefer-dist --optimize-autoloader',
            ROOT
        );

        if ($installResult['exit_code'] !== 0) {
            return [
                'status' => 'warning',
                'message' => $this->formatCommandError($installResult, return_translation('admin_update_composer_failed')),
            ];
        }

        return [
            'status' => 'success',
            'message' => return_translation('admin_update_success'),
        ];
    }

    /**
     * Определяет доступную команду Composer в окружении.
     */
    protected function detectComposerBinary(): string
    {
        $composerCheck = $this->runCommand('command -v composer', ROOT);
        if ($composerCheck['exit_code'] === 0 && trim($composerCheck['stdout']) !== '') {
            return 'composer';
        }

        $localComposer = ROOT . '/composer.phar';
        if (is_file($localComposer)) {
            return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($localComposer);
        }

        return '';
    }

    /**
     * Возвращает удалённую версию по release/tag или, если их нет, по ближайшему тегу и коммиту ветки.
     */
    protected function resolveRemoteVersion(?array $release, string $branch, string $remoteCommitHash): string
    {
        if (is_array($release)) {
            $releaseVersion = $this->extractReleaseVersion($release);
            if ($releaseVersion !== '0.0.0') {
                return $releaseVersion;
            }
        }

        $tagResult = $this->runCommand(
            'git describe --tags --abbrev=0 ' . escapeshellarg('origin/' . $branch),
            ROOT
        );
        $tag = trim($tagResult['stdout']);
        if ($tag !== '') {
            return $tag;
        }

        return $remoteCommitHash !== '' ? substr($remoteCommitHash, 0, 7) : '';
    }

    /**
     * Запускает shell-команду и возвращает stdout/stderr.
     */
    protected function runCommand(string $command, string $cwd): array
    {
        if (!$this->canRunProcesses()) {
            return [
                'command' => $command,
                'stdout' => '',
                'stderr' => return_translation('admin_update_process_disabled'),
                'exit_code' => 1,
            ];
        }

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['/bin/sh', '-lc', $command], $descriptorSpec, $pipes, $cwd);
        if (!is_resource($process)) {
            return [
                'command' => $command,
                'stdout' => '',
                'stderr' => return_translation('admin_update_process_start_failed'),
                'exit_code' => 1,
            ];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'command' => $command,
            'stdout' => trim((string)$stdout),
            'stderr' => trim((string)$stderr),
            'exit_code' => (int)proc_close($process),
        ];
    }

    /**
     * Проверяет, разрешены ли системные процессы в текущем PHP-окружении.
     */
    protected function canRunProcesses(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    /**
     * Выбирает репозиторий из настроек или origin.
     */
    protected function resolveRepository(array $settings, string $originUrl = ''): string
    {
        $candidate = trim((string)($settings['updater_github_repository'] ?? ''));
        if ($candidate === '') {
            $candidate = $originUrl;
        }

        return $this->normalizeRepository($candidate);
    }

    /**
     * Выбирает ветку из настроек, локального репозитория или значения по умолчанию.
     */
    protected function resolveBranch(array $settings, string $localBranch = ''): string
    {
        $branch = $this->normalizeBranch((string)($settings['updater_github_branch'] ?? ''));
        if ($branch === '') {
            $branch = $this->normalizeBranch($localBranch);
        }

        return $branch !== '' ? $branch : 'main';
    }

    /**
     * Приводит значение репозитория к формату owner/repo.
     */
    protected function normalizeRepository(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('~^git@github\.com:~i', '', $value) ?? $value;
        $value = preg_replace('~^https?://github\.com/~i', '', $value) ?? $value;
        $value = preg_replace('~\.git$~i', '', $value) ?? $value;
        $value = trim($value, '/');

        if (preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $value)) {
            return $value;
        }

        return '';
    }

    /**
     * Приводит имя ветки к безопасному формату для git-команд.
     */
    protected function normalizeBranch(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('~^(?!-)[A-Za-z0-9._/-]+$~', $value) !== 1) {
            return '';
        }

        return $value;
    }

    /**
     * Сравнивает версии в стиле semver, если это возможно.
     */
    protected function compareVersions(string $localVersion, string $remoteVersion): ?int
    {
        $localComparable = $this->normalizeComparableVersion($localVersion);
        $remoteComparable = $this->normalizeComparableVersion($remoteVersion);

        if ($localComparable === '' || $remoteComparable === '') {
            return null;
        }

        return version_compare($localComparable, $remoteComparable);
    }

    /**
     * Нормализует строку версии для version_compare.
     */
    protected function normalizeComparableVersion(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('~^v~', '', $value) ?? $value;
        $value = str_replace([' ', '_'], '-', $value);

        if (preg_match('~(\d+(?:\.\d+)+(?:-[a-z0-9.-]+)?)~', $value, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Выделяет строку версии из данных релиза.
     */
    protected function extractReleaseVersion(array $release): string
    {
        $candidates = [
            trim((string)($release['name'] ?? '')),
            trim((string)($release['tag_name'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '0.0.0';
    }

    /**
     * Сравнивает даты релизов, если версии не удалось распарсить.
     */
    protected function fallbackReleaseComparison(string $localReleasedAt, string $remotePublishedAt): bool
    {
        $localTime = $localReleasedAt !== '' ? strtotime($localReleasedAt) : false;
        $remoteTime = $remotePublishedAt !== '' ? strtotime($remotePublishedAt) : false;

        return $localTime !== false && $remoteTime !== false && $remoteTime > $localTime;
    }

    /**
     * Сохраняет результат последней проверки в таблицу настроек.
     */
    protected function persistLastCheck(array $payload): void
    {
        $this->siteSettings->setMany([
            'updater_last_check_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updater_last_checked_at' => (string)($payload['checked_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }

    /**
     * Декодирует сохранённый JSON последней проверки.
     */
    protected function decodeLastCheck(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Преобразует вывод git status в список путей.
     */
    protected function normalizeDirtyFiles(string $statusOutput): array
    {
        $files = [];

        foreach (preg_split('/\R/', $statusOutput) ?: [] as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            $path = strlen($line) > 3 ? substr($line, 3) : $line;
            if (str_contains($path, ' -> ')) {
                $parts = explode(' -> ', $path);
                $path = (string)end($parts);
            }

            $path = ltrim(trim($path), './');
            if ($path === '') {
                continue;
            }

            $files[] = $path;
        }

        return array_values(array_unique($files));
    }

    /**
     * Отбрасывает runtime-файлы и служебные vendor-объекты, которые не должны блокировать обновление.
     */
    protected function filterBlockingDirtyFiles(array $dirtyFiles): array
    {
        return array_values(array_filter($dirtyFiles, function (string $path): bool {
            return !$this->shouldIgnoreDirtyFile($path);
        }));
    }

    /**
     * Определяет, можно ли игнорировать локальное изменение для целей автообновления.
     */
    protected function shouldIgnoreDirtyFile(string $path): bool
    {
        $normalizedPath = ltrim(str_replace('\\', '/', trim($path)), '/');

        $ignoredExact = [
            '.DS_Store',
            'tmp/error.log',
            'vendor/symfony/var-dumper/Resources/bin/var-dump-server',
        ];

        if (in_array($normalizedPath, $ignoredExact, true)) {
            return true;
        }

        $ignoredPrefixes = [
            '__MACOSX/',
            'tmp/',
            'vendor/bin/',
        ];

        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($normalizedPath, $prefix)) {
                return true;
            }
        }

        foreach (explode('/', $normalizedPath) as $segment) {
            if ($segment !== '' && str_starts_with($segment, '._')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Строит короткое описание релиза для UI.
     */
    protected function buildReleaseExcerpt(string $body): string
    {
        $body = preg_replace('/[`#>*_-]+/u', ' ', $body) ?? $body;
        $body = preg_replace('/\s+/u', ' ', trim($body)) ?? trim($body);

        return mb_substr($body, 0, 240);
    }

    /**
     * Собирает понятное сообщение по итогу проверки обновлений.
     */
    protected function resolveCheckMessage(string $branchStatus, bool $updateAvailable): string
    {
        return match ($branchStatus) {
            'behind' => return_translation('admin_update_check_available'),
            'no_local_commit' => $updateAvailable
                ? return_translation('admin_update_check_available')
                : return_translation('admin_update_check_none'),
            'ahead' => return_translation('admin_update_branch_ahead'),
            'diverged' => return_translation('admin_update_branch_diverged'),
            'not_applicable' => $updateAvailable
                ? return_translation('admin_update_check_available')
                : return_translation('admin_update_check_none'),
            'identical' => $updateAvailable
                ? return_translation('admin_update_check_available')
                : return_translation('admin_update_check_none'),
            default => $updateAvailable
                ? return_translation('admin_update_check_available')
                : return_translation('admin_update_check_none'),
        };
    }

    /**
     * Собирает URL GitHub API для конкретного репозитория.
     */
    protected function buildGithubApiUrl(string $repository, string $path): string
    {
        return 'https://api.github.com/repos/' . str_replace('%2F', '/', rawurlencode($repository)) . $path;
    }

    /**
     * Собирает стандартные заголовки GitHub API.
     */
    protected function buildGithubHeaders(string $token = ''): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: FIREBALL_CMS-Updater',
        ];

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Возвращает сохранённый commit последнего успешного ZIP/git-обновления.
     */
    protected function getStoredInstalledCommit(array $settings): string
    {
        return trim((string)($settings['updater_last_installed_commit'] ?? ''));
    }

    /**
     * Собирает заголовки для скачивания ZIP-архива релиза.
     */
    protected function buildGithubDownloadHeaders(string $token = '', string $downloadMode = 'direct'): array
    {
        $headers = [
            'User-Agent: FIREBALL_CMS-Updater',
        ];

        if ($downloadMode === 'release_asset_api') {
            $headers[] = 'Accept: application/octet-stream';
            $headers[] = 'X-GitHub-Api-Version: 2022-11-28';
        } elseif ($downloadMode === 'repository_zipball_api') {
            $headers[] = 'Accept: application/vnd.github+json';
            $headers[] = 'X-GitHub-Api-Version: 2022-11-28';
        }

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Извлекает HTTP-код из массива ответных заголовков stream wrapper.
     */
    protected function extractHttpStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $matches)) {
                return (int)$matches[1];
            }
        }

        return 200;
    }

    /**
     * Формирует удобное текстовое сообщение по ошибке системной команды.
     */
    protected function formatCommandError(array $result, string $fallbackMessage): string
    {
        $details = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);

        return $details !== '' ? $fallbackMessage . ' ' . $details : $fallbackMessage;
    }

}
