<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$options = array_slice($argv, 1);
foreach ($options as $option) if (!in_array($option, ['--browser', '--mysql', '--composer'], true)) {
    fwrite(STDERR, "Usage: php bin/check-release.php [--browser] [--mysql] [--composer]\n"); exit(2);
}
$suite = json_decode(file_get_contents($root . '/tests/release-suite.json'), true, 512, JSON_THROW_ON_ERROR);
$node = getenv('FIREBALL_NODE') ?: 'node';
$failed = 0; $passed = 0;
function releaseCommand(array $command, string $label, int $timeout = 180): bool {
    global $root;
    $output = tempnam(sys_get_temp_dir(), 'fbl-check-');
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $output, 'a']], $pipes, $root);
    try {
        if (!is_resource($process)) throw new RuntimeException('Cannot start check: ' . $label);
        $deadline = microtime(true) + $timeout;
        do {
            $state = proc_get_status($process);
            if (!$state['running']) break;
            if (microtime(true) >= $deadline) { proc_terminate($process); break; }
            usleep(20000);
        } while (true);
        $exit = proc_close($process);
        if (!$state['running'] && $state['exitcode'] >= 0) $exit = $state['exitcode'];
        $ok = !$state['running'] && $exit === 0;
        if (!$ok) fwrite(STDERR, "FAIL $label\n" . substr((string)file_get_contents($output), -12000) . "\n");
        return $ok;
    } finally { unlink($output); }
}
$ownFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$syntaxCount = 0;
foreach ($ownFiles as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($file->isLink() || !$file->isFile() || preg_match('~^(?:vendor|dist|tmp|storage|\.git|public/uploads|node_modules)/~', $relative)
        || str_contains($relative, '.bak') || $relative === 'config/config.local.php') continue;
    $extension = strtolower($file->getExtension());
    if ($extension !== 'php' && !in_array($extension, ['js', 'cjs', 'mjs'], true)) continue;
    $command = $extension === 'php' ? [PHP_BINARY, '-l', $relative] : [$node, '--check', $relative];
    if (releaseCommand($command, $relative)) $syntaxCount++; else $failed++;
}
echo "Syntax: $syntaxCount files passed.\n";
$groups = ['php', 'javascript'];
if (in_array('--browser', $options, true)) $groups[] = 'browser';
if (in_array('--mysql', $options, true)) $groups[] = 'mysql';
foreach ($groups as $group) {
    $count = 0;
    foreach ($suite[$group] as $file) {
        $command = in_array($group, ['php', 'mysql'], true) ? [PHP_BINARY, $file] : [$node, $file];
        if (releaseCommand($command, $file)) { $passed++; $count++; } else $failed++;
    }
    echo "$group: $count/" . count($suite[$group]) . " passed.\n";
}
if (in_array('--composer', $options, true)) {
    $composer = getenv('FIREBALL_COMPOSER') ?: '';
    if ($composer === '') foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
        if (is_file($directory . '/composer')) { $composer = $directory . '/composer'; break; }
    }
    if ($composer === '') { fwrite(STDERR, "Composer executable not found.\n"); exit(1); }
    foreach ([['validate', '--strict', '--no-check-publish'], ['check-platform-reqs'], ['audit', '--locked', '--no-interaction']] as $args) {
        if (!releaseCommand([PHP_BINARY, $composer, ...$args], 'Composer ' . $args[0])) $failed++;
    }
}
echo "Release checks: $passed test suites passed, $failed failures.\n";
exit($failed === 0 ? 0 : 1);
