<?php

namespace App\Services;

use PDO;
use RuntimeException;

/** Offline restoration is deliberately unavailable once normal traffic resumes. */
final class UpdateRecoveryService
{
    public function __construct(private readonly string $root) {}

    public function restore(PDO $pdo): void
    {
        $storage = $this->root . '/storage';
        if (!is_file($storage . '/update.maintenance')) throw new RuntimeException('Recovery requires pending update maintenance.');
        $lock = fopen($storage . '/update.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('Another process is using the installation.');
        }
        $stage = $storage . '/backups/recovery-' . bin2hex(random_bytes(8));
        try {
            $descriptor = json_decode((string)file_get_contents($storage . '/update-recovery.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach (['files', 'database'] as $key) {
                $path = realpath((string)($descriptor[$key] ?? ''));
                $backups = realpath($storage . '/backups');
                if ($path === false || $backups === false || !str_starts_with($path, $backups . '/') || !is_file($path)
                    || !hash_equals((string)($descriptor[$key . '_sha256'] ?? ''), (string)hash_file('sha256', $path))) {
                    throw new RuntimeException('Recovery snapshot is missing or its checksum differs.');
                }
                $descriptor[$key] = $path;
            }
            if (!mkdir($stage, 0700)) throw new RuntimeException('Unable to stage recovery.');
            $zip = new \ZipArchive();
            if ($zip->open($descriptor['files']) !== true) throw new RuntimeException('Unable to open recovery snapshot.');
            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string)$zip->getNameIndex($i);
                    $attributes = 0;
                    $system = 0;
                    $zip->getExternalAttributesIndex($i, $system, $attributes);
                    if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\')
                        || array_intersect(explode('/', $name), ['..', '.']) || str_contains($name, "\0")
                        || (($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('Unsafe recovery archive.');
                }
                if (!$zip->extractTo($stage)) throw new RuntimeException('Unable to extract recovery snapshot.');
            } finally { $zip->close(); }
            $this->restoreDatabase($pdo, $descriptor['database']);
            $this->restoreFiles($stage);
            $commit = (string)($descriptor['git_commit'] ?? '');
            if ($commit !== '' && is_dir($this->root . '/.git')) {
                if (!preg_match('/^[a-f0-9]{40}$/D', $commit)) throw new RuntimeException('Invalid recovery commit.');
                $process = proc_open(['git', 'reset', '--mixed', $commit], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
                if (!is_resource($process)) throw new RuntimeException('Unable to restore Git metadata.');
                foreach ($pipes as $pipe) { stream_get_contents($pipe); fclose($pipe); }
                if (proc_close($process) !== 0) throw new RuntimeException('Unable to restore Git metadata.');
            }
            if (!unlink($storage . '/update.maintenance')) throw new RuntimeException('Unable to finish recovery.');
            if (function_exists('opcache_reset')) @opcache_reset();
        } finally {
            $this->removeStage($stage);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function restoreDatabase(PDO $pdo, string $path): void
    {
        $expected = [];
        $stream = fopen($path, 'rb');
        if ($stream === false) throw new RuntimeException('Unable to read database snapshot.');
        while (($line = fgets($stream)) !== false) {
            if (preg_match('/^DROP TABLE IF EXISTS `((?:``|[^`])+)`;$/D', rtrim($line, "\r\n"), $match)) $expected[] = str_replace('``', '`', $match[1]);
        }
        if ($expected === []) { fclose($stream); throw new RuntimeException('Empty database recovery snapshot.'); }
        rewind($stream);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Pre-update snapshots reject these objects, so any present now was
            // introduced by the failed migration and must be removed as well.
            foreach ([
                ['VIEW', "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()"],
                ['TRIGGER', "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()"],
                ['EVENT', "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()"],
            ] as [$type, $query]) {
                foreach ($pdo->query($query)->fetchAll(PDO::FETCH_COLUMN) as $name) $pdo->exec('DROP ' . $type . ' `' . str_replace('`', '``', $name) . '`');
            }
            foreach ($pdo->query("SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_ASSOC) as $routine) {
                $type = $routine['ROUTINE_TYPE'];
                if (!in_array($type, ['FUNCTION', 'PROCEDURE'], true)) throw new RuntimeException('Unsupported recovery routine.');
                $pdo->exec('DROP ' . $type . ' `' . str_replace('`', '``', $routine['ROUTINE_NAME']) . '`');
            }
            $current = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            foreach ($current as $table) {
                if (!in_array($table[0], $expected, true)) $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table[0]) . '`');
            }
            $buffer = '';
            $runner = new SqlFileRunner();
            while (($line = fgets($stream)) !== false) {
                $buffer .= $line;
                if (str_ends_with(rtrim($line), ';')) {
                    $runner->executePdo($pdo, $buffer);
                    $buffer = '';
                }
            }
            if (trim($buffer) !== '') $runner->executePdo($pdo, $buffer);
        } finally {
            fclose($stream);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function restoreFiles(string $stage): void
    {
        // Remove only runtime files introduced by the failed version. Local data is retained.
        foreach (['app', 'core', 'helpers', 'vendor', 'config', 'database', 'plugins', 'themes', 'public', 'bin'] as $directory) {
            $base = $this->root . '/' . $directory;
            if (is_link($base)) throw new RuntimeException('Symlink in runtime recovery root.');
            if (!is_dir($base)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $relative = substr($item->getPathname(), strlen($this->root) + 1);
                if ($this->localData($relative)) continue;
                if ($item->isLink()) throw new RuntimeException('Symlink in runtime recovery destination.');
                if ($item->isFile() && !is_file($stage . '/' . $relative) && !unlink($item->getPathname())) throw new RuntimeException('Unable to remove introduced runtime file.');
            }
        }
        foreach (['.htaccess', '.gitattributes', '.gitignore', 'composer.json', 'composer.lock', 'update.json', 'release-plugins.json'] as $relative) {
            $target = $this->root . '/' . $relative;
            if (is_link($target)) throw new RuntimeException('Symlink in recovery destination.');
            if (is_file($target) && !is_file($stage . '/' . $relative) && !unlink($target)) throw new RuntimeException('Unable to remove introduced root file.');
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink()) continue;
            $relative = substr($item->getPathname(), strlen($stage) + 1);
            if (in_array($relative, ['storage/update.lock', 'storage/update.maintenance', 'storage/update-recovery.json'], true)) continue;
            $target = $this->root . '/' . $relative;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) throw new RuntimeException('Unable to create recovery directory.');
            $parent = realpath(dirname($target));
            if ($parent === false || !str_starts_with($parent . '/', realpath($this->root) . '/') || is_link($target)) throw new RuntimeException('Unsafe recovery destination.');
            $temporary = tempnam($parent, '.fbl-recover-');
            if ($temporary === false || !copy($item->getPathname(), $temporary)
                || !chmod($temporary, $relative === 'config/config.local.php' ? 0600 : ($item->getPerms() & 0777))
                || !rename($temporary, $target)) throw new RuntimeException('Unable to restore file: ' . $relative);
        }
    }

    private function localData(string $path): bool
    {
        foreach (['public/uploads', 'themes/custom', 'config/config.local.php'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) return true;
        }
        return false;
    }

    private function removeStage(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) @rmdir($item->getPathname()); else @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
