<?php

namespace FBL;

/**
 * Обёртка над PHP-сессией для хранения обычных и flash-данных.
 */
class Session
{

    /**
     * Запускает сессию и настраивает безопасные параметры cookie.
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $params['lifetime'],
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $this->isSecureRequest(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Регенерирует идентификатор активной сессии.
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
        }
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
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

}
