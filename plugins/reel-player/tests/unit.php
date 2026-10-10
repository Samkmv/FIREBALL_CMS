<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Services/MediaStorage.php';
require dirname(__DIR__) . '/src/Services/GoogleDrive.php';
require dirname(__DIR__, 3) . '/core/Auth.php';
require dirname(__DIR__, 3) . '/core/Plugins/PluginInterface.php';
require dirname(__DIR__) . '/Plugin.php';
// Use the CMS's real role check with a session double; no user accounts are changed.
function session(): object {
    static $session;
    return $session ??= new class {
        public function has(string $key): bool { return $key === 'user' && isset($GLOBALS['reelTestUser']); }
        public function get(string $key, mixed $default = null): mixed { return $key === 'user' ? ($GLOBALS['reelTestUser'] ?? null) : ($GLOBALS['reelTestSession'][$key] ?? $default); }
        public function set(string $key, mixed $value): void { $GLOBALS['reelTestSession'][$key] = $value; }
        public function remove(string $key): void { unset($GLOBALS['reelTestSession'][$key]); }
    };
}
function check_admin(): bool { return \FBL\Auth::isAdmin(); }
function base_href(string $path): string { return $path; }
function base_url(string $path): string { return 'https://reel-unit.invalid' . $path; }
function add_filter(string $hook, callable $callback): void { $GLOBALS['reelTestFilters'][$hook] = $callback; }
use Fireball\ReelPlayer\Services\MediaStorage;
use Fireball\ReelPlayer\Services\GoogleDrive;
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void { $checks++; if (!$condition) throw new RuntimeException($message); };
foreach ([['', [0,99,false]], ['bytes=0-9',[0,9,true]], ['bytes=30-',[30,99,true]], ['bytes=-20',[80,99,true]], ['bytes=90-1000',[90,99,true]], ['bytes=-200',[0,99,true]]] as [$header,$expected]) $assert(MediaStorage::range(100,$header) === $expected,'Range: '.$header);
foreach (['bytes=100-', 'bytes=9-2', 'bytes=-0', 'bytes=-', 'bytes=0-2,4-6', 'items=1-2', "bytes=0-1\r\nX-Test: 1"] as $header) $assert(MediaStorage::range(100,$header) === null,'Reject invalid range');
$assert(MediaStorage::range(0,'') === null,'Empty file');
$storage = new MediaStorage('/tmp/reel-unit');
$assert($storage->path('12/' . str_repeat('a',40) . '.mp3') === '/tmp/reel-unit/12/' . str_repeat('a',40) . '.mp3','Private storage path');
foreach (['../config.php','12/../../config.php','12/drive.json','0/'.str_repeat('a',40).'.mp3','1/x.php'] as $path) {
    try { $storage->path($path); $assert(false,'Reject traversal'); } catch (InvalidArgumentException) { $assert(true,'Reject traversal'); }
}
foreach (['https://drive.google.com/file/d/abcdefghijk123/view' => ['abcdefghijk123',false], 'https://drive.google.com/drive/folders/abcdefghijk123' => ['abcdefghijk123',true], 'https://drive.google.com/open?id=abcdefghijk123' => ['abcdefghijk123',false]] as $url=>$expected) $assert(GoogleDrive::linkId($url) === $expected,'Drive links');
foreach (['http://drive.google.com/file/d/abcdefghijk123/view','https://evil.test/file/d/abcdefghijk123/view','https://drive.google.com.evil.test/open?id=abcdefghijk123','https://drive.google.com/open?id[]=abcdefghijk123'] as $url) {
    try { GoogleDrive::linkId($url); $assert(false,'Reject foreign link'); } catch (InvalidArgumentException) { $assert(true,'Reject foreign link'); }
}
$file = GoogleDrive::assertAudio(['id'=>'abcdefghijk123','name'=>'Music.m4a','size'=>'42','mimeType'=>'video/mp4','capabilities'=>['canDownload'=>true]]);
$assert($file['mimeType'] === 'audio/mp4','Drive MIME normalization');
foreach ([['name'=>'script.php','size'=>42],['name'=>'Song.mp3','size'=>0],['name'=>'Song.mp3','size'=>42,'capabilities'=>['canDownload'=>false]]] as $invalid) {
    try { GoogleDrive::assertAudio(['id'=>'abcdefghijk123',...$invalid]); $assert(false,'Reject unavailable audio'); } catch (InvalidArgumentException) { $assert(true,'Reject unavailable audio'); }
}
$mixed = GoogleDrive::audioPage(['nextPageToken'=>'next-audio-page', 'files'=>[
    ['id'=>'abcdefghijk123','name'=>'Cover.png','size'=>'500','mimeType'=>'image/png'],
    ['id'=>'abcdefghijk124','name'=>'.access.php','size'=>'128','mimeType'=>'application/x-httpd-php'],
    ['id'=>'abcdefghijk125','name'=>'Music','mimeType'=>'application/vnd.google-apps.folder'],
    ['id'=>'abcdefghijk126','name'=>'Empty.mp3','size'=>'0','mimeType'=>'audio/mpeg'],
    ['id'=>'abcdefghijk127','name'=>'Locked.mp3','size'=>'1500','capabilities'=>['canDownload'=>false]],
    ['id'=>'abcdefghijk128','name'=>'Night.MP3','size'=>'1500','mimeType'=>'application/octet-stream'],
    ['id'=>'abcdefghijk129','name'=>'Moon.m4a','size'=>'1700','mimeType'=>'video/mp4'],
]]);
$assert(array_column($mixed['files'],'name') === ['Night.MP3','Moon.m4a'], 'Drive picker excludes non-audio, empty and download-blocked files');
$assert(array_column($mixed['files'],'mimeType') === ['audio/mpeg','audio/mp4'], 'Drive picker normalizes uppercase and generic MIME audio');
$assert($mixed['nextPageToken'] === 'next-audio-page', 'Filtering preserves Drive pagination');
$assert(GoogleDrive::audioPage(['files'=>[],'nextPageToken'=>'partial-page']) === ['files'=>[],'nextPageToken'=>'partial-page'], 'Empty Drive page can still have more results');
$browse = GoogleDrive::browsePage(['nextPageToken'=>'next-folder-page','files'=>[
    ['id'=>'abcdefghijk123','name'=>'Cover.png','size'=>'500','mimeType'=>'image/png'],
    ['id'=>'abcdefghijk124','name'=>'.access.php','size'=>'128','mimeType'=>'application/x-httpd-php'],
    ['id'=>'abcdefghijk125','name'=>'Альбомы','mimeType'=>'application/vnd.google-apps.folder'],
    ['id'=>'abcdefghijk126','name'=>'Night.MP3','size'=>'1500','mimeType'=>'application/octet-stream'],
]]);
$assert(array_column($browse['files'],'name') === ['Альбомы','Night.MP3'], 'Drive folder browser keeps navigable folders and audio, excluding unrelated files');
$assert($browse['nextPageToken'] === 'next-folder-page', 'Drive folder browser preserves pagination');
$driveRoot = sys_get_temp_dir() . '/reel-drive-unit-' . bin2hex(random_bytes(8));
mkdir($driveRoot . '/1',0700,true);
$client = 'unit-test-client-12345.apps.googleusercontent.com';
$legacy = ['client_id'=>$client,'client_secret'=>'unit-test-secret','access_token'=>'creator-access','refresh_token'=>'creator-refresh','expires_at'=>time()+3600];
file_put_contents($driveRoot . '/1/drive.json', json_encode($legacy));
try {
    $creatorDrive = new GoogleDrive(1,$driveRoot,true);
    $creatorStatus = $creatorDrive->status();
    $assert($creatorStatus['configured'] && $creatorStatus['connected'] && $creatorStatus['canManage'],'Creator legacy app migration preserves connection');
    $appFile = $driveRoot . '/drive-app.json';
    $assert((fileperms($appFile) & 0777) === 0600,'Shared Google app credentials remain private');
    $creatorFile = json_decode(file_get_contents($driveRoot . '/1/drive.json'),true);
    $assert(!isset($creatorFile['client_secret']) && $creatorFile['refresh_token']==='creator-refresh','Personal tokens are separated from shared app credentials');
    $adminDrive = new GoogleDrive(2,$driveRoot);
    $adminStatus = $adminDrive->status();
    $assert($adminStatus['configured'] && !$adminStatus['connected'] && !$adminStatus['canManage'] && $adminStatus['clientId']==='','Admin uses configured site app without creator settings or another user tokens');
    $originalApp = file_get_contents($appFile);
    try { $adminDrive->settings($client,'admin-change'); $assert(false,'Admin cannot change OAuth app'); } catch (InvalidArgumentException) { $assert(file_get_contents($appFile)===$originalApp,'Admin settings rejection leaves site app unchanged'); }
    mkdir($driveRoot . '/2',0700);
    file_put_contents($driveRoot . '/2/drive.json', json_encode([...$legacy,'refresh_token'=>'admin-refresh','access_token'=>'admin-access']));
    $assert($adminDrive->status()['connected'] && $creatorDrive->status()['connected'],'Matching legacy clients keep separate existing user connections');
    $assert(!(new GoogleDrive(3,$driveRoot))->status()['connected'],'Another user never inherits a Google connection');
    $creatorDrive->settings($client,'');
    $assert($creatorDrive->status()['connected'] && $adminDrive->status()['connected'],'Resaving unchanged app with blank secret keeps connections');
    $creatorDrive->authorizationUrl();
    $assert(($GLOBALS['reelTestSession']['reel_drive_oauth']['owner'] ?? 0) === 1,'OAuth pending state stays bound to the current owner');
    $pending = $GLOBALS['reelTestSession']['reel_drive_oauth'];
    try { $adminDrive->finish($pending['state'],'unit-code'); $assert(false,'OAuth callback rejects different owner'); } catch (InvalidArgumentException) { $assert(true,'OAuth callback rejects different owner before contacting Google'); }
    $creatorDrive->authorizationUrl(); $pending = $GLOBALS['reelTestSession']['reel_drive_oauth'];
    $creatorDrive->settings('unit-test-new-client-12345.apps.googleusercontent.com','new-unit-secret');
    $assert(!$creatorDrive->status()['connected'] && !$adminDrive->status()['connected'],'Changing site app requires fresh per-user OAuth');
    try { $creatorDrive->finish($pending['state'],'unit-code'); $assert(false,'OAuth callback rejects changed site app'); } catch (InvalidArgumentException $error) { $assert(str_contains($error->getMessage(),'Настройки Google изменились'),'OAuth callback rejects changed site app before contacting Google'); }
} finally {
    foreach ([1,2] as $owner) { @unlink($driveRoot . '/' . $owner . '/drive.json'); @rmdir($driveRoot . '/' . $owner); }
    @unlink($driveRoot . '/drive-app.json'); @rmdir($driveRoot);
}
$manifest = json_decode((string)file_get_contents(dirname(__DIR__).'/plugin.json'),true,512,JSON_THROW_ON_ERROR);
$assert($manifest['update']['enabled'] && $manifest['update']['path']==='plugins/reel-player','CMS update source');
$router = new class {
    public array $routes = [];
    public function get(string $path, mixed $handler): static { $this->routes[] = ['path'=>$path,'guard'=>[]]; return $this; }
    public function post(string $path, mixed $handler): static { return $this->get($path,$handler); }
    public function middleware(array $guard): static { $this->routes[array_key_last($this->routes)]['guard'] = $guard; return $this; }
};
require dirname(__DIR__) . '/routes.php';
$privateRoutes = array_values(array_filter($router->routes, static fn(array $route): bool => str_starts_with($route['path'],'/admin/reel-player')));
$assert(count($privateRoutes) === 9, 'Player, API, media, covers and Google callback are registered');
foreach ($privateRoutes as $route) $assert($route['guard'] === ['auth','admin'],'Authenticated admin guard: ' . $route['path']);
(new FireballPluginReelPlayer())->boot();
foreach (['creator'=>true,'admin'=>true,'moderator'=>false,'user'=>false,'unknown'=>false,'guest'=>false] as $role=>$allowed) {
    $GLOBALS['reelTestUser'] = $role === 'guest' ? null : ['id'=>1,'role'=>$role];
    $assert(check_admin() === $allowed, 'CMS player access for ' . $role);
    $menu = $GLOBALS['reelTestFilters']['admin_menu']([]);
    $assert((count($menu) === 1) === $allowed, 'Player menu visibility for ' . $role);
}
unset($GLOBALS['reelTestUser']);
echo "Passed {$checks} Tape Room checks.\n";
