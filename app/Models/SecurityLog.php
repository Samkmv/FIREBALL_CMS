<?php

namespace App\Models;

use FBL\Pagination;

class SecurityLog
{
    protected string $table = 'security_logs';
    protected static bool $schemaReady = false;

    public function ensureTableExists(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id INT(10) UNSIGNED NULL,
                target_user_id INT(10) UNSIGNED NULL,
                event VARCHAR(80) NOT NULL,
                result VARCHAR(30) NOT NULL DEFAULT 'success',
                reason TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY actor_user_id (actor_user_id),
                KEY target_user_id (target_user_id),
                KEY event (event),
                KEY result (result),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    public function record(string $event, string $result = 'success', ?int $actorUserId = null, ?int $targetUserId = null, string $reason = ''): void
    {
        $this->ensureTableExists();

        db()->query(
            "INSERT INTO {$this->table}
             (actor_user_id, target_user_id, event, result, reason, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $actorUserId && $actorUserId > 0 ? $actorUserId : null,
                $targetUserId && $targetUserId > 0 ? $targetUserId : null,
                mb_substr(trim($event), 0, 80),
                mb_substr(trim($result) ?: 'success', 0, 30),
                trim($reason) !== '' ? trim($reason) : null,
                function_exists('client_ip') ? client_ip() : null,
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
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
            'event' => 'sl.event',
            'result' => 'sl.result',
            'created_at' => 'sl.created_at',
        ];
        $orderBy = $sortMap[$sort] ?? 'sl.created_at';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = 'WHERE sl.event LIKE ? OR sl.result LIKE ? OR sl.reason LIKE ? OR sl.ip_address LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like, $like];
        }

        $total = (int)db()->query("SELECT COUNT(*) FROM {$this->table} sl {$where}", $params)->getColumn();
        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();
        $items = db()->query(
            "SELECT sl.*, actor.login AS actor_login, target.login AS target_login
             FROM {$this->table} sl
             LEFT JOIN users actor ON actor.id = sl.actor_user_id
             LEFT JOIN users target ON target.id = sl.target_user_id
             {$where}
             ORDER BY {$orderBy} {$direction}, sl.id DESC
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
