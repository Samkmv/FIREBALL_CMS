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
    public const ADMIN_EXPIRES_3_DAYS = 'admin_expires_3_days';
    public const ADMIN_EXPIRES_1_DAY = 'admin_expires_1_day';
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
        $today = new \DateTimeImmutable('today');
        $administrators = $this->expirationRecipients($settings);
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
            // Catch up the 3-day reminder on day -2/-1 if the exact
            // scheduler run was missed. occurrence_key keeps it idempotent.
            if ($days >= 1 && $days <= 3 && !empty($settings['notify_expiration_3_days'])) {
                $queued += $this->queue($subscription, self::EXPIRES_3_DAYS, 'expiry:' . $date);
            }
            if ($days === 0 && !empty($settings['notify_expiration_day'])) {
                $queued += $this->queue($subscription, self::EXPIRES_TODAY, 'expiry:' . $date);
            }
            if ($administrators !== []) {
                $type = $this->administratorExpirationType($expiresAt, $subscription['status'], $today);
                if ($type !== null) {
                    foreach ($administrators as $administratorId) {
                        $queued += $this->repository()->enqueue(
                            (int)$subscription['id'],
                            $administratorId,
                            $type,
                            $this->administratorOccurrence($date, $administratorId),
                            'profile',
                            date('Y-m-d H:i:s')
                        ) ? 1 : 0;
                    }
                }
            }
        }

        return ['queued' => $queued];
    }

    private function expirationRecipients(array $settings): array
    {
        $selected = $settings['expiration_admin_recipient_ids'];
        if ($selected === []) {
            return [];
        }

        return array_intersect($selected, array_column($this->repository()->expirationAdministrators(), 'id'));
    }

    private function administratorExpirationType(string $expiresAt, string $status, \DateTimeImmutable $today): ?string
    {
        if (!in_array($status, ['active', 'partial_sync', 'sync_error'], true)) {
            return null;
        }

        $date = substr($expiresAt, 0, 10);
        $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $today->getTimezone());
        if ($expiry === false || $expiry->format('Y-m-d') !== $date) {
            return null;
        }

        return match ((int)$today->diff($expiry)->format('%r%a')) {
            3 => self::ADMIN_EXPIRES_3_DAYS,
            1 => self::ADMIN_EXPIRES_1_DAY,
            default => null,
        };
    }

    private function administratorOccurrence(string $date, int $administratorId): string
    {
        return 'expiry:' . $date . ':recipient:' . $administratorId;
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
        if (!$subscription || !in_array((string)$subscription['status'], ['active', 'partial_sync'], true)) {
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
        $administrators = null;
        $today = new \DateTimeImmutable('today');
        foreach ($this->repository()->pending($types) as $row) {
            $id = (int)$row['id'];
            if (!$this->repository()->claim($id)) {
                continue;
            }
            try {
                $type = (string)$row['notification_type'];
                $subscriptionId = (int)$row['subscription_id'];
                $recipientId = (int)$row['user_id'];
                $isAdminExpiration = in_array($type, [self::ADMIN_EXPIRES_3_DAYS, self::ADMIN_EXPIRES_1_DAY], true);
                $message = $this->message($type);
                $actionUrl = '/profile/vpn-v2/' . $subscriptionId;
                if ($isAdminExpiration) {
                    $administrators ??= $this->expirationRecipients($this->settings());
                    $expiresAt = (string)$row['expires_at'];
                    $date = substr($expiresAt, 0, 10);
                    if (!in_array($recipientId, $administrators, true)
                        || $this->administratorExpirationType($expiresAt, $row['subscription_status'], $today) !== $type
                        || $row['occurrence_key'] !== $this->administratorOccurrence($date, $recipientId)
                        || $row['channel'] !== 'profile') {
                        $this->repository()->markSkipped($id);
                        continue;
                    }
                    $message = sprintf($message, $subscriptionId, $date);
                    $actionUrl = '/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId;
                }
                if ((string)$row['channel'] === 'email') {
                    $email = trim((string)($row['user_email'] ?? ''));
                    if ($email === '' || !($this->mail ?? new MailService())->send(
                        [$email],
                        $this->title($type),
                        '<p>' . htmlSC($message) . '</p>',
                        $message
                    )) {
                        throw new \RuntimeException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_notification_email'));
                    }
                } else {
                    ($this->notifications ?? new NotificationService())->createNotification([
                        'user_id' => $recipientId,
                        'title' => $this->title($type),
                        'message' => $message,
                        'type' => 'vpn_v2_' . $type,
                        'action_url' => $actionUrl,
                        'source' => 'vpn-manager-v2',
                        'priority' => $type === self::CRITICAL_ERROR ? 'high' : 'normal',
                        'store_unread' => true,
                        'metadata' => [
                            'subscription_id' => $subscriptionId,
                            'occurrence_key' => (string)$row['occurrence_key'],
                        ],
                    ]);
                }
                $this->repository()->markSent($id);
                $sent++;
            } catch (\Throwable $exception) {
                $this->repository()->markFailed(
                    $id,
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_notification_delivery')
                );
                log_error_details(
                    'VPN Manager V2 notification delivery failed',
                    [
                        'Notification ID' => $id,
                        'Subscription ID' => (int)($row['subscription_id'] ?? 0),
                        'User ID' => (int)($row['user_id'] ?? 0),
                        'Type' => (string)($row['notification_type'] ?? ''),
                        'Channel' => (string)($row['channel'] ?? ''),
                        'Error Class' => get_class($exception),
                    ],
                    $exception
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
        // Owner notifications require a CMS account.
        if ((int)($subscription['user_id'] ?? 0) <= 0) {
            return 0;
        }

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
