<?php

namespace Fireball\VpnManager\Services;

use App\Services\MailService;
use App\Services\NotificationService;
use Fireball\VpnManager\Repositories\VpnRepository;
use Fireball\VpnManager\Support\Schema;

final class NotificationScheduler
{
    public function queueExpirationNotifications(): array
    {
        Schema::ensure();
        $settings = SettingsService::settings();
        $created = 0;

        if (!empty($settings['notify_3_days_before_expire'])) {
            $created += $this->queueByDate('expires_3_days', "DATE(expires_at) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)", $settings);
        }

        if (!empty($settings['notify_on_expire_day'])) {
            $created += $this->queueByDate('expires_today', 'DATE(expires_at) = CURDATE()', $settings);
        }

        return ['created' => $created];
    }

    public function queueTrafficNotifications(): array
    {
        Schema::ensure();
        $settings = SettingsService::settings();
        $created = 0;
        if (empty($settings['notify_traffic_80']) && empty($settings['notify_traffic_100'])) {
            return ['created' => 0];
        }

        $rows = db()->query(
            "SELECT *
             FROM vpn_subscriptions
             WHERE status = 'active'
               AND traffic_limit_bytes > 0
             ORDER BY id ASC
             LIMIT 500"
        )->get() ?: [];

        foreach ($rows as $subscription) {
            $ratio = $this->trafficRatio($subscription);
            $scheduledFor = substr((string)($subscription['starts_at'] ?? ''), 0, 10) ?: date('Y-m-d');
            if (!empty($settings['notify_traffic_80']) && $ratio >= 0.8) {
                $created += $this->queueNotification((int)$subscription['id'], (int)$subscription['user_id'], 'traffic_80', $scheduledFor, $settings);
            }
            if (!empty($settings['notify_traffic_100']) && $ratio >= 1.0) {
                $created += $this->queueNotification((int)$subscription['id'], (int)$subscription['user_id'], 'traffic_100', $scheduledFor, $settings);
            }
        }

        return ['created' => $created];
    }

    public function sendPending(?array $types = null): array
    {
        Schema::ensure();
        $settings = SettingsService::settings();
        $params = [];
        $typeSql = '';
        if ($types !== null) {
            $types = array_values(array_unique(array_filter(array_map('strval', $types))));
            if ($types) {
                $typeSql = ' AND n.type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
                $params = $types;
            }
        }

        $rows = db()->query(
            "SELECT n.*, s.expires_at, u.email AS user_email
             FROM vpn_notifications n
             INNER JOIN vpn_subscriptions s ON s.id = n.subscription_id
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.status = 'pending' AND n.scheduled_for <= CURDATE(){$typeSql}
             ORDER BY n.id ASC
             LIMIT 100",
            $params
        )->get() ?: [];

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                if ($row['channel'] === 'email') {
                    if (empty($settings['use_email_notifications'])) {
                        $this->mark((int)$row['id'], 'skipped', null);
                        continue;
                    }

                    $email = trim((string)($row['user_email'] ?? ''));
                    if ($email === '') {
                        $this->mark((int)$row['id'], 'skipped', null);
                        continue;
                    }

                    $ok = (new MailService())->send(
                        [$email],
                        $this->title((string)$row['type']),
                        '<p>' . htmlSC($this->message((string)$row['type'])) . '</p>',
                        $this->message((string)$row['type'])
                    );
                    if (!$ok) {
                        throw new \RuntimeException('Email notification was not sent.');
                    }
                    $this->mark((int)$row['id'], 'sent', null);
                    (new VpnRepository())->logEvent('notification.email_sent', 'VPN email notification sent.', ['type' => $row['type']], (int)$row['user_id'], (int)$row['subscription_id']);
                    $sent++;
                    continue;
                }

                if (empty($settings['use_account_notifications']) && empty($settings['use_push_notifications'])) {
                    $this->mark((int)$row['id'], 'skipped', null);
                    continue;
                }

                NotificationService::create([
                    'user_id' => (int)$row['user_id'],
                    'title' => $this->title((string)$row['type']),
                    'message' => $this->message((string)$row['type']),
                    'type' => 'vpn_' . (string)$row['type'],
                    'action_url' => '/profile/vpn',
                    'source' => 'vpn-manager',
                    'priority' => 'normal',
                    'store_unread' => !empty($settings['use_account_notifications']),
                    'metadata' => [
                        'subscription_id' => (int)$row['subscription_id'],
                        'scheduled_for' => (string)$row['scheduled_for'],
                    ],
                ]);
                $this->mark((int)$row['id'], 'sent', null);
                (new VpnRepository())->logEvent('notification.sent', 'VPN notification sent.', ['type' => $row['type'], 'channel' => $row['channel']], (int)$row['user_id'], (int)$row['subscription_id']);
                $sent++;
            } catch (\Throwable $exception) {
                $this->mark((int)$row['id'], 'failed', $exception->getMessage());
                (new VpnRepository())->logEvent('notification.failed', 'VPN notification failed.', ['type' => $row['type'], 'error' => $exception->getMessage()], (int)$row['user_id'], (int)$row['subscription_id']);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function manualReminder(int $subscriptionId): void
    {
        $subscription = (new VpnRepository())->subscription($subscriptionId);
        if (!$subscription) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_subscription_not_found'));
        }

        $this->queueNotification($subscriptionId, (int)$subscription['user_id'], 'manual_reminder', date('Y-m-d'), SettingsService::settings());
        $this->sendPending(['manual_reminder']);
    }

