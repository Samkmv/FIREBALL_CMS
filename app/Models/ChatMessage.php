<?php

namespace App\Models;

use App\Services\ChatCipher;

/**
 * Хранит сообщения чата, вложения, аудит и производные данные для списков диалогов.
 */
class ChatMessage
{

    protected string $table = 'chat_messages';
    protected string $auditTable = 'chat_audit_logs';

    /**
     * Создаёт таблицу сообщений и недостающие совместимые поля.
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
                sender_ip VARCHAR(64) NULL,
                sender_user_agent VARCHAR(255) NULL,
                is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                deleted_at DATETIME NULL,
                deleted_by INT(10) UNSIGNED NULL,
                deleted_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY sender_id (sender_id),
                KEY receiver_id (receiver_id),
                KEY conversation_pair (sender_id, receiver_id),
                KEY deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $columns = [
            'is_read' => "ALTER TABLE {$this->table} ADD COLUMN is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER message_ciphertext",
            'attachment_path' => "ALTER TABLE {$this->table} ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message_ciphertext",
            'attachment_name' => "ALTER TABLE {$this->table} ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path",
            'attachment_type' => "ALTER TABLE {$this->table} ADD COLUMN attachment_type VARCHAR(120) NULL AFTER attachment_name",
            'attachment_size' => "ALTER TABLE {$this->table} ADD COLUMN attachment_size INT(10) UNSIGNED NULL AFTER attachment_type",
            'sender_ip' => "ALTER TABLE {$this->table} ADD COLUMN sender_ip VARCHAR(64) NULL AFTER attachment_size",
            'sender_user_agent' => "ALTER TABLE {$this->table} ADD COLUMN sender_user_agent VARCHAR(255) NULL AFTER sender_ip",
            'deleted_at' => "ALTER TABLE {$this->table} ADD COLUMN deleted_at DATETIME NULL AFTER is_read",
            'deleted_by' => "ALTER TABLE {$this->table} ADD COLUMN deleted_by INT(10) UNSIGNED NULL AFTER deleted_at",
            'deleted_reason' => "ALTER TABLE {$this->table} ADD COLUMN deleted_reason VARCHAR(255) NULL AFTER deleted_by",
        ];

        foreach ($columns as $column => $query) {
            $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->table} LIKE '{$column}'")->getColumn();
            if (!$columnExists) {
                db()->query($query);
            }
        }

        $this->ensureAuditTableExists();
    }

    /**
     * Сохраняет новое сообщение чата с шифрованием текста и метаданными вложения.
     */
    public function create(int $senderId, int $receiverId, string $message, ?array $attachment = null, array $meta = []): int
    {
        $this->ensureTableExists();
        $encryptedMessage = ChatCipher::encrypt($message);

        db()->query(
            "INSERT INTO {$this->table}
             (sender_id, receiver_id, message_ciphertext, attachment_path, attachment_name, attachment_type, attachment_size, sender_ip, sender_user_agent, is_read, deleted_at, deleted_by, deleted_reason, created_at)
             VALUES (:sender_id, :receiver_id, :message_ciphertext, :attachment_path, :attachment_name, :attachment_type, :attachment_size, :sender_ip, :sender_user_agent, :is_read, NULL, NULL, NULL, :created_at)",
            [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message_ciphertext' => $encryptedMessage,
                'attachment_path' => $attachment['path'] ?? null,
                'attachment_name' => $attachment['name'] ?? null,
                'attachment_type' => $attachment['type'] ?? null,
                'attachment_size' => isset($attachment['size']) ? (int)$attachment['size'] : null,
                'sender_ip' => $this->normalizeIpAddress((string)($meta['ip'] ?? '')),
                'sender_user_agent' => $this->normalizeUserAgent((string)($meta['user_agent'] ?? '')),
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
            "SELECT id, sender_id, receiver_id, message_ciphertext, attachment_path, attachment_name, attachment_type, attachment_size, sender_ip, sender_user_agent, is_read, created_at
             FROM {$this->table}
             WHERE deleted_at IS NULL
               AND (
                    (sender_id = :first_user_id AND receiver_id = :second_user_id)
                    OR (sender_id = :second_user_id AND receiver_id = :first_user_id)
               )
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
             WHERE role IN ('creator', 'admin', 'moderator')
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
             WHERE deleted_at IS NULL
               AND sender_id = :contact_id
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
             WHERE receiver_id = :user_id
               AND is_read = 0
               AND deleted_at IS NULL",
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
             WHERE receiver_id = :user_id
               AND is_read = 0
               AND deleted_at IS NULL
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
               AND m.deleted_at IS NULL
             GROUP BY m.sender_id, u.name, u.avatar, u.role
             ORDER BY created_at DESC, sort_id DESC
             LIMIT {$limit}",
            ['user_id' => $userId]
        )->get() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $latestMessage = $this->getMessagePreviewMetaById((int)($row['sort_id'] ?? 0));
            $items[] = [
                'type' => 'chat',
                'sender_id' => (int)$row['sender_id'],
                'name' => (string)($row['name'] ?? ''),
                'avatar' => (string)($row['avatar'] ?? ''),
                'role' => (string)($row['role'] ?? 'user'),
                'unread_count' => (int)($row['unread_count'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
                'sort_id' => (int)($row['sort_id'] ?? 0),
                'preview' => (string)($latestMessage['preview'] ?? ''),
                'time' => (string)($latestMessage['created_at'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * Возвращает права текущей роли на операции чата.
     */
    public function getPermissionsForRole(?string $role = null): array
    {
        $role = trim((string)($role ?? 'user'));
        $rank = get_role_rank($role);

        return [
            'can_moderate' => $rank >= get_role_rank('moderator'),
            'can_bulk_delete' => $rank >= get_role_rank('admin'),
            'can_clear_chat' => $rank >= get_role_rank('admin'),
            'can_view_audit' => $rank >= get_role_rank('creator'),
            'is_creator' => $role === 'creator',
        ];
    }

    /**
     * Мягко удаляет сообщения и, при необходимости, очищает медиа.
     */
    public function softDeleteMessages(array $messageIds, int $actorUserId, array $options = []): array
    {
        $this->ensureTableExists();

        $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if (empty($messageIds)) {
            return [
                'deleted_count' => 0,
                'contacts' => [],
            ];
        }

        $actor = (new User())->findById($actorUserId);
        $permissions = $this->getPermissionsForRole((string)($actor['role'] ?? 'user'));
        if (empty($permissions['can_moderate'])) {
            throw new \RuntimeException(return_translation('chat_permission_denied'));
        }

        if (count($messageIds) > 1 && empty($permissions['can_bulk_delete']) && empty($options['allow_bulk'])) {
            throw new \RuntimeException(return_translation('chat_permission_denied'));
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $messages = db()->query(
            "SELECT id, sender_id, receiver_id, message_ciphertext, attachment_path, attachment_name, attachment_type, attachment_size, sender_ip, sender_user_agent
             FROM {$this->table}
             WHERE deleted_at IS NULL
               AND id IN ({$placeholders})",
            $messageIds
        )->get() ?: [];

        if (empty($messages)) {
            return [
                'deleted_count' => 0,
                'contacts' => [],
            ];
        }

        $now = date('Y-m-d H:i:s');
        $reason = mb_substr(trim((string)($options['reason'] ?? '')), 0, 255);
        $removeMedia = !array_key_exists('remove_media', $options) || (bool)$options['remove_media'];
        $contactIds = [];

        foreach ($messages as $message) {
            $contactIds[] = (int)$message['sender_id'];
            $contactIds[] = (int)$message['receiver_id'];

            $attachmentMeta = self::normalizeAttachment($message);
            if ($removeMedia && $attachmentMeta) {
                $this->deleteAttachmentFile((string)($message['attachment_path'] ?? ''));
            }

            db()->query(
                "UPDATE {$this->table}
                 SET deleted_at = ?,
                     deleted_by = ?,
                     deleted_reason = ?,
                     attachment_path = NULL,
                     attachment_name = NULL,
                     attachment_type = NULL,
                     attachment_size = NULL
                 WHERE id = ?",
                [$now, $actorUserId, $reason !== '' ? $reason : null, (int)$message['id']]
            );

            $this->logAudit([
                'action' => count($messageIds) > 1 ? 'bulk_delete' : 'delete_message',
                'actor_user_id' => $actorUserId,
                'message_id' => (int)$message['id'],
                'conversation_first_user_id' => (int)$message['sender_id'],
                'conversation_second_user_id' => (int)$message['receiver_id'],
                'details' => [
                    'reason' => $reason,
                    'remove_media' => $removeMedia,
                    'message_preview' => $this->buildMessagePreview(
                        trim(ChatCipher::decrypt((string)($message['message_ciphertext'] ?? ''))),
                        (string)($message['attachment_path'] ?? ''),
                        (string)($message['attachment_type'] ?? '')
                    ),
                    'attachment' => $attachmentMeta,
                    'sender_ip' => trim((string)($message['sender_ip'] ?? '')),
                    'sender_user_agent' => trim((string)($message['sender_user_agent'] ?? '')),
                ],
                'ip_address' => (string)($options['ip'] ?? ''),
                'user_agent' => (string)($options['user_agent'] ?? ''),
            ]);
        }

        return [
            'deleted_count' => count($messages),
            'contacts' => array_values(array_unique(array_filter($contactIds, static fn ($id) => (int)$id > 0))),
        ];
    }

    /**
     * Мягко очищает весь диалог между двумя пользователями.
     */
    public function clearConversation(int $actorUserId, int $contactId, array $options = []): array
    {
        $this->ensureTableExists();

        $actor = (new User())->findById($actorUserId);
        $permissions = $this->getPermissionsForRole((string)($actor['role'] ?? 'user'));
        if (empty($permissions['can_clear_chat'])) {
            throw new \RuntimeException(return_translation('chat_permission_denied'));
        }

        $messageIds = db()->query(
            "SELECT id
             FROM {$this->table}
             WHERE deleted_at IS NULL
               AND (
                    (sender_id = :actor_id AND receiver_id = :contact_id)
                    OR (sender_id = :contact_id AND receiver_id = :actor_id)
               )",
            [
                'actor_id' => $actorUserId,
                'contact_id' => $contactId,
            ]
        )->get() ?: [];

        $ids = array_map(static fn (array $item): int => (int)$item['id'], $messageIds);
        $result = $this->softDeleteMessages($ids, $actorUserId, [
            'allow_bulk' => true,
            'remove_media' => true,
            'reason' => (string)($options['reason'] ?? ''),
            'ip' => (string)($options['ip'] ?? ''),
            'user_agent' => (string)($options['user_agent'] ?? ''),
        ]);

        $this->logAudit([
            'action' => 'clear_conversation',
            'actor_user_id' => $actorUserId,
            'conversation_first_user_id' => $actorUserId,
            'conversation_second_user_id' => $contactId,
            'details' => [
                'reason' => trim((string)($options['reason'] ?? '')),
                'deleted_count' => (int)($result['deleted_count'] ?? 0),
            ],
            'ip_address' => (string)($options['ip'] ?? ''),
            'user_agent' => (string)($options['user_agent'] ?? ''),
        ]);

        return $result;
    }

    /**
     * Возвращает аудит действий по выбранному диалогу.
     */
    public function getAuditLogForConversation(int $firstUserId, int $secondUserId, int $limit = 100): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(200, $limit));

        $rows = db()->query(
            "SELECT l.id, l.action, l.actor_user_id, l.message_id, l.conversation_first_user_id, l.conversation_second_user_id,
                    l.details_json, l.ip_address, l.user_agent, l.created_at,
                    u.name AS actor_name, u.role AS actor_role
             FROM {$this->auditTable} l
             LEFT JOIN users u ON u.id = l.actor_user_id
             WHERE (
                    (l.conversation_first_user_id = :first_user_id AND l.conversation_second_user_id = :second_user_id)
                    OR (l.conversation_first_user_id = :second_user_id AND l.conversation_second_user_id = :first_user_id)
               )
             ORDER BY l.id DESC
             LIMIT {$limit}",
            [
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
            ]
        )->get() ?: [];

        return array_map(static function (array $row): array {
            $details = json_decode((string)($row['details_json'] ?? ''), true);
            if (!is_array($details)) {
                $details = [];
            }

            return [
                'id' => (int)$row['id'],
                'action' => (string)($row['action'] ?? ''),
                'actor_user_id' => (int)($row['actor_user_id'] ?? 0),
                'actor_name' => (string)($row['actor_name'] ?? ''),
                'actor_role' => (string)($row['actor_role'] ?? 'user'),
                'message_id' => isset($row['message_id']) ? (int)$row['message_id'] : null,
                'ip_address' => (string)($row['ip_address'] ?? ''),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'details' => $details,
            ];
        }, $rows);
    }

    /**
     * Создаёт таблицу аудита модерации.
     */
    protected function ensureAuditTableExists(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->auditTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                action VARCHAR(50) NOT NULL,
                actor_user_id INT(10) UNSIGNED NOT NULL,
                message_id BIGINT UNSIGNED NULL,
                conversation_first_user_id INT(10) UNSIGNED NULL,
                conversation_second_user_id INT(10) UNSIGNED NULL,
                details_json LONGTEXT NULL,
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY actor_user_id (actor_user_id),
                KEY message_id (message_id),
                KEY conversation_pair (conversation_first_user_id, conversation_second_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        $previewKind = self::detectAttachmentPreviewKind($type, $extension);
        $kind = self::detectAttachmentKind($type, $extension);

        return [
            'path' => ltrim($path, '/'),
            'url' => base_url('/' . ltrim($path, '/')),
            'name' => trim((string)($message['attachment_name'] ?? basename($path))),
            'type' => $type,
            'size' => (int)($message['attachment_size'] ?? 0),
            'extension' => $extension,
            'kind' => $kind,
            'preview_kind' => $previewKind,
            'is_previewable' => $previewKind !== null,
            'is_image' => $previewKind === 'image',
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
            $contact['chat_group'] = in_array(($contact['role'] ?? 'user'), ['creator', 'admin', 'moderator'], true) ? 'admins' : 'clients';
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
            "SELECT id, message_ciphertext, attachment_path, attachment_type, created_at
             FROM {$this->table}
             WHERE deleted_at IS NULL
               AND (
                    (sender_id = :first_user_id AND receiver_id = :second_user_id)
                    OR (sender_id = :second_user_id AND receiver_id = :first_user_id)
               )
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
     * Возвращает превью по конкретному сообщению.
     */
    protected function getMessagePreviewMetaById(int $messageId): array
    {
        if ($messageId <= 0) {
            return [
                'preview' => '',
                'created_at' => '',
            ];
        }

        $message = db()->query(
            "SELECT id, message_ciphertext, attachment_path, attachment_type, created_at
             FROM {$this->table}
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1",
            [$messageId]
        )->getOne();

        if (!$message) {
            return [
                'preview' => '',
                'created_at' => '',
            ];
        }

        return [
            'preview' => $this->buildMessagePreview(
                trim(ChatCipher::decrypt((string)($message['message_ciphertext'] ?? ''))),
                (string)($message['attachment_path'] ?? ''),
                (string)($message['attachment_type'] ?? '')
            ),
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
     * Записывает элемент аудита в таблицу.
     */
    protected function logAudit(array $payload): void
    {
        $details = $payload['details'] ?? [];
        $encodedDetails = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        db()->query(
            "INSERT INTO {$this->auditTable}
             (action, actor_user_id, message_id, conversation_first_user_id, conversation_second_user_id, details_json, ip_address, user_agent, created_at)
             VALUES (:action, :actor_user_id, :message_id, :conversation_first_user_id, :conversation_second_user_id, :details_json, :ip_address, :user_agent, :created_at)",
            [
                'action' => trim((string)($payload['action'] ?? 'chat_action')) ?: 'chat_action',
                'actor_user_id' => (int)($payload['actor_user_id'] ?? 0),
                'message_id' => isset($payload['message_id']) ? (int)$payload['message_id'] : null,
                'conversation_first_user_id' => isset($payload['conversation_first_user_id']) ? (int)$payload['conversation_first_user_id'] : null,
                'conversation_second_user_id' => isset($payload['conversation_second_user_id']) ? (int)$payload['conversation_second_user_id'] : null,
                'details_json' => $encodedDetails !== false ? $encodedDetails : null,
                'ip_address' => $this->normalizeIpAddress((string)($payload['ip_address'] ?? '')),
                'user_agent' => $this->normalizeUserAgent((string)($payload['user_agent'] ?? '')),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Удаляет вложение с диска, если оно находится внутри uploads.
     */
    protected function deleteAttachmentFile(string $path): void
    {
        $path = ltrim(trim($path), '/');
        if ($path === '' || !str_starts_with($path, 'uploads/')) {
            return;
        }

        $absolutePath = WWW . '/' . $path;
        if (!is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }

    /**
     * Проверяет по пути файла, является ли вложение изображением.
     */
    protected function isImageAttachmentPath(string $path): bool
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true);
    }

    /**
     * Приводит IP-адрес к совместимому безопасному виду.
     */
    protected function normalizeIpAddress(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 64);
    }

    /**
     * Ограничивает длину user-agent.
     */
    protected function normalizeUserAgent(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    /**
     * Определяет общий тип вложения для отображения на клиенте.
     */
    protected static function detectAttachmentKind(string $type, string $extension): string
    {
        if (str_starts_with($type, 'image/')) {
            return 'image';
        }

        if (str_starts_with($type, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($type, 'video/')) {
            return 'video';
        }

        if (in_array($extension, ['zip', 'rar', '7z'], true)) {
            return 'archive';
        }

        if (in_array($extension, ['pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'odt', 'ods', 'odp'], true)) {
            return 'document';
        }

        return 'file';
    }

    /**
     * Определяет вариант предпросмотра вложения.
     */
    protected static function detectAttachmentPreviewKind(string $type, string $extension): ?string
    {
        if (str_starts_with($type, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true)) {
            return 'image';
        }

        if (str_starts_with($type, 'audio/') || in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'], true)) {
            return 'audio';
        }

        if (str_starts_with($type, 'video/') || in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'mkv', 'mpeg', 'mpg'], true)) {
            return 'video';
        }

        if ($type === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if (str_starts_with($type, 'text/') || in_array($extension, ['txt', 'csv', 'json', 'log', 'xml', 'html', 'md'], true)) {
            return 'text';
        }

        return null;
    }

}
