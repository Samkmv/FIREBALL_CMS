<?php

namespace Fireball\VpnManagerV2\Clients;

use Fireball\VpnManagerV2\DTO\ConnectionTestResult;

interface ThreeXuiClientInterface
{
    public function authenticate(): void;

    public function testConnection(): ConnectionTestResult;

    public function listInbounds(): array;

    public function getInbound(int $remoteInboundId): array;

    public function getClientTraffic(string $clientIdentifier): array;

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array;

    public function addClient(int $remoteInboundId, array $client): array;

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array;

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array;
}
