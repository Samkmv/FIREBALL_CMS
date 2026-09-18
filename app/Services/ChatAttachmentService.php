<?php
namespace App\Services;
// FIREBALL_CHAT2_FOUNDATION
final class ChatAttachmentService
{
    public function registerLegacyAttachment(int $messageId, ?array $attachment): void
    {
        if ($messageId <= 0 || empty($attachment) || empty($attachment['path'])) return;
        db()->query(
            "INSERT IGNORE INTO chat_attachments (message_id, path, name, mime_type, size, created_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $messageId,
                (string)$attachment['path'],
                $attachment['name'] ?? null,
                $attachment['type'] ?? null,
                isset($attachment['size']) ? (int)$attachment['size'] : null,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
