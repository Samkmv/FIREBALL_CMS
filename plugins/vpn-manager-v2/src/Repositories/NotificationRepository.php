<?php

namespace Fireball\VpnManagerV2\Repositories;

final class NotificationRepository
{
    public function enqueue(
        int $subscriptionId,
        int $userId,
        string $type,
        string $occurrenceKey,
        string $channel,
        string $scheduledFor
    ): bool {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT IGNORE INTO vpn_v2_notifications
                (subscription_id, user_id, notification_type, occurrence_key, channel, status,
                 attempts, scheduled_for, sent_at, last_error, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'pending', 0, ?, NULL, NULL, ?, ?)",
            [
                $subscriptionId,
                $userId,
                $this->token($type, 80),
                $this->token($occurrenceKey, 120),
                $this->channel($channel),
                $scheduledFor,
                $now,
                $now,
            ]
        );

        return db()->rowCount() === 1;
    }

    public function pending(?array $types = null, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $params = [];
        $typeSql = '';
        if ($types !== null) {
            $types = array_values(array_unique(array_filter(array_map(
                fn(mixed $type): string => $this->token((string)$type, 80),
                $types
            ))));
            if ($types !== []) {
                $typeSql = ' AND n.notification_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
                $params = $types;
            }
        }

        return db()->query(
            "SELECT n.id, n.subscription_id, n.user_id, n.notification_type,
                    n.occurrence_key, n.channel, n.status, n.attempts, n.scheduled_for,
                    u.email AS user_email
             FROM vpn_v2_notifications n
             INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
             INNER JOIN users u ON u.id = n.user_id
             WHERE n.status = 'pending' AND n.scheduled_for <= NOW(){$typeSql}
             ORDER BY n.id ASC LIMIT {$limit}",
            $params
        )->get() ?: [];
    }

    public function claim(int $id): bool
    {
        db()->query(
            "UPDATE vpn_v2_notifications
             SET status = 'sending', attempts = attempts + 1, updated_at = ?
             WHERE id = ? AND status = 'pending'",
            [date('Y-m-d H:i:s'), $id]
        );

        return db()->rowCount() === 1;
    }

    public function markSent(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_notifications
             SET status = 'sent', sent_at = ?, last_error = NULL, updated_at = ?
             WHERE id = ? AND status = 'sending'",
            [$now, $now, $id]
        );
    }

    public function markFailed(int $id, string $safeError): void
    {
        db()->query(
            "UPDATE vpn_v2_notifications
             SET status = 'failed', last_error = ?, updated_at = ?
             WHERE id = ? AND status = 'sending'",
            [mb_substr(trim($safeError), 0, 1000), date('Y-m-d H:i:s'), $id]
        );
    }

    public function retryFailed(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $rows = db()->query(
            "SELECT id FROM vpn_v2_notifications
             WHERE status = 'failed' AND attempts < 5
             ORDER BY updated_at ASC, id ASC LIMIT {$limit}"
        )->get() ?: [];
        $retried = 0;
        foreach ($rows as $row) {
            db()->query(
                "UPDATE vpn_v2_notifications
                 SET status = 'pending', last_error = NULL, updated_at = ?
                 WHERE id = ? AND status = 'failed' AND attempts < 5",
                [date('Y-m-d H:i:s'), (int)$row['id']]
            );
            $retried += db()->rowCount() === 1 ? 1 : 0;
        }

        return $retried;
    }

    private function channel(string $channel): string
    {
        return in_array($channel, ['profile', 'email'], true) ? $channel : 'profile';
    }

    private function token(string $value, int $max): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || preg_match('/^[a-z0-9_.:-]+$/', $value) !== 1) {
            $value = hash('sha256', $value);
        }

        return mb_substr($value, 0, $max);
    }
}
