<?php

namespace FBL;

/** Pure pre-session origin policy. Never uses a request host as a redirect target. */
final class CanonicalOrigin
{
    public static function redirectUrl(string $baseUrl, array $server, array $trustedProxies = []): ?string
    {
        $base = parse_url($baseUrl);
        if (!is_array($base) || !in_array($base['scheme'] ?? '', ['http', 'https'], true)
            || empty($base['host']) || isset($base['user'], $base['pass'])
            || isset($base['user']) || isset($base['query']) || isset($base['fragment'])) {
            return null;
        }
        $scheme = $base['scheme'];
        $host = strtolower($base['host']);
        $port = (int)($base['port'] ?? ($scheme === 'https' ? 443 : 80));
        $requestHost = (string)($server['HTTP_HOST'] ?? '');
        if (!preg_match('/^(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]{1,5})?$/D', $requestHost)) {
            $requestHost = '';
        }
        $secure = !empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off';
        if (!$secure && in_array((string)($server['REMOTE_ADDR'] ?? ''), $trustedProxies, true)) {
            $proto = strtolower(trim(explode(',', (string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
            $secure = $proto === 'https' || strtolower((string)($server['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
        }
        $requestScheme = $secure ? 'https' : 'http';
        $request = $requestHost !== '' ? parse_url($requestScheme . '://' . $requestHost) : [];
        if (strtolower((string)($request['host'] ?? '')) === $host
            && (int)($request['port'] ?? ($secure ? 443 : 80)) === $port && $requestScheme === $scheme) {
            return null;
        }
        $authority = $host . (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80) ? '' : ':' . $port);
        $uri = (string)($server['REQUEST_URI'] ?? '/');
        // Preserve the encoded path/query verbatim, including locale and installation subdirectory.
        if (!str_starts_with($uri, '/') || preg_match('/[\x00-\x20\x7f]/', $uri)) {
            $uri = '/';
        }
        return $scheme . '://' . $authority . $uri;
    }
}
