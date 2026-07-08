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

        if (session_status() === PHP_SESSION_NONE) {
            $this->configureSession($this->lifetimeSeconds, $this->cookieParams);
            session_start();
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
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.cookie_lifetime', (string)$lifetime);
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', (string)($cookieParams['domain'] ?? ''));
        ini_set('session.cookie_secure', $cookieParams['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params($cookieParams);
    }

    protected function buildCookieParams(int $lifetime): array
    {
        $params = session_get_cookie_params();

        return [
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    protected function resolveLifetimeSeconds(): int
    {
        $settings = $this->loadSessionSettings();

        if (isset($settings[self::SESSION_LIFETIME_SETTING_KEY])) {
            $lifetime = $this->normalizeLifetimeSeconds($settings[self::SESSION_LIFETIME_SETTING_KEY]);
            if ($lifetime !== null) {
                return $lifetime;
            }
        }

        if (isset($settings[self::ADMIN_SESSION_SETTING_KEY])) {
            $hours = $this->normalizeLifetimeHours($settings[self::ADMIN_SESSION_SETTING_KEY]);
            if ($hours !== null) {
                return $hours * 3600;
            }
        }

        return self::DEFAULT_LIFETIME_SECONDS;
    }

    protected function loadSessionSettings(): array
    {
        if (
            !defined('INSTALLED_LOCK')
            || !is_file(INSTALLED_LOCK)
            || !defined('DB_SETTINGS')
            || empty(DB_SETTINGS['database'])
        ) {
            return [];
        }

        try {
            $pdo = new \PDO($this->buildDsn(), DB_SETTINGS['username'] ?? '', DB_SETTINGS['password'] ?? '', DB_SETTINGS['options'] ?? []);
            $stmt = $pdo->prepare(
                'SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN (?, ?)'
            );
            $stmt->execute([self::SESSION_LIFETIME_SETTING_KEY, self::ADMIN_SESSION_SETTING_KEY]);

            $settings = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
            }

            return $settings;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function buildDsn(): string
    {
        $dsn = 'mysql:host=' . (DB_SETTINGS['host'] ?? '127.0.0.1')
            . ';dbname=' . (DB_SETTINGS['database'] ?? '')
            . ';charset=' . (DB_SETTINGS['charset'] ?? 'utf8mb4');

        if (!empty(DB_SETTINGS['port'])) {
            $dsn .= ';port=' . (int)DB_SETTINGS['port'];
        }

        return $dsn;
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
