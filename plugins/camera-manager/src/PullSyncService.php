<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class PullSyncService
{
    public const PROTOCOL_VERSION = 1;

    public function authenticate(string $token): bool
    {
        $storedHash = strtolower(trim((string)plugin_setting(
            \FireballPluginCameraManager::SLUG,
            'pull_token_hash',
            ''
        )));

        return preg_match('/^[a-f0-9]{64}$/', $storedHash) === 1
            && preg_match('/^[a-f0-9]{64}$/', $token) === 1
            && hash_equals($storedHash, hash('sha256', $token));
    }

    public function handle(array $payload): array
    {
        $settings = \FireballPluginCameraManager::settings();
        if ($settings['connection_mode'] !== 'pull') {
            throw new RuntimeException('HTTPS pull synchronization is disabled.');
        }

        $action = strtolower(trim((string)($payload['action'] ?? '')));
        if ($action === 'fetch') {
            return $this->fetch($payload, $settings);
        }
        if ($action === 'report') {
            return $this->report($payload, $settings);
        }

        throw new RuntimeException('Unsupported synchronization action.');
    }

    private function fetch(array $payload, array $settings): array
    {
        $currentRevision = $this->revision($payload['current_revision'] ?? 0);
        $desiredRevision = max(0, (int)$settings['pull_revision']);
        $now = date('Y-m-d H:i:s');
        plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_seen_at', $now);

        if ($desiredRevision === 0 || $currentRevision === $desiredRevision) {
            return [
                'success' => true,
                'protocol' => self::PROTOCOL_VERSION,
                'changed' => false,
                'revision' => $desiredRevision,
                'server_time' => gmdate('c'),
            ];
        }

        $encryptedSnapshot = (string)plugin_setting(
            \FireballPluginCameraManager::SLUG,
            'pull_payload_encrypted',
            ''
        );
        if ($encryptedSnapshot === '') {
            throw new RuntimeException('Published camera snapshot is missing.');
        }
        $snapshotJson = SecretCipher::decrypt($encryptedSnapshot);
        $snapshot = json_decode($snapshotJson, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || (int)($snapshot['revision'] ?? 0) !== $desiredRevision) {
            throw new RuntimeException('Published camera snapshot revision mismatch.');
        }
        $managedBlock = $snapshot['managed_block'] ?? null;
        if (!is_string($managedBlock) || strlen($managedBlock) > 1_500_000) {
            throw new RuntimeException('Published camera snapshot is invalid.');
        }
        $managedBlockHash = strtolower((string)($snapshot['managed_block_sha256'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $managedBlockHash)
            || !hash_equals($managedBlockHash, hash('sha256', $managedBlock))) {
            throw new RuntimeException('Published camera snapshot checksum mismatch.');
        }
        $streamCount = max(0, min(100000, (int)($snapshot['stream_count'] ?? 0)));

        return [
            'success' => true,
            'protocol' => self::PROTOCOL_VERSION,
            'changed' => true,
            'revision' => $desiredRevision,
            'restart' => !empty($snapshot['restart']),
            'stream_count' => $streamCount,
            'managed_block' => $managedBlock,
            'managed_block_sha256' => $managedBlockHash,
            'server_time' => gmdate('c'),
        ];
    }

    private function report(array $payload, array $settings): array
    {
        $revision = $this->revision($payload['revision'] ?? 0);
        if ($revision < 1 || $revision > max(0, (int)$settings['pull_revision'])) {
            throw new RuntimeException('Invalid synchronization revision.');
        }

        $status = strtolower(trim((string)($payload['status'] ?? '')));
        if (!in_array($status, ['success', 'warning', 'failed'], true)) {
            throw new RuntimeException('Invalid synchronization status.');
        }

        $message = $this->singleLine((string)($payload['message'] ?? ''), 1000);
        $backupPath = $this->singleLine((string)($payload['backup_path'] ?? ''), 500);
        if ($backupPath !== '' && !preg_match(
            '~^/var/www/html/rtsp/\.fireball-camera-manager-backups/streams\.pl\.[A-Za-z0-9._-]+\.bak$~',
            $backupPath
        )) {
            $backupPath = '';
        }
        $streamCount = max(0, min(100000, (int)($payload['stream_count'] ?? 0)));
        $fingerprint = hash('sha256', implode("\n", [$revision, $status, $backupPath, $message]));
        $previousFingerprint = (string)plugin_setting(
            \FireballPluginCameraManager::SLUG,
            'pull_last_report_fingerprint',
            ''
        );
        $lastRevision = max(0, (int)$settings['pull_last_revision']);

        plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_seen_at', date('Y-m-d H:i:s'));
        if ($revision >= $lastRevision) {
            plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_revision', $revision);
            plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_status', $status);
            plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_message', $message);
            plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_backup_path', $backupPath);
        }

        if (!hash_equals($previousFingerprint, $fingerprint)) {
            plugin_setting_set(\FireballPluginCameraManager::SLUG, 'pull_last_report_fingerprint', $fingerprint);
            \FireballPluginCameraManager::recordPublication(
                $status,
                $streamCount,
                $backupPath !== '' ? $backupPath : null,
                'RTSP-агент, ревизия ' . $revision . ': ' . ($message !== '' ? $message : $status)
            );
        }

        return [
            'success' => true,
            'protocol' => self::PROTOCOL_VERSION,
            'accepted_revision' => $revision,
            'server_time' => gmdate('c'),
        ];
    }

    private function revision(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (!is_string($value) || preg_match('/^\d{1,18}$/', $value) !== 1) {
            throw new RuntimeException('Invalid synchronization revision.');
        }

        return max(0, (int)$value);
    }

    private function singleLine(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n", "\0"], ' ', $value));

        return mb_substr($value, 0, $limit);
    }
}
