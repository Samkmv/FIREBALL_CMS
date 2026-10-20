<?php

namespace App\Services;

/** Localizes audit codes at display time, including records from older releases. */
final class SecurityLogPresenter
{
    public const EVENTS = [
        'login', 'logout', 'login_2fa', 'login_session_created', 'session_revoked',
        'logout_other_devices', 'logout_all', 'two_factor_email_recovery_request',
        'two_factor_email_recovery_complete', 'password_reset_request',
        'password_reset_complete', 'registration', 'email_verification',
        'two_factor_enabled', 'two_factor_recovery_codes_created',
        'two_factor_disabled', 'profile_identity_update', 'admin_reset_2fa',
    ];
    public const RESULTS = ['success', 'failed', 'error', 'denied', 'warning', 'unknown'];
    public const REASONS = [
        'credentials_invalid', 'mail_not_configured', 'mail_failed',
        'invalid_token_format', 'token_missing', 'account_missing', 'token_used',
        'token_expired', 'account_email_changed', 'token_claim_failed',
        'storage_failure', 'identifier_conflict_or_storage_failure',
        'verification_storage_failed', 'verification_mail_failed', 'verification_sent',
        'verification_completed', 'verification_conflict_or_storage_failed',
        'email_not_verified', 'verification_code_invalid', 'verification_code_attempts_exhausted',
        'permission_denied', 'password_failed', '2fa_failed', 'csrf_invalid',
    ];
    private const STAGES = ['begin', 'lookup', 'claim', 'password', 'sessions', 'consume', 'commit'];

    public static function event(string $code): string
    {
        return return_translation('security_event_' . (in_array($code, self::EVENTS, true) ? $code : 'unknown'));
    }

    public static function result(string $code): string
    {
        return return_translation('security_result_' . (in_array($code, self::RESULTS, true) ? $code : 'unknown'));
    }

    /** Never accept arbitrary exception messages, SQL, tokens or form data. */
    public static function safeDetails(array $details): array
    {
        $safe = [];
        if (in_array($details['stage'] ?? null, self::STAGES, true)) $safe['stage'] = $details['stage'];
        if (isset($details['affected_rows']) && is_int($details['affected_rows']) && $details['affected_rows'] >= 0) $safe['affected_rows'] = $details['affected_rows'];
        if (isset($details['sql_state']) && is_string($details['sql_state']) && preg_match('/^[A-Z0-9]{5}$/D', $details['sql_state'])) $safe['sql_state'] = $details['sql_state'];
        if (isset($details['driver_code']) && is_int($details['driver_code']) && $details['driver_code'] > 0) $safe['driver_code'] = $details['driver_code'];
        return $safe;
    }

    public static function encodeReason(string $reason, array $details): string
    {
        $details = self::safeDetails($details);
        return $details === [] ? $reason : json_encode(['code' => $reason, 'details' => $details], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Returns plain text only; views must escape all values. */
    public static function reason(string $stored, string $result = ''): array
    {
        $code = trim($stored);
        $details = [];
        $decoded = json_decode($code, true);
        if (is_array($decoded) && is_string($decoded['code'] ?? null) && is_array($decoded['details'] ?? null)) {
            $code = $decoded['code'];
            $details = self::safeDetails($decoded['details']);
        }
        if ($code === '') {
            $message = $result === 'success' ? '—' : return_translation('security_reason_not_recorded');
        } elseif (in_array($code, self::REASONS, true)) {
            $message = return_translation('security_reason_' . $code);
        } elseif (preg_match('/^Record #(\d+)$/D', $code, $match)) {
            $message = str_replace(':id', $match[1], return_translation('security_reason_session_record'));
        } else {
            // An administrator's own comment is not a translation key.
            $message = $code;
        }
        $rows = [];
        foreach ($details as $key => $value) {
            $rows[] = [
                'label' => return_translation('security_detail_' . $key),
                'value' => $key === 'stage' ? return_translation('security_stage_' . $value) : (string)$value,
            ];
        }
        return ['code' => $code, 'message' => $message, 'details' => $rows];
    }

    /** Search should also recognize the localized labels shown in the table. */
    public static function matchingCodes(string $search): array
    {
        $matches = ['event' => [], 'result' => [], 'reason' => []];
        foreach (['event' => self::EVENTS, 'result' => self::RESULTS, 'reason' => self::REASONS] as $kind => $codes) {
            foreach ($codes as $code) {
                if (mb_stripos(return_translation('security_' . $kind . '_' . $code), $search) !== false) $matches[$kind][] = $code;
            }
        }
        return $matches;
    }
}
