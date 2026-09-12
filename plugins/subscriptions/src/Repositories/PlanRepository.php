<?php

namespace Fireball\Subscriptions\Repositories;

use Fireball\Subscriptions\Support\Money;

final class PlanRepository
{
    public const PERMISSIONS = [
        'posts.view_paid',
        'videos.view_paid',
        'camera_archive.view',
        'camera_archive.download',
        'camera_archive.max_days',
        'camera_archive.max_fragment_minutes',
    ];

    public function all(bool $publicOnly = false): array
    {
        $where = $publicOnly ? 'WHERE is_active = 1 AND is_public = 1' : '';
        $plans = db()->query(
            "SELECT * FROM subscription_plans {$where} ORDER BY sort_order ASC, id ASC"
        )->get() ?: [];

        return array_map(fn(array $plan): array => $this->decorate($plan), $plans);
    }

    public function find(int $id, bool $activeOnly = false): ?array
    {
        $where = $activeOnly ? ' AND is_active = 1' : '';
        $plan = db()->query(
            "SELECT * FROM subscription_plans WHERE id = ?{$where} LIMIT 1",
            [$id]
        )->getOne();

        return is_array($plan) ? $this->decorate($plan) : null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $name = mb_substr(trim((string)($data['name'] ?? '')), 0, 255);
        $slug = make_slug((string)($data['slug'] ?? $name), '');
        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_plan_required'));
        }

        $isPopular = !empty($data['is_popular']);
        $autoRenewEnabled = array_key_exists('auto_renew_enabled', $data)
            ? !empty($data['auto_renew_enabled'])
            : !empty($data['is_recurring']);
        $values = [
            $slug,
            $name,
            trim((string)($data['description'] ?? '')),
            Money::toMinor((string)($data['price'] ?? '0')),
            $this->currency((string)($data['currency'] ?? 'RUB')),
            $this->durationUnit((string)($data['duration_unit'] ?? 'days')),
            max(1, min(1200, (int)($data['duration_value'] ?? 30))),
            max(0, min(365, (int)($data['grace_period_days'] ?? 0))),
            $autoRenewEnabled ? 1 : 0,
            $autoRenewEnabled ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            !empty($data['is_public']) ? 1 : 0,
            $isPopular ? 1 : 0,
            max(0, (int)($data['sort_order'] ?? 0)),
        ];
        $now = date('Y-m-d H:i:s');

        db()->beginTransaction();
        try {
            if ($id !== null) {
                if (!$this->find($id)) {
                    throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_plan_not_found'));
                }
                db()->query(
                    'UPDATE subscription_plans SET slug = ?, name = ?, description = ?, price_minor = ?, currency = ?, duration_unit = ?, duration_value = ?, grace_period_days = ?, is_recurring = ?, auto_renew_enabled = ?, is_active = ?, is_public = ?, is_popular = ?, sort_order = ?, updated_at = ? WHERE id = ?',
                    [...$values, $now, $id]
                );
            } else {
                db()->query(
                    'INSERT INTO subscription_plans (slug, name, description, price_minor, currency, duration_unit, duration_value, grace_period_days, is_recurring, auto_renew_enabled, is_active, is_public, is_popular, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [...$values, $now, $now]
                );
                $id = (int)db()->getInsertId();
            }

            if ($isPopular) {
                db()->query(
                    'UPDATE subscription_plans SET is_popular = 0, updated_at = ? WHERE id <> ? AND is_popular = 1',
                    [$now, $id]
                );
            }

            $this->savePermissions($id, $data);
            $this->saveResources($id, $data);
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }

