<?php

namespace Fireball\Calendar\Services;

final class OccurrenceService
{
    private \DateTimeZone $timezone;

    public function __construct(?\DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?: new \DateTimeZone(date_default_timezone_get());
    }

    public function between(array $event, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd, int $limit = 1500): array
    {
        $start = $this->date((string)($event['starts_at'] ?? ''));
        $end = $this->date((string)($event['ends_at'] ?? ''));
        if (!$start || !$end || $end <= $start || $rangeEnd <= $rangeStart) {
            return [];
        }

        $recurrence = (string)($event['recurrence'] ?? 'none');
        if (!in_array($recurrence, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            return $end > $rangeStart && $start < $rangeEnd
                ? [$this->occurrence($event, $start, $end)]
                : [];
        }

        $until = $this->date((string)($event['recurrence_until'] ?? ''));
        $duration = max(0, $end->getTimestamp() - $start->getTimestamp());
        $index = $this->firstUsefulIndex($recurrence, $start, $end, $rangeStart);
        $items = [];

        for ($iterations = 0; $iterations < $limit; $iterations++, $index++) {
            $occurrenceStart = $this->atIndex($start, $recurrence, $index);
            if ($until && $occurrenceStart > $until) {
                break;
            }
            if ($occurrenceStart >= $rangeEnd) {
                break;
            }

            $occurrenceEnd = $occurrenceStart->modify('+' . $duration . ' seconds');
            if ($occurrenceEnd > $rangeStart) {
                $items[] = $this->occurrence($event, $occurrenceStart, $occurrenceEnd);
            }
        }

        return $items;
    }

    public function reminderAt(array $occurrence, array $reminder): ?\DateTimeImmutable
    {
        $start = $this->date((string)($occurrence['starts_at'] ?? ''));
        if (!$start) {
            return null;
        }

        $value = max(1, (int)($reminder['offset_value'] ?? 1));
        $unit = (string)($reminder['offset_unit'] ?? 'days');
        if (!in_array($unit, ['minutes', 'hours', 'days'], true)) {
            return null;
        }

        $scheduled = $start->modify('-' . $value . ' ' . $unit);
        $remindTime = trim((string)($reminder['remind_time'] ?? ''));
        if ($unit === 'days' && preg_match('/^(\d{2}):(\d{2})$/', $remindTime, $matches)) {
            $scheduled = $scheduled->setTime((int)$matches[1], (int)$matches[2]);
        }

        return $scheduled;
    }

    private function firstUsefulIndex(
        string $recurrence,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        \DateTimeImmutable $rangeStart
    ): int {
        if ($end >= $rangeStart) {
            return 0;
        }

        if ($recurrence === 'daily' || $recurrence === 'weekly') {
            $seconds = $recurrence === 'daily' ? 86400 : 604800;
            return max(0, (int)floor(($rangeStart->getTimestamp() - $end->getTimestamp()) / $seconds));
        }

        if ($recurrence === 'monthly') {
            $months = ((int)$rangeStart->format('Y') - (int)$start->format('Y')) * 12
                + ((int)$rangeStart->format('n') - (int)$start->format('n'));
            return max(0, $months - 1);
        }

        return max(0, (int)$rangeStart->format('Y') - (int)$start->format('Y') - 1);
    }

    private function atIndex(\DateTimeImmutable $start, string $recurrence, int $index): \DateTimeImmutable
    {
        if ($index <= 0) {
            return $start;
        }

        if ($recurrence === 'daily') {
            return $start->modify('+' . $index . ' days');
        }
        if ($recurrence === 'weekly') {
            return $start->modify('+' . $index . ' weeks');
        }

        $year = (int)$start->format('Y');
        $month = (int)$start->format('n');
        if ($recurrence === 'monthly') {
            $absoluteMonth = ($year * 12 + $month - 1) + $index;
            $year = intdiv($absoluteMonth, 12);
            $month = $absoluteMonth % 12 + 1;
        } else {
            $year += $index;
        }

        $monthStart = $start->setDate($year, $month, 1);
        $day = min((int)$start->format('j'), (int)$monthStart->format('t'));

        return $start->setDate($year, $month, $day);
    }

    private function occurrence(array $event, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $eventId = (int)($event['id'] ?? 0);
        $startValue = $start->format('Y-m-d H:i:s');

        return array_merge($event, [
            'event_id' => $eventId,
            'occurrence_id' => $eventId . '@' . $start->format('Ymd\THis'),
            'occurrence_start' => $startValue,
            'series_starts_at' => (string)($event['starts_at'] ?? $startValue),
            'series_ends_at' => (string)($event['ends_at'] ?? $end->format('Y-m-d H:i:s')),
            'starts_at' => $startValue,
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, $this->timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
