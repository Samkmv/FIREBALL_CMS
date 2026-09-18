<?php

namespace App\Services;

// FIREBALL_CHAT30_GROUPS
final class GroupChatService
{
    public function createGroup(int $creatorId, string $title, array $memberIds): int
    {
        $title = trim($title);
        if ($creatorId <= 0 || mb_strlen($title) < 2 || mb_strlen($title) > 100) {
            throw new \InvalidArgumentException('Invalid group data.');
        }

        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        $memberIds = array_values(array_filter(
            $memberIds,
            static fn (int $id): bool => $id > 0 && $id !== $creatorId
        ));

        if (count($memberIds) < 2) {
            throw new \InvalidArgumentException('A group requires at least three participants.');
        }

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $existing = db()->query(
            "SELECT id FROM users WHERE id IN ({$placeholders})",
            $memberIds
        )->get() ?: [];

        $existingIds = array_map(
            static fn (array $row): int => (int)$row['id'],
            $existing
        );
        sort($existingIds);
        $expected = $memberIds;
        sort($expected);

        if ($existingIds !== $expected) {
            throw new \InvalidArgumentException('Invalid group members.');
        }

        $now = date('Y-m-d H:i:s');

        db()->query(
            "INSERT INTO chat_conversations
                (type, direct_key, title, created_by, last_message_id, created_at, updated_at)
             VALUES ('group', NULL, ?, ?, NULL, ?, ?)",
            [$title, $creatorId, $now, $now]
        );

        $conversationId = (int)db()->getInsertId();
        if ($conversationId <= 0) {
            throw new \RuntimeException('Could not create group conversation.');
        }

        try {
            db()->query(
                "INSERT INTO chat_members
                    (conversation_id, user_id, role, last_read_message_id, muted_until, joined_at)
                 VALUES (?, ?, 'owner', NULL, NULL, ?)",
                [$conversationId, $creatorId, $now]
            );

            foreach ($memberIds as $memberId) {
                db()->query(
                    "INSERT INTO chat_members
                        (conversation_id, user_id, role, last_read_message_id, muted_until, joined_at)
                     VALUES (?, ?, 'member', NULL, NULL, ?)",
                    [$conversationId, $memberId, $now]
                );
            }
        } catch (\Throwable $exception) {
            db()->query(
                "DELETE FROM chat_members WHERE conversation_id = ?",
                [$conversationId]
            );
            db()->query(
                "DELETE FROM chat_conversations
                 WHERE id = ? AND type = 'group' AND last_message_id IS NULL",
                [$conversationId]
            );
            throw $exception;
        }

        return $conversationId;
    }

    public function isMember(int $conversationId, int $userId): bool
    {
        return $conversationId > 0
            && $userId > 0
            && (bool)db()->query(
                "SELECT COUNT(*)
                 FROM chat_members cm
                 INNER JOIN chat_conversations c
                   ON c.id = cm.conversation_id
                  AND c.type = 'group'
                 WHERE cm.conversation_id = ?
                   AND cm.user_id = ?",
                [$conversationId, $userId]
            )->getColumn();
    }

