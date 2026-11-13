<?php

namespace Fireball\VpnManagerV2\Clients;

use Fireball\VpnManagerV2\DTO\ConnectionTestResult;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiHttpException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Support\BoundedResponseBody;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;

/** Native transport shares the existing subscription operations contract. */
final class NativeXrayClient implements ThreeXuiClientInterface
{
    public function __construct(
        private readonly ThreeXuiServerConfig $config,
        private readonly ?\Closure $transport = null,
    ) {
    }

    public function authenticate(): void
    {
        $this->rpc('health');
    }

    public function testConnection(): ConnectionTestResult
    {
        $this->authenticate();
        return new ConnectionTestResult(true, 'Xray agent connected', count($this->listInbounds()));
    }

    public function listInbounds(): array
    {
        return $this->rpc('inbounds');
    }

    public function getInbound(int $remoteInboundId): array
    {
        return $this->rpc('inbound', ['inbound_id' => $remoteInboundId]);
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        foreach (($this->getInbound($remoteInboundId)['settings']['clients'] ?? []) as $client) {
            if (($clientId !== '' && in_array($clientId, [$client['id'] ?? '', $client['password'] ?? ''], true))
                || ($clientEmail !== '' && ($client['email'] ?? '') === $clientEmail)) {
                return $client;
            }
        }
        return null;
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        return $this->rpc('client_put', ['inbound_id' => $remoteInboundId, 'client' => $client]);
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        return $this->rpc('client_put', ['inbound_id' => $remoteInboundId, 'credential' => $clientId, 'client' => $client]);
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        return $this->rpc('client_delete', [
            'inbound_id' => $remoteInboundId, 'credential' => $clientId, 'email' => $clientEmail,
        ]);
    }

    public function getClientTraffic(string $clientIdentifier): array
    {
        return ['success' => true, 'obj' => $this->rpc('traffic', ['email' => $clientIdentifier])];
    }

    public function resetClientTraffic(int $remoteInboundId, string $clientEmail): array
    {
        return $this->rpc('traffic_reset', ['inbound_id' => $remoteInboundId, 'email' => $clientEmail]);
    }

    public function serverStatus(): array
    {
        return $this->rpc('health');
    }

    public function onlineClientNames(): array
    {
        return $this->rpc('online');
    }

    public function probeSubscriptionHwid(string $subId, array $deviceHeaders): int
    {
        $headers = array_change_key_case($deviceHeaders, CASE_LOWER);
        try {
            $result = $this->rpc('hwid', ['sub_id' => $subId, 'hwid' => (string)($headers['x-hwid'] ?? '')]);
            return (int)($result['status'] ?? 503);
        } catch (ThreeXuiHttpException $exception) {
            if ($exception->httpStatus() === 404 || $exception->httpStatus() === 429 || $exception->httpStatus() >= 500) {
                return $exception->httpStatus();
            }
            throw $exception;
        }
    }

    public function listClientDevices(string $email): array
    {
        return $this->rpc('devices', ['email' => $email]);
    }

    public function deleteClientDevice(string $email, int $deviceId): void
    {
        $this->rpc('device_delete', ['email' => $email, 'device_id' => $deviceId]);
    }

    public function clearClientDevices(string $email): void
    {
        $this->rpc('device_delete', ['email' => $email]);
    }

    public function adopt(array $manifest, bool $apply = false, string $fingerprint = ''): array
    {
        return $this->rpc($apply ? 'adopt' : 'adopt_preview', [
            'manifest' => $manifest, 'fingerprint' => $fingerprint,
        ]);
    }

    public function changeInbound(array $inbound, bool $delete, bool $apply = false, string $fingerprint = ''): array
    {
        return $this->rpc($apply ? 'inbound_apply' : 'inbound_preview', [
            'inbound' => $inbound, 'delete' => $delete, 'fingerprint' => $fingerprint,
        ]);
    }

