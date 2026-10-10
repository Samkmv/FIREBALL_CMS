<?php
declare(strict_types=1);
// Isolated browser test server. No production accounts, database or music are used.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$project = dirname(__DIR__, 3);
$auditMode = getenv('REEL_PLAYER_SEQUENCE_PREVIEW');
$audit = in_array($auditMode, ['short','long'], true);
define('STORAGE', $project . '/tmp/reel-player-browser' . ($audit ? '-audit-'.$auditMode : ''));
if (!is_dir(STORAGE)) mkdir(STORAGE, 0700, true);
require $project . '/vendor/autoload.php';
require dirname(__DIR__) . '/Plugin.php';
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function base_href(string $path): string { return $path; }
function base_url(string $path): string { return 'http://127.0.0.1:8897' . $path; }
function get_user(): array { $admin = getenv('REEL_PLAYER_ROLE_PREVIEW') === 'admin'; return ['id'=>$admin ? 2 : 1,'role'=>$admin ? 'admin' : 'creator']; }
function check_creator(): bool { return get_user()['role'] === 'creator'; }
function session(): object { static $session; return $session ??= new class {
    private array $values = [];
    public function get(string $key,mixed $default=null): mixed { return $key==='needCSRFToken' ? 'reel-browser-fixture' : ($this->values[$key] ?? $default); }
    public function set(string $key,mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
    public function close(): void {}
}; }
function abort(string $message='',int $status=404): never { http_response_code($status); exit($message); }
function log_error_details(string $message,array $context=[],?Throwable $error=null): void { error_log($message . ': ' . $error?->getMessage()); }
function request(): \FBL\Request { static $request; return $request ??= new \FBL\Request($_SERVER['REQUEST_URI']); }
function response(): \FBL\Response { static $response; return $response ??= new \FBL\Response(); }
function get_route_param(string $key): string { return (string)($GLOBALS['route'][$key] ?? ''); }
function return_translation(string $key): string { return $key; }
function plugin_view(string $slug,string $view,array $data,bool $layout=true): string {
    if (getenv('REEL_PLAYER_STREAM_PREVIEW') === '1') foreach ($data['config']['state']['tracks'] as &$track) {
        if(($_GET['local']??'')==='1'){$track['source']='local';$track['url'].='?local=1';}else $track['source'] = 'drive';
    }
    if (getenv('REEL_PLAYER_METERS_PREVIEW') === '1' && ($_GET['mirror'] ?? '') === '1') $data['meters_url'] .= '&mirror=1';
    if (getenv('REEL_PLAYER_METERS_PREVIEW') === '1' && in_array($_GET['meterFault'] ?? '', ['clock','resume','blocked'], true)) $data['meters_url'] .= '&meterFault=' . $_GET['meterFault'];
    unset($track); extract($data); ob_start(); require dirname(__DIR__).'/views/'.$view.'.php'; return (string)ob_get_clean();
}
function setting(string $key,mixed $default=null): mixed { return $default; }
function config_value(string $key,mixed $default=null): mixed { return $default; }
function db(): object { static $db; return $db ??= new class {
    private PDO $pdo; private PDOStatement $stmt;
    public function __construct() {
        $this->pdo=new PDO('sqlite:'.STORAGE.'/fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS reel_tracks(id INTEGER PRIMARY KEY AUTOINCREMENT,owner_id INTEGER,title TEXT,artist TEXT DEFAULT '',filename TEXT,file_path TEXT,mime TEXT,file_size INTEGER,duration REAL DEFAULT 0,cover_path TEXT,cover_mime TEXT,source TEXT DEFAULT 'local',source_id TEXT,created_at TEXT,UNIQUE(owner_id,source,source_id)); CREATE TABLE IF NOT EXISTS reel_playlists(id INTEGER PRIMARY KEY AUTOINCREMENT,owner_id INTEGER,name TEXT,created_at TEXT); CREATE TABLE IF NOT EXISTS reel_playlist_tracks(playlist_id INTEGER REFERENCES reel_playlists(id) ON DELETE CASCADE,track_id INTEGER REFERENCES reel_tracks(id) ON DELETE CASCADE,position INTEGER,PRIMARY KEY(playlist_id,track_id));");
        if (!in_array('favorite', array_column($this->pdo->query('PRAGMA table_info(reel_tracks)')->fetchAll(), 'name'), true)) $this->pdo->exec('ALTER TABLE reel_tracks ADD COLUMN favorite INTEGER NOT NULL DEFAULT 0');
    }
    public function query(string $sql,array $params=[]): static { $sql=str_replace([' FOR UPDATE','INSERT IGNORE'],['','INSERT OR IGNORE'],$sql); $this->stmt=$this->pdo->prepare($sql); $this->stmt->execute($params); return $this; }
    public function get(): array { return $this->stmt->fetchAll(); }
    public function getOne(): mixed { return $this->stmt->fetch(); }
    public function getColumn(): mixed { return $this->stmt->fetchColumn(); }
    public function getInsertId(): string { return $this->pdo->lastInsertId(); }
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { $this->pdo->rollBack(); }
}; }
$router=new class {
    public string $assetPattern='';public mixed $assetHandler=null;
    public function get(string $pattern,mixed $handler):static{if(str_starts_with($pattern,'/plugins/reel-player/assets/')){$this->assetPattern=$pattern;$this->assetHandler=$handler;}return $this;}
    public function post(string $pattern,mixed $handler):static{return $this;}
    public function middleware(array $guard):static{return $this;}
};
require dirname(__DIR__).'/routes.php';
$path = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (str_starts_with($path,'/assets/')) {
    $file=$project.'/public'.$path;
    if (!is_file($file) || !str_ends_with($path,'.woff2')) abort();
    header('Content-Type:font/woff2'); readfile($file); exit;
}
if (preg_match('~^'.$router->assetPattern.'$~',$path,$match)) {
    header('Content-Type:'.match(pathinfo($match[1],PATHINFO_EXTENSION)) {'css'=>'text/css','js'=>'application/javascript','png'=>'image/png',default=>'image/svg+xml'});
    if ($match[1] === 'meters.js' && getenv('REEL_PLAYER_METERS_PREVIEW') === '1') {
        $source = file_get_contents(dirname(__DIR__).'/assets/meters.js');
        if (getenv('REEL_PLAYER_MIRROR_PREVIEW') === '1' || ($_GET['mirror'] ?? '') === '1') $source = str_replace('const capture = this.forceMirror ? null : this.audio.captureStream || this.audio.mozCaptureStream;', 'const capture = null;', $source);
        if (in_array($_GET['meterFault'] ?? '', ['clock','resume','blocked'], true)) {
            $fault = json_encode($_GET['meterFault']);
            $injection = <<<'JS'
    // Explicit test-only faults reproduce a running-but-frozen engine and a
    // never-settling resume promise. No production browser APIs are changed.
    const previewBaseContext = window.AudioContext;
    let previewContexts = 0;
    window.AudioContext = class extends previewBaseContext {
        constructor(...args) { super(...args); this.previewFault = ++previewContexts === 1 || PREVIEW_FAULT === 'blocked'; }
        get currentTime() { return this.previewFault ? 0 : super.currentTime; }
        get state() { return this.previewFault && PREVIEW_FAULT === 'resume' && super.state !== 'closed' ? 'suspended' : super.state; }
        resume() { return this.previewFault && PREVIEW_FAULT === 'resume' ? new Promise(() => {}) : super.resume(); }
    };
JS;
            $source = str_replace("    'use strict';", "    'use strict';\n" . str_replace('PREVIEW_FAULT', $fault, $injection), $source);
        }
        echo $source; exit;
    }
    if ($match[1] === 'player.js' && getenv('REEL_PLAYER_METERS_PREVIEW') === '1') {
        $source = file_get_contents(dirname(__DIR__).'/assets/player.js');
        $diagnostic = <<<'JS'
    // Opt-in, DOM-visible measurements for browser QA, absent in production.
    let previewStart = 0, previewFirstMeter = null, previewFrames = 0, previewGeometry = 0, previewFrameTime = 0, previewMaxGap = 0;
    audio.addEventListener('play', () => { previewStart = performance.now(); previewFirstMeter = null; previewFrames = previewGeometry = previewFrameTime = previewMaxGap = 0; });
    new MutationObserver(records => {
        if (!previewStart || audio.paused) return;
        previewGeometry += records.filter(record => ['r','d'].includes(record.attributeName)).length;
        if (previewFirstMeter === null && app.querySelector('[data-meter] i.is-lit')) previewFirstMeter = performance.now() - previewStart;
    }).observe(app.querySelector('.rp-machine'), {subtree:true,attributes:true,attributeFilter:['r','d','class']});
    const previewFrame = time => {
        if (previewStart && !audio.paused && !document.hidden) {
            if (previewFrameTime) previewMaxGap = Math.max(previewMaxGap,time-previewFrameTime);
            previewFrameTime = time; previewFrames++;
        }
        requestAnimationFrame(previewFrame);
    };
    requestAnimationFrame(previewFrame);
    let previewGeneration=-1,previewCurrent=null;const previewTransitions=[];
    setInterval(() => {
        if(previewGeneration!==generation){previewGeneration=generation;previewCurrent={generation,phase:meterController.phase,firstSignalMs:null,maxRms:[0,0],track:current};previewTransitions.push(previewCurrent);if(previewTransitions.length>100)previewTransitions.shift();}
        if(previewCurrent){previewCurrent.phase=meterController.phase;previewCurrent.firstSignalMs=meterController.firstSignal;previewCurrent.maxRms=previewCurrent.maxRms.map((v,i)=>Math.max(v,meterController.rms[i]));}
        app.dataset.meterDiagnostics = JSON.stringify({health:meterController.snapshot(),nativeTime:audio.currentTime,nativePaused:audio.paused,nativeReady:audio.readyState,waiting:loading,captureTrack:graph?.capturedTrack?.readyState,preloadActive:preloader.active,preloadDone:preloader.done.size,firstMeterMs:previewFirstMeter,frames:previewFrames,geometryWrites:previewGeometry,maxFrameGapMs:previewMaxGap,transitions:previewTransitions,rotors:visuals.reels.map(reel=>({state:reel.motion?.playState,rate:reel.motion?.playbackRate,time:reel.motion?.currentTime}))});
    }, 100);
JS;
        echo preg_replace('/\}\)\(\);\s*$/', $diagnostic . "\n})();", $source); exit;
    }
    $GLOBALS['route']=['file'=>$match['file']];($router->assetHandler)();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'reel-browser-fixture') { response()->json(['status'=>false,'message'=>'Invalid CSRF'],419); }
// Reproducible opt-in PCM fixtures, kept in a separate database and directory.
// Levels come from the audible file, including true zero and unequal stereo.
if ($audit && $path === '/admin/reel-player') {
    $lock=fopen(STORAGE.'/seed.lock','c');flock($lock,LOCK_EX);
    try {
        if(!(int)db()->query('SELECT COUNT(*) FROM reel_tracks')->getColumn()) {
            $owner=(int)get_user()['id'];$dir=STORAGE.'/reel-player/'.$owner;
            if(!is_dir($dir))mkdir($dir,0700,true);
            $duration=$auditMode==='short'?2:24;$rate=44100;
            foreach ([['Zero',2,0,0],['Quiet',2,.014,.007],['Mono',1,.12,.12],['Stereo',2,.18,.04]] as [$name,$channels,$left,$right]) {
                $data='';for($n=0;$n<$duration*$rate;$n++){ $tone=sin(2*M_PI*440*$n/$rate);$data.=pack('v',((int)round($tone*$left*32767))&65535);if($channels===2)$data.=pack('v',((int)round($tone*$right*32767))&65535); }
                $relative=$owner.'/'.sha1('audit-'.$name).'.wav';$bytes='RIFF'.pack('V',36+strlen($data)).'WAVEfmt '.pack('VvvVVvv',16,1,$channels,$rate,$rate*$channels*2,$channels*2,16).'data'.pack('V',strlen($data)).$data;
                file_put_contents(STORAGE.'/reel-player/'.$relative,$bytes);chmod(STORAGE.'/reel-player/'.$relative,0600);
                db()->query('INSERT INTO reel_tracks(owner_id,title,artist,filename,file_path,mime,file_size,duration,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$owner,$name,'PCM audit',$name.'.wav',$relative,'audio/wav',strlen($bytes),$duration,date('Y-m-d H:i:s')]);
            }
            $mp3=getenv('REEL_PLAYER_MP3_PREVIEW');
            if($mp3 && is_file($mp3) && pathinfo($mp3,PATHINFO_EXTENSION)==='mp3') {
                $relative=$owner.'/'.sha1('audit-mp3').'.mp3';copy($mp3,STORAGE.'/reel-player/'.$relative);chmod(STORAGE.'/reel-player/'.$relative,0600);
                db()->query('INSERT INTO reel_tracks(owner_id,title,artist,filename,file_path,mime,file_size,duration,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$owner,'MP3','WPT test tone','sine440.mp3',$relative,'audio/mpeg',filesize($mp3),0,date('Y-m-d H:i:s')]);
            }
        }
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
// Exercise the real streaming/cache code with disposable local audio as the
// upstream. No Google credentials or user libraries are exposed by this mode.
if (getenv('REEL_PLAYER_STREAM_PREVIEW') === '1' && ($_GET['local']??'')!=='1' && (preg_match('~^/admin/reel-player/media/(\d+)$~',$path,$mediaMatch) || $path === '/admin/reel-player/api/prepare')) {
    $warming = $path === '/admin/reel-player/api/prepare'; $id = (int)($warming ? ($_POST['id'] ?? 0) : $mediaMatch[1]);
    try { $track = (new \Fireball\ReelPlayer\Repositories\Library((int)get_user()['id']))->track($id); } catch (\InvalidArgumentException) { abort('Not found',404); }
    $filePath = (new \Fireball\ReelPlayer\Services\MediaStorage())->path($track['file_path']);
    $file = ['id'=>'fixture-audio-'.$id,'name'=>$track['filename'],'size'=>(string)filesize($filePath),'mimeType'=>$track['mime'],'headRevisionId'=>'fixture-'.filemtime($filePath)];
    $cache = new \Fireball\ReelPlayer\Services\DriveCache(STORAGE.'/reel-player',(int)get_user()['id'],hash('sha256','stream-fixture'));
    $pipe = new \Fireball\ReelPlayer\Services\DriveStream($cache,static fn():bool=>true);
    $meter = ($_GET['meter'] ?? '') === '1';
    $reader = static function(array $file,int $start,int $end,bool $warm,callable $head,callable $body) use ($filePath,$id,$meter):void {
        file_put_contents(STORAGE.'/stream-events.jsonl',json_encode(['track'=>$id,'warming'=>$warm,'meter'=>$meter,'start'=>$start,'end'=>$end,'time'=>microtime(true)])."\n",FILE_APPEND|LOCK_EX);
        $head(206,['content-range'=>"bytes {$start}-{$end}/{$file['size']}",'content-length'=>(string)($end-$start+1)]);
        $handle=fopen($filePath,'rb'); fseek($handle,$start); $remaining=$end-$start+1;
        try { while($remaining>0 && !feof($handle) && !connection_aborted()) {
            if(getenv('REEL_PLAYER_SLOW_PREVIEW')==='1') usleep($warm?100000:200000);
            $chunk=fread($handle,min(65536,$remaining)); if($chunk===''||$chunk===false)break; $remaining-=strlen($chunk);
            $more=$body($chunk); if($warm){echo ' ';flush();} if($more===false)break;
        }} finally {fclose($handle);}
    };
    header('Cache-Control: private, no-store'); header('X-Accel-Buffering: no');
    if ($warming) {
        header('Content-Type: application/json'); echo ' '; flush();
        try { $result=$pipe->prepare($file,$reader); echo json_encode(['status'=>true,...$result]); } catch (Throwable $e) { echo json_encode(['status'=>false,'message'=>$e->getMessage()]); }
    } else {
        $pipe->serve($file,(string)($_SERVER['HTTP_RANGE']??''),$reader,static function(int $code,array $headers):void {http_response_code($code);foreach($headers as $name=>$value)header($name.': '.$value);},static function(string $chunk):bool {echo $chunk;flush();return !connection_aborted();});
    }
    exit;
}
// Opt-in UI fixture for the Drive picker; never reads or changes a Google account.
if (getenv('REEL_PLAYER_DRIVE_PREVIEW') === '1') {
    if ($path === '/admin/reel-player/api/drive/status') response()->json(['status'=>true,'drive'=>['configured'=>true,'connected'=>true,'canManage'=>check_creator(),'clientId'=>'','callback'=>base_url('/admin/reel-player/drive/callback')]]);
    if ($path === '/admin/reel-player/api/drive/files') {
        $files = match ($_GET['page'] ?? '') {
            'audio-page' => [
                ['id'=>'fixture_audio_001','name'=>'Солнечный ветер — Stereo Lab.mp3','size'=>'8750123','mimeType'=>'audio/mpeg'],
                ['id'=>'fixture_audio_002','name'=>'Лунный свет — живая запись ночного концерта на берегу моря.FLAC','size'=>'134501234','mimeType'=>'audio/flac'],
                ['id'=>'fixture_audio_003','name'=>'Полночь.m4a','size'=>'6574321','mimeType'=>'video/mp4'],
            ],
            'more-audio' => [
                ['id'=>'fixture_audio_001','name'=>'Солнечный ветер — Stereo Lab.mp3','size'=>'8750123','mimeType'=>'audio/mpeg'],
                ['id'=>'fixture_audio_004','name'=>'Тихий океан.opus','size'=>'15743','mimeType'=>'audio/ogg'],
            ],
            default => [
                ['id'=>'fixture_png_001','name'=>'!.png','size'=>'123','mimeType'=>'image/png'],
                ['id'=>'fixture_php_001','name'=>'.access.php','size'=>'12','mimeType'=>'application/x-httpd-php'],
                ['id'=>'fixture_folder_001','name'=>'Музыка','mimeType'=>'application/vnd.google-apps.folder'],
            ],
        };
        $next = match ($_GET['page'] ?? '') {'audio-page'=>'more-audio','more-audio'=>'',default=>'audio-page'};
        $folder = $_GET['folder'] ?? '';
        if ($folder === 'root') {
            $files = [
                ['id'=>'fixture_music_folder_001','name'=>'Музыка','mimeType'=>'application/vnd.google-apps.folder'],
                ['id'=>'fixture_concert_folder_001','name'=>'Концерты','mimeType'=>'application/vnd.google-apps.folder'],
                ['id'=>'fixture_png_001','name'=>'!.png','size'=>'123','mimeType'=>'image/png'],
            ]; $next = '';
        } elseif ($folder === 'fixture_music_folder_001' && empty($_GET['page'])) {
            $files = [
                ['id'=>'fixture_album_folder_001','name'=>'Ночные альбомы','mimeType'=>'application/vnd.google-apps.folder'],
                ['id'=>'fixture_audio_001','name'=>'Солнечный ветер — Stereo Lab.mp3','size'=>'8750123','mimeType'=>'audio/mpeg'],
                ['id'=>'fixture_audio_002','name'=>'Лунный свет — живая запись ночного концерта на берегу моря.FLAC','size'=>'134501234','mimeType'=>'audio/flac'],
            ]; $next = 'more-audio';
        } elseif ($folder === 'fixture_album_folder_001') {
            $files = [['id'=>'fixture_audio_003','name'=>'Полночь.m4a','size'=>'6574321','mimeType'=>'video/mp4']]; $next = '';
        } elseif ($folder === 'fixture_concert_folder_001') { $files = []; $next = ''; }
        $result = ['files'=>$files,'nextPageToken'=>$next];
        response()->json(['status'=>true,...($folder !== '' ? \Fireball\ReelPlayer\Services\GoogleDrive::browsePage($result) : \Fireball\ReelPlayer\Services\GoogleDrive::audioPage($result))]);
    }
}
$controller=new \Fireball\ReelPlayer\Controllers\PlayerController();
try {
    // Real network delay for buffering UI checks; only on this opt-in test server.
    if (getenv('REEL_PLAYER_BUFFER_PREVIEW') === '1' && $path === '/admin/reel-player/media/2') sleep(10);
    if (preg_match('~^/admin/reel-player/(media|cover)/(\d+)$~',$path,$match)) { $GLOBALS['route']=['id'=>$match[2]]; $controller->{$match[1]}(); exit; }
    $method = match ($path) { '/admin/reel-player'=>'index','/admin/reel-player/api/state'=>'state','/admin/reel-player/api/action'=>'action','/admin/reel-player/api/upload'=>'upload','/admin/reel-player/api/prepare'=>'prepare','/admin/reel-player/api/drive/status'=>'driveStatus',default=>'' };
    if ($method==='') abort();
    echo $controller->$method();
} catch (Throwable $error) { error_log((string)$error); response()->json(['status'=>false,'message'=>$error->getMessage()],500); }
