<?php

namespace App\Controllers;

use App\Models\User;
use FBL\Auth;
use FBL\File;

/**
 * Обрабатывает аутентификацию, регистрацию, восстановление пароля и профиль пользователя.
 */
class AuthController extends BaseController
{

    protected const MAX_LOGIN_ATTEMPTS = 5;
    protected const LOGIN_LOCK_SECONDS = 600;

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

        if (!Auth::login([
            'login' => $data['login'],
            'password' => (string)($data['password'] ?? ''),
        ])) {
            $this->recordLoginFailure($data['login']);
            $this->setFormState([
                'login' => $data['login'],
            ], [
                'login' => [return_translation('auth_login_invalid_credentials')],
            ]);
            session()->setFlash('error', return_translation('auth_login_invalid_credentials'));
            response()->redirect(base_href('/login'));
        }

        $this->clearLoginThrottle($data['login']);
        $this->clearFormState();
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
        $this->assertValidCsrfToken('/register', [
            'name' => trim((string)($data['name'] ?? '')),
            'login' => $data['login'],
            'email' => $data['email'],
        ]);
        $errors = $this->users->validateRegistration($data);

        if ($errors) {
            $this->setFormState([
                'name' => trim((string)($data['name'] ?? '')),
                'login' => $data['login'],
                'email' => $data['email'],
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

        if (request()->isPost()) {
            $action = trim((string)request()->post('profile_action', 'avatar'));

            if ($action === 'details') {
                $data = [
                    'name' => trim((string)request()->post('name', '')),
                    'login' => trim((string)request()->post('login', '')),
                    'email' => mb_strtolower(trim((string)request()->post('email', ''))),
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

            return view('auth/profile', [
            'title' => return_translation('auth_profile_title'),
            'user' => $user,
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
        $state = session()->get($this->loginThrottleKey($login), []);
        $lockedUntil = (int)($state['locked_until'] ?? 0);

        if ($lockedUntil <= time()) {
            if ($lockedUntil > 0) {
                session()->remove($this->loginThrottleKey($login));
            }
            return false;
        }

        return true;
    }

    /**
     * Учитывает неудачную попытку входа и при необходимости включает временную блокировку.
     */
    protected function recordLoginFailure(string $login): void
    {
        $key = $this->loginThrottleKey($login);
        $state = session()->get($key, []);
        if ((int)($state['locked_until'] ?? 0) > 0 && (int)$state['locked_until'] <= time()) {
            $state = [];
        }
        $attempts = (int)($state['attempts'] ?? 0) + 1;

        session()->set($key, [
            'attempts' => $attempts,
            'locked_until' => $attempts >= self::MAX_LOGIN_ATTEMPTS ? time() + self::LOGIN_LOCK_SECONDS : 0,
        ]);
    }

    /**
     * Сбрасывает состояние ограничения попыток входа для логина.
     */
    protected function clearLoginThrottle(string $login): void
    {
        session()->remove($this->loginThrottleKey($login));
    }

    /**
     * Формирует ключ сессии для хранения ограничений входа по логину и IP-адресу.
     */
    protected function loginThrottleKey(string $login): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return 'auth.login_throttle.' . hash('sha256', make_slug($login, '') . '|' . $ip);
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
