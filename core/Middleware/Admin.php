<?php

namespace FBL\Middleware;

/**
 * Ограничивает доступ к маршруту только для администраторов.
 */
class Admin
{

    /**
     * Проверяет роль пользователя и перенаправляет его при отсутствии прав администратора.
     */
    public function handle(): void
    {
        if (!check_admin()) {
            session()->setFlash('error', \FBL\Language::get('tpl_auth_required_admin'));
            response()->redirect(base_href('/profile'));
        }
    }

}