        return $id;
    }

    public function clone(int $id): int
    {
        $plan = $this->find($id);
        if (!$plan) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_plan_not_found'));
        }
        $copy = $plan;
        $copy['name'] .= ' — ' . \FireballPluginSubscriptions::t('subscriptions_copy');
        $copy['slug'] .= '-copy-' . time();
        $copy['price'] = Money::decimal((int)$plan['price_minor']);
        $copy['is_active'] = 0;
        $copy['is_popular'] = 0;
        foreach ($plan['permissions'] as $key => $value) {
            $copy['permissions'][$key] = $value;
        }

        return $this->save($copy);
    }

    public function toggle(int $id, string $field): void
    {
        if (!in_array($field, ['is_active', 'is_public'], true) || !$this->find($id)) {
            throw new \InvalidArgumentException('Invalid plan toggle.');
        }
        db()->query(
            "UPDATE subscription_plans SET {$field} = IF({$field} = 1, 0, 1), updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function permissionRows(int $planId): array
    {
        $rows = db()->query(
            'SELECT permission_key, permission_value FROM subscription_plan_permissions WHERE plan_id = ?',
            [$planId]
        )->get() ?: [];

        $result = [];
        foreach ($rows as $row) {
            $value = json_decode((string)$row['permission_value'], true);
            $result[(string)$row['permission_key']] = json_last_error() === JSON_ERROR_NONE
                ? $value
                : (string)$row['permission_value'];
        }

        return $result;
    }

    public function purchaseSnapshot(array $plan): array
    {
        $keys = [
            'id', 'slug', 'name', 'description', 'price_minor', 'currency',
            'duration_unit', 'duration_value', 'grace_period_days', 'is_recurring', 'auto_renew_enabled',
            'permissions', 'resources',
        ];

        return array_intersect_key($plan, array_flip($keys));
    }

    private function savePermissions(int $planId, array $data): void
    {
        $input = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];
        db()->query('DELETE FROM subscription_plan_permissions WHERE plan_id = ?', [$planId]);

        foreach (self::PERMISSIONS as $permission) {
            if (in_array($permission, ['camera_archive.max_days', 'camera_archive.max_fragment_minutes'], true)) {
                $value = max(0, (int)($input[$permission] ?? 0));
                if ($value === 0) {
                    continue;
                }
            } else {
                $value = !empty($input[$permission]);
                if (!$value) {
                    continue;
                }
            }
            db()->query(
                'INSERT INTO subscription_plan_permissions (plan_id, permission_key, permission_value) VALUES (?, ?, ?)',
                [$planId, $permission, json_encode($value)]
            );
        }
    }

    private function saveResources(int $planId, array $data): void
    {
        db()->query('DELETE FROM subscription_plan_resources WHERE plan_id = ?', [$planId]);
        $permissions = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];
        $archiveDays = max(0, (int)($permissions['camera_archive.max_days'] ?? 0));
        $maxMinutes = max(0, (int)($permissions['camera_archive.max_fragment_minutes'] ?? 0));
        $canView = !empty($permissions['camera_archive.view']) ? 1 : 0;
        $canDownload = !empty($permissions['camera_archive.download']) ? 1 : 0;
        $resources = [];
        if (!empty($data['all_cameras'])) {
            $resources[] = ['camera', '*'];
        } else {
            foreach (preg_split('/[\s,]+/', trim((string)($data['camera_ids'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
                if (preg_match('/^[a-zA-Z0-9._:-]{1,190}$/', $id) === 1) {
                    $resources[] = ['camera', $id];
                }
            }
            foreach (preg_split('/[\s,]+/', trim((string)($data['camera_group_ids'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
                if (preg_match('/^[a-zA-Z0-9._:-]{1,190}$/', $id) === 1) {
                    $resources[] = ['camera_group', $id];
                }
            }
        }
        $now = date('Y-m-d H:i:s');
        foreach (array_unique($resources, SORT_REGULAR) as [$type, $resourceId]) {
            db()->query(
                'INSERT INTO subscription_plan_resources (plan_id, resource_type, resource_id, can_view, can_download, archive_days, max_fragment_minutes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$planId, $type, $resourceId, $canView, $canDownload, $archiveDays ?: null, $maxMinutes ?: null, $now, $now]
            );
        }
    }

    private function decorate(array $plan): array
    {
        foreach (['id', 'price_minor', 'duration_value', 'grace_period_days', 'sort_order'] as $field) {
            $plan[$field] = (int)$plan[$field];
        }
        if (!array_key_exists('auto_renew_enabled', $plan)) {
            $plan['auto_renew_enabled'] = $plan['is_recurring'] ?? false;
        }
        foreach (['is_recurring', 'auto_renew_enabled', 'is_active', 'is_public', 'is_popular'] as $field) {
            $plan[$field] = (bool)$plan[$field];
        }
        $plan['permissions'] = $this->permissionRows((int)$plan['id']);
        $resources = db()->query('SELECT * FROM subscription_plan_resources WHERE plan_id = ? ORDER BY resource_type, resource_id', [(int)$plan['id']])->get() ?: [];
        $plan['resources'] = $resources;
        $plan['all_cameras'] = count(array_filter($resources, static fn(array $row): bool => $row['resource_type'] === 'camera' && $row['resource_id'] === '*')) > 0;
        $plan['camera_ids'] = implode(', ', array_column(array_filter($resources, static fn(array $row): bool => $row['resource_type'] === 'camera' && $row['resource_id'] !== '*'), 'resource_id'));
        $plan['camera_group_ids'] = implode(', ', array_column(array_filter($resources, static fn(array $row): bool => $row['resource_type'] === 'camera_group'), 'resource_id'));
        $plan['price_display'] = Money::display($plan['price_minor'], (string)$plan['currency']);

        return $plan;
    }

    private function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('Invalid currency.');
        }

        return $currency;
    }

    private function durationUnit(string $unit): string
    {
        return in_array($unit, ['days', 'months'], true) ? $unit : 'days';
    }
}
