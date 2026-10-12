<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$temporary = dirname(__DIR__,3) . '/tests/.tmp';
if (!is_dir($temporary)) mkdir($temporary,0700,true);
$testRoot = $temporary . '/reel-repository-' . bin2hex(random_bytes(8));
mkdir($testRoot,0700); mkdir($testRoot.'/cache',0700); mkdir($testRoot.'/sessions',0700); mkdir($testRoot.'/data',0700);
define('CACHE',$testRoot.'/cache'); define('ERROR_LOGS',$testRoot.'/error.log');
define('STORAGE',$testRoot.'/data');
ini_set('session.save_path',$testRoot.'/sessions'); ini_set('upload_tmp_dir',$testRoot); ini_set('error_log',$testRoot.'/php-error.log');
$testProcess = getmypid();
register_shutdown_function(static function() use ($testRoot,$testProcess): void {
    if (getmypid() !== $testProcess) return;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!is_dir($testRoot)) return;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($testRoot);
});
define('FIREBALL_CLI', true);
require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();
$app = new \FBL\Application(false);
$app->bootInstalledServices();
require_once dirname(__DIR__) . '/Plugin.php';
use Fireball\ReelPlayer\Repositories\Library;
$owner = (int)db()->query("SELECT id FROM users WHERE role = 'creator' ORDER BY id LIMIT 1")->getColumn();
if (!$owner) throw new RuntimeException('A creator account is required for this integration test.');
$library = new Library($owner);
$foreign = new Library(PHP_INT_MAX);
$checks = 0; $playlists = []; $tracks = []; $extraTracks = [];
$assert = static function (bool $ok,string $message) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($message); };
$rejected = static function (callable $fn,string $message) use ($assert): void {
    try { $fn(); } catch (InvalidArgumentException) { $assert(true,$message); return; }
    $assert(false,$message);
};
try {
    $p = $library->action('playlist.create',['name'=>'__reel_test_' . bin2hex(random_bytes(8))]); $playlists[]=$p;
    foreach (['First', 'Second'] as $title) {
        db()->query('INSERT INTO reel_tracks (owner_id,title,artist,filename,mime,file_size,created_at) VALUES (?,?,?,?,?,?,?)',[$owner,'__reel_test_'.$title,'Test','test.wav','audio/wav',10,date('Y-m-d H:i:s')]);
        $tracks[]=(int)db()->getInsertId();
    }
    $rejected(fn()=>$foreign->track($tracks[0]),'Other owners cannot read tracks');
    $rejected(fn()=>$foreign->playlist($p),'Other owners cannot read playlists');
    $rejected(fn()=>$foreign->action('track.delete',['id'=>$tracks[0]]),'Other owners cannot delete tracks');
    $rejected(fn()=>$foreign->action('track.favorite',['id'=>$tracks[0],'favorite'=>1]),'Other owners cannot favorite tracks');
    $rejected(fn()=>$foreign->action('track.duration',['id'=>$tracks[0],'duration'=>22]),'Other owners cannot change durations');
    $rejected(fn()=>$foreign->action('playlist.add',['id'=>$p,'track_id'=>$tracks[0]]),'Other owners cannot add membership');
    foreach ($tracks as $track) $library->add($p,$track);
    $library->add($p,$tracks[0]);
    $ids = static fn(): array => array_map('intval',array_column(db()->query('SELECT track_id FROM reel_playlist_tracks WHERE playlist_id=? ORDER BY position',[$p])->get(),'track_id'));
    $assert($ids()===$tracks,'Playlist order and duplicate prevention');
    // Repeated Drive import preserves edits and joins the existing library entry.
    $driveFile = ['id'=>'__reel_test_'.bin2hex(random_bytes(8)),'name'=>'Test - Original.mp3','mimeType'=>'audio/mpeg','size'=>123];
    $assert($library->importDrive([$driveFile],$p)===1,'Drive import adds one requested file');
    $driveId = (int)db()->query('SELECT id FROM reel_tracks WHERE owner_id=? AND source=? AND source_id=?',[$owner,'drive',$driveFile['id']])->getColumn();
    $extraTracks[] = $driveId;
    $library->action('track.update',['id'=>$driveId,'title'=>'__reel_test_Edited Drive title','artist'=>'Edited artist','duration'=>12]);
    $library->action('track.favorite',['id'=>$driveId,'favorite'=>1]);
    $library->importDrive([$driveFile],$p); $again = $library->track($driveId);
    $assert($again['title']==='__reel_test_Edited Drive title' && (int)$again['favorite']===1 && (float)$again['duration']===12.0,'Duplicate Drive import preserves metadata and favorites');
    $assert((int)db()->query('SELECT COUNT(*) FROM reel_playlist_tracks WHERE playlist_id=? AND track_id=?',[$p,$driveId])->getColumn()===1,'Duplicate Drive import does not duplicate playlist membership');
    $library->action('playlist.remove',['id'=>$p,'track_id'=>$driveId]);
    $library->action('playlist.reorder',['id'=>$p,'tracks'=>array_reverse($tracks)]);
    $assert($ids()===array_reverse($tracks),'Reorder persists');
    $rejected(fn()=>$library->action('playlist.reorder',['id'=>$p,'tracks'=>[$tracks[0],$tracks[0]]]),'Reject duplicate reorder IDs');
    $assert($ids()===array_reverse($tracks),'Rejected reorder is atomic');
    $rejected(fn()=>$library->action('playlist.rename',['id'=>$p,'name'=>'']),'Reject empty names');
    $library->action('playlist.rename',['id'=>$p,'name'=>'__reel_test_🎵']);
    $assert($library->playlist($p)['name']==='__reel_test_🎵','Unicode playlist metadata');
    $library->action('track.update',['id'=>$tracks[0],'title'=>'__reel_test_New title','artist'=>'Artist','duration'=>48.25]);
    $assert((float)$library->track($tracks[0])['duration']===48.25,'Track metadata and duration persist');
    $coverDirectory = STORAGE.'/reel-player/'.$owner; mkdir($coverDirectory,0700,true);
    $coverPath = $owner.'/'.bin2hex(random_bytes(20)).'.png';
    file_put_contents(STORAGE.'/reel-player/'.$coverPath,'dummy cover bytes');
    db()->query('UPDATE reel_tracks SET cover_path=?,cover_mime=? WHERE id=? AND owner_id=?',[$coverPath,'image/png',$tracks[0],$owner]);
    $library->action('track.update',['id'=>$tracks[0],'artist'=>'Artist']);
    $assert($library->track($tracks[0])['cover_path']===$coverPath && is_file(STORAGE.'/reel-player/'.$coverPath),'Metadata edit preserves the current private cover');
    $library->action('track.favorite',['id'=>$tracks[0],'favorite'=>1]);
    $stateTrack = array_values(array_filter($library->state()['tracks'],static fn(array $track): bool => $track['id'] === $tracks[0]))[0];
    $assert($stateTrack['favorite'] === true,'Favorites persist and are returned as booleans');
    $library->action('track.favorite',['id'=>$tracks[0],'favorite'=>1]);
    $assert((int)$library->track($tracks[0])['favorite'] === 1,'Setting favorite twice is idempotent');
    $library->action('track.duration',['id'=>$tracks[0],'duration'=>17.5]);
    $durationTrack = $library->track($tracks[0]);
    $assert((float)$durationTrack['duration'] === 17.5 && $durationTrack['title'] === '__reel_test_New title' && (int)$durationTrack['favorite'] === 1,'Duration-only writes preserve title and favorite');
    $library->action('track.favorite',['id'=>$tracks[0],'favorite'=>0]);
    $assert((int)$library->track($tracks[0])['favorite'] === 0,'Favorites can be removed');
    $rejected(fn()=>$library->action('track.favorite',['id'=>$tracks[0],'favorite'=>'yes']),'Reject invalid favorite flags');
    $rejected(fn()=>$library->action('track.duration',['id'=>$tracks[0],'duration'=>'unknown']),'Reject invalid metadata duration');
    $rejected(fn()=>$library->action('track.update',['id'=>$tracks[0],'duration'=>-1]),'Reject negative duration');
    $library->action('playlist.remove',['id'=>$p,'track_id'=>$tracks[0]]);
    $assert($ids()===[$tracks[1]] && $library->track($tracks[0])['id']==$tracks[0],'Removing membership keeps original track');
    $library->action('playlist.delete',['id'=>$p]);
    $assert($library->track($tracks[1])['id']==$tracks[1],'Deleting playlist keeps music');
    $library->action('track.delete',['id'=>$tracks[0]]);
    $rejected(fn()=>$library->track($tracks[0]),'Deleting track removes the library entry');
    $assert(!is_file(STORAGE.'/reel-player/'.$coverPath),'Deleting a track removes its current private cover');
    if (function_exists('pcntl_fork')) {
        // An uncommitted matching row forces the second import to overlap: a
        // SELECT-then-INSERT implementation sees no row and fails after commit.
        // Each process opens its own connection; no PDO socket crosses fork.
        session()->close(); $app->db = null;
        $raceFile = ['id'=>'__reel_test_race_'.bin2hex(random_bytes(8)),'name'=>'Test - Race.mp3','mimeType'=>'audio/mpeg','size'=>123];
        $pid = pcntl_fork();
        if ($pid < 0) throw new RuntimeException('Cannot fork Drive import test');
        if ($pid === 0) {
            try {
                $app->db = new \FBL\Database();
                file_put_contents($testRoot.'/import-connection',(string)db()->query('SELECT CONNECTION_ID()')->getColumn());
                $deadline = microtime(true)+5;
                while (!is_file($testRoot.'/import-go') && microtime(true)<$deadline) usleep(1000);
                if (!is_file($testRoot.'/import-go')) throw new RuntimeException('Import test start timed out');
                $library->importDrive([$raceFile]);
                file_put_contents($testRoot.'/import-result',json_encode(['ok'=>true]));
                exit(0);
            } catch (Throwable $error) {
                file_put_contents($testRoot.'/import-result',json_encode(['ok'=>false,'message'=>$error->getMessage()]));
                exit(1);
            }
        }
        $app->db = new \FBL\Database();
        db()->beginTransaction();
        try {
            db()->query('INSERT INTO reel_tracks (owner_id,title,artist,filename,mime,file_size,source,source_id,favorite,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$owner,'__reel_test_Race existing','Artist',$raceFile['name'],$raceFile['mimeType'],$raceFile['size'],'drive',$raceFile['id'],1,date('Y-m-d H:i:s')]);
            $raceId = (int)db()->getInsertId(); $tracks[] = $raceId;
            $deadline = microtime(true)+5;
            while (!is_file($testRoot.'/import-connection') && microtime(true)<$deadline) usleep(1000);
            $connection = (int)@file_get_contents($testRoot.'/import-connection');
            file_put_contents($testRoot.'/import-go','go'); $overlapped = false;
            do {
                foreach (db()->query('SHOW PROCESSLIST')->get() ?: [] as $process) {
                    if ((int)$process['Id']===$connection && str_contains((string)($process['Info'] ?? ''),'INSERT INTO reel_tracks')) { $overlapped=true; break; }
                }
                if (!$overlapped) usleep(1000);
            } while (!$overlapped && microtime(true)<$deadline);
            $assert($overlapped,'Drive imports overlap while the first matching row is uncommitted');
            db()->commit();
        } finally {
            if (db()->inTransaction()) db()->rollBack();
            pcntl_waitpid($pid,$childStatus);
        }
        $raceResult = json_decode((string)file_get_contents($testRoot.'/import-result'),true);
        $assert(($raceResult['ok'] ?? false) && pcntl_wexitstatus($childStatus)===0,'Concurrent Drive import succeeds instead of a duplicate-key failure');
        $assert((int)db()->query('SELECT COUNT(*) FROM reel_tracks WHERE owner_id=? AND source=? AND source_id=?',[$owner,'drive',$raceFile['id']])->getColumn()===1,'Concurrent Drive import creates one library entry');
        $assert($library->track($raceId)['title']==='__reel_test_Race existing' && (int)$library->track($raceId)['favorite']===1,'Concurrent duplicate import preserves existing user edits');
        // Model a replacement still being committed when delete starts. A
        // plain SELECT sees the old path, then DELETE waits and leaks the new
        // cover. FOR UPDATE waits before reading, so it deletes the new path.
        $oldCover = $owner.'/'.bin2hex(random_bytes(20)).'.png';
        $newCover = $owner.'/'.bin2hex(random_bytes(20)).'.png';
        foreach ([$oldCover,$newCover] as $path) file_put_contents(STORAGE.'/reel-player/'.$path,'dummy cover bytes');
        db()->query('INSERT INTO reel_tracks (owner_id,title,filename,mime,file_size,cover_path,cover_mime,created_at) VALUES (?,?,?,?,?,?,?,?)',[$owner,'__reel_test_Cover race','fixture.mp3','audio/mpeg',123,$oldCover,'image/png',date('Y-m-d H:i:s')]);
        $coverRaceId = (int)db()->getInsertId(); $extraTracks[] = $coverRaceId;
        $app->db = null; $pid = pcntl_fork();
        if ($pid < 0) throw new RuntimeException('Cannot fork cover cleanup test');
        if ($pid === 0) {
            try {
                $app->db = new \FBL\Database();
                file_put_contents($testRoot.'/cover-connection',(string)db()->query('SELECT CONNECTION_ID()')->getColumn());
                $deadline = microtime(true)+5;
                while (!is_file($testRoot.'/cover-go') && microtime(true)<$deadline) usleep(1000);
                if (!is_file($testRoot.'/cover-go')) throw new RuntimeException('Cover test start timed out');
                $library->action('track.delete',['id'=>$coverRaceId]);
                file_put_contents($testRoot.'/cover-result',json_encode(['ok'=>true]));
                exit(0);
            } catch (Throwable $error) {
                file_put_contents($testRoot.'/cover-result',json_encode(['ok'=>false,'message'=>$error->getMessage()]));
                exit(1);
            }
        }
        $app->db = new \FBL\Database(); db()->beginTransaction();
        try {
            db()->query('UPDATE reel_tracks SET cover_path=? WHERE id=? AND owner_id=?',[$newCover,$coverRaceId,$owner]);
            $deadline = microtime(true)+5;
            while (!is_file($testRoot.'/cover-connection') && microtime(true)<$deadline) usleep(1000);
            $connection = (int)@file_get_contents($testRoot.'/cover-connection');
            file_put_contents($testRoot.'/cover-go','go'); $overlapped = false;
            do {
                foreach (db()->query('SHOW PROCESSLIST')->get() ?: [] as $process) {
                    if ((int)$process['Id']===$connection && str_contains((string)($process['Info'] ?? ''),'reel_tracks')) { $overlapped=true; break; }
                }
                if (!$overlapped) usleep(1000);
            } while (!$overlapped && microtime(true)<$deadline);
            $assert($overlapped,'Delete overlaps with an uncommitted cover replacement');
            db()->commit();
            // The replacement request cleans its own old cover after commit.
            if (is_file(STORAGE.'/reel-player/'.$oldCover)) unlink(STORAGE.'/reel-player/'.$oldCover);
        } finally {
            if (db()->inTransaction()) db()->rollBack();
            pcntl_waitpid($pid,$childStatus);
        }
        $coverResult = json_decode((string)file_get_contents($testRoot.'/cover-result'),true);
        $assert(($coverResult['ok'] ?? false) && pcntl_wexitstatus($childStatus)===0,'Delete succeeds after concurrent cover replacement commits');
        $assert(!is_file(STORAGE.'/reel-player/'.$newCover),'Concurrent delete removes the latest cover instead of leaking it');
        $rejected(fn()=>$library->track($coverRaceId),'Concurrent delete removes only its own test track');
    }
    echo "Passed {$checks} repository integration checks.\n";
} finally {
    foreach ($playlists as $id) db()->query('DELETE FROM reel_playlists WHERE id=? AND owner_id=?',[$id,$owner]);
    foreach (array_merge($tracks,$extraTracks) as $id) db()->query('DELETE FROM reel_tracks WHERE id=? AND owner_id=?',[$id,$owner]);
}
