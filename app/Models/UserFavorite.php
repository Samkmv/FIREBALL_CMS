<?php
namespace App\Models;

use App\Services\SchemaManifest;
use FBL\Pagination;

/** Generic storage; published posts are the first supported entity policy. */
final class UserFavorite
{
    public static function available(): bool { return SchemaManifest::hasTable('user_favorites'); }

    public function add(int $userId, string $type, int $id): bool
    {
        $this->assertEntity($userId, $type, $id);
        // INSERT SELECT also checks publication at write time, not just in a stale form.
        db()->query("INSERT INTO user_favorites (user_id, entity_type, entity_id, created_at)
            SELECT ?, 'post', id, ? FROM posts WHERE id = ? AND is_published = 1
            ON DUPLICATE KEY UPDATE entity_type = 'post'", [$userId, date('Y-m-d H:i:s'), $id]);
        return (bool)db()->query("SELECT f.id FROM user_favorites f JOIN posts p ON p.id = f.entity_id
            WHERE f.user_id = ? AND f.entity_type = 'post' AND f.entity_id = ? AND p.is_published = 1 LIMIT 1", [$userId, $id])->getColumn();
    }

    public function remove(int $userId, string $type, int $id): void
    {
        $this->assertEntity($userId, $type, $id);
        db()->query('DELETE FROM user_favorites WHERE user_id = ? AND entity_type = ? AND entity_id = ?', [$userId, $type, $id]);
    }

    /** Batch state lookup for a whole card list, never one query per post. */
    public function ids(int $userId, string $type, array $ids): array
    {
        if ($userId <= 0 || $type !== 'post' || $ids === []) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        return array_map('intval', array_column(db()->query("SELECT entity_id FROM user_favorites
            WHERE user_id = ? AND entity_type = ? AND entity_id IN ({$marks})", [$userId, $type, ...$ids])->get() ?: [], 'entity_id'));
    }

    /** Add viewer-specific state after public access filters, never to shared post caches. */
    public function withPostStates(array $posts, int $userId): array
    {
        if ($posts === []) return [];
        $ready = self::available();
        $saved = $ready && $userId > 0 ? array_fill_keys($this->ids($userId, 'post', array_column($posts, 'id')), true) : [];
        foreach ($posts as &$post) {
            $post['favorite_ready'] = $ready;
            $post['favorite_saved'] = isset($saved[(int)($post['id'] ?? 0)]);
        }
        unset($post);
        return $posts;
    }

    public function paginatedPosts(int $userId): array
    {
        if ($userId <= 0) throw new \InvalidArgumentException('Authenticated favorite owner required.');
        $join = "FROM user_favorites f JOIN posts p ON p.id = f.entity_id
            LEFT JOIN post_categories c ON c.id = p.category_id
            WHERE f.user_id = ? AND f.entity_type = 'post' AND p.is_published = 1";
        $total = (int)db()->query("SELECT COUNT(*) {$join}", [$userId])->getColumn();
        $pagination = new Pagination($total, 20);
        $offset = $pagination->getOffset();
        $category = (new Category())->nameSql('c');
        $items = db()->query("SELECT p.id, p.title, p.slug, p.excerpt, p.image, p.published_at,
            p.hide_placeholder_image, COALESCE({$category}, p.category, '') AS category
            {$join} ORDER BY f.created_at DESC, f.id DESC LIMIT 20 OFFSET {$offset}", [$userId])->get() ?: [];
        foreach ($items as &$post) {
            $post['url'] = base_href('/posts/' . $post['slug']);
            $post['show_post_image'] = (int)$post['hide_placeholder_image'] !== 1 && trim((string)$post['image']) !== '';
        }
        unset($post);
        return ['items' => $items, 'total' => $total, 'pagination' => $pagination];
    }

    private function assertEntity(int $userId, string $type, int $id): void
    {
        if ($userId <= 0 || $type !== 'post' || $id <= 0) throw new \InvalidArgumentException(return_translation('account_favorite_invalid'));
    }
}
