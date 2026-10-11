<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$project=dirname(__DIR__);$temporary=__DIR__.'/.tmp';
if(!is_dir($temporary))mkdir($temporary,0700,true);
$testRoot=$temporary.'/tape-room-chapters-'.bin2hex(random_bytes(8));mkdir($testRoot,0700);
mkdir($testRoot.'/sessions',0700);mkdir($testRoot.'/data',0700);mkdir($testRoot.'/cache',0700);
define('STORAGE',$testRoot.'/data');define('CACHE',$testRoot.'/cache');define('ERROR_LOGS',$testRoot.'/error.log');
ini_set('error_log',$testRoot.'/php.log');ini_set('session.save_path',$testRoot.'/sessions');ini_set('upload_tmp_dir',$testRoot);
$parent=getmypid();$schema=null;$admin=null;
register_shutdown_function(static function()use($testRoot,$parent,&$schema,&$admin):void{
    if(getmypid()!==$parent)return;
    if($schema&&$admin){try{$admin->exec('DROP DATABASE `'.$schema.'`');}catch(Throwable $error){fwrite(STDERR,'Could not remove disposable chapter database: '.$error->getMessage().PHP_EOL);}}
    if(is_dir($testRoot)){
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($testRoot);
    }
});
require $project.'/config/config.php';require $project.'/vendor/autoload.php';require $project.'/plugins/reel-player/Plugin.php';
function base_href(string $path):string{return $path;}
function log_error_details(string $message,array $context=[],?Throwable $error=null):void{error_log($message.': '.$error?->getMessage());}
function db():object{return $GLOBALS['chaptersDb'];}
use Fireball\ReelPlayer\Repositories\Chapters;
use Fireball\ReelPlayer\Repositories\Library;
use Fireball\ReelPlayer\Services\CueParser;
$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $operation,string $message)use($assert):void{
    try{$operation();}catch(InvalidArgumentException){$assert(true,$message);return;}$assert(false,$message);
};
$cue=<<<'CUE'
REM GENRE Ambient
PERFORMER "Общий исполнитель"
TITLE "Альбом"
FILE "C:\Music\СЕТ.FLAC" WAVE
  TRACK 01 AUDIO
    TITLE "Вступление"
    INDEX 01 00:00:00
  TRACK 02 AUDIO
    TITLE "Вторая часть"
    PERFORMER "Другой исполнитель"
    INDEX 00 00:59:00
    INDEX 01 01:00:74
