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
            $this->rejectAjax();
            if (!session()->get('flash.error')) {
                session()->setFlash('error', \FBL\Language::get('tpl_auth_required_login'));
            }
            response()->redirect(base_href('/login'));
        }

        \FBL\Auth::setUser();

        if (!check_auth()) {
            $this->rejectAjax();
            response()->redirect(base_href('/login'));
        }
    }

    private function rejectAjax(): void
    {
        if (request()->isAjax()) {
            response()->json(['status' => 'error', 'message' => \FBL\Language::get('tpl_auth_required_login')], 401);
        }
    }

}
