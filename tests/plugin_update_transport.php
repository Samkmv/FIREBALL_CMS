<?php
declare(strict_types=1);

// Offline transport regressions: no real network, database, mail, or CMS files.
require dirname(__DIR__) . '/vendor/autoload.php';

function return_translation(string $key): string { return $key; }

final class PluginTransportSettings extends App\Models\SiteSetting
{
    public array $values = [];
    public function all(): array { return $this->values; }
}

final class PluginTransportProbe extends App\Services\PluginUpdateService
{
    public array $requests = [];
    public array $downloads = [];
    public ?Closure $respond = null;
    public ?Closure $download = null;

    public function __construct(public PluginTransportSettings $settings)
    {
        $this->siteSettings = $settings;
    }

    public function metadata(array $config, string $slug, ?string $commit = null, bool $allowMissing = false): array
    {
        return (new ReflectionMethod(App\Services\PluginUpdateService::class, 'fetchRemotePluginMetadata'))
            ->invoke($this, $config, $slug, $commit, $allowMissing);
    }

    public function archive(array $config, string $commit, string $destination): void
    {
        $this->downloadPluginArchive($config, $commit, $destination);
    }

    public function decode(array $response): array
    {
        return (new ReflectionMethod(App\Services\PluginUpdateService::class, 'decodeGithubJson'))
            ->invoke($this, $response);
    }

    public function stale(array $state): bool
    {
        return (new ReflectionMethod(App\Services\PluginUpdateService::class, 'shouldRefreshState'))
            ->invoke($this, $state);
    }

    public function retryTime(): int
    {
        return (new ReflectionProperty(App\Services\PluginUpdateService::class, 'retryNotBefore'))->getValue($this);
    }

    protected function httpGet(string $url, array $headers = []): array
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers];
        if ($this->respond === null) throw new RuntimeException('Unexpected offline HTTP request');
        return ($this->respond)($url, $headers);
    }

    protected function downloadFile(string $url, string $destination, array $headers = []): void
    {
        $this->downloads[] = ['url' => $url, 'headers' => $headers, 'destination' => $destination];
        if ($this->download !== null) ($this->download)($url, $destination, $headers);
    }
}

$checks = [];
function transportCheck(string $description, bool $condition): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $description);
    $checks[] = $description;
}
function transportFailure(callable $operation): string
{
    try { $operation(); return ''; } catch (Throwable $exception) { return $exception->getMessage(); }
}
function transportResponse(int $status, mixed $payload): array
{
    return ['status_code' => $status, 'body' => is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR)];
}
function transportConfig(string $slug, string $repository = 'fixture/public', string $branch = 'main'): array
{
    return ['repository' => $repository, 'branch' => $branch, 'path' => 'plugins/' . $slug];
}
function transportManifest(string $slug): array
{
    return ['slug' => $slug, 'version' => '2.0.0', 'name' => 'Offline fixture'];
}
function transportHasCredentials(array $headers): bool
{
    return (bool)array_filter($headers, static fn(string $header): bool => (bool)preg_match('/^(?:authorization|cookie):/i', $header));
}

$settings = new PluginTransportSettings();
$settings->values['updater_github_token'] = 'fixture-invalid-secret-not-a-real-token';
$sha = str_repeat('a', 40);
$otherSha = str_repeat('b', 40);
$probe = new PluginTransportProbe($settings);
$probe->respond = static function (string $url) use ($sha): array {
    if (str_contains($url, '.git/info/refs?')) return transportResponse(200, $sha . " refs/heads/main\0multi_ack\n");
    if (str_starts_with($url, 'https://raw.githubusercontent.com/fixture/public/' . $sha . '/plugins/')) {
        $slug = basename(dirname((string)parse_url($url, PHP_URL_PATH)));
        return transportResponse(200, transportManifest($slug));
    }
    throw new RuntimeException('Public fixture must not depend on GitHub REST API');
};
foreach (['calendar', 'camera-manager', 'reel-player', 'subscriptions', 'toy-car-rental', 'vpn-manager-v2'] as $slug) {
    $remote = $probe->metadata(transportConfig($slug), $slug);
    transportCheck($slug . ': public metadata has a pinned commit and matching slug', $remote['commit'] === $sha && $remote['metadata']['slug'] === $slug);
}
$heads = array_filter($probe->requests, static fn(array $request): bool => str_contains($request['url'], '.git/info/refs?'));
$raw = array_filter($probe->requests, static fn(array $request): bool => str_starts_with($request['url'], 'https://raw.githubusercontent.com/'));
transportCheck('Six plugins in one repository share a single branch HEAD request', count($heads) === 1);
transportCheck('Each plugin manifest is read from its immutable commit URL', count($raw) === 6 && count($probe->requests) === 7);
transportCheck('A configured invalid token is never sent to public HEAD or raw endpoints', !array_filter($probe->requests, static fn(array $request): bool => transportHasCredentials($request['headers'])));
transportCheck('Manifest URLs never confuse a commit SHA with a branch ref', !array_filter($raw, static fn(array $request): bool => str_contains($request['url'], '/refs/heads/')));
$requestsBeforeRepeat = count($probe->requests);
$probe->metadata(transportConfig('subscriptions'), 'subscriptions', $sha);
transportCheck('Repeated metadata reads at the same immutable commit reuse the validated result', count($probe->requests) === $requestsBeforeRepeat);

