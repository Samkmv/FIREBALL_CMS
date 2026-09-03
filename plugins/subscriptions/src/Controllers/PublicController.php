<?php

namespace Fireball\Subscriptions\Controllers;

use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Repositories\ProfileRepository;
use Fireball\Subscriptions\Services\AccessService;
use Fireball\Subscriptions\Services\CheckoutService;
use Fireball\Subscriptions\Services\MediaTokenService;
use Fireball\Subscriptions\Services\PaymentService;
use Fireball\Subscriptions\Services\SubscriptionEligibilityService;
use Fireball\Subscriptions\Services\SubscriptionService;
use FBL\Pagination;

final class PublicController
{
    public function plans(): string
    {
        return plugin_view('subscriptions', 'public/plans', \FireballPluginSubscriptions::viewData([
            'title' => \FireballPluginSubscriptions::t('subscriptions_plans_title'),
            'plans' => (new PlanRepository())->all(true),
        ]));
    }

    public function account(): string
    {
        $userId = $this->userId();
        $subscription = (new AccessService())->activeSubscription($userId);
        $paymentsPerPage = 15;
        $paymentsTotal = (int)db()->query(
            'SELECT COUNT(*)
             FROM subscription_payments sp
             INNER JOIN subscription_plans p ON p.id = sp.plan_id
             WHERE sp.user_id = ?',
            [$userId]
        )->getColumn();
        $paymentsPagination = new Pagination($paymentsTotal, $paymentsPerPage);
        $paymentsOffset = $paymentsPagination->getOffset();
        $payments = db()->query(
            "SELECT sp.*, p.name AS plan_name
             FROM subscription_payments sp
             INNER JOIN subscription_plans p ON p.id = sp.plan_id
             WHERE sp.user_id = ?
             ORDER BY sp.created_at DESC, sp.id DESC
             LIMIT {$paymentsOffset}, {$paymentsPerPage}",
            [$userId]
        )->get() ?: [];

        return plugin_view('subscriptions', 'public/account', \FireballPluginSubscriptions::viewData([
            'title' => \FireballPluginSubscriptions::t('subscriptions_account_title'),
            'subscription' => $subscription,
            'payments' => $payments,
            'payments_total' => $paymentsTotal,
            'payments_pagination' => $paymentsPagination,
            'permissions' => $subscription ? (new AccessService())->permissions((int)$subscription['plan_id']) : [],
        ]));
    }

    public function profile(): string
    {
        $userId = $this->userId();
        $profiles = new ProfileRepository();
        if (request()->isPost()) {
            try {
                $returnTo = trim((string)request()->post('return_to', ''));
                $savedProfile = $profiles->saveProfile(
                    $userId,
                    request()->getData(),
                    $this->checkoutPlanId($returnTo)
                );
                $eligible = !isset($savedProfile['eligibility']) || !empty($savedProfile['eligibility']['eligible']);
                session()->setFlash(
                    'success',
                    \FireballPluginSubscriptions::t($eligible ? 'subscriptions_profile_saved' : 'subscriptions_address_included_in_utilities')
                );
                if (!$eligible) {
                    response()->redirect(base_href('/account/subscription'));
                }
                if ($eligible && $returnTo !== '' && str_starts_with($returnTo, base_href('/subscriptions/checkout/'))) {
                    response()->redirect($returnTo);
                }
            } catch (\Throwable $exception) {
                session()->setFlash('error', $exception->getMessage());
                session()->set('subscriptions.profile_data', request()->getData());
            }
            response()->redirect(base_href('/profile/subscription-details'));
        }
        $profile = $profiles->profileForUser($userId) ?: [];

        $formData = (array)session()->get('subscriptions.profile_data', []);
        session()->remove('subscriptions.profile_data');

        return plugin_view('subscriptions', 'public/profile', \FireballPluginSubscriptions::viewData([
            'title' => \FireballPluginSubscriptions::t('subscriptions_profile_title'),
            'profile' => $profile,
            'fields' => $profiles->fields(true),
            'completion' => $profiles->completion($profile),
            'form_data' => $formData,
        ]));
    }

    public function checkout(): string
    {
        $userId = $this->userId();
        $planId = (int)get_route_param('id');
        $plan = (new PlanRepository())->find($planId, true);
        if (!$plan || !$plan['is_public']) {
            abort();
        }
        $profiles = new ProfileRepository();
        $profile = $profiles->profileForUser($userId) ?: [];
        $completion = $profiles->completion($profile, $planId);
        if (!$completion['complete']) {
            session()->setFlash('warning', \FireballPluginSubscriptions::t('subscriptions_error_profile_incomplete'));
            session()->set('subscriptions.checkout_return', base_href('/subscriptions/checkout/' . $planId));
            response()->redirect(base_href('/profile/subscription-details'));
        }
        try {
            (new SubscriptionEligibilityService())->assertEligible($userId, 'checkout_view', $profile);
        } catch (\DomainException $exception) {
            $this->activateUtilityAccess($userId, $planId);
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_address_included_in_utilities'));
            response()->redirect(base_href('/account/subscription'));
        }

        return plugin_view('subscriptions', 'public/checkout', \FireballPluginSubscriptions::viewData([
            'title' => \FireballPluginSubscriptions::t('subscriptions_checkout_title'),
            'plan' => $plan,
            'profile' => $profile,
        ]));
    }

