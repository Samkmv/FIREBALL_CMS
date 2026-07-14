<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\ConnectionTestResult;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\ServerRepository;

final class ServerConnectionService
{
    public function __construct(
        private readonly ?ServerRepository $repository = null,
        private readonly ?ServerSecretService $secrets = null,
    ) {
    }

    public function test(int $serverId): ConnectionTestResult
    {
        $repository = $this->repository ?? new ServerRepository();
        $server = $repository->findWithSecrets($serverId);
        if (!$server) {
            return new ConnectionTestResult(
                false,
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'),
                status: 'error'
            );
        }

        try {
            $config = ($this->secrets ?? new ServerSecretService())->clientConfig($server);
            $result = (new ThreeXuiClient($config))->testConnection();
            $repository->recordConnectionSuccess($serverId, $result->inboundCount);

            return $result;
        } catch (ThreeXuiTransportException $exception) {
            return $this->failure($repository, $serverId, 'offline', $exception);
        } catch (VpnManagerV2Exception $exception) {
            return $this->failure($repository, $serverId, 'error', $exception);
        } catch (\Throwable $exception) {
            $message = \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_generic');
            $repository->recordConnectionFailure($serverId, 'error', $message, $exception::class);

            return new ConnectionTestResult(false, $message, status: 'error');
        }
    }

    private function failure(
        ServerRepository $repository,
        int $serverId,
        string $status,
        \Throwable $exception
    ): ConnectionTestResult {
        $message = mb_substr(trim($exception->getMessage()), 0, 1000);
        if ($message === '') {
            $message = \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_connection_generic');
        }
        $repository->recordConnectionFailure($serverId, $status, $message, $exception::class);

        return new ConnectionTestResult(false, $message, status: $status);
    }
}
