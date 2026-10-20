<?php
declare(strict_types=1);
// Real FLAC header, Drive prefix cache, quality API and library; no Google calls.
if(($argv[1]??'')==='--worker'){
    define('FIREBALL_CLI',true);
    $_SERVER=array_replace($_SERVER,['HTTP_HOST'=>'fixture.test','REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/','REMOTE_ADDR'=>'127.0.0.1','SERVER_PROTOCOL'=>'HTTP/1.1']);
    putenv('TAPE_ROOM_TEST_BOOTSTRAP_ONLY=1');require __DIR__.'/tape_room_fixture.php';
    final class DurationReply extends Exception {public function __construct(public array $data,public int $status){parent::__construct('JSON');}}
    final class DurationResponse extends FBL\Response {public function json($data,$code=200){throw new DurationReply($data,$code);}}
    $app->response=new DurationResponse();$checks=[];
    $check=static function(string $label,bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
    $request=static function(string $path,array $post=[])use($app):DurationReply{
        $_SERVER['REQUEST_METHOD']=$post?'POST':'GET';$_SERVER['REQUEST_URI']=$path;$_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';
        $_GET=$app->request->get=[];$_POST=$app->request->post=$post;$app->request->uri=trim($path,'/');$app->request->rowUri=$path;
        try{$app->router->dispatch();}catch(DurationReply $reply){return $reply;}throw new RuntimeException('No JSON response');
    };
    session()->set('user',db()->findOne('users',2));session()->set('needCSRFToken',str_repeat('a',64));
    $check('Fixture starts with an underestimated stored duration',(float)db()->query('SELECT duration FROM reel_tracks WHERE id=4')->getColumn()===6.0);
    $quality=$request('/tape-room/api/quality/4');
    $check('Drive quality route returns a successful FLAC response',$quality->status===200&&$quality->data['quality']['format']==='FLAC');
    $check('STREAMINFO gives all 529200 decoded samples at 44.1 kHz',abs($quality->data['quality']['duration']-12)<.0001&&$quality->data['quality']['sampleRate']===44100);
    $check('Header detection retains bit depth and lossless status',$quality->data['quality']['bitDepth']===16&&$quality->data['quality']['lossless']===true);
    $check('Reading optional metadata does not itself mutate the library',(float)db()->query('SELECT duration FROM reel_tracks WHERE id=4')->getColumn()===6.0);
    $library=new Fireball\ReelPlayer\Repositories\Library(2);
    $library->action('track.duration',['id'=>'4','duration'=>(string)$quality->data['quality']['duration']]);
    $check('The existing owner action saves the corrected duration',(float)db()->query('SELECT duration FROM reel_tracks WHERE id=4')->getColumn()===12.0);
    $tracks=array_column($library->state()['tracks'],null,'id');
    $check('Refreshed library returns corrected FLAC duration for rows and queue',abs($tracks[4]['duration']-12)<.0001);
    $check('Other recordings retain their duration',(float)db()->query('SELECT duration FROM reel_tracks WHERE id=1')->getColumn()===12.0);
    echo json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),"\n";exit;
}
$scratch=__DIR__.'/.tmp/tape-room-duration-'.bin2hex(random_bytes(8));mkdir($scratch,0700,true);
$run=static function(array $args,array $env=[])use($scratch):string{
    $process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),array_replace(getenv(),['TMPDIR'=>$scratch],$env));
    if(!is_resource($process))throw new RuntimeException('Cannot start test');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException($err.$out);return $out;
};
try{
    $run(['python3',__DIR__.'/tape_room_fixture_setup.py',$scratch,'flac-duration']);
    $report=$run([PHP_BINARY,'-d','session.save_path='.$scratch.'/sessions',__FILE__,'--worker'],['TAPE_ROOM_TEST_ROOT'=>$scratch,'TAPE_ROOM_TEST_PUBLIC'=>'1']);
    json_decode($report,true,512,JSON_THROW_ON_ERROR);$folder=__DIR__.'/artifacts/tape-room-flac-duration';if(!is_dir($folder))mkdir($folder,0700,true);file_put_contents($folder.'/api.json',$report);echo $report;
}finally{
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($entries as$entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($scratch);
}
