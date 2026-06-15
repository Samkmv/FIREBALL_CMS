<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use FBL\Auth;
use FBL\File;
use FBL\RateLimiter;

/**
 * Обрабатывает аутентификацию, регистрацию, восстановление пароля и профиль пользователя.
 */
class AuthController extends BaseController
{

    protected const MAX_LOGIN_ATTEMPTS = 5;
    protected const LOGIN_LOCK_SECONDS = 600;
    protected const TWO_FACTOR_PENDING_SECONDS = 300;
    protected const TWO_FACTOR_SETUP_SECONDS = 600;

    protected User $users;

    /**
     * Инициализирует модель пользователей для всех сценариев авторизации.
     */
    public function __construct()
    {
        parent::__construct();
        $this->users = new User();
    }

    /**
     * Показывает форму входа и обрабатывает попытку авторизации пользователя.
     */
    public function login()
    {
        if (request()->isGet()) {
            return view('auth/login', [
                'title' => return_translation('auth_login_title'),
            ]);
        }

        $data = request()->getData();
        $data['login'] = trim((string)($data['login'] ?? ''));
        $this->assertValidCsrfToken('/login', [
            'login' => $data['login'],
        ]);
        $errors = $this->users->validateLogin($data);

        if ($errors) {
            $this->setFormState([
                'login' => $data['login'],
            ], $errors);
            response()->redirect(base_href('/login'));
        }

        if ($this->isLoginLocked($data['login'])) {
            $this->setFormState([
                'login' => $data['login'],
            ], [
                'login' => [return_translation('auth_login_rate_limited')],
            ]);
            session()->setFlash('error', return_translation('auth_login_rate_limited'));
            response()->redirect(base_href('/login'));
        }

        $user = Auth::validateCredentials([
            'login' => $data['login'],
            'password' => (string)($data['password'] ?? ''),
        ]);
        if (!$user) {
            $this->setFormState([
                'login' => $data['login'],
            ], [
                'password' => [return_translation('auth_login_invalid_credentials')],
            ]);
            session()->setFlash('error', return_translation('auth_login_invalid_credentials'));
            response()->redirect(base_href('/login'));
        }

        if ($this->users->hasTwoFactorEnabled($user)) {
            session()->set('auth.two_factor_pending', [
                'user_id' => (int)$user['id'],
                'login' => (string)$user['login'],
                'expires_at' => time() + self::TWO_FACTOR_PENDING_SECONDS,
            ]);
            $this->clearFormState();
            response()->redirect(base_href('/two-factor-challenge'));
        }

        Auth::loginUser($user);
        $this->clearLoginThrottle($data['login']);
        $this->clearFormState();
        session()->setFlash('success', return_translation('auth_login_success'));
        response()->redirect(base_href('/profile'));
    }

    /**
     * Completes a sign-in protected by an authenticator code or recovery code.
     */
    public function twoFactorChallenge()
    {
        $pending = session()->get('auth.two_factor_pending');
        if (!is_array($pending) || (int)($pending['expires_at'] ?? 0) < time()) {
            session()->remove('auth.two_factor_pending');
            session()->setFlash('error', return_translation('auth_two_factor_challenge_expired'));
            response()->redirect(base_href('/login'));
        }

        $user = $this->users->findById((int)($pending['user_id'] ?? 0));
        if (!$user || !$this->users->hasTwoFactorEnabled($user)) {
            session()->remove('auth.two_factor_pending');
            response()->redirect(base_href('/login'));
        }

        if (request()->isGet()) {
            return view('auth/two_factor_challenge', [
                'title' => return_translation('auth_two_factor_challenge_title'),
            ]);
        }

        $this->assertValidCsrfToken('/two-factor-challenge');
        $code = trim((string)request()->post('code', ''));
        $throttleKey = 'auth.two-factor.' . (int)$user['id'] . '|' . client_ip();
        if (!RateLimiter::attempt($throttleKey, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_SECONDS)) {
            session()->setFlash('error', return_translation('auth_two_factor_rate_limited'));
            response()->redirect(base_href('/two-factor-challenge'));
        }

        $verified = $this->users->verifyTwoFactorCode($user, $code)
            || $this->users->consumeRecoveryCode((int)$user['id'], $code);
        if (!$verified) {
            $this->setFormState([], [
                'code' => [return_translation('auth_two_factor_invalid_code')],
            ]);
            session()->setFlash('error', return_translation('auth_two_factor_invalid_code'));
            response()->redirect(base_href('/two-factor-challenge'));
        }

        RateLimiter::clear($throttleKey);
        $this->clearLoginThrottle((string)($pending['login'] ?? $user['login'] ?? ''));
        session()->remove('auth.two_factor_pending');
        $this->clearFormState();
        Auth::loginUser($user);
        session()->setFlash('success', return_translation('auth_login_success'));
        response()->redirect(base_href('/profile'));
    }

