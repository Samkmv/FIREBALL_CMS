<?php

namespace App\Models;

use FBL\Pagination;

/**
 * Работает с заявками, отправленными через форму контактов.
 */
class ContactRequest
{

    protected string $table = 'contact_requests';
    protected string $messagesTable = 'contact_request_messages';
    // FIREBALL_SUPPORT_HISTORY_PATCH_V1

    /**
     * Создаёт таблицу заявок и недостающие поля, если схема ещё не готова.
     */
    public function ensureTableExists(): void
    {
        if (!\App\Services\SchemaMigration::isRunning()) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(50) NOT NULL DEFAULT '',
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

        $phoneExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'phone'")->getColumn();
        if (!$phoneExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN phone VARCHAR(50) NOT NULL DEFAULT '' AFTER email");
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
             (name, email, phone, subject, message, is_viewed, status, created_at)
             VALUES (:name, :email, :phone, :subject, :message, :is_viewed, :status, :created_at)",
            [
                'name' => trim((string)$data['name']),
                'email' => mb_strtolower(trim((string)$data['email'])),
                'phone' => mb_substr(trim((string)($data['phone'] ?? '')), 0, 50),
                'subject' => trim((string)$data['subject']),
                'message' => trim((string)$data['message']),
                'is_viewed' => 0,
                'status' => 'new',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $requestId = (int)db()->getInsertId();

        $this->addConversationMessage($requestId, [
            'sender_type' => 'requester',
            'sender_name' => trim((string)$data['name']),
            'sender_email' => mb_strtolower(trim((string)$data['email'])),
            'recipient_email' => '',
            'subject' => trim((string)$data['subject']),
            'message' => trim((string)$data['message']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $requestId;
    }

    /**
     * Возвращает все заявки без пагинации.
     */
    public function getAll(): array
    {
        $this->ensureTableExists();

        return db()->query(
            "SELECT id, name, email, phone, subject, message, is_viewed, status, created_at
             FROM {$this->table}
             ORDER BY id DESC"
        )->get() ?: [];
    }

    public function findById(int $id): ?array
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return null;
        }

        $row = db()->query(
            "SELECT id, name, email, phone, subject, message, is_viewed, status, created_at
             FROM {$this->table} WHERE id = ? LIMIT 1",
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
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
            'phone' => 'phone',
            'subject' => 'subject',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $orderBy = $sortMap[$sort] ?? 'created_at';
        $whereParts = [];
        $params = [];

        if ($search !== '') {
            $whereParts[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike];
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
            "SELECT id, name, email, phone, subject, message, is_viewed, status, created_at
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
     * Возвращает компактную сводку и последние заявки для главной страницы админки.
     */
    public function getDashboardSummary(int $limit = 6): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(12, $limit));

        $counts = db()->query(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'new'), 0) AS new_count,
                COALESCE(SUM(status = 'in_work'), 0) AS in_work_count,
                COALESCE(SUM(status = 'closed'), 0) AS closed_count,
                COALESCE(SUM(status = 'spam'), 0) AS spam_count,
                COALESCE(SUM(is_viewed = 0), 0) AS unread_count
             FROM {$this->table}"
        )->getOne() ?: [];

        $items = db()->query(
            "SELECT id, name, email, phone, subject, status, is_viewed, created_at
             FROM {$this->table}
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        )->get() ?: [];

        return [
            'total' => (int)($counts['total'] ?? 0),
            'new' => (int)($counts['new_count'] ?? 0),
            'in_work' => (int)($counts['in_work_count'] ?? 0),
            'closed' => (int)($counts['closed_count'] ?? 0),
            'spam' => (int)($counts['spam_count'] ?? 0),
            'unread' => (int)($counts['unread_count'] ?? 0),
            'items' => array_values(array_filter($items, 'is_array')),
        ];
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
            if ($this->conversationTableExists()) {
                db()->query(
                    "DELETE FROM {$this->messagesTable} WHERE request_id IN ({$placeholders})",
                    $ids
                );
            }
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
     * Возвращает сообщения переписки по заявке в хронологическом порядке.
     */
    public function getConversation(int $requestId): array
    {
        $this->ensureTableExists();
        if ($requestId <= 0 || !$this->conversationTableExists()) {
            return [];
        }

        return db()->query(
            "SELECT id, request_id, sender_type, sender_user_id, sender_name,
                    sender_email, recipient_email, subject, message, created_at
             FROM {$this->messagesTable}
             WHERE request_id = ?
             ORDER BY created_at ASC, id ASC",
            [$requestId]
        )->get() ?: [];
    }

    /**
     * Сохраняет успешно отправленный ответ администратора в историю заявки.
     */
    public function addAdminReply(int $requestId, array $data): bool
    {
        if ($requestId <= 0 || !$this->conversationTableExists()) {
            return false;
        }

        return $this->addConversationMessage($requestId, [
            'sender_type' => 'admin',
            'sender_user_id' => (int)($data['sender_user_id'] ?? 0) ?: null,
            'sender_name' => (string)($data['sender_name'] ?? ''),
            'sender_email' => (string)($data['sender_email'] ?? ''),
            'recipient_email' => (string)($data['recipient_email'] ?? ''),
            'subject' => (string)($data['subject'] ?? ''),
            'message' => (string)($data['message'] ?? ''),
            'created_at' => (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }

    /**
     * Добавляет сообщение в историю заявки.
     */
    protected function addConversationMessage(int $requestId, array $data): bool
    {
        if ($requestId <= 0 || !$this->conversationTableExists()) {
            return false;
        }

        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') {
            return false;
        }

        $senderType = (string)($data['sender_type'] ?? 'requester');
        if (!in_array($senderType, ['requester', 'admin'], true)) {
            $senderType = 'requester';
        }

        db()->query(
            "INSERT INTO {$this->messagesTable}
             (request_id, sender_type, sender_user_id, sender_name, sender_email,
              recipient_email, subject, message, created_at)
             VALUES
             (:request_id, :sender_type, :sender_user_id, :sender_name, :sender_email,
              :recipient_email, :subject, :message, :created_at)",
            [
                'request_id' => $requestId,
                'sender_type' => $senderType,
                'sender_user_id' => isset($data['sender_user_id']) && (int)$data['sender_user_id'] > 0
                    ? (int)$data['sender_user_id']
                    : null,
                'sender_name' => mb_substr(trim((string)($data['sender_name'] ?? '')), 0, 150),
                'sender_email' => mb_substr(mb_strtolower(trim((string)($data['sender_email'] ?? ''))), 0, 190),
                'recipient_email' => mb_substr(mb_strtolower(trim((string)($data['recipient_email'] ?? ''))), 0, 190),
                'subject' => mb_substr(trim((string)($data['subject'] ?? '')), 0, 190),
                'message' => $message,
                'created_at' => (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            ]
        );

        return true;
    }

    /**
     * Проверяет наличие таблицы истории без изменения схемы во время обычного запроса.
     */
    protected function conversationTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $exists = (bool)db()->query(
                "SHOW TABLES LIKE ?",
                [$this->messagesTable]
            )->getColumn();
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
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

        if ($this->conversationTableExists()) {
            db()->query(
                "DELETE FROM {$this->messagesTable} WHERE request_id = ?",
                [$id]
            );
        }

        db()->query(
            "DELETE FROM {$this->table}
             WHERE id = :id
             LIMIT 1",
            ['id' => $id]
        );
    }
}
