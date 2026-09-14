<?php

namespace FBL;

/** Published during maintenance; public lookups never discover or hash assets. */
final class AssetManifest
{
    private static ?array $versions = null;

    public static function version(string $path): string
    {
        if (self::$versions === null) {
            $manifest = json_decode((string)@file_get_contents(STORAGE . '/asset-manifest.json'), true);
            self::$versions = [];
            if (is_array($manifest) && ($manifest['version'] ?? null) === 1 && is_array($manifest['assets'] ?? null)) {
                foreach ($manifest['assets'] as $name => $version) {
                    if (is_string($version) && preg_match('/^[a-f0-9]{20}$/D', $version)) self::$versions[$name] = $version;
                }
            }
        }
        return self::$versions[$path] ?? '';
    }

    public static function url(string $url, string $path): string
    {
        $version = self::version($path);
        if ($version === '') return $url;
        $parts = explode('#', $url, 2);
        return $parts[0] . (str_contains($parts[0], '?') ? '&' : '?') . 'v=' . $version
            . (isset($parts[1]) ? '#' . $parts[1] : '');
    }

    /** Only explicit maintenance callers use this compatibility entry point. */
    public static function invalidate(): void
    {
        self::rebuild();
    }

    /** Optional roots support custom deployments; omitted roots cover themes, plugins and public assets. */
    public static function rebuild(?array $roots = null): int
    {
        if (!is_dir(STORAGE) && !mkdir(STORAGE, 0755, true) && !is_dir(STORAGE)) {
            throw new \RuntimeException('Unable to create asset manifest directory.');
        }
        $lock = fopen(STORAGE . '/asset-manifest.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Unable to open asset manifest lock.');
        $temporary = false;
        try {
            if (!flock($lock, LOCK_EX)) throw new \RuntimeException('Unable to lock asset manifest.');
            $versions = [];
            foreach ($roots ?? [WWW . '/assets', WWW . '/uploads', ROOT . '/themes', ROOT . '/plugins'] as $root) {
                if (!file_exists($root)) continue;
                PerformanceProfiler::count('asset_manifest_scans');
                $files = is_file($root) ? [new \SplFileInfo($root)] : new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($files as $file) {
                    if (!$file->isFile() || $file->isLink()
                        || !preg_match('/\.(?:css|js|mjs|json|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico)$/i', $file->getFilename())) continue;
                    PerformanceProfiler::count('asset_hashes');
                    $hash = hash_file('sha256', $file->getPathname());
                    if ($hash === false) throw new \RuntimeException('Unable to hash asset: ' . $file->getPathname());
                    $versions[$file->getPathname()] = substr($hash, 0, 20);
                    $versions[$file->getRealPath()] = substr($hash, 0, 20);
                }
            }
            ksort($versions);
            $payload = json_encode(['version' => 1, 'assets' => $versions], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $temporary = tempnam(STORAGE, '.assets-');
            if ($temporary === false || file_put_contents($temporary, $payload) !== strlen($payload)
                || !rename($temporary, STORAGE . '/asset-manifest.json')) {
                throw new \RuntimeException('Unable to publish asset manifest.');
            }
            self::$versions = $versions;
            return count($versions);
        } finally {
            if ($temporary !== false && is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