    private function rpc(string $action, array $arguments = []): array
    {
        $baseUrl = trim((string)$this->config->apiUrl);
        $url = $baseUrl !== '' ? $baseUrl
            : rtrim($this->config->panelUrl, '/') . '/' . trim($this->config->panelPath, '/');
        $url = rtrim($url, '/') . '/v1/rpc';
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !$this->config->verifySsl
            || strlen($this->config->token) < 32 || preg_match('/[\r\n]/', $this->config->token)) {
            throw new ThreeXuiTransportException('Xray agent requires HTTPS, TLS verification and a token of at least 32 characters.');
        }
        $payload = ['action' => $action, 'arguments' => $arguments];
        $headers = ['Content-Type: application/json', 'Accept: application/json',
            'Authorization: Bearer ' . $this->config->token];
        if ($this->transport !== null) {
            $response = ($this->transport)('POST', $url, $payload, 'json', $headers);
            if (!$response instanceof ThreeXuiHttpResponse) {
                throw new ThreeXuiTransportException('Invalid Xray agent transport.');
            }
        } else {
            $guard = new NetworkTargetGuard();
            $addresses = $guard->validatedRequestAddresses($url, $this->config->allowPrivateNetwork);
            $body = new BoundedResponseBody(8388608);
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => $headers, CURLOPT_WRITEFUNCTION => $body->append(...),
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '',
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => max(1, min(30, $this->config->connectTimeout)),
                CURLOPT_TIMEOUT => max(2, min(90, $this->config->readTimeout)),
                CURLOPT_RESOLVE => $guard->curlResolveEntries($url, $addresses),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $ok = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($ok === false || $body->exceeded || $status === 0) {
                throw new ThreeXuiTransportException('Xray agent is unavailable or its response exceeds the limit.');
            }
            $response = new ThreeXuiHttpResponse($status, 'application/json', $body->body);
        }
        if ($response->status !== 200) {
            $error = json_decode($response->body, true);
            $messages = [
                'adoption_required' => 'Сначала проверьте перенос и передайте сервер под управление агента.',
                'credential_mismatch' => 'Ключ клиента в Xray отличается от сохранённого в CMS. Перенос остановлен.',
                'inbound_mismatch' => 'Протокол или порт входящего подключения не совпадает с CMS.',
                'transport_mismatch' => 'Транспорт, SNI или shortId входящего подключения отличается от сохранённого в CMS.',
                'reality_key_mismatch' => 'Ключ REALITY на сервере отличается от ключа действующих клиентов. Перенос остановлен.',
                'public_key_unavailable' => 'Агент не смог вычислить public key REALITY. Проверьте серверный privateKey и OpenSSL с поддержкой X25519.',
                'config_changed' => 'Конфигурация Xray изменена вне агента. Повторите проверку переноса.',
                'preview_expired' => 'Состав подключений изменился. Повторите проверку переноса.',
                'statistics_not_configured' => 'Включите статистику пользователей Xray по инструкции агента.',
                'ip_limit_unsupported' => 'У клиента включено ограничение IP. Этот режим пока блокирует перенос в Xray.',
                'device_identity_missing' => 'Для ограничения устройств нужен сохранённый идентификатор subId. Перенос этого клиента остановлен.',
                'duplicate_email' => 'Несколько подключений используют одинаковый email. Нужна проверка идентификаторов.',
                'duplicate_credential' => 'Этот ключ уже используется другим клиентом входящего подключения.',
                'unsupported_protocol' => 'Этот протокол или шифр пока не поддерживается агентом.',
                'client_not_owned' => 'Клиент ещё не передан под управление агента. Выполните проверку переноса.',
                'xray_unavailable' => 'Агент не может подключиться к локальному API Xray.',
                'xray_command_failed' => 'Xray отклонил команду. Проверьте версию ядра, конфигурацию и локальный API.',
                'add_not_confirmed' => 'Работающее ядро Xray не подтвердило добавление клиента. Операция будет повторена.',
                'remove_not_confirmed' => 'Работающее ядро Xray не подтвердило удаление клиента. Операция будет повторена.',
                'inbound_has_clients' => 'Во входящем подключении ещё есть клиенты. Сначала перенесите или удалите их.',
                'invalid_inbound' => 'Проверьте тег inbound-ID, протокол и порт входящего подключения.',
                'clients_managed_separately' => 'Уберите settings.clients из JSON. Пользователи сохраняются и управляются через подписки.',
                'protocol_change_blocked' => 'Для смены протокола создайте отдельное входящее подключение и перенесите клиентов.',
                'inbound_add_not_confirmed' => 'Xray не подтвердил применение входящего подключения. Агент повторит операцию.',
                'inbound_remove_not_confirmed' => 'Xray не подтвердил удаление входящего подключения. Агент повторит операцию.',
            ];
            $code = is_array($error) && ($error['protocol'] ?? '') === 'fireball-xray/1'
                ? (string)($error['error'] ?? '') : '';
            throw new ThreeXuiHttpException($messages[$code] ?? 'Xray agent returned HTTP ' . $response->status, $response->status);
        }
        if (strlen($response->body) > 8388608) {
            throw new ThreeXuiResponseException('Xray agent response exceeds the limit.');
        }
        $decoded = json_decode($response->body, true);
        if (!is_array($decoded) || ($decoded['protocol'] ?? '') !== 'fireball-xray/1'
            || !is_array($decoded['result'] ?? null)) {
            throw new ThreeXuiResponseException('Invalid Xray agent response.');
        }
        return $decoded['result'];
    }
}
