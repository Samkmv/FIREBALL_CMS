<?php

namespace Fireball\VpnManager\Services;

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
            $created += $this->queueByDate('expires_3_days', "DATE(expires_at) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
        }

        if (!empty($settings['notify_on_expire_day'])) {
            $created += $this->queueByDate('expires_today', 'DATE(expires_at) = CURDATE()');
        }

        return ['created' => $created];
    }

    public function sendPending(): array
    {
        Schema::ensure();
        $settings = SettingsService::settings();
        $rows = db()->query(
            "SELECT n.*, s.expires_at
             FROM vpn_notifications n
             INNER JOIN vpn_subscriptions s ON s.id = n.subscription_id
             WHERE n.status = 'pending' AND n.scheduled_for <= CURDATE()
             ORDER BY n.id ASC
             LIMIT 100"
        )->get() ?: [];

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                if (empty($settings['use_account_notifications']) && empty($settings['use_push_notifications'])) {
                    $this->mark((int)$row['id'], 'skipped', null);
                    continue;
                }

                NotificationService::create([
                    'user_id' => (int)$row['user_id'],
                    'title' => $this->title((string)$row['type']),
                    'message' => $this->message((string)$row['type']),
                    'type' => 'vpn_' . (string)$row['type'],
                    'action_url' => '/my-vpn',
                    'source' => 'vpn-manager',
                    'priority' => 'normal',
                    'metadata' => [
                        'subscription_id' => (int)$row['subscription_id'],
                        'scheduled_for' => (string)$row['scheduled_for'],
                    ],
                ]);
                $this->mark((int)$row['id'], 'sent', null);
                (new VpnRepository())->logEvent('notification.sent', 'VPN notification sent.', ['type' => $row['type']], (int)$row['user_id'], (int)$row['subscription_id']);
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

        $this->insertNotification($subscriptionId, (int)$subscription['user_id'], 'manual_reminder', date('Y-m-d'), 'account');
        $this->sendPending();
    }

    private function queueByDate(string $type, string $condition): int
    {
        $rows = db()->query(
            "SELECT id, user_id, DATE(expires_at) AS scheduled_for
             FROM vpn_subscriptions
             WHERE status = 'active' AND expires_at IS NOT NULL AND {$condition}"
        )->get() ?: [];

        $count = 0;
        foreach ($rows as $row) {
            $this->insertNotification((int)$row['id'], (int)$row['user_id'], $type, (string)$row['scheduled_for'], 'account');
            $count++;
        }

        return $count;
    }

    private function insertNotification(int $subscriptionId, int $userId, string $type, string $scheduledFor, string $channel): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "INSERT IGNORE INTO vpn_notifications
                (subscription_id, user_id, type, scheduled_for, sent_at, channel, status, error_message, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, ?, 'pending', NULL, ?, ?)",
            [$subscriptionId, $userId, $type, $scheduledFor, $channel, $now, $now]
        );
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
            'manual_reminder' => \FireballPluginVpnManager::t('vpn_manager_notification_manual_title'),
            default => \FireballPluginVpnManager::t('vpn_manager_menu'),
        };
    }

    private function message(string $type): string
    {
        return match ($type) {
            'expires_3_days' => \FireballPluginVpnManager::t('vpn_manager_notification_expires_3_days_message'),
            'expires_today' => \FireballPluginVpnManager::t('vpn_manager_notification_expires_today_message'),
            'manual_reminder' => \FireballPluginVpnManager::t('vpn_manager_notification_manual_message'),
            default => \FireballPluginVpnManager::t('vpn_manager_notification_default_message'),
        };
    }
}
