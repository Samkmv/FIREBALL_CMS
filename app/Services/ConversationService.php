<?php
namespace App\Services;
// FIREBALL_CHAT2_FOUNDATION
final class ConversationService
{
    public function ensureDirectConversation(int $firstUserId, int $secondUserId): int
    {
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            throw new \InvalidArgumentException('Invalid direct chat participants.');
        }
        $directKey = $this->directKey($firstUserId, $secondUserId);
        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT INTO chat_conversations
                (type, direct_key, title, created_by, last_message_id, created_at, updated_at)
             VALUES ('direct', ?, NULL, ?, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            [$directKey, min($firstUserId, $secondUserId), $now, $now]
        );
        $id = (int)db()->getInsertId();
        if ($id <= 0) {
            $id = (int)db()->query(
                "SELECT id FROM chat_conversations WHERE type = 'direct' AND direct_key = ? LIMIT 1",
                [$directKey]
            )->getColumn();
        }
        if ($id <= 0) {
            throw new \RuntimeException('Could not resolve direct chat conversation.');
        }
        $this->ensureMember($id, $firstUserId, $now);
        $this->ensureMember($id, $secondUserId, $now);
        return $id;
    }

    public function findDirectConversationId(int $firstUserId, int $secondUserId): ?int
    {
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            return null;
        }
        $id = (int)db()->query(
            "SELECT id FROM chat_conversations WHERE type = 'direct' AND direct_key = ? LIMIT 1",
            [$this->directKey($firstUserId, $secondUserId)]
        )->getColumn();
        return $id > 0 ? $id : null;
    }

    public function isMember(int $conversationId, int $userId): bool
    {
        return $conversationId > 0 && $userId > 0 && (bool)db()->query(
            "SELECT COUNT(*) FROM chat_members WHERE conversation_id = ? AND user_id = ?",
            [$conversationId, $userId]
        )->getColumn();
    }

    public function touchMessage(int $conversationId, int $messageId): void
    {
        if ($conversationId <= 0 || $messageId <= 0) return;
        db()->query(
            "UPDATE chat_conversations SET last_message_id = ?, updated_at = ? WHERE id = ?",
            [$messageId, date('Y-m-d H:i:s'), $conversationId]
        );
    }

    public function directKey(int $firstUserId, int $secondUserId): string
    {
        return min($firstUserId, $secondUserId) . ':' . max($firstUserId, $secondUserId);
    }

    private function ensureMember(int $conversationId, int $userId, string $joinedAt): void
    {
        db()->query(
            "INSERT IGNORE INTO chat_members
                (conversation_id, user_id, role, last_read_message_id, muted_until, joined_at)
             VALUES (?, ?, 'member', NULL, NULL, ?)",
            [$conversationId, $userId, $joinedAt]
        );
    }
}