    public function createPayment(): never
    {
        $userId = $this->userId();
        $planId = (int)request()->post('plan_id');
        try {
            $checkout = (new CheckoutService())->create($userId, $planId, [
                'offer' => !empty(request()->post('consent_offer')),
                'privacy' => !empty(request()->post('consent_privacy')),
                'recurring' => !empty(request()->post('consent_recurring')),
                'accepted_at' => date(DATE_ATOM),
                'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $userId),
            ]);
            $this->redirectToRobokassa((string)$checkout['url']);
        } catch (\Throwable $exception) {
            log_error_details('Subscription checkout failed', ['user_id' => $userId, 'plan_id' => $planId], $exception);
            if ($exception instanceof \DomainException && $exception->getMessage() === \FireballPluginSubscriptions::t('subscriptions_address_included_in_utilities')) {
                $this->activateUtilityAccess($userId, $planId);
                session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_address_included_in_utilities'));
                response()->redirect(base_href('/account/subscription'));
            }
            if (str_contains($exception->getMessage(), \FireballPluginSubscriptions::t('subscriptions_error_profile_incomplete'))) {
                session()->setFlash('error', $exception->getMessage());
                response()->redirect(base_href('/profile/subscription-details'));
            }
            $message = $this->checkoutErrorMessage($exception);
            session()->setFlash('error', $message);
            response()->redirect(base_href('/subscriptions/checkout/' . $planId));
        }
    }

    public function result(): never
    {
        $payload = array_replace(request()->get, request()->post);
        try {
            $response = (new PaymentService())->processRobokassaResult($payload);
            header('Content-Type: text/plain; charset=utf-8');
            response()->text($response, 200);
        } catch (\Throwable $exception) {
            log_error_details('Robokassa ResultURL rejected', ['invoice_id' => (string)($payload['InvId'] ?? '')], $exception);
            header('Content-Type: text/plain; charset=utf-8');
            response()->text('ERROR', 400);
        }
    }

    public function success(): string
    {
        $payload = array_replace(request()->get, request()->post);
        $invoiceId = (int)($payload['InvId'] ?? $payload['InvoiceID'] ?? 0);
        $verified = false;
        try {
            $verified = (new RobokassaGateway())->verifySuccess($payload);
        } catch (\Throwable) {
            // A missing or invalid browser signature never activates a subscription.
        }
        $payment = $verified && $invoiceId > 0
            ? db()->query('SELECT status FROM subscription_payments WHERE invoice_id = ? AND user_id = ? LIMIT 1', [$invoiceId, $this->userId(false)])->getOne()
            : null;

        return plugin_view('subscriptions', 'public/payment-result', \FireballPluginSubscriptions::viewData([
            'title' => \FireballPluginSubscriptions::t('subscriptions_payment_result_title'),
            'success' => true,
            'activated' => $payment && (string)$payment['status'] === 'paid',
        ]));
    }

    public function fail(): string
    {
        return plugin_view('subscriptions', 'public/payment-result', \FireballPluginSubscriptions::viewData([
            'title' => \FireballPluginSubscriptions::t('subscriptions_payment_result_title'),
            'success' => false,
            'activated' => false,
        ]));
    }

