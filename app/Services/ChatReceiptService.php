<?php
namespace App\Services;
// FIREBALL_CHAT2_FOUNDATION
final class ChatReceiptService
{
    public function markConversationRead(int $conversationId, int $userId): void
    {
        if ($conversationId <= 0 || $userId <= 0) return;
        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT INTO chat_receipts (message_id, user_id, delivered_at, read_at)
             SELECT id, ?, ?, ? FROM chat_messages
             WHERE conversation_id = ? AND receiver_id = ? AND deleted_at IS NULL
             ON DUPLICATE KEY UPDATE
                delivered_at = COALESCE(chat_receipts.delivered_at, VALUES(delivered_at)),
                read_at = VALUES(read_at)",
            [$userId, $now, $now, $conversationId, $userId]
        );
        // FIREBALL_CHAT22_RECEIPTS
        // Read logically implies delivered; keep legacy message field synchronized.
        db()->query(
            "UPDATE chat_messages
             SET delivered_at = COALESCE(delivered_at, ?)
             WHERE conversation_id = ?
               AND receiver_id = ?
               AND deleted_at IS NULL
               AND delivered_at IS NULL",
            [$now, $conversationId, $userId]
        );
        $last = (int)db()->query(
            "SELECT COALESCE(MAX(id), 0) FROM chat_messages
             WHERE conversation_id = ? AND receiver_id = ? AND is_read = 1 AND deleted_at IS NULL",
            [$conversationId, $userId]
        )->getColumn();
        if ($last > 0) {
            db()->query(
                "UPDATE chat_members
                 SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
                 WHERE conversation_id = ? AND user_id = ?",
                [$last, $conversationId, $userId]
            );
        }
    }

    public function markDelivered(int $conversationId, int $userId): void
    {
        if ($conversationId <= 0 || $userId <= 0) return;
        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT INTO chat_receipts (message_id, user_id, delivered_at, read_at)
             SELECT id, ?, ?, NULL FROM chat_messages
             WHERE conversation_id = ? AND receiver_id = ? AND deleted_at IS NULL
             ON DUPLICATE KEY UPDATE
                delivered_at = COALESCE(chat_receipts.delivered_at, VALUES(delivered_at))",
            [$userId, $now, $conversationId, $userId]
        );
        db()->query(
            "UPDATE chat_messages
             SET delivered_at = ?
             WHERE conversation_id = ?
               AND receiver_id = ?
               AND deleted_at IS NULL
               AND delivered_at IS NULL",
            [$now, $conversationId, $userId]
        );
    }
}
