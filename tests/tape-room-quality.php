<?php
declare(strict_types=1);

namespace Fireball\ReelPlayer\Services {
    // Replace transport only: real Google owner/cache/token/range validation runs.
    function curl_init(?string $url = null): object { return (object)['url'=>$url,'options'=>[]]; }
    function curl_setopt_array(object $curl, array $options): bool { $curl->options += $options; return true; }
    function curl_setopt(object $curl, int $option, mixed $value): bool { $curl->options[$option] = $value; return true; }
    function curl_exec(object $curl): bool {
        $GLOBALS['rangeCalls']++;
        $GLOBALS['lastRangeOptions'] = $curl->options;
        $status = array_shift($GLOBALS['responses']) ?? 206;
        $request = implode("\n", $curl->options[CURLOPT_HTTPHEADER]);
        if (!preg_match('/Range: bytes=(\d+)-(\d+)/', $request, $range)) throw new \RuntimeException('Metadata/refresh transport is unexpected in this fixture.');
        $start = (int)$range[1]; $end = (int)$range[2]; $size = $GLOBALS['remoteSize'];
        $header = $curl->options[CURLOPT_HEADERFUNCTION];
        $header($curl, "HTTP/1.1 $status Fixture\r\n");
        $header($curl, "Content-Range: bytes $start-$end/$size\r\n");
        $header($curl, "\r\n");
        $body = substr(str_pad($GLOBALS['remoteHeader'], $end - $start + 1, "\0"), 0, $end - $start + 1);
        if (!empty($GLOBALS['oversizedBody'])) $body .= '!';
        return $curl->options[CURLOPT_WRITEFUNCTION]($curl, $body) === strlen($body);
    }
    function curl_errno(object $curl): int { return 0; }
    function curl_close(object $curl): void {}
}

namespace {
    use Fireball\ReelPlayer\Services\AudioQuality;
    use Fireball\ReelPlayer\Services\DriveCache;
    use Fireball\ReelPlayer\Services\GoogleDrive;

    require dirname(__DIR__).'/plugins/reel-player/src/Services/AudioQuality.php';
    require dirname(__DIR__).'/plugins/reel-player/src/Services/DriveCache.php';
    require dirname(__DIR__).'/plugins/reel-player/src/Services/GoogleDrive.php';
    require dirname(__DIR__).'/plugins/reel-player/src/Services/MediaStorage.php';
    require dirname(__DIR__).'/plugins/reel-player/src/Repositories/Library.php';
    require dirname(__DIR__).'/plugins/reel-player/src/Repositories/Chapters.php';
    require dirname(__DIR__).'/plugins/reel-player/src/Controllers/PlayerController.php';

