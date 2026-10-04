<?php

namespace FBL;

/**
 * File-backed fixed-window limiter for public endpoints.
 */
final class RateLimiter
{
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $maxAttempts = max(1, $maxAttempts);
        $windowSeconds = max(1, $windowSeconds);
        $directory = CACHE . '/rate-limits';

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            log_error_details('Rate limiter directory error', ['Directory' => $directory]);
            return false;
        }

        $handle = @fopen($directory . '/' . hash('sha256', $key) . '.json', 'c+');
        if ($handle === false) {
            log_error_details('Rate limiter file error', ['Key Hash' => hash('sha256', $key)]);
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (is_string($raw) && $raw !== '' && (!is_array($state) || !isset($state['attempts'], $state['expires_at']) || !is_int($state['attempts']) || $state['attempts'] < 0 || !is_int($state['expires_at']) || $state['expires_at'] < 0)) {
                throw new \RuntimeException('Invalid rate limit state.');
            }
            $now = time();

            if (!is_array($state) || (int)($state['expires_at'] ?? 0) <= $now) {
                $state = ['attempts' => 0, 'expires_at' => $now + $windowSeconds];
            }

            if ((int)$state['attempts'] >= $maxAttempts) {
                return false;
            }

            $state['attempts'] = (int)$state['attempts'] + 1;
            rewind($handle);
            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new \RuntimeException('Unable to persist rate limit.');
            }

            return true;
        } catch (\Throwable $exception) {
            log_error_details('Rate limiter update error', [], $exception);
            return false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function clear(string $key): void
    {
        $path = CACHE . '/rate-limits/' . hash('sha256', $key) . '.json';
        if (!is_file($path)) return;
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) return;
        try {
            if (!flock($handle, LOCK_EX) || !ftruncate($handle, 0) || !fflush($handle)) {
                log_error_details('Rate limiter clear failed', ['Key Hash' => hash('sha256', $key)]);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
