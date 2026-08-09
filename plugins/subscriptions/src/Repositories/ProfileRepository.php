<?php

namespace Fireball\Subscriptions\Repositories;

final class ProfileRepository
{
    public const SYSTEM_FIELDS = [
        'first_name', 'last_name', 'middle_name', 'email', 'phone', 'country',
        'region', 'city', 'street', 'house', 'apartment', 'postal_code',
    ];

    public const FIELD_TYPES = [
        'text', 'textarea', 'number', 'email', 'phone', 'date',
        'select', 'radio', 'checkbox', 'boolean',
    ];

    public function profileForUser(int $userId, bool $create = true): ?array
    {
        $profile = db()->query(
            'SELECT * FROM subscription_profiles WHERE user_id = ? LIMIT 1',
            [$userId]
        )->getOne();
        if (!$profile && $create) {
            $user = db()->query('SELECT name, email FROM users WHERE id = ? LIMIT 1', [$userId])->getOne();
            if (!$user) {
                return null;
            }
            $now = date('Y-m-d H:i:s');
            db()->query(
                'INSERT INTO subscription_profiles (user_id, first_name, email, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$userId, trim((string)$user['name']), trim((string)$user['email']), $now, $now]
            );
            $profile = db()->query('SELECT * FROM subscription_profiles WHERE user_id = ? LIMIT 1', [$userId])->getOne();
        }
        if (!is_array($profile)) {
            return null;
        }

        $profile['custom_values'] = $this->values((int)$profile['id']);

        return $profile;
    }

    public function fields(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $rows = db()->query(
            "SELECT * FROM subscription_profile_fields {$where} ORDER BY sort_order ASC, id ASC"
        )->get() ?: [];

        foreach ($rows as &$row) {
            foreach (['id', 'sort_order'] as $field) {
                $row[$field] = (int)$row[$field];
            }
            foreach (['is_required', 'is_active', 'is_system', 'is_editable', 'show_during_checkout', 'use_in_receipt'] as $field) {
                $row[$field] = (bool)$row[$field];
            }
            if ($row['is_system']) {
                $row['label'] = \FireballPluginSubscriptions::t('subscriptions_profile_field_' . (string)$row['field_key']);
            }
            $row['options'] = json_decode((string)($row['options_json'] ?? ''), true) ?: [];
            $row['plan_ids'] = array_map('intval', json_decode((string)($row['plan_ids_json'] ?? ''), true) ?: []);
        }
        unset($row);

        return $rows;
    }

    public function saveProfile(int $userId, array $data): array
    {
        $profile = $this->profileForUser($userId);
        if (!$profile) {
            throw new \RuntimeException('Subscriber profile could not be created.');
        }
        $columns = [];
        foreach (self::SYSTEM_FIELDS as $key) {
            $columns[$key] = trim((string)($data[$key] ?? $profile[$key] ?? ''));
        }
        if ($columns['email'] !== '' && filter_var($columns['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_email'));
        }
        if ($columns['phone'] !== '' && preg_match('/^[+0-9()\-\s]{7,30}$/', $columns['phone']) !== 1) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_phone'));
        }

        $now = date('Y-m-d H:i:s');
        db()->beginTransaction();
        try {
            db()->query(
                'UPDATE subscription_profiles SET first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, country = ?, region = ?, city = ?, street = ?, house = ?, apartment = ?, postal_code = ?, updated_at = ? WHERE id = ?',
                [...array_values($columns), $now, (int)$profile['id']]
            );

            $custom = is_array($data['fields'] ?? null) ? $data['fields'] : [];
            foreach ($this->fields(true) as $field) {
                if ($field['is_system'] || !$field['is_editable']) {
                    continue;
                }
                $value = $this->normalizeCustomValue($field, $custom[(string)$field['field_key']] ?? null);
                db()->query(
                    'INSERT INTO subscription_profile_values (profile_id, field_id, field_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = VALUES(updated_at)',
                    [(int)$profile['id'], (int)$field['id'], $value, $now, $now]
                );
            }

            $fresh = $this->profileForUser($userId, false) ?: [];
            $completion = $this->completion($fresh);
            db()->query(
                'UPDATE subscription_profiles SET data_completed_at = ?, updated_at = ? WHERE id = ?',
                [$completion['complete'] ? $now : null, $now, (int)$profile['id']]
            );
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }

