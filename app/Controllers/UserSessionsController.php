<?php
namespace App\Controllers;

use App\Services\UserSessionService;
use FBL\Auth;
use FBL\RateLimiter;

final class UserSessionsController extends BaseController
{
    public function revoke(): never
    {
        $userId = (int)get_user()['id'];
        if (!UserSessionService::available()) $this->failure('account_feature_unavailable', 503);
        $id = (int)request()->post('id', 0);
        $current = db()->query('SELECT id FROM user_sessions WHERE user_id = ? AND session_hash = ? LIMIT 1',
            [$userId, UserSessionService::hash(session_id())])->getOne();
        if (!(new UserSessionService())->revoke($userId, $id)) $this->failure('account_session_not_found', 404);
        if ($id === (int)($current['id'] ?? 0)) {
            Auth::logout();
            $this->success('account_session_revoked', '/login');
        }
        $this->success('account_session_revoked', '/profile/sessions');
    }

    public function others(): never { $this->bulk(false); }
    public function all(): never { $this->bulk(true); }

    private function bulk(bool $all): never
    {
        $userId = (int)get_user()['id'];
        if (!UserSessionService::available()) $this->failure('account_feature_unavailable', 503);
        $user = db()->findOne('users', $userId);
        $key = 'auth.session-revoke.' . $userId . '|' . client_ip();
        if (!RateLimiter::attempt($key, 5, 600) || !$user || !password_verify((string)request()->post('current_password', ''), (string)$user['password'])) {
            $this->failure('account_confirm_password_error', 403);
        }
        $sessions = new UserSessionService();
        if ($all) {
            $sessions->revokeAll($userId);
            Auth::logout();
            $this->success('account_sessions_all_revoked', '/login');
        }
        $sessions->revokeOthers($userId, session_id());
        $this->success('account_sessions_others_revoked', '/profile/sessions');
    }

    private function failure(string $key, int $status): never
    {
        if (request()->isAjax()) response()->json(['status' => 'error', 'message' => return_translation($key)], $status);
        if ($status === 403) {
            session()->setFlash('error', return_translation($key));
            response()->redirect(base_href('/profile/sessions'));
        }
        abort(return_translation($key), $status);
    }

    private function success(string $key, string $path): never
    {
        $message = return_translation($key);
        if (request()->isAjax()) response()->json(['status' => 'success', 'message' => $message, 'redirect' => base_href($path)]);
        session()->setFlash('success', $message);
        response()->redirect(base_href($path));
    }
}
