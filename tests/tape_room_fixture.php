<?php
/** Isolated HTTP fixture using real CMS routing, authentication and Tape Room repositories. */
declare(strict_types=1);
$testRoot = getenv('TAPE_ROOM_TEST_ROOT');
if (!$testRoot || !str_starts_with(realpath($testRoot) ?: '', dirname(__DIR__) . '/tests/.tmp/')) {
    throw new RuntimeException('TAPE_ROOM_TEST_ROOT must point inside tests/.tmp/.');
}
foreach (['cache', 'storage', 'sessions', 'uploads', 'views/layouts', 'views/themes/default/admin'] as $directory) {
    if (!is_dir($testRoot . '/' . $directory)) mkdir($testRoot . '/' . $directory, 0755, true);
}
define('ROOT', dirname(__DIR__));
if(getenv('TAPE_ROOM_TEST_PUBLIC')==='1')define('WWW',$testRoot.'/public');
define('CACHE', $testRoot . '/cache');
define('STORAGE', $testRoot . '/storage');
define('ERROR_LOGS', $testRoot . '/php-errors.log');
define('UPLOADS', getenv('TAPE_ROOM_TEST_PUBLIC')==='1' ? WWW.'/uploads' : $testRoot . '/uploads');
if(!is_dir(UPLOADS))mkdir(UPLOADS,0755,true);
define('VIEWS', $testRoot . '/views');
define('PATH', 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8797'));
ini_set('session.save_path', $testRoot . '/sessions');
ini_set('upload_tmp_dir', $testRoot . '/uploads');
ini_set('error_log', ERROR_LOGS);
error_reporting(E_ALL);
header('X-Frame-Options: SAMEORIGIN');
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';

final class TapeRoomMissingTable extends PDOException
{
    public function __construct(PDOException $error) { parent::__construct($error->getMessage(), 0, $error); $this->code = '42S02'; }
}

final class TapeRoomTestDatabase extends \FBL\Database
{
    public function __construct(PDO $pdo) { $this->connection = $pdo; }
    private ?int $lastDriveId=null;
    public function getInsertId(): string|false { return $this->lastDriveId!==null ? (string)$this->lastDriveId : parent::getInsertId(); }
    public function query(string $query, array $params = []): static
    {
        try {
            if(preg_match("/^SHOW TABLES LIKE '([^']+)'$/",$query,$match)){
                $query="SELECT name FROM sqlite_master WHERE type='table' AND name=?";$params=[$match[1]];
            }
            $query = str_replace(' FOR UPDATE', '', $query);
            $query=str_replace('ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)','ON CONFLICT(owner_id,source,source_id) DO UPDATE SET id=id RETURNING id',$query);
            $query = str_replace('INSERT IGNORE INTO','INSERT OR IGNORE INTO',$query);
            $query = str_replace(['TIMESTAMPDIFF(MICROSECOND,started_at,NOW(6))/1000000','DATE_SUB(NOW(6),INTERVAL 1 MINUTE)','DATE_ADD(NOW(6),INTERVAL 2 HOUR)','DATE_SUB(?,INTERVAL 30 SECOND)','NOW(6)'],["(julianday('now')-julianday(started_at))*86400","datetime('now','-1 minute')","datetime('now','+2 hours')","datetime(?,'-30 seconds')","STRFTIME('%Y-%m-%d %H:%M:%f','now')"],$query);
            if(str_starts_with($query,'DELETE'))$query=preg_replace('/ LIMIT [0-9]+$/','',$query);
            $query=str_replace('ON DUPLICATE KEY UPDATE listens=listens+1','ON CONFLICT(track_id) DO UPDATE SET listens=listens+1',$query);
            $query = str_replace('ON DUPLICATE KEY UPDATE favorite=1','ON CONFLICT DO UPDATE SET favorite=1',$query);
            $this->stmt = $this->connection->prepare($query);
            $this->stmt->execute($params);
            $this->lastDriveId=str_contains($query,'RETURNING id') ? (int)$this->stmt->fetchColumn() : null;
            if ($this->lastDriveId!==null) $this->stmt->closeCursor();
            return $this;
        } catch (PDOException $error) {
            if (str_contains($error->getMessage(), 'no such table')) throw new TapeRoomMissingTable($error);
            throw $error;
        }
    }
}
$pdo = new PDO('sqlite:' . $testRoot . '/fixture.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$app = new \FBL\Application(false);
$app->db = new TapeRoomTestDatabase($pdo);
$app->theme = new \FBL\ThemeManager();
$app->plugins = new \FBL\Plugins\PluginManager();
const MIDDLEWARE = ['auth' => \FBL\Middleware\Auth::class, 'admin' => \FBL\Middleware\Admin::class, 'guest' => \FBL\Middleware\Guest::class];
require ROOT . '/plugins/reel-player/Plugin.php';
(new FireballPluginReelPlayer())->boot();
add_filter('cms_rendered_html', static fn(string $html): string => (($_GET['safe_area_test']??'')==='1') ? str_replace('</head>','<style>:root{--tr-safe-top:54px;--tr-safe-bottom:34px}</style></head>',$html) : $html);
add_filter('cms_rendered_html', static fn(string $html): string => preg_replace_callback('~<(?:main|div)[^>]*data-player[^>]*>~',static fn($match)=>substr($match[0],0,-1).' data-test-document="'.bin2hex(random_bytes(12)).'">',$html,1));
add_filter('cms_rendered_html', static function(string $html): string {
    if(in_array($_GET['sync_device_test']??'', ['a','b'],true))$html=preg_replace_callback('~(<script type="application/json" data-player-config>)(.*?)(</script>)~s',static function($match){$config=json_decode($match[2],true,512,JSON_THROW_ON_ERROR);$config['storageKey'].=':sync-device-'.$_GET['sync_device_test'];return $match[1].json_encode($config,JSON_HEX_TAG|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).$match[3];},$html,1);
    if (($_GET['torrent_buffer_test']??'')!=='1') return $html;
    return preg_replace_callback('~(<script type="application/json" data-player-config>)(.*?)(</script>)~s',static function($match){$config=json_decode($match[2],true,512,JSON_THROW_ON_ERROR);$config['torrent']['bufferBytes']=262144;return $match[1].json_encode($config,JSON_HEX_TAG|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).$match[3];},$html,1);
});
add_filter('cms_rendered_html', static function(string $html): string {
    $instrumentation=<<<'JS'
<script>
addEventListener('DOMContentLoaded',()=>{
    const player=document.querySelector('[data-player]');if(!player)return;
    const output=document.createElement('output');output.hidden=true;output.dataset.testImages='';document.body.append(output);
    const ids=new WeakMap();let id=0;
    const report=()=>{output.dataset.instances=JSON.stringify([...player.querySelectorAll('[data-library-image]')].map(image=>{if(!ids.has(image))ids.set(image,++id);return {id:ids.get(image),src:image.getAttribute('src')};}));};
    new MutationObserver(report).observe(player,{childList:true,subtree:true});report();
    const browser=document.createElement('output');browser.hidden=true;browser.dataset.testBrowser='';document.body.append(browser);
    player.querySelector('audio').addEventListener('playing',()=>{browser.dataset.firstPlay=JSON.stringify({torrent:window.TapeRoomTorrents?.state(),time:player.querySelector('audio').currentTime});},{once:true});
    const range=document.createElement('button');range.hidden=true;range.textContent='Проверить torrent Range';document.body.append(range);
    const rangeResult=document.createElement('output');rangeResult.hidden=true;rangeResult.dataset.testTorrentRange='';document.body.append(rangeResult);
    range.onclick=async()=>{try{const audio=player.querySelector('audio'),response=await fetch(audio.currentSrc,{headers:{Range:'bytes=0-100'},signal:AbortSignal.timeout(5000)});rangeResult.textContent=JSON.stringify({status:response.status,headers:[...response.headers],bytes:[...new Uint8Array(await response.arrayBuffer())].slice(0,20)});}catch(e){rangeResult.textContent=e.message;}};
    setInterval(()=>{const audio=player.querySelector('audio');browser.textContent=JSON.stringify({webRTC:window.WebTorrent?.WEBRTC_SUPPORT,worker:navigator.serviceWorker?.controller?.scriptURL,torrent:window.TapeRoomTorrents?.state(),audio:{src:audio.currentSrc,time:audio.currentTime,duration:audio.duration,paused:audio.paused,ready:audio.readyState,error:audio.error?.message},requests:performance.getEntriesByType('resource').filter(e=>/torrent|webtorrent/.test(e.name)).map(e=>({url:e.name,bytes:e.transferSize}))});},500);
});
</script>
JS;
    return str_replace('</body>',$instrumentation.'</body>',$html);
});
$router = $app->router;
$router->get('/tape-room/api/drive/files',static function() use($testRoot){
    $files=json_decode((string)file_get_contents($testRoot.'/drive-files.json'),true,512,JSON_THROW_ON_ERROR);
    $folder=(string)request()->get('folder','root');
    response()->json(['status'=>true,'files'=>$folder==='root' ? [['id'=>'fixture-folder-01','name'=>'Music','mimeType'=>'application/vnd.google-apps.folder']] : $files,'nextPageToken'=>'']);
})->middleware(['auth']);
require ROOT . '/plugins/reel-player/routes.php';
$router->get('/test/recovery-media.wav',static function()use($testRoot):never{
    $counter=$testRoot.'/recovery-requests.json';$items=is_file($counter)?json_decode(file_get_contents($counter),true):[];$items[]=['range'=>$_SERVER['HTTP_RANGE']??'','retry'=>isset($_GET['_tr_retry'])];file_put_contents($counter,json_encode($items));
    if(count($items)<=2){http_response_code(503);header('Content-Type: text/plain');echo 'Synthetic temporary upstream interruption';exit;}
    $bytes=file_get_contents($testRoot.'/storage/reel-player/2/'.str_repeat('a',40).'.wav');$size=strlen($bytes);$start=0;$end=$size-1;
    if(preg_match('/^bytes=(\d+)-(\d*)$/',$_SERVER['HTTP_RANGE']??'', $m)){$start=(int)$m[1];$end=$m[2]!==''?min($end,(int)$m[2]):$end;http_response_code(206);header("Content-Range: bytes $start-$end/$size");}
    header('Content-Type: audio/wav');header('Accept-Ranges: bytes');header('Cache-Control: no-store');header('Content-Length: '.($end-$start+1));echo substr($bytes,$start,$end-$start+1);exit;
});
add_filter('cms_rendered_html',static function(string $html):string{
    if(($_GET['drive_recovery_test']??'')!=='1')return $html;
    return preg_replace_callback('~(<script type="application/json" data-player-config>)(.*?)(</script>)~s',static function($m){$config=json_decode($m[2],true);foreach($config['state']['tracks'] as &$item)if($item['id']===4){$item['url']=base_href('/test/recovery-media.wav');$item['title']='Проверка восстановления Drive';$item['duration']=12;}$config['state']['catalog']['albums']=[];$config['state']['catalog']['tracks']=[];return $m[1].json_encode($config,JSON_HEX_TAG|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).$m[3];},$html);
});
add_filter('cms_rendered_html', static function (string $html): string {
    if (($_GET['fresh_play_test'] ?? '') !== '1') return $html;
    return preg_replace_callback('~(<script type="application/json" data-player-config>)(.*?)(</script>)~s', static function ($match) {
        $config = json_decode($match[2], true, 512, JSON_THROW_ON_ERROR);
        $config['storageKey'] .= ':first-play-check';
        return $match[1] . json_encode($config, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $match[3];
    }, $html);
});
add_filter('cms_rendered_html', static function (string $html): string {
    if (($_GET['mini_player_test'] ?? '') !== '1') return $html;
    $audit = <<<'HTML'
<script>
addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-player]'); if (!app) return;
    const audio = app.querySelector('[data-audio]'), controls = app.querySelector('.rp-main-controls');
    const output = document.createElement('output'); output.hidden = true; output.dataset.miniTest = ''; document.body.append(output);
    const events = {loadstart:0,play:0,pause:0,volumechange:0};
    const sync = () => { output.textContent = JSON.stringify({events, volume:audio.volume, muted:audio.muted, sameAudio:audio === app.querySelector('[data-audio]'), sameControls:controls === app.querySelector('.rp-main-controls')}); };
    for (const name of Object.keys(events)) audio.addEventListener(name, () => { events[name]++; sync(); });
    for (const name of ['change','click']) document.addEventListener(name, () => requestAnimationFrame(sync));
    sync();
});
</script>
HTML;
    return str_replace('</body>', $audit . '</body>', $html);
});
$router->get('/', [\App\Controllers\HomeController::class, 'index']);
$router->get('/login', [\App\Controllers\AuthController::class, 'login'])->middleware(['guest']);
$router->post('/login', [\App\Controllers\AuthController::class, 'login'])->middleware(['guest']);
$router->get('/register', [\App\Controllers\AuthController::class, 'register'])->middleware(['guest']);
$router->get('/test/embed-host',static function():string{return '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Проверка iframe</title><style>body{margin:20px;background:#f7f5f0;font:14px system-ui}iframe{display:block;width:100%;max-width:640px;height:520px;border:0;border-radius:18px}</style><h1 style="font-size:18px">Плеер на другом сайте</h1><iframe src="'.htmlSC(str_replace('127.0.0.1','localhost',base_href('/tape-room/embed/album/1?theme='.(request()->get('embed_theme')==='dark'?'dark':'light')))).'" title="Tape Room — проверка iframe" allow="autoplay"></iframe>';});
$router->get('/test/guest',static function():never{session()->remove('user');response()->redirect(base_href('/'));});
$router->get('/test/login-recovery', static fn() => theme()->render('auth/login', ['title'=>'Вход', 'password_recovery_available'=>true]));
$router->get('/two-factor-challenge', [\App\Controllers\AuthController::class, 'twoFactorChallenge'])->middleware(['guest']);
$router->get('/profile', static fn() => theme()->render('auth/profile', ['user' => get_user()]))->middleware(['auth']);
$router->get('/test/site-page', static fn() => theme()->render('page', ['title'=>'О музыкальной комнате','page'=>['title'=>'О музыкальной комнате','content'=>'<p>Обычная информационная страница сайта.</p><p><a href="'.htmlSC(base_href('/profile')).'">Перейти в профиль</a></p><p><a href="'.htmlSC(base_href('/test/site-unavailable')).'">Недоступная страница</a></p>']]));
$router->get('/test/site-unavailable',static function():string{http_response_code(503);return 'Synthetic unavailable page';});
$router->get('/profile/settings', static fn() => theme()->render('auth/settings', ['user' => get_user()]))->middleware(['auth']);
$router->post('/profile/settings', [\App\Controllers\AuthController::class,'settings'])->middleware(['auth']);
$router->post('/logout', [\App\Controllers\AuthController::class, 'logout'])->middleware(['auth']);
$router->post('/api/analytics/track', static function () { response()->json(['status' => true]); })->withoutCSRFToken();
$router->get('/test/sign-in/(?P<role>user|creator|admin)', static function () {
    $id = get_route_param('role') === 'creator' ? 1 : (get_route_param('role') === 'admin' ? 3 : 2);
    session()->set('user', db()->findOne('users', $id));
    response()->redirect(base_href('/'));
});
$router->get('/test/baseline', static function () use ($testRoot) {
    (new \FBL\Middleware\Auth())->handle();
    extract((new \Fireball\ReelPlayer\Controllers\PlayerController())->pageData());
    $css_url = base_url('/test/original-assets/player.css');
    foreach (['js' => 'player', 'meters' => 'meters', 'preload' => 'preload', 'chapters' => 'chapters', 'transport' => 'transport', 'quality' => 'quality', 'collections' => 'collections', 'catalog' => 'catalog', 'images' => 'images'] as $key => $name) {
        ${$key . '_url'} = base_url('/test/original-assets/' . $name . '.js');
    }
    ob_start(); require $testRoot . '/original-player.php'; return (string)ob_get_clean();
});
$router->get('/test/diagnostics', static function () use ($testRoot) {
    $copyRoot = $testRoot . '/metadata-themes';
    @mkdir($copyRoot . '/tape-room', 0755, true);
    copy(ROOT . '/themes/tape-room/theme.json', $copyRoot . '/tape-room/theme.json');
    $copy = new \FBL\ThemeManager($copyRoot);
    $copy->updateTheme('tape-room', ['name' => 'Tape Room updated', 'version' => '1.0.1']);
    $copy->invalidateRuntime();
    response()->json([
        'valid' => theme()->validateThemeStructure('tape-room'),
        'missing_template' => theme()->resolveFile('templates', 'search', 'tape-room'),
        'missing_partial' => theme()->resolveFile('partials', 'sidebar', 'tape-room'),
        'missing_asset_path' => theme()->assetPath('css/theme.min.css'),
        'updated' => $copy->getTheme('tape-room'),
        'routes' => array_map(static fn($route) => ['path' => $route['path'], 'middleware' => $route['middleware'], 'csrf' => $route['needCSRFToken']], $GLOBALS['app']->router->getRoutes()),
    ]);
});

$path = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$asset = null;
if (str_starts_with($path, 'themes/tape-room/assets/')) $asset = ROOT . '/' . $path;
if (str_starts_with($path, 'test/original-assets/')) $asset = $testRoot . '/original-assets/' . basename($path);
if ($path === 'assets/default/fonts/inter-variable-latin.woff2') $asset = ROOT . '/public/' . $path;
if (str_starts_with($path, 'assets/img/')) $asset = ROOT . '/public/' . $path;
if(getenv('TAPE_ROOM_TEST_PUBLIC')==='1' && str_starts_with($path,'uploads/'))$asset=WWW.'/'.$path;
if ($asset && !str_contains($path, '..') && is_file($asset)) {
    header('Content-Type: ' . (['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'webp' => 'image/webp', 'woff2' => 'font/woff2'][pathinfo($asset, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($asset); exit;
}
try { echo $router->dispatch(); }
catch (Throwable $error) { http_response_code(500); echo $error::class . ': ' . $error->getMessage() . "\n" . $error->getTraceAsString(); }
