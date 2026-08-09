<?php

namespace Fireball\Subscriptions\Controllers;

use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Repositories\ProfileRepository;
use Fireball\Subscriptions\Services\SettingsService;
use Fireball\Subscriptions\Services\SubscriptionService;

final class AdminController
{
    public function dashboard(): string
    {
        $stats = [
            'active' => (int)db()->query("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active', 'grace_period', 'cancelled') AND starts_at <= NOW() AND COALESCE(grace_ends_at, ends_at) > NOW()")->getColumn(),
            'expiring' => (int)db()->query("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active', 'cancelled') AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")->getColumn(),
            'paid_total_minor' => (int)db()->query("SELECT COALESCE(SUM(amount_minor), 0) FROM subscription_payments WHERE status = 'paid'")->getColumn(),
            'failed' => (int)db()->query("SELECT COUNT(*) FROM subscription_payments WHERE status = 'failed'")->getColumn(),
        ];
        $byPlan = db()->query(
            "SELECT p.name, COUNT(s.id) AS total FROM subscription_plans p LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status IN ('active', 'grace_period', 'cancelled') AND s.ends_at > NOW() GROUP BY p.id, p.name ORDER BY total DESC, p.name"
        )->get() ?: [];

        return $this->view('admin/dashboard', 'overview', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_title'),
            'stats' => $stats,
            'by_plan' => $byPlan,
        ]);
    }

    public function plans(): string
    {
        return $this->view('admin/plans', 'plans', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_plans'),
            'plans' => (new PlanRepository())->all(),
        ]);
    }

    public function planForm(): string
    {
        $id = (int)get_route_param('id');
        $repository = new PlanRepository();
        if (request()->isPost()) {
            try {
                $repository->save(request()->getData(), $id > 0 ? $id : null);
                session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_plan_saved'));
                response()->redirect(base_href('/admin/subscriptions/plans'));
            } catch (\Throwable $exception) {
                session()->setFlash('error', $exception->getMessage());
                session()->set('subscriptions.plan_data', request()->getData());
                response()->redirect($id > 0 ? base_href('/admin/subscriptions/plans/edit/' . $id) : base_href('/admin/subscriptions/plans/create'));
            }
        }
        $plan = $id > 0 ? $repository->find($id) : null;
        if ($id > 0 && !$plan) {
            abort();
        }

        $formData = (array)session()->get('subscriptions.plan_data', []);
        session()->remove('subscriptions.plan_data');

        return $this->view('admin/plan-form', 'plans', [
            'title' => $id > 0 ? \FireballPluginSubscriptions::t('subscriptions_plan_edit') : \FireballPluginSubscriptions::t('subscriptions_plan_create'),
            'plan' => $plan,
            'form_data' => $formData,
            'permissions' => PlanRepository::PERMISSIONS,
        ]);
    }

    public function planAction(): never
    {
        try {
            $id = (int)request()->post('id');
            $action = (string)request()->post('action');
            $repository = new PlanRepository();
            match ($action) {
                'toggle_active' => $repository->toggle($id, 'is_active'),
                'toggle_public' => $repository->toggle($id, 'is_public'),
                'clone' => $repository->clone($id),
                default => throw new \InvalidArgumentException('Invalid plan action.'),
            };
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_plan_saved'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/admin/subscriptions/plans'));
    }

    public function subscribers(): string
    {
        $rows = db()->query(
            "SELECT s.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name
             FROM subscriptions s INNER JOIN users u ON u.id = s.user_id INNER JOIN subscription_plans p ON p.id = s.plan_id
             ORDER BY s.created_at DESC LIMIT 300"
        )->get() ?: [];

        return $this->view('admin/subscribers', 'subscribers', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_subscribers'),
            'subscriptions' => $rows,
            'plans' => (new PlanRepository())->all(),
        ]);
    }

    public function grant(): never
    {
        try {
            (new SubscriptionService())->grant(
                (int)request()->post('user_id'), (int)request()->post('plan_id'),
                max(1, (int)request()->post('duration_value', 30)), (string)request()->post('duration_unit', 'days'),
                (int)(get_user()['id'] ?? 0), (string)request()->post('comment', '')
            );
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_grant_saved'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/admin/subscriptions/subscribers'));
    }

    public function payments(): string
    {
        $rows = db()->query(
            'SELECT sp.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name FROM subscription_payments sp INNER JOIN users u ON u.id = sp.user_id INNER JOIN subscription_plans p ON p.id = sp.plan_id ORDER BY sp.created_at DESC LIMIT 500'
        )->get() ?: [];

        return $this->view('admin/payments', 'payments', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_payments'),
            'payments' => $rows,
        ]);
    }

    public function fields(): string
    {
        return $this->view('admin/fields', 'fields', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_profile_fields'),
            'fields' => (new ProfileRepository())->fields(),
        ]);
    }

    public function fieldForm(): string
    {
        $id = (int)get_route_param('id');
        $profiles = new ProfileRepository();
        if (request()->isPost()) {
            try {
                $profiles->saveField(request()->getData(), $id > 0 ? $id : null);
                session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_field_saved'));
                response()->redirect(base_href('/admin/subscriptions/profile-fields'));
            } catch (\Throwable $exception) {
                session()->setFlash('error', $exception->getMessage());
                response()->redirect($id ? base_href('/admin/subscriptions/profile-fields/edit/' . $id) : base_href('/admin/subscriptions/profile-fields/create'));
            }
        }
        $field = null;
        foreach ($profiles->fields() as $candidate) {
            if ((int)$candidate['id'] === $id) {
                $field = $candidate;
                break;
            }
        }
        if ($id > 0 && !$field) {
            abort();
        }

        return $this->view('admin/field-form', 'fields', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_field_edit'),
            'field' => $field,
            'field_types' => ProfileRepository::FIELD_TYPES,
            'plans' => (new PlanRepository())->all(),
        ]);
    }

    public function deleteField(): never
    {
        try {
            (new ProfileRepository())->deleteField((int)request()->post('id'));
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_field_deleted'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/admin/subscriptions/profile-fields'));
    }

    public function settings(): string
    {
        $settings = new SettingsService();
        if (request()->isPost()) {
            try {
                $settings->save(request()->getData());
                session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_settings_saved'));
            } catch (\Throwable $exception) {
                session()->setFlash('error', $exception->getMessage());
            }
            response()->redirect(base_href('/admin/subscriptions/settings'));
        }

        return $this->view('admin/settings', 'settings', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_settings'),
            'settings' => $settings->current(),
        ]);
    }

    private function view(string $view, string $tab, array $data): string
    {
        return plugin_view('subscriptions', $view, \FireballPluginSubscriptions::viewData($data + [
            'tabs' => \FireballPluginSubscriptions::tabs($tab),
        ]));
    }
}
