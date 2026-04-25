<?php

namespace FBL\Middleware;

/**
 * Требует авторизации пользователя перед доступом к маршруту.
 */
class Auth
{

    /**
     * Проверяет, что пользователь вошёл в систему, иначе перенаправляет на страницу входа.
     */
    public function handle(): void
    {
        if (!check_auth()) {
            session()->setFlash('error', \FBL\Language::get('tpl_auth_required_login'));
            response()->redirect(base_href('/login'));
        }
    }

}
