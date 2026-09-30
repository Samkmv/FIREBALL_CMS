<?php

namespace Fireball\Subscriptions\Repositories;

final class BusinessRepository
{
    public const SOCIALS = ['vk', 'telegram', 'youtube', 'instagram', 'facebook', 'ok'];

    public function canManage(int $userId): bool
    {
        if ($userId <= 0) { return false; }
        // Check every active subscription: a household subscription may coexist with a business plan.
        $now = date('Y-m-d H:i:s');
        return (bool)db()->query("SELECT s.id FROM subscriptions s
            INNER JOIN subscription_plan_permissions p ON p.plan_id = s.plan_id
            WHERE s.user_id = ? AND p.permission_key = 'business.manage'
            AND p.permission_value IN ('true', '1') AND s.archived_at IS NULL AND s.starts_at <= ?
            AND ((s.status IN ('active', 'cancelled') AND (s.ends_at IS NULL OR s.ends_at > ?))
            OR (s.status = 'grace_period' AND COALESCE(s.grace_ends_at, s.ends_at) > ?)) LIMIT 1",
            [$userId, $now, $now, $now])->getColumn();
    }

    public function businessSubscription(int $userId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $row = db()->query("SELECT s.*, plan.name AS plan_name FROM subscriptions s
            INNER JOIN subscription_plans plan ON plan.id=s.plan_id
            INNER JOIN subscription_plan_permissions p ON p.plan_id=s.plan_id
            WHERE s.user_id=? AND p.permission_key='business.manage' AND p.permission_value IN ('true','1')
            AND s.archived_at IS NULL AND s.starts_at<=?
            AND ((s.status IN ('active','cancelled') AND (s.ends_at IS NULL OR s.ends_at>?))
            OR (s.status='grace_period' AND COALESCE(s.grace_ends_at,s.ends_at)>?))
            ORDER BY s.id DESC LIMIT 1", [$userId,$now,$now,$now])->getOne();
        return is_array($row) ? $row : null;
    }

    public function saveCameraSettings(int $userId, array $data): void
    {
        $page = $this->owned($userId);
        db()->query('UPDATE subscription_business_pages SET camera_title=?, show_camera=?, updated_at=? WHERE id=? AND user_id=?',
            [$this->text($data, 'camera_title', 190), !empty($data['show_camera']) ? 1 : 0, date('Y-m-d H:i:s'), $page['id'], $userId]);
    }

    public function forUser(int $userId): ?array
    {
        $row = db()->query('SELECT * FROM subscription_business_pages WHERE user_id = ?', [$userId])->getOne();
        return is_array($row) ? $row : null;
    }

    public function find(int $id, bool $public = false): ?array
    {
        $row = db()->query('SELECT * FROM subscription_business_pages WHERE id = ?' . ($public ? ' AND is_published = 1' : ''), [$id])->getOne();
        return is_array($row) ? $row : null;
    }

    public static function slugFromName(string $name): string
    {
        $slug = trim(substr(make_slug($name, 'business'), 0, 180), '-');
        return ctype_digit($slug) ? 'business-' . $slug : $slug;
    }

    public static function publicPath(array $page): string
    {
        return '/business/' . (!empty($page['slug']) ? $page['slug'] : $page['id']);
    }

    public function findBySlug(string $slug, bool $public = false): ?array
    {
        $row = db()->query('SELECT * FROM subscription_business_pages WHERE slug=?' . ($public ? ' AND is_published=1' : ''), [$slug])->getOne();
        return is_array($row) ? $row : null;
    }

    public function backfillSlugs(): void
    {
        foreach (db()->query('SELECT id,user_id,name FROM subscription_business_pages WHERE slug IS NULL ORDER BY id')->get() ?: [] as $page) {
            db()->query('UPDATE subscription_business_pages SET slug=? WHERE id=? AND slug IS NULL',
                [$this->uniqueSlug($page['name'], (int)$page['id']), $page['id']]);
        }
    }

    private function uniqueSlug(string $name, int $excludeId = 0): string
    {
        $base = self::slugFromName($name);
        $candidate = $base;
        $suffix = 2;
        while (db()->query('SELECT id FROM subscription_business_pages WHERE slug=? AND id<>?', [$candidate, $excludeId])->getColumn()) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }

    public function save(int $userId, array $data, array $images = []): int
    {
        $this->requireAccess($userId);
        $page = $this->forUser($userId);
        $name = $this->text($data, 'name', 190, true);
        $email = $this->text($data, 'email', 190);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->invalid(); }
        $socials = [];
        foreach (self::SOCIALS as $key) { $socials[$key] = self::url((string)($data['socials'][$key] ?? '')); }
        $slug = $page && $page['name'] === $name && !empty($page['slug']) ? $page['slug'] : $this->uniqueSlug($name, (int)($page['id'] ?? 0));
        $values = [$name, $slug, $this->text($data, 'description', 10000), $this->text($data, 'address', 255),
            $this->text($data, 'phone', 50), $email, self::url((string)($data['website'] ?? '')),
            $this->text($data, 'hours', 255), json_encode($socials, JSON_UNESCAPED_UNICODE),
            $images['avatar'] ?? (!empty($data['remove_avatar']) ? '' : ($page['avatar'] ?? '')),
            $images['cover'] ?? (!empty($data['remove_cover']) ? '' : ($page['cover'] ?? '')),
            !empty($data['is_published']) ? 1 : 0, $this->text($data, 'camera_title', 190), !empty($data['show_camera']) ? 1 : 0];
        $now = date('Y-m-d H:i:s');
        if ($page) {
            db()->query('UPDATE subscription_business_pages SET name=?, slug=?, description=?, address=?, phone=?, email=?, website=?, hours=?, socials_json=?, avatar=?, cover=?, is_published=?, camera_title=?, show_camera=?, updated_at=? WHERE id=? AND user_id=?', [...$values, $now, $page['id'], $userId]);
            return (int)$page['id'];
        }
        db()->query('INSERT INTO subscription_business_pages (name, slug, description, address, phone, email, website, hours, socials_json, avatar, cover, is_published, camera_title, show_camera, created_at, updated_at, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$values, $now, $now, $userId]);
        return (int)db()->getInsertId();
    }

    public function posts(int $businessId, int $offset = 0, ?string $kind = null): array
    {
        $offset = max(0, $offset);
        return db()->query('SELECT * FROM subscription_business_posts WHERE business_id = ?' . ($kind !== null ? ' AND kind=?' : '') . " ORDER BY id DESC LIMIT 20 OFFSET {$offset}", $kind !== null ? [$businessId, $kind] : [$businessId])->get() ?: [];
    }

    public function countPosts(int $id, ?string $kind = null): int
    {
        return (int)db()->query('SELECT COUNT(*) FROM subscription_business_posts WHERE business_id=?' . ($kind !== null ? ' AND kind=?' : ''), $kind !== null ? [$id, $kind] : [$id])->getColumn();
    }

    public function savePost(int $userId, array $data, ?string $image = null): void
    {
        $page = $this->owned($userId);
        $id = (int)($data['post_id'] ?? 0);
        $old = $id ? db()->query('SELECT * FROM subscription_business_posts WHERE id=? AND business_id=?', [$id, $page['id']])->getOne() : null;
        if ($id && !$old) { throw new \DomainException('Forbidden'); }
        $kind = (string)($data['kind'] ?? 'news');
        if (!in_array($kind, ['news', 'promotion', 'photo'], true)) { $this->invalid(); }
        $title = $this->text($data, 'title', 190, true);
        $body = $this->text($data, 'body', 10000);
        $image ??= !empty($data['remove_image']) ? '' : ($old['image'] ?? '');
        if ($kind === 'photo' && $image === '') { $this->invalid(); }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            db()->query('UPDATE subscription_business_posts SET kind=?, title=?, body=?, image=?, updated_at=? WHERE id=? AND business_id=?', [$kind, $title, $body, $image, $now, $id, $page['id']]);
        } else {
            db()->query('INSERT INTO subscription_business_posts (business_id, kind, title, body, image, created_at, updated_at) VALUES (?,?,?,?,?,?,?)', [$page['id'], $kind, $title, $body, $image, $now, $now]);
        }
    }

