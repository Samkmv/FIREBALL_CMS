<?php

namespace Fireball\VpnManagerV2\Services;

use App\Services\MailService;
use App\Services\NotificationService;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Repositories\NotificationRepository;

final class VpnNotificationService
{
    public const EXPIRES_3_DAYS = 'expires_3_days';
    public const EXPIRES_TODAY = 'expires_today';
    public const TRAFFIC_80 = 'traffic_80';
    public const TRAFFIC_100 = 'traffic_100';
    public const PROVISIONED = 'provisioned';
    public const CRITICAL_ERROR = 'critical_error';

    public function __construct(
        private readonly ?NotificationRepository $repository = null,
        private readonly ?AutomationRepository $automation = null,
        private readonly ?SettingsService $settings = null,
        private readonly ?NotificationService $notifications = null,
        private readonly ?MailService $mail = null,
        private readonly ?\Closure $expirationCandidates = null,
        private readonly ?\Closure $trafficCandidates = null,
    ) {
    }

    public function queueExpirationNotifications(): array
    {
        $settings = $this->settings();
        $queued = 0;
        $candidates = $this->expirationCandidates !== null
            ? ($this->expirationCandidates)()
            : $this->automation()->expirationNotificationCandidates();
        if (!is_array($candidates)) {
            throw new \LogicException('Invalid expiration notification provider result.');
        }
        foreach ($candidates as $subscription) {
            if (!is_array($subscription)) {
                continue;
            }
            $expiresAt = trim((string)($subscription['expires_at'] ?? ''));
            $date = $expiresAt !== '' ? substr($expiresAt, 0, 10) : date('Y-m-d');
            $days = $expiresAt !== '' ? (int)floor((strtotime($date . ' 00:00:00') - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400) : 0;
            if ($days === 3 && !empty($settings['notify_expiration_3_days'])) {
                $queued += $this->queue($subscription, self::EXPIRES_3_DAYS, 'expiry:' . $date);
            }
            if ($days === 0 && !empty($settings['notify_expiration_day'])) {
                $queued += $this->queue($subscription, self::EXPIRES_TODAY, 'expiry:' . $date);
            }
        }

        return ['queued' => $queued];
    }

    public function queueTrafficNotifications(): array
    {
        $settings = $this->settings();
        $queued = 0;
        $candidates = $this->trafficCandidates !== null
            ? ($this->trafficCandidates)()
            : $this->automation()->trafficNotificationCandidates();
        if (!is_array($candidates)) {
            throw new \LogicException('Invalid traffic notification provider result.');
        }
        foreach ($candidates as $subscription) {
            if (!is_array($subscription)) {
                continue;
            }
            $limit = max(1, (int)($subscription['traffic_limit_bytes'] ?? 0));
            $used = max(0, (int)($subscription['traffic_used_bytes'] ?? 0));
            $cycle = substr((string)($subscription['starts_at'] ?? ''), 0, 10) . ':' . $limit;
            if (($used / $limit) >= 0.8 && !empty($settings['notify_traffic_80'])) {
                $queued += $this->queue($subscription, self::TRAFFIC_80, 'traffic:' . hash('sha256', $cycle . ':80'));
            }
            if ($used >= $limit && !empty($settings['notify_traffic_100'])) {
                $queued += $this->queue($subscription, self::TRAFFIC_100, 'traffic:' . hash('sha256', $cycle . ':100'));
            }
        }

        return ['queued' => $queued];
    }

    public function notifyProvisioned(int $subscriptionId): array
    {
        $settings = $this->settings();
        if (empty($settings['notify_provisioned'])) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0];
        }
        $subscription = $this->automation()->subscription($subscriptionId);
        if (!$subscription || (string)$subscription['status'] !== 'active') {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0];
        }
        $occurrence = 'provisioned:' . substr((string)($subscription['created_at'] ?? ''), 0, 19);
        $queued = $this->queue($subscription, self::PROVISIONED, $occurrence);

        return array_merge(['queued' => $queued], $this->dispatch([self::PROVISIONED]));
    }

    public function notifyCritical(int $subscriptionId, string $operation): array
    {
        $settings = $this->settings();
        if (empty($settings['notify_critical_errors'])) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0];
        }
        $subscription = $this->automation()->subscription($subscriptionId);
        if (!$subscription) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0];
        }
        $operation = preg_replace('/[^a-z0-9_.:-]+/i', '-', trim($operation)) ?: 'automation';
        $queued = $this->queue(
            $subscription,
            self::CRITICAL_ERROR,
            'critical:' . mb_substr(strtolower($operation), 0, 60) . ':' . date('Y-m-d')
        );

        return array_merge(['queued' => $queued], $this->dispatch([self::CRITICAL_ERROR]));
    }

    public function dispatch(?array $types = null): array
    {
        $sent = 0;
        $failed = 0;
        foreach ($this->repository()->pending($types) as $row) {
            $id = (int)$row['id'];
            if (!$this->repository()->claim($id)) {
                continue;
            }
            try {
                if ((string)$row['channel'] === 'email') {
                    $email = trim((string)($row['user_email'] ?? ''));
                    if ($email === '' || !($this->mail ?? new MailService())->send(
                        [$email],
                        $this->title((string)$row['notification_type']),
                        '<p>' . htmlSC($this->message((string)$row['notification_type'])) . '</p>',
                        $this->message((string)$row['notification_type'])
                    )) {
                        throw new \RuntimeException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_notification_email'));
                    }
                } else {
                    ($this->notifications ?? new NotificationService())->createNotification([
                        'user_id' => (int)$row['user_id'],
                        'title' => $this->title((string)$row['notification_type']),
                        'message' => $this->message((string)$row['notification_type']),
                        'type' => 'vpn_v2_' . (string)$row['notification_type'],
                        'action_url' => '/profile/vpn-v2/' . (int)$row['subscription_id'],
                        'source' => 'vpn-manager-v2',
                        'priority' => (string)$row['notification_type'] === self::CRITICAL_ERROR ? 'high' : 'normal',
                        'store_unread' => true,
                        'metadata' => [
                            'subscription_id' => (int)$row['subscription_id'],
                            'occurrence_key' => (string)$row['occurrence_key'],
                        ],
                    ]);
                }
                $this->repository()->markSent($id);
                $sent++;
            } catch (\Throwable) {
                $this->repository()->markFailed(
                    $id,
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_notification_delivery')
                );
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function retryFailed(): array
    {
        $retried = $this->repository()->retryFailed();

        return array_merge(['retried' => $retried], $this->dispatch());
    }

    private function queue(array $subscription, string $type, string $occurrence): int
    {
        $settings = $this->settings();
        $count = 0;
        if (!empty($settings['notifications_profile_enabled'])) {
            $count += $this->repository()->enqueue(
                (int)$subscription['id'],
                (int)$subscription['user_id'],
                $type,
                $occurrence,
                'profile',
                date('Y-m-d H:i:s')
            ) ? 1 : 0;
        }
        if (!empty($settings['notifications_email_enabled'])) {
            $count += $this->repository()->enqueue(
                (int)$subscription['id'],
                (int)$subscription['user_id'],
                $type,
                $occurrence,
                'email',
                date('Y-m-d H:i:s')
            ) ? 1 : 0;
        }

        return $count;
    }

    private function title(string $type): string
    {
        return \FireballPluginVpnManagerV2::t('vpn_manager_v2_notification_' . $type . '_title');
    }

    private function message(string $type): string
    {
        return \FireballPluginVpnManagerV2::t('vpn_manager_v2_notification_' . $type . '_message');
    }

    private function settings(): array
    {
        return ($this->settings ?? new SettingsService())->current();
    }

    private function repository(): NotificationRepository
    {
        return $this->repository ?? new NotificationRepository();
    }

    private function automation(): AutomationRepository
    {
        return $this->automation ?? new AutomationRepository();
    }
}
