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

    public function sharedPassword(array $profile): string
    {
        return SecretCipher::decrypt($profile['encrypted_shared_password'] ?? null);
    }

    private function password(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
