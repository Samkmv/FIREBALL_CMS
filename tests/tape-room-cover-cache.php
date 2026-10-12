<?php
declare(strict_types=1);

// Real controller/library/storage over HTTP, using an isolated SQLite library.
// The fixture's header supplies an authenticated identity; CMS login is unchanged.
if (PHP_SAPI === 'cli-server') {
    $root = realpath((string)getenv('TAPE_ROOM_COVER_TEST_ROOT'));
    $temporary = realpath(__DIR__ . '/.tmp');
    if (!$root || !$temporary || !str_starts_with($root, $temporary . DIRECTORY_SEPARATOR)) {
        http_response_code(500); exit('Invalid fixture directory');
    }
    define('STORAGE', $root);
    require dirname(__DIR__) . '/plugins/reel-player/src/Services/MediaStorage.php';
    require dirname(__DIR__) . '/plugins/reel-player/src/Repositories/Library.php';
    require dirname(__DIR__) . '/plugins/reel-player/src/Controllers/PlayerController.php';
    function get_user(): array { return ['id' => (int)($_SERVER['HTTP_X_TEST_OWNER'] ?? 0)]; }
    function get_route_param(string $key): int|string { return $key === 'file' ? $GLOBALS['fixtureAsset'] : (int)$GLOBALS['fixtureTrack']; }
    function session(): object { return new class { public function close(): void {} }; }
    function abort(string $message = '', int $status = 404): never {
        http_response_code($status); header('Cache-Control: private, no-store'); exit($message);
    }
    function request(): object { return new class { public function get(string $key, mixed $default = null): mixed { return $_GET[$key] ?? $default; } }; }
    function db(): object {
        static $database;
        return $database ??= new class {
            private PDO $pdo;
            private PDOStatement $statement;
            public function __construct() { $this->pdo = new PDO('sqlite:' . STORAGE . '/library.sqlite'); }
            public function query(string $sql, array $values = []): static {
                $this->statement = $this->pdo->prepare($sql); $this->statement->execute($values); return $this;
            }
            public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
        };
    }
    $router = new class {
        public string $assetPattern = '';
        public mixed $assetHandler = null;
        public function get(string $pattern, mixed $handler): static {
            if (str_starts_with($pattern, '/plugins/reel-player/assets/')) { $this->assetPattern = $pattern; $this->assetHandler = $handler; }
            return $this;
        }
        public function post(string $pattern, mixed $handler): static { return $this; }
        public function middleware(array $guards): static { return $this; }
    };
    require dirname(__DIR__) . '/plugins/reel-player/routes.php';
    $path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (str_starts_with($path, '/plugins/reel-player/assets/')) {
        if (!preg_match('~^' . $router->assetPattern . '$~D', $path, $asset)) abort('Not found', 404);
        // Reproduce the legacy session cache limiter present in the real CMS.
        header('Pragma: no-cache');
        header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
        header('Set-Cookie: cover-fixture-session=private; HttpOnly; SameSite=Lax');
        $GLOBALS['fixtureAsset'] = $asset['file']; ($router->assetHandler)();
    }
    if (!get_user()['id']) abort('Login required', 401);
    if (!preg_match('~^/(cover|media)/(\d+)$~D', (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $route)) abort('Not found', 404);
    $GLOBALS['fixtureTrack'] = (int)$route[2];
    (new \Fireball\ReelPlayer\Controllers\PlayerController())->{$route[1]}();
    exit;
}

$checks = 0;
$check = static function (bool $condition, string $description) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($description);
};
$temporary = __DIR__ . '/.tmp';
if (!is_dir($temporary)) mkdir($temporary, 0700, true);
$root = $temporary . '/tape-room-cover-cache-' . bin2hex(random_bytes(8));
mkdir($root . '/reel-player/7', 0700, true);
$cover = '7/' . str_repeat('a', 40) . '.png';
$audio = '7/' . str_repeat('b', 40) . '.wav';
$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/G94AAAAASUVORK5CYII=', true);
file_put_contents($root . '/reel-player/' . $cover, $bytes);
file_put_contents($root . '/reel-player/' . $audio, '0123456789');
$pdo = new PDO('sqlite:' . $root . '/library.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE reel_tracks(id INTEGER PRIMARY KEY,owner_id INTEGER,source TEXT,file_path TEXT,mime TEXT,cover_path TEXT,cover_mime TEXT)');
$insert = $pdo->prepare('INSERT INTO reel_tracks VALUES(?,?,?,?,?,?,?)');
$insert->execute([1, 7, 'local', $audio, 'audio/wav', $cover, 'image/png']);
$insert->execute([2, 8, 'local', $audio, 'audio/wav', $cover, 'image/png']);
$insert->execute([3, 7, 'local', $audio, 'audio/wav', null, null]);

