<?php

namespace App\Controllers;

use App\Services\AnalyticsService;

final class AnalyticsController extends BaseController
{
    private AnalyticsService $analytics;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
    }

    public function dashboardData(): void
    {
        response()->json([
            'status' => 'success',
            'analytics' => $this->analytics->dashboardData(),
        ]);
    }

    public function index()
    {
        return view('admin/analytics', [
            'title' => return_translation('admin_analytics_full_title'),
            'analytics' => $this->analytics->fullAnalyticsData(request()->getData()),
            'can_reset_analytics' => check_creator(),
        ]);
    }

    public function reset(): void
    {
        if (!check_creator()) {
            abort(return_translation('error_403_message'), 403);
        }

        $this->analytics->resetAll();
        session()->setFlash('success', return_translation('admin_analytics_reset_success'));
        response()->redirect(base_href('/admin/analytics'));
    }
}
