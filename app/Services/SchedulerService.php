<?php

namespace App\Services;

/**
 * Shared FIREBALL CMS scheduler for plugin background jobs.
 *
 * Run once per minute:
 *
 *     php bin/cms.php scheduler:run
 *
 * Plugins register jobs through the fireball_scheduled_jobs filter.
 */
final class SchedulerService
{
    public function run(?\DateTimeImmutable $now = null): array
    {
        $timezone = new \DateTimeZone(date_default_timezone_get());
        $now = ($now ?: new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);

        $jobs = apply_filters_safe('fireball_scheduled_jobs', []);
        if (!is_array($jobs)) {
            $jobs = [];
        }

        $summary = [
            'registered' => count($jobs),
            'due' => 0,
            'ran' => 0,
            'failed' => 0,
            'skipped' => 0,
            'jobs' => [],
        ];

        foreach ($jobs as $name => $definition) {
            $name = is_string($name) && $name !== ''
                ? $name
                : 'job-' . count($summary['jobs']);

            if (!is_array($definition)) {
                $summary['skipped']++;
                $summary['jobs'][$name] = ['status' => 'invalid_definition'];
                continue;
            }

            $schedule = trim((string)($definition['schedule'] ?? ''));
            if ($schedule === '' || !$this->isDue($schedule, $now)) {
                $summary['skipped']++;
                $summary['jobs'][$name] = ['status' => 'not_due'];
                continue;
            }

            $summary['due']++;
            $result = $this->execute($name, $definition, $now);
            $summary['jobs'][$name] = $result;

            if (($result['status'] ?? '') === 'ok') {
                $summary['ran']++;
            } elseif (($result['status'] ?? '') === 'failed') {
                $summary['failed']++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    private function execute(
        string $name,
        array $definition,
        \DateTimeImmutable $now
    ): array {
        $class = trim((string)($definition['class'] ?? ''));
        if ($class === '') {
            return [
                'status' => 'failed',
                'error' => 'Scheduled job class is missing.',
            ];
        }

        $directory = CACHE . '/scheduler';
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $lockPath = $directory . '/' . hash('sha256', $name) . '.lock';
        $lock = @fopen($lockPath, 'c+');

        if (!is_resource($lock)) {
            return [
                'status' => 'failed',
                'error' => 'Unable to create scheduler lock.',
            ];
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return ['status' => 'already_running'];
        }

        try {
            $slot = $now->format('YmdHi');

            rewind($lock);
            $lastSlot = trim((string)stream_get_contents($lock));
            if ($lastSlot === $slot) {
                return ['status' => 'already_ran'];
            }

            if (!class_exists($class)) {
                throw new \RuntimeException('Scheduled job class not found: ' . $class);
            }

            $job = new $class();

            if (method_exists($job, 'handle')) {
                $result = $job->handle();
            } elseif (is_callable($job)) {
                $result = $job();
            } else {
                throw new \RuntimeException(
                    'Scheduled job must provide handle() or __invoke().'
                );
            }

            // Mark the minute only after successful execution.
            ftruncate($lock, 0);
            rewind($lock);
            fwrite($lock, $slot);
            fflush($lock);

            return [
                'status' => 'ok',
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            log_error_details(
                'Scheduled job failed',
                [
                    'Job' => $name,
                    'Class' => $class,
                ],
                $exception
            );

            return [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function isDue(
        string $expression,
        \DateTimeImmutable $now
    ): bool {
        $parts = preg_split('/\s+/', trim($expression));
        if (!is_array($parts) || count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        if (!$this->fieldMatches($minute, (int)$now->format('i'), 0, 59)) {
            return false;
        }

        if (!$this->fieldMatches($hour, (int)$now->format('G'), 0, 23)) {
            return false;
        }

        if (!$this->fieldMatches($month, (int)$now->format('n'), 1, 12)) {
            return false;
        }

        $dayMatches = $this->fieldMatches(
            $day,
            (int)$now->format('j'),
            1,
            31
        );

        $weekdayMatches = $this->fieldMatches(
            $weekday,
            (int)$now->format('w'),
            0,
            7,
            true
        );

        $dayWildcard = trim($day) === '*';
        $weekdayWildcard = trim($weekday) === '*';

        if ($dayWildcard && $weekdayWildcard) {
            return true;
        }

        if ($dayWildcard) {
            return $weekdayMatches;
        }

        if ($weekdayWildcard) {
            return $dayMatches;
        }

        return $dayMatches || $weekdayMatches;
    }

    private function fieldMatches(
        string $expression,
        int $value,
        int $minimum,
        int $maximum,
        bool $weekday = false
    ): bool {
        $candidates = [$value];
        if ($weekday && $value === 0) {
            $candidates[] = 7;
        }

        foreach (explode(',', $expression) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $step = 1;
            $base = $token;

            if (str_contains($token, '/')) {
                [$base, $stepValue] = array_pad(
                    explode('/', $token, 2),
                    2,
                    ''
                );

                if (!ctype_digit($stepValue) || (int)$stepValue <= 0) {
                    continue;
                }

                $step = (int)$stepValue;
            }

            if ($base === '*') {
                $start = $minimum;
                $end = $maximum;
            } elseif (str_contains($base, '-')) {
                [$startValue, $endValue] = array_pad(
                    explode('-', $base, 2),
                    2,
                    ''
                );

                if (!ctype_digit($startValue) || !ctype_digit($endValue)) {
                    continue;
                }

                $start = (int)$startValue;
                $end = (int)$endValue;
            } elseif (ctype_digit($base)) {
                $start = (int)$base;
                $end = $start;
            } else {
                continue;
            }

            if (
                $start < $minimum
                || $end > $maximum
                || $start > $end
            ) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if ($candidate < $start || $candidate > $end) {
                    continue;
                }

                if (($candidate - $start) % $step === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