$port = 0;
$process = null;
$request = static function (string $path, array $headers = [], string $method = 'GET') use (&$port): array {
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 3, 'follow_location' => 0]]);
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    $raw = $http_response_header ?? [];
    preg_match('/^HTTP\/\S+ (\d+)/', $raw[0] ?? '', $status);
    $fields = [];
    foreach (array_slice($raw, 1) as $line) {
        if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $fields[strtolower($name)] = trim($value); }
    }
    return [(int)($status[1] ?? 0), $fields, $body === false ? '' : $body];
};
try {
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!$listener) throw new RuntimeException($errorMessage);
    $address = stream_socket_get_name($listener, false); fclose($listener);
    $port = (int)substr($address, strrpos($address, ':') + 1);
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root, '-S', '127.0.0.1:' . $port, __FILE__],
        [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']], $pipes, dirname(__DIR__),
        ['TAPE_ROOM_COVER_TEST_ROOT' => $root]);
    if (!is_resource($process)) throw new RuntimeException('Unable to start fixture server');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, .05);
        if ($socket) { fclose($socket); $ready = true; break; }
        usleep(20000);
    }
    $check($ready, 'Fixture server starts');
    foreach (['light', 'dark'] as $theme) {
        $image = dirname(__DIR__) . '/plugins/reel-player/assets/cover-' . $theme . '.png';
        $hash = hash_file('sha256', $image);
        $url = '/plugins/reel-player/assets/cover-' . $theme . '.png?v=' . substr($hash, 0, 16);
        [$status, $headers, $body] = $request($url);
        $check($status === 200 && hash('sha256', $body) === $hash && str_starts_with($headers['content-type'], 'image/png'), 'Real public ' . $theme . ' cover route delivers the supplied image unchanged');
        $check($headers['cache-control'] === 'public, max-age=31536000, immutable', 'Correctly versioned ' . $theme . ' cover can stay in the browser disk cache for a year');
        $check(!isset($headers['pragma']) && !isset($headers['set-cookie']), 'Public ' . $theme . ' cover removes session no-cache and cookies');
        $check(abs(strtotime($headers['expires'] ?? '') - time() - 31536000) <= 10, 'Public ' . $theme . ' cover replaces stale session expiry with its one-year lifetime');
        [$status, $headers, $body] = $request($url, ['If-None-Match: W/"' . $hash . '"']);
        $check($status === 304 && $body === '' && $headers['etag'] === '"' . $hash . '"', 'Real ' . $theme . ' asset validator can recheck without image bytes');
        $check(!isset($headers['pragma']) && !isset($headers['set-cookie']) && abs(strtotime($headers['expires'] ?? '') - time() - 31536000) <= 10, 'Public ' . $theme . ' 304 also clears session headers and retains future expiry');
        [$status, $headers] = $request('/plugins/reel-player/assets/cover-' . $theme . '.png?v=old-version');
        $check($status === 200 && $headers['cache-control'] === 'public, max-age=3600', 'Mismatched ' . $theme . ' version never receives immutable caching');
        $check(!isset($headers['pragma']) && !isset($headers['set-cookie']) && abs(strtotime($headers['expires'] ?? '') - time() - 3600) <= 10, 'Mismatched ' . $theme . ' asset also removes session headers and expires in one hour');
    }
    [$status, $headers] = $request('/plugins/reel-player/assets/cover-light.png');
    $check($status === 200 && $headers['cache-control'] === 'public, max-age=3600', 'Unversioned public cover keeps the shorter cache period');
    $check(!isset($headers['pragma']) && !isset($headers['set-cookie']) && abs(strtotime($headers['expires'] ?? '') - time() - 3600) <= 10, 'Unversioned public cover clears session headers and has a matching future expiry');
    $plainHash = hash_file('sha256', dirname(__DIR__) . '/plugins/reel-player/assets/cover-light.png');
    [$status, $headers, $body] = $request('/plugins/reel-player/assets/cover-light.png', ['If-None-Match: "' . $plainHash . '"']);
    $check($status === 304 && $body === '' && !isset($headers['pragma']) && !isset($headers['set-cookie']) && abs(strtotime($headers['expires'] ?? '') - time() - 3600) <= 10, 'Unversioned public 304 clears session headers and keeps its one-hour expiry');
    [$status] = $request('/plugins/reel-player/assets/favicon-tape-room-plugin.png');
    $check($status === 404, 'Prepared favicon remains unpublished and untouched');
    [$status] = $request('/plugins/reel-player/assets/cover.png');
    $check($status === 404, 'Removed cover filename is not exposed by the new route');
    [$status, $headers, $body] = $request('/cover/1', ['X-Test-Owner: 7']);
    $check($status === 200 && $body === $bytes && $headers['content-type'] === 'image/png', 'Owned cover returns exact original bytes');
    $check(str_contains($headers['cache-control'], 'private') && str_contains($headers['cache-control'], 'no-cache') && !str_contains($headers['cache-control'], 'no-store'), 'Private bytes can be retained only with mandatory revalidation');
    $check($headers['etag'] === '"' . hash('sha256', $bytes) . '"' && isset($headers['last-modified']), 'Cover advertises exact content fingerprint and modification date');
    $etag = $headers['etag']; $modified = $headers['last-modified'];
    [$status, $headers, $body] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: ' . $etag]);
    $check($status === 304 && $body === '' && $headers['etag'] === $etag, 'Unchanged cover revalidation transfers no image payload');
    [$status, , $body] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: "other", W/' . $etag]);
    $check($status === 304 && $body === '', 'ETag lists and weak comparison are accepted for GET');
    [$status] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: *']);
    $check($status === 304, 'Existing owned cover accepts wildcard revalidation');
    [$status, , $body] = $request('/cover/1', ['X-Test-Owner: 7', 'If-Modified-Since: ' . $modified]);
    $check($status === 304 && $body === '', 'Modification date is supported when ETag is absent');
    [$status] = $request('/cover/1', ['X-Test-Owner: 7', 'If-Modified-Since: invalid-date']);
    $check($status === 200, 'Malformed modification dates never suppress the image');
    [$status] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: "different"', 'If-Modified-Since: ' . $modified]);
    $check($status === 200, 'ETag takes precedence over modification date');
    [$status, , $body] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: ' . $etag], 'HEAD');
    $check($status === 304 && $body === '', 'HEAD supports image revalidation without a body');
    [$status, $headers] = $request('/cover/1', ['If-None-Match: ' . $etag]);
    $check($status === 401 && !isset($headers['etag']), 'Logged-out requests cannot validate private image contents');
    [$status, $headers] = $request('/cover/1', ['X-Test-Owner: 8', 'If-None-Match: ' . $etag]);
    $check($status === 404 && !isset($headers['etag']), 'Another administrator cannot obtain a 304 or image fingerprint');
    [$status] = $request('/cover/999', ['X-Test-Owner: 7', 'If-None-Match: *']);
    $check($status === 404, 'Missing track is rejected before wildcard revalidation');
    [$status] = $request('/cover/3', ['X-Test-Owner: 7', 'If-None-Match: *']);
    $check($status === 404, 'Track without an uploaded cover stays unavailable');
    [$status, $headers, $body] = $request('/media/1', ['X-Test-Owner: 7', 'Range: bytes=2-5', 'If-None-Match: *']);
    $check($status === 206 && $body === '2345' && $headers['content-range'] === 'bytes 2-5/10', 'Audio Range requests continue delivering exact original bytes');
    $check(str_contains($headers['cache-control'], 'no-store') && !isset($headers['etag']) && !isset($headers['last-modified']), 'Audio does not enter the image cache');
    [$status, $headers, $body] = $request('/media/1', ['X-Test-Owner: 7', 'Range: bytes=10-']);
    $check($status === 416 && $body === '' && $headers['content-range'] === 'bytes */10', 'Unsatisfiable audio ranges are unchanged');
    $timestamp = filemtime($root . '/reel-player/' . $cover);
    file_put_contents($root . '/reel-player/' . $cover, $bytes . 'changed');
    touch($root . '/reel-player/' . $cover, $timestamp);
    [$status, $headers, $body] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: ' . $etag, 'If-Modified-Since: ' . $modified]);
    $check($status === 200 && $body === $bytes . 'changed' && $headers['etag'] !== $etag, 'Changed bytes invalidate a cached image even within the same timestamp');
    unlink($root . '/reel-player/' . $cover);
    [$status, $headers] = $request('/cover/1', ['X-Test-Owner: 7', 'If-None-Match: *']);
    $check($status === 404 && !isset($headers['etag']), 'Removed image cannot be validated from an old cache');
    $log = (string)file_get_contents($root . '/server.log');
    $check(!preg_match('/PHP (?:Warning|Fatal error|Notice|Parse error)/', $log), 'Cover and audio requests leave no PHP errors');
    echo "Tape Room cover cache: {$checks} HTTP checks passed.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $pdo = null;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
