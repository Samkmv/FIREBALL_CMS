<?php

namespace Fireball\Calendar\Repositories;

use Fireball\Calendar\Services\OccurrenceService;

final class EventRepository
{
    public function __construct(private readonly ?OccurrenceService $occurrences = null)
    {
    }

    public function visibleBetween(array $user, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $userId = (int)($user['id'] ?? 0);
        $role = (string)($user['role'] ?? 'user');
        $rows = db()->query(
            "SELECT DISTINCT e.*
             FROM calendar_events e
             LEFT JOIN calendar_event_users eu ON eu.event_id = e.id AND eu.user_id = ?
             WHERE e.deleted_at IS NULL
               AND (
                    e.owner_id = ?
                    OR e.visibility = 'all'
                    OR (e.visibility = 'role' AND e.audience_role = ?)
                    OR (e.visibility = 'users' AND eu.user_id IS NOT NULL)
               )
               AND e.starts_at < ?
               AND (
                    (e.recurrence = 'none' AND e.ends_at > ?)
                    OR (e.recurrence <> 'none' AND (e.recurrence_until IS NULL OR e.recurrence_until >= ?))
               )
             ORDER BY e.starts_at ASC, e.id ASC",
            [
                $userId,
                $userId,
                $role,
                $end->format('Y-m-d H:i:s'),
                $start->format('Y-m-d H:i:s'),
                $start->format('Y-m-d H:i:s'),
            ]
        )->get() ?: [];

        $reminders = $this->remindersByEvent(array_column($rows, 'id'));
        $audiences = $this->audienceUsersByEvent(array_column($rows, 'id'));
        $items = [];
        $occurrenceService = $this->occurrences ?? new OccurrenceService();

        foreach ($rows as $row) {
            $eventId = (int)$row['id'];
            $row['reminders'] = $reminders[$eventId] ?? [];
            $row['audience_user_ids'] = $audiences[$eventId] ?? [];
            $row['editable'] = $eventId > 0 && (
                $userId === (int)$row['owner_id']
                || in_array($role, ['creator', 'admin'], true)
            );
            foreach ($occurrenceService->between($row, $start, $end) as $occurrence) {
                $items[] = $occurrence;
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['starts_at'], (string)$b['starts_at']));

        return $items;
    }

    public function findManageable(int $id, int $userId, bool $isAdmin): ?array
    {
        $row = db()->query(
            'SELECT * FROM calendar_events WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$id]
        )->getOne();
        if (!is_array($row) || (!$isAdmin && (int)$row['owner_id'] !== $userId)) {
            return null;
        }

        $row['reminders'] = $this->remindersByEvent([$id])[$id] ?? [];
        $row['audience_user_ids'] = $this->audienceUsersByEvent([$id])[$id] ?? [];

        return $row;
    }

