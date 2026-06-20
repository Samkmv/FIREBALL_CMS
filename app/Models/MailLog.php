<?php

namespace App\Models;

use FBL\Pagination;

class MailLog
{
    protected string $table = 'mail_logs';
    protected static bool $schemaReady = false;

    public function ensureTableExists(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status ENUM('success', 'failed') NOT NULL,
                error_message TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status (status),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    public function record(string $recipient, string $subject, string $status, string $errorMessage = ''): void
    {
        $this->ensureTableExists();

        db()->query(
            "INSERT INTO {$this->table} (recipient, subject, status, error_message, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [
                mb_substr(trim($recipient), 0, 255),
                mb_substr(trim($subject), 0, 255),
                $status === 'success' ? 'success' : 'failed',
                trim($errorMessage) !== '' ? trim($errorMessage) : null,
                date('Y-m-d H:i:s'),
            ]
        );
    }

    public function getPaginated(array $options = []): array
    {
        $this->ensureTableExists();

        $perPage = max(1, (int)($options['per_page'] ?? 20));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'created_at');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sortMap = [
            'recipient' => 'recipient',
            'subject' => 'subject',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'created_at';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = 'WHERE recipient LIKE ? OR subject LIKE ? OR error_message LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        $total = (int)db()->query("SELECT COUNT(*) FROM {$this->table} {$where}", $params)->getColumn();
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();
        $items = db()->query(
            "SELECT id, recipient, subject, status, error_message, created_at
             FROM {$this->table}
             {$where}
             ORDER BY {$orderBy} {$direction}, id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
        ];
    }
}
