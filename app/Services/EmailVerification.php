<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;
use FBL\RateLimiter;

final class EmailVerification
{
    private const CODE_TTL_SECONDS = 600;
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private ?MailService $mail = null) {}

    public static function codesAvailable(): bool
    {
        $columns = SchemaManifest::columns('user_email_verifications');
        return AuthIdentity::available()
            && in_array('code_hash', $columns, true)
            && in_array('code_attempts', $columns, true);
    }

    /** Called only for the newly registered or currently authenticated user. */
    public function request(int $userId, ?string $newEmail = null): bool
    {
        if (!self::codesAvailable()) return false;
        $user = (new User())->findById($userId);
        if (!$user) return false;
        $email = AuthIdentity::email($newEmail ?? ($user['pending_email'] ?: $user['email']));
        if (!AuthIdentity::validEmail($email)) return false;
        $key = 'auth.verify.user.' . $userId;
        if (!RateLimiter::attempt('auth.verify.ip.' . client_ip(), 20, 3600)
            || !RateLimiter::attempt($key, 5, 3600)
            || !RateLimiter::attempt($key . '.cooldown', 1, 60)) return false;

        $failureReason = 'verification_storage_failed';
        try {
            if (!db()->beginTransaction()) throw new \RuntimeException('verification_transaction_failed');
            // Every issuer and redeemer takes the user lock first, including legacy links.
            $fresh = $this->lockUser($userId);
            if (!$fresh) throw new \RuntimeException('verification_user_missing');
            $email = AuthIdentity::email($newEmail ?? $this->currentAddress($fresh));
            if (!AuthIdentity::validEmail($email) || $this->hasOtherOwner($email, $userId)) {
                db()->rollBack();
                return false;
            }
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            if ($codeHash === false) throw new \RuntimeException('verification_code_hash_failed');
            // Keep the legacy NOT NULL token column opaque; new email contains only the code.
            $tokenHash = hash('sha256', random_bytes(32));
            $now = date('Y-m-d H:i:s');
            db()->query('INSERT INTO user_email_verifications (user_id, email, token_hash, code_hash, code_attempts, expires_at, created_at)
                VALUES (?, ?, ?, ?, 0, ?, ?)',
                [$userId, $email, $tokenHash, $codeHash, date('Y-m-d H:i:s', time() + self::CODE_TTL_SECONDS), $now]);
            $verificationId = (int)db()->getInsertId();
            try {
                $sent = ($this->mail ?? new MailService())->sendTemplate([$email], return_translation('auth_verify_mail_subject'), 'auth/email_verification_email', [
                    'verification_code' => $code,
                    'expires_in_minutes' => intdiv(self::CODE_TTL_SECONDS, 60),
                    'account_login' => (string)$fresh['login'],
                ]);
            } catch (\Throwable) { $sent = false; }
            if (!$sent) {
                $failureReason = 'verification_mail_failed';
                throw new \RuntimeException($failureReason);
            }
            // Do not retire an earlier working code or change pending_email on SMTP failure.
            if ($newEmail !== null) {
                db()->query('UPDATE users SET pending_email = ? WHERE id = ?',
                    [$email !== AuthIdentity::email($fresh['email']) ? $email : null, $userId]);
            }
            db()->query('UPDATE user_email_verifications SET used_at = ? WHERE user_id = ? AND id <> ? AND used_at IS NULL',
                [$now, $userId, $verificationId]);
            if (!db()->commit()) throw new \RuntimeException('verification_commit_failed');
        } catch (\Throwable) {
            if (db()->inTransaction()) db()->rollBack();
            if ($failureReason === 'verification_mail_failed') {
                RateLimiter::clear($key . '.cooldown');
                RateLimiter::clear($key);
            }
            $this->audit('failed', $userId, $failureReason);
            return false;
        }
        $this->audit('success', $userId, 'verification_sent');
        return true;
    }

    /** The caller must always send the same public response, including unknown emails. */
    public function resendForAddress(string $email): void
    {
        $email = AuthIdentity::email($email);
        if (!AuthIdentity::validEmail($email) || !self::codesAvailable()) return;
        $user = (new User())->findByEmail($email);
        if ($user && empty($user['email_verified_at'])) $this->request((int)$user['id']);
    }

    public function confirmCode(string $email, string $code, ?int $expectedUserId = null): bool
    {
        $email = AuthIdentity::email($email);
        $code = trim($code);
        if (!self::codesAvailable() || !AuthIdentity::validEmail($email)
            || preg_match('/^[0-9]{6}$/D', $code) !== 1
            || ($expectedUserId !== null && $expectedUserId < 1)) return false;

        $userId = 0;
        try {
            $query = 'SELECT id FROM users WHERE COALESCE(NULLIF(pending_email, \'\'), email_normalized) = ?';
            $params = [$email];
            if ($expectedUserId !== null) { $query .= ' AND id = ?'; $params[] = $expectedUserId; }
            $users = db()->query($query . ' LIMIT 2', $params)->get() ?: [];
            if (count($users) !== 1) return false;
            $userId = (int)$users[0]['id'];
            if (!db()->beginTransaction()) throw new \RuntimeException('verification_transaction_failed');
            $user = $this->lockUser($userId);
            if (!$user || $this->currentAddress($user) !== $email) { db()->rollBack(); return false; }
            $row = db()->query('SELECT id, user_id, email, code_hash, code_attempts FROM user_email_verifications
                WHERE user_id = ? AND email = ? AND code_hash IS NOT NULL AND used_at IS NULL AND expires_at > ?
                ORDER BY id DESC LIMIT 1 FOR UPDATE', [$userId, $email, date('Y-m-d H:i:s')])->getOne();
            if (!$row || (int)$row['code_attempts'] >= self::MAX_CODE_ATTEMPTS) { db()->rollBack(); return false; }
            if (!password_verify($code, (string)$row['code_hash'])) {
                $attempts = (int)$row['code_attempts'] + 1;
                db()->query('UPDATE user_email_verifications SET code_attempts = ? WHERE id = ? AND used_at IS NULL', [$attempts, (int)$row['id']]);
                if (!db()->commit()) throw new \RuntimeException('verification_commit_failed');
                $this->audit('failed', $userId, $attempts >= self::MAX_CODE_ATTEMPTS
                    ? 'verification_code_attempts_exhausted' : 'verification_code_invalid');
                return false;
            }
            if (!$this->complete($user, $row)) { db()->rollBack(); return false; }
            if (!db()->commit()) throw new \RuntimeException('verification_commit_failed');
        } catch (\Throwable) {
            if (db()->inTransaction()) db()->rollBack();
            $this->audit('failed', $userId, 'verification_conflict_or_storage_failed');
            return false;
        }
        $this->audit('success', $userId, 'verification_completed');
        return true;
    }

    /** Existing verification emails issued before the code migration remain usable. */
    public function valid(string $token): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1 || !AuthIdentity::available()) return false;
        $legacyOnly = self::codesAvailable() ? ' AND v.code_hash IS NULL' : '';
        return (bool)db()->query('SELECT v.id FROM user_email_verifications v INNER JOIN users u ON u.id = v.user_id
            WHERE v.token_hash = ? AND v.used_at IS NULL AND v.expires_at > ?' . $legacyOnly . '
              AND v.email = COALESCE(NULLIF(u.pending_email, \'\'), u.email_normalized) LIMIT 1',
            [hash('sha256', $token), date('Y-m-d H:i:s')])->getOne();
    }

    public function confirm(string $token): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1 || !AuthIdentity::available()) return false;
        $userId = 0;
        try {
            $legacyOnly = self::codesAvailable() ? ' AND code_hash IS NULL' : '';
            $candidate = db()->query('SELECT user_id FROM user_email_verifications WHERE token_hash = ?' . $legacyOnly . ' LIMIT 1', [hash('sha256', $token)])->getOne();
            if (!$candidate) return false;
            $userId = (int)$candidate['user_id'];
            if (!db()->beginTransaction()) throw new \RuntimeException('verification_transaction_failed');
            $user = $this->lockUser($userId);
            $row = db()->query('SELECT id, user_id, email FROM user_email_verifications
                WHERE user_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > ?' . $legacyOnly . '
                LIMIT 1 FOR UPDATE', [$userId, hash('sha256', $token), date('Y-m-d H:i:s')])->getOne();
            if (!$user || !$row || !$this->complete($user, $row)) { db()->rollBack(); return false; }
            if (!db()->commit()) throw new \RuntimeException('verification_commit_failed');
        } catch (\Throwable) {
            if (db()->inTransaction()) db()->rollBack();
            $this->audit('failed', $userId, 'verification_conflict_or_storage_failed');
            return false;
        }
        $this->audit('success', $userId, 'verification_completed');
        return true;
    }

    private function lockUser(int $userId): array|false
    {
        return db()->query('SELECT id, login, email, email_normalized, pending_email FROM users WHERE id = ? FOR UPDATE', [$userId])->getOne();
    }

    private function currentAddress(array $user): string
    {
        return AuthIdentity::email($user['pending_email'] ?: $user['email']);
    }

    private function hasOtherOwner(string $email, int $userId): bool
    {
        // This is a read-only conflict check: locking an unindexed pending-email
        // scan here would block unrelated users throughout the SMTP request.
        // The unique current-email index is also enforced when confirmation commits.
        return (bool)db()->query('SELECT id FROM users WHERE id <> ?
            AND (email_normalized = ? OR LOWER(TRIM(pending_email)) = ?) LIMIT 1', [$userId, $email, $email])->getOne();
    }

    /** Caller holds the user and verification row locks and commits the entire change. */
    private function complete(array $user, array $row): bool
    {
        $userId = (int)$user['id'];
        $email = (string)$row['email'];
        if ($this->currentAddress($user) !== $email || $this->hasOtherOwner($email, $userId)) return false;
        $now = date('Y-m-d H:i:s');
        db()->query('UPDATE user_email_verifications SET used_at = ? WHERE id = ? AND used_at IS NULL AND expires_at > ?',
            [$now, (int)$row['id'], $now]);
        if (db()->rowCount() !== 1) return false;
        $changed = AuthIdentity::email($user['email']) !== $email;
        db()->query('UPDATE users SET email = ?, pending_email = NULL, email_verified_at = ?, email_verification_required = 0,
            session_version = COALESCE(session_version, 1) + ? WHERE id = ?', [$email, $now, $changed ? 1 : 0, $userId]);
        db()->query('UPDATE user_email_verifications SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [$now, $userId]);
        if ($changed) {
            db()->query('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
            if (UserSessionService::available()) db()->query('UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [$now, $userId]);
        }
        return true;
    }

    private function audit(string $result, int $userId, string $reason): void
    {
        try { (new SecurityLog())->record('email_verification', $result, null, $userId ?: null, $reason); }
        catch (\Throwable) { error_log('[auth] email_verification audit unavailable: ' . $reason); }
    }
}
