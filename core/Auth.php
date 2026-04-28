<?php

namespace FBL;

/**
 * Отвечает за аутентификацию пользователя и работу с его сессионными данными.
 */
class Auth
{

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
            session()->set('user', [
                'id' => $user['id'],
                'name' => $user['name'],
                'login' => $user['login'] ?? null,
                'email' => $user['email'],
                'avatar' => $user['avatar'] ?? null,
                'role' => $user['role'] ?? 'user',
            ]);

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
        if ($user_data = self::user()) {
            $user = db()->findOne('users', $user_data['id']);

            if ($user) {
                session()->set('user', [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'login' => $user['login'] ?? null,
                    'email' => $user['email'],
                    'avatar' => $user['avatar'] ?? null,
                    'role' => $user['role'] ?? 'user',
                ]);
            }
        }
    }

}
