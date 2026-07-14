<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\InboundSyncResult;
use Fireball\VpnManagerV2\Exceptions\InboundSyncException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\InboundRepository;
use Fireball\VpnManagerV2\Repositories\ServerRepository;

final class InboundSyncService
{
    public function __construct(
        private readonly ?ServerRepository $servers = null,
        private readonly ?InboundRepository $inbounds = null,
        private readonly ?ServerSecretService $secrets = null,
        private readonly ?InboundParser $parser = null,
    ) {
    }

    public function sync(int $serverId): InboundSyncResult
    {
        $servers = $this->servers ?? new ServerRepository();
        $server = $servers->findWithSecrets($serverId);
        if (!$server) {
            throw new InboundSyncException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_not_found'));
        }

        try {
            $config = ($this->secrets ?? new ServerSecretService())->clientConfig($server);
            $remoteItems = (new ThreeXuiClient($config))->listInbounds();
            $parser = $this->parser ?? new InboundParser();
            $parsed = [];
            foreach ($remoteItems as $item) {
                $parsed[] = $parser->parse($item);
            }

            $result = ($this->inbounds ?? new InboundRepository())->syncServer($serverId, $parsed);
            $servers->recordConnectionSuccess($serverId, $result->received);
            (new VpnSubscriptionRevisionService())->touchByServer($serverId);

            return $result;
        } catch (ThreeXuiTransportException $exception) {
            $servers->recordConnectionFailure($serverId, 'offline', $exception->getMessage(), $exception::class);
            throw $exception;
        } catch (VpnManagerV2Exception $exception) {
            $servers->recordConnectionFailure($serverId, 'error', $exception->getMessage(), $exception::class);
            throw $exception;
        } catch (\Throwable $exception) {
            $message = \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_sync_generic');
            $servers->recordConnectionFailure($serverId, 'error', $message, $exception::class);
            throw new InboundSyncException($message, 0, $exception);
        }
    }
}