    public function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return db()->query(
            "SELECT
                c.id,
                c.title,
                c.created_by,
                c.updated_at,
                cm.role,
                cm.last_read_message_id,
                (
                    SELECT COUNT(*)
                    FROM chat_members all_members
                    WHERE all_members.conversation_id = c.id
                ) AS member_count,
                (
                    SELECT COUNT(*)
                    FROM chat_messages unread_messages
                    WHERE unread_messages.conversation_id = c.id
                      AND unread_messages.deleted_at IS NULL
                      AND unread_messages.sender_id <> ?
                      AND unread_messages.id > COALESCE(cm.last_read_message_id, 0)
                ) AS unread_count
             FROM chat_members cm
             INNER JOIN chat_conversations c
               ON c.id = cm.conversation_id
              AND c.type = 'group'
             WHERE cm.user_id = ?
             ORDER BY c.updated_at DESC, c.id DESC",
            [$userId, $userId]
        )->get() ?: [];
    }

    public function getForUser(int $conversationId, int $userId): ?array
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return null;
        }

        $row = db()->query(
            "SELECT
                c.id,
                c.title,
                c.created_by,
                c.created_at,
                c.updated_at,
                cm.role,
                (
                    SELECT COUNT(*)
                    FROM chat_members all_members
                    WHERE all_members.conversation_id = c.id
                ) AS member_count
             FROM chat_conversations c
             INNER JOIN chat_members cm
               ON cm.conversation_id = c.id
              AND cm.user_id = ?
             WHERE c.id = ?
               AND c.type = 'group'
             LIMIT 1",
            [$userId, $conversationId]
        )->getOne();

        return $row ?: null;
    }

    public function getMembers(int $conversationId): array
    {
        if ($conversationId <= 0) {
            return [];
        }

        return db()->query(
            "SELECT
                u.id,
                u.name,
                u.avatar,
                u.role,
                u.last_seen_at,
                cm.role AS group_role,
                cm.joined_at
             FROM chat_members cm
             INNER JOIN users u ON u.id = cm.user_id
             WHERE cm.conversation_id = ?
             ORDER BY
                CASE cm.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END,
                u.name ASC",
            [$conversationId]
        )->get() ?: [];
    }

    public function createMessage(
        int $conversationId,
        int $senderId,
        string $message,
        array $meta = []
    ): int {
        $message = trim($message);

        if (
            $message === ''
            || mb_strlen($message) > 2000
            || !$this->isMember($conversationId, $senderId)
        ) {
            throw new \InvalidArgumentException('Invalid group message.');
        }

        $encryptedMessage = ChatCipher::encrypt($message);
        $encryptionKeyId = ChatCipher::currentKeyId();
        $ip = mb_substr(trim((string)($meta['ip'] ?? '')), 0, 64);
        $userAgent = mb_substr(trim((string)($meta['user_agent'] ?? '')), 0, 255);
        $now = date('Y-m-d H:i:s');

        db()->query(
            "INSERT INTO chat_messages
                (
                    conversation_id,
                    sender_id,
                    receiver_id,
                    message_ciphertext,
                    encryption_key_id,
                    attachment_path,
                    attachment_name,
                    attachment_type,
                    attachment_size,
                    sender_ip,
                    sender_user_agent,
                    is_read,
                    delivered_at,
                    edited_at,
                    reply_to_id,
                    deleted_at,
                    deleted_by,
                    deleted_reason,
                    created_at
                )
             VALUES (?, ?, 0, ?, ?, NULL, NULL, NULL, NULL, ?, ?, 0, NULL, NULL, NULL, NULL, NULL, NULL, ?)",
            [
                $conversationId,
                $senderId,
                $encryptedMessage,
                $encryptionKeyId,
                $ip !== '' ? $ip : null,
                $userAgent !== '' ? $userAgent : null,
                $now,
            ]
        );

        $messageId = (int)db()->getInsertId();
        if ($messageId <= 0) {
            throw new \RuntimeException('Could not create group message.');
        }

        (new ConversationService())->touchMessage($conversationId, $messageId);

        return $messageId;
    }

    public function getMessages(
        int $conversationId,
        int $currentUserId,
        int $limit = 100
    ): array {
        if (!$this->isMember($conversationId, $currentUserId)) {
            return [];
        }

        $limit = max(1, min(300, $limit));

        $rows = db()->query(
            "SELECT
                m.id,
                m.sender_id,
                m.message_ciphertext,
                m.created_at,
                m.edited_at,
                u.name AS sender_name,
                u.avatar AS sender_avatar,
                u.role AS sender_role
             FROM chat_messages m
             INNER JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ?
               AND m.deleted_at IS NULL
             ORDER BY m.id DESC
             LIMIT {$limit}",
            [$conversationId]
        )->get() ?: [];

        $rows = array_reverse($rows);

        return array_map(
            static function (array $row) use ($currentUserId): array {
                return [
                    'id' => (int)$row['id'],
                    'sender_id' => (int)$row['sender_id'],
                    'sender_name' => (string)($row['sender_name'] ?? ''),
                    'sender_avatar' => (string)($row['sender_avatar'] ?? ''),
                    'sender_role' => (string)($row['sender_role'] ?? 'user'),
                    'message' => ChatCipher::decrypt(
                        (string)($row['message_ciphertext'] ?? '')
                    ),
                    'created_at' => (string)$row['created_at'],
                    'edited_at' => !empty($row['edited_at'])
                        ? (string)$row['edited_at']
                        : null,
                    'is_mine' => (int)$row['sender_id'] === $currentUserId,
                ];
            },
            $rows
        );
    }

    public function markDelivered(int $conversationId, int $userId): void
    {
        if (!$this->isMember($conversationId, $userId)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        db()->query(
            "INSERT INTO chat_receipts
                (message_id, user_id, delivered_at, read_at)
             SELECT id, ?, ?, NULL
             FROM chat_messages
             WHERE conversation_id = ?
               AND sender_id <> ?
               AND deleted_at IS NULL
             ON DUPLICATE KEY UPDATE
                delivered_at = COALESCE(
                    chat_receipts.delivered_at,
                    VALUES(delivered_at)
                )",
            [$userId, $now, $conversationId, $userId]
        );
    }

    public function markRead(int $conversationId, int $userId): void
    {
        if (!$this->isMember($conversationId, $userId)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        db()->query(
            "INSERT INTO chat_receipts
                (message_id, user_id, delivered_at, read_at)
             SELECT id, ?, ?, ?
             FROM chat_messages
             WHERE conversation_id = ?
               AND sender_id <> ?
               AND deleted_at IS NULL
             ON DUPLICATE KEY UPDATE
                delivered_at = COALESCE(
                    chat_receipts.delivered_at,
                    VALUES(delivered_at)
                ),
                read_at = VALUES(read_at)",
            [$userId, $now, $now, $conversationId, $userId]
        );

        $lastMessageId = (int)db()->query(
            "SELECT COALESCE(MAX(id), 0)
             FROM chat_messages
             WHERE conversation_id = ?
               AND deleted_at IS NULL",
            [$conversationId]
        )->getColumn();

        if ($lastMessageId > 0) {
            db()->query(
                "UPDATE chat_members
                 SET last_read_message_id = GREATEST(
                     COALESCE(last_read_message_id, 0),
                     ?
                 )
                 WHERE conversation_id = ?
                   AND user_id = ?",
                [$lastMessageId, $conversationId, $userId]
            );
        }
    }
}
