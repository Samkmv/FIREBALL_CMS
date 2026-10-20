<?php

namespace Fireball\Subscriptions\Support;

/** The same canonical Russian phone identity as the CMS account forms. */
final class RussianPhone
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        try {
            if (class_exists(\App\Services\AuthIdentity::class)) {
                return \App\Services\AuthIdentity::phone($value) ?? '';
            }
            // Independent plugin updates also work with older CMS releases.
            if (preg_match('/^\+?[0-9() .-]+$/D', $value) !== 1) throw new \InvalidArgumentException();
            $digits = preg_replace('/\D/', '', $value);
            if (strlen($digits) === 10) $digits = '7' . $digits;
            if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
            if (preg_match('/^7[3489][0-9]{9}$/D', $digits) !== 1) throw new \InvalidArgumentException();
            return '+' . $digits;
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_phone'));
        }
    }

    /** Read legacy values without silently erasing or rewriting invalid data. */
    public static function canonicalOrOriginal(string $value): string
    {
        try { return self::normalize($value); }
        catch (\InvalidArgumentException) { return trim($value); }
    }

    public static function display(string $value): string
    {
        $value = self::canonicalOrOriginal($value);
        return preg_match('/^\+7([3489][0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/D', $value, $parts)
            ? '+7 (' . $parts[1] . ') ' . $parts[2] . '-' . $parts[3] . '-' . $parts[4]
            : $value;
    }
}
