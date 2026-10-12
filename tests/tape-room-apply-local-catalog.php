<?php
declare(strict_types=1);
// Explicit live maintenance, not a fixture: apply only Tape Room's pending SQL.
if (PHP_SAPI !== 'cli' || !in_array('--apply', $argv, true)) exit("Usage: php tests/tape-room-apply-local-catalog.php --apply\n");
$project=dirname(__DIR__);
$temporary=__DIR__.'/.tmp/tape-room-local-migrate-'.bin2hex(random_bytes(8));
mkdir($temporary.'/sessions',0700,true);
define('FIREBALL_CLI',true);
ini_set('session.save_path',$temporary.'/sessions');
ini_set('error_log',$temporary.'/php-error.log');
register_shutdown_function(static function() use($temporary):void {
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
    rmdir($temporary);
});
require $project.'/config/config.php'; require ROOT.'/vendor/autoload.php'; require HELPERS.'/helpers.php';
\FBL\PerformanceProfiler::start();
$app=new \FBL\Application(false); $app->db=new \FBL\Database();
$snapshot=static fn():array=>[
    db()->query('SELECT * FROM reel_tracks ORDER BY id')->get(),
    db()->query('SELECT * FROM reel_playlists ORDER BY id')->get(),
    db()->query('SELECT * FROM reel_playlist_tracks ORDER BY playlist_id,position')->get(),
    db()->query('SELECT * FROM reel_chapters ORDER BY id')->get(),
];
$before=$snapshot();
$hashes=[];
foreach($before[0] as $track) foreach(['file_path','cover_path'] as $field) if(!empty($track[$field])) {
    $path=STORAGE.'/reel-player/'.$track[$field]; if(is_file($path)) $hashes[$path]=hash_file('sha256',$path);
}
foreach(array_merge([STORAGE.'/reel-player/drive-app.json'],glob(STORAGE.'/reel-player/*/drive.json') ?: []) as $path) if(is_file($path)) $hashes[$path]=hash_file('sha256',$path);
$metadata=(new \FBL\Plugins\PluginManager(PLUGINS))->completeUpdate('reel-player');
if($snapshot()!==$before) throw new RuntimeException('Original library records changed during migration.');
foreach($hashes as $path=>$hash) if(!is_file($path) || hash_file('sha256',$path)!==$hash) throw new RuntimeException('Original runtime bytes changed during migration.');
$migrations=array_column(db()->query('SELECT migration FROM plugin_migrations WHERE plugin_slug=? ORDER BY migration',['reel-player'])->get(),'migration');
echo json_encode(['version'=>$metadata['version'],'migrations'=>$migrations,'tracksPreserved'=>count($before[0]),'playlistsPreserved'=>count($before[1]),'chaptersPreserved'=>count($before[3]),'runtimeFilesPreserved'=>count($hashes)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