    /**
     * Принимает запрос на восстановление пароля и отправляет письмо со ссылкой сброса.
     */
    public function forgotPassword()
    {
        $data = [
            'reset_email' => mb_strtolower(trim((string)request()->post('reset_email', ''))),
        ];

        $this->assertValidCsrfToken('/login', $data);
        $errors = $this->users->validatePasswordResetRequest([
            'email' => $data['reset_email'],
        ]);

        if ($errors) {
            $this->setFormState($data, $errors);
            response()->redirect(base_href('/login'));
        }

        if (!RateLimiter::attempt($this->passwordResetThrottleKey(), 3, 3600)) {
            $this->clearFormState();
            session()->setFlash('success', return_translation('auth_reset_request_success'));
            response()->redirect(base_href('/login'));
        }

        $token = $this->users->createPasswordResetToken($data['reset_email']);
        if ($token !== null) {
            $mailSent = send_mail(
                [$data['reset_email']],
                return_translation('auth_reset_email_subject'),
                'auth/password_reset_email',
                [
                    'reset_url' => base_href('/reset-password?token=' . urlencode($token)),
                    'expires_in_minutes' => 60,
                ]
            );

            if (!$mailSent) {
                $this->setFormState($data, []);
                session()->setFlash('error', return_translation('auth_reset_request_error'));
                response()->redirect(base_href('/login'));
            }
        }

        $this->clearFormState();
        session()->setFlash('success', return_translation('auth_reset_request_success'));
        response()->redirect(base_href('/login'));
    }

    /**
     * Показывает форму сброса пароля и обрабатывает установку нового пароля по токену.
     */
    public function resetPassword()
    {
        $token = trim((string)request()->get('token', request()->post('token', '')));
        $resetRequest = $token !== '' ? $this->users->findActivePasswordResetByToken($token) : false;

        if (request()->isGet()) {
            if (!$resetRequest) {
                session()->setFlash('error', return_translation('auth_reset_invalid_token'));
                response()->redirect(base_href('/login'));
            }

            return view('auth/reset_password', [
                'title' => return_translation('auth_reset_title'),
                'token' => $token,
                'reset_request' => $resetRequest,
            ]);
        }

        $this->assertValidCsrfToken('/reset-password?token=' . urlencode($token), [
            'token' => $token,
        ]);

        if (!$resetRequest) {
            session()->setFlash('error', return_translation('auth_reset_invalid_token'));
            response()->redirect(base_href('/login'));
        }

        $data = [
            'token' => $token,
            'password' => (string)request()->post('password', ''),
            'password_confirmation' => (string)request()->post('password_confirmation', ''),
        ];

        $errors = $this->users->validatePasswordReset($data);
        if ($errors) {
            $this->setFormState([
                'token' => $token,
            ], $errors);
            response()->redirect(base_href('/reset-password?token=' . urlencode($token)));
        }

        if (!$this->users->resetPasswordByToken($token, $data['password'])) {
            session()->setFlash('error', return_translation('auth_reset_invalid_token'));
            response()->redirect(base_href('/login'));
        }

        $this->clearFormState();
        session()->setFlash('success', return_translation('auth_reset_success'));
        response()->redirect(base_href('/login'));
    }

    /**
     * Показывает форму регистрации и создаёт нового пользователя.
     */
    public function register()
    {
        if (request()->isGet()) {
            return view('auth/register', [
                'title' => return_translation('auth_register_title'),
            ]);
        }

        $data = request()->getData();
        $data['login'] = trim((string)($data['login'] ?? ''));
        $data['email'] = mb_strtolower(trim((string)($data['email'] ?? '')));
        $data['privacy_accepted'] = !empty($data['privacy_accepted']) ? 1 : 0;
        $this->assertValidCsrfToken('/register', [
            'name' => trim((string)($data['name'] ?? '')),
            'login' => $data['login'],
            'email' => $data['email'],
        ]);
        $errors = $this->users->validateRegistration($data);
        if (empty($data['privacy_accepted'])) {
            $errors['privacy_accepted'][] = return_translation('auth_validation_privacy_required');
        }

        if ($errors) {
            $this->setFormState([
                'name' => trim((string)($data['name'] ?? '')),
                'login' => $data['login'],
                'email' => $data['email'],
                'privacy_accepted' => $data['privacy_accepted'],
            ], $errors);
            response()->redirect(base_href('/register'));
        }

        $this->users->create($data);

        Auth::login([
            'login' => $data['login'],
            'password' => (string)$data['password'],
        ]);

        $this->clearFormState();
        session()->setFlash('success', return_translation('auth_register_success'));
        response()->redirect(base_href('/profile'));
    }