    public function queueSubscriptionNotification(int $subscriptionId, string $type, ?string $scheduledFor = null): int
    {
        $subscription = (new VpnRepository())->subscription($subscriptionId);
        if (!$subscription) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_subscription_not_found'));
        }

        return $this->queueNotification(
            $subscriptionId,
            (int)$subscription['user_id'],
            $type,
            $scheduledFor ?: date('Y-m-d'),
            SettingsService::settings()
        );
    }

    private function queueByDate(string $type, string $condition, array $settings): int
    {
        $rows = db()->query(
            "SELECT id, user_id, DATE(expires_at) AS scheduled_for
             FROM vpn_subscriptions
             WHERE status = 'active' AND expires_at IS NOT NULL AND {$condition}"
        )->get() ?: [];

        $count = 0;
        foreach ($rows as $row) {
            $count += $this->queueNotification((int)$row['id'], (int)$row['user_id'], $type, (string)$row['scheduled_for'], $settings);
        }

        return $count;
    }

    private function queueNotification(int $subscriptionId, int $userId, string $type, string $scheduledFor, array $settings): int
    {
        $count = 0;
        if (!empty($settings['use_account_notifications']) || !empty($settings['use_push_notifications'])) {
            $count += $this->insertNotification($subscriptionId, $userId, $type, $scheduledFor, 'account');
        }
        if (!empty($settings['use_email_notifications'])) {
            $count += $this->insertNotification($subscriptionId, $userId, $type, $scheduledFor, 'email');
        }

        return $count;
    }

    private function insertNotification(int $subscriptionId, int $userId, string $type, string $scheduledFor, string $channel): int
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT IGNORE INTO vpn_notifications
                (subscription_id, user_id, type, scheduled_for, sent_at, channel, status, error_message, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, ?, 'pending', NULL, ?, ?)",
            [$subscriptionId, $userId, $type, $scheduledFor, $channel, $now, $now]
        );

        return db()->rowCount() > 0 ? 1 : 0;
    }

    private function mark(int $id, string $status, ?string $error): void
    {
        db()->query(
            'UPDATE vpn_notifications SET status = ?, sent_at = IF(? = "sent", ?, sent_at), error_message = ?, updated_at = ? WHERE id = ?',
            [$status, $status, date('Y-m-d H:i:s'), $error ? mb_substr($error, 0, 1000) : null, date('Y-m-d H:i:s'), $id]
        );
    }

    private function title(string $type): string
    {
        return match ($type) {
            'expires_3_days' => \FireballPluginVpnManager::t('vpn_manager_notification_expires_3_days_title'),
            'expires_today' => \FireballPluginVpnManager::t('vpn_manager_notification_expires_today_title'),
            'traffic_80' => \FireballPluginVpnManager::t('vpn_manager_notification_traffic_80_title'),
            'traffic_100' => \FireballPluginVpnManager::t('vpn_manager_notification_traffic_100_title'),
            'subscription_expired' => \FireballPluginVpnManager::t('vpn_manager_notification_subscription_expired_title'),
            'manual_reminder' => \FireballPluginVpnManager::t('vpn_manager_notification_manual_title'),
            default => \FireballPluginVpnManager::t('vpn_manager_menu'),
        };
    }

    private function message(string $type): string
    {
        return match ($type) {
            'expires_3_days' => \FireballPluginVpnManager::t('vpn_manager_notification_expires_3_days_message'),
            'expires_today' => \FireballPluginVpnManager::t('vpn_manager_notification_expires_today_message'),
            'traffic_80' => \FireballPluginVpnManager::t('vpn_manager_notification_traffic_80_message'),
            'traffic_100' => \FireballPluginVpnManager::t('vpn_manager_notification_traffic_100_message'),
            'subscription_expired' => \FireballPluginVpnManager::t('vpn_manager_notification_subscription_expired_message'),
            'manual_reminder' => \FireballPluginVpnManager::t('vpn_manager_notification_manual_message'),
            default => \FireballPluginVpnManager::t('vpn_manager_notification_default_message'),
        };
    }

    private function trafficRatio(array $subscription): float
    {
        $limit = max(1, (int)($subscription['traffic_limit_bytes'] ?? 0));
        if ((string)($subscription['traffic_mode'] ?? 'shared') !== 'per_node') {
            return (int)($subscription['traffic_used_bytes'] ?? 0) / $limit;
        }

        $nodes = (new VpnRepository())->subscriptionNodes((int)$subscription['id']);
        $max = 0.0;
        foreach ($nodes as $node) {
            $max = max($max, (int)($node['traffic_used_bytes'] ?? 0) / $limit);
        }

        return $max;
    }
}
