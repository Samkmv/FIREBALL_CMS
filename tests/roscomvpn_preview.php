<?php
declare(strict_types=1);
ini_set('display_errors', '0');

// Standalone device-test endpoint. No CMS bootstrap, database or server writes.
// Serve over HTTPS on a separate test host; keep ROSCOMVPN_TEST_DIR private.
header('Cache-Control: private, no-store, must-revalidate');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$secret = (string)getenv('ROSCOMVPN_TEST_TOKEN');
$provided = $_GET['token'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
    || preg_match('/\A[a-f0-9]{64}\z/D', $secret) !== 1
    || !is_string($provided) || !hash_equals($secret, $provided)) {
    http_response_code(404);
    exit;
}
try {
    $directory = realpath((string)getenv('ROSCOMVPN_TEST_DIR'));
    if ($directory === false) throw new RuntimeException('Test directory missing');
    $config = json_decode((string)file_get_contents($directory . '/xray.json'), true, 64, JSON_THROW_ON_ERROR);
    $incy = strcasecmp(trim((string)($_SERVER['HTTP_X_CLIENT'] ?? '')), 'INCY') === 0
        || preg_match('~^INCY/~i', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')) === 1;
    if ($incy && getenv('ROSCOMVPN_ALLOW_INCY_LAB') !== '1') {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":"incy_dns_patch_requires_device_verification"}';
        exit;
    }
    $profile = json_decode((string)file_get_contents($directory . ($incy ? '/routing.incy.json' : '/routing.original.json')),
        false, 32, JSON_THROW_ON_ERROR);
    // Full-config clients must load original geo files; automatic trimming of
    // native JSON rules is not consistently supported. These originals are small.
    if ($incy) {
        $profile->useChunkFiles = false;
    } else {
        $profile->UseChunkFiles = 'false';
    }
    if (empty($config['inbounds']) || empty($config['outbounds']) || !$profile instanceof stdClass) {
        throw new RuntimeException('Incomplete test profile');
    }
    $link = ($incy ? 'incy' : 'happ') . '://routing/onadd/'
        . base64_encode(json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    if (strlen($link) > 4096) throw new RuntimeException('Routing header too large');
    header('Content-Type: application/json; charset=utf-8');
    header('profile-title: base64:' . base64_encode('RoscomVPN LAB'));
    header('profile-update-interval: 1');
    header('routing: ' . $link);
    if (!$incy) {
        header('routing-enable: true');
        header('dns-from-json-enable: true');
    }
    echo json_encode([$config], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('RoscomVPN test endpoint: ' . $exception::class);
    http_response_code(503);
}
