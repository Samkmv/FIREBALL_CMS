<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** OAuth credentials and refresh tokens stay outside the updatable plugin directory. */
final class GoogleDrive
{
    private bool $meterStream = false;
    private int $meterSpeed = 524288;
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
        DriveCache::clearOwner($this->root, $this->owner);
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
            $latest = $this->read();
            if (($latest['refresh_token'] ?? '') !== $account['refresh_token'] || $this->binding($latest) !== $this->binding($account)) throw new \InvalidArgumentException('Подключение Google Drive изменилось. Войдите снова.');
            $this->write([...$account, 'access_token' => $tokens['access_token'], 'expires_at' => time() + (int)($tokens['expires_in'] ?? 3600)]);
            return $tokens['access_token'];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function request(string $url, ?array $post = null, ?string $token = null, bool $retry = true): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => $token ? ['Authorization: Bearer ' . $token] : []]);
        if ($post !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        $body = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        if ($body === false) throw new \InvalidArgumentException('Google Drive недоступен. Проверьте соединение сервера с Google.');
        if ($status === 401 && $token && $retry) {
            $this->expireAccess($token);
            return $this->request($url,$post,$this->token(),false);
        }
        if ($status >= 400 && $post !== null) {
            $reason = json_decode((string)$body,true)['error'] ?? '';
            if ($reason === 'invalid_grant') throw new \InvalidArgumentException('Доступ Google Drive истёк или отозван. Войдите в Google Drive снова.');
            if ($reason === 'invalid_client') throw new \InvalidArgumentException('Google отклонил настройки приложения. Создателю сайта нужно проверить Client ID и Client Secret.');
        }
        if ($status < 200 || $status >= 300) throw new \InvalidArgumentException(match ($status) {
            401 => 'Доступ Google Drive истёк. Подключите аккаунт снова.',
            403 => 'Google Drive не разрешил доступ. Проверьте Drive API, OAuth-разрешения и доступность файла.',
            404 => 'Файл Google Drive не найден или недоступен этому аккаунту.',
            default => 'Google отклонил запрос. Проверьте настройки подключения и повторите попытку.',
        });
        return json_decode((string)$body, true) ?: [];
    }

    private function expireAccess(string $token): void
    {
        $account = $this->read();
        if (($account['access_token'] ?? '') === $token) $this->write([...$account,'expires_at'=>0]);
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

    private function cacheKey(): string
    {
        $account = $this->read();
        if (empty($account['refresh_token']) && empty($account['access_token'])) throw new \InvalidArgumentException('Войдите в Google Drive снова.');
        return hash('sha256', $this->binding($account) . ':' . ($account['refresh_token'] ?? $account['access_token']));
    }

    private function cache(): DriveCache { return new DriveCache($this->root,$this->owner,$this->cacheKey()); }

    public function file(string $id, bool $refresh = false): array
    {
        $id = self::id($id); $cache = $this->cache();
        if (!$refresh) {
            try { if ($file = $cache->metadata($id)) return $file; } catch (\Throwable) { /* Optional cache. */ }
        }
        $file = $this->request('https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '?' . http_build_query([
            'fields' => 'id,name,mimeType,size,headRevisionId,md5Checksum,modifiedTime,capabilities(canDownload)', 'supportsAllDrives' => 'true',
        ]), null, $this->token());
        // Folders are useful during import but do not belong in the audio cache.
        if (($file['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') {
            $audio = self::assertAudio($file);
            try { $cache->saveMetadata($audio); } catch (\Throwable) { /* Optional cache. */ }
        }
        return $file;
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

    private function pipeline(): DriveStream
    {
        $key = $this->cacheKey();
        return new DriveStream(new DriveCache($this->root,$this->owner,$key),function() use ($key): bool {
            try { return hash_equals($key,$this->cacheKey()); } catch (\Throwable) { return false; }
        });
    }

    /** Bounded warm-up; response contains only readiness, never private URLs. */
    public function prepare(string $id): array
    {
        session()->close(); $this->token();
        $file = self::assertAudio($this->file($id));
        try { return $this->pipeline()->prepare($file,$this->download(...)); }
        catch (\RuntimeException $error) {
            if (!empty($file['headRevisionId']) && in_array($error->getCode(),[403,404],true)) {
                // Some shared files permit current content but not revisions.
                // Continue through files.get; do not splice an unpinned version.
                unset($file['headRevisionId']);
                try { $this->cache()->saveMetadata($file); } catch (\Throwable) {}
                return ['ready'=>false,'reason'=>'revision-unavailable'];
            }
            throw $error;
        }
    }

    private function download(array $file, int $start, int $end, bool $warming, callable $head, callable $body, bool $retry = true): void
    {
        $token = $this->token(); $status = 0; $fields = []; $cancelled = false;
        $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode(self::id($file['id']));
        if (!empty($file['headRevisionId'])) $url .= '/revisions/' . rawurlencode($file['headRevisionId']);
        $curl = curl_init($url . '?alt=media&supportsAllDrives=true');
        curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>$warming ? 15 : 0,
            CURLOPT_LOW_SPEED_LIMIT=>1024,CURLOPT_LOW_SPEED_TIME=>$warming ? 8 : 45,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_UNRESTRICTED_AUTH=>false,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,"Range: bytes={$start}-{$end}",'Accept-Encoding: identity'],
            CURLOPT_HEADERFUNCTION=>static function($curl,string $line) use (&$status,&$fields,$head): int {
                if (preg_match('~^HTTP/\S+ (\d+)~',$line,$m)) { $status = (int)$m[1]; $fields = []; }
                elseif (trim($line) === '' && $status >= 200 && !($status >= 300 && $status < 400)) $head($status,$fields);
                elseif (str_contains($line,':')) { [$name,$value] = explode(':',$line,2); $fields[strtolower(trim($name))] = trim($value); }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION=>static function($curl,string $bytes) use (&$status,&$cancelled,$body,$warming): int {
                if ($status >= 300 && $status < 400) return strlen($bytes);
                if (connection_aborted()) { $cancelled = true; return 0; }
                $more = $body($bytes);
                // A whitespace heartbeat keeps the JSON valid and lets PHP
                // notice AbortController cancellation during a warm-up.
                if ($warming && PHP_SAPI !== 'cli') { echo ' '; flush(); }
                if (!$more) { $cancelled = true; return 0; }
                return strlen($bytes);
            },
        ]);
        if ($warming) curl_setopt($curl,CURLOPT_MAX_RECV_SPEED_LARGE,262144);
        elseif ($this->meterStream) curl_setopt($curl,CURLOPT_MAX_RECV_SPEED_LARGE,$this->meterSpeed);
        try {
            $ok = curl_exec($curl); $error = curl_errno($curl);
            if ($ok === false && !($cancelled && $error === CURLE_WRITE_ERROR)) throw new \RuntimeException('Соединение с Google Drive прервалось. Повторите воспроизведение.',502);
        } catch (\RuntimeException $error) {
            if ($error->getCode() !== 401 || !$retry) throw $error;
            $this->expireAccess($token);
            $this->download($file,$start,$end,$warming,$head,$body,false);
        } finally { curl_close($curl); }
    }

    public function stream(string $id, bool $meterStream = false, float $duration = 0): never
    {
        $this->meterStream = $meterStream;
        session()->close(); $this->token();
        $file = self::assertAudio($this->file($id)); $started = false;
        if ($meterStream && $duration > 0) $this->meterSpeed = (int)min(2097152,max(524288,ceil((int)$file['size']/$duration*1.5)));
        while (ob_get_level() > 0) ob_end_clean();
        header('Cache-Control: private, no-store, max-age=0'); header('X-Content-Type-Options: nosniff'); header('X-Accel-Buffering: no');
        $headers = static function(int $status,array $fields) use (&$started): void {
            http_response_code($status); foreach ($fields as $name=>$value) header($name.': '.$value); $started = true;
        };
        $output = static function(string $bytes): bool { if (connection_aborted()) return false; echo $bytes; flush(); return !connection_aborted(); };
        try {
            for ($attempt=0; $attempt<2; $attempt++) {
                try { $this->pipeline()->serve($file,(string)($_SERVER['HTTP_RANGE'] ?? ''),$this->download(...),$headers,$output); break; }
                catch (\RuntimeException $error) {
                    if ($started || $attempt > 0 || !in_array($error->getCode(),[403,404,416],true)) throw $error;
                    $wasRevision = !empty($file['headRevisionId']);
                    $file = self::assertAudio($this->file($id,true));
                    if ($wasRevision && in_array($error->getCode(),[403,404],true)) {
                        unset($file['headRevisionId']);
                        try { $this->cache()->saveMetadata($file); } catch (\Throwable) {}
                    }
                }
            }
        } catch (\Throwable $error) {
            if (!connection_aborted() && in_array($error->getCode(),[401,403,404,416],true)) {
                try { $this->cache()->invalidate($id); } catch (\Throwable) {}
            }
            // Never append an error message to audio bytes already sent.
            if (!$started) {
                http_response_code(in_array($error->getCode(),[401,403,404,416,429],true) ? $error->getCode() : 502);
                if ($error->getCode() === 416) { header('Content-Range: bytes */'.(int)$file['size']); header('Content-Length: 0'); }
                else { header('Content-Type: text/plain; charset=utf-8'); echo $error->getMessage(); }
            } elseif (!connection_aborted()) log_error_details('Tape Room Drive stream interrupted',[], $error);
        }
        exit;
    }
}
