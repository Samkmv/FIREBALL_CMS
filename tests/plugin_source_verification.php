<?php
declare(strict_types=1);

// Offline regression tests. Every generated package lives under tests/.tmp/.
$project = dirname(__DIR__);
require $project . '/vendor/autoload.php';
$scratch = __DIR__ . '/.tmp/plugin-source-verification-' . bin2hex(random_bytes(6));
mkdir($scratch, 0700, true);
define('ROOT', $scratch);
define('STORAGE', ROOT . '/storage');
function return_translation(string $key): string {
    static $messages;
    $messages ??= require dirname(__DIR__) . '/app/languages/ru.php';
    return $messages[$key] ?? $key;
}
function putFixture(string $relative, string $content): void {
    $file = ROOT . '/' . $relative;
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0700, true);
    file_put_contents($file, $content);
}
final class SourceProofSettings extends App\Models\SiteSetting {
    public array $values = [];
    public function all(): array { return $this->values; }
}
final class SourceProofProbe extends App\Services\PluginUpdateService {
    public array $history = [];
    public array $packages = [];
    public array $sourceFiles = [];
    public array $requests = [];
    public array $downloads = [];
    public array $states = [];
    public bool $truncatedTree = false;
    public bool $symlinkTree = false;
    public ?Closure $onGitTree = null;
    public ?array $forcedResponse = null;
    public function __construct(public SourceProofSettings $settings) {
        $this->siteSettings = $settings;
        (new ReflectionProperty(App\Services\PluginUpdateService::class, 'pluginsPath'))->setValue($this, ROOT . '/plugins');
    }
    public function probe(string $slug, string $attempt): array {
        $manager = new FBL\Plugins\PluginManager(ROOT . '/plugins');
        (new ReflectionProperty($manager, 'settingsCache'))->setValue($manager, [$slug => isset($this->states[$slug])
            ? ['__plugin_update_state' => ['setting_value' => json_encode($this->states[$slug])]] : []]);
        (new ReflectionProperty(App\Services\PluginUpdateService::class, 'pluginManager'))->setValue($this, $manager);
        $metadata = json_decode((string)file_get_contents(ROOT . '/plugins/' . $slug . '/plugin.json'), true);
        $workspace = ROOT . '/work/' . $attempt;
        mkdir($workspace, 0700, true);
        return $this->assertInstalledPluginPackage($slug, $metadata,
            ['repository' => 'fixture/repository', 'branch' => 'main', 'path' => 'plugins/' . $slug], $workspace);
    }
    protected function httpGet(string $url, array $headers = []): array {
        $this->requests[] = $url;
        if ($this->forcedResponse !== null) return $this->forcedResponse;
        // These fixtures exercise the authenticated API fallback; public sources
        // are covered independently by plugin_update_transport.php.
        if (str_starts_with($url, 'https://raw.githubusercontent.com/') || str_contains($url, '.git/info/refs?')) {
            return ['status_code' => 404, 'body' => 'Not public'];
        }
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        if (str_contains($url, '/git/trees/')) {
            if ($this->onGitTree !== null) { $callback = $this->onGitTree; $this->onGitTree = null; $callback(); }
            $requested = basename((string)parse_url($url, PHP_URL_PATH));
            $data = null;
            foreach ($this->sourceFiles + $this->packages as $commit => $packages) {
                $packages = ($this->sourceFiles[$commit] ?? []) + ($this->packages[$commit] ?? []);
                $pluginsSha = sha1($commit . '/plugins');
                if ($requested === $commit) $data = ['tree' => [['type' => 'tree', 'mode' => '040000', 'path' => 'plugins', 'sha' => $pluginsSha]]];
                if ($requested === $pluginsSha) $data = ['tree' => array_map(fn($slug) => ['type' => 'tree', 'mode' => '040000', 'path' => $slug, 'sha' => sha1($commit . '/plugins/' . $slug)], array_keys($packages))];
                foreach ($packages as $slug => $files) {
                    if ($requested !== sha1($commit . '/plugins/' . $slug)) continue;
                    $data = ['tree' => []];
                    foreach ($files as $path => $content) $data['tree'][] = ['type' => 'blob', 'mode' => $this->symlinkTree ? '120000' : '100644',
                        'path' => $path, 'sha' => sha1('blob ' . strlen($content) . "\0" . $content), 'size' => strlen($content)];
                }
            }
            if ($data === null) throw new RuntimeException('Unknown fixture Git tree');
            $data['truncated'] = $this->truncatedTree;
        } elseif (str_contains($url, '/commits?')) {
            if (str_ends_with((string)$query['path'], '/plugin.json')) throw new RuntimeException('Manifest-only history must not be used');
            $slug = basename((string)$query['path']);
            $size = (int)$query['per_page'];
            $data = array_map(fn($sha) => ['sha' => $sha], array_slice($this->history[$slug] ?? [], ((int)$query['page'] - 1) * $size, $size));
        } else {
            $sha = (string)($query['ref'] ?? '');
            $path = rawurldecode((string)preg_replace('~^.+/contents/~', '', parse_url($url, PHP_URL_PATH)));
            [, $slug, $relative] = explode('/', $path, 3);
            $files = $this->sourceFiles[$sha][$slug] ?? $this->packages[$sha][$slug] ?? [];
            if (!array_key_exists($relative, $files)) return ['status_code' => 404, 'body' => '{"message":"Not Found"}'];
            $content = $files[$relative];
            $data = ['content' => base64_encode($content), 'type' => 'file', 'path' => $path,
                'sha' => sha1('blob ' . strlen($content) . "\0" . $content), 'size' => strlen($content)];
        }
        return ['status_code' => 200, 'body' => json_encode($data, JSON_THROW_ON_ERROR)];
    }
    protected function downloadFile(string $url, string $destination, array $headers = []): void {
        $sha = basename((string)parse_url($url, PHP_URL_PATH));
        $this->downloads[] = $sha;
        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP error');
        foreach ($this->packages[$sha] as $slug => $files) {
            foreach ($files as $name => $contents) $zip->addFromString('repository-' . $sha . '/plugins/' . $slug . '/' . $name, $contents);
        }
        $zip->close();
    }
}
function packageFixture(string $slug, string $version, string $code): array {
    return ['plugin.json' => json_encode(['slug' => $slug, 'name' => $slug, 'version' => $version]), 'Plugin.php' => '<?php ' . $code];
}
function installFixture(string $slug, array $package): void {
    foreach ($package as $name => $contents) putFixture('plugins/' . $slug . '/' . $name, $contents);
}
$checks = [];
function verifyProof(string $label, bool $condition): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $label);
    $checks[] = $label;
}
function failureProof(callable $call): string {
    try { $call(); return ''; } catch (RuntimeException $error) { return $error->getMessage(); }
}
function fixtureGit(array $arguments): ?string {
    global $project;
    $process = proc_open(['git', '-C', $project, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) return null;
    $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return proc_close($process) === 0 ? $output : null;
}
function catalogRecord(string $version, string $commit, array $required, array $extras = []): array {
    return ['version' => $version, 'commit' => $commit,
        'required_files' => array_map(fn($bytes) => hash('sha256', $bytes), $required),
        'allowed_extras' => array_map(fn($bytes) => hash('sha256', $bytes), $extras)];
}
function catalogDocument(string $slug, array $records, string $repository = 'fixture/repository'): array {
    return ['schema' => 1, 'repository' => $repository, 'plugins' => [$slug => $records]];
}
function catalogMatch(array $document, array $current, array $config = [], string $version = '1.0.0', string $slug = 'fixture-catalog'): ?array {
    putFixture('catalog/unit.json', json_encode($document, JSON_THROW_ON_ERROR));
    return (new App\Services\PluginBaselineCatalog(ROOT . '/catalog/unit.json'))->match($slug, $version,
        $config + ['repository' => 'fixture/repository', 'path' => 'plugins/' . $slug], $current);
}
$previousCeiling = getenv('GIT_CEILING_DIRECTORIES');
putenv('GIT_CEILING_DIRECTORIES=' . dirname(ROOT));
try {
    mkdir(STORAGE, 0700, true); mkdir(ROOT . '/tmp', 0700, true);
    $settings = new SourceProofSettings(); $probe = new SourceProofProbe($settings);
    $installations = new App\Services\ManagedPluginInstallations(ROOT);
    $oldSha = str_repeat('a', 40);
    $old = packageFixture('fixture-history', '1.0.0', 'return "official old package";');
    installFixture('fixture-history', $old); $probe->packages[$oldSha]['fixture-history'] = $old;
    for ($index = 1; $index <= 61; $index++) {
        $sha = hash('sha1', 'history-' . $index);
        $probe->history['fixture-history'][] = $sha;
        $probe->packages[$sha]['fixture-history'] = packageFixture('fixture-history', '1.' . $index . '.0', 'return "official newer package";');
    }
    $probe->history['fixture-history'][] = $oldSha;
    $before = $installations->tree(ROOT . '/plugins/fixture-history');
    verifyProof('Official old package beyond 20 changes and the first API page is verified', $probe->probe('fixture-history', 'old-second-page') === $before);
    verifyProof('Directory history is paginated', count(array_filter($probe->requests, fn($url) => str_contains($url, '/commits?'))) === 2);
    verifyProof('No archive is downloaded for a different installed version', $probe->downloads === [$oldSha]);
    verifyProof('Source verification does not mutate plugin files or manufacture an installation receipt', $installations->tree(ROOT . '/plugins/fixture-history') === $before && $installations->receipt('fixture-history') === null);
    verifyProof('Baseline archives and extracted repositories are removed immediately', glob(ROOT . '/work/old-second-page/*') === []);
    $settings->values['updater_last_installed_commit'] = $oldSha;
    $probe->requests = [];
    verifyProof('Known source commit is verified first without any history request', $probe->probe('fixture-history', 'old-known-commit') === $before
        && !array_filter($probe->requests, fn($url) => str_contains($url, '/commits?')));
    $settings->values['updater_last_installed_commit'] = str_repeat('f', 40);
    verifyProof('A stale source commit missing from this repository falls back to history', $probe->probe('fixture-history', 'unknown-known-commit') === $before);
    $settings->values = [];
    $manifestSha = str_repeat('b', 40); $sourceSha = str_repeat('c', 40); $newSha = str_repeat('d', 40);
    $atManifest = packageFixture('fixture-version', '1.0.0', 'return "official first code";');
    $atSource = packageFixture('fixture-version', '1.0.0', 'return "official same-version fix";');
    $probe->packages[$manifestSha]['fixture-version'] = $atManifest;
    $probe->packages[$sourceSha]['fixture-version'] = $atSource;
    $probe->packages[$newSha]['fixture-version'] = packageFixture('fixture-version', '1.1.0', 'return "official new version";');
    $probe->history['fixture-version'] = [$newSha, $sourceSha, $manifestSha];
    installFixture('fixture-version', $atSource);
    verifyProof('Official same-version source changes are found without a manifest change', count($probe->probe('fixture-version', 'same-version-fix')) === 2);
    putFixture('plugins/fixture-version/Plugin.php', '<?php return "manual local customization";');
    $error = failureProof(fn() => $probe->probe('fixture-version', 'manual-code-change'));
    verifyProof('Actual local source changes are rejected and the conflicting file is named', str_contains($error, 'Plugin.php') && str_contains($error, 'сохранены'));
    verifyProof('Failed proof leaves local changes intact', str_contains((string)file_get_contents(ROOT . '/plugins/fixture-version/Plugin.php'), 'manual local customization'));
    installFixture('fixture-version', $atSource);
    unlink(ROOT . '/plugins/fixture-version/Plugin.php');
    verifyProof('Missing required source files are rejected', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'missing-file')), 'Plugin.php'));
    installFixture('fixture-version', $atSource);
    putFixture('plugins/fixture-version/custom.php', '<?php return "untracked customization";');
    verifyProof('Unknown additional files are not silently dropped', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'extra-file')), 'custom.php'));
    unlink(ROOT . '/plugins/fixture-version/custom.php');
    $probe->sourceFiles[$sourceSha]['fixture-version'] = $atSource + ['docs/Пример.md' => "Official documentation\n", 'tests/source.php' => '<?php return "official test";'];
    installFixture('fixture-version', $probe->sourceFiles[$sourceSha]['fixture-version']);
    $full = $installations->tree(ROOT . '/plugins/fixture-version');
    verifyProof('Export-ignored files in a full checkout are verified against the same source commit', $probe->probe('fixture-version', 'full-checkout') === $full);
    $probe->truncatedTree = true;
    verifyProof('A truncated Git tree cannot authorize replacement', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'truncated-tree')), 'некорректны'));
    $probe->truncatedTree = false;
    $probe->symlinkTree = true;
    verifyProof('A source-tree symlink cannot authorize a regular extra file', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'symlink-tree')), 'некорректны'));
    $probe->symlinkTree = false;
    putFixture('plugins/fixture-version/docs/Пример.md', 'Manual documentation edit');
    verifyProof('Even edited development files remain protected from replacement', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'edited-documentation')), 'docs/Пример.md'));
    $probe->onGitTree = fn() => putFixture('plugins/fixture-version/docs/Пример.md', "Official documentation\n");
    verifyProof('Git proof and the initial SHA-256 snapshot must describe the same bytes during a concurrent edit', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'changing-extra-file')), 'docs/Пример.md'));
    putFixture('plugins/fixture-version/docs/Пример.md', "Official documentation\n");
    $probe->packages[$manifestSha]['fixture-version']['docs/old.php'] = '<?php return "only in a different commit";';
    putFixture('plugins/fixture-version/docs/old.php', '<?php return "only in a different commit";');
    verifyProof('Extra files from another commit cannot authorize a mixed source tree', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'mixed-commits')), 'docs/old.php'));
    unlink(ROOT . '/plugins/fixture-version/docs/old.php');
    $installations->record('fixture-version', ['kind' => 'regression-fixture', 'commit' => $sourceSha]);
    $probe->requests = [];
    verifyProof('A matching receipt bypasses all historical network lookups', $probe->probe('fixture-version', 'valid-receipt') === $full && $probe->requests === []);
    putFixture('plugins/fixture-version/Plugin.php', '<?php return "manual edit after receipt";');
    verifyProof('An installation receipt never authorizes subsequently edited code', str_contains(failureProof(fn() => $probe->probe('fixture-version', 'changed-after-receipt')), 'отличается от установленного пакета') && $probe->requests === []);
    putFixture('.git', 'gitdir: /nonexistent/fixture-worktree');
    verifyProof('A Git worktree pointer cannot enter the ZIP-installation fallback', str_contains(failureProof(fn() => $probe->probe('fixture-history', 'git-pointer')), 'отличается от установленного пакета') && $probe->requests === []);
    unlink(ROOT . '/.git');
    $probe->forcedResponse = ['status_code' => 403, 'body' => '{"message":"API rate limit exceeded"}'];
    verifyProof('Network or rate-limit failures never approve an unverified package', str_contains(failureProof(fn() => $probe->probe('fixture-history', 'api-rate-limit')), 'GitHub временно ограничил'));
    $probe->forcedResponse = ['status_code' => 200, 'body' => '{"unexpected":"history"}'];
    verifyProof('Malformed history fails closed', str_contains(failureProof(fn() => $probe->probe('fixture-history', 'invalid-history')), 'некорректны'));
    $probe->forcedResponse = null;
    $probe->history['fixture-history'] = array_fill(0, 50, str_repeat('e', 40));
    $probe->requests = [];
    verifyProof('An exhausted history has an explicit source-not-found message', str_contains(failureProof(fn() => $probe->probe('fixture-history', 'source-not-found')), 'Не найден исходный пакет'));
    verifyProof('Duplicate commits are not verified or downloaded again', count(array_filter($probe->requests, fn($url) => str_contains($url, '/contents/'))) === 1);
    $probe->history['fixture-history'] = array_fill(0, 250, str_repeat('e', 40));
    $probe->requests = [];
    failureProof(fn() => $probe->probe('fixture-history', 'bounded-history'));
    verifyProof('Historical lookups have a hard pagination bound', count(array_filter($probe->requests, fn($url) => str_contains($url, '/commits?'))) === 4);

    // A core-shipped catalog proves old ZIP packages without spending any REST
    // quota. Every comparison is by file bytes, never just plugin version.
    $catalogPackage = packageFixture('fixture-catalog', '1.0.0', 'return "catalog official package";');
    $catalogExtras = ['docs/readme.md' => "Official development documentation\n", 'tests/check.php' => '<?php return true;'];
    $catalogRecord = catalogRecord('1.0.0', str_repeat('1', 40), $catalogPackage, $catalogExtras);
    $catalogDocument = catalogDocument('fixture-catalog', [$catalogRecord]);
    $catalogHashes = array_map(fn($bytes) => hash('sha256', $bytes), $catalogPackage);
    $fullCatalogHashes = $catalogHashes + array_map(fn($bytes) => hash('sha256', $bytes), $catalogExtras);
    verifyProof('Bundled catalog accepts an exact official archive', (catalogMatch($catalogDocument, $catalogHashes)['commit'] ?? '') === str_repeat('1', 40));
    verifyProof('Bundled catalog accepts a full checkout with same-commit export-ignored files', catalogMatch($catalogDocument, $fullCatalogHashes) !== null);
    verifyProof('Optional development files may be present only in part', catalogMatch($catalogDocument, $catalogHashes + ['docs/readme.md' => hash('sha256', $catalogExtras['docs/readme.md'])]) !== null);
    verifyProof('Repository identity is case-insensitive but otherwise exact', catalogMatch($catalogDocument, $catalogHashes, ['repository' => 'FIXTURE/Repository']) !== null
        && catalogMatch($catalogDocument, $catalogHashes, ['repository' => 'different/repository']) === null);
    verifyProof('Catalog cannot approve another plugin or configured directory', catalogMatch($catalogDocument, $catalogHashes, ['path' => 'plugins/different']) === null
        && catalogMatch($catalogDocument, $catalogHashes, [], '1.0.0', 'another-plugin') === null);
    verifyProof('Catalog requires the exact installed version', catalogMatch($catalogDocument, $catalogHashes, [], '1.0.1') === null);
    $editedHashes = $catalogHashes; $editedHashes['Plugin.php'] = hash('sha256', 'manual customization');
    verifyProof('Catalog rejects edited required source files', catalogMatch($catalogDocument, $editedHashes) === null);
    $missingHashes = $catalogHashes; unset($missingHashes['Plugin.php']);
    verifyProof('Catalog rejects missing required source files', catalogMatch($catalogDocument, $missingHashes) === null);
    verifyProof('Catalog rejects unknown extra files', catalogMatch($catalogDocument, $catalogHashes + ['custom.php' => hash('sha256', 'custom')]) === null);
    $editedExtraHashes = $fullCatalogHashes; $editedExtraHashes['docs/readme.md'] = hash('sha256', 'edited development documentation');
    verifyProof('Catalog rejects edited export-ignored files', catalogMatch($catalogDocument, $editedExtraHashes) === null);
    $otherRecord = catalogRecord('1.0.0', str_repeat('2', 40), $catalogPackage, ['tests/other.php' => '<?php return "other commit";']);
    $mixedHashes = $fullCatalogHashes + ['tests/other.php' => hash('sha256', '<?php return "other commit";')];
    verifyProof('Catalog never combines allowed extras from different commits', catalogMatch(catalogDocument('fixture-catalog', [$catalogRecord, $otherRecord]), $mixedHashes) === null);
    $malformed = [
        'unsupported schema' => array_replace($catalogDocument, ['schema' => 2]),
        'repository type' => array_replace($catalogDocument, ['repository' => []]),
        'plugin list type' => array_replace($catalogDocument, ['plugins' => 'not a map']),
    ];
    foreach ([
        'invalid commit' => ['commit' => 'main'],
        'invalid required hash' => ['required_files' => ['plugin.json' => 'wrong', 'Plugin.php' => $catalogHashes['Plugin.php']]],
        'empty required files' => ['required_files' => []],
        'invalid extra hash' => ['allowed_extras' => ['docs/readme.md' => 'wrong']],
    ] as $name => $change) $malformed[$name] = catalogDocument('fixture-catalog', [array_replace($catalogRecord, $change)]);
    foreach (['../escape.php', '/absolute.php', 'docs/../escape.php', 'docs\\escape.php', '.git/config', "control\x00.php"] as $path) {
        $malformed['unsafe required path ' . json_encode($path)] = catalogDocument('fixture-catalog', [array_replace($catalogRecord,
            ['required_files' => $catalogHashes + [$path => hash('sha256', 'unsafe')]])]);
        $malformed['unsafe optional path ' . json_encode($path)] = catalogDocument('fixture-catalog', [array_replace($catalogRecord,
            ['allowed_extras' => [$path => hash('sha256', 'unsafe')]])]);
    }
    foreach ($malformed as $name => $document) verifyProof('Malformed catalog fails closed: ' . $name, catalogMatch($document, $catalogHashes) === null);
    verifyProof('Catalog rejects unsafe current paths and malformed fingerprints', catalogMatch($catalogDocument, $catalogHashes + ['../escape.php' => hash('sha256', 'unsafe')]) === null
        && catalogMatch($catalogDocument, array_replace($catalogHashes, ['Plugin.php' => 'invalid'])) === null);
    putFixture('catalog/invalid.json', '{not valid JSON');
    verifyProof('Unreadable, absent and invalid JSON catalogs cannot authorize an update', (new App\Services\PluginBaselineCatalog(ROOT . '/catalog/absent.json'))->match('fixture-catalog', '1.0.0', ['repository' => 'fixture/repository', 'path' => 'plugins/fixture-catalog'], $catalogHashes) === null
        && (new App\Services\PluginBaselineCatalog(ROOT . '/catalog/invalid.json'))->match('fixture-catalog', '1.0.0', ['repository' => 'fixture/repository', 'path' => 'plugins/fixture-catalog'], $catalogHashes) === null);
    putFixture('catalog/oversized.json', json_encode($catalogDocument) . str_repeat(' ', 8 * 1048576));
    verifyProof('Catalog input has a hard eight MiB bound', (new App\Services\PluginBaselineCatalog(ROOT . '/catalog/oversized.json'))->match('fixture-catalog', '1.0.0', ['repository' => 'fixture/repository', 'path' => 'plugins/fixture-catalog'], $catalogHashes) === null);
    putFixture('catalog/real.json', json_encode($catalogDocument));
    if (!symlink(ROOT . '/catalog/real.json', ROOT . '/catalog/link.json')) throw new RuntimeException('Unable to create catalog symlink fixture');
    verifyProof('Catalog symlinks cannot authorize an update', (new App\Services\PluginBaselineCatalog(ROOT . '/catalog/link.json'))->match('fixture-catalog', '1.0.0', ['repository' => 'fixture/repository', 'path' => 'plugins/fixture-catalog'], $catalogHashes) === null);

    installFixture('fixture-catalog', $catalogPackage + $catalogExtras);
    putFixture('config/plugin-baselines.json', json_encode($catalogDocument));
    $probe->forcedResponse = ['status_code' => 403, 'body' => '{"message":"API rate limit exceeded"}'];
    $probe->requests = []; $catalogBefore = $installations->tree(ROOT . '/plugins/fixture-catalog');
    verifyProof('A known legacy ZIP package is verified with zero HTTP requests despite REST rate limits', $probe->probe('fixture-catalog', 'catalog-rate-limit') === $catalogBefore && $probe->requests === []);
    verifyProof('Catalog adoption neither changes files nor manufactures a receipt', $installations->tree(ROOT . '/plugins/fixture-catalog') === $catalogBefore && $installations->receipt('fixture-catalog') === null);
    $installations->record('fixture-catalog', ['kind' => 'catalog-regression-fixture']);
    putFixture('plugins/fixture-catalog/Plugin.php', '<?php return "edited after a receipt";');
    $receiptEdited = $installations->tree(ROOT . '/plugins/fixture-catalog');
    $editedRecord = $catalogRecord; $editedRecord['required_files'] = array_intersect_key($receiptEdited, $catalogRecord['required_files']);
    putFixture('config/plugin-baselines.json', json_encode(catalogDocument('fixture-catalog', [$editedRecord])));
    verifyProof('Even a catalog match cannot bypass an existing receipt conflict', str_contains(failureProof(fn() => $probe->probe('fixture-catalog', 'catalog-receipt-conflict')), 'отличается от установленного пакета') && $probe->requests === []);
    unlink(STORAGE . '/component-installations/plugins/fixture-catalog.json');
    installFixture('fixture-catalog', $catalogPackage);
    putFixture('config/plugin-baselines.json', json_encode($catalogDocument));
    putFixture('.git', 'gitdir: /nonexistent/fixture-worktree');
    verifyProof('Catalog cannot bypass a Git worktree conflict', str_contains(failureProof(fn() => $probe->probe('fixture-catalog', 'catalog-git-conflict')), 'отличается от установленного пакета') && $probe->requests === []);
    unlink(ROOT . '/.git'); unlink(ROOT . '/config/plugin-baselines.json');
    $probe->forcedResponse = null;

    // Real historical layouts of both reported plugins. Optional in shallow
    // checkouts; the synthetic security tests above need no repository history.
    $historical = [];
    foreach (['subscriptions', 'reel-player'] as $slug) {
        $ref = 'da983a09c24e15ddecf0a72a0a31b8b7ebf40f8c';
        $listing = fixtureGit(['ls-tree', '-rz', '--full-tree', $ref, '--', 'plugins/' . $slug . '/']);
        if ($listing === null || $listing === '') continue;
        $files = [];
        foreach (array_filter(explode("\0", $listing), 'strlen') as $entry) {
            if (!preg_match('/^100(?:644|755) blob [a-f0-9]{40}\t(.+)$/sD', $entry, $match)) throw new RuntimeException('Unexpected historical fixture type');
            $contents = fixtureGit(['show', $ref . ':' . $match[1]]);
            if ($contents === null) throw new RuntimeException('Unable to read historical fixture');
            $files[substr($match[1], strlen('plugins/' . $slug . '/'))] = $contents;
        }
        $metadata = json_decode($files['plugin.json'], true);
        $probe->sourceFiles[$ref][$slug] = $files;
        $probe->packages[$ref][$slug] = array_filter($files, fn($path) => !preg_match('~^(tests|docs)/~', $path), ARRAY_FILTER_USE_KEY);
        $probe->history[$slug] = [$ref];
        installFixture($slug, $probe->packages[$ref][$slug]);
        verifyProof($slug . ' ' . $metadata['version'] . ': official source archive passes', $probe->probe($slug, $slug . '-archive') === $installations->tree(ROOT . '/plugins/' . $slug));
        installFixture($slug, $files);
        verifyProof($slug . ' ' . $metadata['version'] . ': full source checkout also passes', $probe->probe($slug, $slug . '-checkout') === $installations->tree(ROOT . '/plugins/' . $slug));
        $historical[$slug] = ['version' => $metadata['version'], 'commit' => $ref, 'full_files' => count($files), 'archive_files' => count($probe->packages[$ref][$slug])];
    }
    // Verify the three production versions reported on Maxipapa against actual
    // repository blobs AND git archive export rules, not a self-made fixture.
    $productCatalogPath = $project . '/config/plugin-baselines.json';
    $productDocument = json_decode((string)file_get_contents($productCatalogPath), true, 512, JSON_THROW_ON_ERROR);
    $productCatalog = new App\Services\PluginBaselineCatalog($productCatalogPath);
    foreach (['reel-player' => '1.7.6', 'subscriptions' => '1.5.4', 'toy-car-rental' => '1.1.11'] as $slug => $version) {
        $records = array_values(array_filter($productDocument['plugins'][$slug] ?? [], fn($record) => $record['version'] === $version));
        verifyProof($slug . ' ' . $version . ': the shipped catalog includes the reported production version', $records !== []);
        $record = $records[0]; $ref = $record['commit'];
        $listing = fixtureGit(['ls-tree', '-rz', '--full-tree', $ref, '--', 'plugins/' . $slug . '/']);
        if ($listing === null || $listing === '') continue; // Shallow source distributions can still run synthetic checks.
        $files = [];
        foreach (array_filter(explode("\0", $listing), 'strlen') as $entry) {
            if (!preg_match('/^100(?:644|755) blob [a-f0-9]{40}\t(.+)$/sD', $entry, $match)) throw new RuntimeException('Unexpected catalog fixture type');
            $contents = fixtureGit(['show', $ref . ':' . $match[1]]);
            if ($contents === null) throw new RuntimeException('Unable to read catalog fixture blob');
            $files[substr($match[1], strlen('plugins/' . $slug . '/'))] = $contents;
        }
        $archiveBytes = fixtureGit(['archive', '--format=zip', $ref, 'plugins/' . $slug]);
        if ($archiveBytes === null) throw new RuntimeException('Unable to read catalog fixture archive');
        $archivePath = ROOT . '/catalog/product-' . $slug . '.zip'; file_put_contents($archivePath, $archiveBytes);
        $zip = new ZipArchive(); if ($zip->open($archivePath) !== true) throw new RuntimeException('Unable to open catalog fixture archive');
        $archivedFiles = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!str_starts_with($name, 'plugins/' . $slug . '/') || str_ends_with($name, '/')) continue;
            $bytes = $zip->getFromIndex($index);
            if ($bytes === false) throw new RuntimeException('Unable to read catalog fixture archive entry');
            $archivedFiles[substr($name, strlen('plugins/' . $slug . '/'))] = $bytes;
        }
        $zip->close(); unlink($archivePath);
        $config = ['repository' => $productDocument['repository'], 'path' => 'plugins/' . $slug];
        $archiveHashes = array_map(fn($bytes) => hash('sha256', $bytes), $archivedFiles); ksort($archiveHashes, SORT_STRING);
        $fullHashes = array_map(fn($bytes) => hash('sha256', $bytes), $files); ksort($fullHashes, SORT_STRING);
        verifyProof($slug . ' ' . $version . ': catalog mandatory hashes equal the real historical git archive', $archiveHashes === $record['required_files']);
        verifyProof($slug . ' ' . $version . ': catalog approves the real historical source archive without a token', $productCatalog->match($slug, $version, $config, $archiveHashes) !== null);
        verifyProof($slug . ' ' . $version . ': catalog approves the same-commit full checkout', $productCatalog->match($slug, $version, $config, $fullHashes) !== null);
        $fullHashes['Plugin.php'] = hash('sha256', 'simulated production customization');
        verifyProof($slug . ' ' . $version . ': catalog preserves actual local customizations', $productCatalog->match($slug, $version, $config, $fullHashes) === null);
        $historical[$slug . '-catalog'] = ['version' => $version, 'commit' => $ref, 'full_files' => count($files), 'archive_files' => count($archivedFiles)];
    }
    foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
        $messages = require $project . '/app/languages/' . $language . '.php';
        verifyProof($language . ': baseline diagnostics have translated parameters', str_contains(sprintf($messages['admin_plugin_updates_baseline_files_differ'], 'fixture', 'Plugin.php'), 'Plugin.php')
            && str_contains(sprintf($messages['admin_plugin_updates_baseline_not_found'], 'fixture'), 'fixture'));
    }
    $artifacts = __DIR__ . '/artifacts/plugin-source-verification';
    if (!is_dir($artifacts)) mkdir($artifacts, 0700, true);
    file_put_contents($artifacts . '/checks.json', json_encode(['passed' => count($checks),
        'scope' => 'Offline regression tests with simulated GitHub responses; no production changes.', 'historical_packages' => $historical, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo 'PASS ' . count($checks) . " plugin source verification regression checks\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) { if ($item->isDir() && !$item->isLink()) rmdir($item->getPathname()); else unlink($item->getPathname()); }
    rmdir(ROOT);
    $previousCeiling === false ? putenv('GIT_CEILING_DIRECTORIES') : putenv('GIT_CEILING_DIRECTORIES=' . $previousCeiling);
}
