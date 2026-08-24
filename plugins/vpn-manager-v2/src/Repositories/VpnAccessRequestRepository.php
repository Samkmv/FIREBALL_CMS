<?php

namespace Fireball\VpnManagerV2\Repositories;

final class VpnAccessRequestRepository
{
    public function createOrFindPending(int $userId): array
    {
        $database = db();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }

        try {
            $user = $database->query(
                'SELECT id, name, login, email FROM users WHERE id = ? LIMIT 1 FOR UPDATE',
                [$userId]
            )->getOne();
            if (!is_array($user)) {
                throw new \RuntimeException('VPN access request user was not found.');
            }

            $activeSubscription = (bool)$database->query(
                "SELECT id FROM vpn_v2_subscriptions
                 WHERE user_id = ?
                   AND status IN ('active', 'partial_sync', 'sync_error')
                   AND starts_at <= ?
                   AND (expires_at IS NULL OR expires_at > ?)
                 LIMIT 1",
                [$userId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            )->getOne();
            if ($activeSubscription) {
                if ($ownsTransaction) {
                    $database->commit();
                }

                return ['created' => false, 'active_subscription' => true, 'request' => null, 'user' => $user];
            }

            $pending = $database->query(
                "SELECT * FROM vpn_v2_access_requests
                 WHERE user_id = ? AND status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                [$userId]
            )->getOne();
            if (is_array($pending)) {
                if ($ownsTransaction) {
                    $database->commit();
                }

                return ['created' => false, 'active_subscription' => false, 'request' => $pending, 'user' => $user];
            }

            $now = date('Y-m-d H:i:s');
            $database->query(
                "INSERT INTO vpn_v2_access_requests
                    (user_id, status, requested_at, created_at, updated_at)
                 VALUES (?, 'pending', ?, ?, ?)",
                [$userId, $now, $now, $now]
            );
            $request = [
                'id' => (int)$database->getInsertId(),
                'user_id' => $userId,
                'status' => 'pending',
                'requested_at' => $now,
            ];

            if ($ownsTransaction) {
                $database->commit();
            }

            return ['created' => true, 'active_subscription' => false, 'request' => $request, 'user' => $user];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function pendingForUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = db()->query(
            "SELECT id, user_id, status, requested_at
             FROM vpn_v2_access_requests
             WHERE user_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
            [$userId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function pending(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return db()->query(
            "SELECT r.id, r.user_id, r.status, r.requested_at,
                    u.name AS user_name, u.login AS user_login, u.email AS user_email
             FROM vpn_v2_access_requests r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.status = 'pending'
             ORDER BY r.requested_at ASC, r.id ASC
             LIMIT {$limit}"
        )->get() ?: [];
    }

    public function fulfillForUser(int $userId, int $adminId, int $subscriptionId): int
    {
        if ($userId <= 0 || $subscriptionId <= 0) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE vpn_v2_access_requests
             SET status = 'fulfilled', handled_at = ?, handled_by = ?, subscription_id = ?, updated_at = ?
             WHERE user_id = ? AND status = 'pending'",
            [$now, $adminId > 0 ? $adminId : null, $subscriptionId, $now, $userId]
        );

        return db()->rowCount();
    }

    public function adminRecipients(): array
    {
        return db()->query(
            "SELECT id, name, email FROM users
             WHERE role IN ('creator', 'admin')
             ORDER BY id ASC"
        )->get() ?: [];
    }
}