$isolated = new PluginTransportProbe($settings);
$isolated->respond = static function (string $url) use ($sha, $otherSha): array {
    if (str_contains($url, '.git/info/refs?')) return transportResponse(200,
        $sha . " refs/heads/main\0multi_ack\n" . $otherSha . " refs/heads/preview\n");
    if (str_starts_with($url, 'https://raw.githubusercontent.com/')) return transportResponse(200, transportManifest('isolated'));
    throw new RuntimeException('Unexpected branch-cache fixture request');
};
$isolatedMain = $isolated->metadata(transportConfig('isolated'), 'isolated');
$isolatedPreview = $isolated->metadata(transportConfig('isolated', 'fixture/public', 'preview'), 'isolated');
$isolatedOther = $isolated->metadata(transportConfig('isolated', 'fixture/other'), 'isolated');
transportCheck('HEAD caching is isolated by both repository and branch', $isolatedMain['commit'] === $sha && $isolatedPreview['commit'] === $otherSha && $isolatedOther['commit'] === $sha
    && count(array_filter($isolated->requests, static fn(array $request): bool => str_contains($request['url'], '.git/info/refs?'))) === 3);

$known = new PluginTransportProbe($settings);
$known->respond = static function (string $url) use ($otherSha): array {
    if ($url !== 'https://raw.githubusercontent.com/fixture/public/' . $otherSha . '/plugins/known/plugin.json') throw new RuntimeException('Known commit must not resolve a moving branch');
    return transportResponse(200, transportManifest('known'));
};
transportCheck('An explicitly known commit is fetched without resolving the branch', $known->metadata(transportConfig('known'), 'known', $otherSha)['commit'] === $otherSha && count($known->requests) === 1);

foreach (['not JSON', '{"slug":"different-plugin","version":"2.0.0"}', '{"slug":"broken"}'] as $invalid) {
    $invalidProbe = new PluginTransportProbe($settings);
    $invalidProbe->respond = static function (string $url) use ($invalid): array {
        if (str_starts_with($url, 'https://raw.githubusercontent.com/')) return transportResponse(200, $invalid);
        throw new RuntimeException('An invalid successful manifest must not fall through to another source');
    };
    $message = transportFailure(fn() => $invalidProbe->metadata(transportConfig('broken'), 'broken', $sha));
    transportCheck('A malformed or mismatched public manifest fails closed: ' . $invalid, $message === 'admin_plugin_updates_remote_invalid' && count($invalidProbe->requests) === 1);
}

$private = new PluginTransportProbe($settings);
$private->respond = static function (string $url, array $headers) use ($sha): array {
    if (str_contains($url, '.git/info/refs?') || str_starts_with($url, 'https://raw.githubusercontent.com/')) return transportResponse(404, 'not public');
    if (str_contains($url, '/commits/main')) return transportResponse(200, ['sha' => $sha]);
    if (str_contains($url, '/contents/plugins/private/plugin.json?ref=' . $sha)) return transportResponse(200, ['content' => base64_encode(json_encode(transportManifest('private'), JSON_THROW_ON_ERROR))]);
    throw new RuntimeException('Unexpected private fixture URL');
};
$privateRemote = $private->metadata(transportConfig('private', 'fixture/private'), 'private');
$privateApi = array_values(array_filter($private->requests, static fn(array $request): bool => str_starts_with($request['url'], 'https://api.github.com/')));
transportCheck('Private repository metadata still falls back to the authenticated GitHub API', $privateRemote['commit'] === $sha && $privateRemote['metadata']['slug'] === 'private' && count($privateApi) === 2);
transportCheck('Private API fallback retains the configured token', !array_filter($privateApi, static fn(array $request): bool => !in_array('Authorization: Bearer fixture-invalid-secret-not-a-real-token', $request['headers'], true)));
transportCheck('Private-source probes never expose the token to public endpoints', !array_filter($private->requests, static fn(array $request): bool => !str_starts_with($request['url'], 'https://api.github.com/') && transportHasCredentials($request['headers'])));

