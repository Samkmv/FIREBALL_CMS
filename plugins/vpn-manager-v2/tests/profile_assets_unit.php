<?php

declare(strict_types=1);

// Exercise the real public asset handler without booting the CMS or a database.
if (($argv[1] ?? '') === '--asset') {
    function get_route_param(string $name): string { global $argv; return (string)($argv[2] ?? ''); }
    function abort(): never { exit(3); }
    $router = new class {
        public ?Closure $assetHandler = null;
        public function get(string $path, mixed $handler): self
        {
            if (str_starts_with($path, '/plugins/vpn-manager-v2/assets/')) {
                $this->assetHandler = $handler;
            }
            return $this;
        }
        public function __call(string $name, array $arguments): self { return $this; }
    };
    require dirname(__DIR__) . '/routes.php';
    ($router->assetHandler)();
}

$cases = ['profile-vpn.css', 'vpn-manager-v2.js', 'Plugin.php', '../Plugin.php', 'unknown.css'];
foreach ($cases as $file) {
    $process = proc_open([PHP_BINARY, __FILE__, '--asset', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start asset test.');
    $body = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if (in_array($file, ['profile-vpn.css', 'vpn-manager-v2.js'], true)) {
        if ($status !== 0 || $errors !== '' || $body !== file_get_contents(dirname(__DIR__) . '/assets/' . $file)) {
            throw new RuntimeException('Public asset was not served correctly: ' . $file . ' ' . $errors);
        }
    } elseif ($status !== 3 || $body !== '') {
        throw new RuntimeException('Asset allowlist accepted an unapproved file: ' . $file);
    }
}

echo json_encode(['status' => 'ok', 'cases' => count($cases)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
