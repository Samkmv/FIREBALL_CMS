<?php

declare(strict_types=1);

// No database, cache, network or installed Happ application is required.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
final class FireballPluginVpnManagerV2
{
    public static function t(string $key): string
    {
        return $key;
    }
}
function htmlSC(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionMetadataService;
use Fireball\VpnManagerV2\Support\HappRoutingProfile;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

$count = 0;
$assert = static function (bool $condition, string $message) use (&$count): void {
    ++$count;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$codec = new HappRoutingProfile();
$profile = ['Name' => 'Тест Happ 🌐', 'GlobalProxy' => 'true', 'DirectSites' => ['domain:example.org'],
    'DirectIp' => ['10.0.0.0/8'], 'DnsHosts' => (object)[], 'LastUpdated' => '1789230000'];
$json = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
$link = $codec->normalize($json);
$assert(str_starts_with($link, 'happ://routing/onadd/'), 'Profile must activate on import.');
$decoded = json_decode(base64_decode(substr($link, strlen('happ://routing/onadd/')), true), true, 32, JSON_THROW_ON_ERROR);
$assert($decoded['Name'] === $profile['Name'] && $decoded['DirectSites'] === $profile['DirectSites']
    && $decoded['LastUpdated'] === $profile['LastUpdated'], 'UTF-8 or profile fields changed.');
$roundTrip = json_decode(base64_decode(substr($link, strlen('happ://routing/onadd/')), true));
$assert($roundTrip->DnsHosts instanceof stdClass, 'Empty DNS host map became a list.');
$assert($codec->normalize('happ://routing/add/' . base64_encode($json)) === $link, '3x-ui add link not supported.');
$assert($codec->normalize($link) === $link && $codec->normalize('') === '', 'Normalization is unstable.');
$assert($codec->normalize('{"Name":"Boolean","GlobalProxy":true}') === $codec->normalize('{"Name":"Boolean","GlobalProxy":"true"}'),
    'Boolean profile compatibility failed.');
foreach ([
    [], 'javascript:alert(1)', 'https://example.org/routing.json', 'happ://routing/off',
    'happ://routing/onadd/!!!!', 'happ://routing/onadd/' . base64_encode('not json'),
    $link . "\r\nX-Injected: true", '{}', '[]', '{"Name":""}', '{"Name":[]}',
    '{"Name":"Test","DirectSites":"example.org"}', '{"Name":"Test","ProxyIp":[{}]}',
    '{"Name":"Test","GlobalProxy":"maybe"}', '{"Name":"Test","BlockSites":["bad\\nrule"]}',
    json_encode(['Name' => 'Oversized', 'DirectSites' => [str_repeat('a', 4000)]]),
    str_repeat(' ', HappRoutingProfile::MAX_INPUT_BYTES + 1),
] as $invalid) {
    $rejected = false;
    try {
        $codec->normalize($invalid);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $assert($rejected, 'Invalid or unsafe profile was accepted.');
}
$defaults = SettingsService::defaults();
$validator = new SettingsValidator();
$settings = $validator->validate(array_replace($defaults, [
    'happ_routing_enabled' => '1', 'happ_routing_link' => $json,
]), $defaults)->toArray();
$assert($settings['happ_routing_enabled'] === true && $settings['happ_routing_link'] === $link, 'Settings did not normalize.');
$metadata = new VpnSubscriptionMetadataService();
$headers = $metadata->headers([], $settings);
$assert($headers['routing'] === $link && $headers['routing-enable'] === 'true', 'Subscription routing headers missing.');
$assert(!isset($metadata->headers([], $defaults)['routing']), 'Defaults must not change client routing.');
$settings['happ_routing_enabled'] = false;
$assert($codec->headers($settings) === ['routing' => 'happ://routing/off', 'routing-enable' => '0']
    && $codec->activeLink($settings) === '', 'Disable failed to revoke routing and hide the action.');
$settings['happ_routing_link'] = '';
$assert($codec->headers($settings) === [], 'Clearing a profile should stop managing routing.');
$rejected = false;
try {
    $validator->validate(array_replace($defaults, ['happ_routing_enabled' => true]), $defaults);
} catch (ValidationException) {
    $rejected = true;
}
$assert($rejected, 'Enabled routing requires a profile.');
$invalidStored = $validator->validateStored(array_replace($defaults, [
    'happ_routing_enabled' => true, 'happ_routing_link' => 'javascript:alert(1)',
]))->toArray();
$assert($invalidStored['happ_routing_enabled'] === false && $codec->headers($invalidStored) === [],
    'Invalid stored settings must not break subscriptions or render unsafe links.');
$render = static function (string $routingLink): string {
    ob_start();
    require dirname(__DIR__) . '/views/partials/happ-routing-link.php';
    return (string)ob_get_clean();
};
$html = $render($link);
$assert(str_contains($html, 'href="' . htmlSC($link) . '"') && str_contains($html, 'data-vpn-v2-copy-value="' . htmlSC($link)),
    'Apply and copy actions do not use the actual generated link.');
$assert(!str_contains($render('javascript:alert(1)'), '<a'), 'Unsafe link rendered.');
foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
    $strings = require dirname(__DIR__) . '/lang/' . $language . '.php';
    $assert(!empty($strings['vpn_manager_v2_happ_title']) && !empty($strings['vpn_manager_v2_error_happ_routing']),
        'Happ translations missing: ' . $language);
}
echo json_encode(['status' => 'ok', 'assertions' => $count], JSON_UNESCAPED_SLASHES) . PHP_EOL;
