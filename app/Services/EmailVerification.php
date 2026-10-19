<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;
use FBL\RateLimiter;

final class EmailVerification
{
    public function __construct(private ?MailService $mail = null) {}

    /** Called only for the newly registered or currently authenticated user. */
    public function request(int $userId, ?string $newEmail = null): bool
    {
        if (!AuthIdentity::available()) return false;
        $user = (new User())->findById($userId);
        if (!$user) return false;
        $email = AuthIdentity::email($newEmail ?? ($user['pending_email'] ?: $user['email']));
        if (!AuthIdentity::validEmail($email)) return false;
        $owner = db()->query('SELECT id FROM users WHERE email_normalized = ? AND id <> ? LIMIT 1', [$email, $userId])->getOne();
        if ($owner) return false;
        $key = 'auth.verify.user.' . $userId;
        if (!RateLimiter::attempt('auth.verify.ip.' . client_ip(), 20, 3600)
            || !RateLimiter::attempt($key, 5, 3600)
            || !RateLimiter::attempt($key . '.cooldown', 1, 60)) return false;
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');
        try {
            if (!db()->beginTransaction()) throw new \RuntimeException('verification_transaction_failed');
            $fresh = db()->query('SELECT id, email, pending_email FROM users WHERE id = ? FOR UPDATE', [$userId])->getOne();
            if (!$fresh) throw new \RuntimeException('verification_user_missing');
            if ($newEmail !== null && $email !== AuthIdentity::email($fresh['email'])) {
                db()->query('UPDATE users SET pending_email = ? WHERE id = ?', [$email, $userId]);
            }
            db()->query('INSERT INTO user_email_verifications (user_id, email, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
                [$userId, $email, $hash, date('Y-m-d H:i:s', time() + 86400), $now]);
            if (!db()->commit()) throw new \RuntimeException('verification_commit_failed');
        } catch (\Throwable) {
            if (db()->inTransaction()) db()->rollBack();
            $this->audit('failed', $userId, 'verification_storage_failed');
            return false;
        }
        try {
            $sent = ($this->mail ?? new MailService())->sendTemplate([$email], return_translation('auth_verify_mail_subject'), 'auth/email_verification_email', [
                'verification_url' => base_href('/verify-email?token=' . $token),
                'expires_in_hours' => 24,
                'account_login' => (string)$user['login'],
            ]);
        } catch (\Throwable) { $sent = false; }
        if (!$sent) {
            db()->query('DELETE FROM user_email_verifications WHERE token_hash = ?', [$hash]);
            RateLimiter::clear($key . '.cooldown');
            RateLimiter::clear($key);
            $this->audit('failed', $userId, 'verification_mail_failed');
            return false;
        }
        $this->audit('success', $userId, 'verification_sent');
        return true;
    }

    /** The caller must always send the same public response, including unknown emails. */
    public function resendForAddress(string $email): void
    {
        $email = AuthIdentity::email($email);
        if (!AuthIdentity::validEmail($email) || !AuthIdentity::available()) return;
        $user = (new User())->findByEmail($email);
        if ($user && empty($user['email_verified_at'])) $this->request((int)$user['id']);
    }

    public function valid(string $token): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1 || !AuthIdentity::available()) return false;
        return (bool)db()->query('SELECT v.id FROM user_email_verifications v INNER JOIN users u ON u.id = v.user_id
            WHERE v.token_hash = ? AND v.used_at IS NULL AND v.expires_at > ?
              AND v.email = COALESCE(NULLIF(u.pending_email, \'\'), u.email_normalized) LIMIT 1',
            [hash('sha256', $token), date('Y-m-d H:i:s')])->getOne();
    }

    public function confirm(string $token): bool
    {
        if (!$this->valid($token)) return false;
        $userId = 0;
        try {
            if (!db()->beginTransaction()) throw new \RuntimeException('verification_transaction_failed');
            $row = db()->query('SELECT v.id, v.user_id, v.email, u.email AS previous_email
                FROM user_email_verifications v INNER JOIN users u ON u.id = v.user_id
                WHERE v.token_hash = ? AND v.used_at IS NULL AND v.expires_at > ?
                  AND v.email = COALESCE(NULLIF(u.pending_email, \'\'), u.email_normalized)
                LIMIT 1 FOR UPDATE', [hash('sha256', $token), date('Y-m-d H:i:s')])->getOne();
            if (!$row) { db()->rollBack(); return false; }
            $userId = (int)$row['user_id'];
            $changed = AuthIdentity::email($row['previous_email']) !== $row['email'];
            db()->query('UPDATE users SET email = ?, pending_email = NULL, email_verified_at = ?, email_verification_required = 0,
                session_version = COALESCE(session_version, 1) + ? WHERE id = ?',
                [$row['email'], date('Y-m-d H:i:s'), $changed ? 1 : 0, $userId]);
            db()->query('UPDATE user_email_verifications SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [date('Y-m-d H:i:s'), $userId]);
            if ($changed) {
                db()->query('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
                if (UserSessionService::available()) db()->query('UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [date('Y-m-d H:i:s'), $userId]);
            }
            if (!db()->commit()) throw new \RuntimeException('verification_commit_failed');
        } catch (\Throwable) {
            if (db()->inTransaction()) db()->rollBack();
            $this->audit('failed', $userId, 'verification_conflict_or_storage_failed');
            return false;
        }
        $this->audit('success', $userId, 'verification_completed');
        return true;
    }

    private function audit(string $result, int $userId, string $reason): void
    {
        try { (new SecurityLog())->record('email_verification', $result, null, $userId ?: null, $reason); }
        catch (\Throwable) { error_log('[auth] email_verification audit unavailable: ' . $reason); }
    }
}
