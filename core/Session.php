<?php

namespace FBL;

/**
 * Обёртка над PHP-сессией для хранения обычных и flash-данных.
 */
class Session
{

    protected const SESSION_LIFETIME_SETTING_KEY = 'session_lifetime';
    protected const ADMIN_SESSION_SETTING_KEY = 'admin_session_lifetime_hours';
    protected const DEFAULT_LIFETIME_SECONDS = 43200;
    protected const MAX_LIFETIME_SECONDS = 2592000;

    protected int $lifetimeSeconds = self::DEFAULT_LIFETIME_SECONDS;
    protected array $cookieParams = [];

    /**
     * Запускает сессию и настраивает безопасные параметры cookie.
     */
    public function __construct()
    {
        $this->lifetimeSeconds = $this->resolveLifetimeSeconds();
        $this->cookieParams = $this->buildCookieParams($this->lifetimeSeconds);

        // PHP auto_start or another application may have opened PHPSESSID in
        // the hosting-wide directory. Never import that application's identity.
        if (session_status() === PHP_SESSION_ACTIVE && (
            session_name() !== $this->cookieName()
            || (session_module_name() === 'files' && session_save_path() !== $this->sessionDirectory())
        )) {
            session_write_close();
            session_id('');
            $_SESSION = [];
        }
        if (session_status() === PHP_SESSION_NONE) {
            $this->configureSession($this->lifetimeSeconds, $this->cookieParams);
            $this->startSession();
            $this->refreshCookie();
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            $this->refreshCookie();
        }
    }

