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
        if ($repository->codeExists($data->code)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_code_exists'));
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

    public function update(int $id, array $input): void
    {
        $repository = $this->repository();
        $existing = $repository->findWithSecrets($id);
        if (!$existing) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
        }

        $data = ($this->validator ?? new ServerValidator())->validate($input);
        if ($repository->codeExists($data->code, $id)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_code_exists'));
        }

        $secretService = $this->secrets ?? new ServerSecretService();
        $this->validateRequiredSecrets($input, $existing, $data->authType, $secretService);
        $repository->update($id, $data->toArray(), $secretService->encryptedUpdates($input));
        (new VpnSubscriptionRevisionService())->touchByServer($id);
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

    private function validateRequiredSecrets(
        array $input,
        array $existing,
        string $authType,
        ServerSecretService $secrets
    ): void {
        if ($authType === 'token') {
            if (!$secrets->submittedOrStored($input, $existing, 'token')) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_token_required'));
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
