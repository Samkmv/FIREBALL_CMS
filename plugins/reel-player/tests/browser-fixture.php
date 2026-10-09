<?php
declare(strict_types=1);
// Isolated browser test server. No production accounts, database or music are used.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$project = dirname(__DIR__, 3);
define('STORAGE', $project . '/tmp/reel-player-browser');
if (!is_dir(STORAGE)) mkdir(STORAGE, 0700, true);
require $project . '/vendor/autoload.php';
require dirname(__DIR__) . '/Plugin.php';
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function base_href(string $path): string { return $path; }
function base_url(string $path): string { return 'http://127.0.0.1:8897' . $path; }
function get_user(): array { return ['id'=>1,'role'=>'creator']; }
function session(): object { static $session; return $session ??= new class {
    public function get(string $key,mixed $default=null): mixed { return $key==='needCSRFToken' ? 'reel-browser-fixture' : $default; }
    public function close(): void {}
}; }
function abort(string $message='',int $status=404): never { http_response_code($status); exit($message); }
function log_error_details(string $message,array $context=[],?Throwable $error=null): void { error_log($message . ': ' . $error?->getMessage()); }
function request(): \FBL\Request { static $request; return $request ??= new \FBL\Request($_SERVER['REQUEST_URI']); }
function response(): \FBL\Response { static $response; return $response ??= new \FBL\Response(); }
function get_route_param(string $key): string { return (string)($GLOBALS['route'][$key] ?? ''); }
function return_translation(string $key): string { return $key; }
function plugin_view(string $slug,string $view,array $data,bool $layout=true): string { extract($data); ob_start(); require dirname(__DIR__).'/views/'.$view.'.php'; return (string)ob_get_clean(); }
function setting(string $key,mixed $default=null): mixed { return $default; }
function config_value(string $key,mixed $default=null): mixed { return $default; }
function db(): object { static $db; return $db ??= new class {
    private PDO $pdo; private PDOStatement $stmt;
    public function __construct() {
        $this->pdo=new PDO('sqlite:'.STORAGE.'/fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS reel_tracks(id INTEGER PRIMARY KEY AUTOINCREMENT,owner_id INTEGER,title TEXT,artist TEXT DEFAULT '',filename TEXT,file_path TEXT,mime TEXT,file_size INTEGER,duration REAL DEFAULT 0,cover_path TEXT,cover_mime TEXT,source TEXT DEFAULT 'local',source_id TEXT,created_at TEXT,UNIQUE(owner_id,source,source_id)); CREATE TABLE IF NOT EXISTS reel_playlists(id INTEGER PRIMARY KEY AUTOINCREMENT,owner_id INTEGER,name TEXT,created_at TEXT); CREATE TABLE IF NOT EXISTS reel_playlist_tracks(playlist_id INTEGER REFERENCES reel_playlists(id) ON DELETE CASCADE,track_id INTEGER REFERENCES reel_tracks(id) ON DELETE CASCADE,position INTEGER,PRIMARY KEY(playlist_id,track_id));");
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
$path = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (str_starts_with($path,'/assets/')) {
    $file=$project.'/public'.$path;
    if (!is_file($file) || !str_ends_with($path,'.woff2')) abort();
    header('Content-Type:font/woff2'); readfile($file); exit;
}
if (preg_match('~^/plugins/reel-player/assets/(player\.(css|js)|cover\.svg)$~',$path,$match)) {
    header('Content-Type:'.match(pathinfo($match[1],PATHINFO_EXTENSION)) {'css'=>'text/css','js'=>'application/javascript',default=>'image/svg+xml'});
    readfile(dirname(__DIR__).'/assets/'.$match[1]); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'reel-browser-fixture') { response()->json(['status'=>false,'message'=>'Invalid CSRF'],419); }
$controller=new \Fireball\ReelPlayer\Controllers\PlayerController();
try {
    if (preg_match('~^/admin/reel-player/(media|cover)/(\d+)$~',$path,$match)) { $GLOBALS['route']=['id'=>$match[2]]; $controller->{$match[1]}(); exit; }
    $method = match ($path) { '/admin/reel-player'=>'index','/admin/reel-player/api/state'=>'state','/admin/reel-player/api/action'=>'action','/admin/reel-player/api/upload'=>'upload','/admin/reel-player/api/drive/status'=>'driveStatus',default=>'' };
    if ($method==='') abort();
    echo $controller->$method();
} catch (Throwable $error) { error_log((string)$error); response()->json(['status'=>false,'message'=>$error->getMessage()],500); }
