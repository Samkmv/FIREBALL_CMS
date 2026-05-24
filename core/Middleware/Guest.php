<?php

namespace FBL\Middleware;

/**
 * Разрешает доступ к маршруту только неавторизованным пользователям.
 */
class Guest
{

    /**
     * Перенаправляет авторизованного пользователя в профиль.
     */
    public function handle(): void
    {
        if (check_auth()) {
            response()->redirect(base_href('/profile'));
        }
    }

}