    public function deletePost(int $userId, int $id): void
    {
        $page = $this->owned($userId);
        db()->query('DELETE FROM subscription_business_posts WHERE id=? AND business_id=?', [$id, $page['id']]);
    }

    public function reviews(int $id, bool $admin = false, int $offset = 0): array
    {
        $offset = max(0, $offset);
        return db()->query('SELECT r.*, u.name AS author FROM subscription_business_reviews r INNER JOIN users u ON u.id=r.user_id WHERE r.business_id=?' . ($admin ? '' : ' AND r.is_hidden=0') . " ORDER BY r.id DESC LIMIT 20 OFFSET {$offset}", [$id])->get() ?: [];
    }

    public function countReviews(int $id, bool $admin = false): int
    {
        return (int)db()->query('SELECT COUNT(*) FROM subscription_business_reviews WHERE business_id=?' . ($admin ? '' : ' AND is_hidden=0'), [$id])->getColumn();
    }

    public function rating(int $id): array
    {
        return db()->query('SELECT COUNT(*) AS total, AVG(rating) AS average FROM subscription_business_reviews WHERE business_id=? AND is_hidden=0', [$id])->getOne() ?: ['total'=>0, 'average'=>null];
    }

    public function reviewForUser(int $id, int $userId): ?array
    {
        $row = db()->query('SELECT * FROM subscription_business_reviews WHERE business_id=? AND user_id=?', [$id, $userId])->getOne();
        return is_array($row) ? $row : null;
    }