$network = new PluginTransportProbe($settings);
$network->respond = static function (string $url): array {
    throw new RuntimeException('Authorization: Bearer fixture-invalid-secret-not-a-real-token; unsafe-network-details');
};
$networkMessages = [];
foreach (['network-a', 'network-b', 'network-c'] as $slug) $networkMessages[] = transportFailure(fn() => $network->metadata(transportConfig($slug), $slug));
transportCheck('Repeated failed HEAD discovery is cached across plugins in the same request', count($network->requests) === 2);
transportCheck('Network failures are translated without exposing underlying credentials or proxy details', $networkMessages === array_fill(0, 3, 'admin_plugin_updates_remote_network_error'));

$limited = new PluginTransportProbe($settings);
$limited->respond = static function (string $url): array {
    if (str_contains($url, '.git/info/refs?')) return transportResponse(404, 'not public');
    return transportResponse(403, ['message' => 'API rate limit exceeded']);
};
foreach (['limited-a', 'limited-b', 'limited-c'] as $slug) transportFailure(fn() => $limited->metadata(transportConfig($slug), $slug));
transportCheck('One rate-limited branch does not trigger a fresh REST request for every plugin', count($limited->requests) === 2);

$missing = new PluginTransportProbe($settings);
$missing->respond = static fn(string $url): array => transportResponse(404, ['message' => 'Not Found']);
transportCheck('Allowed missing historical manifests remain an empty immutable result', $missing->metadata(transportConfig('missing'), 'missing', $sha, true) === ['metadata' => [], 'commit' => $sha]);
transportCheck('Current missing manifests are not accepted as installable packages', transportFailure(fn() => $missing->metadata(transportConfig('missing'), 'missing', $sha)) !== '');

$archive = new PluginTransportProbe($settings);
$archive->respond = static fn(string $url): array => transportResponse(200, transportManifest('archive'));
$archive->metadata(transportConfig('archive'), 'archive', $sha);
$archive->archive(transportConfig('archive'), $sha, 'offline-fixture-not-written.zip');
transportCheck('Public archives use codeload pinned to the validated commit, not a moving branch', count($archive->downloads) === 1 && $archive->downloads[0]['url'] === 'https://codeload.github.com/fixture/public/zip/' . $sha);
transportCheck('Public archive downloads do not send the configured token', !transportHasCredentials($archive->downloads[0]['headers']));
$anonymousSettings = new PluginTransportSettings();
$anonymousArchive = new PluginTransportProbe($anonymousSettings);
$anonymousArchive->archive(transportConfig('archive'), $sha, 'offline-fixture-not-written.zip');
transportCheck('An installation without a token downloads directly from public codeload', count($anonymousArchive->downloads) === 1 && $anonymousArchive->downloads[0]['url'] === 'https://codeload.github.com/fixture/public/zip/' . $sha && !transportHasCredentials($anonymousArchive->downloads[0]['headers']));
$privateArchive = new PluginTransportProbe($settings);
$privateArchive->archive(transportConfig('archive', 'fixture/private'), $sha, 'offline-fixture-not-written.zip');
transportCheck('Unproven private archives keep their authenticated API download at the same SHA', count($privateArchive->downloads) === 1 && $privateArchive->downloads[0]['url'] === 'https://api.github.com/repos/fixture/private/zipball/' . $sha && in_array('Authorization: Bearer fixture-invalid-secret-not-a-real-token', $privateArchive->downloads[0]['headers'], true));
$fallbackArchive = new PluginTransportProbe($settings);
$fallbackArchive->respond = static fn(string $url): array => transportResponse(200, transportManifest('archive'));
$fallbackArchive->metadata(transportConfig('archive'), 'archive', $sha);
$fallbackArchive->download = static function (string $url): void {
    if (str_starts_with($url, 'https://codeload.github.com/')) throw new RuntimeException('Offline codeload unavailable: HTTP 404');
};
$fallbackArchive->archive(transportConfig('archive'), $sha, 'offline-fixture-not-written.zip');
transportCheck('An unavailable public codeload endpoint can fall back to the API at the identical commit', count($fallbackArchive->downloads) === 2 && $fallbackArchive->downloads[1]['url'] === 'https://api.github.com/repos/fixture/public/zipball/' . $sha && in_array('Authorization: Bearer fixture-invalid-secret-not-a-real-token', $fallbackArchive->downloads[1]['headers'], true));
transportCheck('Public archive fallback does not send credentials to its first public endpoint', !transportHasCredentials($fallbackArchive->downloads[0]['headers']));
$invalidArchive = new PluginTransportProbe($settings);
transportCheck('Archive paths cannot accept a moving branch or malformed commit identifier', transportFailure(fn() => $invalidArchive->archive(transportConfig('archive'), 'main', 'offline-fixture-not-written.zip')) === 'admin_plugin_updates_remote_invalid' && $invalidArchive->downloads === []);

