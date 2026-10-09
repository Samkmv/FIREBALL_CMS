<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
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
$checks = 0; $playlists = []; $tracks = [];
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
    $library->action('playlist.reorder',['id'=>$p,'tracks'=>array_reverse($tracks)]);
    $assert($ids()===array_reverse($tracks),'Reorder persists');
    $rejected(fn()=>$library->action('playlist.reorder',['id'=>$p,'tracks'=>[$tracks[0],$tracks[0]]]),'Reject duplicate reorder IDs');
    $assert($ids()===array_reverse($tracks),'Rejected reorder is atomic');
    $rejected(fn()=>$library->action('playlist.rename',['id'=>$p,'name'=>'']),'Reject empty names');
    $library->action('playlist.rename',['id'=>$p,'name'=>'__reel_test_🎵']);
    $assert($library->playlist($p)['name']==='__reel_test_🎵','Unicode playlist metadata');
    $library->action('track.update',['id'=>$tracks[0],'title'=>'__reel_test_New title','artist'=>'Artist','duration'=>48.25]);
    $assert((float)$library->track($tracks[0])['duration']===48.25,'Track metadata and duration persist');
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
    echo "Passed {$checks} repository integration checks.\n";
} finally {
    foreach ($playlists as $id) db()->query('DELETE FROM reel_playlists WHERE id=? AND owner_id=?',[$id,$owner]);
    foreach ($tracks as $id) db()->query('DELETE FROM reel_tracks WHERE id=? AND owner_id=?',[$id,$owner]);
}