    /**
     * Показывает профиль пользователя и обрабатывает обновление данных или аватара.
     */
    public function profile()
    {
        $user = $this->users->findById((int)get_user()['id']);

        if (!$user) {
            logout();
            session()->setFlash('error', return_translation('auth_profile_not_found'));
            response()->redirect(base_href('/login'));
        }

        $twoFactorSetup = session()->get('auth.two_factor_setup');
        if (
            is_array($twoFactorSetup)
            && (
                (int)($twoFactorSetup['user_id'] ?? 0) !== (int)$user['id']
                || (int)($twoFactorSetup['expires_at'] ?? 0) < time()
            )
        ) {
            session()->remove('auth.two_factor_setup');
            $twoFactorSetup = null;
        }

        if (request()->isPost()) {
            $action = trim((string)request()->post('profile_action', 'avatar'));

            if ($action === 'two_factor_prepare') {
                $this->assertValidCsrfToken('/profile');
                $currentPassword = (string)request()->post('current_password', '');
                if (!password_verify($currentPassword, (string)$user['password'])) {
                    $this->setFormState([], [
                        'two_factor_current_password' => [return_translation('auth_validation_current_password')],
                    ]);
                    response()->redirect(base_href('/profile'));
                }

                $twoFactor = new TwoFactorService();
                $secret = $twoFactor->generateSecret();
                session()->set('auth.two_factor_setup', [
                    'user_id' => (int)$user['id'],
                    'secret' => $secret,
                    'expires_at' => time() + self::TWO_FACTOR_SETUP_SECONDS,
                ]);
                $this->clearFormState();
                response()->redirect(base_href('/profile'));
            }

            if ($action === 'two_factor_confirm') {
                $this->assertValidCsrfToken('/profile');
                $setup = session()->get('auth.two_factor_setup');
                $code = trim((string)request()->post('code', ''));
                if (
                    !is_array($setup)
                    || (int)($setup['user_id'] ?? 0) !== (int)$user['id']
                    || (int)($setup['expires_at'] ?? 0) < time()
                ) {
                    session()->remove('auth.two_factor_setup');
                    session()->setFlash('error', return_translation('auth_two_factor_setup_expired'));
                    response()->redirect(base_href('/profile'));
                }

                $twoFactor = new TwoFactorService();
                if (!$twoFactor->verifyCode((string)$setup['secret'], $code)) {
                    $this->setFormState([], [
                        'two_factor_code' => [return_translation('auth_two_factor_invalid_code')],
                    ]);
                    response()->redirect(base_href('/profile'));
                }

                $recoveryCodes = $twoFactor->generateRecoveryCodes();
                $this->users->enableTwoFactor((int)$user['id'], (string)$setup['secret'], $recoveryCodes);
                session()->remove('auth.two_factor_setup');
                session()->set('auth.two_factor_recovery_codes', $recoveryCodes);
                $this->clearFormState();
                session()->setFlash('success', return_translation('auth_two_factor_enabled'));
                response()->redirect(base_href('/profile'));
            }

            if ($action === 'two_factor_disable') {
                $this->assertValidCsrfToken('/profile');
                $currentPassword = (string)request()->post('current_password', '');
                $code = trim((string)request()->post('code', ''));
                $validPassword = password_verify($currentPassword, (string)$user['password']);
                $validCode = $validPassword && (
                    $this->users->verifyTwoFactorCode($user, $code)
                    || $this->users->consumeRecoveryCode((int)$user['id'], $code)
                );

                if (!$validPassword || !$validCode) {
                    $this->setFormState([], [
                        'two_factor_disable' => [return_translation('auth_two_factor_disable_invalid')],
                    ]);
                    response()->redirect(base_href('/profile'));
                }

                $this->users->disableTwoFactor((int)$user['id']);
                session()->remove('auth.two_factor_setup');
                $this->clearFormState();
                session()->setFlash('success', return_translation('auth_two_factor_disabled'));
                response()->redirect(base_href('/profile'));
            }

            if ($action === 'details') {
                $data = [
                    'name' => trim((string)request()->post('name', '')),
                    'login' => trim((string)request()->post('login', '')),
                    'email' => mb_strtolower(trim((string)request()->post('email', ''))),
                    'current_password' => (string)request()->post('current_password', ''),
                    'password' => (string)request()->post('password', ''),
                    'password_confirmation' => (string)request()->post('password_confirmation', ''),
                ];

                $errors = $this->users->validateProfileUpdate($data, (int)$user['id']);
                if (!empty($errors)) {
                    $this->setFormState([
                        'name' => $data['name'],
                        'login' => $data['login'],
                        'email' => $data['email'],
                    ], $errors);
                    response()->redirect(base_href('/profile'));
                }

                $this->users->updateProfile((int)$user['id'], $data);
                Auth::setUser();
                session()->regenerateId();
                app()->regenerateCSRFToken();
                $this->clearFormState();
                session()->setFlash('success', return_translation('auth_profile_updated'));
                response()->redirect(base_href('/profile'));
            }

            $avatarFile = new File('avatar_file');

            if (!$avatarFile->isFile && $avatarFile->getError() === UPLOAD_ERR_NO_FILE) {
                session()->set('form_errors', [
                    'avatar_file' => [return_translation('auth_profile_avatar_required')],
                ]);
                session()->setFlash('error', return_translation('auth_profile_avatar_required'));
                response()->redirect(base_href('/profile'));
            }

            $errors = $this->users->validateAvatarFile($avatarFile);
            if (!empty($errors)) {
                session()->set('form_errors', $errors);
                session()->setFlash('error', $errors['avatar_file'][0] ?? return_translation('auth_profile_avatar_upload_error'));
                response()->redirect(base_href('/profile'));
            }

            $avatar = $this->users->storeAvatar($avatarFile, $user['avatar'] ?? null);
            if ($avatar === false) {
                session()->set('form_errors', [
                    'avatar_file' => [return_translation('auth_profile_avatar_upload_error')],
                ]);
                session()->setFlash('error', return_translation('auth_profile_avatar_upload_error'));
                response()->redirect(base_href('/profile'));
            }

            $this->users->updateAvatar((int)$user['id'], $avatar);
            Auth::setUser();
            $this->clearFormState();
            session()->setFlash('success', return_translation('auth_profile_avatar_updated'));
            response()->redirect(base_href('/profile'));
        }

        $recoveryCodes = session()->get('auth.two_factor_recovery_codes', []);
        session()->remove('auth.two_factor_recovery_codes');
        $twoFactor = new TwoFactorService();
        $twoFactorUri = is_array($twoFactorSetup)
            ? $twoFactor->provisioningUri(
                (string)$twoFactorSetup['secret'],
                (string)($user['email'] ?? $user['login']),
                (string)site_setting('site_title', SITE_NAME)
            )
            : '';

        return view('auth/profile', [
            'title' => return_translation('auth_profile_title'),
            'user' => $user,
            'two_factor_setup' => $twoFactorSetup,
            'two_factor_uri' => $twoFactorUri,
            'two_factor_qr_code' => $twoFactor->qrCodeDataUri($twoFactorUri),
            'two_factor_recovery_codes' => is_array($recoveryCodes) ? $recoveryCodes : [],
        ]);
    }

