<?php

namespace App\Controllers;

use App\Services\DatabaseMaintenanceService;

final class AdminMaintenanceController extends BaseController
{
    private DatabaseMaintenanceService $maintenance;

    public function __construct()
    {
        $this->maintenance = new DatabaseMaintenanceService();
    }

    public function index()
    {
        $this->requireCreator();

        return view('admin/database_maintenance', [
            'title' => return_translation('admin_maintenance_title'),
            'actions' => $this->maintenance->actions(),
            'logs' => $this->maintenance->logs(),
            'confirmation_phrase' => DatabaseMaintenanceService::CONFIRMATION_PHRASE,
        ]);
    }

    public function run(): void
    {
        $this->requireCreator();

        $action = trim((string)request()->post('action', ''));
        if (!$this->maintenance->isSafeAction($action) && !$this->maintenance->isDangerousAction($action)) {
            abort(return_translation('error_404_message'), 404);
        }

        if ($this->maintenance->isDangerousAction($action)) {
            $this->validateDangerousAction();
        }

        $result = $this->maintenance->run($action, get_user() ?: [], $this->clientIp());

        if (($result['status'] ?? 'error') === 'success') {
            session()->setFlash('success', return_translation('admin_maintenance_action_success'));
        } else {
            session()->setFlash('error', return_translation('admin_maintenance_action_error') . ' ' . (string)($result['message'] ?? ''));
        }

        response()->redirect(base_href('/admin/system/database-maintenance'));
    }

    private function validateDangerousAction(): void
    {
        $password = (string)request()->post('current_password', '');
        $confirmation = trim((string)request()->post('confirmation_phrase', ''));

        if ($confirmation !== DatabaseMaintenanceService::CONFIRMATION_PHRASE) {
            session()->setFlash('error', return_translation('admin_maintenance_confirm_invalid'));
            response()->redirect(base_href('/admin/system/database-maintenance'));
        }

        $currentUserId = (int)(get_user()['id'] ?? 0);
        $user = $currentUserId > 0 ? db()->findOne('users', $currentUserId) : false;
        if (!$user || !password_verify($password, (string)($user['password'] ?? ''))) {
            session()->setFlash('error', return_translation('admin_maintenance_password_invalid'));
            response()->redirect(base_href('/admin/system/database-maintenance'));
        }
    }

    private function requireCreator(): void
    {
        if (!check_creator()) {
            abort(return_translation('error_403_message'), 403);
        }
    }

    private function clientIp(): string
    {
        $headers = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($headers as $header) {
            foreach (explode(',', (string)$header) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return '';
    }
}