    /**
     * Регенерирует идентификатор активной сессии.
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
            $this->refreshCookie();
        }
    }

    public function lifetimeSeconds(): int { return $this->lifetimeSeconds; }

    /**
     * Продлевает cookie активной PHP-сессии при sliding-сессии.
     */
    public function refreshCookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent() || session_id() === '') {
            return;
        }

        $params = $this->cookieParams ?: $this->buildCookieParams($this->lifetimeSeconds);
        $options = [
            'expires' => time() + $this->lifetimeSeconds,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ];

        if (($params['domain'] ?? '') !== '') {
            $options['domain'] = $params['domain'];
        }

        setcookie(session_name(), session_id(), $options);
    }

    /**
     * Сохраняет значение в сессии, поддерживая вложенные ключи через точку.
     */
    public function set($key, $value): void
    {
        $keys = explode('.', $key);
        $session_data =& $_SESSION;

        foreach ($keys as $k) {
            if (!isset($session_data[$k]) || !is_array($session_data[$k])) {
                $session_data[$k] = [];
            }
            $session_data =& $session_data[$k];
        }
        $session_data = $value;
    }

    /**
     * Возвращает значение из сессии по ключу или значение по умолчанию.
     */
    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $session_data = $_SESSION;

        foreach ($keys as $k) {
            if (isset($session_data[$k])) {
                $session_data = $session_data[$k];
            } else {
                return $default;
            }
        }
        return $session_data;
    }

    /**
     * Проверяет наличие значения в сессии по ключу.
     */
    public function has($key): bool
    {
        $keys = explode('.', $key);
        $session_data = $_SESSION;
        foreach ($keys as $k) {
            if (isset($session_data[$k])) {
                $session_data = $session_data[$k];
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Удаляет значение из сессии по ключу.
     */
    public function remove($key): void
    {
        $keys = explode('.', $key);
        $last_key = array_pop($keys);
        $session_data =& $_SESSION;
        foreach ($keys as $k) {
            if (!isset($session_data[$k]) || !is_array($session_data[$k])) {
                return;
            }
            $session_data =& $session_data[$k];
        }
        unset($session_data[$last_key]);
    }

    /**
     * Removes all session data while keeping the active session usable for flash messages.
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Сохраняет flash-сообщение до следующего чтения.
     */
    public function setFlash($key, $value): void
    {
        $_SESSION['flash'][$key] = $value;
    }

    /**
     * Возвращает flash-сообщение и сразу удаляет его из сессии.
     */
    public function getFlash($key, $default = null)
    {
        if (isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
        }
        return $value ?? $default;
    }

    /**
     * Определяет, выполняется ли текущий запрос по HTTPS.
     */
    protected function isSecureRequest(): bool
    {
        return request_is_secure();
    }

    protected function configureSession(int $lifetime, array $cookieParams): void
    {
        session_name($this->cookieName());
        if (session_module_name() === 'files') {
            // Separate installations must not garbage-collect each other's
            // sessions using a shorter php.ini gc_maxlifetime (often 24 min).
            $directory = $this->sessionDirectory();
            if (is_link($directory)
                || (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory))
                || !is_writable($directory) || !@chmod($directory, 0700)
                || session_save_path($directory) === false) {
                throw new \RuntimeException('Unable to configure private CMS session storage. Check storage/sessions permissions.');
            }
            // Some hosts disable PHP GC because a system job cleans the shared
            // directory. That job must not manage this private directory.
            if ((int)ini_get('session.gc_probability') <= 0) {
                ini_set('session.gc_probability', '1');
                if ((int)ini_get('session.gc_divisor') <= 0) ini_set('session.gc_divisor', '1000');
            }
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.cookie_lifetime', (string)$lifetime);
        ini_set('session.cookie_path', $cookieParams['path']);
        ini_set('session.cookie_domain', (string)($cookieParams['domain'] ?? ''));
        ini_set('session.cookie_secure', $cookieParams['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params($cookieParams);
    }

    protected function buildCookieParams(int $lifetime): array
    {
        return [
            'lifetime' => $lifetime,
            'path' => rtrim((string)(parse_url(PATH, PHP_URL_PATH) ?: ''), '/') ?: '/',
            // Host-only cookies: do not inherit another site's php.ini domain.
            'domain' => '',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    protected function resolveLifetimeSeconds(): int
    {
        return $this->settingsLifetime($this->loadSessionSettings());
    }

    protected function loadSessionSettings(): array
    {
        // Cached after installed settings load; no database connection here.
        $settings = cache()->get('session:policy');
        return is_array($settings) ? $settings : [
            // Real settings are applied after DB boot, before Auth validation.
            // A cold cache must never collect a valid longer session first.
            self::SESSION_LIFETIME_SETTING_KEY => self::MAX_LIFETIME_SECONDS,
        ];
    }

    public function applySettings(array $settings): void
    {
        $lifetime = $this->settingsLifetime($settings);
        $policy = array_intersect_key($settings, array_flip([self::SESSION_LIFETIME_SETTING_KEY, self::ADMIN_SESSION_SETTING_KEY]));
        if (cache()->get('session:policy') !== $policy) {
            cache()->set('session:policy', $policy, self::MAX_LIFETIME_SECONDS);
        }
        if ($lifetime !== $this->lifetimeSeconds) {
            // Reopen the same session so PHP's garbage collector sees the real lifetime.
            $wasActive = session_status() === PHP_SESSION_ACTIVE;
            if ($wasActive) session_write_close();
            $this->lifetimeSeconds = $lifetime;
            $this->cookieParams = $this->buildCookieParams($lifetime);
            $this->configureSession($lifetime, $this->cookieParams);
            if ($wasActive) {
                $this->startSession();
                $this->refreshCookie();
            }
        }
    }

    protected function settingsLifetime(array $settings): int
    {
        $general = $this->normalizeLifetimeSeconds($settings[self::SESSION_LIFETIME_SETTING_KEY]
            ?? (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : ''));
        $admin = $this->normalizeLifetimeHours($settings[self::ADMIN_SESSION_SETTING_KEY] ?? '')
            ?? intdiv(self::DEFAULT_LIFETIME_SECONDS, 3600);
        // Shared PHP storage/cookie lifetime cannot be shorter than the admin
        // inactivity policy. Auth still enforces that policy independently.
        return (int)max($general ?? 0, $admin * 3600);
    }

    protected function sessionDirectory(): string
    {
        return STORAGE . '/sessions';
    }

    protected function cookieName(): string
    {
        // Use the configured canonical URL, not an untrusted request Host or a
        // deployment directory that could change between servers/releases.
        $url = parse_url(PATH);
        $site = strtolower((string)($url['host'] ?? 'localhost'))
            . ':' . (string)($url['port'] ?? '')
            . '/' . trim((string)($url['path'] ?? ''), '/');
        return 'FBLSESSID' . substr(hash('sha256', $site), 0, 24);
    }

    protected function startSession(): void
    {
        if (!session_start()) {
            throw new \RuntimeException('Unable to start the CMS session. Check session storage configuration.');
        }
    }

    /** Use only after the endpoint has finished all session writes. */
    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    protected function normalizeLifetimeSeconds($value): ?int
    {
        $value = trim((string)$value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        $seconds = (int)$value;

        return $seconds > 0 && $seconds <= self::MAX_LIFETIME_SECONDS ? $seconds : null;
    }

    protected function normalizeLifetimeHours($value): ?int
    {
        $value = trim((string)$value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        $hours = (int)$value;

        return $hours >= 1 && $hours <= 720 ? $hours : null;
    }

}
