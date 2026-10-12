<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$project = dirname(__DIR__); $temporary = __DIR__.'/.tmp';
if (!is_dir($temporary)) mkdir($temporary,0700,true);
$testRoot = $temporary.'/tape-room-catalog-'.bin2hex(random_bytes(8)); mkdir($testRoot,0700);
foreach (['data','sessions','cache'] as $directory) mkdir($testRoot.'/'.$directory,0700);
define('STORAGE',$testRoot.'/data'); define('CACHE',$testRoot.'/cache'); define('ERROR_LOGS',$testRoot.'/error.log');
ini_set('error_log',$testRoot.'/php.log'); ini_set('session.save_path',$testRoot.'/sessions'); ini_set('upload_tmp_dir',$testRoot);
$schema = null; $admin = null; $parent = getmypid();
register_shutdown_function(static function() use ($testRoot,&$schema,&$admin,$parent): void {
    if (getmypid() !== $parent) return;
    if ($schema && $admin && preg_match('/^tape_catalog_test_[a-f0-9]{16}$/D',$schema)) {
        try { $admin->exec('DROP DATABASE `'.$schema.'`'); }
        catch (Throwable $error) { fwrite(STDERR,'Could not remove disposable catalog schema: '.$error->getMessage().PHP_EOL); }
    }
    if (is_dir($testRoot)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($testRoot);
    }
});
require $project.'/config/config.php'; require $project.'/vendor/autoload.php'; require $project.'/plugins/reel-player/Plugin.php';
function base_href(string $path): string { return $path; }
function check_creator(): bool { return true; }
function log_error_details(string $message,array $context=[],?Throwable $error=null): void { error_log($message.': '.$error?->getMessage()); }
function db(): object { return $GLOBALS['catalogDb']; }
use Fireball\ReelPlayer\Repositories\Catalog;
use Fireball\ReelPlayer\Repositories\Library;
\FBL\PerformanceProfiler::start();
$dsn = 'mysql:host='.DB_SETTINGS['host'].';charset=utf8mb4';
if (!empty(DB_SETTINGS['port'])) $dsn .= ';port='.(int)DB_SETTINGS['port'];
if (($socket = getenv('REEL_PLAYER_TEST_MYSQL_SOCKET')) !== false && $socket !== '') $dsn = 'mysql:unix_socket='.$socket.';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$admin = new PDO($dsn,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);
$schema = 'tape_catalog_test_'.bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$connection = new PDO($dsn.';dbname='.$schema,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);
$GLOBALS['catalogDb'] = new \FBL\Database($connection);
db()->query('CREATE TABLE users(id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
db()->query('INSERT INTO users(id) VALUES(1),(2),(3)');
foreach (glob($project.'/plugins/reel-player/migrations/*.sql') as $migration) foreach (explode(';',(string)file_get_contents($migration)) as $sql) if (trim($sql)!=='') db()->query($sql);
$checks = 0;
$assert = static function(bool $ok,string $message) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($message); };
$reject = static function(callable $action,string $message) use ($assert): void { try { $action(); } catch (InvalidArgumentException) { $assert(true,$message); return; } $assert(false,$message); };
$insertTrack = static function(int $owner,string $title,string $source='local',float $duration=60): int {
    db()->query('INSERT INTO reel_tracks(owner_id,title,artist,filename,file_path,mime,file_size,duration,source,source_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [$owner,$title,'Artist',$title.'.mp3',null,'audio/mpeg',1024,$duration,$source,$source==='drive' ? 'private_drive_identifier_'.$owner : null,date('Y-m-d H:i:s')]);
    return (int)db()->getInsertId();
};
$creator = new Catalog(1,true); $viewer = new Catalog(2,false); $other = new Catalog(3,true);
$library = new Library(1); $viewerLibrary = new Library(2);
$first = $insertTrack(1,'First 🎵'); $second = $insertTrack(1,'Second','drive');
$private = $insertTrack(1,'Unpublished secret'); $foreign = $insertTrack(2,'Viewer own');
db()->query('INSERT INTO reel_chapters(track_id,title,artist,start_time) VALUES(?,?,?,?)', [$first,'Chapter','Artist',0]);
$reject(fn()=>$viewer->accessibleTrack($first),'Private audio is not cross-user accessible');
$assert($creator->accessibleTrack($second)['source_id']==='private_drive_identifier_1','Owned Drive source remains available server-side');
$reject(fn()=>$creator->action('album.create',['title'=>'Wrong owner','tracks'=>[$foreign]]),'Cannot publish or package foreign owned tracks');
$assert((int)db()->query('SELECT COUNT(*) FROM reel_albums')->getColumn()===0,'Failed create rolls back album and membership');
$album = $creator->action('album.create',['title'=>'Album 🎵','artist'=>'Album Artist','release_date'=>'2026-10-12','description'=>'Details','tracks'=>json_encode([$first,$second])]);
$assert($album>0 && (int)$creator->album($album)['published']===0,'Creation starts private without automatic publication');
$assert($creator->state()['albums'][0]['tracks']===[$first,$second],'Album member order is preserved');
$assert($creator->state()['albums'][0]['saved']===true,'Owned album starts saved');
$assert($viewer->state()['albums']===[] && $viewer->state()['tracks']===[],'Foreign private albums and metadata are not serialized');
$reject(fn()=>$viewer->album($album),'Cannot read private foreign album');
$reject(fn()=>$viewer->action('album.save',['id'=>$album]),'Cannot bookmark private foreign album');
$reject(fn()=>$viewer->action('album.update',['id'=>$album,'title'=>'Steal']),'Cannot edit foreign album');
$reject(fn()=>$creator->action('album.update',['id'=>$album,'release_date'=>'2026-02-30']),'Invalid real release date is rejected');
$reject(fn()=>$creator->action('album.update',['id'=>$album,'release_date'=>'12/10/2026']),'Browser-specific date string rejected');
$reject(fn()=>$creator->action('album.update',['id'=>$album,'title'=>str_repeat('x',241)]),'Album title length is bounded');
$reject(fn()=>$creator->action('album.update',['id'=>$album,'artist'=>"Bad\x00artist"]),'Album control characters rejected');
$reject(fn()=>$creator->action('album.tracks',['id'=>$album,'tracks'=>[$first,$first]]),'Repeated album IDs rejected');
$reject(fn()=>$creator->action('album.tracks',['id'=>$album,'tracks'=>'not json']),'Malformed track membership JSON rejected');
$reject(fn()=>$creator->action('album.tracks',['id'=>$album,'tracks'=>['bad']]),'Non-numeric track IDs rejected');
$assert($creator->state()['albums'][0]['tracks']===[$first,$second],'Failed membership updates leave current order intact');
$viewerAlbum = $viewer->action('album.create',['title'=>'Viewer album','tracks'=>[$foreign]]);
$reject(fn()=>$viewer->action('album.publish',['id'=>$viewerAlbum,'published'=>1]),'Ordinary admin cannot publish own album');
$reject(fn()=>$other->action('album.publish',['id'=>$album,'published'=>1]),'Another creator cannot publish foreign album');
$empty = $creator->action('album.create',['title'=>'Empty']);
$reject(fn()=>$creator->action('album.publish',['id'=>$empty,'published'=>1]),'Empty album cannot be published');
$creator->action('album.publish',['id'=>$album,'published'=>1]);
$assert((int)$viewer->album($album)['published']===1,'Explicit publication makes album readable');
$assert($viewer->accessibleTrack($second)['owner_id']==1 && $viewer->accessibleTrack($second)['source']==='drive','Shared source lookup keeps original Drive owner');
$reject(fn()=>$viewer->accessibleTrack($private),'Publishing album does not share other owner music');
$public = $viewer->state(); $publicAlbum = array_values(array_filter($public['albums'],static fn(array $a):bool=>$a['id']===$album))[0];
$assert($publicAlbum['editable']===false && $publicAlbum['canPublish']===false && $publicAlbum['saved']===false,'Foreign album has no edit/publish/save privileges implicitly');
$publicTrack = array_values(array_filter($public['tracks'],static fn(array $t):bool=>$t['id']===$first))[0];
$assert($publicTrack['shared']===true && $publicTrack['collected']===false && $publicTrack['editable']===false,'Discovery alone does not add shared song to private collection');
$assert($publicTrack['url']==='/admin/reel-player/catalog/media/'.$first && count($publicTrack['chapters'])===1,'Shared playback uses guarded catalog endpoint and readable chapters');
$serialized = json_encode($public,JSON_THROW_ON_ERROR);
foreach (['source_id','private_drive_identifier','owner_id','file_path','cover_path','refresh_token','access_token'] as $secret) $assert(!str_contains($serialized,$secret),'Catalog JSON excludes '.$secret);
$creator->action('track.favorite',['id'=>$first,'favorite'=>1]);
$assert(array_values(array_filter($viewer->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['favorite']===false,'Track owner favorite does not leak to other users');
$viewer->action('album.save',['id'=>$album,'saved'=>1]); $viewer->action('album.save',['id'=>$album,'saved'=>1]);
$assert((int)db()->query('SELECT COUNT(*) FROM reel_album_saves WHERE owner_id=2 AND album_id=?',[$album])->getColumn()===1,'Album save is an idempotent reference');
$assert((int)db()->query('SELECT COUNT(*) FROM reel_tracks')->getColumn()===4,'Saving creates no track or physical file copies');
$assert(array_values(array_filter($viewer->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['collected']===true,'Saved album songs join collection');
$viewer->action('album.save',['id'=>$album,'saved'=>0]);
$assert(array_values(array_filter($viewer->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['collected']===false,'Removing album save removes collection membership alone');
$viewer->action('track.favorite',['id'=>$first,'favorite'=>1]);
$assert(array_values(array_filter($viewer->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['favorite']===true,'Viewer favorite is independent and persisted');
$reject(fn()=>$viewer->action('track.favorite',['id'=>$first,'favorite'=>'yes']),'Favorite rejects non-boolean flags');
$viewer->action('track.favorite',['id'=>$first,'favorite'=>0]);
$playlist = $viewerLibrary->action('playlist.create',['name'=>'Shared collection']);
$viewerLibrary->add($playlist,$first); $viewerLibrary->add($playlist,$first);
$assert((int)db()->query('SELECT COUNT(*) FROM reel_playlist_tracks WHERE playlist_id=?',[$playlist])->getColumn()===1,'Private playlist can reference one accessible shared song without duplication');
$assert(array_values(array_filter($viewer->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['collected']===true,'Private playlist keeps shared song collected without album save');
$reject(fn()=>$viewerLibrary->action('track.update',['id'=>$first,'title'=>'Hacked']),'Shared track metadata stays read-only');
$reject(fn()=>$viewerLibrary->action('track.delete',['id'=>$first]),'Shared track original cannot be deleted by viewer');
$reject(fn()=>$viewerLibrary->add($playlist,$private),'Private playlist cannot be used to gain unauthorized audio access');
$creator->action('album.tracks',['id'=>$album,'tracks'=>[$second,$first]]);
$assert(array_values(array_filter($viewer->state()['albums'],static fn(array $a):bool=>$a['id']===$album))[0]['tracks']===[$second,$first],'Album reorder reaches other users without changing source IDs');
$reject(fn()=>$creator->action('album.tracks',['id'=>$album,'tracks'=>[]]),'Published album cannot become empty through reorder');
$originalOrder = $creator->state()['albums'];
$reject(fn()=>$creator->action('album.update',['id'=>$album,'title'=>'Must rollback','tracks'=>[$foreign]]),'Metadata and invalid membership update roll back atomically');
$assert($creator->album($album)['title']==='Album 🎵','Rolled-back edit does not leak new title');
$creator->action('album.update',['id'=>$album,'release_date'=>'']);
$assert($creator->album($album)['release_date']===null,'Unknown release date stays distinct from added date');

$listen = $viewer->beginListening($first);
$assert($listen['threshold']===30.0 && strlen($listen['token'])===64,'Listen token has an actual 30 second qualification threshold');
$assert($viewer->finishListening($listen['token'],30)['counted']===false,'Claiming played seconds immediately does not count before wall time');
$reject(fn()=>$creator->finishListening($listen['token'],30),'Listening token cannot cross user accounts');
$reject(fn()=>$viewer->finishListening('bad',30),'Invalid token syntax rejected');
$reject(fn()=>$viewer->finishListening($listen['token'],INF),'Non-finite listened seconds rejected');
$reject(fn()=>$viewer->finishListening($listen['token'],-1),'Negative listening time rejected');
db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL 31 SECOND) WHERE token_hash=?',[hash('sha256',$listen['token'])]);
$assert($viewer->finishListening($listen['token'],1000)['counted']===false,'Impossible client duration greater than server elapsed limit rejected');
$assert($viewer->finishListening($listen['token'],29)['counted']===false,'Partial listens below threshold do not count');
$assert($viewer->finishListening($listen['token'],30)['listens']===1,'Real elapsed qualifying listen counts');
$assert($viewer->finishListening($listen['token'],30)['listens']===1,'Retry of same token never double counts');
$assert(array_values(array_filter($creator->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['listen_count']===1,'Popularity is based on persisted listens rather than clicks');
$assert(array_values(array_filter($library->state()['tracks'],static fn(array $t):bool=>$t['id']===$first))[0]['listen_count']===1,'Owned library also carries listen counts');
$parallel = $viewer->beginListening($first);
db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL 31 SECOND) WHERE token_hash=?',[hash('sha256',$parallel['token'])]);
$assert($viewer->finishListening($parallel['token'],30)['listens']===1,'Overlapping tabs for same user and song do not multiply listens');
$assert($viewer->finishListening($parallel['token'],30)['credited']===false,'Processed duplicate does not claim a credited listen');
$newListen = $creator->beginListening($first);
db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL 31 SECOND) WHERE token_hash=?',[hash('sha256',$newListen['token'])]);
$assert($creator->finishListening($newListen['token'],30)['listens']===2,'Another viewer qualifying session contributes to shared popularity');
$short = $insertTrack(1,'Short', 'local',8);
$shortListen = $creator->beginListening($short);
$assert($shortListen['threshold']===4.0,'Short track threshold is half its known duration');
db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL 5 SECOND) WHERE token_hash=?',[hash('sha256',$shortListen['token'])]);
$assert($creator->finishListening($shortListen['token'],4)['counted']===true,'Short qualifying listening works');
$expired = $creator->beginListening($short);
db()->query('UPDATE reel_listening_sessions SET expires_at=DATE_SUB(NOW(6),INTERVAL 1 SECOND) WHERE token_hash=?',[hash('sha256',$expired['token'])]);
$reject(fn()=>$creator->finishListening($expired['token'],4),'Expired listening tokens cannot count');

$repeatTrack = $insertTrack(1,'Short repeats','local',24);
$creditedRepeat = $creator->beginListening($repeatTrack);
$ignoredRepeat = $creator->beginListening($repeatTrack);
$laterRepeat = $creator->beginListening($repeatTrack);
foreach ([[$creditedRepeat,110],[$ignoredRepeat,86],[$laterRepeat,62]] as [$repeatToken,$age]) {
    db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL '.$age.' SECOND) WHERE token_hash=?',[hash('sha256',$repeatToken['token'])]);
}
$assert($creator->finishListening($creditedRepeat['token'],12)['credited']===true,'First short repeat earns a real credit');
$ignoredResult = $creator->finishListening($ignoredRepeat['token'],12);
$assert($ignoredResult['counted']===true && $ignoredResult['credited']===false && $ignoredResult['listens']===1,'Rapid duplicate is processed once without a popularity credit');
$laterResult = $creator->finishListening($laterRepeat['token'],12);
$assert($laterResult['credited']===true && $laterResult['listens']===2,'Intermediate ignored session does not block a legitimate later repeat');
$assert($creator->finishListening($ignoredRepeat['token'],12)['listens']===2,'Retrying an ignored processed token cannot gain a later credit');

if (function_exists('pcntl_fork')) {
    $raceTrack = $insertTrack(1,'Concurrent listeners');
    $raceTokens = [$creator->beginListening($raceTrack),$creator->beginListening($raceTrack)];
    foreach ($raceTokens as $raceToken) db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL 31 SECOND) WHERE token_hash=?',[hash('sha256',$raceToken['token'])]);
    // Never inherit an open PDO socket across fork. Both processes establish
    // their own connection to this disposable schema after being separated.
    $GLOBALS['catalogDb'] = null; $connection = null; $admin = null;
    $child = pcntl_fork();
    if ($child<0) throw new RuntimeException('Could not fork catalog listening check.');
    if ($child===0) {
        try {
            $GLOBALS['catalogDb'] = new \FBL\Database(new PDO($dsn.';dbname='.$schema,DB_SETTINGS['username'],DB_SETTINGS['password'],$options));
            db()->query('SET SESSION innodb_lock_wait_timeout=5');
            file_put_contents($testRoot.'/listen-child-ready',(string)db()->query('SELECT CONNECTION_ID()')->getColumn());
            $deadline = microtime(true)+8;
            while (!is_file($testRoot.'/listen-go') && microtime(true)<$deadline) usleep(1000);
            if (!is_file($testRoot.'/listen-go')) throw new RuntimeException('Listening race start timed out.');
            $result = (new Catalog(1,true))->finishListening($raceTokens[0]['token'],30);
            file_put_contents($testRoot.'/listen-result',json_encode($result,JSON_THROW_ON_ERROR));
            exit(0);
        } catch (Throwable $error) { file_put_contents($testRoot.'/listen-error',$error->getMessage()); exit(1); }
    }
    $admin = new PDO($dsn,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);
    $GLOBALS['catalogDb'] = new \FBL\Database(new PDO($dsn.';dbname='.$schema,DB_SETTINGS['username'],DB_SETTINGS['password'],$options));
    db()->query('SET SESSION innodb_lock_wait_timeout=5');
    $deadline = microtime(true)+8;
    while (!is_file($testRoot.'/listen-child-ready') && microtime(true)<$deadline) usleep(1000);
    $childConnection = (int)@file_get_contents($testRoot.'/listen-child-ready');
    db()->beginTransaction();
    try {
        db()->query('SELECT id FROM reel_tracks WHERE id=? FOR UPDATE',[$raceTrack])->getOne();
        file_put_contents($testRoot.'/listen-go','go'); $overlapped = false;
        do {
            foreach ($admin->query('SHOW PROCESSLIST')->fetchAll() as $process) {
                if ((int)$process['Id']===$childConnection && str_contains((string)($process['Info'] ?? ''),'SELECT id FROM reel_tracks WHERE id=') && str_contains((string)$process['Info'],'FOR UPDATE')) { $overlapped = true; break; }
            }
            if (!$overlapped) usleep(1000);
        } while (!$overlapped && microtime(true)<$deadline);
        $assert($overlapped,'Concurrent token finish waits on shared track lock');
        db()->commit();
        $raceResult = $creator->finishListening($raceTokens[1]['token'],30);
    } finally {
        if (db()->inTransaction()) db()->rollBack();
        pcntl_waitpid($child,$childStatus);
    }
    $childResult = json_decode((string)@file_get_contents($testRoot.'/listen-result'),true);
    $assert(pcntl_wexitstatus($childStatus)===0 && ($childResult['counted'] ?? false),'Concurrent listener finishes without deadlock or duplicate write');
    $assert($raceResult['listens']===1 && (int)db()->query('SELECT listens FROM reel_track_stats WHERE track_id=?',[$raceTrack])->getColumn()===1,'Different overlapping tokens count one actual user/song listen');
}

$creator->action('album.publish',['id'=>$album,'published'=>0]);
$reject(fn()=>$viewer->accessibleTrack($first),'Unpublication immediately revokes new shared playback lookups');
$reject(fn()=>$viewer->beginListening($first),'Unpublication revokes new listening sessions');
$reject(fn()=>$viewerLibrary->add($playlist,$first),'Existing private playlist does not bypass revoked sharing');
$assert(!in_array($first,array_column($viewer->state()['tracks'],'id'),true),'Unpublished foreign metadata disappears from catalog despite old saves/playlists');
$creator->action('album.publish',['id'=>$album,'published'=>1]);
$single = $creator->action('album.create',['title'=>'One track','tracks'=>[$short]]);
$creator->action('album.publish',['id'=>$single,'published'=>1]);
$library->action('track.delete',['id'=>$short]);
$assert((int)$creator->album($single)['published']===0,'Deleting last original track withdraws its empty published album');

$directory = STORAGE.'/reel-player/1'; mkdir($directory,0700,true);
$trackCover = '1/'.bin2hex(random_bytes(20)).'.png'; file_put_contents(STORAGE.'/reel-player/'.$trackCover,'test track cover');
db()->query('UPDATE reel_tracks SET cover_path=?,cover_mime=? WHERE id=?',[$trackCover,'image/png',$first]);
$assert($viewer->albumCover($album)['path']===$trackCover,'Album cover falls back to a guarded member cover');
$coverState = array_values(array_filter($viewer->state()['albums'],static fn(array $a):bool=>$a['id']===$album))[0];
$assert(str_contains($coverState['cover'],'/catalog/album-cover/'.$album.'?v=') && !str_contains($coverState['cover'],'1/'),'Album cover URL carries revision without private owner path');
$albumCover = '1/'.bin2hex(random_bytes(20)).'.png'; file_put_contents(STORAGE.'/reel-player/'.$albumCover,'test album cover');
db()->query('UPDATE reel_albums SET cover_path=?,cover_mime=? WHERE id=?',[$albumCover,'image/png',$album]);
$assert($viewer->albumCover($album)['path']===$albumCover,'Explicit album cover wins over member image');
$creator->action('album.delete',['id'=>$album]);
$assert(is_file(STORAGE.'/reel-player/'.$trackCover) && !is_file(STORAGE.'/reel-player/'.$albumCover),'Deleting album cleans only its own cover and preserves original song cover');
$assert($library->track($first)['id']==$first && $library->track($second)['id']==$second,'Deleting album preserves original audio rows and Drive references');
$reject(fn()=>$viewer->accessibleTrack($first),'Deleting the last published album revokes shared source access');
$assert((int)db()->query('SELECT COUNT(*) FROM reel_album_tracks WHERE album_id=?',[$album])->getColumn()===0,'Album memberships cascade on deletion');
$assert((int)db()->query('SELECT COUNT(*) FROM reel_album_saves WHERE album_id=?',[$album])->getColumn()===0,'Album saves cascade on deletion');
echo "Passed {$checks} isolated Tape Room catalog checks: migrations, ownership, publication, metadata privacy, saves, favorite/playlist references, ordering, release dates, source owners, listens and cover cleanup.\n";
