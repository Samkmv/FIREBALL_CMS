<?php

namespace Fireball\VpnManagerV2\Repositories;

use Fireball\VpnManagerV2\Support\SecretCipher;
use Fireball\VpnManagerV2\Support\Uuid;

final class VpnProfileRepository
{
    public function findByUser(int $userId): ?array
    {
        $row = db()->query(
            'SELECT id, cms_user_id, shared_uuid, encrypted_shared_password, status, created_at, updated_at
             FROM vpn_v2_profiles WHERE cms_user_id = ? LIMIT 1',
            [$userId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function getOrCreate(int $userId, ?string $preferredCredential = null): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('A valid CMS user is required.');
        }

        $database = db();
        $database->beginTransaction();
        try {
            $profile = $database->query(
                'SELECT id, cms_user_id, shared_uuid, encrypted_shared_password, status, created_at, updated_at
                 FROM vpn_v2_profiles WHERE cms_user_id = ? FOR UPDATE',
                [$userId]
            )->getOne();
            if (!is_array($profile)) {
                $now = date('Y-m-d H:i:s');
                $sharedCredential = trim((string)$preferredCredential);
                if ($sharedCredential === '') {
                    $sharedCredential = Uuid::v4();
                }
                $database->query(
                    'INSERT INTO vpn_v2_profiles
                        (cms_user_id, shared_uuid, encrypted_shared_password, status, created_at, updated_at)
                     VALUES (?, ?, ?, \'active\', ?, ?)',
                    [$userId, $sharedCredential, SecretCipher::encrypt($this->password()), $now, $now]
                );
                $profile = $database->query(
                    'SELECT id, cms_user_id, shared_uuid, encrypted_shared_password, status, created_at, updated_at
                     FROM vpn_v2_profiles WHERE id = ? LIMIT 1',
                    [(int)$database->getInsertId()]
                )->getOne();
            }
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        if (!is_array($profile)) {
            throw new \RuntimeException('VPN profile could not be created.');
        }

        return $profile;
    }

    public function find(int $profileId): ?array
    {
        if ($profileId <= 0) {
            return null;
        }

        $row = db()->query(
            'SELECT id, cms_user_id, shared_uuid, encrypted_shared_password,
                    status, created_at, updated_at
             FROM vpn_v2_profiles
             WHERE id = ? LIMIT 1',
            [$profileId]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function createManual(): array
    {
        $now = date('Y-m-d H:i:s');

        db()->query(
            'INSERT INTO vpn_v2_profiles
                (cms_user_id, shared_uuid, encrypted_shared_password,
                 status, created_at, updated_at)
             VALUES (NULL, ?, ?, \'active\', ?, ?)',
            [
                Uuid::v4(),
                SecretCipher::encrypt($this->password()),
                $now,
                $now,
            ]
        );

        $profile = $this->find((int)db()->getInsertId());

        if (!$profile) {
            throw new \RuntimeException(
                'Manual VPN profile could not be created.'
            );
        }

        return $profile;
    }

    public function deleteManualIfUnused(int $profileId): void
    {
        if ($profileId <= 0) {
            return;
        }

        db()->query(
            'DELETE p
             FROM vpn_v2_profiles p
             LEFT JOIN vpn_v2_subscriptions s ON s.profile_id = p.id
             WHERE p.id = ?
               AND p.cms_user_id IS NULL
               AND s.id IS NULL',
            [$profileId]
        );
    }

    public function sharedPassword(array $profile): string
    {
        return SecretCipher::decrypt($profile['encrypted_shared_password'] ?? null);
    }

    private function password(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
