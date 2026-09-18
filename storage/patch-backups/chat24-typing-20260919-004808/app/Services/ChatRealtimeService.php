<?php

namespace App\Services;

// FIREBALL_CHAT2_FOUNDATION
// FIREBALL_CHAT21_REACTIONS
// FIREBALL_CHAT22_RECEIPTS
// FIREBALL_CHAT23_REALTIME
final class ChatRealtimeService
{
    private const STREAM_LIFETIME_SECONDS = 55;
    private const DB_POLL_MICROSECONDS = 1200000;

    public function streamDirectConversation(int $currentUserId, int $contactId): never
    {
        $conversations = new ConversationService();
        $conversationId = $conversations->ensureDirectConversation($currentUserId, $contactId);

        if (!$conversations->isMember($conversationId, $currentUserId)) {
            http_response_code(403);
            exit;
        }

        $receipts = new ChatReceiptService();
        $receipts->markDelivered($conversationId, $currentUserId);

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
        $lastSnapshot = null;

        while ((microtime(true) - $startedAt) < self::STREAM_LIFETIME_SECONDS) {
            if (connection_aborted()) {
                break;
            }

            $snapshot = $this->snapshot($conversationId, $currentUserId, $contactId);

            if (
                (int)($snapshot['pending_delivery'] ?? 0) > 0
                && (
                    $lastSnapshot === null
                    || ($snapshot['messages_hash'] ?? '') !== ($lastSnapshot['messages_hash'] ?? '')
                )
            ) {
                $receipts->markDelivered($conversationId, $currentUserId);
                $snapshot = $this->snapshot($conversationId, $currentUserId, $contactId);
            }

            $changes = $this->detectChanges($lastSnapshot, $snapshot);

            if ($lastSnapshot === null || !empty($changes)) {
                $payload = [
                    'conversation_id' => $conversationId,
                    'contact_id' => $contactId,
                    'revision' => (string)$snapshot['revision'],
                    'changes' => $changes,
                    'presence' => $snapshot['presence'],
                ];

                $json = json_encode(
                    $payload,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );

                echo 'id: ' . $snapshot['revision'] . "\n";
                echo "event: chat\n";
                echo 'data: ' . $json . "\n\n";
                $lastSnapshot = $snapshot;
            } else {
                echo ": ping\n\n";
            }

            @flush();
            usleep(self::DB_POLL_MICROSECONDS);
        }

        exit;
    }

    private function snapshot(
        int $conversationId,
        int $currentUserId,
        int $contactId
    ): array {
        $messageState = db()->query(
            "SELECT
                COUNT(*) AS total,
                COALESCE(MAX(id), 0) AS max_id,
                COALESCE(MAX(GREATEST(
                    UNIX_TIMESTAMP(created_at),
                    COALESCE(UNIX_TIMESTAMP(deleted_at), 0),
                    COALESCE(UNIX_TIMESTAMP(edited_at), 0)
                )), 0) AS content_change,
                COALESCE(SUM(
                    CASE
                        WHEN receiver_id = ?
                         AND deleted_at IS NULL
                         AND delivered_at IS NULL
                        THEN 1 ELSE 0
                    END
                ), 0) AS pending_delivery
             FROM chat_messages
             WHERE conversation_id = ?",
            [$currentUserId, $conversationId]
        )->getOne() ?: [];

        $receiptState = db()->query(
            "SELECT
                COALESCE(SUM(CASE WHEN m.is_read = 1 THEN 1 ELSE 0 END), 0) AS read_total,
                COALESCE(SUM(CASE WHEN m.delivered_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS delivered_total,
                COUNT(cr.message_id) AS receipt_total,
                COALESCE(MAX(GREATEST(
                    COALESCE(UNIX_TIMESTAMP(m.delivered_at), 0),
                    COALESCE(UNIX_TIMESTAMP(cr.delivered_at), 0),
                    COALESCE(UNIX_TIMESTAMP(cr.read_at), 0)
                )), 0) AS last_change
             FROM chat_messages m
             LEFT JOIN chat_receipts cr
               ON cr.message_id = m.id
              AND cr.user_id = m.receiver_id
             WHERE m.conversation_id = ?
               AND m.deleted_at IS NULL",
            [$conversationId]
        )->getOne() ?: [];

        $reactionState = db()->query(
            "SELECT
                COUNT(*) AS total,
                COALESCE(MAX(r.id), 0) AS max_id,
                COALESCE(MAX(UNIX_TIMESTAMP(r.created_at)), 0) AS last_change
             FROM chat_reactions r
             INNER JOIN chat_messages m ON m.id = r.message_id
             WHERE m.conversation_id = ?
               AND m.deleted_at IS NULL",
            [$conversationId]
        )->getOne() ?: [];

        $presence = (new \App\Models\User())->getPresenceForChat($contactId);
        $presenceData = [
            'is_online' => !empty($presence['is_online']),
            'last_seen_at' => $presence['last_seen_at'] ?? null,
        ];

        $messagesHash = $this->fingerprint([
            (int)($messageState['total'] ?? 0),
            (int)($messageState['max_id'] ?? 0),
            (int)($messageState['content_change'] ?? 0),
        ]);

        $receiptsHash = $this->fingerprint([
            (int)($receiptState['read_total'] ?? 0),
            (int)($receiptState['delivered_total'] ?? 0),
            (int)($receiptState['receipt_total'] ?? 0),
            (int)($receiptState['last_change'] ?? 0),
        ]);

        $reactionsHash = $this->fingerprint([
            (int)($reactionState['total'] ?? 0),
            (int)($reactionState['max_id'] ?? 0),
            (int)($reactionState['last_change'] ?? 0),
        ]);

        $presenceHash = $this->fingerprint([
            $presenceData['is_online'] ? 1 : 0,
            (string)($presenceData['last_seen_at'] ?? ''),
        ]);

        return [
            'messages_hash' => $messagesHash,
            'receipts_hash' => $receiptsHash,
            'reactions_hash' => $reactionsHash,
            'presence_hash' => $presenceHash,
            'pending_delivery' => (int)($messageState['pending_delivery'] ?? 0),
            'presence' => $presenceData,
            'revision' => substr(hash(
                'sha256',
                implode(':', [
                    $messagesHash,
                    $receiptsHash,
                    $reactionsHash,
                    $presenceHash,
                ])
            ), 0, 24),
        ];
    }

    private function detectChanges(?array $previous, array $current): array
    {
        if ($previous === null) {
            return ['messages', 'reactions', 'receipts', 'presence'];
        }

        $changes = [];

        if (($previous['messages_hash'] ?? '') !== ($current['messages_hash'] ?? '')) {
            $changes[] = 'messages';
        }

        if (($previous['reactions_hash'] ?? '') !== ($current['reactions_hash'] ?? '')) {
            $changes[] = 'reactions';
        }

        if (($previous['receipts_hash'] ?? '') !== ($current['receipts_hash'] ?? '')) {
            $changes[] = 'receipts';
        }

        if (($previous['presence_hash'] ?? '') !== ($current['presence_hash'] ?? '')) {
            $changes[] = 'presence';
        }

        return $changes;
    }

    private function fingerprint(array $values): string
    {
        return substr(hash(
            'sha256',
            json_encode(
                $values,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) ?: ''
        ), 0, 16);
    }
}
