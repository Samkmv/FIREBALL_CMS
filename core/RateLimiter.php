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
            return true;
        }

        $handle = @fopen($directory . '/' . hash('sha256', $key) . '.json', 'c+');
        if ($handle === false) {
            log_error_details('Rate limiter file error', ['Key Hash' => hash('sha256', $key)]);
            return true;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return true;
            }

            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $now = time();

            if (!is_array($state) || (int)($state['expires_at'] ?? 0) <= $now) {
                $state = ['attempts' => 0, 'expires_at' => $now + $windowSeconds];
            }

            if ((int)$state['attempts'] >= $maxAttempts) {
                return false;
            }

            $state['attempts'] = (int)$state['attempts'] + 1;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);

            return true;
        } catch (\Throwable $exception) {
            log_error_details('Rate limiter update error', [], $exception);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function clear(string $key): void
    {
        $path = CACHE . '/rate-limits/' . hash('sha256', $key) . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
