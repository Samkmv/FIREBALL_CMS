<?php

namespace Fireball\VpnManagerV2\Controllers\Admin;

use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\NativeXrayMigrationService;
use Fireball\VpnManagerV2\Support\Permissions;
use Fireball\VpnManagerV2\Support\SecretCipher;

final class NativeXrayController
{
    public function show(): string
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);
        return $this->render();
    }

    public function preview(): string
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);
        return $this->run(false);
    }

    public function adopt(): string
    {
        Permissions::authorize(Permissions::MANAGE_SERVERS);
        return $this->run(true);
    }

    public function inboundPreview(): string
    {
        return $this->changeInbound(false);
    }

    public function inboundApply(): string
    {
        return $this->changeInbound(true);
    }

    private function changeInbound(bool $apply): string
    {
        Permissions::authorize(Permissions::MANAGE_INBOUNDS);
        Permissions::authorize(Permissions::MANAGE_SERVERS);
        $id = (int)get_route_param('id');
        $key = 'vpn_native_inbound_' . $id;
        try {
            $pending = session()->get($key, []);
            if ($apply) {
                if (!is_array($pending) || time() - (int)($pending['at'] ?? 0) > 600
                    || !hash_equals((string)($pending['ticket'] ?? ''), (string)request()->post('ticket'))
                    || empty($pending['ticket'])) {
                    throw new \RuntimeException('Preview expired');
                }
                $arguments = json_decode(SecretCipher::decrypt($pending['encrypted'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
            } else {
                $json = (string)request()->post('inbound_json');
                if (strlen($json) > 131072) {
                    throw new \RuntimeException('Input exceeds limit');
                }
                $inbound = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($inbound) || array_is_list($inbound)) {
                    throw new \RuntimeException('Object required');
                }
                $arguments = ['inbound' => $inbound, 'delete' => (string)request()->post('delete') === '1', 'fingerprint' => ''];
            }
            $result = (new NativeXrayMigrationService())->client($id)->changeInbound(
                $arguments['inbound'], $arguments['delete'], $apply, $arguments['fingerprint']
            );
            if ($apply) {
                session()->remove($key);
                (new SubscriptionRepository())->logEvent('xray.inbound_changed', null, null, $id,
                    null, (int)(get_user()['id'] ?? 0), ['deleted' => (bool)$arguments['delete']]);
                (new \Fireball\VpnManagerV2\Services\InboundSyncService())->sync($id);
            } else {
                $arguments['fingerprint'] = $result['fingerprint'];
                $result['ticket'] = bin2hex(random_bytes(24));
                session()->set($key, ['at' => time(), 'ticket' => $result['ticket'],
                    'encrypted' => SecretCipher::encrypt(json_encode($arguments, JSON_THROW_ON_ERROR))]);
            }
            return $this->render(inboundResult: $result);
        } catch (\Throwable $exception) {
            session()->remove($key);
            (new SubscriptionRepository())->logEvent('xray.inbound_change_failed', null, null, $id,
                null, (int)(get_user()['id'] ?? 0), ['error_type' => $exception::class,
                    'reason' => $exception instanceof VpnManagerV2Exception ? $exception->getMessage() : 'invalid_input']);
            return $this->render(error: $exception instanceof VpnManagerV2Exception
                ? $exception->getMessage() : 'Не удалось обработать изменение. Проверьте JSON и повторите предварительную проверку.');
        }
    }

    private function run(bool $apply): string
    {
        $id = (int)get_route_param('id');
        try {
            $result = (new NativeXrayMigrationService())->inspect($id, $apply, (string)request()->post('fingerprint'));
            return $this->render($result);
        } catch (VpnManagerV2Exception $exception) {
            (new SubscriptionRepository())->logEvent('xray.adoption_failed', null, null, $id,
                null, (int)(get_user()['id'] ?? 0), ['error_type' => $exception::class, 'reason' => $exception->getMessage()]);
            return $this->render(error: $exception->getMessage());
        } catch (\Throwable $exception) {
            log_error_details('Native Xray adoption failed', ['Server' => $id, 'Error Class' => $exception::class]);
            return $this->render(error: 'Не удалось завершить проверку. Подробности записаны в журнал CMS.');
        }
    }

    private function render(array $result = [], string $error = '', array $inboundResult = []): string
    {
        $server = (new ServerRepository())->find((int)get_route_param('id'));
        if (!$server) {
            abort('', 404);
        }
        return plugin_view(\FireballPluginVpnManagerV2::SLUG, 'admin/native-xray',
            \FireballPluginVpnManagerV2::viewData('servers', [
                'title' => $server['name'] . ' · Xray-core', 'server' => $server,
                'result' => $result, 'error' => $error, 'inboundResult' => $inboundResult,
            ]));
    }
}
