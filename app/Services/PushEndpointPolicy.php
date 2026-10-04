<?php

namespace App\Services;

/** Only browser push providers may receive VAPID requests. */
final class PushEndpointPolicy
{
    public static function accepts(string $url): bool
    {
        if (strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return false;
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (int)($parts['port'] ?? 443) !== 443 || empty($parts['path'])) return false;
        $host = strtolower((string)($parts['host'] ?? ''));
        return in_array($host, ['fcm.googleapis.com', 'android.googleapis.com', 'web.push.apple.com'], true)
            || $host === 'updates.push.services.mozilla.com'
            || str_ends_with($host, '.push.services.mozilla.com')
            || str_ends_with($host, '.notify.windows.com');
    }

    public static function publicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        // Reject IPv4-mapped IPv6 and addresses outside global unicast.
        if (str_contains($ip, ':')) {
            $packed = inet_pton($ip);
            return is_string($packed) && (ord($packed[0]) & 0xe0) === 0x20;
        }
        $octets = array_map('intval', explode('.', $ip));
        return !($octets[0] === 100 && $octets[1] >= 64 && $octets[1] <= 127)
            && $octets[0] < 224;
    }

    public static function resolve(string $url): array
    {
        if (!self::accepts($url)) throw new \RuntimeException('Untrusted push endpoint.');
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach (is_array($records) ? $records : [] as $record) {
            $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip === '') continue;
            if (!self::publicIp($ip)) throw new \RuntimeException('Push endpoint resolved to a non-public address.');
            $ips[] = $ip;
        }
        if ($ips === []) throw new \RuntimeException('Unable to resolve push endpoint.');
        $ip = $ips[0];
        return [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)];
    }
}
