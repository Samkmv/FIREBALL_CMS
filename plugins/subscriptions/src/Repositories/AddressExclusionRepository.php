<?php

namespace Fireball\Subscriptions\Repositories;

use Fireball\Subscriptions\Services\SubscriptionEligibilityService;
use Fireball\Subscriptions\Services\SubscriptionService;
use Fireball\Subscriptions\Support\AddressNormalizer;

final class AddressExclusionRepository
{
    public function active(): array
    {
        return db()->query(
            'SELECT * FROM subscription_address_exclusions WHERE is_active = 1 ORDER BY normalized_house IS NOT NULL DESC, normalized_apartment IS NOT NULL DESC, id ASC'
        )->get() ?: [];
    }

    public function find(int $id): ?array
    {
        $row = db()->query('SELECT * FROM subscription_address_exclusions WHERE id = ? LIMIT 1', [$id])->getOne();

        return is_array($row) ? $row : null;
    }

    public function paginated(string $search, int $limit, int $offset): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE e.address LIKE ? OR e.normalized_address LIKE ? OR e.comment LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $total = $this->count($search);
        $rows = db()->query(
            "SELECT e.*, COUNT(p.id) AS matched_users_count
             FROM subscription_address_exclusions e
             LEFT JOIN subscription_profiles p ON p.matched_address_exclusion_id = e.id AND p.address_excluded = 1
             {$where}
             GROUP BY e.id
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT {$offset}, {$limit}",
            $params
        )->get() ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['is_active'] = (bool)$row['is_active'];
            $row['matched_users_count'] = (int)$row['matched_users_count'];
        }
        unset($row);

        return ['items' => $rows, 'total' => $total];
    }

    public function count(string $search = ''): int
    {
        $search = trim($search);
        if ($search === '') {
            return (int)db()->query('SELECT COUNT(*) FROM subscription_address_exclusions')->getColumn();
        }

        $like = '%' . $search . '%';

        return (int)db()->query(
            'SELECT COUNT(*) FROM subscription_address_exclusions WHERE address LIKE ? OR normalized_address LIKE ? OR comment LIKE ?',
            [$like, $like, $like]
        )->getColumn();
    }

    public function save(array $data, ?int $id, int $actorId): int
    {
        $address = mb_substr(trim((string)($data['address'] ?? '')), 0, 500);
        $parts = (new AddressNormalizer())->normalizeRule($address);
        if ($address === '' || (string)$parts['normalized_street'] === '') {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_exclusion_address_required'));
        }

        $duplicateParams = [(string)$parts['normalized_address']];
        $duplicateWhere = '';
        if ($id !== null) {
            $duplicateWhere = ' AND id <> ?';
            $duplicateParams[] = $id;
        }
        if (db()->query(
            'SELECT id FROM subscription_address_exclusions WHERE normalized_address = ?' . $duplicateWhere . ' LIMIT 1',
            $duplicateParams
        )->getOne()) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_exclusion_duplicate'));
        }

        $existing = $id !== null ? $this->find($id) : null;
        if ($id !== null && !$existing) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_exclusion_not_found'));
        }
        $now = date('Y-m-d H:i:s');
        $values = [
            $address,
            (string)$parts['normalized_address'],
            (string)$parts['rule_type'],
            $parts['street_type'],
            (string)$parts['normalized_street'],
            $parts['normalized_house'],
            $parts['normalized_apartment'],
            mb_substr(trim((string)($data['comment'] ?? '')), 0, 10000),
            !empty($data['is_active']) ? 1 : 0,
        ];

        db()->beginTransaction();
        try {
            if ($existing) {
                db()->query(
                    'UPDATE subscription_address_exclusions SET address = ?, normalized_address = ?, rule_type = ?, street_type = ?, normalized_street = ?, normalized_house = ?, normalized_apartment = ?, comment = ?, is_active = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                    [...$values, $actorId ?: null, $now, $id]
                );
                $eventKey = 'address_exclusion.updated';
            } else {
                db()->query(
                    'INSERT INTO subscription_address_exclusions (address, normalized_address, rule_type, street_type, normalized_street, normalized_house, normalized_apartment, comment, is_active, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [...$values, $actorId ?: null, $actorId ?: null, $now, $now]
                );
                $id = (int)db()->getInsertId();
                $eventKey = 'address_exclusion.created';
            }
            (new SubscriptionService())->event($eventKey, null, null, null, null, null, [
                'address_exclusion_id' => $id,
                'rule_type' => (string)$parts['rule_type'],
                'is_active' => !empty($data['is_active']),
            ], $actorId ?: null);
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }

        $this->refreshProfileMatches();

        return (int)$id;
    }

    public function delete(int $id, int $actorId): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_exclusion_not_found'));
        }

        db()->beginTransaction();
        try {
            db()->query(
                'UPDATE subscription_profiles SET address_excluded = 0, matched_address_exclusion_id = NULL, address_checked_at = ? WHERE matched_address_exclusion_id = ?',
                [date('Y-m-d H:i:s'), $id]
            );
            db()->query('DELETE FROM subscription_address_exclusions WHERE id = ?', [$id]);
            (new SubscriptionService())->event('address_exclusion.deleted', null, null, null, null, null, [
                'address_exclusion_id' => $id,
                'rule_type' => (string)$existing['rule_type'],
            ], $actorId ?: null);
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }

        $this->refreshProfileMatches();
    }

    public function matchedUsers(int $id, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return db()->query(
            "SELECT u.id, u.name, u.email, p.street, p.house, p.apartment
             FROM subscription_profiles p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.address_excluded = 1 AND p.matched_address_exclusion_id = ?
             ORDER BY u.name, u.email
             LIMIT {$limit}",
            [$id]
        )->get() ?: [];
    }

    private function refreshProfileMatches(): void
    {
        try {
            (new SubscriptionEligibilityService($this))->refreshAllProfiles();
        } catch (\Throwable $exception) {
            log_error_details('Subscription address eligibility refresh failed', [], $exception);
        }
    }
}