    public function saveReview(int $id, int $userId, array $data): void
    {
        $page = $this->find($id, true);
        if (!$page || $userId <= 0 || (int)$page['user_id'] === $userId) { throw new \DomainException('Forbidden'); }
        $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) { $this->invalid(); }
        $body = $this->text($data, 'body', 3000);
        $now = date('Y-m-d H:i:s');
        $old = $this->reviewForUser($id, $userId);
        // An edit does not undo moderation or the owner's reply.
        if ($old) {
            db()->query('UPDATE subscription_business_reviews SET rating=?, body=?, updated_at=? WHERE id=? AND user_id=?', [$rating, $body, $now, $old['id'], $userId]);
        } else {
            db()->query("INSERT INTO subscription_business_reviews (business_id,user_id,rating,body,reply,created_at,updated_at) VALUES (?,?,?,?,'',?,?)", [$id, $userId, $rating, $body, $now, $now]);
        }
    }

    public function deleteReview(int $id, int $userId): void
    {
        db()->query('DELETE FROM subscription_business_reviews WHERE business_id=? AND user_id=?', [$id, $userId]);
    }

    public function reply(int $userId, int $reviewId, array $data): void
    {
        $page = $this->owned($userId);
        db()->query('UPDATE subscription_business_reviews SET reply=?, updated_at=? WHERE id=? AND business_id=?', [$this->text($data, 'reply', 3000), date('Y-m-d H:i:s'), $reviewId, $page['id']]);
    }

    public function assignCamera(int $id, ?int $cameraId): void
    {
        if (!$this->find($id)) { $this->invalid(); }
        if ($cameraId !== null && (!class_exists('FireballPluginCameraManager') || !\FireballPluginCameraManager::camera($cameraId))) { $this->invalid(); }
        // Unique index prevents accidentally assigning the same camera to two businesses.
        db()->query('UPDATE subscription_business_pages SET camera_id=?, updated_at=? WHERE id=?', [$cameraId, date('Y-m-d H:i:s'), $id]);
    }

    public function saveCameraLinks(int $id, array $data): void
    {
        if (!$this->find($id)) { $this->invalid(); }
        try { $url = self::url($this->text($data, 'camera_url', 500)); }
        catch (\InvalidArgumentException) { throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_camera_url_invalid')); }
        try { $poster = self::url($this->text($data, 'camera_poster', 500)); }
        catch (\InvalidArgumentException) { throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_camera_poster_invalid')); }
        if ($url === '' && $poster !== '') { throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_camera_url_required')); }
        // A direct source replaces the old manager binding. Clearing both fields disconnects the camera.
        db()->query('UPDATE subscription_business_pages SET camera_url=?, camera_poster=?, camera_id=NULL, updated_at=? WHERE id=?',
            [$url, $poster, date('Y-m-d H:i:s'), $id]);
    }

    public static function url(string $url): string
    {
        $url = trim($url);
        if ($url === '') { return ''; }
        $parts = parse_url($url);
        if (mb_strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_invalid'));
        }
        return $url;
    }

    private function owned(int $userId): array
    {
        $this->requireAccess($userId);
        return $this->forUser($userId) ?? throw new \DomainException('Forbidden');
    }

    private function requireAccess(int $userId): void
    {
        if (!$this->canManage($userId)) { throw new \DomainException('Forbidden'); }
    }

    private function text(array $data, string $key, int $max, bool $required = false): string
    {
        if (isset($data[$key]) && !is_scalar($data[$key])) { $this->invalid(); }
        $value = trim((string)($data[$key] ?? ''));
        if (($required && $value === '') || mb_strlen($value) > $max) { $this->invalid(); }
        return $value;
    }

    private function invalid(): never
    {
        throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('business_invalid'));
    }
}
