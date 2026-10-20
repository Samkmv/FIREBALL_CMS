<?php
declare(strict_types=1);

// Real multipart HTTP uploads; all generated files and PHP runtime paths stay in tests/.tmp.
// No CMS bootstrap, database, notifications, browser or audio device is used.
const TOY_SOUND_PROJECT = __DIR__ . '/..';

function config_value(string $key, mixed $fallback = null): mixed { return $fallback; }
function return_translation(string $key): string { return $key; }
function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_csrf_field(): string { return '<input type="hidden" name="needCSRFToken" value="fixture">'; }
function view(): object { return new class { public function renderPartial(string $name, array $data = []): string { return str_ends_with($name, 'shell_open') ? '<main>' : '</main>'; } }; }
function request(): object { return new class { public function header(string $key, string $fallback = ''): string { return (string)($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] ?? $fallback); } }; }
function abort(int $code = 404): never { http_response_code($code); exit; }
final class FireballPluginToyCarRental { public static function t(string $key): string { return $key; } }

foreach (['UploadSettings', 'UploadException', 'UploadPolicy', 'SafeUploadService'] as $dependency) {
    require TOY_SOUND_PROJECT . '/app/Services/' . $dependency . '.php';
}
require TOY_SOUND_PROJECT . '/plugins/toy-car-rental/SoundStorage.php';

if (PHP_SAPI === 'cli-server') {
    $scratch = (string)getenv('TOY_SOUND_TEST_ROOT');
    $testBase = realpath(__DIR__ . '/.tmp');
    if (!$testBase || !str_starts_with((string)realpath($scratch), $testBase . DIRECTORY_SEPARATOR)
        || !hash_equals((string)getenv('TOY_SOUND_TEST_TOKEN'), (string)($_SERVER['HTTP_X_TEST_TOKEN'] ?? ''))) abort(403);
    $storage = new FireballToyRentalSoundStorage($scratch . '/sounds');
    $path = parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH);
    try {
        if ($path === '/asset') $storage->serve((string)($_GET['file'] ?? ''));
        header('Content-Type: application/json');
        $response = match ($path) {
            '/health' => ['ready' => true],
            '/upload', '/upload-spoofed-size' => (static function () use ($path, $storage): array {
                $file = $_FILES['custom_sound'] ?? [];
                $uploaded = is_uploaded_file((string)($file['tmp_name'] ?? ''));
                if ($path === '/upload-spoofed-size') $file['size'] = 1;
                $saved = $storage->upload($file);
                $asset = $storage->asset($saved['file']);
                return ['saved' => $saved, 'uploaded' => $uploaded, 'size' => $asset['size'],
                    'sha256' => hash_file('sha256', $asset['path']), 'permissions' => fileperms($asset['path']) & 0777];
            })(),
            '/fake-upload' => $storage->upload(['name' => 'not-uploaded.wav', 'tmp_name' => $scratch . '/outside.wav', 'size' => filesize($scratch . '/outside.wav'), 'error' => UPLOAD_ERR_OK]),
            '/asset-info' => ['exists' => $storage->asset((string)($_GET['file'] ?? '')) !== null],
            '/delete' => (static function () use ($storage): array {
                $file = (string)($_GET['file'] ?? '');
                $storage->delete($file);
                return ['exists' => $storage->asset($file) !== null];
            })(),
            default => throw new RuntimeException('Unknown fixture route'),
        };
        echo json_encode($response, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        http_response_code(422);
        echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit;
}

function soundCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAILED: ' . $label);
    $GLOBALS['soundChecks']++;
}

