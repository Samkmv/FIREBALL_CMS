<?php
declare(strict_types=1);
// Separate PHP processes and pipe backpressure reproduce a slow listener.
$project=dirname(__DIR__);require $project.'/vendor/autoload.php';
function return_translation(string $key): string{return $key;}
final class StreamDatabase extends FBL\Database {
    public function __construct(){ $this->connection=new PDO('sqlite::memory:'); }
}
if(($argv[1]??'')==='--worker'){
    $scratch=realpath($argv[2]);$mode=$argv[3];
    if(!$scratch || !str_starts_with($scratch,__DIR__.'/.tmp/update-streaming-'))throw new RuntimeException('Unsafe scratch path.');
    define('ROOT',$scratch);define('STORAGE',ROOT.'/storage');
    ini_set('session.save_path',ROOT.'/sessions');ini_set('error_log',ROOT.'/worker-errors.log');
    if($mode==='gate'){require ROOT.'/public/runtime-gate.php';echo 'application ran';exit;}
    $GLOBALS['fireball_runtime_lock']=fopen(STORAGE.'/update.lock','c+');flock($GLOBALS['fireball_runtime_lock'],LOCK_SH);
    if($mode==='reader'||$mode==='poll'){
        file_put_contents(ROOT.'/ready-'.$mode,'1');
        $deadline=microtime(true)+10;
        while(microtime(true)<$deadline && !is_file(ROOT.'/stop-'.$mode)){
            if($mode==='poll' && FBL\UpdateLock::pending(STORAGE)){echo 'yielded';exit;}
            usleep(10000);
        }
        echo 'finished';exit;
    }
    session_start();$_SESSION['saved']='before-stream';
    $GLOBALS['stream_app']=(object)['db'=>new StreamDatabase()];
    function app(): object{return $GLOBALS['stream_app'];}
    function session(): object{return new class{public function close(): void{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();}};}
    function abort(string $message,int $status=404): never{throw new RuntimeException($message,$status);}
    function log_error_details(string $message,array $context=[],?Throwable $error=null): void{error_log($message.': '.($error?->getMessage()??''));}
    app()->db->query('SELECT 1')->getColumn();
    if($mode==='guard'){
        FBL\UpdateLock::stream(static function():void{
            $blocked=false;try{app()->db->query('SELECT 1');}catch(LogicException){$blocked=true;}
            echo json_encode(['sessionClosed'=>session_status()!==PHP_SESSION_ACTIVE,'databaseBlocked'=>$blocked,'readerReleased'=>!is_resource($GLOBALS['fireball_runtime_lock'])]);
        },app()->db);
    }
    spl_autoload_register(static function(string $class)use($scratch):void{
        if(!empty($GLOBALS['fireball_stream_detached']))throw new LogicException('Autoload after stream handoff: '.$class);
        $prefix='Fireball\\ReelPlayer\\Services\\';
        if(str_starts_with($class,$prefix))require $scratch.'/code/'.substr($class,strlen($prefix)).'.php';
    },true,true);
    if($mode==='drive'){
        (new Fireball\ReelPlayer\Services\GoogleDrive(1,ROOT.'/media'))->stream('fixture-drive-12345');
    }
    if($mode==='range')$_SERVER['HTTP_RANGE']='bytes=17-1048600';
    $_SERVER['REQUEST_METHOD']='GET';
    (new Fireball\ReelPlayer\Services\MediaStorage(ROOT.'/media'))->stream('1/'.str_repeat('a',40).'.flac','audio/flac');
}
$scratch=__DIR__.'/.tmp/update-streaming-'.bin2hex(random_bytes(8));mkdir($scratch,0700,true);
$checks=[];$workers=[];
function check(string $label,bool $ok):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function worker(string $mode):array{
    global $scratch,$workers;
    $process=proc_open([PHP_BINARY,__FILE__,'--worker',$scratch,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$scratch.'/worker-errors.log','a']],$pipes,null,['TMPDIR'=>$scratch]);
    if(!is_resource($process))throw new RuntimeException('Unable to start worker.');
    fclose($pipes[0]);$workers[]=$process;return [$process,$pipes[1]];
}
function firstBytes($process,$output):string{
    stream_set_blocking($output,false);$deadline=microtime(true)+5;
    do{$bytes=fread($output,128);if($bytes!==''&&$bytes!==false){stream_set_blocking($output,true);return $bytes;}
        if(!proc_get_status($process)['running'])break;usleep(10000);
    }while(microtime(true)<$deadline);
    throw new RuntimeException('Transfer did not start: '.@file_get_contents($GLOBALS['scratch'].'/worker-errors.log'));
}
function waitFile(string $file):void{$deadline=microtime(true)+5;while(!is_file($file)){if(microtime(true)>$deadline)throw new RuntimeException('Worker did not start.');usleep(10000);clearstatcache(true,$file);}}
try{
    foreach(['storage','sessions','media/1','code','public']as$dir)mkdir($scratch.'/'.$dir,0700,true);
    copy($project.'/public/runtime-gate.php',$scratch.'/public/runtime-gate.php');
    $bytes=str_repeat(hash('sha256','audio-fixture',true),65536);$file=$scratch.'/media/1/'.str_repeat('a',40).'.flac';file_put_contents($file,$bytes);
    $app=['client_id'=>'fixture.apps.googleusercontent.com','client_secret'=>'fixture-secret'];$binding=hash('sha256',$app['client_id']."\0".$app['client_secret']);
    file_put_contents($scratch.'/media/drive-app.json',json_encode($app));file_put_contents($scratch.'/media/1/drive.json',json_encode(['app_binding'=>$binding,'access_token'=>'fixture-token','expires_at'=>time()+3600]));
    require $project.'/plugins/reel-player/src/Services/DriveCache.php';
    $cache=new Fireball\ReelPlayer\Services\DriveCache($scratch.'/media',1,hash('sha256',$binding.':fixture-token'));
    $metadata=['id'=>'fixture-drive-12345','name'=>'song.flac','mimeType'=>'audio/flac','size'=>strlen($bytes),'headRevisionId'=>'fixture-revision','capabilities'=>['canDownload'=>true]];
    $cache->saveMetadata($metadata);$cache->savePrefix($metadata,$bytes);
    foreach(['file','range','drive']as$mode){
        foreach(['MediaStorage','GoogleDrive','DriveStream','DriveCache']as$class)copy($project.'/plugins/reel-player/src/Services/'.$class.'.php',$scratch.'/code/'.$class.'.php');
        [$process,$output]=worker($mode);$received=firstBytes($process,$output);
        check($mode.': a slow stream is still running',proc_get_status($process)['running']);
        $start=microtime(true);$lock=FBL\UpdateLock::acquire($scratch.'/storage',.5);
        check($mode.': updater acquires exclusive access while music is transferring',microtime(true)-$start<.5 && proc_get_status($process)['running']);
        foreach(['MediaStorage','GoogleDrive','DriveStream','DriveCache']as$class)file_put_contents($scratch.'/code/'.$class.'.php','<?php invalid replacement fixture');
        [$gate,$gateOutput]=worker('gate');$maintenance=stream_get_contents($gateOutput);fclose($gateOutput);proc_close($gate);
        check($mode.': new requests cannot enter partially replaced code',str_contains($maintenance,'data-update-maintenance-page')&&!str_contains($maintenance,'application ran'));
        $lock->release();$received.=stream_get_contents($output);fclose($output);$exit=proc_close($process);
        $expected=$mode==='range'?substr($bytes,17,1048600-17+1):$bytes;
        check($mode.': active transfer finishes byte-for-byte without further autoload',$exit===0&&hash_equals(hash('sha256',$expected),hash('sha256',$received)));
    }
    [$process,$output]=worker('guard');$guard=json_decode(stream_get_contents($output),true);fclose($output);proc_close($process);
    check('Detached callback has closed session, no DB access and no runtime reader',$guard===['sessionClosed'=>true,'databaseBlocked'=>true,'readerReleased'=>true]);
    [$process,$output]=worker('reader');waitFile($scratch.'/ready-reader');$blocked=false;
    try{FBL\UpdateLock::acquire($scratch.'/storage',.1);}catch(RuntimeException $error){$blocked=$error->getMessage()==='admin_update_requests_busy';}
    check('A real application request still blocks source replacement',$blocked);
    file_put_contents($scratch.'/stop-reader','1');stream_get_contents($output);fclose($output);proc_close($process);
    [$process,$output]=worker('poll');waitFile($scratch.'/ready-poll');$lock=FBL\UpdateLock::acquire($scratch.'/storage',.5);
    check('DB-backed long poll yields to update intent without dropping its guard early',stream_get_contents($output)==='yielded');fclose($output);proc_close($process);$lock->release();
    check('Update intent is cleared after completion',!FBL\UpdateLock::pending($scratch.'/storage'));
    $held=fopen($scratch.'/storage/update.lock','c+');flock($held,LOCK_EX);$blocked=false;
    try{FBL\UpdateLock::acquire($scratch.'/storage',0);}catch(RuntimeException $error){$blocked=$error->getMessage()==='admin_update_already_running';}
    check('Concurrent exclusive updater remains blocked',$blocked);flock($held,LOCK_UN);fclose($held);
    $db=new StreamDatabase();$db->beginTransaction();$transactionBlocked=false;
    try{$db->closeForStreaming();}catch(LogicException){$transactionBlocked=true;}
    check('An active transaction cannot detach',$transactionBlocked&&$db->inTransaction());$db->rollBack();
    define('CONFIG',$scratch.'/config');mkdir(CONFIG,0700);
    $updater=(new ReflectionClass(App\Services\PluginUpdateService::class))->newInstanceWithoutConstructor();
    $requirements=new ReflectionMethod($updater,'assertRequirements');
    $metadata=json_decode(file_get_contents($project.'/plugins/reel-player/plugin.json'),true,512,JSON_THROW_ON_ERROR);
    file_put_contents(CONFIG.'/version.php',"<?php return ['version'=>'1.8.18'];");$rejected=false;
    try{$requirements->invoke($updater,$metadata);}catch(RuntimeException $error){$rejected=$error->getMessage()==='admin_plugin_updates_cms_incompatible';}
    check('Tape Room cannot install its new stream API on an older CMS',$rejected);
    file_put_contents(CONFIG.'/version.php',"<?php return ['version'=>'1.8.19'];");$requirements->invoke($updater,$metadata);
    check('Tape Room stream requirements accept the fixed CMS',true);
    $artifact=__DIR__.'/artifacts/update-streaming';if(!is_dir($artifact))mkdir($artifact,0700,true);
    $report=['passed'=>count($checks),'checks'=>$checks];file_put_contents($artifact.'/streaming-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),"\n";
}finally{
    foreach($workers as$process)if(is_resource($process)){if(proc_get_status($process)['running'])proc_terminate($process);proc_close($process);}
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($items as$item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($scratch);
}
