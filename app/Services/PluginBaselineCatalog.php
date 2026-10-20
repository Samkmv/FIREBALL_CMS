<?php
declare(strict_types=1);

namespace App\Services;

/** Shipped historical package proofs, independent of GitHub API and server Git. */
final class PluginBaselineCatalog
{
    private const MAX_BYTES = 8388608;
    private const MAX_RECORDS = 2000;
    private const MAX_FILES = 20000;

    public function __construct(private string $path) {}

    /**
     * The complete active package must match ONE official commit. Files absent
     * from that commit are accepted only as byte-proven leftovers of an older
     * official version of the same plugin, never as arbitrary local additions.
     */
    public function match(string $slug, string $version, array $config, array $current): ?array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $slug) || $version === '' || strlen($version) > 128
            || ($config['path'] ?? '') !== 'plugins/' . $slug || !$this->validFiles($current, false)) {
            return null;
        }
        if (is_link($this->path) || is_link(dirname($this->path)) || !is_file($this->path)) return null;
        $size = @filesize($this->path);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) return null;
        $json = @file_get_contents($this->path, false, null, 0, self::MAX_BYTES + 1);
        if ($json === false || strlen($json) > self::MAX_BYTES) return null;
        $catalog = json_decode($json, true, 64);
        if (!is_array($catalog) || ($catalog['schema'] ?? null) !== 1
            || !is_string($catalog['repository'] ?? null) || !is_string($config['repository'] ?? null)
            || strcasecmp($catalog['repository'], $config['repository']) !== 0
            || !is_array($catalog['plugins'] ?? null)) {
            return null;
        }
        $records = $catalog['plugins'][$slug] ?? null;
        if (!is_array($records) || !array_is_list($records) || count($records) > self::MAX_RECORDS) return null;

        foreach ($records as $record) {
            if (!is_array($record)) return null;
            if (($record['version'] ?? null) !== $version) continue;
            $required = $record['required_files'] ?? null;
            $extras = $record['allowed_extras'] ?? null;
            if (!$this->validRecord($record)) return null;
            if (array_diff_assoc($required, $current) !== []) continue;
            // A changed optional file is a customization, not a legacy leftover.
            if (array_diff_assoc(array_intersect_key($current, $extras), $extras) !== []) continue;
            $unconfirmed = array_diff_key($current, $required, $extras);
            if ($unconfirmed === []) return $record;
            $legacy = $this->legacyExtras($records, $version, $unconfirmed);
            if ($legacy !== null) return $record + ['legacy_extras' => $legacy];
        }
        return null;
    }

    /**
     * ZIP overlay updates historically left deleted upstream files on disk.
     * Recognize their exact path AND bytes, including removed scripts/backups;
     * extensions alone never authorize discarding a local file. The caller must
     * back up the FULL installed tree before replacing it with the new package.
     */
    private function legacyExtras(array $records, string $version, array $unconfirmed): ?array
    {
        if (!$this->validVersion($version)) return null;
        $comparableVersion = explode('+', $version, 2)[0];
        $legacy = [];
        foreach ($records as $record) {
            if (!is_array($record) || !$this->validVersion($record['version'] ?? null)) return null;
            // Same-version commits cannot contribute a mixture of optional files.
            if (!version_compare(explode('+', $record['version'], 2)[0], $comparableVersion, '<')) continue;
            if (!$this->validRecord($record)) return null;
            $historical = $record['required_files'] + $record['allowed_extras'];
            foreach ($unconfirmed as $path => $hash) {
                if (($historical[$path] ?? null) === $hash) $legacy[$path] = $hash;
            }
        }
        if (count($legacy) !== count($unconfirmed)) return null;
        ksort($legacy, SORT_STRING);
        return $legacy;
    }

    private function validVersion(mixed $version): bool
    {
        return is_string($version) && strlen($version) <= 128
            && preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?(?:\+[a-zA-Z0-9.-]+)?$/D', $version) === 1;
    }

    private function validRecord(array $record): bool
    {
        $required = $record['required_files'] ?? null;
        $extras = $record['allowed_extras'] ?? null;
        return is_string($record['commit'] ?? null) && preg_match('/^[a-f0-9]{40,64}$/D', $record['commit']) === 1
            && $this->validFiles($required, false) && $this->validFiles($extras, true)
            && isset($required['plugin.json']) && count($required) + count($extras) <= self::MAX_FILES
            && array_intersect_key($required, $extras) === [];
    }

    private function validFiles(mixed $files, bool $allowEmpty): bool
    {
        if (!is_array($files) || (!$allowEmpty && $files === []) || count($files) > self::MAX_FILES) return false;
        foreach ($files as $path => $hash) {
            if (!is_string($path) || $path === '' || str_starts_with($path, '/') || strlen($path) > 4096
                || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)
                || array_intersect(explode('/', $path), ['', '.', '..', '.git'])
                || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                return false;
            }
        }
        return true;
    }
}
