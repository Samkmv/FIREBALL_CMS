<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

use App\Services\Maintenance\DatabaseBackupService;
use App\Services\SchemaMigration;
use App\Services\SqlFileRunner;

/** Explicit maintenance only; the existing CMS migration journal is retained. */
final class SchemaUpgrade
{
    private string $migrations;
    private string $storage;
    private string $backups;
    private ?\Closure $databaseBackup;

    /** Optional dependencies support isolated upgrade/restore tests. */
    public function __construct(?string $migrations = null, ?string $storage = null, ?string $backups = null, ?callable $databaseBackup = null)
    {
        $this->migrations = $migrations ?? dirname(__DIR__, 2) . '/migrations';
        $this->storage = $storage ?? STORAGE . '/reel-player';
        $this->backups = $backups ?? STORAGE . '/backups/reel-player';
        $this->databaseBackup = $databaseBackup ? \Closure::fromCallable($databaseBackup) : null;
    }

    public function run(): void
    {
        if (!SchemaMigration::isRunning()) throw new \RuntimeException('Обновление схемы Tape Room доступно только через обслуживание CMS.');
        $lock = 'fblpm:' . substr(hash('sha256', 'reel-player'), 0, 48);
        if ((int)db()->query('SELECT GET_LOCK(?,15)', [$lock])->getColumn() !== 1) throw new \RuntimeException('Обновление Tape Room уже выполняется. Повторите позже.');
        try {
            $files = glob($this->migrations . '/*.sql') ?: []; sort($files, SORT_STRING);
            $applied = array_column(db()->query('SELECT migration FROM plugin_migrations WHERE plugin_slug=?', ['reel-player'])->get() ?: [], 'migration');
            $pending = [];
            foreach ($files as $file) {
                $name = basename($file); $real = realpath($file); $directory = realpath($this->migrations);
                if (!$real || !$directory || is_link($file) || !str_starts_with($real, $directory . DIRECTORY_SEPARATOR) || !preg_match('/^\d{3}_[a-z0-9_]+\.sql$/D', $name)) throw new \RuntimeException('Проверьте файлы миграций Tape Room.');
                if (!in_array($name, $applied, true)) $pending[$name] = $real;
            }
            if (!$pending) return;
            $existing = (int)db()->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['reel_tracks'])->getColumn() > 0;
            if ($existing && isset($pending['008_catalog_distribution.sql'])) $this->backup();
            $runner = new SqlFileRunner();
            foreach ($pending as $name => $file) {
                $sql = file_get_contents($file);
                if ($sql === false) throw new \RuntimeException('Не удалось прочитать миграцию Tape Room.');
                if (trim($sql) !== '') $runner->executeDatabase($sql);
                db()->query('INSERT INTO plugin_migrations(plugin_slug,migration,executed_at) VALUES(?,?,?)', ['reel-player', $name, date('Y-m-d H:i:s')]);
            }
        } finally {
            try { db()->query('SELECT RELEASE_LOCK(?)', [$lock]); } catch (\Throwable) { /* Connection closure also releases the advisory lock. */ }
        }
    }

    /** Original music/OAuth files are never moved, rewritten or removed. */
    private function backup(): void
    {
        $directory = $this->secureDirectory($this->backups);
        $name = 'upgrade-' . date('Y-m-d-H-i-s') . '-' . bin2hex(random_bytes(8));
        $pending = $directory . '/.' . $name . '.pending'; $complete = $directory . '/' . $name;
        if (!@mkdir($pending, 0700) || !@chmod($pending, 0700)) throw new \RuntimeException('Не удалось создать защищённую резервную копию Tape Room. Обновление отменено.');
        try {
            $files = $this->storageSnapshot();
            $this->verifyReferences($files);
            $copied = $this->copyStorage($pending . '/files', $files);
            $database = $this->databaseBackup
                ? ($this->databaseBackup)($pending)
                : $this->createDatabaseBackup($pending);
            $realDatabase = is_string($database) ? realpath($database) : false;
            if (!$realDatabase || !str_starts_with($realDatabase, $pending . DIRECTORY_SEPARATOR) || is_link($database) || !is_file($database) || filesize($database) === 0 || !chmod($database, 0600)) throw new \RuntimeException('Резервная копия базы данных не создана. Обновление Tape Room отменено.');
            if ($this->storageSnapshot() !== $files) throw new \RuntimeException('Музыкальная библиотека изменилась во время резервного копирования. Обновление отменено; повторите после завершения загрузок.');
            $manifest = ['created_at' => gmdate('c'), 'target' => 'Tape Room 2.0', 'database' => basename($database), 'database_sha256' => hash_file('sha256', $database), 'files' => $copied];
            $path = $pending . '/manifest.json'; $handle = fopen($path, 'x+b');
            if (!$handle) throw new \RuntimeException('Не удалось сохранить описание резервной копии.');
            try {
                if (!chmod($path, 0600)) throw new \RuntimeException('Не удалось защитить описание резервной копии.');
                $bytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) throw new \RuntimeException('Не удалось записать описание резервной копии.');
                if (function_exists('fsync') && !fsync($handle)) throw new \RuntimeException('Не удалось завершить запись резервной копии.');
            } finally { fclose($handle); }
            if (!rename($pending, $complete)) throw new \RuntimeException('Не удалось завершить резервное копирование Tape Room. Обновление отменено.');
        } catch (\Throwable $error) {
            // Keep partial backups protected for diagnostics. Never remove an
            // existing backup, source file, owner directory, or credentials.
            throw new \RuntimeException('Не удалось подготовить резервную копию Tape Room. Новые миграции не запускались. ' . $error->getMessage(), 0, $error);
        }
    }

    private function secureDirectory(string $path): string
    {
        for ($part = $path; $part !== dirname($part); $part = dirname($part)) if (is_link($part)) throw new \RuntimeException('Каталог резервных копий не должен проходить через символическую ссылку.');
        if (!is_dir($path) && !@mkdir($path, 0700, true)) throw new \RuntimeException('Не удалось создать каталог резервных копий.');
        $real = realpath($path);
        $source = realpath($this->storage);
        if (!$real || ($source && ($real === $source || str_starts_with($real, $source . DIRECTORY_SEPARATOR))) || !@chmod($real, 0700)) throw new \RuntimeException('Не удалось защитить каталог резервных копий.');
        return $real;
    }

    private function verifyReferences(array $files): void
    {
        $references = db()->query('SELECT file_path,cover_path FROM reel_tracks')->get() ?: [];
        foreach (['reel_albums' => 'cover_path', 'reel_artist_profiles' => 'image_path'] as $table => $column) {
            try { $references = [...$references, ...(db()->query('SELECT ' . $column . ' FROM ' . $table)->get() ?: [])]; }
            catch (\PDOException $error) { if ((string)$error->getCode() !== '42S02') throw $error; }
        }
        foreach ($references as $row) foreach ($row as $value) {
            if ($value !== null && $value !== '' && !isset($files[$value])) throw new \RuntimeException('Исходный файл или обложка из библиотеки отсутствует. Сначала восстановите его; обновление отменено.');
        }
    }

    private function createDatabaseBackup(string $directory): string
    {
        $method = new \ReflectionMethod(DatabaseBackupService::class, '__construct');
        if ($method->getNumberOfParameters() >= 2) return (new DatabaseBackupService(null, $directory))->createBackup();
        // Existing CMS releases retain their native backup implementation. Its
        // protected SQL can be copied into the group without deleting it.
        $path = (new DatabaseBackupService())->createBackup();
        if ($path === '' || is_link($path) || !is_file($path)) return '';
        $destination = $directory . '/' . basename($path);
        $input = @fopen($path, 'rb'); $output = @fopen($destination, 'x+b');
        if (!$input || !$output) { if (is_resource($input)) fclose($input); if (is_resource($output)) fclose($output); return ''; }
        try {
            if (!@chmod($destination, 0600) || stream_copy_to_stream($input, $output) !== filesize($path) || !fflush($output) || (function_exists('fsync') && !fsync($output))) return '';
        } finally { fclose($input); fclose($output); }
        return hash_equals(hash_file('sha256', $path), hash_file('sha256', $destination)) ? $destination : '';
    }

    /** Paths and stat identities detect immutable-upload and OAuth rename races. */
    private function storageSnapshot(): array
    {
        if (!file_exists($this->storage)) return [];
        $root = realpath($this->storage);
        if (!$root || is_link($this->storage) || !is_dir($root)) throw new \RuntimeException('Музыкальная библиотека недоступна для резервного копирования.');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isLink()) throw new \RuntimeException('В музыкальной библиотеке найдена символическая ссылка. Обновление отменено.');
            if ($entry->isDir()) continue;
            if (!$entry->isFile() || !is_readable($path)) throw new \RuntimeException('Один из музыкальных файлов недоступен для резервного копирования.');
            clearstatcache(true, $path); $stat = stat($path);
            if (!$stat) throw new \RuntimeException('Не удалось проверить музыкальный файл.');
            $relative = substr($path, strlen($root) + 1);
            $files[$relative] = ['size' => $stat['size'], 'mtime' => $stat['mtime'], 'ctime' => $stat['ctime'], 'inode' => $stat['ino']];
        }
        ksort($files, SORT_STRING); return $files;
    }

    private function copyStorage(string $target, array $files): array
    {
        if (!@mkdir($target, 0700) || !@chmod($target, 0700)) throw new \RuntimeException('Не удалось создать резервную копию файлов.');
        $sourceRoot = realpath($this->storage);
        $result = [];
        foreach ($files as $relative => $identity) {
            $source = $sourceRoot . '/' . $relative; $destination = $target . '/' . $relative;
            $parent = dirname($destination);
            if (!is_dir($parent) && !@mkdir($parent, 0700, true)) throw new \RuntimeException('Не удалось создать папку резервной копии.');
            $input = @fopen($source, 'rb'); $output = @fopen($destination, 'x+b');
            if (!$input || !$output) { if (is_resource($input)) fclose($input); if (is_resource($output)) fclose($output); throw new \RuntimeException('Не удалось скопировать музыкальный файл.'); }
            $digest = hash_init('sha256'); $count = 0;
            try {
                if (!chmod($destination, 0600)) throw new \RuntimeException('Не удалось защитить музыкальную резервную копию.');
                while (!feof($input)) {
                    $chunk = fread($input, 1048576);
                    if ($chunk === false) throw new \RuntimeException('Не удалось прочитать музыкальный файл.');
                    if ($chunk === '') break;
                    hash_update($digest, $chunk); $length = strlen($chunk);
                    for ($offset = 0; $offset < $length; $offset += $written) {
                        $written = fwrite($output, substr($chunk, $offset));
                        if ($written === false || $written === 0) throw new \RuntimeException('Недостаточно места для музыкальной резервной копии.');
                    }
                    $count += $length;
                }
                if ($count !== $identity['size'] || !fflush($output) || (function_exists('fsync') && !fsync($output))) throw new \RuntimeException('Музыкальный файл изменился или не удалось записать резервную копию.');
            } finally { fclose($input); fclose($output); }
            $result[$relative] = ['size' => $count, 'sha256' => hash_final($digest)];
            if (!hash_equals($result[$relative]['sha256'], hash_file('sha256', $destination))) throw new \RuntimeException('Проверка резервной копии музыкального файла не пройдена.');
        }
        return $result;
    }
}
