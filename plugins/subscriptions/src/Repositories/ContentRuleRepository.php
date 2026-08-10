<?php

namespace Fireball\Subscriptions\Repositories;

final class ContentRuleRepository
{
    public function find(string $type, string|int $id): ?array
    {
        $rule = db()->query(
            'SELECT * FROM subscription_content_rules WHERE content_type = ? AND content_id = ? LIMIT 1',
            [$type, (string)$id]
        )->getOne();
        if (!is_array($rule)) {
            return null;
        }
        $rule['plan_ids'] = array_map('intval', array_column(db()->query(
            'SELECT plan_id FROM subscription_content_plans WHERE content_rule_id = ?',
            [(int)$rule['id']]
        )->get() ?: [], 'plan_id'));

        return $rule;
    }

    public function save(string $type, string|int $contentId, array $data): void
    {
        $requestedMode = (string)($data['subscription_access_mode'] ?? 'public');
        $mode = in_array($requestedMode, ['public', 'authenticated', 'subscribers', 'plans', 'permission'], true)
            ? $requestedMode
            : 'public';
        $permission = trim((string)($data['subscription_required_permission'] ?? ''));
        if ($mode === 'permission' && preg_match('/^[a-z0-9_.-]{3,120}$/', $permission) !== 1) {
            $permission = 'posts.view_paid';
        }
        $now = date('Y-m-d H:i:s');
        db()->query(
            'INSERT INTO subscription_content_rules (content_type, content_id, access_mode, show_title, show_excerpt, show_image, hide_video, required_permission, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE access_mode = VALUES(access_mode), show_title = VALUES(show_title), show_excerpt = VALUES(show_excerpt), show_image = VALUES(show_image), hide_video = VALUES(hide_video), required_permission = VALUES(required_permission), updated_at = VALUES(updated_at)',
            [
                $type, (string)$contentId, $mode,
                !empty($data['subscription_show_title']) ? 1 : 0,
                !empty($data['subscription_show_excerpt']) ? 1 : 0,
                !empty($data['subscription_show_image']) ? 1 : 0,
                !empty($data['subscription_hide_video']) ? 1 : 0,
                $permission !== '' ? $permission : null,
                $now, $now,
            ]
        );
        $rule = $this->find($type, $contentId);
        if (!$rule) {
            return;
        }
        db()->query('DELETE FROM subscription_content_plans WHERE content_rule_id = ?', [(int)$rule['id']]);
        if ($mode === 'plans') {
            foreach (array_values(array_unique(array_filter(array_map('intval', (array)($data['subscription_plan_ids'] ?? []))))) as $planId) {
                db()->query(
                    'INSERT IGNORE INTO subscription_content_plans (content_rule_id, plan_id) VALUES (?, ?)',
                    [(int)$rule['id'], $planId]
                );
            }
        }
    }

    public function delete(string $type, string|int $contentId): void
    {
        db()->query('DELETE FROM subscription_content_rules WHERE content_type = ? AND content_id = ?', [$type, (string)$contentId]);
    }
}
