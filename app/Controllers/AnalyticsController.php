<?php

namespace App\Controllers;

use App\Services\AnalyticsService;
use App\Services\DatabaseMaintenanceService;

final class AnalyticsController extends BaseController
{
    private AnalyticsService $analytics;
    private DatabaseMaintenanceService $maintenance;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
        $this->maintenance = new DatabaseMaintenanceService();
    }

    public function dashboardData(): void
    {
        response()->json([
            'status' => 'success',
            'analytics' => $this->analytics->dashboardData(),
        ]);
    }

    public function track(): void
    {
        try {
            $this->analytics->track(request()->getData());
        } catch (\Throwable $exception) {
            log_error_details('Analytics tracking failed', [
                'Payload' => request()->getData(),
            ], $exception);
        }

        response()->json(['status' => 'accepted']);
    }

    public function index()
    {
        return view('admin/analytics', [
            'title' => return_translation('admin_analytics_full_title'),
            'analytics' => $this->analytics->fullAnalyticsData(request()->getData()),
            'geoip_status' => $this->analytics->geoIpStatus(),
            'can_reset_analytics' => false,
        ]);
    }

    public function refresh(): void
    {
        $this->analytics->clearDashboardCache();
        session()->setFlash('success', return_translation('admin_analytics_refresh_success'));
        response()->redirect(base_href('/admin/analytics'));
    }

    public function reset(): void
    {
        if (!check_creator()) {
            abort(return_translation('error_403_message'), 403);
        }

        $this->maintenance->run('clear_analytics', get_user() ?: [], $this->clientIp());
        session()->setFlash('success', return_translation('admin_analytics_reset_success'));
        response()->redirect(base_href('/admin/analytics'));
    }

    private function clientIp(): string
    {
        foreach ([
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ] as $header) {
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
