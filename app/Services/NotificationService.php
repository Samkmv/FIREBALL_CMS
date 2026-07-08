<?php

namespace App\Services;

class NotificationService
{
    protected bool $schemaReady = false;

    public static function create(array $payload): array
    {
        return (new self())->createNotification($payload);
    }

    public static function createForUsers(array $userIds, array $payload): array
    {
        $service = new self();
        $results = [];

        foreach (array_values(array_unique(array_filter(array_map('intval', $userIds)))) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $item = $payload;
            $item['user_id'] = $userId;
            $results[] = $service->createNotification($item);
        }

        return [
            'sent' => array_sum(array_map(static fn(array $result): int => (int)($result['push']['sent'] ?? 0), $results)),
            'failed' => array_sum(array_map(static fn(array $result): int => (int)($result['push']['failed'] ?? 0), $results)),
            'total' => array_sum(array_map(static fn(array $result): int => (int)($result['push']['total'] ?? 0), $results)),
            'notifications' => $results,
        ];
    }

    public static function createForAdmins(array $payload): array
    {
        $rows = db()->query(
            "SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id ASC"
        )->get() ?: [];

        return self::createForUsers(array_column($rows, 'id'), $payload);
    }

    public static function send(array $payload, array $options = []): array
    {
        if (isset($options['user_id'])) {
            $payload['user_id'] = (int)$options['user_id'];

            return self::create($payload)['push'] ?? ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        if (!empty($options['user_ids']) && is_array($options['user_ids'])) {
            return self::createForUsers($options['user_ids'], $payload);
        }

        return (new PwaService())->send($payload, $options);
    }

    public static function sendToUser(int $userId, array $payload): array
    {
        return self::send($payload, ['user_id' => $userId]);
    }

    public static function sendToUsers(array $userIds, array $payload): array
    {
        return self::send($payload, ['user_ids' => $userIds]);
    }

    public static function broadcast(array $payload): array
    {
        return (new PwaService())->send($payload);
    }

    public function createNotification(array $payload): array
    {
        $this->ensureTables();
        $notification = $this->normalizeNotification($payload);
        $now = date('Y-m-d H:i:s');

        db()->query(
            'INSERT INTO notifications
                (user_id, title, message, type, action_url, icon, source, priority, metadata, is_read, read_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $notification['user_id'],
                $notification['title'],
                $notification['message'],
                $notification['type'],
                $notification['action_url'],
                $notification['icon'],
                $notification['source'],
                $notification['priority'],
                $notification['metadata'],
                $notification['is_read'],
                $notification['read_at'],
                $now,
            ]
        );

        $notification['id'] = (int)db()->getInsertId();
        $notification['created_at'] = $now;

        try {
            fireball_event('notification.created', $notification);
        } catch (\Throwable $exception) {
            log_error_details('Notification created event failed', [
                'notification_id' => $notification['id'],
                'source' => $notification['source'],
            ], $exception);
        }

        $pushResult = ['sent' => 0, 'failed' => 0, 'total' => 0, 'disabled' => true];
        try {
            $pushResult = (new PwaService())->send($this->pushPayload($notification), [
                'user_id' => $notification['user_id'],
                'notification_id' => $notification['id'],
                'source' => $notification['source'],
                'type' => $notification['type'],
            ]);
        } catch (\Throwable $exception) {
            log_error_details('Notification push dispatch failed', [
                'notification_id' => $notification['id'],
                'user_id' => $notification['user_id'],
                'source' => $notification['source'],
            ], $exception);
        }

        return [
            'notification' => $notification,
            'push' => $pushResult,
        ];
    }

    public function unreadItemsForUser(int $userId, int $limit = 8): array
    {
        $this->ensureTables();
        $limit = max(1, min(20, $limit));

        $rows = db()->query(
            "SELECT *
             FROM notifications
             WHERE user_id = ? AND is_read = 0
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}",
            [$userId]
        )->get() ?: [];

        return array_map([$this, 'feedItem'], $rows);
    }

    public function unreadCountForUser(int $userId): int
    {
        $this->ensureTables();

        return (int)db()->query(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        )->getColumn();
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        if ($userId <= 0 || $notificationId <= 0) {
            return false;
        }

        $this->ensureTables();
        db()->query(
            'UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, ?)
             WHERE id = ? AND user_id = ?',
            [date('Y-m-d H:i:s'), $notificationId, $userId]
        );

        return db()->rowCount() > 0;
    }