    public function autoRenew(): never
    {
        try {
            (new SubscriptionService())->setAutoRenew($this->userId(), !empty(request()->post('enabled')));
            session()->setFlash('success', \FireballPluginSubscriptions::t('subscriptions_auto_renew_saved'));
        } catch (\Throwable $exception) {
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/account/subscription'));
    }

    public function mediaToken(): never
    {
        $resourceType = (string)get_route_param('type');
        $resourceId = (string)get_route_param('id');
        $download = !empty(request()->post('download'));
        $permission = $resourceType === 'camera_archive'
            ? ($download ? 'camera_archive.download' : 'camera_archive.view')
            : 'videos.view_paid';
        try {
            $from = trim((string)request()->post('from', ''));
            $to = trim((string)request()->post('to', ''));
            $groups = [];
            if ($resourceType === 'camera_archive') {
                $fromTimestamp = strtotime($from);
                $toTimestamp = strtotime($to);
                if ($fromTimestamp === false || $toTimestamp === false || $toTimestamp <= $fromTimestamp || $toTimestamp > time() + 300) {
                    throw new \RuntimeException('Invalid camera archive time range.');
                }
                $from = date(DATE_ATOM, $fromTimestamp);
                $to = date(DATE_ATOM, $toTimestamp);
                $resolvedGroups = apply_filters('subscriptions_camera_groups_for_resource', [], $resourceId);
                if (is_array($resolvedGroups)) {
                    $groups = array_values(array_filter(array_unique(array_map('strval', $resolvedGroups)), static fn(string $id): bool => preg_match('/^[a-zA-Z0-9._:-]{1,190}$/', $id) === 1));
                }
            }
            $context = [
                'camera_id' => $resourceId,
                'camera_group_ids' => $groups,
                'from' => $from,
                'to' => $to,
                'download' => $download,
            ];
            $token = (new MediaTokenService())->issue($this->userId(), $permission, $resourceType, $resourceId, $context);
            response()->json(['status' => true, 'token' => $token, 'expires_in' => (int)(new \Fireball\Subscriptions\Services\SettingsService())->current()['media_token_ttl']]);
        } catch (\Throwable $exception) {
            response()->json(['status' => false, 'message' => \FireballPluginSubscriptions::t('subscriptions_access_denied')], 403);
        }
    }

    public function validateMediaToken(): never
    {
        header('Cache-Control: no-store, private');
        $resourceType = (string)get_route_param('type');
        $resourceId = (string)get_route_param('id');
        $claims = (new MediaTokenService())->verifyForResource((string)request()->get('token', ''), $resourceType, $resourceId);
        if (!$claims) {
            response()->json(['status' => false], 403);
        }
        response()->json([
            'status' => true,
            'user_id' => (int)$claims['uid'],
            'permission' => (string)$claims['permission'],
            'expires_at' => (int)$claims['exp'],
            'context' => (array)($claims['context'] ?? []),
        ]);
    }

    private function userId(bool $required = true): int
    {
        $id = (int)(get_user()['id'] ?? 0);
        if ($required && $id <= 0) {
            abort('', 401);
        }

        return $id;
    }

    private function checkoutPlanId(string $returnTo): ?int
    {
        $path = parse_url($returnTo, PHP_URL_PATH);
        if (!is_string($path) || preg_match('~/subscriptions/checkout/([1-9][0-9]*)/?$~', $path, $matches) !== 1) {
            return null;
        }

        return (int)$matches[1];
    }

    private function activateUtilityAccess(int $userId, int $planId): void
    {
        $eligibility = (new SubscriptionEligibilityService())->evaluateUser($userId, null, true);
        if (empty($eligibility['eligible'])) {
            (new SubscriptionService())->syncUtilityAccess($userId, $eligibility, $planId);
        }
    }

    private function checkoutErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        if ($message === \FireballPluginSubscriptions::t('subscriptions_error_recurring_disabled')) {
            return $this->recurringUnavailableMessage();
        }
        if (str_starts_with($message, \Fireball\Subscriptions\Services\SettingsService::CREDENTIALS_NOT_CONFIGURED)) {
            return $this->isAdministrativeUser()
                ? \FireballPluginSubscriptions::t('subscriptions_payment_configuration_error_detailed')
                : \FireballPluginSubscriptions::t('subscriptions_payment_configuration_error');
        }

        if ($this->isAdministrativeUser()) {
            return str_replace(
                [':type', ':error'],
                [get_class($exception), $message],
                \FireballPluginSubscriptions::t('subscriptions_checkout_error_detailed')
            );
        }

        $safeCustomerMessages = [
            \FireballPluginSubscriptions::t('subscriptions_error_plan_not_found'),
            \FireballPluginSubscriptions::t('subscriptions_error_consents'),
            \FireballPluginSubscriptions::t('subscriptions_error_recurring_consent'),
        ];

        return in_array($message, $safeCustomerMessages, true)
            ? $message
            : \FireballPluginSubscriptions::t('subscriptions_payment_configuration_error');
    }

    private function recurringUnavailableMessage(): string
    {
        return \FireballPluginSubscriptions::t(
            $this->isAdministrativeUser()
                ? 'subscriptions_error_recurring_disabled_detailed'
                : 'subscriptions_recurring_unavailable_customer'
        );
    }

    private function isAdministrativeUser(): bool
    {
        return in_array((string)(get_user()['role'] ?? ''), ['admin', 'creator'], true);
    }

    private function redirectToRobokassa(string $url): never
    {
        $url = trim(str_replace(["\r", "\n"], '', $url));
        $parts = parse_url($url);
        if (
            !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'auth.robokassa.ru'
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException('Invalid Robokassa payment URL.');
        }

        header('Location: ' . $url, true, 303);
        exit;
    }
}
