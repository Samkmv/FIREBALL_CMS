<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class NetworkConfigGenerator
{
    public function generate(array $site, array $settings): array
    {
        $lanCidr = InputValidator::nullableIpv4Cidr($site['lan_cidr'] ?? '', 'LAN CIDR');
        $recorderIp = InputValidator::requiredIpv4($site['recorder_ip'] ?? '', 'IP регистратора');
        $routerIp = InputValidator::nullableIpv4($site['router_ip'] ?? '', 'IP роутера');
        $vpnIp = InputValidator::nullableIpv4($site['vpn_ip'] ?? '', 'VPN IP роутера');
        $peerKey = InputValidator::wireGuardPublicKey($site['wireguard_public_key'] ?? '');
        $rtspPort = InputValidator::requiredPort($site['rtsp_port'] ?? 554, 'RTSP-порт', 554);
        $externalRtspPort = InputValidator::nullablePort($site['external_rtsp_port'] ?? null, 'Внешний RTSP-порт');
        $managementPort = InputValidator::nullablePort($site['management_port'] ?? null, 'Порт управления');
        $externalManagementPort = InputValidator::nullablePort($site['external_management_port'] ?? null, 'Внешний порт управления');
        $wgInterface = InputValidator::linuxInterface($settings['wireguard_interface'] ?? 'wg0', 'Интерфейс WireGuard');
        $externalInterface = InputValidator::linuxInterface($settings['external_interface'] ?? 'ens3', 'Внешний интерфейс');
        $serverIp = InputValidator::nullableIpv4($settings['wireguard_server_ip'] ?? '', 'IP WireGuard-сервера');
        $publicIp = InputValidator::nullableIpv4($settings['public_ip'] ?? '', 'Публичный IP RTSP-сервера');
        $serverPublicKey = InputValidator::wireGuardPublicKey($settings['wireguard_server_public_key'] ?? '', 'PublicKey WireGuard-сервера');
        $endpoint = InputValidator::wireGuardEndpoint($settings['wireguard_endpoint'] ?? '');

        $missing = [];
        foreach ([
            'LAN CIDR' => $lanCidr,
            'VPN IP роутера' => $vpnIp,
            'PublicKey объекта' => $peerKey,
            'IP WireGuard-сервера' => $serverIp,
            'PublicKey WireGuard-сервера' => $serverPublicKey,
            'Endpoint WireGuard' => $endpoint,
            'Публичный IP RTSP-сервера' => $publicIp,
            'Внешний RTSP-порт' => $externalRtspPort,
        ] as $label => $value) {
            if ($value === null || $value === '') {
                $missing[] = $label;
            }
        }

        $peerBlock = '';
        if ($peerKey !== null && $vpnIp !== null && $lanCidr !== null) {
            $peerBlock = "[Peer]\n"
                . 'PublicKey = ' . $peerKey . "\n"
                . 'AllowedIPs = ' . $vpnIp . '/32, ' . $lanCidr;
        }

        $routerConfig = '';
        if ($serverPublicKey !== null && $endpoint !== '' && $vpnIp !== null && $serverIp !== null) {
            $routerConfig = "WireGuard peer (Keenetic / Netcraze)\n"
                . 'Address: ' . $vpnIp . "/32\n"
                . 'PublicKey сервера: ' . $serverPublicKey . "\n"
                . 'Endpoint: ' . $endpoint . "\n"
                . 'Allowed IPs: ' . $serverIp . "/32\n"
                . 'Persistent keepalive: 25';
        }

        $routeCommand = $lanCidr !== null ? 'ip route replace ' . $lanCidr . ' dev ' . $wgInterface : '';
        $routeCheck = 'ip route get ' . $recorderIp;
        $routeExpected = $serverIp !== null
            ? $recorderIp . ' dev ' . $wgInterface . ' src ' . $serverIp
            : $recorderIp . ' dev ' . $wgInterface;

        $scripts = ['up' => '', 'down' => '', 'up_sha256' => '', 'down_sha256' => '', 'up_name' => '', 'down_name' => ''];
        $warnings = [];
        if (($managementPort === null) !== ($externalManagementPort === null)) {
            $warnings[] = 'Management NAT не создан: внутренний и внешний management-порты должны быть заполнены вместе.';
        }
        $verificationCommands = [
            'ip route get ' . $recorderIp,
            'timeout 5 bash -c \'</dev/tcp/' . $recorderIp . '/' . $rtspPort . "'",
        ];
        if ($lanCidr !== null && $serverIp !== null && $publicIp !== null && $externalRtspPort !== null) {
            $token = $this->scriptToken((string)($site['code'] ?? 'site'));
            $upName = 'ipt.tun.' . $token . '.up.sh';
            $downName = 'ipt.tun.' . $token . '.down.sh';
            $mappings = [[
                'label' => 'RTSP',
                'external_port' => $externalRtspPort,
                'internal_port' => $rtspPort,
            ]];
            if ($managementPort !== null && $externalManagementPort !== null) {
                $mappings[] = [
                    'label' => 'MANAGEMENT',
                    'external_port' => $externalManagementPort,
                    'internal_port' => $managementPort,
                ];
            }
            $up = $this->upScript($externalInterface, $wgInterface, $publicIp, $serverIp, $lanCidr, $recorderIp, $mappings);
            $down = $this->downScript($externalInterface, $wgInterface, $publicIp, $serverIp, $lanCidr, $recorderIp, $mappings);
            $scripts = [
                'up' => $up,
                'down' => $down,
                'up_sha256' => hash('sha256', $up),
                'down_sha256' => hash('sha256', $down),
                'up_name' => $upName,
                'down_name' => $downName,
            ];
            $verificationCommands[] = "iptables-save | grep -- '--dport " . $externalRtspPort . "'";
        }

        return [
            'missing' => $missing,
            'router_config' => $routerConfig,
            'peer_block' => $peerBlock,
            'route_command' => $routeCommand,
            'route_check' => $routeCheck,
            'route_expected' => $routeExpected,
            'scripts' => $scripts,
            'warnings' => $warnings,
            'verification_commands' => implode("\n", $verificationCommands),
            'router_ip' => $routerIp,
            'vpn_ip' => $vpnIp,
            'recorder_ip' => $recorderIp,
            'rtsp_port' => $rtspPort,
            'management_port' => $managementPort,
            'external_management_port' => $externalManagementPort,
            'wireguard_interface' => $wgInterface,
            'firewall_rules' => [
                'Разрешить IPv4: source any → destination any',
                'Разрешить ICMP: source any → destination any',
            ],
        ];
    }

    /** @param list<array{label:string,external_port:int,internal_port:int}> $mappings */
    private function upScript(
        string $externalInterface,
        string $wireGuardInterface,
        string $publicIp,
        string $serverIp,
        string $lanCidr,
        string $recorderIp,
        array $mappings
    ): string {
        $lines = $this->header($externalInterface, $wireGuardInterface, $publicIp, $serverIp, $lanCidr, $recorderIp);
        $lines[] = 'ip route replace "$LAN_NET" dev "$INT_IF"';
        foreach ($mappings as $mapping) {
            $lines[] = '';
            $lines[] = '# ' . $mapping['label'];
            foreach ($this->iptablesRules($mapping['external_port'], $mapping['internal_port']) as [$table, $chain, $rule]) {
                $prefix = $table === 'filter' ? 'iptables' : 'iptables -t ' . $table;
                $lines[] = $prefix . ' -C ' . $chain . ' ' . $rule . ' 2>/dev/null || ' . $prefix . ' -A ' . $chain . ' ' . $rule;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<array{label:string,external_port:int,internal_port:int}> $mappings */
    private function downScript(
        string $externalInterface,
        string $wireGuardInterface,
        string $publicIp,
        string $serverIp,
        string $lanCidr,
        string $recorderIp,
        array $mappings
    ): string {
        $lines = $this->header($externalInterface, $wireGuardInterface, $publicIp, $serverIp, $lanCidr, $recorderIp);
        foreach (array_reverse($mappings) as $mapping) {
            $lines[] = '';
            $lines[] = '# ' . $mapping['label'];
            foreach (array_reverse($this->iptablesRules($mapping['external_port'], $mapping['internal_port'])) as [$table, $chain, $rule]) {
                $prefix = $table === 'filter' ? 'iptables' : 'iptables -t ' . $table;
                $lines[] = $prefix . ' -D ' . $chain . ' ' . $rule . ' 2>/dev/null || true';
            }
        }
        $lines[] = 'ip route del "$LAN_NET" dev "$INT_IF" 2>/dev/null || true';

        return implode("\n", $lines) . "\n";
    }

    private function header(
        string $externalInterface,
        string $wireGuardInterface,
        string $publicIp,
        string $serverIp,
        string $lanCidr,
        string $recorderIp
    ): array {
        return [
            '#!/bin/sh',
            'set -eu',
            '',
            "EXT_IF='" . $externalInterface . "'",
            "INT_IF='" . $wireGuardInterface . "'",
            "EXT_IP='" . $publicIp . "'",
            "INT_IP='" . $serverIp . "'",
            "LAN_NET='" . $lanCidr . "'",
            "LAN_IP='" . $recorderIp . "'",
        ];
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function iptablesRules(int $externalPort, int $internalPort): array
    {
        $fake = (string)$externalPort;
        $real = (string)$internalPort;

        return [
            ['nat', 'PREROUTING', '-i "$EXT_IF" -d "$EXT_IP" -p tcp --dport ' . $fake . ' -j DNAT --to-destination "$LAN_IP":' . $real],
            ['nat', 'POSTROUTING', '-o "$INT_IF" -d "$LAN_IP" -p tcp --dport ' . $real . ' -j SNAT --to-source "$INT_IP"'],
            ['filter', 'FORWARD', '-i "$EXT_IF" -o "$INT_IF" -p tcp -d "$LAN_IP" --dport ' . $real . ' -m conntrack --ctstate NEW,ESTABLISHED -j ACCEPT'],
            ['filter', 'FORWARD', '-i "$INT_IF" -o "$EXT_IF" -p tcp -s "$LAN_IP" --sport ' . $real . ' -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT'],
            ['nat', 'OUTPUT', '-d "$EXT_IP" -p tcp --dport ' . $fake . ' -j DNAT --to-destination "$LAN_IP":' . $real],
        ];
    }

    private function scriptToken(string $code): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $code)) {
            throw new RuntimeException('Код объекта нельзя использовать в имени сетевого скрипта.');
        }

        return ctype_digit($code) ? str_pad($code, 3, '0', STR_PAD_LEFT) : strtolower($code);
    }
}
