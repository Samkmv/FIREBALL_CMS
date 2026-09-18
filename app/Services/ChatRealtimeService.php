<?php
namespace App\Services;
// FIREBALL_CHAT2_FOUNDATION
final class ChatRealtimeService
{
    private const STREAM_LIFETIME_SECONDS = 25;
    private const DB_POLL_MICROSECONDS = 1500000;

    public function streamDirectConversation(int $currentUserId, int $contactId): never
    {
        $conversations = new ConversationService();
        $conversationId = $conversations->ensureDirectConversation($currentUserId, $contactId);
        if (!$conversations->isMember($conversationId, $currentUserId)) {
            http_response_code(403);
            exit;
        }

        (new ChatReceiptService())->markDelivered($conversationId, $currentUserId);
        @set_time_limit(0);
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        ignore_user_abort(false);
        while (ob_get_level() > 0) @ob_end_flush();

        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        echo "retry: 3000\n";
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        @flush();

        $startedAt = microtime(true);
        $lastRevision = '';
        while ((microtime(true) - $startedAt) < self::STREAM_LIFETIME_SECONDS) {
            if (connection_aborted()) break;
            $revision = $this->revision($conversationId, $contactId);
            if ($revision !== $lastRevision) {
                $json = json_encode([
                    'conversation_id' => $conversationId,
                    'contact_id' => $contactId,
                    'revision' => $revision,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                echo 'id: ' . $revision . "\n";
                echo "event: chat\n";
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

    private function revision(int $conversationId, int $contactId): string
    {
        $state = db()->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(MAX(id), 0) AS max_id,
                    COALESCE(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END), 0) AS read_total,
                    COALESCE(MAX(GREATEST(
                        UNIX_TIMESTAMP(created_at),
                        COALESCE(UNIX_TIMESTAMP(deleted_at), 0),
                        COALESCE(UNIX_TIMESTAMP(edited_at), 0),
                        COALESCE(UNIX_TIMESTAMP(delivered_at), 0)
                    )), 0) AS last_change
             FROM chat_messages WHERE conversation_id = ?",
            [$conversationId]
        )->getOne() ?: [];
        $presence = (int)db()->query(
            "SELECT COALESCE(UNIX_TIMESTAMP(last_seen_at), 0) FROM users WHERE id = ? LIMIT 1",
            [$contactId]
        )->getColumn();
        $raw = implode(':', [
            (int)($state['total'] ?? 0),
            (int)($state['max_id'] ?? 0),
            (int)($state['read_total'] ?? 0),
            (int)($state['last_change'] ?? 0),
            $presence,
        ]);
        return substr(hash('sha256', $raw), 0, 24);
    }
}
