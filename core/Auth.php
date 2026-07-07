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
    protected const PRESENCE_LAST_TOUCH_KEY = 'auth.presence_last_touch_at';
    protected const DEFAULT_ADMIN_SESSION_LIFETIME_HOURS = 12;

    /**
     * Проверяет учётные данные и авторизует пользователя в сессии.
     */
    public static function login(array $credentials): bool
    {
        $user = self::validateCredentials($credentials);
        if (!$user) {
            return false;
        }

        self::loginUser($user);

        return true;
    }

    /**
     * Validates credentials without creating an authenticated session.
     */
    public static function validateCredentials(array $credentials): array|false
    {
        $password = (string)($credentials['password'] ?? '');
        unset($credentials['password']);
        $field = array_key_first($credentials);
        if ($field === null) {
            return false;
        }
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

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        return $user;
    }

    /**
     * Creates an authenticated session for an already verified user.
     */
    public static function loginUser(array $user): void
    {
        session()->regenerateId();
        app()->regenerateCSRFToken();
        $sessionUser = [
            'id' => $user['id'],
            'name' => $user['name'],
            'login' => $user['login'] ?? null,
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null,
            'role' => $user['role'] ?? 'user',
            'locale' => Localization::normalizeLocale((string)($user['locale'] ?? '')),
            'admin_locale' => Localization::normalizeLocale((string)($user['admin_locale'] ?? '')),
            'session_version' => (int)($user['session_version'] ?? 1),
        ];
        session()->set('user', $sessionUser);
        self::syncAdminSession($sessionUser);
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
        $publicLocale = session()->get(Localization::PUBLIC_SESSION_KEY);
        $adminLocale = session()->get(Localization::ADMIN_SESSION_KEY);

        session()->clear();
        if ($publicLocale) {
            session()->set(Localization::PUBLIC_SESSION_KEY, $publicLocale);
        }
        if ($adminLocale) {
            session()->set(Localization::ADMIN_SESSION_KEY, $adminLocale);
        }
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
     * Обновляет отметку активности авторизованного пользователя без частых запросов к базе.
     */
    public static function touchPresence(int $throttleSeconds = 30): void
    {
        if (!self::isAuth()) {
            session()->remove(self::PRESENCE_LAST_TOUCH_KEY);
            return;
        }

        $userId = (int)(self::user()['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $now = time();
        $lastTouch = (int)session()->get(self::PRESENCE_LAST_TOUCH_KEY, 0);
        if ($lastTouch > 0 && ($lastTouch + max(1, $throttleSeconds)) > $now) {
            return;
        }

        (new \App\Models\User())->touchPresence($userId);
        session()->set(self::PRESENCE_LAST_TOUCH_KEY, $now);
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

        if ((int)($userData['session_version'] ?? 1) !== (int)($user['session_version'] ?? 1)) {
            self::logout();
            session()->setFlash('error', \FBL\Language::get('auth_session_invalidated'));
            return;
        }

        $sessionUser = [
            'id' => $user['id'],
            'name' => $user['name'],
            'login' => $user['login'] ?? null,
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null,
            'role' => $user['role'] ?? 'user',
            'locale' => Localization::normalizeLocale((string)($user['locale'] ?? '')),
            'admin_locale' => Localization::normalizeLocale((string)($user['admin_locale'] ?? '')),
            'session_version' => (int)($user['session_version'] ?? 1),
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