CUE;
$parsed=CueParser::parse($cue,'сет.flac',100);
$assert($parsed['entries'][0]===['title'=>'Вступление','artist'=>'Общий исполнитель','start'=>0.0],'Global artist and Unicode Windows FILE basename');
$assert(abs($parsed['entries'][1]['start']-(60+74/75))<0.000001,'INDEX uses 75 frames per second without truncation');
$assert($parsed['entries'][1]['artist']==='Другой исполнитель','Track performer overrides global performer');
$assert(count($parsed['warnings'])===1,'INDEX 00 is ignored with a useful warning');
$assert(CueParser::parse("\xef\xbb\xbf".$cue,'сет.flac')['entries']===$parsed['entries'],'UTF-8 BOM');
foreach(['UTF-16LE'=>"\xff\xfe",'UTF-16BE'=>"\xfe\xff",'Windows-1251'=>'']as$encoding=>$bom){
    $result=CueParser::parse($bom.iconv('UTF-8',$encoding,$cue),'сет.flac');
    $assert($result['entries']===$parsed['entries'],'Encoding '.$encoding.' preserves Cyrillic and timestamps');
    $assert($result['encoding']===$encoding,'Encoding '.$encoding.' is reported');
}
$reject(fn()=>CueParser::parse($cue,'other.flac'),'Mismatched FILE is rejected');
$reject(fn()=>CueParser::parse($cue."\nFILE \"сет.flac\" WAVE",'сет.flac'),'Multiple FILE records are rejected');
$reject(fn()=>CueParser::parse(str_replace('01:00:74','01:00:75',$cue),'сет.flac'),'Frames 75 are invalid');
$reject(fn()=>CueParser::parse(str_replace('01:00:74','01:60:00',$cue),'сет.flac'),'Seconds 60 are invalid');
$reject(fn()=>CueParser::parse(str_replace('INDEX 01 01:00:74','INDEX 00 01:00:74',$cue),'сет.flac'),'Every track needs INDEX 01');
$reject(fn()=>CueParser::parse($cue."\n    INDEX 01 01:05:00",'сет.flac'),'Duplicated INDEX 01 cannot be accepted');
$reject(fn()=>CueParser::parse(str_replace('TRACK 02 AUDIO','TRACK 01 AUDIO',$cue),'сет.flac'),'Duplicate TRACK numbers are rejected');
$reject(fn()=>CueParser::parse(str_replace('TRACK 02 AUDIO','TRACK 02 MODE1/2352',$cue),'сет.flac'),'Data tracks are rejected');
$reject(fn()=>CueParser::parse(str_replace('FILE "C:\\Music\\СЕТ.FLAC" WAVE','',$cue),'сет.flac'),'Missing FILE cannot accidentally attach to a track');
$reject(fn()=>CueParser::parse($cue,'сет.flac',60),'CUE positions beyond known duration are rejected');
$reject(fn()=>CueParser::parse(str_replace('01:00:74','00:00:00',$cue),'сет.flac'),'Duplicate CUE start times are rejected');
$reject(fn()=>CueParser::parse(str_repeat('x',CueParser::MAX_BYTES+1),'сет.flac'),'CUE byte limit is enforced');
$reject(fn()=>CueParser::parse("\xff\xfe\x41",'сет.flac'),'Truncated UTF-16 is rejected');
$simple="FILE \"x.mp3\" MP3\nTRACK 01 AUDIO\nINDEX 01 00:01:00";
$simpleResult=CueParser::parse($simple,'X.MP3');
$assert($simpleResult['entries'][0]['title']==='Глава 01','Missing titles get editable chapter labels');
$assert(count($simpleResult['warnings'])===2 && str_contains(implode(' ',$simpleResult['warnings']),'Длительность') && str_contains(implode(' ',$simpleResult['warnings']),'TITLE'),'Unknown duration and placeholder names are explicitly disclosed');
$assert(!str_contains(implode(' ',CueParser::parse($simple,'X.MP3',20)['warnings']),'Длительность'),'Known duration does not claim it is unknown');
$emptyTitle=CueParser::parse(str_replace('INDEX','TITLE ""'."\nINDEX",$simple),'X.MP3',20);
$assert($emptyTitle['entries'][0]['title']==='Глава 01' && count($emptyTitle['warnings'])===1,'Empty TITLE retains an editable placeholder with a warning');
$cp=CueParser::parse(iconv('UTF-8','Windows-1251',$cue),'сет.flac',100);
$assert(str_contains(implode(' ',$cp['warnings']),'Применена кодировка Windows-1251') && !str_contains(implode(' ',$cp['warnings']),'Распознана'),'Ambiguous legacy encoding is presented as an applied fallback');
foreach([-1,INF,NAN,604801,'oops',null]as$value)$reject(fn()=>Chapters::validateEntries([['title'=>'Bad','start'=>$value]]),'Invalid start value rejected');
$reject(fn()=>Chapters::validateEntries([['title'=>'At end','start'=>10]],10),'Chapter cannot start exactly at track end');
$reject(fn()=>Chapters::validateEntries([['title'=>'A','start'=>1],['title'=>'B','start'=>1.00000001]]),'Sub-microsecond duplicate is rejected before SQL rounding');
$reject(fn()=>Chapters::validateEntries([['title'=>'','start'=>0]]),'Manual title is required');
$assert(Chapters::validateEntries([['title'=>'Later','start'=>2],['title'=>'First','start'=>0]])[0]['title']==='First','Input is normalized into playback order');
if(in_array('--parser-only',$argv,true)){echo "Passed {$checks} CUE/chapter validation checks.\n";exit;}

