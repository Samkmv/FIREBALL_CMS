<?php
declare(strict_types=1);
// Explicit maintenance check; reads only the named owner's existing library.
// --check-drive refreshes the existing Google authorization and warms its prefix.
if (PHP_SAPI !== 'cli') exit;
$options = getopt('', ['owner:', 'check-drive', 'check-quality']);
$owner = filter_var($options['owner'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$owner) { fwrite(STDERR,"Usage: php tests/tape-room-live-recovery.php --owner=ID [--check-drive] [--check-quality]\n"); exit(2); }
$project = dirname(__DIR__);
$temporary = $project.'/tests/.tmp/tape-room-recovery-'.bin2hex(random_bytes(8));
mkdir($temporary.'/sessions',0700,true); mkdir($temporary.'/cache',0700);
define('FIREBALL_CLI',true); define('CACHE',$temporary.'/cache'); define('ERROR_LOGS',$temporary.'/error.log');
ini_set('session.save_path',$temporary.'/sessions'); ini_set('error_log',$temporary.'/php-error.log');
register_shutdown_function(static function() use ($temporary): void {
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($items as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($temporary);
});
require $project.'/config/config.php'; require ROOT.'/vendor/autoload.php'; require HELPERS.'/helpers.php';
require ROOT.'/plugins/reel-player/Plugin.php';
\FBL\PerformanceProfiler::start();
$app=new \FBL\Application(false); $app->db=new \FBL\Database();
$user=db()->query('SELECT id,role FROM users WHERE id=?',[$owner])->getOne();
if (!$user || !in_array($user['role'],['creator','admin'],true)) throw new RuntimeException('Owner must be an existing Tape Room administrator.');
$library=new \Fireball\ReelPlayer\Repositories\Library($owner);
$state=$library->state(); $local=0; $present=0; $bytesMatch=0; $cloud=[];
$storage=new \Fireball\ReelPlayer\Services\MediaStorage();
foreach($state['tracks'] as $track) {
    $row=$library->track($track['id']);
    if ($row['source']==='drive') { $cloud[]=$row; continue; }
    $local++; $path=$storage->path($row['file_path']);
    if (is_file($path)) { $present++; if (filesize($path)===(int)$row['file_size']) $bytesMatch++; }
}
$drive=new \Fireball\ReelPlayer\Services\GoogleDrive($owner,null,$user['role']==='creator');
$status=$drive->status();
$result=['owner'=>$owner,'tracks'=>count($state['tracks']),'localTracks'=>$local,'localFilesPresent'=>$present,'localSizesMatch'=>$bytesMatch,
    'driveTracks'=>count($cloud),'driveConfigured'=>$status['configured'],'driveConnected'=>$status['connected']];
if (array_key_exists('check-drive',$options) && $cloud) {
    $row=$cloud[0];
    try {
        $metadata=$drive->file($row['source_id'],true);
        $result['liveMetadataReadable']=!empty($metadata['size']);
        $result['liveMime']=$metadata['mimeType'];
        $prepared=$drive->prepare($row['source_id']);
        $result['liveAudioPrefixReady']=(bool)($prepared['ready']??false);
        $result['liveAudioPrefixBytes']=(int)($prepared['bytes']??0);
        $result['liveAudioPrefixReason']=$prepared['reason']??null;
    } catch (Throwable $error) {
        // Never include Google response bodies, file identifiers or credentials.
        $result['liveDriveErrorType']=get_class($error); $result['liveDriveErrorCode']=$error->getCode();
        $result['liveDriveNeedsReconnect']=str_contains($error->getMessage(),'снова')||str_contains($error->getMessage(),'Переподключите');
        fwrite(STDERR,"Google Drive live read did not complete.\n");
    }
}
if (array_key_exists('check-quality',$options) && $cloud) {
    try {
        $result['liveQuality']=$drive->quality($cloud[0]['source_id'],(float)$cloud[0]['duration']);
        $result['liveQualityHeaderReadable']=isset($result['liveQuality']['sampleRate']);
    } catch (Throwable $error) {
        $result['liveQualityErrorType']=get_class($error); $result['liveQualityErrorCode']=$error->getCode();
        fwrite(STDERR,"Google Drive technical metadata read did not complete.\n");
    }
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
exit(($present===$local&&$bytesMatch===$local&&!isset($result['liveDriveErrorType'])&&!isset($result['liveQualityErrorType']))?0:1);
