<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Validators\ServerValidator;

final class ServerManagerService
{
    public function __construct(
        private readonly ?ServerRepository $repository = null,
        private readonly ?ServerValidator $validator = null,
        private readonly ?ServerSecretService $secrets = null,
    ) {
    }

    public function create(array $input): int
    {
        $repository = $this->repository();
        $data = ($this->validator ?? new ServerValidator())->validate($input);
        $availableCode = $repository->availableCode($data->code);
        if ($availableCode !== $data->code) {
            $input['code'] = $availableCode;
            $data = ($this->validator ?? new ServerValidator())->validate($input);
        }

        $secretService = $this->secrets ?? new ServerSecretService();
        $this->validateRequiredSecrets($input, [], $data->authType, $secretService);
        $updates = $secretService->encryptedUpdates($input);

        return $repository->create($data->toArray(), [
            'encrypted_username' => $updates['encrypted_username'] ?? '',
            'encrypted_password' => $updates['encrypted_password'] ?? '',
            'encrypted_token' => $updates['encrypted_token'] ?? '',
        ]);
    }

    public function update(int $id, array $input, bool $replacement = false): void
    {
        $repository = $this->repository();
        $existing = $repository->findWithSecrets($id);
        if (!$existing) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
        }

        $data = ($this->validator ?? new ServerValidator())->validate($input);
        $availableCode = $repository->availableCode($data->code, $id);
        if ($availableCode !== $data->code) {
            $input['code'] = $availableCode;
            $data = ($this->validator ?? new ServerValidator())->validate($input);
        }

        $secretService = $this->secrets ?? new ServerSecretService();
        $this->validateRequiredSecrets($input, $existing, $data->authType, $secretService);
        $updated = $data->toArray();
        $database = db();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $current = $repository->findForUpdate($id);
            if (!$current) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
            }
            foreach (['panel_url', 'panel_path', 'api_url', 'auth_type'] as $key) {
                $replacement = $replacement || (string)($current[$key] ?? '') !== (string)($updated[$key] ?? '');
            }
            if ($replacement) {
                $database->query(
                    "UPDATE vpn_v2_subscription_nodes SET sync_status = 'replacement_pending'
                     WHERE server_id = ? AND status NOT IN ('deleted', 'deleting', 'pending_remote_delete', 'delete_failed')",
                    [$id]
                );
            }
            $repository->update($id, $updated, $secretService->encryptedUpdates($input));
            (new VpnSubscriptionRevisionService())->touchByServer($id);
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    public function toggle(int $id): bool
    {
        if (!$this->repository()->find($id)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
        }

        $enabled = $this->repository()->toggle($id);
        (new VpnSubscriptionRevisionService())->touchByServer($id);

        return $enabled;
    }

    public function delete(int $id): string
    {
        $repository = $this->repository();
        $database = db();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }

        try {
            $server = $repository->findForUpdate($id);
            if (!$server) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
            }
            $dependencies = $repository->dependencies($id);
            if ((int)$dependencies['plan_nodes'] > 0 || (int)$dependencies['subscription_nodes'] > 0) {
                throw new ValidationException(sprintf(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_delete_in_use'),
                    (int)$dependencies['plan_nodes'],
                    (int)$dependencies['subscription_nodes']
                ));
            }
            if (!$repository->delete($id, $server)) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
            }
            if ($ownsTransaction) {
                $database->commit();
            }

            return (string)$server['name'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function validateRequiredSecrets(
        array $input,
        array $existing,
        string $authType,
        ServerSecretService $secrets
    ): void {
        if (in_array($authType, ['token', 'xray'], true)) {
            if (!$secrets->submittedOrStored($input, $existing, 'token')) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_token_required'));
            }
            if ($authType === 'xray') {
                $plainToken = \Fireball\VpnManagerV2\Support\SecretCipher::shouldReplace((string)($input['token'] ?? ''))
                    ? trim((string)$input['token'])
                    : \Fireball\VpnManagerV2\Support\SecretCipher::decrypt($existing['encrypted_token'] ?? null);
                if (strlen($plainToken) < 32 || preg_match('/[\r\n]/', $plainToken)) {
                    throw new ValidationException('Xray agent token must contain at least 32 characters and no line breaks.');
                }
            }

            return;
        }

        if (!$secrets->submittedOrStored($input, $existing, 'username')
            || !$secrets->submittedOrStored($input, $existing, 'password')) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_credentials_required'));
        }
    }

    private function repository(): ServerRepository
    {
        return $this->repository ?? new ServerRepository();
    }
}
