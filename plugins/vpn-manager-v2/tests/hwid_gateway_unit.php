<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

if (!function_exists('return_translation')) {
    function return_translation(string $key): string { return $key; }
}

require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$json = static fn(array $body, int $status = 200): ThreeXuiHttpResponse =>
    new ThreeXuiHttpResponse($status, 'application/json', json_encode($body, JSON_THROW_ON_ERROR));

$config = new ThreeXuiServerConfig(
    panelUrl: 'https://panel.example.invalid',
    panelPath: '',
    authType: 'token',
    username: '',
    password: '',
    token: 'super-secret-panel-token'
);

$publicHeaders = [];
$publicUrl = '';

$transport = static function (
    string $method,
    string $url,
    array $payload,
    string $encoding,
    array $headers = []
) use ($json, &$publicHeaders, &$publicUrl): ThreeXuiHttpResponse {
    $path = (string)parse_url($url, PHP_URL_PATH);

    if ($method === 'GET' && str_ends_with($path, '/panel/api/inbounds/list')) {
        return $json(['success' => true, 'obj' => []]);
    }

    if ($method === 'POST' && str_ends_with($path, '/panel/api/setting/all')) {
        return $json([
            'success' => true,
            'obj' => [
                'subEnable' => true,
                'subURI' => 'https://sub.example.invalid/native-sub/',
                'subPort' => 2096,
                'subPath' => '/native-sub/',
            ],
        ]);
    }

    if ($method === 'HEAD') {
        $publicUrl = $url;
        $publicHeaders = $headers;
        return new ThreeXuiHttpResponse(200, '', '');
    }

    throw new LogicException('Unexpected fixture request: ' . $method . ' ' . $url);
};

$client = new ThreeXuiClient($config, $transport);
$status = $client->probeSubscriptionHwid('0123456789abcdef', [
    'X-HWID' => 'device-hwid-123456',
    'X-Device-OS' => 'iOS',
    'X-Ver-OS' => '26.0',
    'X-Device-Model' => 'iPhone',
    'User-Agent' => 'Happ/fixture',
]);

$assert($status === 200, 'Native HWID HEAD did not return 200.');
$assert(
    $publicUrl === 'https://sub.example.invalid/native-sub/0123456789abcdef',
    'Wrong native subscription URL.'
);

$joined = "\n" . implode("\n", $publicHeaders) . "\n";
foreach ([
    'X-HWID: device-hwid-123456',
    'X-Device-OS: iOS',
    'X-Ver-OS: 26.0',
    'X-Device-Model: iPhone',
    'User-Agent: Happ/fixture',
] as $expected) {
    $assert(str_contains($joined, "\n" . $expected . "\n"), 'Missing forwarded header: ' . $expected);
}

$lower = strtolower($joined);
$assert(!str_contains($lower, 'authorization:'), 'Panel Authorization leaked to public sub server.');
$assert(!str_contains($lower, 'x-api-key:'), 'Panel API key leaked to public sub server.');
$assert(!str_contains($joined, 'super-secret-panel-token'), 'Panel token leaked to public sub server.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'native_sub_uri',
        'hwid_headers_forwarded',
        'panel_credentials_not_forwarded',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
