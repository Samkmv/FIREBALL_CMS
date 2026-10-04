<?php

namespace App\Services\Maintenance;

use PDO;

final class DatabaseBackupService
{
    public function __construct(private readonly ?PDO $connection = null) {}

    public function createBackup(): string
    {
        $directory = ROOT . '/storage/backups';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return '';
        $path = $directory . '/db-backup-' . date('Y-m-d-H-i-s') . '-' . bin2hex(random_bytes(6)) . '.sql';
        $temporary = $path . '.tmp';
        $handle = @fopen($temporary, 'x+b');
        if (!is_resource($handle)) return '';
        $pdo = null;
        $buffered = null;
        try {
            $pdo = $this->connection ?? $this->pdo();
            if (!chmod($temporary, 0600)) throw new \RuntimeException('Unable to protect database backup.');
            $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
            $visible = false;
            foreach ($grants as $grant) {
                // Limited grants/roles may hide triggers or routines from metadata.
                // Never label an unverifiable table-only dump as complete recovery.
                if (preg_match('/^REVOKE /i', $grant)) throw new \RuntimeException('Partial grants require an administrator-managed backup.');
                if (preg_match('/^GRANT ALL PRIVILEGES ON (.+)\.\* TO /i', $grant, $match)) {
                    $scope = str_replace(['``', '\\_', '\\%'], ['`', '_', '%'], trim($match[1], '`'));
                    if ($scope === '*' || $scope === $databaseName) $visible = true;
                }
            }
            if (!$visible) throw new \RuntimeException('Automatic recovery requires verifiable metadata visibility; use a database-owner backup account or manual deployment.');
            foreach ([
                "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE()",
                "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()",
                "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()",
                "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()",
            ] as $query) {
                if ((int)$pdo->query($query)->fetchColumn() !== 0) {
                    throw new \RuntimeException('Automatic recovery does not support views, triggers, routines or events; update aborted.');
                }
            }
            $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
            if ($tables === []) throw new \RuntimeException('No database tables to back up.');
            foreach ($tables as $table) {
                if (strcasecmp((string)$table['ENGINE'], 'InnoDB') !== 0) {
                    throw new \RuntimeException('A consistent backup requires InnoDB: ' . $table['TABLE_NAME']);
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
            if (!fflush($handle)) throw new \RuntimeException('Unable to flush database backup.');
            fclose($handle);
            $handle = null;
            if (!rename($temporary, $path)) throw new \RuntimeException('Unable to publish database backup.');
            return $path;
        } catch (\Throwable $exception) {
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

    private function write($handle, string $data): void
    {
        $length = strlen($data);
        for ($offset = 0; $offset < $length; $offset += $written) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) throw new \RuntimeException('Unable to write database backup.');
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