function soundHttp(string $base, string $token, string $path, string $method = 'GET', ?string $body = null, array $headers = []): array
{
    $headers = array_merge(['X-Test-Token: ' . $token, 'Connection: close'], $headers);
    if ($body !== null) $headers[] = 'Content-Length: ' . strlen($body);
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body ?? '', 'ignore_errors' => true, 'timeout' => 10]]);
    $content = @file_get_contents($base . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    if ($content === false || $responseHeaders === []) throw new RuntimeException('Fixture HTTP request failed: ' . $path);
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $status);
    $parsed = [];
    foreach (array_slice($responseHeaders, 1) as $header) {
        if (str_contains($header, ':')) {
            [$key, $value] = explode(':', $header, 2);
            $parsed[strtolower(trim($key))] = trim($value);
        }
    }
    return ['status' => (int)($status[1] ?? 0), 'headers' => $parsed, 'body' => $content];
}

function soundUpload(string $base, string $token, string $name, string $bytes, string $mime = 'audio/wav', string $path = '/upload'): array
{
    $boundary = 'rental-' . bin2hex(random_bytes(12));
    $name = str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
    $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"custom_sound\"; filename=\"" . $name
        . "\"\r\nContent-Type: " . $mime . "\r\n\r\n" . $bytes . "\r\n--" . $boundary . "--\r\n";
    $response = soundHttp($base, $token, $path, 'POST', $body, ['Content-Type: multipart/form-data; boundary=' . $boundary]);
    $response['json'] = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    return $response;
}

function soundWav(int $dataBytes): string
{
    return 'RIFF' . pack('V', $dataBytes + 36) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
        . 'data' . pack('V', $dataBytes) . str_repeat("\0", $dataBytes);
}

function soundRemoveScratch(string $scratch): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isLink() || !$entry->isDir()) unlink($entry->getPathname());
        else rmdir($entry->getPathname());
    }
    rmdir($scratch);
}

