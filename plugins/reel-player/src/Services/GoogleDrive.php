<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** OAuth credentials and refresh tokens stay outside the updatable plugin directory. */
final class GoogleDrive
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';
    private string $directory;
    private string $root;

    public function __construct(private int $owner, ?string $root = null, private bool $canManage = false)
    {
        $this->root = $root ?? STORAGE . '/reel-player';
        $this->directory = $this->root . '/' . $owner;
    }

    public function status(): array
    {
        $account = $this->read();
        return ['configured' => !empty($account['client_id']) && !empty($account['client_secret']),
            'connected' => !empty($account['refresh_token']) || !empty($account['access_token']),
            'canManage' => $this->canManage,
            'clientId' => $this->canManage ? ($account['client_id'] ?? '') : '', 'callback' => $this->callbackUrl()];
    }

    public function settings(string $id, string $secret): void
    {
        if (!$this->canManage) throw new \InvalidArgumentException('Настройки подключения доступны только создателю сайта.');
        $id = trim($id); $secret = trim($secret);
        if (!preg_match('/^[a-zA-Z0-9._-]{10,450}\.apps\.googleusercontent\.com$/D', $id)) throw new \InvalidArgumentException('Проверьте OAuth Client ID Google.');
        if (strlen($secret) > 500 || preg_match('/[\x00-\x1f]/', $secret)) throw new \InvalidArgumentException('Проверьте Client Secret.');
        $app = $this->sharedApp();
        $nextSecret = $secret ?: ($app['client_secret'] ?? '');
        if (!$nextSecret) throw new \InvalidArgumentException('Введите OAuth Client Secret.');
        $this->writeFile($this->root . '/drive-app.json', ['client_id' => $id, 'client_secret' => $nextSecret]);
    }

    public function callbackUrl(): string { return base_url('/admin/reel-player/drive/callback'); }

    public function authorizationUrl(): string
    {
        $account = $this->read();
        if (empty($account['client_id']) || empty($account['client_secret'])) throw new \InvalidArgumentException('Создатель сайта ещё не настроил подключение Google Drive.');
        $state = bin2hex(random_bytes(32));
        session()->set('reel_drive_oauth', ['state' => $state, 'owner' => $this->owner, 'created' => time(), 'app_binding' => $this->binding($account)]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $account['client_id'], 'redirect_uri' => $this->callbackUrl(), 'response_type' => 'code',
            'scope' => self::SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function finish(string $state, string $code): void
    {
        $pending = session()->get('reel_drive_oauth', []);
        session()->remove('reel_drive_oauth');
        if (!is_array($pending) || empty($pending['state']) || !hash_equals($pending['state'], $state)
            || (int)($pending['owner'] ?? 0) !== $this->owner || time() - (int)($pending['created'] ?? 0) > 600 || $code === '') {
            throw new \InvalidArgumentException('Подключение устарело. Попробуйте снова.');
        }
        $account = $this->read();
        if (!hash_equals((string)($pending['app_binding'] ?? ''), $this->binding($account))) throw new \InvalidArgumentException('Настройки Google изменились. Начните подключение заново.');
        $tokens = $this->request('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $account['client_id'], 'client_secret' => $account['client_secret'],
            'redirect_uri' => $this->callbackUrl(), 'grant_type' => 'authorization_code',
        ]);
        if (isset($tokens['scope']) && !in_array(self::SCOPE, explode(' ', $tokens['scope']), true)) throw new \InvalidArgumentException('Разрешите чтение Google Drive при подключении.');
        if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) throw new \InvalidArgumentException('Google не предоставил доступ. Повторите подключение.');
        $this->write([...$account, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'], 'expires_at' => time() + (int)($tokens['expires_in'] ?? 3600)]);
    }

    public function disconnect(): void
    {
        $account = $this->read();
        $token = $account['refresh_token'] ?? $account['access_token'] ?? '';
        // Always remove local access, even if Google's revocation service is unavailable.
        $this->write([]);
        if ($token !== '') {
            try { $this->request('https://oauth2.googleapis.com/revoke', ['token' => $token]); } catch (\Throwable) {}
        }
    }

    private function read(): array
    {
        $account = $this->readFile($this->directory . '/drive.json');
        $app = $this->sharedApp();
        // Promote only the creator's legacy app credentials. Tokens belong to
        // the same owner and remain valid when the OAuth client is unchanged.
        if (!$app && $this->canManage && !empty($account['client_id']) && !empty($account['client_secret'])) {
            $app = array_intersect_key($account, array_flip(['client_id','client_secret']));
            $this->writeFile($this->root . '/drive-app.json', $app);
            $this->write($account);
        }
        $binding = $account['app_binding'] ?? (!empty($account['client_id']) && !empty($account['client_secret']) ? $this->binding($account) : '');
        $tokens = $app && hash_equals($this->binding($app), (string)$binding)
            ? array_intersect_key($account, array_flip(['access_token','refresh_token','expires_at'])) : [];
        return [...$app, ...$tokens];
    }

    private function write(array $account): void
    {
        $tokens = array_intersect_key($account, array_flip(['access_token','refresh_token','expires_at']));
        if ($tokens) $tokens['app_binding'] = $this->binding($account);
        $this->writeFile($this->directory . '/drive.json', $tokens);
    }

    private function sharedApp(): array
    {
        return array_intersect_key($this->readFile($this->root . '/drive-app.json'), array_flip(['client_id','client_secret']));
    }

    private function binding(array $app): string
    {
        return hash('sha256', ($app['client_id'] ?? '') . "\0" . ($app['client_secret'] ?? ''));
    }

    private function readFile(string $file): array
    {
        $value = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
        return is_array($value) ? $value : [];
    }

    private function writeFile(string $file, array $account): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Не удалось сохранить настройки Google Drive.');
        $tmp = $directory . '/drive-' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($tmp, json_encode($account, JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new \RuntimeException('Не удалось сохранить подключение.');
        chmod($tmp, 0600);
        if (!rename($tmp, $file)) { @unlink($tmp); throw new \RuntimeException('Не удалось сохранить подключение.'); }
    }

    private function token(): string
    {
        $account = $this->read();
        if (!empty($account['access_token']) && (int)($account['expires_at'] ?? 0) > time() + 90) return $account['access_token'];
        if (empty($account['refresh_token'])) throw new \InvalidArgumentException('Подключите аккаунт Google Drive.');
        $lock = fopen($this->directory . '/drive.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('Подключение Google Drive занято.');
        try {
            $account = $this->read();
            if (!empty($account['access_token']) && (int)($account['expires_at'] ?? 0) > time() + 90) return $account['access_token'];
            if (empty($account['refresh_token'])) throw new \InvalidArgumentException('Настройки Google изменились. Подключите свой аккаунт снова.');
            $tokens = $this->request('https://oauth2.googleapis.com/token', ['client_id' => $account['client_id'], 'client_secret' => $account['client_secret'], 'refresh_token' => $account['refresh_token'], 'grant_type' => 'refresh_token']);
            if (empty($tokens['access_token'])) throw new \InvalidArgumentException('Переподключите Google Drive.');
            $this->write([...$account, 'access_token' => $tokens['access_token'], 'expires_at' => time() + (int)($tokens['expires_in'] ?? 3600)]);
            return $tokens['access_token'];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function request(string $url, ?array $post = null, ?string $token = null): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => $token ? ['Authorization: Bearer ' . $token] : []]);
        if ($post !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        $body = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        if ($body === false) throw new \InvalidArgumentException('Google Drive недоступен. Проверьте соединение сервера с Google.');
        if ($status < 200 || $status >= 300) throw new \InvalidArgumentException(match ($status) {
            401 => 'Доступ Google Drive истёк. Подключите аккаунт снова.',
            403 => 'Google Drive не разрешил доступ. Проверьте Drive API, OAuth-разрешения и доступность файла.',
            404 => 'Файл Google Drive не найден или недоступен этому аккаунту.',
            default => 'Google отклонил запрос. Проверьте настройки подключения и повторите попытку.',
        });
        return json_decode((string)$body, true) ?: [];
    }

    private static function id(string $id): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{10,200}$/D', $id)) throw new \InvalidArgumentException('Некорректный идентификатор Google Drive.');
        return $id;
    }

    private static function audioQuery(): string
    {
        // Drive's name contains operator is not an extension/suffix search.
        // Validate every returned file; generic files can be imported by folder/link.
        return "trashed = false and (mimeType contains 'audio/' or mimeType = 'application/ogg' or mimeType = 'video/mp4' or mimeType = 'video/webm')";
    }

    public function files(string $page = '', ?string $folder = null): array
    {
        if (strlen($page) > 2000) throw new \InvalidArgumentException('Некорректная страница Google Drive.');
        $q = $folder ? "trashed = false and '" . self::id($folder) . "' in parents" : self::audioQuery();
        $result = $this->request('https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => $q, 'fields' => 'nextPageToken,files(id,name,mimeType,size,capabilities(canDownload))', 'pageSize' => 100,
            'orderBy' => 'name', 'pageToken' => $page, 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
        ], '', '&', PHP_QUERY_RFC3986), null, $this->token());
        return self::audioPage($result);
    }

    public static function audioPage(array $result): array
    {
        $files = [];
        foreach ($result['files'] ?? [] as $file) {
            if (!is_array($file)) continue;
            try { $files[] = self::assertAudio($file); } catch (\InvalidArgumentException) {}
        }
        return ['files' => $files, 'nextPageToken' => (string)($result['nextPageToken'] ?? '')];
    }

    public function browse(string $page = '', string $folder = 'root'): array
    {
        if (strlen($page) > 2000) throw new \InvalidArgumentException('Некорректная страница Google Drive.');
        $folder = $folder === 'root' ? 'root' : self::id($folder);
        $result = $this->request('https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => "trashed = false and '" . $folder . "' in parents",
            'fields' => 'nextPageToken,files(id,name,mimeType,size,capabilities(canDownload))', 'pageSize' => 100,
            'orderBy' => 'name', 'pageToken' => $page, 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
        ], '', '&', PHP_QUERY_RFC3986), null, $this->token());
        return self::browsePage($result);
    }

    public static function browsePage(array $result): array
    {
        $folders = [];
        foreach ($result['files'] ?? [] as $file) {
            if (is_array($file) && ($file['mimeType'] ?? '') === 'application/vnd.google-apps.folder' && !empty($file['id']) && !empty($file['name'])) $folders[] = $file;
        }
        $audio = self::audioPage($result);
        return ['files' => [...$folders, ...$audio['files']], 'nextPageToken' => $audio['nextPageToken']];
    }

    public function file(string $id): array
    {
        return $this->request('https://www.googleapis.com/drive/v3/files/' . rawurlencode(self::id($id)) . '?' . http_build_query([
            'fields' => 'id,name,mimeType,size,capabilities(canDownload)', 'supportsAllDrives' => 'true',
        ]), null, $this->token());
    }

    public static function linkId(string $url): array
    {
        $parsed = parse_url(trim($url));
        if (!is_array($parsed) || !in_array(strtolower($parsed['host'] ?? ''), ['drive.google.com', 'www.drive.google.com'], true) || ($parsed['scheme'] ?? '') !== 'https') throw new \InvalidArgumentException('Вставьте ссылку https://drive.google.com/ на файл или папку.');
        $path = $parsed['path'] ?? '';
        if (preg_match('~/(folders|d)/([a-zA-Z0-9_-]+)~', $path, $match)) return [self::id($match[2]), $match[1] === 'folders'];
        parse_str($parsed['query'] ?? '', $query);
        return [self::id(is_string($query['id'] ?? null) ? $query['id'] : ''), false];
    }

    public static function assertAudio(array $file): array
    {
        if (empty($file['id']) || empty($file['name']) || !preg_match('/\.(mp3|flac|wav|m4a|ogg|opus|aac|webm)$/i', $file['name'])) throw new \InvalidArgumentException('Выбранный файл не является поддерживаемым аудиофайлом.');
        if (isset($file['capabilities']['canDownload']) && !$file['capabilities']['canDownload']) throw new \InvalidArgumentException('Владелец запретил скачивание этого файла.');
        if ((int)($file['size'] ?? 0) <= 0) throw new \InvalidArgumentException('Аудиофайл пустой или недоступен для воспроизведения.');
        $file['mimeType'] = match (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg', 'flac' => 'audio/flac', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4', 'ogg', 'opus' => 'audio/ogg', 'aac' => 'audio/aac', 'webm' => 'audio/webm',
        };
        return $file;
    }

    public function importFiles(array $ids, string $link): array
    {
        if (count($ids) > 100) throw new \InvalidArgumentException('Выберите до 100 треков за один раз.');
        $files = [];
        foreach (array_unique($ids) as $id) {
            if (!is_string($id)) throw new \InvalidArgumentException('Некорректный файл Google Drive.');
            $file = self::assertAudio($this->file($id)); $files[$file['id']] = $file;
        }
        if (trim($link) !== '') {
            [$id, $folder] = self::linkId($link);
            if ($folder) {
                $page = '';
                do {
                    $result = $this->files($page, $id);
                    foreach ($result['files'] ?? [] as $file) { $file = self::assertAudio($file); $files[$file['id']] = $file; }
                    if (count($files) > 1000) throw new \InvalidArgumentException('В папке больше 1000 треков. Добавьте файлы частями.');
                    $page = $result['nextPageToken'] ?? '';
                } while ($page !== '');
            } else { $file = self::assertAudio($this->file($id)); $files[$id] = $file; }
        }
        if (!$files) throw new \InvalidArgumentException('В папке нет поддерживаемых аудиофайлов.');
        return array_values($files);
    }

    public function stream(string $id): never
    {
        $file = self::assertAudio($this->file($id));
        $size = (int)$file['size']; $range = MediaStorage::range($size, (string)($_SERVER['HTTP_RANGE'] ?? ''));
        if ($range === null) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
        [$start, $end, $partial] = $range;
        $token = $this->token(); session()->close();
        while (ob_get_level() > 0) ob_end_clean();
        $started = false; $upstreamCode = 0;
        $curl = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode(self::id($id)) . '?alt=media&supportsAllDrives=true');
        curl_setopt_array($curl, [CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 0, CURLOPT_LOW_SPEED_LIMIT => 1, CURLOPT_LOW_SPEED_TIME => 45,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_UNRESTRICTED_AUTH => false,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, "Range: bytes={$start}-{$end}"],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$upstreamCode): int {
                if (preg_match('~^HTTP/\S+ (\d+)~', $header, $m)) $upstreamCode = (int)$m[1];
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $bytes) use (&$started, &$upstreamCode, $file, $start, $end, $size, $partial): int {
                if ($upstreamCode >= 300 && $upstreamCode < 400) return strlen($bytes);
                if (!in_array($upstreamCode, [200, 206], true)) return 0;
                // Never label a full upstream response as a partial response.
                if ($upstreamCode === 200 && ($start !== 0 || $end !== $size - 1)) return 0;
                if (!$started) {
                    http_response_code($partial ? 206 : 200); header('Content-Type: ' . $file['mimeType']); header('Accept-Ranges: bytes');
                    header('Content-Length: ' . ($end - $start + 1)); header('Cache-Control: private, no-store, max-age=0'); header('X-Content-Type-Options: nosniff');
                    if ($partial) header("Content-Range: bytes {$start}-{$end}/{$size}");
                    $started = true;
                }
                if (connection_aborted()) return 0;
                echo $bytes; flush(); return strlen($bytes);
            },
        ]);
        curl_exec($curl); curl_close($curl);
        if (!$started) { http_response_code(502); header('Content-Type: text/plain; charset=utf-8'); echo 'Не удалось воспроизвести файл Google Drive. Проверьте доступ к файлу и переподключите аккаунт.'; }
        exit;
    }
}
