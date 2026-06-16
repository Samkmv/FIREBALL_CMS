<?php

namespace App\Services\Maintenance;

use FBL\Pagination;

final class MaintenanceLogService
{
    private const LOG_TABLE = 'database_maintenance_logs';

    public function getPaginatedLogs(int $perPage = 20): array
    {
        $this->ensureLogTable();
        $perPage = max(1, min(100, $perPage));
        $total = (int)db()->query(
            'SELECT COUNT(*) FROM ' . self::LOG_TABLE
        )->getColumn();
        $pagination = new Pagination(
            $total,
            $perPage,
            PAGINATION_SETTINGS['midSize'],
            PAGINATION_SETTINGS['maxPages'],
            PAGINATION_SETTINGS['tpl'],
            'logs_page'
        );
        $offset = $pagination->getOffset();

        return [
            'items' => db()->query(
                'SELECT * FROM ' . self::LOG_TABLE . " ORDER BY id DESC LIMIT {$offset}, {$perPage}"
            )->get() ?: [],
            'total' => $total,
            'pagination' => $pagination,
        ];
    }

    public function clearMaintenanceLogs(): int
    {
        $this->ensureLogTable();
        db()->query('DELETE FROM ' . self::LOG_TABLE);

        return db()->rowCount();
    }

    public function ensureLogTable(): void
    {
        db()->query(
            'CREATE TABLE IF NOT EXISTS ' . self::LOG_TABLE . ' (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NULL,
                user_name VARCHAR(255) NULL,
                ip_address VARCHAR(64) NULL,
                action VARCHAR(100) NOT NULL,
                result VARCHAR(20) NOT NULL,
                error TEXT NULL,
                backup_path VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY action (action),
                KEY result (result),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function writeLog(array $user, string $ip, string $action, string $result, ?string $error, ?string $backupPath): void
    {
        $this->ensureLogTable();
        db()->query(
            'INSERT INTO ' . self::LOG_TABLE . ' (user_id, user_name, ip_address, action, result, error, backup_path, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int)($user['id'] ?? 0) ?: null,
                (string)($user['name'] ?? ''),
                $ip,
                $action,
                $result,
                $error,
                $backupPath,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
