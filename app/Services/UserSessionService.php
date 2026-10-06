<?php
namespace App\Services;

use App\Models\SecurityLog;
use FBL\Pagination;

final class UserSessionService
{
    public static function available(): bool { return SchemaManifest::hasTable('user_sessions'); }

    public static function hash(string $sessionId): string
    {
        if ($sessionId === '') throw new \InvalidArgumentException('An active PHP session is required.');
        return hash('sha256', "fireball:user-session:v1\0" . $sessionId);
    }

    public function register(int $userId, int $version, string $sessionId, array $server, int $lifetime): void
    {
        if ($userId <= 0) throw new \InvalidArgumentException('Invalid session owner.');
        $ua = mb_substr((string)($server['HTTP_USER_AGENT'] ?? ''), 0, 1000);
        $client = (new AnalyticsService())->describeClient($ua);
        $now = date('Y-m-d H:i:s');
        db()->query('INSERT INTO user_sessions
            (user_id, session_hash, session_version, device_type, browser, browser_version, os, user_agent, ip_address, created_at, last_activity_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $userId, self::hash($sessionId), $version, $client['device_type'], $client['browser'],
            mb_substr($client['browser_version'], 0, 40), $client['os'], $ua, client_ip(), $now, $now,
            date('Y-m-d H:i:s', time() + max(60, $lifetime)),
        ]);
        (new SecurityLog())->record('login_session_created', 'success', $userId, $userId);
    }

    /** Existing pre-migration PHP sessions may enroll once, never revive a known/revoked row. */
    public function validate(int $userId, int $version, string $sessionId, int $lifetime, bool $allowLegacy = false): bool
    {
        $hash = self::hash($sessionId);
        $row = db()->query('SELECT * FROM user_sessions WHERE session_hash = ? AND user_id = ? LIMIT 1', [$hash, $userId])->getOne();
        if (!$row) {
            if (!$allowLegacy) return false;
            $this->register($userId, $version, $sessionId, $_SERVER, $lifetime);
            return true;
        }
        $now = time();
        if ($row['revoked_at'] !== null || (int)$row['session_version'] !== $version || strtotime($row['expires_at']) <= $now) return false;
        if (strtotime($row['last_activity_at']) <= $now - 90) {
            // Revocation is rechecked on every request, only activity writes are throttled.
            db()->query('UPDATE user_sessions SET last_activity_at = ?, expires_at = ?
                WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [
                date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now + max(60, $lifetime)), (int)$row['id'], $userId,
            ]);
        }
        return true;
    }

    public function paginated(int $userId, string $sessionId): array
    {
        $where = 's.user_id = ? AND s.revoked_at IS NULL AND s.expires_at > ? AND s.session_version = u.session_version';
        $params = [$userId, date('Y-m-d H:i:s')];
        $total = (int)db()->query("SELECT COUNT(*) FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE {$where}", $params)->getColumn();
        $pagination = new Pagination($total, 20);
        $offset = $pagination->getOffset();
        $items = db()->query("SELECT s.id, s.device_type, s.browser, s.browser_version, s.os, s.ip_address,
            s.created_at, s.last_activity_at, CASE WHEN s.session_hash = ? THEN 1 ELSE 0 END AS is_current
            FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE {$where}
            ORDER BY is_current DESC, s.last_activity_at DESC, s.id DESC LIMIT 20 OFFSET {$offset}", [self::hash($sessionId), ...$params])->get() ?: [];
        return ['items' => $items, 'total' => $total, 'pagination' => $pagination];
    }

    public function revoke(int $userId, int $id): bool
    {
        db()->query('UPDATE user_sessions SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $id, $userId]);
        $changed = db()->rowCount() > 0;
        if ($changed) (new SecurityLog())->record('session_revoked', 'success', $userId, $userId, 'Record #' . $id);
        return $changed;
    }

    public function revokeCurrent(int $userId, string $sessionId): void
    {
        $row = db()->query('SELECT id FROM user_sessions WHERE user_id = ? AND session_hash = ? LIMIT 1', [$userId, self::hash($sessionId)])->getOne();
        if ($row) $this->revoke($userId, (int)$row['id']);
    }

    public function revokeOthers(int $userId, string $sessionId): void
    {
        db()->query('UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND session_hash <> ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $userId, self::hash($sessionId)]);
        (new SecurityLog())->record('logout_other_devices', 'success', $userId, $userId);
    }

    public function revokeAll(int $userId): void
    {
        db()->beginTransaction();
        try {
            db()->query('UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?', [$userId]);
            db()->query('UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [date('Y-m-d H:i:s'), $userId]);
            (new SecurityLog())->record('logout_all', 'success', $userId, $userId);
            db()->commit();
        } catch (\Throwable $e) { db()->rollBack(); throw $e; }
    }

    public function cleanup(): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - 90 * 86400);
        db()->query('DELETE FROM user_sessions WHERE expires_at < ? OR revoked_at < ? LIMIT 1000', [$cutoff, $cutoff]);
        return db()->rowCount();
    }
}
