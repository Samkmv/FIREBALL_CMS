<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/config/config.php';
require $root . '/vendor/autoload.php';

use App\Models\SiteSetting;
use App\Services\UpdateCenter;

function updaterFallbackAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class UpdateCenterGithubFallbackProbe extends UpdateCenter
{
    /** @var callable(string, array): array */
    private $responseFactory;

    public array $requestedUrls = [];

    public function __construct(callable $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        parent::__construct(new SiteSetting());
    }

    protected function httpGet(string $url, array $headers = []): array
    {
        $this->requestedUrls[] = $url;

        return ($this->responseFactory)($url, $headers);
    }

    public function remoteVersion(string $repository, string $branch, string $token = ''): array
    {
        return $this->fetchRemoteVersionMetadata($repository, $branch, $token);
    }

    public function branchState(string $repository, string $branch, string $localCommit, string $token = ''): array
    {
        return $this->fetchBranchState($repository, $branch, $localCommit, $token);
    }

    public function branchArchive(string $repository, string $branch, string $token = ''): array
    {
        $release = $this->buildBranchRelease(
            $repository,
            $branch,
            ['version' => '1.8.2', 'released_at' => '', 'summary' => 'Release', 'changes' => []],
            '',
            $token
        );

        return $this->resolveReleaseArchive($release, $repository);
    }
}

$versionSource = <<<'PHP'
<?php

return [
    'name' => 'FIREBALL_CMS',
    'version' => '1.8.2',
    'released_at' => '2026-08-12',
    'summary' => 'Release',
    'changes' => ['One', 'Two'],
];
PHP;

$versionProbe = new UpdateCenterGithubFallbackProbe(static fn(string $url): array => [
    'status_code' => 200,
    'body' => $versionSource,
]);
$version = $versionProbe->remoteVersion('Samkmv/FIREBALL_CMS', 'main');
updaterFallbackAssert(($version['version'] ?? '') === '1.8.2', 'Raw version metadata was not parsed.');
updaterFallbackAssert(
    $versionProbe->requestedUrls === [
        'https://raw.githubusercontent.com/Samkmv/FIREBALL_CMS/refs/heads/main/config/version.php',
    ],
    'Public version lookup still calls the rate-limited GitHub REST API.'
);

$remoteCommit = str_repeat('a', 40);
$advertisement = "001e# service=git-upload-pack\n0000"
    . "004f{$remoteCommit} HEAD\0multi_ack\n"
    . "003f{$remoteCommit} refs/heads/main\n0000";
$branchProbe = new UpdateCenterGithubFallbackProbe(static fn(string $url): array => [
    'status_code' => 200,
    'body' => $advertisement,
]);
$branchState = $branchProbe->branchState('Samkmv/FIREBALL_CMS', 'main', str_repeat('b', 40));
updaterFallbackAssert(($branchState['status'] ?? '') === 'behind', 'Changed public branch was not detected.');
updaterFallbackAssert(($branchState['remote_commit_hash'] ?? '') === $remoteCommit, 'Public branch head was not parsed.');
updaterFallbackAssert(
    count(array_filter($branchProbe->requestedUrls, static fn(string $url): bool => str_contains($url, 'api.github.com'))) === 0,
    'Public branch lookup unexpectedly called the GitHub REST API.'
);

$rateLimitProbe = new UpdateCenterGithubFallbackProbe(static function (string $url): array {
    if (str_contains($url, '/info/refs?')) {
        return ['status_code' => 503, 'body' => ''];
    }

    return [
        'status_code' => 403,
        'body' => json_encode(['message' => 'API rate limit exceeded'], JSON_THROW_ON_ERROR),
    ];
});
$rateLimitState = $rateLimitProbe->branchState('Samkmv/FIREBALL_CMS', 'main', '');
updaterFallbackAssert(($rateLimitState['status'] ?? '') === 'unknown', 'API quota exhaustion still blocks ZIP updates.');

$archiveProbe = new UpdateCenterGithubFallbackProbe(static fn(string $url): array => [
    'status_code' => 500,
    'body' => '',
]);
$publicArchive = $archiveProbe->branchArchive('Samkmv/FIREBALL_CMS', 'feature/test');
updaterFallbackAssert(
    ($publicArchive['download_url'] ?? '') === 'https://codeload.github.com/Samkmv/FIREBALL_CMS/zip/refs/heads/feature/test',
    'Public branch archive does not use codeload.github.com.'
);
updaterFallbackAssert(($publicArchive['download_mode'] ?? '') === 'direct', 'Codeload archive must use direct download headers.');

$privateArchive = $archiveProbe->branchArchive('Samkmv/FIREBALL_CMS', 'main', 'token');
updaterFallbackAssert(str_contains((string)($privateArchive['download_url'] ?? ''), 'api.github.com/repos/'), 'Authenticated archive must keep API access.');
updaterFallbackAssert(($privateArchive['download_mode'] ?? '') === 'repository_zipball_api', 'Authenticated archive lost API headers.');

echo json_encode([
    'status' => 'ok',
    'raw_version_metadata' => true,
    'public_branch_head' => true,
    'rate_limit_degraded' => true,
    'codeload_archive' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
