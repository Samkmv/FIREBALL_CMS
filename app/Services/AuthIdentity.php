<?php

namespace App\Services;

/** Canonical identifiers shared by forms, authentication and migrations. */
final class AuthIdentity
{
    public static function email(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function validEmail(string $email): bool
    {
        return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function phone(string $phone): ?string
    {
        $phone = trim($phone);
        if ($phone === '') return null;
        if (preg_match('/^\+?[0-9() .-]+$/D', $phone) !== 1) throw new \InvalidArgumentException('auth_phone_invalid');
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) $digits = '7' . $digits;
        if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
        if (preg_match('/^7[3489][0-9]{9}$/D', $digits) !== 1) throw new \InvalidArgumentException('auth_phone_invalid');
        return '+' . $digits;
    }

    public static function available(): bool
    {
        return in_array('email_verification_required', SchemaManifest::columns('users'), true);
    }

    /** One identifier can never resolve to different accounts, even in legacy data. */
    public static function findForLogin(string $identifier, ?array $policy = null): array|false
    {
        $identifier = trim($identifier);
        if ($identifier === '' || strlen($identifier) > 254) return false;
        $policy ??= (new AuthSettings())->all();
        $where = [];
        $params = [];
        if ($policy['auth_login_login'] === '1') {
            // Preserve historical slug normalization, but never turn an email
            // or formatted phone into somebody else's unrelated username.
            if (!str_contains($identifier, '@') && !preg_match('/[+()]/', $identifier)) {
                $login = make_slug($identifier, '');
                if ($login !== '' && mb_strlen($login) <= 50) {
                    $where[] = 'login = ?';
                    $params[] = $login;
                }
            }
        }
        $email = self::email($identifier);
        if ($policy['auth_login_email'] === '1' && self::validEmail($email)) {
            $where[] = self::available() ? 'email_normalized = ?' : 'LOWER(TRIM(email)) = ?';
            $params[] = $email;
        }
        if (self::available() && $policy['auth_phone_mode'] !== 'off' && $policy['auth_login_phone'] === '1') {
            try { $phone = self::phone($identifier); } catch (\InvalidArgumentException) { $phone = null; }
            if ($phone !== null) {
                $where[] = 'phone_normalized = ?';
                $params[] = $phone;
            }
        }
        if ($where === []) return false;
        $rows = db()->query('SELECT * FROM users WHERE ' . implode(' OR ', $where) . ' LIMIT 2', $params)->get() ?: [];
        return count($rows) === 1 ? $rows[0] : false;
    }
}
