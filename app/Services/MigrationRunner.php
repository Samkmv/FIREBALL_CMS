<?php

namespace App\Services;

use FBL\Database;

/** Shared versioned migration journal for installer, updater and CLI. */
final class MigrationRunner
{
    public function run(?Database $database = null): array
    {
        $previous = app()->db;
        app()->db = $database ?? $previous ?? new Database();
        $lock = 'fblcms:' . substr(hash('sha256', (string)db()->query('SELECT DATABASE()')->getColumn()), 0, 40);
        $locked = false;
        try {
            $locked = (int)db()->query('SELECT GET_LOCK(?, 30)', [$lock])->getColumn() === 1;
            if (!$locked) {
                throw new \RuntimeException('Could not acquire CMS migration lock.');
            }
            return SchemaMigration::run(function (): array {
                db()->query('CREATE TABLE IF NOT EXISTS update_migrations (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    executed_at DATETIME NOT NULL,
                    UNIQUE KEY migration (migration)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                $files = [];
                // Canonical database/ entries take precedence over legacy duplicates.
                foreach ([CONFIG . '/migrations', ROOT . '/database/migrations'] as $directory) {
                    foreach (glob($directory . '/*.{sql,php}', GLOB_BRACE) ?: [] as $file) {
                        $files[basename($file)] = $file;
                    }
                }
                ksort($files);
                $applied = array_flip(array_column(db()->query('SELECT migration FROM update_migrations')->get() ?: [], 'migration'));
                $executed = [];
                foreach ($files as $name => $file) {
                    if (isset($applied[$name])) {
                        continue;
                    }
                    if (str_ends_with($file, '.php')) {
                        $migration = require $file;
                        $migration();
                    } else {
                        (new SqlFileRunner())->executeDatabase((string)file_get_contents($file));
                    }
                    // MySQL DDL auto-commits: only journal fully successful migrations.
                    db()->query('INSERT INTO update_migrations (migration, executed_at) VALUES (?, ?)', [$name, date('Y-m-d H:i:s')]);
                    $executed[] = $name;
                }
                SchemaManifest::rebuild();
                \App\Models\SiteSetting::clearPublicCache();
                cache()->clear();
                return $executed;
            });
        } finally {
            if ($locked) {
                db()->query('SELECT RELEASE_LOCK(?)', [$lock]);
            }
            app()->db = $previous;
        }
    }
}
