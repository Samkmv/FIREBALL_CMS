<?php

namespace App\Controllers;

use App\Services\PwaService;

class PwaController extends BaseController
{
    protected PwaService $pwa;

    public function __construct()
    {
        parent::__construct();
        $this->pwa = new PwaService();
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        exit(json_encode($this->pwa->manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, must-revalidate');
        exit($this->pwa->serviceWorkerScript());
    }

    public function offline(): string
    {
        return view('pwa/offline', [
            'title' => return_translation('pwa_offline_title'),
            'seo_robots' => 'noindex,nofollow',
        ]);
    }

    public function support(): void
    {
        $currentUser = check_auth() ? get_user() : null;

        response()->json([
            'status' => true,
            'pwa_enabled' => $this->pwa->isEnabled(),
            'push_enabled' => $this->pwa->isPushEnabled(),
            'vapid_public_key' => pwa_head_data()['vapid_public_key'] ?? '',
            'secure_context' => request_is_secure()
                || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true),
            'user' => $currentUser ? $this->pwa->pushStatusForUser((int)$currentUser['id']) : null,
        ]);
    }

    public function subscribe(): void
    {
        if (!check_auth()) {
            response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
        }

        if (!$this->pwa->isPushEnabled()) {
            response()->json(['status' => false, 'message' => 'Push is disabled.'], 403);
        }

        $currentUser = get_user();
        $payload = request()->json();
        $ok = $this->pwa->saveSubscription(
            (int)$currentUser['id'],
            $payload,
            (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        response()->json([
            'status' => $ok,
            'push' => $this->pwa->pushStatusForUser((int)$currentUser['id']),
        ], $ok ? 200 : 422);
    }

    public function updateSubscription(): void
    {
        $this->subscribe();
    }

    public function unsubscribe(): void
    {
        if (!check_auth()) {
            response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
        }

        $currentUser = get_user();
        $this->pwa->deleteSubscription(
            (int)$currentUser['id'],
            (string)request()->post('endpoint', '')
        );

        response()->json([
            'status' => true,
            'push' => $this->pwa->pushStatusForUser((int)$currentUser['id']),
        ]);
    }

    public function pushStatus(): void
    {
        if (!check_auth()) {
            response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
        }

        response()->json([
            'status' => true,
            'push' => $this->pwa->pushStatusForUser((int)get_user()['id']),
        ]);
    }

    public function clearBadge(): void
    {
        response()->json(['status' => true]);
    }
}
