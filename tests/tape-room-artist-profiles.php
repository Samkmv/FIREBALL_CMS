<?php
declare(strict_types=1);
// Exercises actual multipart uploads, repository permissions and guarded image
// delivery on the disposable HTTP fixture. No production data is accessed.
if (PHP_SAPI !== 'cli') exit;
$project = dirname(__DIR__);
$temporary = __DIR__.'/.tmp';
if (!is_dir($temporary)) mkdir($temporary,0700,true);
$testRoot = $temporary.'/tape-room-artists-'.bin2hex(random_bytes(8));
foreach (['','/sessions','/uploads','/php-tmp'] as $directory) mkdir($testRoot.$directory,0700,true);
$process = null;
register_shutdown_function(static function() use (&$process,$testRoot): void {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (!is_dir($testRoot)) return;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($testRoot);
});
$socket = stream_socket_server('tcp://127.0.0.1:0',$number,$message);
if (!$socket) throw new RuntimeException('Cannot reserve an isolated artist test port.');
$address = stream_socket_get_name($socket,false); fclose($socket);
$port = (int)substr($address,strrpos($address,':')+1);
$environment = [...getenv(),'REEL_PLAYER_TEST_ROOT'=>$testRoot,'REEL_PLAYER_SEQUENCE_PREVIEW'=>'short','REEL_PLAYER_CATALOG_PREVIEW'=>'1'];
unset($environment['PHP_CLI_SERVER_WORKERS'],$environment['REEL_PLAYER_ROLE_PREVIEW'],$environment['REEL_PLAYER_STREAM_PREVIEW']);
$process = proc_open([PHP_BINARY,'-d','sys_temp_dir='.$testRoot.'/php-tmp','-d','session.save_path='.$testRoot.'/sessions','-d','upload_tmp_dir='.$testRoot.'/uploads','-S','127.0.0.1:'.$port,$project.'/plugins/reel-player/tests/browser-fixture.php'],[0=>['pipe','r'],1=>['file',$testRoot.'/server.log','a'],2=>['file',$testRoot.'/server.log','a']],$pipes,$project,$environment);
if (!is_resource($process)) throw new RuntimeException('Cannot start the isolated artist fixture.');
fclose($pipes[0]);
$base = 'http://127.0.0.1:'.$port;
$checks = 0;
$assert = static function(bool $ok,string $reason) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($reason); };
$request = static function(string $viewer,string $path,?array $fields=null,array $headers=[],?string $image=null) use ($base): array {
    $curl = curl_init($base.$path); $responseHeaders = [];
    $allHeaders = ['Cookie: reel_catalog_fixture_viewer='.$viewer,...$headers];
    if ($fields!==null) $allHeaders[] = 'X-CSRF-Token: reel-browser-fixture';
    $options = [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>$allHeaders,CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$responseHeaders): int {
        if (str_contains($line,':')) { [$name,$value] = explode(':',$line,2); $responseHeaders[strtolower(trim($name))] = trim($value); }
        return strlen($line);
    }];
    if ($fields!==null) {
        $options[CURLOPT_POST] = true;
        if ($image!==null) $fields['cover'] = new CURLFile($image,'image/png',basename($image));
        $options[CURLOPT_POSTFIELDS] = $image!==null ? $fields : http_build_query($fields);
    }
    curl_setopt_array($curl,$options); $body = curl_exec($curl); $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
    return ['status'=>$status,'headers'=>$responseHeaders,'body'=>is_string($body)?$body:'','error'=>$error];
};
$json = static function(array $reply): array {
    $value = json_decode($reply['body'],true);
    if (!is_array($value)) throw new RuntimeException('Expected JSON, HTTP '.$reply['status'].': '.mb_substr($reply['body'],0,120));
    return $value;
};
$state = static fn(string $viewer): array => $json($request($viewer,'/admin/reel-player/api/state'));
$action = static fn(string $viewer,string $name,array $fields=[],?string $image=null): array => $request($viewer,'/admin/reel-player/api/action',['action'=>'catalog.'.$name,...$fields],[],$image);
$profile = static function(array $state,string $name): ?array {
    foreach ($state['catalog']['artistProfiles'] ?? [] as $profile) if ($profile['key']===mb_strtolower(trim($name),'UTF-8')) return $profile;
    return null;
};
try {
    $deadline = microtime(true)+8;
    do { $page = $request('creator','/admin/reel-player'); if ($page['status']) break; usleep(50000); } while (microtime(true)<$deadline);
    $assert($page['status']===200,'The migrated player renders before artist changes');
    $db = new PDO('sqlite:'.$testRoot.'/data/fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $assert((bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reel_artist_profiles'")->fetchColumn(),'The fixture includes the migration 007 table');
    $image = $testRoot.'/portrait.png';
    file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/a7v0AAAAASUVORK5CYII=',true));
    $replacement = $testRoot.'/replacement.png'; file_put_contents($replacement,file_get_contents($image)."\n");
    $bad = $testRoot.'/malformed.png'; file_put_contents($bad,'This is not an image.');
    $initial = $state('creator');
    $mono = (int)array_column($initial['tracks'],'id','title')['Mono'];
    $albums = array_column($initial['catalog']['albums'],'id','title');
    $night = (int)$albums['Ночная сторона · демо']; $draft = (int)$albums['Черновик · демо'];
    $assert($request('creator','/admin/reel-player/api/action',['action'=>'track.update','id'=>$mono],[],$image)['status']===200,'Original track artwork is uploaded independently');
    $assert($action('creator','album.update',['id'=>$night],$image)['status']===200,'Original album artwork is uploaded independently');
    $assert(($state('creator')['catalog']['artistProfiles'] ?? null)===[],'Album and song covers never become artist photographs automatically');
    $snapshot = static function() use ($db): array {
        $result = [];
        foreach (['reel_tracks','reel_albums','reel_album_tracks','reel_album_saves','reel_playlist_tracks'] as $table) $result[$table] = $db->query('SELECT * FROM '.$table.' ORDER BY 1,2')->fetchAll();
        return $result;
    };
    $before = $snapshot();
    $originalFiles = [];
    foreach (glob($testRoot.'/data/reel-player/*/*') ?: [] as $file) if (is_file($file)) $originalFiles[$file] = hash_file('sha256',$file);
    $assert($action('admin','artist.update',['name'=>'Stereo Lab'],$image)['status']===422,'Administrator cannot replace site artist photography');
    $assert($action('admin','artist.delete',['name'=>'Stereo Lab'])['status']===422,'Administrator cannot delete site artist photography');
    foreach (['','Absent artist',str_repeat('a',241),"Stereo\0Lab"] as $name) $assert($action('creator','artist.update',['name'=>$name],$image)['status']===422,'Invalid or nonexistent artist name is rejected');
    $assert($action('creator','artist.update',['name'=>['Stereo Lab']])['status']===422,'Array-valued artist names are rejected');
    $assert($action('creator','artist.update',['name'=>'Stereo Lab'])['status']===422,'Creating a photo profile requires an uploaded photograph');
    $assert($action('creator','artist.update',['name'=>'Stereo Lab'],$bad)['status']===422,'Malformed uploaded images are rejected without persistent changes');
    $assert($db->query('SELECT COUNT(*) FROM reel_artist_profiles')->fetchColumn()==0,'Rejected changes do not create profiles');
    $created = $action('creator','artist.update',['name'=>'  Stereo Lab  '],$image);
    $assert($created['status']===200,'Creator can upload a real independent artist image');
    $ownerProfile = $profile($json($created),'Stereo Lab');
    $assert($ownerProfile!==null && $ownerProfile['editable']===true && $ownerProfile['name']==='Stereo Lab','Created profile has a stable trimmed name and creator capability');
    $id = (int)$ownerProfile['id'];
    $assert($id>0 && str_contains($ownerProfile['image'],'/catalog/artist-image/'.$id.'?v='),'Image URL contains a profile ID and replaceable revision');
    $viewerProfile = $profile($state('admin'),'Stereo Lab');
    $assert($viewerProfile!==null && $viewerProfile['editable']===false && $viewerProfile['image']===$ownerProfile['image'],'Other viewers see the same public photo with no mutation capability');
    foreach (['owner_id','image_path','image_mime','name_key'] as $private) $assert(!array_key_exists($private,$viewerProfile),'Artist state hides server-only '.$private);
    $row = $db->query('SELECT * FROM reel_artist_profiles WHERE id='.$id)->fetch();
    $path = $testRoot.'/data/reel-player/'.$row['image_path'];
    $assert($row['name_key']===hash('sha256','stereo lab'),'Persistent identity is independent from case and surrounding whitespace');
    $assert(preg_match('~^1/[a-f0-9]{40}\.png$~D',$row['image_path'])===1 && file_get_contents($path)===file_get_contents($image),'Portrait uses its own private storage file with original bytes');
    $photo = $request('admin','/admin/reel-player/catalog/artist-image/'.$id);
    $assert($photo['status']===200 && $photo['body']===file_get_contents($image),'Authorized artist route streams the independent image');
    $assert(str_contains($photo['headers']['cache-control'] ?? '','private') && ($photo['headers']['etag'] ?? '')!=='','Artist image uses a private HTTP cache validator');
    $etag = $photo['headers']['etag'];
    $assert($request('admin','/admin/reel-player/catalog/artist-image/'.$id,null,['If-None-Match: '.$etag])['status']===304,'Authorized unchanged photograph returns 304');
    $replaced = $json($action('creator','artist.update',['name'=>'sTeReO lAb'],$replacement));
    $changed = $profile($replaced,'Stereo Lab');
    $assert($changed['id']===$id && $changed['name']==='Stereo Lab' && $changed['image']!==$ownerProfile['image'],'Replacing via another case keeps profile identity and changes its image revision');
    $assert((int)$db->query('SELECT COUNT(*) FROM reel_artist_profiles')->fetchColumn()===1,'Replacing never duplicates the artist');
    $assert(!is_file($path),'Replacing removes only the superseded artist photo');
    $newPhoto = $request('admin','/admin/reel-player/catalog/artist-image/'.$id,null,['If-None-Match: '.$etag]);
    $assert($newPhoto['status']===200 && $newPhoto['body']===file_get_contents($replacement),'Old validators cannot retain a replaced portrait');
    $assert($snapshot()===$before,'Creating and replacing a photo never rename artists or change album/track relationships');
    foreach ($originalFiles as $file=>$hash) $assert(is_file($file) && hash_file('sha256',$file)===$hash,'Artist mutations preserve original audio and album/song artwork bytes');
    $assert($action('creator','artist.update',['name'=>'Private Sessions'],$image)['status']===200,'Creator can prepare a profile for their own private album');
    $privateProfile = $profile($state('creator'),'Private Sessions');
    $assert($privateProfile!==null && $profile($state('admin'),'Private Sessions')===null,'A private album artist does not leak into another viewer state');
    $assert($request('admin','/admin/reel-player/catalog/artist-image/'.$privateProfile['id'])['status']===404,'Guessing a private artist profile ID cannot expose its photo');
    $assert($request('admin','/admin/reel-player/catalog/artist-image/'.$privateProfile['id'],null,['If-None-Match: '.$etag])['status']===404,'Private artist authorization is enforced before cache revalidation');
    $collision = $json($action('admin','album.create',['title'=>'Guessed private artist','artist'=>'Private Sessions','tracks'=>'[]']));
    $assert($profile($state('admin'),'Private Sessions')===null,'A viewer-created private album with the same artist name cannot reveal another owner photo');
    $assert($request('admin','/admin/reel-player/catalog/artist-image/'.$privateProfile['id'])['status']===404,'Name collisions cannot bypass artist image publication rights');
    $assert($action('admin','album.delete',['id'=>$collision['id']])['status']===200,'The collision fixture cleans up only its own private album');
    $assert($action('creator','album.publish',['id'=>$night,'published'=>0])['status']===200,'Creator can withdraw the album independently');
    $assert($profile($state('admin'),'Stereo Lab')===null,'Withdrawing the last matching published album removes its profile from viewer state');
    $assert($request('admin','/admin/reel-player/catalog/artist-image/'.$id,null,['If-None-Match: '.$newPhoto['headers']['etag']])['status']===404,'Withdrawn artist image access is revoked before 304');
    $assert($action('creator','album.publish',['id'=>$night,'published'=>1])['status']===200,'Publishing again restores the original music relationship');
    $assert($profile($state('admin'),'Stereo Lab')['id']===$id,'Reopening an artist after state reload preserves its photo and identity');
    $currentPath = $testRoot.'/data/reel-player/'.$db->query('SELECT image_path FROM reel_artist_profiles WHERE id='.$id)->fetchColumn();
    $deleted = $action('creator','artist.delete',['name'=>'Stereo Lab']);
    $assert($deleted['status']===200 && $profile($json($deleted),'Stereo Lab')['image']==='','Deleting a portrait switches the artist back to its own placeholder');
    $assert(!is_file($currentPath) && $request('admin','/admin/reel-player/catalog/artist-image/'.$id)['status']===404,'Deleting clears stored bytes and invalidates its image route');
    $assert($profile($state('admin'),'Stereo Lab')['id']===$id,'Deleting the photo preserves artist identity and album associations');
    $assert($action('creator','artist.delete',['name'=>'stereo lab'])['status']===200,'Photo deletion is idempotent across name casing');
    $assert($action('creator','album.delete',['id'=>$draft])['status']===200,'Removing an album is independent of an artist profile');
    $assert($request('creator','/admin/reel-player/catalog/artist-image/'.$privateProfile['id'])['status']===404,'A detached profile cannot expose a photo without matching accessible music');
    $assert($action('creator','artist.delete',['name'=>'Private Sessions'])['status']===200,'Creator can clean up a detached photo after removing its last album');
    // The HTTP fixture creates its tables on each request. Check a pending
    // migration directly against the same disposable DB without that bootstrap.
    $db->exec('DROP TABLE reel_artist_profiles');
    define('STORAGE',$testRoot.'/data');
    require $project.'/vendor/autoload.php'; require $project.'/plugins/reel-player/Plugin.php';
    $GLOBALS['artistTestDb'] = new class($db) {
        private PDOStatement $statement;
        public function __construct(private PDO $pdo) {}
        public function query(string $sql,array $params=[]): static { $this->statement = $this->pdo->prepare(str_replace(' FOR UPDATE','',$sql)); $this->statement->execute($params); return $this; }
        public function get(): array { return $this->statement->fetchAll(); }
        public function getOne(): mixed { return $this->statement->fetch(); }
        public function beginTransaction(): void { $this->pdo->beginTransaction(); }
        public function commit(): void { $this->pdo->commit(); }
        public function rollBack(): void { $this->pdo->rollBack(); }
    };
    $pending = new \Fireball\ReelPlayer\Repositories\Catalog(1,true);
    $assert($pending->state()['artistProfiles']===[],'Pending migration cannot break existing library playback/state with a 500');
    $rejected = false;
    try { $pending->action('artist.update',['name'=>'Stereo Lab'],['error'=>UPLOAD_ERR_OK,'tmp_name'=>$image]); }
    catch (InvalidArgumentException $error) { $rejected = str_contains($error->getMessage(),'обновление'); }
    $assert($rejected,'Pending migration reports an actionable update error before storing images');
    $log = (string)file_get_contents($testRoot.'/server.log');
    $assert(!preg_match('/PHP (?:Fatal error|Warning|Notice)|Uncaught/',$log),'Artist HTTP fixture server log has no uncaught errors');
    echo "Passed {$checks} Tape Room artist profile checks: independent original images, creator permissions, multipart validation, replace/delete cleanup, revisions, private publication boundaries, reload and migration fallback.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    if (is_file($testRoot.'/server.log')) fwrite(STDERR,mb_substr((string)file_get_contents($testRoot.'/server.log'),-4500));
    exit(1);
}

function base_href(string $path): string { return $path; }
function db(): object { return $GLOBALS['artistTestDb']; }
