<?php

namespace App\Services;

// FIREBALL_CHAT24_TYPING
final class ChatTypingService
{
    private const TTL_SECONDS = 4;
    private static ?bool $tableAvailable = null;

    public function setTyping(int $conversationId, int $userId, bool $isTyping): bool
    {
        if ($conversationId <= 0 || $userId <= 0 || !$this->tableAvailable()) {
            return false;
        }

        if (!(new ConversationService())->isMember($conversationId, $userId)) {
            return false;
        }

        if (!$isTyping) {
            db()->query(
                "DELETE FROM chat_typing_states
                 WHERE conversation_id = ? AND user_id = ?",
                [$conversationId, $userId]
            );
            return true;
        }

        $now = date('Y-m-d H:i:s');
        $typingUntil = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        db()->query(
            "INSERT INTO chat_typing_states
                (conversation_id, user_id, typing_until, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                typing_until = VALUES(typing_until),
                updated_at = VALUES(updated_at)",
            [$conversationId, $userId, $typingUntil, $now]
        );

        return true;
    }

    public function getTypingState(int $conversationId, int $userId): array
    {
        if ($conversationId <= 0 || $userId <= 0 || !$this->tableAvailable()) {
            return ['is_typing' => false, 'typing_until' => null];
        }

        $row = db()->query(
            "SELECT typing_until
             FROM chat_typing_states
             WHERE conversation_id = ?
               AND user_id = ?
               AND typing_until > NOW()
             LIMIT 1",
            [$conversationId, $userId]
        )->getOne();

        if (!$row) {
            return ['is_typing' => false, 'typing_until' => null];
        }

        return [
            'is_typing' => true,
            'typing_until' => (string)$row['typing_until'],
        ];
    }

    private function tableAvailable(): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        try {
            self::$tableAvailable = (bool)db()->query(
                "SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'chat_typing_states'"
            )->getColumn();
        } catch (\Throwable $exception) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }
}
