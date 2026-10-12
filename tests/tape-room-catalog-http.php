<?php
declare(strict_types=1);
// Real HTTP/controllers/streaming on the disposable SQLite browser fixture.
// No production database, accounts, Google tokens or user music are read.
if (PHP_SAPI !== 'cli') exit;
$project = dirname(__DIR__);
$temporary = __DIR__ . '/.tmp';
if (!is_dir($temporary)) mkdir($temporary,0700,true);
$testRoot = $temporary . '/tape-room-catalog-http-' . bin2hex(random_bytes(8));
foreach (['','/sessions','/uploads','/php-tmp'] as $directory) mkdir($testRoot.$directory,0700,true);
$process = null;
register_shutdown_function(static function() use (&$process,$testRoot): void {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (is_dir($testRoot)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($testRoot);
    }
});
$socket = stream_socket_server('tcp://127.0.0.1:0',$number,$message);
if (!$socket) throw new RuntimeException('Cannot reserve isolated HTTP fixture port.');
$address = stream_socket_get_name($socket,false);fclose($socket);
$port = (int)substr($address,strrpos($address,':')+1);
$environment = [...getenv(),'REEL_PLAYER_TEST_ROOT'=>$testRoot,'REEL_PLAYER_SEQUENCE_PREVIEW'=>'short','REEL_PLAYER_CATALOG_PREVIEW'=>'1'];
unset($environment['PHP_CLI_SERVER_WORKERS'],$environment['REEL_PLAYER_ROLE_PREVIEW'],$environment['REEL_PLAYER_STREAM_PREVIEW']);
$process = proc_open([PHP_BINARY,'-d','sys_temp_dir='.$testRoot.'/php-tmp','-d','session.save_path='.$testRoot.'/sessions','-d','upload_tmp_dir='.$testRoot.'/uploads','-S','127.0.0.1:'.$port,$project.'/plugins/reel-player/tests/browser-fixture.php'],[0=>['pipe','r'],1=>['file',$testRoot.'/server.log','a'],2=>['file',$testRoot.'/server.log','a']],$pipes,$project,$environment);
if (!is_resource($process)) throw new RuntimeException('Cannot start isolated HTTP fixture.');
fclose($pipes[0]);
$base = 'http://127.0.0.1:'.$port;
$checks = 0;
$assert = static function(bool $ok,string $reason) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($reason); };
$request = static function(string $viewer,string $path,?array $fields=null,array $headers=[],array $files=[]) use ($base): array {
    $curl = curl_init($base.$path);$responseHeaders=[];
    $allHeaders=['Cookie: reel_catalog_fixture_viewer='.$viewer,...$headers];
    $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>$allHeaders,CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$responseHeaders): int {
        if (str_contains($line,':')) { [$name,$value]=explode(':',$line,2);$responseHeaders[strtolower(trim($name))]=trim($value); } return strlen($line);
    }];
    if ($fields!==null) {
        $options[CURLOPT_POST]=true;$allHeaders[]='X-CSRF-Token: reel-browser-fixture';$options[CURLOPT_HTTPHEADER]=$allHeaders;
        if ($files) foreach ($files as $name=>$file) $fields[$name]=new CURLFile($file,'image/png',basename($file));
        $options[CURLOPT_POSTFIELDS]=$files ? $fields : http_build_query($fields);
    }
    curl_setopt_array($curl,$options);$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
    return ['status'=>$status,'headers'=>$responseHeaders,'body'=>is_string($body)?$body:'','error'=>$error];
};
$json = static function(array $reply): array { $data=json_decode($reply['body'],true);if(!is_array($data))throw new RuntimeException('Expected JSON; HTTP '.$reply['status'].': '.mb_substr($reply['body'],0,160));return $data; };
$action = static fn(string $viewer,string $name,array $fields=[]) => $request($viewer,'/admin/reel-player/api/action',['action'=>$name,...$fields]);
try {
    $deadline=microtime(true)+8;
    do { $page=$request('creator','/admin/reel-player'); if($page['status']) break; usleep(50000); } while(microtime(true)<$deadline);
    $assert($page['status']===200,'The real player controller renders on an isolated migrated fixture');
    $creator=$json($request('creator','/admin/reel-player/api/state'));
    $admin=$json($request('admin','/admin/reel-player/api/state'));
    $assert(count($creator['tracks'])===4 && count($creator['catalog']['albums'])===3,'Creator sees own tracks, two published albums and a private draft');
    $assert(count($admin['tracks'])===0 && count($admin['catalog']['albums'])===2,'A different viewer has no copied owned tracks or foreign draft');
    $assert($creator['catalog']['canPublish']===true && $admin['catalog']['canPublish']===false,'Publication capability follows the actual fixture role');
    $byTitle=array_column($creator['tracks'],'id','title');$mono=(int)$byTitle['Mono'];$stereo=(int)$byTitle['Stereo'];$quiet=(int)$byTitle['Quiet'];$zero=(int)$byTitle['Zero'];
    $albums=array_column($creator['catalog']['albums'],'id','title');$night=(int)$albums['Ночная сторона · демо'];$draft=(int)$albums['Черновик · демо'];
    $assert(count(glob($testRoot.'/data/reel-player/*/*.wav'))===4,'The fixture starts with exactly four physical original audio files');
    foreach (['file_path','cover_path','source_id','owner_id','client_secret','refresh_token'] as $sensitive) $assert(!str_contains(json_encode($admin,JSON_THROW_ON_ERROR),'"'.$sensitive.'"'),'State excludes server-only '.$sensitive);
    foreach (['media/','cover/','api/quality/'] as $prefix) $assert($request('admin','/admin/reel-player/'.$prefix.$mono)['status']===404,'Private owner route denies another user: '.$prefix);
    $assert($request('admin','/admin/reel-player/api/prepare',['id'=>$mono])['status']===404,'Private preparation route cannot access a foreign track');
    $stream=$request('admin','/admin/reel-player/catalog/media/'.$mono,null,['Range: bytes=0-43']);
    $assert($stream['status']===206 && strlen($stream['body'])===44 && substr($stream['body'],0,4)==='RIFF','A published foreign track is playable through the real Range-aware catalog stream');
    $assert(str_starts_with($stream['headers']['content-range']??'','bytes 0-43/'),'The public stream reports the original byte range');
    $assert(str_contains($stream['headers']['cache-control']??'','no-store'),'Shared audio does not enter public or persistent HTTP caches');
    $quality=$json($request('admin','/admin/reel-player/api/catalog/quality/'.$mono));
    $assert(($quality['quality']['format']??'')==='WAV' && ($quality['quality']['sampleRate']??0)===44100 && ($quality['quality']['channels']??0)===1 && ($quality['quality']['bitDepth']??0)===16,'Catalog technical quality reads the publisher original WAV header');
    $assert($json($request('admin','/admin/reel-player/api/catalog/prepare',['id'=>$mono]))['ready']===true,'Shared preparation uses the original owned source');
    $assert($request('admin','/admin/reel-player/catalog/media/'.$zero)['status']===404,'Foreign private album music is denied');
    $assert($request('admin','/admin/reel-player/catalog/album-cover/'.$draft)['status']===404,'Foreign draft album cover is denied');
    $assert($action('admin','catalog.album.publish',['id'=>$night,'published'=>0])['status']===422,'Another viewer cannot unpublish someone else album');
    $assert($action('admin','track.update',['id'=>$mono,'title'=>'Forbidden'])['status']===422,'A published foreign track is read only');
    $saved=$json($action('admin','catalog.album.save',['id'=>$night,'saved'=>1]));
    $savedAlbum=array_values(array_filter($saved['catalog']['albums'],static fn(array $album):bool=>(int)$album['id']===$night))[0];
    $assert($savedAlbum['saved']===true && $saved['tracks']===[],'Saving an album adds a personal reference and does not clone owned tracks');
    $personal=array_values(array_filter($saved['catalog']['tracks'],static fn(array $track):bool=>$track['collected']));
    $assert(count($personal)===2,'Saving the two-track album adds its songs to this viewer collection');
    $favorite=$json($action('admin','catalog.track.favorite',['id'=>$mono,'favorite'=>1]));
    $shared=array_values(array_filter($favorite['catalog']['tracks'],static fn(array $track):bool=>(int)$track['id']===$mono))[0];
    $creatorNow=$json($request('creator','/admin/reel-player/api/state'));
    $owner=array_values(array_filter($creatorNow['tracks'],static fn(array $track):bool=>(int)$track['id']===$mono))[0];
    $assert($shared['favorite']===true && $owner['favorite']===false,'Shared favorites belong to the viewer and do not modify publisher favorites');
    $playlist=(int)$admin['playlists'][0]['id'];
    $playlistState=$json($action('admin','playlist.add',['id'=>$playlist,'track_id'=>$quiet]));
    $assert(in_array($quiet,$playlistState['playlists'][0]['tracks'],true),'A personal playlist can reference a published foreign song');
    $assert($action('admin','playlist.add',['id'=>$playlist,'track_id'=>$zero])['status']===422,'A personal playlist cannot reference another user private song');
    $assert(count(glob($testRoot.'/data/reel-player/*/*.wav'))===4,'Save, favorite and playlist actions preserve the same four physical audio files');
    $image=$testRoot.'/tiny-cover.png';file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/a7v0AAAAASUVORK5CYII=',true));
    // The action helper intentionally has only fields; upload uses the same
    // actual endpoint with multipart data so is_uploaded_file is exercised.
    $coverSaved=$request('creator','/admin/reel-player/api/action',['action'=>'track.update','id'=>$mono],[],['cover'=>$image]);
    $assert($coverSaved['status']===200 && $json($coverSaved)['status']===true,'Publisher can upload a real fixture cover through the styled editor API');
    $cover=$request('admin','/admin/reel-player/catalog/cover/'.$mono);
    $assert($cover['status']===200 && $cover['body']===file_get_contents($image),'Catalog cover streams the original uploaded image');
    $assert(str_contains($cover['headers']['cache-control']??'','private') && str_contains($cover['headers']['cache-control']??'','must-revalidate'),'Shared cover caching remains private and rechecks publication');
    $etag=$cover['headers']['etag']??'';$assert($etag!=='','Catalog cover has a revision validator');
    $assert($request('admin','/admin/reel-player/catalog/cover/'.$mono,null,['If-None-Match: '.$etag])['status']===304,'Authorized unchanged cover revalidation skips image bytes');
    $albumCover=$request('admin','/admin/reel-player/catalog/album-cover/'.$night);
    $assert($albumCover['status']===200 && $albumCover['body']===$cover['body'],'An album without its own artwork falls back to its first actual song cover');
    $listen=$json($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'start','id'=>$mono]));
    $early=$json($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'finish','token'=>$listen['token'],'seconds'=>$listen['threshold']]));
    $assert($early['counted']===false && $early['listens']===0,'A play click or spoofed immediate listening duration does not increase popularity');
    usleep(1200000);
    $finished=$json($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'finish','token'=>$listen['token'],'seconds'=>1.2]));
    $assert($finished['counted']===true && $finished['listens']===1 && $finished['credited']===true,'A real elapsed half-track listening interval records one credited play');
    $again=$json($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'finish','token'=>$listen['token'],'seconds'=>1.2]));
    $assert($again['listens']===1,'Repeated listen completion is idempotent');
    $assert($request('creator','/admin/reel-player/api/catalog/listen',['action'=>'finish','token'=>$listen['token'],'seconds'=>1.2])['status']===422,'Listening tokens belong to their original viewer');
    $repeatToken=$json($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'start','id'=>$mono]));usleep(1200000);
    $deduplicated=$json($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'finish','token'=>$repeatToken['token'],'seconds'=>1.2]));
    $assert($deduplicated['counted']===true && $deduplicated['credited']===false && $deduplicated['listens']===1,'An immediate second token is processed but cannot multiply the credited popularity count');
    $unpublish=$action('creator','catalog.album.publish',['id'=>$night,'published'=>0]);
    $assert($unpublish['status']===200,'Publisher can explicitly withdraw an album');
    $withdrawn=$json($request('admin','/admin/reel-player/api/state'));
    $assert(!in_array($mono,array_column($withdrawn['catalog']['tracks'],'id'),true) && !in_array($night,array_column($withdrawn['catalog']['albums'],'id'),true),'Withdrawn-only songs and saved album disappear from foreign state');
    foreach (['catalog/media/','catalog/cover/','api/catalog/quality/'] as $prefix) $assert($request('admin','/admin/reel-player/'.$prefix.$mono,null,['If-None-Match: '.$etag])['status']===404,'Withdrawal is enforced before media/quality/304 response: '.$prefix);
    $assert($request('admin','/admin/reel-player/catalog/album-cover/'.$night,null,['If-None-Match: '.$etag])['status']===404,'Album artwork authorization is checked before a matching cache validator');
    $assert($request('admin','/admin/reel-player/api/catalog/prepare',['id'=>$mono])['status']===404,'Preparation is revoked immediately with publication');
    $assert($request('admin','/admin/reel-player/api/catalog/listen',['action'=>'start','id'=>$mono])['status']===422,'A withdrawn song cannot start a new listening record');
    $assert($request('creator','/admin/reel-player/media/'.$mono,null,['Range: bytes=0-43'])['status']===206,'Publisher retains their own original audio after withdrawal');
    $assert($request('admin','/admin/reel-player/catalog/media/'.$stereo,null,['Range: bytes=0-43'])['status']===206,'A song still present in another published album remains accessible');
    $assert(count(glob($testRoot.'/data/reel-player/*/*.wav'))===4,'Unpublish does not delete, copy or split original audio');
    $routes=(string)file_get_contents($project.'/plugins/reel-player/routes.php');
    foreach (['catalogMedia','catalogCover','albumCover','catalogQuality','catalogPrepare','catalogListen'] as $method) $assert((bool)preg_match('/PlayerController::class,\s*\x27'.preg_quote($method,'/').'\x27\]\)->middleware\(\$guard\)/',$routes),'Catalog endpoint retains the CMS auth/admin guard: '.$method);
    $assert(str_contains($routes,'$guard = [\'auth\', \'admin\'];'),'Catalog routes use the same CMS access policy');
    $log=(string)file_get_contents($testRoot.'/server.log');
    $assert(!preg_match('/PHP (?:Fatal error|Warning|Notice)|SQLSTATE\[|Uncaught/',$log),'Fixture server log contains no PHP or SQL errors');
    echo "Passed {$checks} Tape Room catalog HTTP checks: migrated roles, private/public streams, Range, quality, save/favorite/playlist references, cover caches, real listening and immediate publication revocation.\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    $log=is_file($testRoot.'/server.log')?(string)file_get_contents($testRoot.'/server.log'):'';
    fwrite(STDERR,mb_substr($log,-5000));exit(1);
}