    public function create(array $data, array $actor, bool $isAdmin): int
    {
        $normalized = $this->normalize($data, $actor, $isAdmin);
        $now = date('Y-m-d H:i:s');

        db()->beginTransaction();
        try {
            db()->query(
                'INSERT INTO calendar_events
                    (owner_id, title, description, starts_at, ends_at, all_day, color, recurrence,
                     recurrence_until, status, visibility, audience_role, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $normalized['owner_id'], $normalized['title'], $normalized['description'],
                    $normalized['starts_at'], $normalized['ends_at'], $normalized['all_day'],
                    $normalized['color'], $normalized['recurrence'], $normalized['recurrence_until'],
                    $normalized['status'], $normalized['visibility'], $normalized['audience_role'],
                    (int)$actor['id'], $now, $now,
                ]
            );
            $id = (int)db()->getInsertId();
            $this->replaceAudience($id, $normalized['audience_user_ids'], $now);
            $this->replaceReminders($id, $normalized['reminders'], $now);
            db()->commit();

            return $id;
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, array $data, array $actor, bool $isAdmin): void
    {
        $current = $this->findManageable($id, (int)$actor['id'], $isAdmin);
        if (!$current) {
            throw new \RuntimeException(\FireballPluginCalendar::t('calendar_error_not_found'));
        }

        $normalized = $this->normalize($data, $actor, $isAdmin, $current);
        $now = date('Y-m-d H:i:s');
        db()->beginTransaction();
        try {
            db()->query(
                'UPDATE calendar_events SET
                    owner_id = ?, title = ?, description = ?, starts_at = ?, ends_at = ?, all_day = ?,
                    color = ?, recurrence = ?, recurrence_until = ?, status = ?, visibility = ?,
                    audience_role = ?, updated_at = ?
                 WHERE id = ?',
                [
                    $normalized['owner_id'], $normalized['title'], $normalized['description'],
                    $normalized['starts_at'], $normalized['ends_at'], $normalized['all_day'],
                    $normalized['color'], $normalized['recurrence'], $normalized['recurrence_until'],
                    $normalized['status'], $normalized['visibility'], $normalized['audience_role'],
                    $now, $id,
                ]
            );
            $this->replaceAudience($id, $normalized['audience_user_ids'], $now);
            $this->replaceReminders($id, $normalized['reminders'], $now);
            db()->commit();
        } catch (\Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }
    }

    public function duplicate(int $id, array $actor, bool $isAdmin): int
    {
        $event = $this->findManageable($id, (int)$actor['id'], $isAdmin);
        if (!$event) {
            throw new \RuntimeException(\FireballPluginCalendar::t('calendar_error_not_found'));
        }
        unset($event['id'], $event['created_at'], $event['updated_at'], $event['deleted_at']);
        $event['title'] = mb_substr((string)$event['title'] . ' — ' . \FireballPluginCalendar::t('calendar_copy_suffix'), 0, 160);

        return $this->create($event, $actor, $isAdmin);
    }

    public function setStatus(int $id, string $status, int $userId, bool $isAdmin): void
    {
        if (!in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_invalid_status'));
        }
        if (!$this->findManageable($id, $userId, $isAdmin)) {
            throw new \RuntimeException(\FireballPluginCalendar::t('calendar_error_not_found'));
        }
        db()->query('UPDATE calendar_events SET status = ?, updated_at = ? WHERE id = ?', [
            $status, date('Y-m-d H:i:s'), $id,
        ]);
    }

    public function delete(int $id, int $userId, bool $isAdmin): void
    {
        if (!$this->findManageable($id, $userId, $isAdmin)) {
            throw new \RuntimeException(\FireballPluginCalendar::t('calendar_error_not_found'));
        }
        $now = date('Y-m-d H:i:s');
        db()->query('UPDATE calendar_events SET deleted_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $id]);
        db()->query("UPDATE calendar_reminders SET status = 'cancelled', updated_at = ? WHERE event_id = ?", [$now, $id]);
    }

    public function reminderCandidates(): array
    {
        return db()->query(
            "SELECT e.*, r.id AS reminder_id, r.offset_value, r.offset_unit, r.remind_time,
                    r.site_notification, r.push_notification
             FROM calendar_events e
             INNER JOIN calendar_reminders r ON r.event_id = e.id AND r.status = 'active'
             WHERE e.deleted_at IS NULL
               AND e.status = 'scheduled'
               AND e.starts_at <= DATE_ADD(NOW(), INTERVAL 11 DAY)
               AND (e.recurrence = 'none' OR e.recurrence_until IS NULL OR e.recurrence_until >= DATE_SUB(NOW(), INTERVAL 1 DAY))
             ORDER BY e.id ASC, r.id ASC"
        )->get() ?: [];
    }

    public function recipientIds(array $event): array
    {
        $visibility = (string)($event['visibility'] ?? 'personal');
        if ($visibility === 'all') {
            $rows = db()->query('SELECT id FROM users ORDER BY id ASC')->get() ?: [];
            return array_map('intval', array_column($rows, 'id'));
        }
        if ($visibility === 'role') {
            $rows = db()->query('SELECT id FROM users WHERE role = ? ORDER BY id ASC', [(string)$event['audience_role']])->get() ?: [];
            return array_map('intval', array_column($rows, 'id'));
        }
        if ($visibility === 'users') {
            $rows = db()->query('SELECT user_id FROM calendar_event_users WHERE event_id = ? ORDER BY user_id ASC', [(int)$event['id']])->get() ?: [];
            return array_map('intval', array_column($rows, 'user_id'));
        }

        return [(int)$event['owner_id']];
    }

    private function normalize(array $data, array $actor, bool $isAdmin, array $current = []): array
    {
        $title = mb_substr(trim((string)($data['title'] ?? '')), 0, 160);
        if ($title === '') {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_title_required'));
        }

        $startsAt = $this->normalizeDateTime((string)($data['starts_at'] ?? ''));
        $endsAt = $this->normalizeDateTime((string)($data['ends_at'] ?? ''));
        if (!$startsAt || !$endsAt || $endsAt <= $startsAt) {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_dates'));
        }

        $recurrence = (string)($data['recurrence'] ?? 'none');
        if (!in_array($recurrence, ['none', 'daily', 'weekly', 'monthly', 'yearly'], true)) {
            $recurrence = 'none';
        }
        $recurrenceUntil = null;
        if ($recurrence !== 'none' && trim((string)($data['recurrence_until'] ?? '')) !== '') {
            $until = $this->normalizeDateTime((string)$data['recurrence_until'], true);
            if (!$until || $until < $startsAt) {
                throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_recurrence_until'));
            }
            $recurrenceUntil = $until->format('Y-m-d H:i:s');
        }

        $status = (string)($data['status'] ?? 'scheduled');
        if (!in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
            $status = 'scheduled';
        }

        $visibility = $isAdmin ? (string)($data['visibility'] ?? 'personal') : 'personal';
        if (!in_array($visibility, ['personal', 'all', 'role', 'users'], true)) {
            $visibility = 'personal';
        }
        $audienceRole = null;
        if ($visibility === 'role') {
            $audienceRole = mb_substr(trim((string)($data['audience_role'] ?? '')), 0, 50);
            if ($audienceRole === '' || !db()->query('SELECT 1 FROM user_roles WHERE slug = ? LIMIT 1', [$audienceRole])->getColumn()) {
                throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_audience'));
            }
        }

        $ownerId = (int)($current['owner_id'] ?? $actor['id']);
        if (!$isAdmin) {
            $ownerId = (int)$actor['id'];
        }

        $color = strtolower(trim((string)($data['color'] ?? '#6f5ef9')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $color = '#6f5ef9';
        }

        return [
            'owner_id' => $ownerId,
            'title' => $title,
            'description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 5000),
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'all_day' => !empty($data['all_day']) ? 1 : 0,
            'color' => $color,
            'recurrence' => $recurrence,
            'recurrence_until' => $recurrenceUntil,
            'status' => $status,
            'visibility' => $visibility,
            'audience_role' => $audienceRole,
            'audience_user_ids' => $visibility === 'users' ? $this->validUserIds((array)($data['audience_user_ids'] ?? [])) : [],
            'reminders' => $this->normalizeReminders((array)($data['reminders'] ?? [])),
        ];
    }

    private function normalizeDateTime(string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if ($endOfDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= ' 23:59:59';
        }
        try {
            return new \DateTimeImmutable($value, new \DateTimeZone(date_default_timezone_get()));
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeReminders(array $items): array
    {
        $normalized = [];
        foreach (array_slice($items, 0, 8) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $unit = (string)($item['unit'] ?? $item['offset_unit'] ?? 'days');
            if (!in_array($unit, ['minutes', 'hours', 'days'], true)) {
                continue;
            }
            $value = (int)($item['value'] ?? $item['offset_value'] ?? 1);
            $maximum = ['minutes' => 1440, 'hours' => 168, 'days' => 10][$unit];
            $value = max(1, min($maximum, $value));
            $time = trim((string)($item['time'] ?? $item['remind_time'] ?? ''));
            if ($unit !== 'days' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                $time = null;
            }
            $site = !empty($item['site']) || !empty($item['site_notification']);
            $push = !empty($item['push']) || !empty($item['push_notification']);
            if (!$site && !$push) {
                $site = true;
            }
            $normalized[] = [
                'id' => max(0, (int)($item['id'] ?? 0)),
                'offset_value' => $value,
                'offset_unit' => $unit,
                'remind_time' => $time,
                'site_notification' => $site ? 1 : 0,
                'push_notification' => $push ? 1 : 0,
            ];
        }

        return $normalized;
    }

    private function validUserIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_audience'));
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = db()->query("SELECT id FROM users WHERE id IN ({$placeholders})", $ids)->get() ?: [];
        $valid = array_map('intval', array_column($rows, 'id'));
        if ($valid === []) {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_audience'));
        }

        return $valid;
    }

    private function replaceAudience(int $eventId, array $ids, string $now): void
    {
        db()->query('DELETE FROM calendar_event_users WHERE event_id = ?', [$eventId]);
        foreach ($ids as $userId) {
            db()->query('INSERT INTO calendar_event_users (event_id, user_id, created_at) VALUES (?, ?, ?)', [
                $eventId, (int)$userId, $now,
            ]);
        }
    }

    private function replaceReminders(int $eventId, array $items, string $now): void
    {
        $rows = db()->query('SELECT id FROM calendar_reminders WHERE event_id = ?', [$eventId])->get() ?: [];
        $ownedIds = array_map('intval', array_column($rows, 'id'));
        $retainedIds = [];
        foreach ($items as $item) {
            $reminderId = (int)($item['id'] ?? 0);
            if ($reminderId > 0 && in_array($reminderId, $ownedIds, true)) {
                db()->query(
                    "UPDATE calendar_reminders SET offset_value = ?, offset_unit = ?, remind_time = ?,
                        site_notification = ?, push_notification = ?, status = 'active', updated_at = ?
                     WHERE id = ? AND event_id = ?",
                    [
                        $item['offset_value'], $item['offset_unit'], $item['remind_time'],
                        $item['site_notification'], $item['push_notification'], $now, $reminderId, $eventId,
                    ]
                );
                $retainedIds[] = $reminderId;
                continue;
            }
            db()->query(
                'INSERT INTO calendar_reminders
                    (event_id, offset_value, offset_unit, remind_time, site_notification, push_notification, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'active\', ?, ?)',
                [
                    $eventId, $item['offset_value'], $item['offset_unit'], $item['remind_time'],
                    $item['site_notification'], $item['push_notification'], $now, $now,
                ]
            );
            $retainedIds[] = (int)db()->getInsertId();
        }

        $removedIds = array_values(array_diff($ownedIds, $retainedIds));
        if ($removedIds !== []) {
            db()->query(
                "UPDATE calendar_reminders SET status = 'cancelled', updated_at = ?
                 WHERE event_id = ? AND id IN (" . implode(',', array_fill(0, count($removedIds), '?')) . ')',
                array_merge([$now, $eventId], $removedIds)
            );
        }
    }

    private function remindersByEvent(array $eventIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        if ($ids === []) {
            return [];
        }
        $rows = db()->query(
            'SELECT * FROM calendar_reminders WHERE status = \'active\' AND event_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id ASC',
            $ids
        )->get() ?: [];
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['event_id']][] = $row;
        }

        return $grouped;
    }

    private function audienceUsersByEvent(array $eventIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        if ($ids === []) {
            return [];
        }
        $rows = db()->query(
            'SELECT event_id, user_id FROM calendar_event_users WHERE event_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY user_id ASC',
            $ids
        )->get() ?: [];
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['event_id']][] = (int)$row['user_id'];
        }

        return $grouped;
    }
}
