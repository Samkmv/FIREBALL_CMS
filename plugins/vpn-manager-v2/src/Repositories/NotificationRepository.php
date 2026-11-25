<?php

namespace Fireball\VpnManagerV2\Repositories;

use App\Models\User;
use App\Services\SchemaManifest;
use FBL\Localization;

final class NotificationRepository
{
    public function expirationAdministrators(): array
    {
        return db()->query(
            'SELECT id, name, login FROM users WHERE role IN (?, ?) ORDER BY name, login, id',
            [User::ROLE_CREATOR, User::ROLE_ADMIN]
        )->get() ?: [];
    }

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
            "INSERT INTO vpn_v2_notifications
                (subscription_id, user_id, notification_type, occurrence_key, channel, status,
                 attempts, scheduled_for, sent_at, last_error, created_at, updated_at)
             SELECT s.id, u.id, ?, ?, ?, 'pending', 0, ?, NULL, NULL, ?, ?
             FROM users u INNER JOIN vpn_v2_subscriptions s ON s.id = ? WHERE u.id = ?
             ON DUPLICATE KEY UPDATE
                last_error = IF(vpn_v2_notifications.status = 'skipped', NULL, vpn_v2_notifications.last_error),
                attempts = IF(vpn_v2_notifications.status = 'skipped', 0, vpn_v2_notifications.attempts),
                updated_at = IF(vpn_v2_notifications.status = 'skipped', VALUES(updated_at), vpn_v2_notifications.updated_at),
                status = IF(vpn_v2_notifications.status = 'skipped', 'pending', vpn_v2_notifications.status)",
            [
                $this->token($type, 80),
                $this->token($occurrenceKey, 120),
                $this->channel($channel),
                $scheduledFor,
                $now,
                $now,
                $subscriptionId,
                $userId,
            ]
        );

        return db()->rowCount() > 0;
    }

    public function pending(?array $types = null, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $params = [date('Y-m-d H:i:s')];
        $typeSql = '';
        if ($types !== null) {
            $types = array_values(array_unique(array_filter(array_map(
                fn(mixed $type): string => $this->token((string)$type, 80),
                $types
            ))));
            if ($types !== []) {
                $typeSql = ' AND n.notification_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
                $params = array_merge($params, $types);
            }
        }
        $columns = $this->recipientLocaleColumns();
        $locale = in_array('locale', $columns, true) ? 'u.locale' : 'NULL';
        $adminLocale = in_array('admin_locale', $columns, true) ? 'u.admin_locale' : 'NULL';

        return db()->query(
            "SELECT n.id, n.subscription_id, n.user_id, n.notification_type,
                    n.occurrence_key, n.channel, n.status, n.attempts, n.scheduled_for,
                    u.email AS user_email, {$locale} AS recipient_locale, {$adminLocale} AS recipient_admin_locale,
                    s.status AS subscription_status, s.expires_at,
                    COALESCE(NULLIF(TRIM(s.client_display_name), ''),
                             NULLIF(TRIM(s.manual_customer_name), ''),
                             NULLIF(TRIM(owner.login), ''), NULLIF(TRIM(owner.name), '')) AS subscription_label
             FROM vpn_v2_notifications n
             INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
             INNER JOIN users u ON u.id = n.user_id
             LEFT JOIN users owner ON owner.id = s.user_id
             WHERE n.status = 'pending' AND n.scheduled_for <= ?{$typeSql}
             ORDER BY n.id ASC LIMIT {$limit}",
            $params
        )->get() ?: [];
    }

    private function recipientLocaleColumns(): array
    {
        if (!class_exists(Localization::class)) {
            return [];
        }
        $columns = class_exists(SchemaManifest::class) ? SchemaManifest::columns('users') : [];
        if (in_array('locale', $columns, true) && in_array('admin_locale', $columns, true)) {
            return $columns;
        }

        return array_column(db()->query("SHOW COLUMNS FROM users WHERE Field IN ('locale', 'admin_locale')")->get() ?: [], 'Field');
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

    public function markSkipped(int $id): void
    {
        db()->query(
            "UPDATE vpn_v2_notifications SET status = 'skipped', last_error = NULL, updated_at = ?
             WHERE id = ? AND status = 'sending'",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function retryFailed(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $this->recoverStaleSending();

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

    /**
     * Return interrupted deliveries to failed so the normal retry path can pick them up.
     */
    public function recoverStaleSending(int $minutes = 15): int
    {
        $minutes = max(5, min(120, $minutes));

        db()->query(
            "UPDATE vpn_v2_notifications
             SET status = 'failed',
                 last_error = COALESCE(last_error, 'delivery_interrupted'),
                 updated_at = ?
             WHERE status = 'sending'
               AND attempts < 5
               AND updated_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)",
            [date('Y-m-d H:i:s')]
        );

        return db()->rowCount();
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