    $checks = 0;
    function verify(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
    function rejected(callable $operation, string $message): void { $failed=false; try { $operation(); } catch (RuntimeException|InvalidArgumentException) { $failed=true; } verify($failed, $message); }
    function flacHeader(int $rate=48000,int $bits=24,int $channels=2,int $samples=5760000): string {
        $block = str_repeat("\0",34);
        $packed = ($rate << 44) | (($channels-1) << 41) | (($bits-1) << 36) | $samples;
        $block = substr_replace($block, pack('N2', ($packed >> 32) & 0xffffffff, $packed & 0xffffffff), 10, 8);
        return 'fLaC'."\x80\0\0\x22".$block;
    }
    function waveHeader(int $rate=44100,int $bits=16,int $channels=2,int $encoding=1,string $extra=''): string {
        $align = $channels*(int)ceil($bits/8); $format = pack('vvVVvv',$encoding,$channels,$rate,$rate*$align,$align,$bits).$extra;
        return 'RIFF'.pack('V',36+strlen($extra)+176400).'WAVEfmt '.pack('V',strlen($format)).$format.'data'.pack('V',176400);
    }
    function mpegFrames(string $frameHeader="\xff\xfb\x90\x00",int $length=417,string $next="\xff\xfb\xa0\x00"): string {
        return $frameHeader.str_repeat("\0",$length-4).$next.str_repeat("\0",600);
    }
    $temporary=__DIR__.'/.tmp/tape-room-quality-'.bin2hex(random_bytes(8)); mkdir($temporary.'/reel-player/7',0700,true);
    define('STORAGE',$temporary);
    register_shutdown_function(static function() use ($temporary): void {
        $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($items as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname()); rmdir($temporary);
    });

    $flac=AudioQuality::describe(flacHeader(),'song.flac','audio/flac',12000000,90);
    verify($flac['sampleRate']===48000 && $flac['bitDepth']===24 && $flac['channels']===2 && $flac['lossless'], 'FLAC STREAMINFO preserves actual resolution');
    verify($flac['duration']===120 && $flac['bitrate']===800000 && $flac['bitrateKind']==='average', 'FLAC total samples provide duration; compressed bitrate remains approximate');
    $long=AudioQuality::describe(flacHeader(192000,32,8,1382400000),'mix.flac');
    verify($long['duration']===7200 && $long['channels']===8 && $long['bitDepth']===32, 'Two-hour high-resolution FLAC counters and channels decode correctly');
    verify(AudioQuality::describe(flacHeader(1048575,16,1,1048575),'song.flac')['sampleRate']===1048575, 'Current FLAC specification accepts the complete twenty-bit sample-rate range');
    verify(!isset(AudioQuality::describe(substr(flacHeader(),0,41),'song.flac')['sampleRate']), 'Truncated STREAMINFO does not invent resolution');
    verify(!isset(AudioQuality::describe(str_replace("\x80\0\0\x22","\x81\0\0\x22",flacHeader()),'song.flac')['sampleRate']), 'Invalid first FLAC block is rejected');
    verify(!isset(AudioQuality::describe(flacHeader(0),'song.flac')['sampleRate']), 'Invalid zero sample rate is rejected');
    $wave=AudioQuality::describe(waveHeader(),'song.wav');
    verify($wave['sampleRate']===44100 && $wave['bitDepth']===16 && $wave['bitrate']===1411200 && $wave['bitrateKind']==='exact', 'WAV PCM byte rate is exact and independent of tags');
    $junk='RIFF'.pack('V',176450).'WAVEJUNK'.pack('V',3).'abc'."\0".substr(waveHeader(96000,24),12);
    verify(AudioQuality::describe($junk,'song.wav')['sampleRate']===96000, 'WAV chunk alignment and padding are supported');
    $extensible=pack('vvV',22,24,3)."\x01\0\0\0\0\0\x10\0\x80\0\0\xaa\0\x38\x9b\x71";
    $valid=AudioQuality::describe(waveHeader(96000,32,2,65534,$extensible),'song.wav');
    verify($valid['bitDepth']===24 && $valid['bitrate']===6144000, 'Extensible WAV valid bits differ from its padded container bitrate');
    verify(AudioQuality::describe(waveHeader(48000,32,1,3),'song.wav')['bitDepth']===32, 'Float WAV precision is supported');
    verify(!isset(AudioQuality::describe(waveHeader(44100,4,2,17),'song.wav')['bitDepth']), 'Compressed WAV does not claim PCM bit depth');
    verify(!isset(AudioQuality::describe('RIFF'.pack('V',999).'WAVEJUNK'.pack('V',0xfffffffe),'song.wav')['sampleRate']), 'Malformed huge chunk stops parsing within the prefix');
    $mp3=AudioQuality::describe(mpegFrames(),'song.mp3','audio/mpeg',1000000,100);
    verify($mp3['sampleRate']===44100 && $mp3['channels']===2 && $mp3['frameBitrate']===128000, 'MP3 first validated frame exposes rate/channel metadata');
    verify($mp3['bitrate']===80000 && $mp3['bitrateKind']==='average', 'VBR MP3 bitrate uses total file/duration rather than the first frame');
    verify(!isset(AudioQuality::describe(mpegFrames(),'song.mp3')['bitrate']), 'Unknown MP3 duration cannot be described as constant first-frame bitrate');
    $id3='ID3'."\x04\0\0\0\0\0\x05".'hello'.mpegFrames();
    verify(AudioQuality::describe($id3,'song.mp3')['sampleRate']===44100, 'MP3 safely skips ID3v2 synchsafe tag size');
    verify(AudioQuality::describe(mpegFrames("\xff\xf3\x80\xc0",208,"\xff\xf3\x80\xc0"),'song.mp3')['sampleRate']===22050, 'MPEG-2 Layer III frame length and mono are parsed');
    verify(!isset(AudioQuality::describe("\xff\xfb\x90\x00".str_repeat("x",2000),'song.mp3')['sampleRate']), 'A sync-like isolated word does not claim audio metadata');
    verify(!isset(AudioQuality::describe('ID3'."\x04\0\0\0\0\x80\0".mpegFrames(),'song.mp3')['sampleRate']), 'Malformed synchsafe tag rejects embedded false audio');
    verify(AudioQuality::describe('', 'song.m4a')['format']==='M4A' && !isset(AudioQuality::describe('', 'song.m4a')['lossless']), 'M4A does not assume AAC or lossless encoding');
    verify(AudioQuality::format('unknown.bin','application/x-flac')==='FLAC' && AudioQuality::format('unknown.bin','invalid')==='', 'Honest format fallback accepts MIME and keeps unsupported values unknown');
    verify(!isset(AudioQuality::describe('', 'song.mp3','audio/mpeg',100,INF)['bitrate']), 'Non-finite duration cannot introduce an invalid bitrate');

    $path=$temporary.'/reel-player/7/'.str_repeat('a',40).'.wav';
    file_put_contents($path,waveHeader().str_repeat("\0",1000000));
    verify(AudioQuality::local($path,'song.wav')['sampleRate']===44100, 'Local metadata reads a bounded prefix from the original file');
    verify(AudioQuality::local($temporary.'/missing','song.flac')['format']==='FLAC', 'Missing local file gives only honest fallback metadata');

    $app=['client_id'=>'fixture.apps.googleusercontent.com','client_secret'=>'fixture-secret'];
    $binding=hash('sha256',$app['client_id']."\0".$app['client_secret']);
    $token=['access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires_at'=>time()+3600,'app_binding'=>$binding];
    file_put_contents($temporary.'/reel-player/drive-app.json',json_encode($app));
    file_put_contents($temporary.'/reel-player/7/drive.json',json_encode($token));
    $cache=new DriveCache($temporary.'/reel-player',7,hash('sha256',$binding.':'.$token['refresh_token']));
    $file=['id'=>'fixture-audio-001','name'=>'source.flac','mimeType'=>'audio/flac','size'=>4000000000,'headRevisionId'=>'revision-1'];
    $cache->saveMetadata($file);
    $GLOBALS['rangeCalls']=0; $GLOBALS['responses']=[]; $GLOBALS['remoteHeader']=flacHeader(); $GLOBALS['remoteSize']=(int)$file['size'];
    $drive=new GoogleDrive(7,$temporary.'/reel-player');
    $cloud=$drive->quality($file['id']);
    verify($cloud['bitDepth']===24 && $GLOBALS['rangeCalls']===1, 'Google Drive reads technical metadata using one bounded real-service range');
    $rangeHeader=implode("\n",$GLOBALS['lastRangeOptions'][CURLOPT_HTTPHEADER]);
    verify(str_contains($rangeHeader,'Range: bytes=0-'.(AudioQuality::HEADER_BYTES-1)) && $GLOBALS['lastRangeOptions'][CURLOPT_TIMEOUT]<=8, 'Huge source cannot trigger a full download or unbounded media timeout');
    $GLOBALS['responses']=[403,206]; $before=$GLOBALS['rangeCalls'];
    verify($drive->quality($file['id'])['sampleRate']===48000 && $GLOBALS['rangeCalls']===$before+2, 'A forbidden pinned revision falls back to one verified current header');
    $GLOBALS['responses']=[200]; rejected(static fn()=>$drive->quality($file['id']), 'Ignored Google Range never accepts an unbounded HTTP 200 body');
    $GLOBALS['oversizedBody']=true; rejected(static fn()=>$drive->quality($file['id']), 'Even a matching header cannot exceed the byte budget'); $GLOBALS['oversizedBody']=false;
    $small=[...$file,'id'=>'fixture-audio-002','size'=>strlen(flacHeader())]; $cache->saveMetadata($small); $cache->savePrefix($small,flacHeader()); $before=$GLOBALS['rangeCalls'];
    verify($drive->quality($small['id'])['bitDepth']===24 && $GLOBALS['rangeCalls']===$before, 'Existing authorized versioned prefix supplies quality without a network request');
    $other=[...$file,'id'=>'fixture-audio-003','name'=>'song.m4a','mimeType'=>'audio/mp4']; $cache->saveMetadata($other); $before=$GLOBALS['rangeCalls'];
    verify($drive->quality($other['id'],100)['format']==='M4A' && $GLOBALS['rangeCalls']===$before, 'Unimplemented containers keep useful average/format without audio downloads');

    // Real repository SQL and controller API, using a disposable in-memory DB.
    final class QualityQuery {
        public function __construct(private PDOStatement $statement) {}
        public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
        public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
    }
    final class QualityDB {
        public function __construct(public PDO $pdo) {}
        public function query(string $sql,array $bindings=[]): QualityQuery { $statement=$this->pdo->prepare($sql); $statement->execute($bindings); return new QualityQuery($statement); }
    }
    $GLOBALS['qualityDB']=new QualityDB(new PDO('sqlite::memory:')); $pdo=$GLOBALS['qualityDB']->pdo;
    $pdo->exec('CREATE TABLE reel_tracks(id INTEGER,owner_id INTEGER,title TEXT,artist TEXT,filename TEXT,file_path TEXT,mime TEXT,file_size INTEGER,duration REAL,cover_path TEXT,cover_mime TEXT,source TEXT,favorite INTEGER,created_at TEXT);CREATE TABLE reel_chapters(id INTEGER,track_id INTEGER,title TEXT,artist TEXT,start_time REAL);CREATE TABLE reel_playlists(id INTEGER,owner_id INTEGER,name TEXT);CREATE TABLE reel_playlist_tracks(playlist_id INTEGER,track_id INTEGER,position INTEGER)');
    $insert=$pdo->prepare('INSERT INTO reel_tracks VALUES(13,7,?,?,?,?,?,1000044,0,NULL,NULL,"local",0,"2026-10-12")');
    $insert->execute(['Fixture','Artist','source.wav','7/'.str_repeat('a',40).'.wav','audio/wav']);
    $GLOBALS['userOwner']=7; $GLOBALS['trackId']=13; $GLOBALS['closed']=0;
    function db(): QualityDB { return $GLOBALS['qualityDB']; }
    function get_user(): array { return ['id'=>$GLOBALS['userOwner']]; }
    function check_creator(): bool { return false; }
    function get_route_param(string $name): int { return $GLOBALS['trackId']; }
    function base_href(string $path): string { return $path; }
    function session(): object { return new class { public function close(): void { $GLOBALS['closed']++; } }; }
    final class QualityResponse extends RuntimeException { public function __construct(public array $data) { parent::__construct('captured'); } }
    function response(): object { return new class { public function json(array $data): never { throw new QualityResponse($data); } }; }
    final class QualityAbort extends RuntimeException { public function __construct(public int $status) { parent::__construct('aborted'); } }
    function abort(string $message,int $status=404): never { throw new QualityAbort($status); }
    $controller=new \Fireball\ReelPlayer\Controllers\PlayerController();
    try { $controller->quality(); } catch (QualityResponse $result) { $api=$result->data; }
    verify(($api['status']??false) && $api['quality']['sampleRate']===44100 && $GLOBALS['closed']===1, 'Owned-track API serves technical quality and releases session before IO');
    verify(!str_contains(json_encode($api),'file_path') && !str_contains(json_encode($api),$path) && !str_contains(json_encode($api),'fixture-access'), 'Public quality response contains no private paths, ids, or credentials');
    $state=(new \Fireball\ReelPlayer\Repositories\Library(7))->state();
    verify($state['tracks'][0]['mime']==='audio/wav' && is_int($state['tracks'][0]['file_size']), 'Library exposes lightweight MIME/size without audio parsing');
    $GLOBALS['userOwner']=8;
    try { $controller->quality(); throw new RuntimeException('Cross-owner access was allowed'); } catch (QualityAbort $error) { verify($error->status===404 && $GLOBALS['closed']===1, 'Another administrator cannot read quality of an unowned track'); }
    $GLOBALS['userOwner']=7; $GLOBALS['trackId']=999;
    try { $controller->quality(); throw new RuntimeException('Missing track was allowed'); } catch (QualityAbort $error) { verify($error->status===404, 'Missing tracks return 404'); }
    echo "Tape Room audio quality: $checks checks passed.\n";
}
