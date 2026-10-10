<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/vendor/autoload.php';
require dirname(__DIR__).'/Plugin.php';
use Fireball\ReelPlayer\Services\DriveCache;
use Fireball\ReelPlayer\Services\DriveStream;
use Fireball\ReelPlayer\Services\GoogleDrive;
$checks = 0;
$assert = static function(bool $value,string $label) use (&$checks): void { $checks++; if (!$value) throw new RuntimeException($label); };
$reject = static function(callable $operation,int $code,string $label) use ($assert): void { try { $operation(); } catch (RuntimeException $e) { $assert($e->getCode() === $code,$label); return; } $assert(false,$label); };
$root = sys_get_temp_dir().'/reel-stream-'.bin2hex(random_bytes(8)); mkdir($root,0700);
$account = hash('sha256','test-account'); $cache = new DriveCache($root,1,$account);
$file = ['id'=>'fixture_audio_001','name'=>'Fixture.mp3','size'=>(string)(DriveCache::PREFIX_BYTES+1000),'mimeType'=>'audio/mpeg','headRevisionId'=>'fixture-revision'];
$bytes = str_repeat('0123456789abcdef',intdiv((int)$file['size']+15,16)); $bytes = substr($bytes,0,(int)$file['size']);
$calls = []; $mode = 206;
$reader = static function(array $file,int $start,int $end,bool $warming,callable $head,callable $body) use (&$calls,&$mode,$bytes): void {
    $calls[] = [$start,$end,$warming];
    if ($mode === 206) { $head(206,['content-range'=>"bytes {$start}-{$end}/{$file['size']}",'content-length'=>(string)($end-$start+1)]); $data=substr($bytes,$start,$end-$start+1); }
    else { $head($mode,['content-length'=>(string)strlen($bytes)]); $data=$bytes; }
    for ($i=0;$i<strlen($data);$i+=57341) if ($body(substr($data,$i,57341)) === false) break;
};
$valid = true; $pipe = new DriveStream($cache,static function() use (&$valid): bool { return $valid; });
$serve = static function(string $range) use ($pipe,&$file,$reader): array {
    $body=''; $status=0; $fields=[];
    $pipe->serve($file,$range,$reader,static function(int $code,array $headers) use (&$status,&$fields): void {$status=$code;$fields=$headers;},static function(string $chunk) use (&$body): void {$body.=$chunk;});
    return [$status,$fields,$body];
};
try {
    $cache->saveMetadata($file); $assert($cache->metadata($file['id']) === $file,'Cached metadata matches source');
    $assert((new DriveCache($root,2,$account))->metadata($file['id']) === null,'Metadata isolated between users');
    $assert((new DriveCache($root,1,hash('sha256','another-account')))->metadata($file['id']) === null,'Metadata isolated between Google connections');
    $result=$pipe->prepare($file,$reader); $assert($result['ready'] && $result['bytes'] === DriveCache::PREFIX_BYTES,'Warm exactly 2 MiB');
    $assert($calls === [[0,DriveCache::PREFIX_BYTES-1,true]],'Warm sends a bounded Range');
    $path=$cache->prefix($file); $assert(filesize($path) === DriveCache::PREFIX_BYTES,'Bounded disk prefix');
    $assert((fileperms($path)&0777)===0600,'Private cache file permissions');
    $assert((fileperms(dirname($path))&0777)===0700,'Private cache directory permissions');
    $assert((new DriveCache($root,2,$account))->prefix($file)===null,'Audio isolated between users');
    $assert((new DriveCache($root,1,hash('sha256','another-account')))->prefix($file)===null,'Audio isolated between Google connections');
    $pipe->prepare($file,$reader); $assert(count($calls)===1,'Repeated warm reuses prefix without network');
    [$code,$headers,$data]=$serve('bytes=0-1'); $assert($code===206 && $data===substr($bytes,0,2) && $headers['Content-Range']==='bytes 0-1/'.$file['size'],'Safari two-byte probe from cache');
    $assert(count($calls)===1,'Cached range does not contact Google');
    [$code,$headers,$data]=$serve('bytes='.(DriveCache::PREFIX_BYTES-19).'-'.(DriveCache::PREFIX_BYTES+22));
    $assert(strlen($data)===42 && $data===substr($bytes,DriveCache::PREFIX_BYTES-19,42),'Cache-tail boundary has no repeated or missing bytes');
    $assert(end($calls)===[DriveCache::PREFIX_BYTES,DriveCache::PREFIX_BYTES+22,false],'Tail starts after cached prefix');
    [$code,$headers,$data]=$serve(''); $assert($code===200 && $data===$bytes && (int)$headers['Content-Length']===strlen($bytes),'Full response combines exact bytes');
    [$code,$headers,$data]=$serve('bytes=-11'); $assert($code===206 && $data===substr($bytes,-11),'Suffix seeking');
    $before=count($calls); [$code,$headers,$data]=$serve('bytes='.$file['size'].'-'); $assert($code===416 && $data==='' && $headers['Content-Range']==='bytes */'.$file['size'] && count($calls)===$before,'Unsatisfiable range does not open upstream');
    $other=[...$file,'headRevisionId'=>'new-revision']; $assert($cache->prefix($other)===null,'Changed revision never reuses old bytes');
    $assert(!$pipe->prepare([...$file,'headRevisionId'=>''],$reader)['ready'],'Mutable file without revision is not spliced');
    $mode=200; $cache->invalidate($file['id'],false); $before=count($calls); $pipe->prepare($file,$reader); $assert(filesize($cache->prefix($file))===DriveCache::PREFIX_BYTES,'Ignored upstream Range clipped to cache limit');
    [$code,$headers,$data]=$serve('bytes=9-21'); $assert($data===substr($bytes,9,13),'200 full-body fallback serves only requested bytes');
    $reject(fn()=>DriveStream::response(200,['content-length'=>(string)$file['size']],DriveCache::PREFIX_BYTES+1,(int)$file['size']-1,(int)$file['size']),502,'Do not download full FLAC to discard a large seek');
    $reject(fn()=>DriveStream::response(206,['content-range'=>'bytes 0-1/123','content-length'=>'2'],0,1,100),502,'Reject mismatched total size');
    $reject(fn()=>DriveStream::response(206,['content-range'=>'bytes 1-2/100','content-length'=>'2'],0,1,100),502,'Reject shifted upstream range');
    $reject(fn()=>DriveStream::response(206,['content-range'=>'bytes 0-1/100','content-length'=>'3'],0,1,100),502,'Reject mismatched Content-Length');
    $reject(fn()=>DriveStream::response(206,['content-range'=>'bytes 0-1/100','content-encoding'=>'gzip'],0,1,100),502,'Reject compressed audio byte ranges');
    foreach ([401,403,404,416,429,500] as $error) $reject(fn()=>DriveStream::response($error,[],0,1,100),$error===500?502:$error,'Handle upstream '.$error);
    $cache->invalidate($file['id'],false); $lock=$cache->lock($file['id']); $before=count($calls);
    $assert($pipe->prepare($file,$reader)['reason']==='busy' && count($calls)===$before,'Single flight prevents duplicate background download'); DriveCache::unlock($lock);
    $ownerLock=$cache->lock('another-file',true); $assert($pipe->prepare($file,$reader)['reason']==='busy','One warm request per owner'); DriveCache::unlock($ownerLock);
    $valid=false; $reject(fn()=>$pipe->prepare($file,$reader),403,'Disconnect during warm prevents publishing cache'); $assert($cache->prefix($file)===null,'No prefix saved after disconnect'); $valid=true;
    $broken=static function(array $f,int $s,int $e,bool $w,callable $h,callable $b):void { $h(206,['content-range'=>"bytes {$s}-{$e}/{$f['size']}"]); $b('short'); };
    $reject(fn()=>$pipe->prepare($file,$broken),502,'Truncated warm rejected'); $assert($cache->prefix($file)===null,'Partial warm not published');
    $uncached=[...$file,'headRevisionId'=>''];$cancelledBytes=0;
    $pipe->serve($uncached,'',$reader,static function(){},static function(string $chunk)use(&$cancelledBytes):bool{$cancelledBytes+=strlen($chunk);return false;});
    $assert($cancelledBytes===57341,'Intentional analysis disconnect is not reported as truncated upstream');
    $reject(fn()=>$pipe->serve($uncached,'',$broken,static function(){},static function(){}),502,'Audible truncated upstream is still rejected');
    $reject(fn()=>$pipe->serve($uncached,'',static function($f,$s,$e,$w,$h,$b){$b('without headers');},static function(){},static function(){}),502,'Body before upstream headers is rejected');
    $assert(GoogleDrive::meterRate(100,0)===2097152,'Unknown imported duration has a 2 MiB analysis ceiling');
    $assert(GoogleDrive::meterRate(100,NAN)===2097152 && GoogleDrive::meterRate(100,INF)===2097152,'Invalid duration cannot break transfer options');
    $assert(GoogleDrive::meterRate(12000000,300)===524288,'Low bitrate analysis remains bounded at 512 KiB/s');
    $assert(GoogleDrive::meterRate(125829120,30)===6291456,'High bitrate file receives 1.5 times its 4 MiB/s average');
    $assert(GoogleDrive::meterRate(PHP_INT_MAX,.001)===8388608,'Extreme bitrate never removes the 8 MiB/s ceiling');
    // The audible stream publishes a complete prefix before its tail finishes.
    // A simultaneous mirror can reuse it, with the per-file lock released.
    $earlyReader=static function(array $f,int $s,int $e,bool $w,callable $h,callable $b) use ($bytes,$cache,$assert):void {
        $h(206,['content-range'=>"bytes {$s}-{$e}/{$f['size']}",'content-length'=>(string)($e-$s+1)]);
        $b(substr($bytes,0,DriveCache::PREFIX_BYTES));
        $assert($cache->prefix($f)!==null,'Prefix published before upstream tail completes');
        $released=$cache->lock($f['id']);$assert($released!==null,'Prefix lock released before upstream tail completes');DriveCache::unlock($released);
        $b(substr($bytes,DriveCache::PREFIX_BYTES));
    };
    $earlyBody='';$pipe->serve($file,'',$earlyReader,static function(){},static function(string $chunk)use(&$earlyBody):void{$earlyBody.=$chunk;});
    $assert($earlyBody===$bytes,'Early cache publication preserves native byte stream');
    $cache->invalidate($file['id'],false);$valid=false;$earlyBody='';
    $pipe->serve($file,'',$reader,static function(){},static function(string $chunk)use(&$earlyBody):void{$earlyBody.=$chunk;});
    $assert($cache->prefix($file)===null && $earlyBody===$bytes,'Disconnected stream does not publish early prefix or stop native bytes');$valid=true;
    $mode=206; $pipe->prepare($file,$reader); $path=$cache->prefix($file); touch(substr($path,0,-4).'.info',time()-DriveCache::TTL-1); $assert($cache->prefix($file)===null,'Expired prefix not served');
    foreach (glob(dirname($path).'/*.meta') as $meta) touch($meta,time()-DriveCache::METADATA_TTL-1);
    $assert($cache->metadata($file['id'])===null,'Expired metadata is refreshed');
    touch($path,time()-DriveCache::TTL-1); $cache->cleanup(); $assert(!is_file($path),'Expired bytes cleaned automatically');
    // Budget tests use real sized files and cover eviction across account owners.
    for ($owner=1;$owner<=5;$owner++) {
        $ownerCache=new DriveCache($root,$owner,$account);
        for ($n=0;$n<17;$n++) $ownerCache->savePrefix([...$file,'id'=>'budget-'.$n],substr($bytes,0,DriveCache::PREFIX_BYTES));
        $sum=array_sum(array_map('filesize',glob($root.'/'.$owner.'/drive-cache/*.bin')?:[])); $assert($sum<=DriveCache::OWNER_BYTES,'Owner disk budget '.$owner);
    }
    $sum=array_sum(array_map('filesize',glob($root.'/*/drive-cache/*.bin')?:[])); $assert($sum<=DriveCache::TOTAL_BYTES,'Global disk budget');
    DriveCache::clearOwner($root,5); $assert(!(glob($root.'/5/drive-cache/*.bin')?:[]),'Disconnect purges only owner cache');
    $assert((bool)(glob($root.'/4/drive-cache/*.bin')?:[]),'Other owner cache survives disconnect');
    $png=getimagesize(dirname(__DIR__).'/assets/cover.png'); $assert($png[0]===512 && $png[1]===512 && $png['mime']==='image/png','System cover is a real square 512px PNG');
    $blocked=$root.'/blocked'; file_put_contents($blocked,'not-a-directory');
    $unwritable=new DriveStream(new DriveCache($blocked,1,$account),static fn():bool=>true); $body='';
    set_error_handler(static fn():bool=>true);
    try { $unwritable->serve($file,'',$reader,static function(){},static function(string $chunk)use(&$body):void{$body.=$chunk;}); }
    finally { restore_error_handler(); }
    $assert($body===$bytes,'Unwritable cache does not stop native audio streaming');
    echo "Passed {$checks} Drive stream/cache checks.\n";
} finally {
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $entry) $entry->isDir()?rmdir($entry->getPathname()):unlink($entry->getPathname()); rmdir($root);
}
