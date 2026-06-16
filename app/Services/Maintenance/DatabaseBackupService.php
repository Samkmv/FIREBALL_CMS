<?php

namespace App\Services\Maintenance;

use PDO;

final class DatabaseBackupService
{
    public function createBackup(): string
    {
        $directory = ROOT . '/storage/backups';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return '';
        }

        $path = $directory . '/db-backup-' . date('Y-m-d-H-i') . '.sql';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory . '/db-backup-' . date('Y-m-d-H-i') . '-' . $suffix . '.sql';
            $suffix++;
        }

        $sql = $this->buildSqlDump();
        if ($sql === '' || file_put_contents($path, $sql, LOCK_EX) === false) {
            return '';
        }

        return $path;
    }

    private function buildSqlDump(): string
    {
        $pdo = $this->pdo();
        $database = (string)(DB_SETTINGS['database'] ?? '');
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = ' . $pdo->quote('BASE TABLE'))->fetchAll(PDO::FETCH_NUM);
        if (!is_array($tables)) {
            return '';
        }

        $lines = [
            '-- FIREBALL CMS database backup',
            '-- Created at: ' . date('Y-m-d H:i:s'),
            '-- Database: ' . $database,
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        foreach ($tables as $row) {
            $table = (string)($row[0] ?? '');
            if ($table === '') {
                continue;
            }

            $quotedTable = $this->quoteIdentifier($table);
            $create = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_ASSOC);
            $createSql = (string)($create['Create Table'] ?? '');

            $lines[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';
            $lines[] = $createSql . ';';

            $stmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map([$this, 'quoteIdentifier'], array_keys($record));
                $values = array_map(
                    static fn($value): string => $value === null ? 'NULL' : $pdo->quote((string)$value),
                    array_values($record)
                );

                $lines[] = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function pdo(): PDO
    {
        $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';dbname=' . DB_SETTINGS['database'] . ';charset=' . DB_SETTINGS['charset'];
        if (!empty(DB_SETTINGS['port'])) {
            $dsn .= ';port=' . (int)DB_SETTINGS['port'];
        }

        return new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
