<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Repositories\ContentRuleRepository;

final class AccessService
{
    private array $subscriptionCache = [];
    private array $permissionCache = [];
    private array $roleCache = [];

    public function activeSubscription(int $userId, bool $lock = false): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        if (!$lock && array_key_exists($userId, $this->subscriptionCache)) {
            return $this->subscriptionCache[$userId];
        }
        $now = date('Y-m-d H:i:s');
        $suffix = $lock ? ' FOR UPDATE' : '';
        $row = db()->query(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.grace_period_days
             FROM subscriptions s
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.user_id = ?
               AND s.archived_at IS NULL
               AND (
                    (s.status IN ('active', 'cancelled') AND s.starts_at <= ? AND (s.ends_at IS NULL OR s.ends_at > ?))
                    OR (s.status = 'grace_period' AND s.starts_at <= ? AND COALESCE(s.grace_ends_at, s.ends_at) > ?)
               )
             ORDER BY (s.ends_at IS NULL) DESC, COALESCE(s.grace_ends_at, s.ends_at) DESC, s.id DESC
             LIMIT 1{$suffix}",
            [$userId, $now, $now, $now, $now]
        )->getOne();

        $subscription = is_array($row) ? $row : null;
        if (!$lock) {
            $this->subscriptionCache[$userId] = $subscription;
        }

