<?php

namespace App\Models;

use FBL\Pagination;

/**
 * Работает с заявками, отправленными через форму контактов.
 */
class ContactRequest
{

    protected string $table = 'contact_requests';

    /**
     * Создаёт таблицу заявок и недостающие поля, если схема ещё не готова.
     */
    public function ensureTableExists(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NOT NULL,
                subject VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                is_viewed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY created_at (created_at),
                KEY is_viewed (is_viewed),
                KEY status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $isViewedExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_viewed'")->getColumn();
        if (!$isViewedExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN is_viewed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER message");
        }

        $statusExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'status'")->getColumn();
        if (!$statusExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'new' AFTER is_viewed");
            db()->query("ALTER TABLE {$this->table} ADD INDEX status (status)");
            db()->query("UPDATE {$this->table} SET status = CASE WHEN is_viewed = 1 THEN 'in_work' ELSE 'new' END");
        }
    }

    /**
     * Сохраняет новую заявку из формы контактов.
     */
    public function create(array $data): int
    {
        $this->ensureTableExists();

        db()->query(
            "INSERT INTO {$this->table}
             (name, email, subject, message, is_viewed, status, created_at)
             VALUES (:name, :email, :subject, :message, :is_viewed, :status, :created_at)",
            [
                'name' => trim((string)$data['name']),
                'email' => mb_strtolower(trim((string)$data['email'])),
                'subject' => trim((string)$data['subject']),
                'message' => trim((string)$data['message']),
                'is_viewed' => 0,
                'status' => 'new',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Возвращает все заявки без пагинации.
     */
    public function getAll(): array
    {
        $this->ensureTableExists();

        return db()->query(
            "SELECT id, name, email, subject, message, is_viewed, status, created_at
             FROM {$this->table}
             ORDER BY id DESC"
        )->get() ?: [];
    }

    /**
     * Возвращает список заявок с поиском, сортировкой и пагинацией.
     */
    public function getPaginated(array $options = []): array
    {
        $this->ensureTableExists();

        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $search = trim((string)($options['search'] ?? ''));
        $status = $this->normalizeStatus((string)($options['status'] ?? ''));
        $sort = (string)($options['sort'] ?? 'created_at');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'id',
            'name' => 'name',
            'email' => 'email',
            'subject' => 'subject',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $orderBy = $sortMap[$sort] ?? 'created_at';
        $whereParts = [];
        $params = [];

        if ($search !== '') {
            $whereParts[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        }

        if ($status !== '') {
            $whereParts[] = "status = ?";
            $params[] = $status;
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $total = (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} {$where}",
            $params
        )->getColumn();

        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT id, name, email, subject, message, is_viewed, status, created_at
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
            'status' => $status,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    public function statuses(): array
    {
        return ['new', 'in_work', 'closed', 'spam'];
    }

    public function normalizeStatus(string $status): string
    {
        $status = trim($status);
        return in_array($status, $this->statuses(), true) ? $status : '';
    }

    /**
     * Возвращает общее количество заявок.
     */
    public function countAll(): int
    {
        $this->ensureTableExists();

        return (int)db()->query("SELECT COUNT(*) FROM {$this->table}")->getColumn();
    }

    /**
     * Возвращает количество непросмотренных заявок.
     */
    public function countUnread(): int
    {
        $this->ensureTableExists();

        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE is_viewed = 0"
        )->getColumn();
    }

    public function countNew(): int
    {
        $this->ensureTableExists();

        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'new'"
        )->getColumn();
    }

    /**
     * Возвращает последние непросмотренные заявки в формате для уведомлений.
     */
    public function getUnreadNotificationItems(int $limit = 8): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(20, $limit));

        $rows = db()->query(
            "SELECT id, name, subject, created_at
             FROM {$this->table}
             WHERE is_viewed = 0
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        )->get() ?: [];

        return array_map(static function (array $row): array {
            return [
                'type' => 'contact_request',
                'id' => (int)$row['id'],
                'name' => (string)($row['name'] ?? ''),
                'subject' => (string)($row['subject'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'sort_id' => (int)($row['id'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Помечает все непросмотренные заявки как просмотренные.
     */
    public function markAllViewed(): void
    {
        $this->ensureTableExists();

        db()->query(
            "UPDATE {$this->table}
             SET is_viewed = 1
             WHERE is_viewed = 0"
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->ensureTableExists();
        $status = $this->normalizeStatus($status);
        if ($id <= 0 || $status === '') {
            return;
        }

        db()->query(
            "UPDATE {$this->table}
             SET status = :status, is_viewed = 1
             WHERE id = :id
             LIMIT 1",
            ['status' => $status, 'id' => $id]
        );
    }

    public function bulkAction(array $ids, string $action, string $status = ''): int
    {
        $this->ensureTableExists();

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if (!$ids) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'delete') {
            db()->query("DELETE FROM {$this->table} WHERE id IN ({$placeholders})", $ids);
            return count($ids);
        }

        if ($action === 'mark_viewed') {
            db()->query("UPDATE {$this->table} SET is_viewed = 1 WHERE id IN ({$placeholders})", $ids);
            return count($ids);
        }

        if ($action === 'status') {
            $status = $this->normalizeStatus($status);
            if ($status === '') {
                return 0;
            }

            db()->query("UPDATE {$this->table} SET status = ?, is_viewed = 1 WHERE id IN ({$placeholders})", array_merge([$status], $ids));
            return count($ids);
        }

        return 0;
    }

    /**
     * Удаляет заявку по идентификатору.
     */
    public function deleteById(int $id): void
    {
        $this->ensureTableExists();

        if ($id <= 0) {
            return;
        }

        db()->query(
            "DELETE FROM {$this->table}
             WHERE id = :id
             LIMIT 1",
            ['id' => $id]
        );
    }
}
