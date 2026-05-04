<?php

namespace FBL;

use App\Models\SiteSetting;

/**
 * Отвечает за аутентификацию пользователя и работу с его сессионными данными.
 */
class Auth
{

    protected const ADMIN_SESSION_SETTING_KEY = 'admin_session_lifetime_hours';
    protected const ADMIN_SESSION_LAST_ACTIVITY_KEY = 'auth.admin_last_activity_at';
    protected const DEFAULT_ADMIN_SESSION_LIFETIME_HOURS = 12;

    /**
     * Проверяет учётные данные и авторизует пользователя в сессии.
     */
    public static function login(array $credentials): bool
    {
        $password = $credentials['password'];
        unset($credentials['password']);
        $field = array_key_first($credentials);
        $value = $credentials[$field];

        if ($field === 'login') {
            $value = make_slug((string)$value, '');
        } elseif ($field === 'email') {
            $value = mb_strtolower(trim((string)$value));
        }

        $user = db()->findOne('users', $value, $field);

        if (!$user) {
            return false;
        }

        if (password_verify($password, $user['password'])) {
            session()->regenerateId();
            app()->regenerateCSRFToken();
            $sessionUser = [
                'id' => $user['id'],
                'name' => $user['name'],
                'login' => $user['login'] ?? null,
                'email' => $user['email'],
                'avatar' => $user['avatar'] ?? null,
                'role' => $user['role'] ?? 'user',
            ];
            session()->set('user', $sessionUser);
            self::syncAdminSession($sessionUser);

            return true;
        }

        return false;
    }

    /**
     * Возвращает данные текущего пользователя из сессии.
     */
    public static function user()
    {
        return session()->get('user');
    }

    /**
     * Проверяет, авторизован ли пользователь.
     */
    public static function isAuth(): bool
    {
        return session()->has('user');
    }

    /**
     * Завершает сессию пользователя и обновляет идентификатор сессии.
     */
    public static function logout(): void
    {
        session()->remove('user');
        session()->remove(self::ADMIN_SESSION_LAST_ACTIVITY_KEY);
        session()->regenerateId();
        app()->regenerateCSRFToken();
    }

    /**
     * Проверяет, соответствует ли роль текущего пользователя указанной роли.
     */
    public static function hasRole(string $role): bool
    {
        return self::isAuth() && (self::user()['role'] ?? 'user') === $role;
    }

    /**
     * Проверяет, является ли текущий пользователь администратором.
     */
    public static function isAdmin(): bool
    {
        $role = self::user()['role'] ?? 'user';

        return in_array($role, ['creator', 'admin'], true);
    }

    /**
     * Обновляет данные пользователя в сессии по актуальной записи из базы.
     */
    public static function setUser(): void
    {
        $userData = session()->get('user');
        if (!$userData) {
            session()->remove(self::ADMIN_SESSION_LAST_ACTIVITY_KEY);
            return;
        }

        $user = db()->findOne('users', $userData['id']);
        if (!$user) {
            self::logout();
            return;
        }

        $sessionUser = [
            'id' => $user['id'],
            'name' => $user['name'],
            'login' => $user['login'] ?? null,
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null,
            'role' => $user['role'] ?? 'user',
        ];
        session()->set('user', $sessionUser);

        self::syncAdminSession($sessionUser);
    }

    protected static function syncAdminSession(array $user): void
    {
        $role = (string)($user['role'] ?? 'user');
        if (!in_array($role, ['creator', 'admin'], true)) {
            session()->remove(self::ADMIN_SESSION_LAST_ACTIVITY_KEY);
            return;
        }

        $now = time();
        $lastActivity = (int)session()->get(self::ADMIN_SESSION_LAST_ACTIVITY_KEY, 0);
        $lifetimeSeconds = self::getAdminSessionLifetimeHours() * 3600;

        if ($lastActivity > 0 && ($lastActivity + $lifetimeSeconds) < $now) {
            self::logout();
            session()->setFlash('error', \FBL\Language::get('auth_admin_session_expired'));
            return;
        }

        session()->set(self::ADMIN_SESSION_LAST_ACTIVITY_KEY, $now);
    }

    protected static function getAdminSessionLifetimeHours(): int
    {
        $value = (new SiteSetting())->get(
            self::ADMIN_SESSION_SETTING_KEY,
            (string)self::DEFAULT_ADMIN_SESSION_LIFETIME_HOURS
        );

        if (!ctype_digit($value)) {
            return self::DEFAULT_ADMIN_SESSION_LIFETIME_HOURS;
        }

        $hours = (int)$value;

        return $hours >= 1 && $hours <= 720
            ? $hours
            : self::DEFAULT_ADMIN_SESSION_LIFETIME_HOURS;
    }

}
