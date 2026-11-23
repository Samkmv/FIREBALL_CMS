<?php

namespace Fireball\Commerce;

use FireballPluginCommerce as Plugin;

final class Reviews
{
    private const PURCHASE = "SELECT 1 FROM commerce_orders o
        INNER JOIN commerce_order_items i ON i.order_id = o.id
        WHERE o.user_id = ? AND i.product_id = ? AND o.status = 'completed' AND o.payment_status = 'paid'";
    public const PER_PAGE = 24;
    public const STATUSES = ['pending', 'published', 'hidden'];
    public const MIGRATION = '003_product_pages.sql';

    public function summary(int $productId): array
    {
        $rows = db()->query("SELECT rating, COUNT(*) AS total FROM commerce_reviews
            WHERE product_id = ? AND status = 'published' GROUP BY rating", [$productId])->get() ?: [];
        $counts = array_fill_keys(range(5, 1), 0);
        $sum = 0;
        foreach ($rows as $row) {
            $rating = (int)$row['rating'];
            $counts[$rating] = (int)$row['total'];
            $sum += $rating * $counts[$rating];
        }
        $total = array_sum($counts);
        return ['counts' => $counts, 'total' => $total, 'average' => $total ? $sum / $total : 0];
    }

    public function published(int $productId, int $page): array
    {
        return db()->query("SELECT r.rating, r.body, r.created_at, u.name AS author
            FROM commerce_reviews r INNER JOIN users u ON u.id = r.user_id
            WHERE r.product_id = ? AND r.status = 'published' ORDER BY r.id DESC LIMIT "
            . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), [$productId])->get() ?: [];
    }

    public function canWrite(int $productId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        return (bool)db()->query('SELECT EXISTS (' . self::PURCHASE . ')
            AND NOT EXISTS (SELECT 1 FROM commerce_reviews WHERE product_id = ? AND user_id = ?)',
            [$userId, $productId, $productId, $userId])->getColumn();
    }

    public function submit(int $productId, int $userId, array $input): void
    {
        $rating = Input::number($input['rating'] ?? null, 5);
        $body = Input::text($input['body'] ?? null, 2000, true);
        if ($rating < 1) {
            throw new ValidationError(Plugin::t('review_choose_rating'));
        }
        try {
            db()->query('INSERT INTO commerce_reviews (product_id, user_id, rating, body)
                SELECT ?, ?, ?, ? WHERE EXISTS (' . self::PURCHASE . ')', [$productId, $userId, $rating, $body, $userId, $productId]);
        } catch (\PDOException $error) {
            if ((int)($error->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationError(Plugin::t('review_duplicate'));
            }
            throw $error;
        }
        if (db()->rowCount() !== 1) {
            throw new ValidationError(Plugin::t('review_purchase_required'));
        }
    }

    public function queue(string $status, int $page): array
    {
        return db()->query('SELECT r.*, p.name AS product, p.slug, u.name AS author
            FROM commerce_reviews r INNER JOIN commerce_products p ON p.id = r.product_id
            INNER JOIN users u ON u.id = r.user_id WHERE r.status = ? ORDER BY r.id DESC LIMIT '
            . (self::PER_PAGE + 1) . ' OFFSET ' . (($page - 1) * self::PER_PAGE), [$status])->get() ?: [];
    }

    public function moderate(int $id, string $status): void
    {
        if (!in_array($status, ['published', 'hidden'], true)) {
            throw new ValidationError(Plugin::t('error_fields'));
        }
        db()->query('UPDATE commerce_reviews SET status = ? WHERE id = ?', [$status, $id]);
    }
}