$GLOBALS['soundChecks'] = 0;
$temporaryBase = __DIR__ . '/.tmp';
if (!is_dir($temporaryBase)) mkdir($temporaryBase, 0700, true);
$scratch = $temporaryBase . '/toy-rental-sound-' . bin2hex(random_bytes(8));
mkdir($scratch, 0700);
foreach (['uploads', 'sessions', 'temp'] as $directory) mkdir($scratch . '/' . $directory, 0700);
$server = null;
try {
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
    if ($socket === false) throw new RuntimeException('Loopback bind unavailable: ' . $socketMessage);
    $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $token = bin2hex(random_bytes(24));
    $base = 'http://127.0.0.1:' . $port;
    $command = [PHP_BINARY, '-d', 'upload_tmp_dir=' . $scratch . '/uploads', '-d', 'session.save_path=' . $scratch . '/sessions',
        '-d', 'sys_temp_dir=' . $scratch . '/temp', '-d', 'upload_max_filesize=8M', '-d', 'post_max_size=9M',
        '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . $scratch . '/php-errors.log',
        '-S', '127.0.0.1:' . $port, __FILE__];
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $scratch . '/server.log', 'a'],
        2 => ['file', $scratch . '/server.log', 'a']], $pipes, $scratch,
        array_replace(getenv(), ['TOY_SOUND_TEST_ROOT' => $scratch, 'TOY_SOUND_TEST_TOKEN' => $token,
            'TMPDIR' => $scratch . '/temp', 'TMP' => $scratch . '/temp', 'TEMP' => $scratch . '/temp']));
    if (!is_resource($server)) throw new RuntimeException('Cannot start fixture PHP server');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        try { $ready = soundHttp($base, $token, '/health')['status'] === 200; } catch (RuntimeException) {}
        if ($ready) break;
        if (!proc_get_status($server)['running']) break;
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('Fixture server did not start: ' . file_get_contents($scratch . '/server.log'));

    $wav = soundWav(1600);
    file_put_contents($scratch . '/outside.wav', $wav);
    $uploaded = soundUpload($base, $token, 'ride-alert.WAV', $wav, 'text/plain');
    soundCheck($uploaded['status'] === 200 && $uploaded['json']['uploaded'], 'real multipart WAV accepted despite untrusted client MIME');
    $firstFile = $uploaded['json']['saved']['file'];
    soundCheck(FireballToyRentalSoundStorage::validFilename($firstFile) && str_ends_with($firstFile, '.wav')
        && $firstFile !== 'ride-alert.WAV', 'random lowercase storage filename');
    soundCheck($uploaded['json']['size'] === strlen($wav) && $uploaded['json']['sha256'] === hash('sha256', $wav), 'WAV bytes preserved');
    soundCheck($uploaded['json']['permissions'] === 0600, 'sound file has private permissions');
    soundCheck((fileperms($scratch . '/sounds') & 0777) === 0700, 'sound directory has private permissions');

    $second = soundUpload($base, $token, 'ride-alert.WAV', $wav);
    soundCheck($second['status'] === 200 && $second['json']['saved']['file'] !== $firstFile, 'repeated uploads never overwrite by original name');
    $mp3 = str_repeat(hex2bin('fffb9000') . str_repeat("\0", 413), 4);
    $mp3Upload = soundUpload($base, $token, 'silent.mp3', $mp3, 'audio/mpeg');
    soundCheck($mp3Upload['status'] === 200 && str_ends_with($mp3Upload['json']['saved']['file'], '.mp3'), 'MPEG audio frame upload accepted');

    foreach ([['bad.php', $wav, 'audio/wav'], ['bad.mp3', $wav, 'audio/mpeg'], ['bad.wav', '<?php echo 1;', 'audio/wav'],
        ['empty.wav', '', 'audio/wav'], ['bad.txt', 'plain text', 'audio/wav']] as [$name, $bytes, $mime]) {
        $rejected = soundUpload($base, $token, $name, $bytes, $mime);
        soundCheck($rejected['status'] === 422 && isset($rejected['json']['error']), 'invalid extension/content rejected: ' . $name);
    }
    $fake = soundHttp($base, $token, '/fake-upload');
    soundCheck($fake['status'] === 422, 'ordinary local file cannot masquerade as HTTP upload');
    $untrustedName = '<img src=x onerror=alert(1)>.wav';
    $named = soundUpload($base, $token, '../../' . $untrustedName, $wav);
    soundCheck($named['status'] === 200 && $named['json']['saved']['name'] === $untrustedName
        && !str_contains($named['json']['saved']['file'], '<'), 'path stripped from original name and never used as storage filename');

    // Actual settings template must not turn an original upload name into HTML.
    $settings = ['operator_role_id' => 0, 'fixed_durations' => [5, 10], 'max_ride_minutes' => 120, 'default_duration' => 10,
        'default_minute_price' => 20, 'currency' => 'RUB', 'auto_refresh_seconds' => 5, 'sound_enabled' => 1,
        'overdue_push_enabled' => 1, 'custom_sound_name' => $untrustedName];
    $operatorRoles = $notificationUsers = $selectedNotificationUserIds = [];
    $customSoundUrl = '/fixture?file=' . rawurlencode($named['json']['saved']['file']);
    ob_start();
    require TOY_SOUND_PROJECT . '/plugins/toy-car-rental/views/settings.php';
    $settingsHtml = (string)ob_get_clean();
    $document = new DOMDocument();
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $settingsHtml);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);
    soundCheck($xpath->query('//img | //script | //@onerror')->length === 0 && str_contains($settingsHtml, htmlSC($untrustedName)), 'untrusted upload name is displayed as escaped text');

    $limitWav = soundWav(FireballToyRentalSoundStorage::MAX_BYTES - 44);
    $atLimit = soundUpload($base, $token, 'limit.wav', $limitWav);
    soundCheck($atLimit['status'] === 200 && $atLimit['json']['size'] === FireballToyRentalSoundStorage::MAX_BYTES, 'exactly 5 MiB accepted');
    $tooLarge = soundWav(FireballToyRentalSoundStorage::MAX_BYTES - 43);
    soundCheck(soundUpload($base, $token, 'over-limit.wav', $tooLarge)['status'] === 422, '5 MiB plus one byte rejected');
    soundCheck(soundUpload($base, $token, 'spoofed.wav', $tooLarge, 'audio/wav', '/upload-spoofed-size')['status'] === 422, 'actual size checked even when upload metadata is spoofed');
    unset($limitWav, $tooLarge);

    $assetPath = '/asset?file=' . rawurlencode($firstFile);
    $full = soundHttp($base, $token, $assetPath);
    soundCheck($full['status'] === 200 && $full['body'] === $wav && (int)$full['headers']['content-length'] === strlen($wav), 'full audio response has exact bytes/length');
    soundCheck($full['headers']['content-type'] === 'audio/wav' && $full['headers']['accept-ranges'] === 'bytes'
        && $full['headers']['x-content-type-options'] === 'nosniff' && str_starts_with($full['headers']['cache-control'], 'private'), 'private audio headers and MIME retained');
    soundCheck($full['headers']['content-disposition'] === 'inline; filename="rental-alert.wav"', 'download header uses safe fixed name');
    foreach ([['bytes=10-19', 10, 19], ['bytes=10-', 10, strlen($wav) - 1], ['bytes=-16', strlen($wav) - 16, strlen($wav) - 1],
        ['bytes=0-0', 0, 0], ['bytes=0-99999999', 0, strlen($wav) - 1], ['bytes=-99999999', 0, strlen($wav) - 1]] as [$range, $start, $end]) {
        $partial = soundHttp($base, $token, $assetPath, 'GET', null, ['Range: ' . $range]);
        soundCheck($partial['status'] === 206 && $partial['body'] === substr($wav, $start, $end - $start + 1)
            && $partial['headers']['content-range'] === "bytes $start-$end/" . strlen($wav)
            && (int)$partial['headers']['content-length'] === $end - $start + 1, 'exact byte range: ' . $range);
    }
    foreach (['bytes=99999999-', 'bytes=20-10', 'bytes=-0', 'bytes=-', 'bytes=0-1,3-4', 'bytes=garbage'] as $range) {
        $invalid = soundHttp($base, $token, $assetPath, 'GET', null, ['Range: ' . $range]);
        soundCheck($invalid['status'] === 416 && $invalid['body'] === '' && $invalid['headers']['content-range'] === 'bytes */' . strlen($wav), 'invalid byte range rejected: ' . $range);
    }

    $ordinaryName = 'ordinary.wav';
    file_put_contents($scratch . '/sounds/' . $ordinaryName, $wav);
    $symlinkName = str_repeat('f', 40) . '.wav';
    symlink($scratch . '/outside.wav', $scratch . '/sounds/' . $symlinkName);
    foreach (['../outside.wav', '../../outside.wav', $ordinaryName, $symlinkName, str_repeat('a', 40) . '.php'] as $invalidFile) {
        soundCheck(soundHttp($base, $token, '/asset?file=' . rawurlencode($invalidFile))['status'] === 404, 'invalid/outside/non-owned asset blocked: ' . $invalidFile);
        soundHttp($base, $token, '/delete?file=' . rawurlencode($invalidFile));
    }
    soundCheck(file_get_contents($scratch . '/outside.wav') === $wav && file_get_contents($scratch . '/sounds/' . $ordinaryName) === $wav,
        'invalid deletes preserve outside and non-owned files');
    soundHttp($base, $token, '/delete?file=' . rawurlencode($firstFile));
    soundCheck(!is_file($scratch . '/sounds/' . $firstFile), 'owned audio file can be deleted');
    soundCheck(is_file($scratch . '/sounds/' . $second['json']['saved']['file']) && is_file($scratch . '/sounds/' . $mp3Upload['json']['saved']['file']), 'deleting one sound preserves other uploaded sounds');
    echo 'PASS: ' . $GLOBALS['soundChecks'] . " real HTTP sound-storage checks. No CMS data or audio devices used.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    soundRemoveScratch($scratch);
}