    public function ensureTables(): void
    {
        if ($this->schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NULL,
                type VARCHAR(80) NOT NULL DEFAULT 'system',
                action_url VARCHAR(500) NULL,
                icon VARCHAR(500) NULL,
                source VARCHAR(120) NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                metadata MEDIUMTEXT NULL,
                is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY user_unread (user_id, is_read, created_at),
                KEY type (type),
                KEY source (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS notification_settings (
                user_id INT(10) UNSIGNED NOT NULL,
                push_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addColumnIfMissing('notifications', 'action_url', 'VARCHAR(500) NULL AFTER type');
        $this->addColumnIfMissing('notifications', 'icon', 'VARCHAR(500) NULL AFTER action_url');
        $this->addColumnIfMissing('notifications', 'source', 'VARCHAR(120) NULL AFTER icon');
        $this->addColumnIfMissing('notifications', 'priority', "VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER source");
        $this->addColumnIfMissing('notifications', 'metadata', 'MEDIUMTEXT NULL AFTER priority');
        $this->addColumnIfMissing('notifications', 'is_read', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER metadata');
        $this->addColumnIfMissing('notifications', 'read_at', 'DATETIME NULL AFTER is_read');

        $this->schemaReady = true;
    }

    protected function normalizeNotification(array $payload): array
    {
        $userId = (int)($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Notification user_id is required.');
        }

        $title = $this->limit(trim((string)($payload['title'] ?? '')), 255);
        if ($title === '') {
            $title = return_translation('tpl_notifications');
        }

        $message = trim((string)($payload['message'] ?? $payload['body'] ?? $payload['text'] ?? ''));

        return [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $this->normalizeToken((string)($payload['type'] ?? 'system'), 'system', 80),
            'action_url' => $this->normalizeActionUrl((string)($payload['action_url'] ?? $payload['url'] ?? $payload['link'] ?? '')),
            'icon' => $this->limit(trim((string)($payload['icon'] ?? '')), 500),
            'source' => $this->normalizeToken((string)($payload['source'] ?? $payload['plugin'] ?? 'system'), 'system', 120),
            'priority' => $this->normalizePriority((string)($payload['priority'] ?? 'normal')),
            'metadata' => $this->encodeMetadata($payload['metadata'] ?? []),
            'is_read' => isset($payload['store_unread']) && !$payload['store_unread'] ? 1 : 0,
            'read_at' => isset($payload['store_unread']) && !$payload['store_unread'] ? date('Y-m-d H:i:s') : null,
        ];
    }

    protected function feedItem(array $row): array
    {
        return [
            'type' => (string)($row['type'] ?? 'system'),
            'notification_id' => (int)$row['id'],
            'source_label' => $this->sourceLabel((string)($row['source'] ?? 'system')),
            'title' => (string)$row['title'],
            'text' => (string)($row['message'] ?? ''),
            'url' => (string)($row['action_url'] ?? base_href('/')),
            'icon' => (string)($row['icon'] ?? ''),
            'source' => (string)($row['source'] ?? 'system'),
            'priority' => (string)($row['priority'] ?? 'normal'),
            'created_at' => (string)$row['created_at'],
            'time' => (string)$row['created_at'],
            'sort_id' => (int)$row['id'],
        ];
    }

    protected function pushPayload(array $notification): array
    {
        return [
            'title' => $notification['title'],
            'body' => $notification['message'],
            'url' => $notification['action_url'] ?: base_href('/'),
            'icon' => $notification['icon'] ?: null,
            'tag' => 'notification-' . (int)$notification['id'],
            'notification_id' => (int)$notification['id'],
            'type' => $notification['type'],
            'source' => $notification['source'],
            'priority' => $notification['priority'],
            'data' => [
                'notification_id' => (int)$notification['id'],
                'type' => $notification['type'],
                'source' => $notification['source'],
            ],
        ];
    }

    protected function normalizeActionUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return base_href('/');
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $targetHost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
            $baseHost = strtolower((string)(parse_url(base_url('/'), PHP_URL_HOST) ?? ''));

            return $targetHost !== '' && $targetHost === $baseHost ? $this->limit($url, 500) : base_href('/');
        }

        return $this->limit(base_href('/' . ltrim($url, '/')), 500);
    }

    protected function normalizeToken(string $value, string $default, int $limit): string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^[a-zA-Z0-9_.:-]+$/', $value)) {
            $value = $default;
        }

        return $this->limit($value, $limit);
    }

    protected function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
    }

    protected function encodeMetadata(mixed $metadata): string
    {
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded !== false ? $encoded : '{}';
    }

    protected function sourceLabel(string $source): string
    {
        if ($source === 'system') {
            return return_translation('tpl_notifications');
        }

        return ucfirst(str_replace(['_', '-'], ' ', $source));
    }

    protected function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)
            || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)
        ) {
            throw new \InvalidArgumentException('Unsafe schema identifier.');
        }

        $exists = db()->query("SHOW COLUMNS FROM {$table} LIKE ?", [$column])->getOne();
        if (!$exists) {
            db()->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    protected function limit(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