$diagnostics = [];
foreach ([401 => 'Bad credentials', 403 => 'Resource not accessible', 404 => 'Not Found', 429 => 'Too many requests', 503 => 'Service Unavailable'] as $status => $message) {
    $diagnostics[$status] = transportFailure(fn() => $probe->decode(transportResponse($status, ['message' => $message . ' fixture-invalid-secret-not-a-real-token <script>unsafe-response</script>'])));
    transportCheck('HTTP ' . $status . ' has a safe diagnostic without arbitrary response content', $diagnostics[$status] !== '' && !str_contains($diagnostics[$status], 'fixture-invalid-secret') && !str_contains($diagnostics[$status], 'unsafe-response') && !str_contains($diagnostics[$status], '<script>'));
}
transportCheck('Authentication, access, missing source, rate limit and server errors are distinguishable', count(array_unique($diagnostics)) === count($diagnostics));
$rate403 = transportFailure(fn() => $probe->decode(transportResponse(403, ['message' => 'API rate limit exceeded for fixture'])));
transportCheck('GitHub primary rate-limit HTTP 403 is classified like HTTP 429', $rate403 === $diagnostics[429]);
transportCheck('Successful malformed API JSON remains invalid rather than unavailable', transportFailure(fn() => $probe->decode(transportResponse(200, 'not-json'))) === 'admin_plugin_updates_remote_invalid');
transportCheck('Oversized metadata responses are rejected without reflecting their contents', transportFailure(fn() => $probe->decode(transportResponse(200, str_repeat('x', 1048577)))) === 'admin_plugin_updates_remote_invalid');
transportCheck('Successful API JSON remains readable', $probe->decode(transportResponse(200, ['sha' => $sha])) === ['sha' => $sha]);

foreach ([
    ['label' => 'Retry-After delta seconds', 'status' => 429, 'headers' => ['retry-after' => '900'], 'seconds' => 900],
    ['label' => 'Retry-After HTTP date', 'status' => 429, 'headers' => ['retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() + 1200)], 'seconds' => 1200],
    ['label' => 'Exhausted quota reset timestamp', 'status' => 403, 'headers' => ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string)(time() + 1800)], 'seconds' => 1800],
    ['label' => 'Missing backoff headers', 'status' => 429, 'headers' => [], 'seconds' => 300],
    ['label' => 'Malformed backoff values', 'status' => 429, 'headers' => ['retry-after' => 'not-a-date', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => 'not-an-epoch'], 'seconds' => 300],
    ['label' => 'Past Retry-After HTTP date', 'status' => 429, 'headers' => ['retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() - 3600)], 'seconds' => 300],
    ['label' => 'Backoff below the minimum', 'status' => 429, 'headers' => ['retry-after' => '1'], 'seconds' => 300],
    ['label' => 'Huge numeric Retry-After cannot overflow', 'status' => 429, 'headers' => ['retry-after' => '9999999999999999999999999999999999999999'], 'seconds' => 86400],
    ['label' => 'Remote quota reset is capped at one day', 'status' => 403, 'headers' => ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string)(time() + 172800)], 'seconds' => 86400],
] as $case) {
    $backoff = new PluginTransportProbe($settings);
    $beforeDecode = time();
    $response = transportResponse($case['status'], ['message' => 'Safe fixture status']);
    $response['headers'] = $case['headers'];
    $message = transportFailure(fn() => $backoff->decode($response));
    $afterDecode = time();
    transportCheck($case['label'] . ' has a safe bounded retry deadline', $message === 'admin_update_github_rate_limit'
        && $backoff->retryTime() >= $beforeDecode + $case['seconds'] - 2
        && $backoff->retryTime() <= $afterDecode + $case['seconds'] + 2);
}
$longestBackoff = new PluginTransportProbe($settings);
$resetEpoch = time() + 1800;
$combinedResponse = transportResponse(429, ['message' => 'Too many requests']);
$combinedResponse['headers'] = ['retry-after' => '900', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string)$resetEpoch];
transportFailure(fn() => $longestBackoff->decode($combinedResponse));
transportCheck('Combined retry hints use the later safe deadline', $longestBackoff->retryTime() === $resetEpoch);
$shorterResponse = transportResponse(429, ['message' => 'Too many requests']);
$shorterResponse['headers'] = ['retry-after' => '300'];
transportFailure(fn() => $longestBackoff->decode($shorterResponse));
transportCheck('Another response cannot shorten an existing quota deadline within the request', $longestBackoff->retryTime() === $resetEpoch);
$forbiddenBackoff = new PluginTransportProbe($settings);
$forbiddenResponse = transportResponse(403, ['message' => 'Resource not accessible']);
$forbiddenResponse['headers'] = ['x-ratelimit-remaining' => '10', 'x-ratelimit-reset' => (string)(time() + 3600)];
transportCheck('Ordinary permission errors do not become rate-limit errors from a reset header alone', transportFailure(fn() => $forbiddenBackoff->decode($forbiddenResponse)) === 'admin_plugin_updates_remote_forbidden' && $forbiddenBackoff->retryTime() === 0);

