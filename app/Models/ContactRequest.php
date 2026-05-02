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
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY created_at (created_at),
                KEY is_viewed (is_viewed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $isViewedExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_viewed'")->getColumn();
        if (!$isViewedExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN is_viewed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER message");
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
             (name, email, subject, message, is_viewed, created_at)
             VALUES (:name, :email, :subject, :message, :is_viewed, :created_at)",
            [
                'name' => trim((string)$data['name']),
                'email' => mb_strtolower(trim((string)$data['email'])),
                'subject' => trim((string)$data['subject']),
                'message' => trim((string)$data['message']),
                'is_viewed' => 0,
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
            "SELECT id, name, email, subject, message, is_viewed, created_at
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
        $sort = (string)($options['sort'] ?? 'created_at');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'id',
            'name' => 'name',
            'email' => 'email',
            'subject' => 'subject',
            'status' => 'is_viewed',
            'created_at' => 'created_at',
        ];

        $orderBy = $sortMap[$sort] ?? 'created_at';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        }

        $total = (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table} {$where}",
            $params
        )->getColumn();

        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT id, name, email, subject, message, is_viewed, created_at
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
            'per_page' => $perPage,
        ];
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