        return $this->profileForUser($userId, false) ?: [];
    }

    public function completion(array $profile, ?int $planId = null): array
    {
        $required = [];
        $missing = [];
        foreach ($this->fields(true) as $field) {
            if (!$field['is_required'] || ($field['plan_ids'] !== [] && ($planId === null || !in_array($planId, $field['plan_ids'], true)))) {
                continue;
            }
            $key = (string)$field['field_key'];
            $required[] = $key;
            $value = $field['is_system']
                ? ($profile[$key] ?? '')
                : ($profile['custom_values'][$key] ?? '');
            if ($this->isEmptyValue($value) || (in_array($field['field_type'], ['checkbox', 'boolean'], true) && (string)$value !== '1')) {
                $missing[] = (string)$field['label'];
            }
        }
        $total = count($required);
        $filled = max(0, $total - count($missing));

        return [
            'complete' => $total > 0 && $missing === [],
            'percent' => $total === 0 ? 100 : (int)floor($filled * 100 / $total),
            'missing' => $missing,
            'required' => $required,
        ];
    }

    public function snapshot(int $userId, ?int $planId = null): array
    {
        $profile = $this->profileForUser($userId, false);
        if (!$profile) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_profile_incomplete'));
        }
        $completion = $this->completion($profile, $planId);
        if (!$completion['complete']) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_profile_incomplete'));
        }

        return [
            'first_name' => (string)$profile['first_name'],
            'last_name' => (string)$profile['last_name'],
            'middle_name' => (string)$profile['middle_name'],
            'email' => (string)$profile['email'],
            'phone' => (string)$profile['phone'],
            'address' => array_intersect_key($profile, array_flip(['country', 'region', 'city', 'street', 'house', 'apartment', 'postal_code'])),
            'custom_fields' => (array)($profile['custom_values'] ?? []),
        ];
    }

    public function saveField(array $data, ?int $id = null): int
    {
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string)($data['field_key'] ?? ''))));
        $label = mb_substr(trim((string)($data['label'] ?? '')), 0, 255);
        $type = in_array((string)($data['field_type'] ?? ''), self::FIELD_TYPES, true) ? (string)$data['field_type'] : 'text';
        if ($key === '' || $label === '') {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_field_required'));
        }
        $existing = $id ? db()->query('SELECT * FROM subscription_profile_fields WHERE id = ?', [$id])->getOne() : null;
        if ($existing && !empty($existing['is_system'])) {
            $key = (string)$existing['field_key'];
            $type = (string)$existing['field_type'];
        }
        $mandatorySystem = in_array($key, ['email', 'phone', 'country', 'region', 'city', 'street', 'house', 'postal_code'], true);
        $options = preg_split('/\R/', trim((string)($data['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $planIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['plan_ids'] ?? [])))));
        $values = [
            $key, $label, trim((string)($data['description'] ?? '')), trim((string)($data['placeholder'] ?? '')),
            $type, ($mandatorySystem || !empty($data['is_required'])) ? 1 : 0, ($mandatorySystem || !empty($data['is_active'])) ? 1 : 0,
            $existing ? (int)$existing['is_system'] : 0, !empty($data['is_editable']) ? 1 : 0,
            !empty($data['show_during_checkout']) ? 1 : 0, !empty($data['use_in_receipt']) ? 1 : 0,
            trim((string)($data['validation_rules'] ?? '')), json_encode(array_values($options), JSON_UNESCAPED_UNICODE),
            json_encode($planIds), max(0, (int)($data['sort_order'] ?? 0)),
        ];
        $now = date('Y-m-d H:i:s');
        if ($id) {
            db()->query(
                'UPDATE subscription_profile_fields SET field_key = ?, label = ?, description = ?, placeholder = ?, field_type = ?, is_required = ?, is_active = ?, is_system = ?, is_editable = ?, show_during_checkout = ?, use_in_receipt = ?, validation_rules = ?, options_json = ?, plan_ids_json = ?, sort_order = ?, updated_at = ? WHERE id = ?',
                [...$values, $now, $id]
            );
            return $id;
        }
        db()->query(
            'INSERT INTO subscription_profile_fields (field_key, label, description, placeholder, field_type, is_required, is_active, is_system, is_editable, show_during_checkout, use_in_receipt, validation_rules, options_json, plan_ids_json, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [...$values, $now, $now]
        );

        return (int)db()->getInsertId();
    }

    public function deleteField(int $id): void
    {
        $field = db()->query('SELECT is_system FROM subscription_profile_fields WHERE id = ?', [$id])->getOne();
        if (!$field || !empty($field['is_system'])) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_error_system_field'));
        }
        db()->query('DELETE FROM subscription_profile_fields WHERE id = ?', [$id]);
    }

    private function values(int $profileId): array
    {
        $rows = db()->query(
            'SELECT f.field_key, v.field_value FROM subscription_profile_values v INNER JOIN subscription_profile_fields f ON f.id = v.field_id WHERE v.profile_id = ?',
            [$profileId]
        )->get() ?: [];

        return array_column($rows, 'field_value', 'field_key');
    }

    private function normalizeCustomValue(array $field, mixed $value): string
    {
        if (in_array((string)$field['field_type'], ['checkbox', 'boolean'], true)) {
            return !empty($value) ? '1' : '0';
        }
        $value = trim(is_scalar($value) ? (string)$value : '');
        if ($field['field_type'] === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException((string)$field['label']);
        }
        if ($field['field_type'] === 'phone' && $value !== '' && preg_match('/^[+0-9()\-\s]{7,30}$/', $value) !== 1) {
            throw new \InvalidArgumentException((string)$field['label']);
        }
        if ($field['field_type'] === 'number' && $value !== '' && !is_numeric($value)) {
            throw new \InvalidArgumentException((string)$field['label']);
        }
        if ($field['field_type'] === 'date' && $value !== '' && \DateTimeImmutable::createFromFormat('Y-m-d', $value) === false) {
            throw new \InvalidArgumentException((string)$field['label']);
        }
        if (in_array($field['field_type'], ['select', 'radio'], true) && $value !== '' && !in_array($value, $field['options'], true)) {
            throw new \InvalidArgumentException((string)$field['label']);
        }
        $this->validateRules($field, $value);

        return mb_substr($value, 0, 10000);
    }

    private function validateRules(array $field, string $value): void
    {
        if ($value === '') {
            return;
        }
        foreach (preg_split('/\|/', (string)($field['validation_rules'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $rule) {
            [$name, $argument] = array_pad(explode(':', trim($rule), 2), 2, '');
            $valid = match ($name) {
                'min' => mb_strlen($value) >= max(0, (int)$argument),
                'max' => mb_strlen($value) <= max(0, (int)$argument),
                'regex' => $argument !== '' && @preg_match($argument, $value) === 1,
                default => true,
            };
            if (!$valid) {
                throw new \InvalidArgumentException((string)$field['label']);
            }
        }
    }

    private function isEmptyValue(mixed $value): bool
    {
        return is_array($value) ? $value === [] : trim((string)$value) === '';
    }
}
