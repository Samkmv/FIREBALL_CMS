<?php

namespace App\Services\Maintenance;

use PDO;

final class DatabaseBackupService
{
    private ?string $lastFailureKey = null;

    public function __construct(private readonly ?PDO $connection = null) {}

    public function lastFailure(): string
    {
        $key = $this->lastFailureKey ?? 'admin_update_backup_failed';
        return function_exists('return_translation') ? return_translation($key) : $key;
    }

    public function createBackup(): string
    {
        $this->lastFailureKey = 'admin_update_backup_storage_failed';
        $directory = ROOT . '/storage/backups';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return '';
        $path = $directory . '/db-backup-' . date('Y-m-d-H-i-s') . '-' . bin2hex(random_bytes(6)) . '.sql';
        $temporary = $path . '.tmp';
        $handle = @fopen($temporary, 'x+b');
        if (!is_resource($handle)) return '';
        $pdo = null;
        $buffered = null;
        try {
            $this->lastFailureKey = 'admin_update_backup_failed';
            $pdo = $this->connection ?? $this->pdo();
            if (!chmod($temporary, 0600)) $this->fail('admin_update_backup_storage_failed', 'Unable to protect database backup.');
            $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
            $this->assertMetadataVisibility($grants, $databaseName);
            foreach ([
                "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()",
                "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()",
                "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()",
            ] as $query) {
                if ((int)$pdo->query($query)->fetchColumn() !== 0) {
                    $this->fail('admin_update_backup_objects_unsupported', 'Automatic recovery does not support views, routines or events; update aborted.');
                }
            }
            $triggers = [];
            $triggerNames = $pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION, ACTION_ORDER")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($triggerNames as $name) {
                $trigger = $pdo->query('SHOW CREATE TRIGGER ' . $this->quoteIdentifier((string)$name))->fetch(PDO::FETCH_ASSOC);
                if (empty($trigger['SQL Original Statement'])) $this->fail('admin_update_backup_permissions_failed', 'Unable to read complete trigger definition.');
                foreach (['character_set_client', 'collation_connection'] as $setting) {
                    if (!preg_match('/^[a-z0-9_]+$/iD', (string)($trigger[$setting] ?? ''))) throw new \RuntimeException('Invalid trigger character set metadata.');
                }
                $triggers[] = $trigger;
            }
            $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
            if ($tables === []) throw new \RuntimeException('No database tables to back up.');
            foreach ($tables as $table) {
                if (strcasecmp((string)$table['ENGINE'], 'InnoDB') !== 0) {
                    $this->fail('admin_update_backup_engine_unsupported', 'A consistent backup requires InnoDB: ' . $table['TABLE_NAME']);
                }
            }
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $this->write($handle, "-- FIREBALL CMS consistent database backup\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");
            $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
            foreach ($tables as $table) {
                $name = $this->quoteIdentifier((string)$table['TABLE_NAME']);
                $create = $pdo->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_ASSOC);
                if (empty($create['Create Table'])) throw new \RuntimeException('Unable to read table schema.');
                $this->write($handle, 'DROP TABLE IF EXISTS ' . $name . ";\n" . $create['Create Table'] . ";\n");
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $statement = $pdo->query('SELECT * FROM ' . $name);
                try {
                    while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
                        $columns = array_map([$this, 'quoteIdentifier'], array_keys($record));
                        // UNHEX is a binary string expression: safe for BLOB, text and numeric coercion.
                        $values = array_map(static fn($value): string => $value === null ? 'NULL' : "UNHEX('" . bin2hex((string)$value) . "')", array_values($record));
                        $this->write($handle, 'INSERT INTO ' . $name . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
                    }
                } finally {
                    $statement->closeCursor();
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
                }
            }
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->commit();
            // Restore triggers only after loading all rows, so their actions do not
            // alter snapshot data. Keep the original DEFINER and creation settings.
            if ($triggers !== []) {
                $this->write($handle, "SET @fbl_backup_sql_mode=@@SESSION.sql_mode;\nSET @fbl_backup_character_set_client=@@SESSION.character_set_client;\nSET @fbl_backup_collation_connection=@@SESSION.collation_connection;\n");
                foreach ($triggers as $trigger) {
                    $sql = (string)$trigger['SQL Original Statement'];
                    do { $delimiter = '//fbl_' . bin2hex(random_bytes(12)) . '//'; } while (str_contains($sql, $delimiter));
                    $this->write($handle, "SET SESSION sql_mode=UNHEX('" . bin2hex((string)$trigger['sql_mode']) . "');\n"
                        . 'SET character_set_client=' . $trigger['character_set_client'] . ";\n"
                        . 'SET collation_connection=' . $trigger['collation_connection'] . ";\n"
                        . 'DELIMITER ' . $delimiter . "\n" . $sql . "\n" . $delimiter . "\nDELIMITER ;\n");
                }
                $this->write($handle, "SET SESSION sql_mode=@fbl_backup_sql_mode;\nSET character_set_client=@fbl_backup_character_set_client;\nSET collation_connection=@fbl_backup_collation_connection;\n");
            }
            if (!fflush($handle)) $this->fail('admin_update_backup_storage_failed', 'Unable to flush database backup.');
            fclose($handle);
            $handle = null;
            if (!rename($temporary, $path)) $this->fail('admin_update_backup_storage_failed', 'Unable to publish database backup.');
            $this->lastFailureKey = null;
            return $path;
        } catch (\Throwable $exception) {
            if ($exception instanceof \PDOException) {
                $driverCode = (int)($exception->errorInfo[1] ?? 0);
                if (in_array($driverCode, [1044, 1045, 1142, 1227], true)) $this->lastFailureKey = 'admin_update_backup_permissions_failed';
                elseif (in_array($driverCode, [2002, 2003, 2006, 2013], true)) $this->lastFailureKey = 'admin_update_backup_connection_failed';
            }
            if ($pdo !== null && $pdo->inTransaction()) $pdo->rollBack();
            if ($pdo !== null && $buffered !== null) $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            if (function_exists('log_error_details')) log_error_details('Database backup failed', [], $exception);
            if (is_resource($handle)) { fclose($handle); $handle = null; }
            @unlink($temporary);
            return '';
        } finally {
            if (is_resource($handle)) fclose($handle);
        }
    }

    private function assertMetadataVisibility(array $grants, string $databaseName): void
    {
        $privileges = [];
        $global = [];
        foreach ($grants as $grant) {
            if (preg_match('/^\s*REVOKE\s/i', $grant)) $this->fail('admin_update_backup_permissions_failed', 'Partial grants require an administrator-managed backup.');
            if (!preg_match('/^\s*GRANT\s+(.+?)\s+ON\s+(\*|`(?:``|[^`])+`)\.\*\s+TO\s/is', $grant, $match)) continue;
            $isGlobal = $match[2] === '*';
            $scope = $isGlobal ? '*' : str_replace(['``', '\\_', '\\%'], ['`', '_', '%'], substr($match[2], 1, -1));
            if (!$isGlobal && $scope !== $databaseName) continue;
            foreach (explode(',', strtoupper($match[1])) as $privilege) {
                $privilege = preg_replace('/\s+/', ' ', trim($privilege));
                $privileges[$privilege] = true;
                if ($isGlobal) $global[$privilege] = true;
            }
        }
        // MySQL 8 enumerates global grants instead of printing ALL PRIVILEGES.
        // Schema-wide grants prove visibility; table-only grants and unexpanded
        // roles may hide objects, so they do not qualify as a complete snapshot.
        if (isset($privileges['ALL PRIVILEGES'])) return;
        $routineVisibility = isset($global['SELECT']) || isset($global['SHOW_ROUTINE'])
            || isset($privileges['CREATE ROUTINE']) || isset($privileges['ALTER ROUTINE']) || isset($privileges['EXECUTE']);
        if (isset($privileges['SELECT'], $privileges['SHOW VIEW'], $privileges['TRIGGER'], $privileges['EVENT']) && $routineVisibility) return;
        $this->fail('admin_update_backup_permissions_failed', 'Automatic recovery requires verifiable metadata visibility; use a database-owner backup account or manual deployment.');
    }

    private function fail(string $key, string $detail): never
    {
        $this->lastFailureKey = $key;
        throw new \RuntimeException($detail);
    }

    private function write($handle, string $data): void
    {
        $length = strlen($data);
        for ($offset = 0; $offset < $length; $offset += $written) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) $this->fail('admin_update_backup_storage_failed', 'Unable to write database backup.');
        }
    }

    private function pdo(): PDO
    {
        $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';dbname=' . DB_SETTINGS['database'] . ';charset=' . DB_SETTINGS['charset'];
        if (!empty(DB_SETTINGS['port'])) $dsn .= ';port=' . (int)DB_SETTINGS['port'];
        return new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
