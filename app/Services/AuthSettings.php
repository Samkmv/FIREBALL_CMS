<?php

namespace App\Services;

use App\Models\SiteSetting;

final class AuthSettings
{
    public const DEFAULTS = [
        'auth_phone_mode' => 'off',
        'auth_login_mode' => 'manual',
        'auth_login_login' => '1',
        'auth_login_email' => '1',
        'auth_login_phone' => '0',
        'auth_email_verification_required' => '1',
    ];

    public function all(): array
    {
        return array_intersect_key((new SiteSetting())->all(), self::DEFAULTS) + self::DEFAULTS;
    }

    public function normalize(array $input): array
    {
        return [
            'auth_phone_mode' => (string)($input['auth_phone_mode'] ?? 'off'),
            'auth_login_mode' => (string)($input['auth_login_mode'] ?? 'manual'),
            'auth_login_login' => !empty($input['auth_login_login']) ? '1' : '0',
            'auth_login_email' => !empty($input['auth_login_email']) ? '1' : '0',
            'auth_login_phone' => !empty($input['auth_login_phone']) ? '1' : '0',
            'auth_email_verification_required' => !empty($input['auth_email_verification_required']) ? '1' : '0',
        ];
    }

    public function emailVerificationRequired(?array $policy = null): bool
    {
        $policy ??= $this->all();
        return ($policy['auth_email_verification_required'] ?? self::DEFAULTS['auth_email_verification_required']) === '1';
    }

    public function validate(array $policy): array
    {
        $errors = [];
        if (!in_array($policy['auth_phone_mode'], ['off', 'optional', 'required'], true)
            || !in_array($policy['auth_login_mode'], ['manual', 'automatic'], true)) {
            $errors['auth_phone_mode'][] = return_translation('auth_policy_invalid');
        }
        if ($policy['auth_login_phone'] === '1' && $policy['auth_phone_mode'] === 'off') {
            $errors['auth_login_phone'][] = return_translation('auth_phone_login_disabled');
        }
        if (!in_array('1', [$policy['auth_login_login'], $policy['auth_login_email'], $policy['auth_login_phone']], true)) {
            $errors['auth_login_login'][] = return_translation('auth_login_method_required');
        }
        if ($policy['auth_login_login'] !== '1' && $policy['auth_login_email'] !== '1' && $policy['auth_phone_mode'] !== 'required') {
            $errors['auth_phone_mode'][] = return_translation('auth_policy_strands_users');
        }
        if ($errors) return $errors;
        // Reject a policy which would strand any existing account. Resolve via
        // the very same identifier matcher as login (including ambiguity checks).
        foreach (db()->query('SELECT id, login, email' . (AuthIdentity::available() ? ', phone_normalized' : '') . ' FROM users')->get() ?: [] as $user) {
            $reachable = false;
            foreach (['login', 'email', 'phone_normalized'] as $field) {
                if (trim((string)($user[$field] ?? '')) === '') continue;
                $match = AuthIdentity::findForLogin((string)$user[$field], $policy);
                if ($match && (int)$match['id'] === (int)$user['id']) { $reachable = true; break; }
            }
            if (!$reachable) {
                $errors['auth_login_login'][] = return_translation('auth_policy_strands_users');
                break;
            }
        }
        return $errors;
    }

    public function loginLabel(?array $policy = null): string
    {
        $policy ??= $this->all();
        $labels = [];
        if ($policy['auth_login_phone'] === '1' && $policy['auth_phone_mode'] !== 'off') $labels[] = return_translation('auth_identifier_phone');
        if ($policy['auth_login_email'] === '1') $labels[] = return_translation('auth_identifier_email');
        if ($policy['auth_login_login'] === '1') $labels[] = return_translation('auth_identifier_login');
        return implode(' / ', $labels);
    }
}
