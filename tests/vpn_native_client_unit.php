<?php

declare(strict_types=1);

use Fireball\VpnManagerV2\Clients\NativeXrayClient;
use Fireball\VpnManagerV2\Clients\ServerClientFactory;
use Fireball\VpnManagerV2\Clients\ThreeXuiClient;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiHttpException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/plugins/vpn-manager-v2/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$config = static fn(string $type = 'xray', string $url = 'https://vpn.example', string $token = '') => new ThreeXuiServerConfig(
    panelUrl: $url, panelPath: '', authType: $type, username: '', password: '', token: $token ?: str_repeat('a', 64),
    apiUrl: 'https://agent.example/control',
);
$check(ServerClientFactory::create($config()) instanceof NativeXrayClient, 'Native backend is selected explicitly.');
$check(ServerClientFactory::create($config('token')) instanceof ThreeXuiClient, 'Existing token servers preserve their backend.');
$check(ServerClientFactory::create($config('password')) instanceof ThreeXuiClient, 'Existing password servers preserve their backend.');
$calls = [];
$transport = static function ($method, $url, $payload, $encoding, $headers) use (&$calls): ThreeXuiHttpResponse {
    $calls[] = compact('method', 'url', 'payload', 'encoding', 'headers');
    $result = match ($payload['action']) {
        'inbounds' => [['id' => 24]],
        'inbound' => ['id' => 24, 'settings' => ['clients' => [['id' => 'old-key', 'email' => 'old@example.test']]]],
        'traffic' => ['email' => 'old@example.test', 'up' => 10, 'down' => 20],
        'hwid' => ['status' => 200],
        'adopt_preview' => ['fingerprint' => str_repeat('b', 64), 'matched' => 1, 'missing' => 0],
        default => ['success' => true],
    };
    return new ThreeXuiHttpResponse(200, 'application/json', json_encode(['protocol' => 'fireball-xray/1', 'result' => $result]));
};
$client = new NativeXrayClient($config(), $transport);
$check($client->testConnection()->inboundCount === 1, 'Health and inventory are checked.');
$check($client->findClient(24, 'old-key')['email'] === 'old@example.test', 'Stored credentials remain usable.');
$check($client->getClientTraffic('old@example.test')['obj']['down'] === 20, 'Traffic uses the existing scheduler contract.');
$check($client->probeSubscriptionHwid('sub-id', ['X-HWID' => 'phone-one']) === 200, 'HWID is forwarded to the native agent.');
$check($client->adopt([])['matched'] === 1, 'Adoption is available through the backend.');
$client->updateClient(24, 'old-key', ['id' => 'old-key']);
$client->deleteClient(24, 'old-key', 'old@example.test');
foreach ($calls as $call) {
    $check($call['url'] === 'https://agent.example/control/v1/rpc', 'Agent address does not replace the public VPN address.');
    $check($call['method'] === 'POST' && $call['encoding'] === 'json', 'RPC uses JSON POST.');
}
$check($calls[count($calls) - 2]['payload']['arguments']['credential'] === 'old-key', 'Updates preserve the stable key.');

$unsafe = new NativeXrayClient(new ThreeXuiServerConfig('http://unsafe.example', '', 'xray', '', '', str_repeat('a', 64)), $transport);
try {
    $unsafe->authenticate();
    throw new RuntimeException('HTTP was accepted.');
} catch (ThreeXuiTransportException) {
}
$failure = new NativeXrayClient($config(), static fn() => new ThreeXuiHttpResponse(409, 'application/json',
    '{"protocol":"fireball-xray/1","error":"credential_mismatch","details":"SECRET-NEVER-DISPLAY"}'));
try {
    $failure->authenticate();
    throw new RuntimeException('Agent failure was accepted.');
} catch (ThreeXuiHttpException $exception) {
    $check(str_contains($exception->getMessage(), 'Ключ клиента'), 'Operators see the specific cause.');
    $check(!str_contains($exception->getMessage(), 'SECRET'), 'Remote debug data never enters UI errors.');
}
echo "Native Xray transport and legacy backend compatibility passed.\n";

function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return '<input type="hidden" name="csrf_token" value="fixture">'; }
function view(): object {
    return new class {
        public function renderPartial(string $name, array $data = []): string {
            return $name === 'admin/shell_open' ? '<main><h1>' . htmlSC($data['title']) . '</h1>' : '</main>';
        }
    };
}
$render = static function (array $result = [], array $inboundResult = [], string $error = ''): string {
    $server = ['id' => 4, 'auth_type' => 'xray'];
    $title = 'Test server';
    $tabs = [];
    ob_start();
    require dirname(__DIR__) . '/plugins/vpn-manager-v2/views/admin/native-xray.php';
    return (string)ob_get_clean();
};
$html = $render(['matched' => 3, 'missing' => 1, 'fingerprint' => str_repeat('b', 64)]);
$check(str_contains($html, 'Передать управление агенту') && str_contains($html, 'name="fingerprint"'), 'Adoption preview has an explicit confirmation.');
$html = $render(inboundResult: ['applied' => false, 'tag' => 'inbound-25', 'clients_preserved' => 0,
    'delete' => false, 'ticket' => 'confirmation-ticket'], error: '<script>unsafe</script>');
$check(str_contains($html, 'name="ticket" value="confirmation-ticket"'), 'Inbound confirmation binds the reviewed proposal.');
$check(!str_contains($html, '<script>unsafe</script>') && str_contains($html, '&lt;script&gt;'), 'Operator errors are escaped.');
preg_match_all('~<form\b[^>]*>(.*?)</form>~s', $html, $forms);
foreach ($forms[1] as $form) {
    $check(str_contains($form, 'name="csrf_token"'), 'Every management form includes CSRF protection.');
}
echo "Native management view, error escaping and confirmation forms passed.\n";
