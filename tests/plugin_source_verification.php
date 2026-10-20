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
    verifyProof('Known source commit is verified first without any history request', $probe->probe('fixture-history', 'old-known-commit') === $before && count($probe->requests) === 1);
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
    verifyProof('Network or rate-limit failures never approve an unverified package', str_contains(failureProof(fn() => $probe->probe('fixture-history', 'api-rate-limit')), 'недоступен'));
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