        return $subscription;
    }

    public function hasActiveSubscription(int $userId): bool
    {
        return $this->activeSubscription($userId) !== null;
    }

    public function can(int $userId, string $permission, array $context = []): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $user = db()->query('SELECT role FROM users WHERE id = ? LIMIT 1', [$userId])->getOne();
        if (in_array((string)($user['role'] ?? ''), ['creator', 'admin'], true)) {
            return true;
        }

        $subscription = $this->activeSubscription($userId);
        if (!$subscription) {
            return false;
        }
        $permissions = $this->permissions((int)$subscription['plan_id']);
        if (empty($permissions[$permission])) {
            return false;
        }

        if (str_starts_with($permission, 'camera_archive.')) {
            return $this->canUseCameraArchive((int)$subscription['plan_id'], $permission, $permissions, $context);
        }

        return true;
    }

    public function contentDecision(int $userId, string $contentType, string|int $contentId): array
    {
        $rule = (new ContentRuleRepository())->find($contentType, $contentId);
        if (!$rule && $contentType === 'post') {
            $rule = $this->defaultPostRule();
        }
        if (!$rule || (string)$rule['access_mode'] === 'public') {
            return ['allowed' => true, 'reason' => 'public', 'rule' => $rule];
        }
        if (in_array((string)($this->userRole($userId)), ['creator', 'admin'], true)) {
            return ['allowed' => true, 'reason' => 'administrator', 'rule' => $rule];
        }
        if ($userId <= 0) {
            return ['allowed' => false, 'reason' => 'authentication_required', 'rule' => $rule];
        }
        if ((string)$rule['access_mode'] === 'authenticated') {
            return ['allowed' => true, 'reason' => 'authenticated', 'rule' => $rule];
        }
        $subscription = $this->activeSubscription($userId);
        if (!$subscription) {
            return ['allowed' => false, 'reason' => 'subscription_required', 'rule' => $rule];
        }
        if ((string)$rule['access_mode'] === 'plans'
            && !in_array((int)$subscription['plan_id'], (array)$rule['plan_ids'], true)) {
            return ['allowed' => false, 'reason' => 'plan_required', 'rule' => $rule];
        }
        if ((string)$rule['access_mode'] === 'permission') {
            $permission = trim((string)($rule['required_permission'] ?? 'posts.view_paid')) ?: 'posts.view_paid';
            if (!$this->can($userId, $permission, ['content_type' => $contentType, 'content_id' => $contentId])) {
                return ['allowed' => false, 'reason' => 'permission_required', 'rule' => $rule];
            }
        }
        $subscriberPermission = $contentType === 'video' ? 'videos.view_paid' : 'posts.view_paid';
        if ((string)$rule['access_mode'] === 'subscribers' && !$this->can($userId, $subscriberPermission)) {
            return ['allowed' => false, 'reason' => 'permission_required', 'rule' => $rule];
        }

        return ['allowed' => true, 'reason' => 'subscription', 'rule' => $rule, 'subscription' => $subscription];
    }

    public function permissions(int $planId): array
    {
        if (array_key_exists($planId, $this->permissionCache)) {
            return $this->permissionCache[$planId];
        }
        $rows = db()->query(
            'SELECT permission_key, permission_value FROM subscription_plan_permissions WHERE plan_id = ?',
            [$planId]
        )->get() ?: [];
        $permissions = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string)$row['permission_value'], true);
            $permissions[(string)$row['permission_key']] = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : (string)$row['permission_value'];
        }

        return $this->permissionCache[$planId] = $permissions;
    }

    private function canUseCameraArchive(int $planId, string $permission, array $permissions, array $context): bool
    {
        $cameraId = trim((string)($context['camera_id'] ?? ''));
        if ($cameraId === '') {
            return false;
        }
        $groupIds = array_values(array_filter(array_map('strval', (array)($context['camera_group_ids'] ?? []))));
        $params = [$planId, $cameraId];
        $where = "(resource_type = 'camera' AND resource_id IN ('*', ?))";
        if ($groupIds !== []) {
            $where .= ' OR (resource_type = \'camera_group\' AND resource_id IN (' . implode(',', array_fill(0, count($groupIds), '?')) . '))';
            $params = array_merge($params, $groupIds);
        }
        $resource = db()->query(
            "SELECT * FROM subscription_plan_resources
             WHERE plan_id = ? AND ({$where})
             ORDER BY resource_id = '*' ASC LIMIT 1",
            $params
        )->getOne();
        $resourceCount = (int)db()->query(
            "SELECT COUNT(*) FROM subscription_plan_resources WHERE plan_id = ? AND resource_type IN ('camera', 'camera_group')",
            [$planId]
        )->getColumn();
        if ($resourceCount > 0 && !$resource) {
            return false;
        }
        if ($permission === 'camera_archive.download' && $resource && empty($resource['can_download'])) {
            return false;
        }
        if ($permission === 'camera_archive.view' && $resource && empty($resource['can_view'])) {
            return false;
        }

        $maxDays = (int)($resource['archive_days'] ?? $permissions['camera_archive.max_days'] ?? 0);
        $hasTimeRange = array_key_exists('from', $context) || array_key_exists('to', $context);
        $from = isset($context['from']) ? strtotime((string)$context['from']) : false;
        $to = isset($context['to']) ? strtotime((string)$context['to']) : false;
        if ($hasTimeRange && ($from === false || $to === false || $to <= $from || $to > time() + 300)) {
            return false;
        }
        if ($maxDays > 0 && $from !== false && $from < time() - $maxDays * 86400) {
            return false;
        }
        $maxMinutes = (int)($resource['max_fragment_minutes'] ?? $permissions['camera_archive.max_fragment_minutes'] ?? 0);
        if ($maxMinutes > 0 && $from !== false && $to !== false && $to - $from > $maxMinutes * 60) {
            return false;
        }

        return true;
    }

    private function userRole(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        if (array_key_exists($userId, $this->roleCache)) {
            return $this->roleCache[$userId];
        }

        return $this->roleCache[$userId] = (string)(db()->query(
            'SELECT role FROM users WHERE id = ? LIMIT 1',
            [$userId]
        )->getColumn() ?: '');
    }

    private function defaultPostRule(): array
    {
        return [
            'access_mode' => 'public',
            'show_title' => 1,
            'show_excerpt' => 1,
            'show_image' => 1,
            'hide_video' => 0,
            'required_permission' => 'posts.view_paid',
            'plan_ids' => [],
            'is_default' => true,
        ];
    }
}
