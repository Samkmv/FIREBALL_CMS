<?php

namespace App\Models;

use App\Services\ChatCipher;

/**
 * Хранит сообщения чата, вложения и производные данные для списков диалогов.
 */
class ChatMessage
{

    protected string $table = 'chat_messages';

    /**
     * Создаёт таблицу сообщений и недостающие поля для вложений и статуса прочтения.
     */
    public function ensureTableExists(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                sender_id INT(10) UNSIGNED NOT NULL,
                receiver_id INT(10) UNSIGNED NOT NULL,
                message_ciphertext MEDIUMTEXT NOT NULL,
                attachment_path VARCHAR(255) NULL,
                attachment_name VARCHAR(255) NULL,
                attachment_type VARCHAR(120) NULL,
                attachment_size INT(10) UNSIGNED NULL,
                is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY sender_id (sender_id),
                KEY receiver_id (receiver_id),
                KEY conversation_pair (sender_id, receiver_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $isReadColumnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE 'is_read'")->getColumn();
        if (!$isReadColumnExists) {
            db()->query("ALTER TABLE {$this->table} ADD COLUMN is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER message_ciphertext");
        }

        $this->ensureAttachmentColumnsExist();
    }

    /**
     * Сохраняет новое сообщение чата с шифрованием текста и метаданными вложения.
     */
    public function create(int $senderId, int $receiverId, string $message, ?array $attachment = null): int
    {
        $this->ensureTableExists();
        $encryptedMessage = ChatCipher::encrypt($message);

        db()->query(
            "INSERT INTO {$this->table}
             (sender_id, receiver_id, message_ciphertext, attachment_path, attachment_name, attachment_type, attachment_size, is_read, created_at)
             VALUES (:sender_id, :receiver_id, :message_ciphertext, :attachment_path, :attachment_name, :attachment_type, :attachment_size, :is_read, :created_at)",
            [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message_ciphertext' => $encryptedMessage,
                'attachment_path' => $attachment['path'] ?? null,
                'attachment_name' => $attachment['name'] ?? null,
                'attachment_type' => $attachment['type'] ?? null,
                'attachment_size' => isset($attachment['size']) ? (int)$attachment['size'] : null,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Возвращает сообщения диалога между двумя пользователями.
     */
    public function getConversationMessages(int $firstUserId, int $secondUserId, int $limit = 100): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(300, $limit));

        $messages = db()->query(
            "SELECT id, sender_id, receiver_id, message_ciphertext, attachment_path, attachment_name, attachment_type, attachment_size, is_read, created_at
             FROM {$this->table}
             WHERE (sender_id = :first_user_id AND receiver_id = :second_user_id)
                OR (sender_id = :second_user_id AND receiver_id = :first_user_id)
             ORDER BY id DESC
             LIMIT {$limit}",
            [
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
            ]
        )->get() ?: [];

        $messages = array_reverse($messages);

        return array_map(static function (array $message): array {
            return [
                'id' => (int)$message['id'],
                'sender_id' => (int)$message['sender_id'],
                'receiver_id' => (int)$message['receiver_id'],
                'message' => ChatCipher::decrypt((string)$message['message_ciphertext']),
                'attachment' => self::normalizeAttachment($message),
                'is_read' => (int)$message['is_read'],
                'created_at' => (string)$message['created_at'],
            ];
        }, $messages);
    }

    /**
     * Возвращает список контактов для чата с учётом роли пользователя.
     */
    public function getContactsForUser(int $userId, bool $isAdmin): array
    {
        $this->ensureTableExists();
        $unreadCounts = $this->getUnreadCountsByContactForUser($userId);

        if ($isAdmin) {
            $contacts = db()->query(
                "SELECT id, name, email, avatar, role, last_seen_at
                 FROM users
                 WHERE id != ?
                 ORDER BY role DESC, name ASC",
                [$userId]
            )->get() ?: [];

            return $this->enrichContactsForList($contacts, $unreadCounts, $userId);
        }

        $contacts = db()->query(
            "SELECT id, name, email, avatar, role, last_seen_at
             FROM users
             WHERE role = 'admin'
             ORDER BY id ASC"
        )->get() ?: [];

        return $this->enrichContactsForList($contacts, $unreadCounts, $userId);
    }

    /**
     * Помечает сообщения выбранного диалога как прочитанные.
     */
    public function markConversationAsRead(int $currentUserId, int $contactId): void
    {
        $this->ensureTableExists();
        db()->query(
            "UPDATE {$this->table}
             SET is_read = 1
             WHERE sender_id = :contact_id
               AND receiver_id = :current_user_id
               AND is_read = 0",
            [
                'contact_id' => $contactId,
                'current_user_id' => $currentUserId,
            ]
        );
    }

    /**
     * Возвращает общее количество непрочитанных сообщений пользователя.
     */
    public function getUnreadCountForUser(int $userId): int
    {
        $this->ensureTableExists();
        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE receiver_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        )->getColumn();
    }

    /**
     * Возвращает количество непрочитанных сообщений в разрезе отправителей.
     */
    public function getUnreadCountsByContactForUser(int $userId): array
    {
        $this->ensureTableExists();
        $rows = db()->query(
            "SELECT sender_id, COUNT(*) AS total
             FROM {$this->table}
             WHERE receiver_id = :user_id AND is_read = 0
             GROUP BY sender_id",
            ['user_id' => $userId]
        )->get() ?: [];

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['sender_id']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * Возвращает элементы уведомлений по непрочитанным сообщениям.
     */
    public function getUnreadNotificationItemsForUser(int $userId, int $limit = 8): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(20, $limit));

        $rows = db()->query(
            "SELECT m.sender_id, COUNT(*) AS unread_count, MAX(m.id) AS sort_id, MAX(m.created_at) AS created_at,
                    u.name, u.avatar, u.role
             FROM {$this->table} m
             INNER JOIN users u ON u.id = m.sender_id
             WHERE m.receiver_id = :user_id
               AND m.is_read = 0
             GROUP BY m.sender_id, u.name, u.avatar, u.role
             ORDER BY created_at DESC, sort_id DESC
             LIMIT {$limit}",
            ['user_id' => $userId]
        )->get() ?: [];

        return array_map(static function (array $row): array {
            return [
                'type' => 'chat',
                'sender_id' => (int)$row['sender_id'],
                'name' => (string)($row['name'] ?? ''),
                'avatar' => (string)($row['avatar'] ?? ''),
                'role' => (string)($row['role'] ?? 'user'),
                'unread_count' => (int)($row['unread_count'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
                'sort_id' => (int)($row['sort_id'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Добавляет в таблицу недостающие колонки для вложений.
     */
    protected function ensureAttachmentColumnsExist(): void
    {
        $columns = [
            'attachment_path' => "ALTER TABLE {$this->table} ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message_ciphertext",
            'attachment_name' => "ALTER TABLE {$this->table} ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path",
            'attachment_type' => "ALTER TABLE {$this->table} ADD COLUMN attachment_type VARCHAR(120) NULL AFTER attachment_name",
            'attachment_size' => "ALTER TABLE {$this->table} ADD COLUMN attachment_size INT(10) UNSIGNED NULL AFTER attachment_type",
        ];

        foreach ($columns as $column => $query) {
            $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE '{$column}'")->getColumn();
            if (!$columnExists) {
                db()->query($query);
            }
        }
    }

    /**
     * Преобразует поля вложения сообщения в удобную структуру для клиента.
     */
    protected static function normalizeAttachment(array $message): ?array
    {
        $path = trim((string)($message['attachment_path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $type = trim((string)($message['attachment_type'] ?? ''));

        return [
            'path' => ltrim($path, '/'),
            'url' => base_url('/' . ltrim($path, '/')),
            'name' => trim((string)($message['attachment_name'] ?? basename($path))),
            'type' => $type,
            'size' => (int)($message['attachment_size'] ?? 0),
            'is_image' => str_starts_with($type, 'image/'),
        ];
    }

    /**
     * Дополняет список контактов счётчиками, онлайном и превью последнего сообщения.
     */
    protected function enrichContactsForList(array $contacts, array $unreadCounts, int $currentUserId): array
    {
        $users = new User();

        $contacts = array_map(function (array $contact) use ($unreadCounts, $users, $currentUserId): array {
            $contact['unread_count'] = $unreadCounts[(int)$contact['id']] ?? 0;
            $contact['is_online'] = $users->isOnline($contact['last_seen_at'] ?? null);
            $contact['chat_group'] = ($contact['role'] ?? 'user') === 'admin' ? 'admins' : 'clients';
            $lastMessage = $this->getLastMessageMeta($currentUserId, (int)$contact['id']);
            $contact['last_message_preview'] = $lastMessage['preview'];
            $contact['last_message_at'] = $lastMessage['created_at'];
            return $contact;
        }, $contacts);

        usort($contacts, static function (array $left, array $right): int {
            $leftTime = strtotime((string)($left['last_message_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string)($right['last_message_at'] ?? '')) ?: 0;

            if ($leftTime === $rightTime) {
                return strcmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
            }

            return $rightTime <=> $leftTime;
        });

        return $contacts;
    }

    /**
     * Возвращает краткую информацию о последнем сообщении в диалоге.
     */
    protected function getLastMessageMeta(int $firstUserId, int $secondUserId): array
    {
        $message = db()->query(
            "SELECT message_ciphertext, attachment_path, attachment_type, created_at
             FROM {$this->table}
             WHERE (sender_id = :first_user_id AND receiver_id = :second_user_id)
                OR (sender_id = :second_user_id AND receiver_id = :first_user_id)
             ORDER BY id DESC
             LIMIT 1",
            [
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
            ]
        )->getOne();

        if (!$message) {
            return [
                'preview' => '',
                'created_at' => null,
            ];
        }

        $text = trim(ChatCipher::decrypt((string)($message['message_ciphertext'] ?? '')));
        $preview = $this->buildMessagePreview(
            $text,
            (string)($message['attachment_path'] ?? ''),
            (string)($message['attachment_type'] ?? '')
        );

        return [
            'preview' => $preview,
            'created_at' => (string)($message['created_at'] ?? ''),
        ];
    }

    /**
     * Собирает краткое превью текста сообщения или вложения.
     */
    protected function buildMessagePreview(string $text, string $attachmentPath, string $attachmentType): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', $text));
        if ($text !== '') {
            return mb_strlen($text) > 72
                ? rtrim(mb_substr($text, 0, 71)) . '...'
                : $text;
        }

        $attachmentPath = trim($attachmentPath);
        $attachmentType = trim($attachmentType);
        if ($attachmentPath !== '' || $attachmentType !== '') {
            return str_starts_with($attachmentType, 'image/') || $this->isImageAttachmentPath($attachmentPath)
                ? return_translation('chat_attachment_image')
                : return_translation('chat_attachment_file');
        }

        return '';
    }

    /**
     * Проверяет по пути файла, является ли вложение изображением.
     */
    protected function isImageAttachmentPath(string $path): bool
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

}