    /**
     * Завершает пользовательскую сессию и перенаправляет на главную страницу.
     */
    public function logout()
    {
        logout();
        session()->setFlash('success', return_translation('auth_profile_logout_success'));
        response()->redirect(base_href('/'));
    }

    /**
     * Сохраняет данные формы и ошибки в сессию для повторного отображения.
     */
    protected function setFormState(array $data, array $errors): void
    {
        session()->set('form_data', $data);
        session()->set('form_errors', $errors);
    }

    /**
     * Очищает временные данные формы и ошибки из сессии.
     */
    protected function clearFormState(): void
    {
        session()->remove('form_data');
        session()->remove('form_errors');
    }

    /**
     * Проверяет, заблокированы ли попытки входа для указанного логина.
     */
    protected function isLoginLocked(string $login): bool
    {
        return !RateLimiter::attempt(
            $this->loginThrottleKey($login),
            self::MAX_LOGIN_ATTEMPTS,
            self::LOGIN_LOCK_SECONDS
        );
    }

    /**
     * Сбрасывает состояние ограничения попыток входа для логина.
     */
    protected function clearLoginThrottle(string $login): void
    {
        RateLimiter::clear($this->loginThrottleKey($login));
    }

    /**
     * Формирует ключ сессии для хранения ограничений входа по логину и IP-адресу.
     */
    protected function loginThrottleKey(string $login): string
    {
        $ip = client_ip();
        return 'auth.login.' . make_slug($login, '') . '|' . $ip;
    }

    protected function passwordResetThrottleKey(): string
    {
        $ip = client_ip();

        return 'auth.password-reset|' . $ip;
    }

    /**
     * Проверяет CSRF-токен, обновляет его и возвращает пользователя на форму при ошибке.
     */
    protected function assertValidCsrfToken(string $redirectPath, array $formData = []): void
    {
        $sessionToken = (string)session()->get('needCSRFToken', '');
        $requestToken = (string)request()->post('needCSRFToken', '');

        if ($sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken)) {
            app()->regenerateCSRFToken();
            return;
        }

        app()->regenerateCSRFToken();

        if ($formData !== []) {
            session()->set('form_data', $formData);
        }

        session()->set('form_errors', [
            'needCSRFToken' => [return_translation('tpl_security_error')],
        ]);
        session()->setFlash('error', return_translation('tpl_security_error'));
        response()->redirect(base_href($redirectPath));
    }

}
