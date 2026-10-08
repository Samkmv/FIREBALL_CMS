<?php

namespace App\Services;

// FIREBALL_CHAT30_GROUPS
final class GroupChatRealtimeService
{
    private const STREAM_LIFETIME_SECONDS = 55;
    private const DB_POLL_MICROSECONDS = 1200000;

    public function stream(int $currentUserId, int $conversationId): never
    {
        $groups = new GroupChatService();

        if (!$groups->isMember($conversationId, $currentUserId)) {
            http_response_code(403);
            exit;
        }

        $groups->markDelivered($conversationId, $currentUserId);

        @set_time_limit(0);
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        ignore_user_abort(false);

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        echo "retry: 2000\n";
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        @flush();

        $startedAt = microtime(true);
        $lastRevision = '';

        while ((microtime(true) - $startedAt) < self::STREAM_LIFETIME_SECONDS) {
            if (connection_aborted() || !$groups->isMember($conversationId, $currentUserId)) {
                break;
            }

            $pending = $this->pendingDelivery(
                $conversationId,
                $currentUserId
            );

            if ($pending > 0) {
                $groups->markDelivered($conversationId, $currentUserId);
            }

            $revision = $this->revision($conversationId);

            if ($revision !== $lastRevision) {
                $json = json_encode([
                    'conversation_id' => $conversationId,
                    'revision' => $revision,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                echo 'id: ' . $revision . "\n";
                echo "event: group-chat\n";
                echo 'data: ' . $json . "\n\n";
                $lastRevision = $revision;
            } else {
                echo ": ping\n\n";
            }

            @flush();
            usleep(self::DB_POLL_MICROSECONDS);
        }

        exit;
    }

    private function revision(int $conversationId): string
    {
        $revision = db()->query('SELECT revision FROM chat_conversations WHERE id = ?', [$conversationId])->getColumn();
        $receipts = db()->query('SELECT COUNT(*) AS total, SUM(cr.read_at IS NOT NULL) AS read_count FROM chat_receipts cr JOIN chat_messages m ON m.id = cr.message_id WHERE m.conversation_id = ?', [$conversationId])->getOne();
        $typing = db()->query('SELECT t.user_id FROM chat_typing_states t JOIN chat_members cm ON cm.user_id = t.user_id AND cm.conversation_id = t.conversation_id WHERE t.conversation_id = ? AND t.typing_until > NOW() ORDER BY t.user_id', [$conversationId])->get() ?: [];
        return substr(hash('sha256', json_encode([$revision, $receipts, $typing])), 0, 24);
    }

    private function pendingDelivery(
        int $conversationId,
        int $userId
    ): int {
        return (int)db()->query(
            "SELECT COUNT(*)
             FROM chat_messages m
             LEFT JOIN chat_receipts r
               ON r.message_id = m.id
              AND r.user_id = ?
             WHERE m.conversation_id = ?
               AND m.sender_id <> ?
               AND m.deleted_at IS NULL
               AND r.delivered_at IS NULL",
            [$userId, $conversationId, $userId]
        )->getColumn();
    }
}
