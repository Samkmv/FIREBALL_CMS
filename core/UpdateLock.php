<?php

namespace FBL;

use RuntimeException;

/** Reserve one updater, stop new readers, then drain existing runtime requests. */
final class UpdateLock
{
    private mixed $intent = null;
    private mixed $runtime = null;
    private bool $restoreRuntime = false;
    private bool $restoreSession = false;

    /** DB-backed long polls keep their reader until they exit, but yield promptly. */
    public static function pending(string $storage): bool
    {
        clearstatcache(true, $storage . '/update.maintenance');
        if (is_file($storage . '/update.maintenance')) return true;
        $intent = @fopen($storage . '/update-intent.lock', 'c+');
        if (!is_resource($intent)) return true;
        try {
            if (!flock($intent, LOCK_SH | LOCK_NB)) return true;
            flock($intent, LOCK_UN);
            return false;
        } finally { fclose($intent); }
    }

    /**
     * Terminal handoff after authorization, opening the source and preloading
     * every transfer class. The callback may use only loaded code and protected
     * runtime files, never SQL, the router, session writes or further autoload.
     */
    public static function stream(callable $transfer, ?Database $database = null): never
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
            throw new RuntimeException('Unable to close the session before streaming.');
        }
        $database?->closeForStreaming();
        $reader = $GLOBALS['fireball_runtime_lock'] ?? null;
        if (is_resource($reader)) {
            if (!flock($reader, LOCK_UN)) throw new RuntimeException('Unable to release the stream runtime reader.');
            fclose($reader);
        }
        $GLOBALS['fireball_runtime_lock'] = null;
        $GLOBALS['fireball_stream_detached'] = true;
        try { $transfer(); }
        catch (\Throwable $error) {
            // Do not invoke the application exception renderer/autoloader while
            // its sources may be changing, or append diagnostics to media bytes.
            error_log('Detached stream failed: ' . $error->getMessage());
            if (!headers_sent()) { http_response_code(500); header('Content-Length: 0'); }
        }
        exit;
    }

    public static function acquire(string $storage, float $waitSeconds = 30): self
    {
        $lock = new self();
        try {
            $lock->intent = @fopen($storage . '/update-intent.lock', 'c+');
            if (!is_resource($lock->intent)) {
                throw new RuntimeException(return_translation('admin_update_lock_unavailable'));
            }
            $wouldBlock = 0;
            if (!flock($lock->intent, LOCK_EX | LOCK_NB, $wouldBlock)) {
                throw new RuntimeException(return_translation($wouldBlock
                    ? 'admin_update_already_running' : 'admin_update_lock_unavailable'));
            }
            $maintenance = $storage . '/update.maintenance';
            clearstatcache(true, $maintenance);
            if (is_file($maintenance)) {
                throw new RuntimeException(return_translation('admin_update_recovery_required'));
            }

            // Requests from this browser may already own a runtime reader while
            // waiting for our PHP session. Let them finish instead of deadlocking.
            if (session_status() === PHP_SESSION_ACTIVE) {
                $lock->restoreSession = true;
                if (!session_write_close()) {
                    throw new RuntimeException(return_translation('admin_update_session_unavailable'));
                }
            }

            // Keep the initiating request protected until it has reserved the
            // writer slot. A rejected second updater must remain a normal reader.
            $lock->restoreRuntime = is_resource($GLOBALS['fireball_runtime_lock'] ?? null);
            $lock->runtime = $lock->restoreRuntime
                ? $GLOBALS['fireball_runtime_lock'] : @fopen($storage . '/update.lock', 'c+');
            if (!is_resource($lock->runtime)) {
                throw new RuntimeException(return_translation('admin_update_lock_unavailable'));
            }
            if ($lock->restoreRuntime) {
                $GLOBALS['fireball_runtime_lock'] = null;
                flock($lock->runtime, LOCK_UN);
            }

            $deadline = hrtime(true) + (int)(max(0, min(30, $waitSeconds)) * 1_000_000_000);
            while (!flock($lock->runtime, LOCK_EX | LOCK_NB, $wouldBlock)) {
                if (!$wouldBlock) {
                    throw new RuntimeException(return_translation('admin_update_lock_unavailable'));
                }
                // An older updater/recovery tool may own the exclusive runtime
                // lock without reserving intent. Do not mislabel it as a reader.
                if (!flock($lock->runtime, LOCK_SH | LOCK_NB, $wouldBlock)) {
                    throw new RuntimeException(return_translation($wouldBlock
                        ? 'admin_update_already_running' : 'admin_update_lock_unavailable'));
                }
                flock($lock->runtime, LOCK_UN);
                $remaining = $deadline - hrtime(true);
                if ($remaining <= 0) {
                    throw new RuntimeException(return_translation('admin_update_requests_busy'));
                }
                usleep((int)min(100_000, max(1, $remaining / 1000)));
            }

            // Recovery maintenance may have appeared while old readers drained.
            clearstatcache(true, $maintenance);
            if (is_file($maintenance)) {
                throw new RuntimeException(return_translation('admin_update_recovery_required'));
            }
            $payload = json_encode(['started_at' => date('c'), 'pid' => getmypid()], JSON_THROW_ON_ERROR);
            if (!ftruncate($lock->runtime, 0) || rewind($lock->runtime) === false
                || fwrite($lock->runtime, $payload) !== strlen($payload) || !fflush($lock->runtime)) {
                throw new RuntimeException(return_translation('admin_update_lock_unavailable'));
            }
            $lock->resumeSession();
            return $lock;
        } catch (\Throwable $error) {
            $lock->release();
            throw $error;
        }
    }

    public function release(): void
    {
        try {
            if (is_resource($this->runtime)) {
                if ($this->restoreRuntime) {
                    // The response can still use CMS/plugin code after installation.
                    // Restore its reader lock before allowing another writer to start.
                    flock($this->runtime, LOCK_SH);
                    $GLOBALS['fireball_runtime_lock'] = $this->runtime;
                } else {
                    flock($this->runtime, LOCK_UN);
                    fclose($this->runtime);
                }
                $this->runtime = null;
            }
            $this->resumeSession();
        } finally {
            if (is_resource($this->intent)) {
                flock($this->intent, LOCK_UN);
                fclose($this->intent);
                $this->intent = null;
            }
        }
    }

    private function resumeSession(): void
    {
        if (!$this->restoreSession) return;
        // Reload intervening session writes, then allow the controllers to save
        // their result/flash messages normally. Never leave a read-only session.
        if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
            throw new RuntimeException(return_translation('admin_update_session_unavailable'));
        }
        $this->restoreSession = false;
    }
}
