<?php

namespace Fireball\Subscriptions\Controllers;

use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Repositories\ProfileRepository;
use Fireball\Subscriptions\Repositories\ContentRuleRepository;
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
        $search = trim((string)request()->get('q', ''));
        $status = trim((string)request()->get('status', ''));
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like];
        }
        if (in_array($status, ['active', 'disabled', 'pending', 'cancelled', 'grace_period', 'past_due', 'expired'], true)) {
            $where[] = 's.status = ?';
            $params[] = $status;
        } else {
            $status = '';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int)db()->query("SELECT COUNT(*) FROM subscriptions s INNER JOIN users u ON u.id = s.user_id {$whereSql}", $params)->getColumn();
        $pagination = new \FBL\Pagination($total, 20);
        $offset = $pagination->getOffset();
        $rows = db()->query(
            "SELECT s.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name
             FROM subscriptions s INNER JOIN users u ON u.id = s.user_id INNER JOIN subscription_plans p ON p.id = s.plan_id
             {$whereSql} ORDER BY s.created_at DESC LIMIT {$offset}, 20",
            $params
        )->get() ?: [];

        return $this->view('admin/subscribers', 'subscribers', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_subscribers'),
            'subscriptions' => $rows,
            'plans' => (new PlanRepository())->all(),
            'users' => db()->query('SELECT id, name, email FROM users ORDER BY name, email LIMIT 1000')->get() ?: [],
            'pagination' => $pagination,
            'total' => $total,
            'search' => $search,
            'status_filter' => $status,
        ]);
    }

    public function grant(): never
    {
        try {
            (new SubscriptionService())->grant(
                (int)request()->post('user_id'), (int)request()->post('plan_id'),
                max(1, (int)request()->post('duration_value', 30)), (string)request()->post('duration_unit', 'days'),
                (int)(get_user()['id'] ?? 0), (string)request()->post('comment', ''), (string)request()->post('source', 'manual')
            );
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_grant_saved'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/admin/subscriptions/subscribers'));
    }

    public function updateSubscriber(): never
    {
        try {
            (new SubscriptionService())->updateManaged(
                (int)request()->post('id'),
                (int)request()->post('plan_id'),
                (string)request()->post('status', 'disabled'),
                (string)request()->post('ends_at', ''),
                (int)(get_user()['id'] ?? 0)
            );
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_subscriber_saved'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/admin/subscriptions/subscribers'));
    }

    public function payments(): string
    {
        $search = trim((string)request()->get('q', ''));
        $where = ['sp.cleared_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR CAST(sp.invoice_id AS CHAR) LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int)db()->query("SELECT COUNT(*) FROM subscription_payments sp INNER JOIN users u ON u.id = sp.user_id {$whereSql}", $params)->getColumn();
        $pagination = new \FBL\Pagination($total, 25);
        $offset = $pagination->getOffset();
        $rows = db()->query(
            "SELECT sp.*, u.name AS user_name, u.email AS user_email, p.name AS plan_name FROM subscription_payments sp INNER JOIN users u ON u.id = sp.user_id INNER JOIN subscription_plans p ON p.id = sp.plan_id {$whereSql} ORDER BY sp.created_at DESC LIMIT {$offset}, 25",
            $params
        )->get() ?: [];

        return $this->view('admin/payments', 'payments', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_payments'),
            'payments' => $rows,
            'pagination' => $pagination,
            'total' => $total,
            'search' => $search,
        ]);
    }

    public function clearPayments(): never
    {
        db()->query('UPDATE subscription_payments SET cleared_at = ?, updated_at = ? WHERE cleared_at IS NULL', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_payments_cleared'));
        response()->redirect(base_href('/admin/subscriptions/payments'));
    }

    public function contentAccess(): string
    {
        $search = trim((string)request()->get('q', ''));
        $access = trim((string)request()->get('access', ''));
        $result = (new ContentRuleRepository())->paginatedPosts($search, $access);

        return $this->view('admin/content', 'content', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_content'),
            'posts' => $result['items'],
            'total' => $result['total'],
            'pagination' => $result['pagination'],
            'plans' => (new PlanRepository())->all(),
            'search' => $search,
            'access_filter' => $access,
        ]);
    }

    public function saveContentAccess(): never
    {
        $postId = (int)request()->post('id');
        try {
            $repository = new ContentRuleRepository();
            $current = $repository->find('post', $postId) ?: [
                'show_title' => 1, 'show_excerpt' => 1, 'show_image' => 1,
                'hide_video' => 0, 'required_permission' => 'posts.view_paid',
            ];
            $repository->save('post', $postId, [
                'subscription_access_mode' => request()->post('subscription_access_mode', 'public'),
                'subscription_plan_ids' => (array)request()->post('subscription_plan_ids', []),
                'subscription_show_title' => !empty($current['show_title']) ? '1' : '',
                'subscription_show_excerpt' => !empty($current['show_excerpt']) ? '1' : '',
                'subscription_show_image' => !empty($current['show_image']) ? '1' : '',
                'subscription_hide_video' => !empty($current['hide_video']) ? '1' : '',
                'subscription_required_permission' => (string)($current['required_permission'] ?? 'posts.view_paid'),
            ]);
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_content_saved'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/admin/subscriptions/content'));
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

        return $this->view('admin/settings', 'settings', [
            'title' => \FireballPluginSubscriptions::t('subscriptions_admin_settings'),
            'settings' => $settings->current(),
        ]);
    }

    public function saveSettings(): never
    {
        $data = request()->getData();
        $database = db();

        try {
            $database->beginTransaction();
            (new SettingsService())->save($data);
            $database->commit();
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_settings_saved'));
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            log_error_details('Robokassa settings save failed', [
                'Submitted keys' => array_values(array_diff(array_keys($data), ['password1', 'password2', 'needCSRFToken'])),
                'Merchant login provided' => trim((string)($data['merchant_login'] ?? '')) !== '',
                'Password 1 provided' => (string)($data['password1'] ?? '') !== '',
                'Password 2 provided' => (string)($data['password2'] ?? '') !== '',
            ], $exception);
            $messageKey = str_starts_with($exception->getMessage(), SettingsService::CREDENTIALS_NOT_CONFIGURED)
                ? 'subscriptions_settings_credentials_missing'
                : 'subscriptions_settings_save_failed';
            session()->setFlash('error', \FireballPluginSubscriptions::t($messageKey));
        }

        response()->redirect(base_href('/admin/subscriptions/settings'));
    }

    private function view(string $view, string $tab, array $data): string
    {
        return plugin_view('subscriptions', $view, \FireballPluginSubscriptions::viewData($data + [
            'tabs' => \FireballPluginSubscriptions::tabs($tab),
        ]));
    }
}