$now = time();
transportCheck('A first check runs immediately', $probe->stale([]));
transportCheck('A recent failed check has a retry cooldown', !$probe->stale(['status' => 'error', 'checked_at' => date('Y-m-d H:i:s', $now - 60)]));
transportCheck('A failed check retries after five minutes rather than six hours', $probe->stale(['status' => 'error', 'checked_at' => date('Y-m-d H:i:s', $now - 301)]));
transportCheck('Successful metadata remains cached for six hours', !$probe->stale(['status' => 'ok', 'checked_at' => date('Y-m-d H:i:s', $now - 301)]));
transportCheck('A successful check refreshes after six hours', $probe->stale(['status' => 'ok', 'checked_at' => date('Y-m-d H:i:s', $now - 21601)]));
transportCheck('Invalid or future timestamps cannot suppress checks forever', $probe->stale(['status' => 'ok', 'checked_at' => 'not-a-time']) && $probe->stale(['status' => 'ok', 'checked_at' => date('Y-m-d H:i:s', $now + 60)]));
$oldCheck = date('Y-m-d H:i:s', $now - 21601);
$futureRetry = date('Y-m-d H:i:s', $now + 900);
transportCheck('Persisted quota backoff defers an otherwise stale failed check', !$probe->stale(['status' => 'error', 'error_stage' => 'check', 'checked_at' => $oldCheck, 'retry_after' => $futureRetry]));
transportCheck('An expired quota deadline allows the next scheduled check', $probe->stale(['status' => 'error', 'error_stage' => 'check', 'checked_at' => $oldCheck, 'retry_after' => date('Y-m-d H:i:s', $now - 1)]));
transportCheck('Malformed persisted retry timestamps cannot block checks', $probe->stale(['status' => 'error', 'error_stage' => 'check', 'checked_at' => $oldCheck, 'retry_after' => 'invalid timestamp']));
transportCheck('A persisted retry timestamp outside the one-day bound cannot block checks indefinitely', $probe->stale(['status' => 'error', 'error_stage' => 'check', 'checked_at' => $oldCheck, 'retry_after' => date('Y-m-d H:i:s', $now + 172800)]));
transportCheck('Successful states ignore stale error backoff fields', $probe->stale(['status' => 'ok', 'checked_at' => $oldCheck, 'retry_after' => $futureRetry]));
transportCheck('Installation errors ignore check-only backoff fields', $probe->stale(['status' => 'error', 'error_stage' => 'install', 'checked_at' => $oldCheck, 'retry_after' => $futureRetry]));
transportCheck('A recent installation error retains the existing six-hour metadata cache', !$probe->stale(['status' => 'error', 'error_stage' => 'install', 'checked_at' => date('Y-m-d H:i:s', $now - 301), 'retry_after' => $futureRetry]));

echo 'PASS ' . count($checks) . " offline plugin update transport checks\n";