\FBL\PerformanceProfiler::start();
$dsn='mysql:host='.DB_SETTINGS['host'].';charset=utf8mb4';
if(!empty(DB_SETTINGS['port']))$dsn.=';port='.(int)DB_SETTINGS['port'];
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$admin=new PDO($dsn,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);
$schema='tape_chapters_test_'.bin2hex(random_bytes(8));$admin->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$connect=static function()use($dsn,$schema,$options):PDO{return new PDO($dsn.';dbname='.$schema,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);};
class ChapterTestDatabase extends \FBL\Database{
    public array $queries=[];
    public function query(string $sql,array $params=[]):static{$this->queries[]=$sql;return parent::query($sql,$params);}
}
$GLOBALS['chaptersDb']=new ChapterTestDatabase($connect());
db()->query('CREATE TABLE users(id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');db()->query('INSERT INTO users(id) VALUES(1),(2)');
foreach(glob($project.'/plugins/reel-player/migrations/*.sql')as$migration){
    foreach(explode(';',(string)file_get_contents($migration))as$sql)if(trim($sql)!=='')db()->query($sql);
}
$library=new Library(1);$repo=new Chapters(1);$foreign=new Chapters(2);
db()->query('INSERT INTO reel_tracks(owner_id,title,filename,mime,file_size,duration,created_at) VALUES(?,?,?,?,?,?,?)',[1,'Test mix','сет.flac','audio/flac',100,100,date('Y-m-d H:i:s')]);
$track=(int)db()->getInsertId();
db()->query('INSERT INTO reel_tracks(owner_id,title,filename,mime,file_size,duration,created_at) VALUES(?,?,?,?,?,?,?)',[2,'Foreign mix','other.flac','audio/flac',100,100,date('Y-m-d H:i:s')]);
$other=(int)db()->getInsertId();
$a=$library->action('chapter.create',['track_id'=>$track,'title'=>'First 🎵','artist'=>'Artist','start'=>0]);
$b=$repo->create($track,['title'=>'Second','start'=>60+74/75]);
$assert($repo->list($track)[0]['id']===$a && abs($repo->list($track)[1]['start']-(60+74/75))<0.000001,'Chapter CRUD stores Unicode and frame fractions');
$reject(fn()=>$foreign->list($track),'Foreign owner cannot read chapters');
$reject(fn()=>$foreign->create($track,['title'=>'No','start'=>2]),'Foreign owner cannot create chapters');
$reject(fn()=>$foreign->update($track,$a,['title'=>'No']),'Foreign owner cannot update chapters');
$reject(fn()=>$foreign->delete($track,$a),'Foreign owner cannot delete chapters');
$reject(fn()=>$repo->update($other,$a,['title'=>'No']),'Owned chapter id cannot be used with a foreign track');
$reject(fn()=>$repo->update($track,$b,['start'=>0]),'Update cannot duplicate another chapter start');
$repo->update($track,$a,['title'=>'Edited','artist'=>'Changed','start'=>1.25]);
$assert($repo->list($track)[0]['title']==='Edited' && $repo->list($track)[0]['artist']==='Changed','Chapter edit preserves omitted fields and updates supplied metadata');
$snapshot=$repo->list($track);$preview=$repo->preview($track,[['title'=>'Imported','artist'=>'CUE artist','start'=>5]],'replace');
$reject(fn()=>$repo->import($track,$preview['entries'],'replace',false,$preview['revision']),'Replace requires explicit confirmation');
$assert($repo->list($track)===$snapshot,'Unconfirmed replace leaves chapters unchanged');
$repo->create($track,['title'=>'Another edit','start'=>9]);
$reject(fn()=>$repo->import($track,$preview['entries'],'replace',true,$preview['revision']),'A stale preview cannot erase a concurrent edit');
$preview=$repo->preview($track,[['title'=>'Imported','artist'=>'CUE artist','start'=>5]],'replace');
$library->action('chapter.import',['track_id'=>$track,'chapters'=>json_encode($preview['entries']),'mode'=>'replace','confirmed'=>'1','revision'=>$preview['revision']]);
$assert(count($repo->list($track))===1 && $repo->list($track)[0]['title']==='Imported','Confirmed replacement commits the normalized entries');
$id=$repo->list($track)[0]['id'];$repo->import($track,[['title'=>'Merged','start'=>10]],'merge');
$assert(count($repo->list($track))===2 && $repo->list($track)[0]['id']===$id,'Merge preserves ids and metadata of existing chapters');
$snapshot=$repo->list($track);
$reject(fn()=>$repo->import($track,[['title'=>'Duplicate','start'=>5]],'merge'),'Merge rejects timestamp collisions');
$reject(fn()=>$repo->import($track,[['title'=>'Beyond','start'=>100]],'replace',true),'Out-of-duration replace is rejected atomically');
$reject(fn()=>$library->action('chapter.import',['track_id'=>$track,'chapters'=>'{bad','confirmed'=>'1']),'Malformed JSON import is rejected');
$assert($repo->list($track)===$snapshot,'Invalid imports preserve original rows');
db()->query("CREATE TRIGGER fail_chapter_test BEFORE INSERT ON reel_chapters FOR EACH ROW BEGIN IF NEW.title='Break' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='chapter test rollback'; END IF; END");
try{$repo->import($track,[['title'=>'First insert','start'=>1],['title'=>'Break','start'=>2]],'replace',true);$assert(false,'Injected second insert must fail');}
catch(PDOException){$assert($repo->list($track)===$snapshot,'SQL failure after DELETE and partial INSERT rolls back the whole replacement');}
db()->query('DROP TRIGGER fail_chapter_test');
$reject(fn()=>$library->action('track.duration',['id'=>$track,'duration'=>10]),'Duration updates cannot strand existing chapters beyond track end');
$reject(fn()=>$library->action('track.update',['id'=>$track,'duration'=>10]),'Metadata edits cannot shorten duration past existing chapters');
$assert((float)$library->track($track)['duration']===100.0,'Rejected duration conflict is rolled back');
$before=count(db()->queries);$state=$library->state();$stateQueries=array_slice(db()->queries,$before);
$assert(count($stateQueries)===4 && count(array_filter($stateQueries,static fn(string $sql):bool=>str_contains($sql,'reel_chapters')))===1,'Library state fetches all chapters with one aggregate query');
$stateTrack=array_values(array_filter($state['tracks'],static fn(array $row):bool=>$row['id']===$track))[0];
$assert($stateTrack['chapters']===$repo->list($track),'Library JSON includes typed chapter metadata');
$assert(!isset($repo->all()[$other]),'Aggregate chapter lookup is owner-filtered');
$repo->delete($track,$id);$assert(count($repo->list($track))===1,'Chapter delete removes only the selected chapter');
$reject(fn()=>$repo->delete($track,$id),'Already deleted chapter cannot be deleted again');
$library->action('track.duration',['id'=>$track,'duration'=>0]);
$many=array_map(static fn(int $index):array=>['title'=>'Chapter '.$index,'start'=>$index],range(0,999));
$repo->import($track,$many,'replace',true);$assert(count($repo->list($track))===1000,'Exactly 1000 chapters is accepted');
$reject(fn()=>$repo->create($track,['title'=>'Over cap','start'=>1001]),'Creating beyond the chapter cap is rejected');
$reject(fn()=>$repo->import($track,[...$many,['title'=>'Over cap','start'=>1001]],'replace',true),'Oversized import is rejected before destructive changes');
$repo->import($track,[],'replace',true);$assert($repo->list($track)===[],'Confirmed empty replacement can clear chapters');
$library->action('track.duration',['id'=>$track,'duration'=>100]);
if(function_exists('pcntl_fork')){
    $GLOBALS['chaptersDb']=null;$admin=null;$pid=pcntl_fork();
    if($pid<0)throw new RuntimeException('Cannot fork chapter lock test');
    if($pid===0){
        try{
            $GLOBALS['chaptersDb']=new ChapterTestDatabase($connect());
            file_put_contents($testRoot.'/child-connection',(string)db()->query('SELECT CONNECTION_ID()')->getColumn());
            $deadline=microtime(true)+5;while(!is_file($testRoot.'/child-go')&&microtime(true)<$deadline)usleep(1000);
            if(!is_file($testRoot.'/child-go'))throw new RuntimeException('Lock test start timed out');
            try{(new Chapters(1))->create($track,['title'=>'After duration race','start'=>40]);file_put_contents($testRoot.'/child-result','created');}
            catch(InvalidArgumentException){file_put_contents($testRoot.'/child-result','rejected');}
            exit(0);
        }catch(Throwable $error){file_put_contents($testRoot.'/child-result','failed:'.$error->getMessage());exit(1);}
    }
    $admin=new PDO($dsn,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);$GLOBALS['chaptersDb']=new ChapterTestDatabase($connect());
    db()->beginTransaction();
    try{
        db()->query('UPDATE reel_tracks SET duration=20 WHERE id=?',[$track]);
        $deadline=microtime(true)+5;while(!is_file($testRoot.'/child-connection')&&microtime(true)<$deadline)usleep(1000);
        $connection=(int)file_get_contents($testRoot.'/child-connection');file_put_contents($testRoot.'/child-go','go');$overlapped=false;
        do{
            foreach(db()->query('SHOW PROCESSLIST')->get()as$process)if((int)$process['Id']===$connection&&str_contains((string)($process['Info']??''),'FOR UPDATE')){$overlapped=true;break;}
            if(!$overlapped)usleep(1000);
        }while(!$overlapped&&microtime(true)<$deadline);
        $assert($overlapped,'Chapter creation waits on the same track lock as duration/deletion');db()->commit();
    }finally{if(db()->inTransaction())db()->rollBack();pcntl_waitpid($pid,$status);}
    $assert(pcntl_wexitstatus($status)===0&&file_get_contents($testRoot.'/child-result')==='rejected','Waiting chapter creation validates the newly committed shorter duration');
    $assert($repo->list($track)===[],'Duration race does not create invalid chapters');
}
$repo->create($track,['title'=>'Cascade test','start'=>1]);$library->action('track.delete',['id'=>$track]);
$assert((int)db()->query('SELECT COUNT(*) FROM reel_chapters WHERE track_id=?',[$track])->getColumn()===0,'Track deletion cascades chapters without touching audio behavior');
echo "Passed {$checks} chapter/CUE checks in disposable MySQL database.\n";
