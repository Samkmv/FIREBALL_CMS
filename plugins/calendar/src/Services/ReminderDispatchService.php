<?php

namespace Fireball\Calendar\Services;

use App\Services\NotificationService;
use App\Services\PwaService;
use Fireball\Calendar\Repositories\EventRepository;

final class ReminderDispatchService
{
    public function __construct(
        private readonly ?EventRepository $events = null,
        private readonly ?OccurrenceService $occurrences = null
    ) {
    }

    public function run(?\DateTimeImmutable $now = null): array
    {
        $timezone = new \DateTimeZone(date_default_timezone_get());
        $now = ($now ?: new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $rangeStart = $now->modify('-1 day');
        $rangeEnd = $now->modify('+11 days');
        $repository = $this->events ?? new EventRepository();
        $occurrenceService = $this->occurrences ?? new OccurrenceService($timezone);
        $stats = ['candidates' => 0, 'deliveries' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($repository->reminderCandidates() as $candidate) {
            $stats['candidates']++;
            $reminder = [
                'id' => (int)$candidate['reminder_id'],
                'offset_value' => (int)$candidate['offset_value'],
                'offset_unit' => (string)$candidate['offset_unit'],
                'remind_time' => $candidate['remind_time'],
                'site_notification' => (int)$candidate['site_notification'],
                'push_notification' => (int)$candidate['push_notification'],
            ];
            foreach ($occurrenceService->between($candidate, $rangeStart, $rangeEnd, 80) as $occurrence) {
                $scheduledAt = $occurrenceService->reminderAt($occurrence, $reminder);
                if (!$scheduledAt || $scheduledAt > $now || $scheduledAt < $now->modify('-1 day')) {
                    continue;
                }
                // Allow short scheduler outages, but do not burst-send reminders that became stale days ago.
                $occurrenceStart = new \DateTimeImmutable((string)$occurrence['starts_at'], $timezone);
                if ($occurrenceStart <= $now) {
                    continue;
                }

                foreach ($repository->recipientIds($candidate) as $userId) {
                    if ($userId <= 0) {
                        continue;
                    }
                    $stats['deliveries']++;
                    $deliveryId = $this->claim(
                        (int)$reminder['id'],
                        (int)$candidate['id'],
                        $userId,
                        $occurrenceStart,
                        $scheduledAt,
                        $now
                    );
                    if ($deliveryId <= 0) {
                        $stats['skipped']++;
                        continue;
                    }

                    try {
                        $result = $this->deliver($candidate, $occurrence, $reminder, $userId);
                        db()->query(
                            "UPDATE calendar_reminder_deliveries
                             SET status = 'sent', site_notification_id = ?, push_sent_count = ?,
                                 last_error = NULL, sent_at = ?, updated_at = ? WHERE id = ?",
                            [
                                $result['notification_id'] ?: null,
                                $result['push_sent'],
                                $now->format('Y-m-d H:i:s'),
                                $now->format('Y-m-d H:i:s'),
                                $deliveryId,
                            ]
                        );
                        $stats['sent']++;
                    } catch (\Throwable $exception) {
                        db()->query(
                            "UPDATE calendar_reminder_deliveries
                             SET status = 'failed', last_error = ?, updated_at = ? WHERE id = ?",
                            [mb_substr($exception->getMessage(), 0, 500), $now->format('Y-m-d H:i:s'), $deliveryId]
                        );
                        $stats['failed']++;
                        log_error_details('Calendar reminder delivery failed', [
                            'Event ID' => (int)$candidate['id'],
                            'Reminder ID' => (int)$reminder['id'],
                            'User ID' => $userId,
                        ], $exception);
                    }
                }
            }
        }

        return $stats;
    }

    private function claim(
        int $reminderId,
        int $eventId,
        int $userId,
        \DateTimeImmutable $occurrenceStart,
        \DateTimeImmutable $scheduledAt,
        \DateTimeImmutable $now
    ): int {
        $existing = db()->query(
            'SELECT * FROM calendar_reminder_deliveries
             WHERE reminder_id = ? AND occurrence_start = ? AND user_id = ? LIMIT 1',
            [$reminderId, $occurrenceStart->format('Y-m-d H:i:s'), $userId]
        )->getOne();

        if (is_array($existing)) {
            if ((string)$existing['status'] === 'sent' || (int)$existing['attempts'] >= 4) {
                return 0;
            }
            db()->query(
                "UPDATE calendar_reminder_deliveries
                 SET status = 'processing', attempts = attempts + 1, last_error = NULL, updated_at = ?
                 WHERE id = ?
                   AND status <> 'sent'
                   AND attempts < 4
                   AND (status <> 'processing' OR updated_at <= ?)",
                [
                    $now->format('Y-m-d H:i:s'),
                    (int)$existing['id'],
                    $now->modify('-10 minutes')->format('Y-m-d H:i:s'),
                ]
            );

            return db()->rowCount() > 0 ? (int)$existing['id'] : 0;
        }

        try {
            db()->query(
                'INSERT INTO calendar_reminder_deliveries
                    (reminder_id, event_id, user_id, occurrence_start, scheduled_at, status, attempts, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, \'processing\', 1, ?, ?)',
                [
                    $reminderId, $eventId, $userId,
                    $occurrenceStart->format('Y-m-d H:i:s'),
                    $scheduledAt->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                ]
            );

            return (int)db()->getInsertId();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function deliver(array $event, array $occurrence, array $reminder, int $userId): array
    {
        $start = new \DateTimeImmutable((string)$occurrence['starts_at']);
        $dateFormat = !empty($event['all_day']) ? 'd.m.Y' : 'd.m.Y H:i';
        $message = \FireballPluginCalendar::t('calendar_notification_message', [
            'date' => $start->format($dateFormat),
        ]);
        $payload = [
            'title' => (string)$event['title'],
            'message' => $message,
            'body' => $message,
            'type' => 'calendar_reminder',
            'source' => 'calendar',
            'priority' => 'high',
            'action_url' => base_href('/calendar?event=' . (int)$event['id'] . '&occurrence=' . rawurlencode((string)$occurrence['starts_at'])),
            'url' => base_href('/calendar?event=' . (int)$event['id']),
            'tag' => 'calendar-' . (int)$event['id'] . '-' . $start->format('YmdHi'),
            'metadata' => [
                'event_id' => (int)$event['id'],
                'reminder_id' => (int)$reminder['id'],
                'occurrence_start' => (string)$occurrence['starts_at'],
            ],
        ];

        $notificationId = 0;
        $pushSent = 0;
        if (!empty($reminder['site_notification'])) {
            $payload['user_id'] = $userId;
            $payload['push_notification'] = !empty($reminder['push_notification']);
            $result = (new NotificationService())->createNotification($payload);
            $notificationId = (int)($result['notification']['id'] ?? 0);
            $pushSent = (int)($result['push']['sent'] ?? 0);
        } elseif (!empty($reminder['push_notification'])) {
            $push = (new PwaService())->send($payload, [
                'user_id' => $userId,
                'source' => 'calendar',
                'type' => 'calendar_reminder',
            ]);
            $pushSent = (int)($push['sent'] ?? 0);
        }

        return ['notification_id' => $notificationId, 'push_sent' => $pushSent];
    }
}
