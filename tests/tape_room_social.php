<?php
declare(strict_types=1);
// Real repositories, router, authentication, CSRF and templates in isolated SQLite.
$project=dirname(__DIR__);
function socialRun(array $args,array $env=[]):string {
    $process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),array_replace(getenv(),$env));
    if(!is_resource($process))throw new RuntimeException('Cannot start test process.');
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException($err.$out);return $out;
}
if(($argv[1]??'')==='--worker'){
    define('FIREBALL_CLI',true);
    $_SERVER=array_replace($_SERVER,['HTTP_HOST'=>'fixture.test','REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/tape-room/people','REMOTE_ADDR'=>'127.0.0.1','SERVER_PROTOCOL'=>'HTTP/1.1']);
    putenv('TAPE_ROOM_TEST_BOOTSTRAP_ONLY=1');require __DIR__.'/tape_room_fixture.php';
    final class SocialReply extends Exception {public function __construct(public array $data,public int $status){parent::__construct('JSON');}}
    final class SocialRedirect extends Exception {}
    final class SocialResponse extends FBL\Response {
        public function json($data,$code=200){throw new SocialReply($data,$code);}
        public function redirect($url=''){throw new SocialRedirect($this->sanitizeRedirectTarget((string)$url));}
    }
    $app->response=new SocialResponse();$checks=[];
    $check=static function(string $label,bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
    $throws=static function(callable $operation,string $class)use($check):bool{try{$operation();}catch(Throwable $error){if($error instanceof $class)return true;throw $error;}return false;};
    $request=static function(string $path,string $method='GET',array $data=[],bool $ajax=false)use($app):mixed{
        $_SERVER['REQUEST_METHOD']=$method;$_SERVER['REQUEST_URI']=$path;
        if($ajax)$_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';else unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        parse_str((string)parse_url($path,PHP_URL_QUERY),$query);$_GET=$app->request->get=$query;$_POST=$app->request->post=$data;
        $app->request->uri=trim($path,'/');$app->request->rowUri=$path;return $app->router->dispatch();
    };
    $post=static function(string $path,array $data,bool $csrf=true)use($request):SocialReply{
        if($csrf)$data['needCSRFToken']=session()->get('needCSRFToken');
        try{$request($path,'POST',$data,true);}catch(SocialReply $response){return $response;}throw new RuntimeException('No JSON response');
    };
    $dom=static function(string $html):DOMXPath{$doc=new DOMDocument();$before=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($before);return new DOMXPath($doc);};
    $guest=new Fireball\ReelPlayer\Repositories\Social();$one=new Fireball\ReelPlayer\Repositories\Social(1);$two=new Fireball\ReelPlayer\Repositories\Social(2);
    foreach([[2,'Secret draft',0,'draft'],[3,'Secret pending',1,'pending'],[4,'Secret rejected',1,'rejected'],[5,'Secret withdrawn',0,'approved']]as[$id,$title,$published,$moderation]){
        db()->query('INSERT INTO reel_albums(id,owner_id,title,artist,published,moderation_status,created_at,updated_at) VALUES(?,1,?,\'Private artist\',?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',[$id,$title,$published,$moderation]);
        db()->query('INSERT INTO reel_album_tracks VALUES(?,2,0)',[$id]);
        db()->query('INSERT INTO reel_album_saves VALUES(2,?,CURRENT_TIMESTAMP)',[$id]);
    }
    db()->query('INSERT INTO reel_album_saves VALUES(2,1,CURRENT_TIMESTAMP)');
    $check('No private albums in public publications',array_column($guest->albums(1)['albums'],'id')===[1]);
    $check('Saved collections are closed by default',$guest->albums(2,'collection')['private']===true&&$guest->albums(2,'collection')['albums']===[]);
    $check('Self preview still excludes draft and unapproved saved albums',array_column($two->albums(2,'collection')['albums'],'id')===[1]);
    $settings=$two->saveSettings(['bio'=>"Музыка <script>alert(1)</script>\nДжаз",'profile_public'=>'1','collection_public'=>'1','owner_id'=>1]);
    $check('Only session owner settings are changed',$settings['collectionPublic']&&!$one->settings()['collectionPublic']);
    $check('Public collection contains approved publications only',array_column($guest->albums(2,'collection')['albums'],'id')===[1]);
    $encoded=json_encode([$guest->profile(2),$guest->directory(),$guest->albums(2,'collection')],JSON_UNESCAPED_UNICODE);
    $check('Public DTOs exclude login email credentials and raw paths',!preg_match('/listener@example|"login"|"email"|"password"|"file_path"|fixture-token|Secret draft/',$encoded));
    $check('Guest cannot follow',$throws(fn()=>$guest->follow(1,true),InvalidArgumentException::class));
    $check('Cannot follow oneself',$throws(fn()=>$two->follow(2,true),InvalidArgumentException::class));
    $check('Cannot follow a missing user',$throws(fn()=>$two->follow(99999,true),OutOfBoundsException::class));
    $two->follow(1,true);$two->follow(1,true);
    $check('Following is idempotent with one edge',(int)db()->query('SELECT COUNT(*) FROM reel_user_follows WHERE follower_id=2 AND followed_id=1')->getColumn()===1);
    $check('Following counts and ownership flags are correct',$two->profile(1)['following']&&$guest->profile(1)['followersCount']===1&&!$guest->profile(1)['following']);
    $check('Following list resolves correct person',array_column($guest->directory('',1,'following',2)['people'],'id')===[1]);
    $check('Follower list resolves correct person',array_column($guest->directory('',1,'followers',1)['people'],'id')===[2]);
    $two->follow(1,false);$two->follow(1,false);$check('Repeated unfollow stays removed',$guest->profile(1)['followersCount']===0);
    $two->follow(1,true);$one->saveSettings(['profile_public'=>'0','collection_public'=>'1']);
    $check('Hidden profile is inaccessible to visitors',$throws(fn()=>$guest->profile(1),OutOfBoundsException::class));
    $check('Hidden profile is inaccessible even to followers',$throws(fn()=>$two->profile(1),OutOfBoundsException::class));
    $check('Self may preview hidden profile',$one->profile(1)['mine']&&!$one->profile(1)['profilePublic']);
    $check('Hidden profile is absent from discovery',!in_array(1,array_column($guest->directory()['people'],'id'),true));
    $check('Hidden profile is absent from subscription lists',$two->directory('',1,'following')['total']===0);
    $check('Cannot create a subscription to a hidden profile',$throws(fn()=>$two->follow(1,true),OutOfBoundsException::class));
    $check('Can still unfollow a now hidden profile',$two->follow(1,false)['following']===false);
    $catalog=(new Fireball\ReelPlayer\Repositories\Catalog(0))->state();
    $check('Published music remains accessible when uploader hides profile',count($catalog['albums'])===1&&$catalog['albums'][0]['uploader']===null);
    $one->saveSettings(['bio'=>'Создатель коллекции','profile_public'=>'1']);
    $catalog=(new Fireball\ReelPlayer\Repositories\Catalog(0))->state();
    $check('Catalogue album and song have safe uploader links',$catalog['albums'][0]['uploader']['id']===1&&$catalog['tracks'][0]['uploader']['url']===base_href('/tape-room/people/1'));
    db()->query('UPDATE reel_albums SET published=0 WHERE id=1');
    $check('Withdrawn albums disappear from publications and saved collections',$guest->albums(1)['total']===0&&$guest->albums(2,'collection')['total']===0);
    db()->query('UPDATE reel_albums SET published=1 WHERE id=1');
    foreach(['profile_public'=>['1'],'collection_public'=>'banana','bio'=>str_repeat('ж',501)]as$key=>$value)$check('Reject invalid setting '.$key,$throws(fn()=>$two->saveSettings([$key=>$value]),InvalidArgumentException::class));
    $check('Guest cannot change profile settings',$throws(fn()=>$guest->saveSettings([]),InvalidArgumentException::class));
    db()->query('UPDATE users SET name=? WHERE id=3',['100%_Слушатель']);
    $check('Wildcard search is literal',$guest->directory('%_')['total']===1);
    $check('Search respects Unicode',$guest->directory('Создатель')['people'][0]['id']===1);
    $check('Search rejects excessive input',$throws(fn()=>$guest->directory(str_repeat('я',81)),InvalidArgumentException::class));
    for($id=10;$id<38;$id++)db()->query('INSERT INTO users(id,name,login,email,role) VALUES(?,?,?,?,\'user\')',[$id,'Слушатель '.$id,'person'.$id,'private'.$id.'@example.test']);
    $first=$guest->directory();$last=$guest->directory('',999999);
    $check('Directory uses bounded stable pagination',count($first['people'])===24&&$last['page']===2&&count($last['people'])===7&&count(array_intersect(array_column($first['people'],'id'),array_column($last['people'],'id')))===0);
    session()->remove('user');session()->set('needCSRFToken',str_repeat('a',64));
    $html=$request('/tape-room/people/2?tab=collection');$xpath=$dom($html);
    $check('Guest public route renders music profile',$xpath->query('//h1[text()="Слушатель"]')->length===1);
    $check('Bio HTML is escaped and never executed',str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>alert(1)</script>'));
    $check('Guest sees login CTA instead of follow form',$xpath->query('//form[@data-tr-follow-user]')->length===0&&str_contains($html,'Войти, чтобы подписаться'));
    $check('Public profile has no account email',!str_contains($html,'listener@example.test'));
    $check('Profile albums link to real player album',$xpath->query('//a[@href="'.base_href('/').'?album=1"]')->length===2);
    $check('Private drafts never occur in HTML',!str_contains($html,'Secret '));
    $check('Invalid scalar query fails cleanly',$throws(fn()=>$request('/tape-room/people?q[]=test'),RuntimeException::class));
    $result=$post('/tape-room/people/1/follow',['following'=>'1']);$check('Guest POST rejected by auth middleware',$result->status===401);
    session()->set('user',db()->findOne('users',2));
    $result=$post('/tape-room/people/1/follow',['following'=>'1'],false);$check('Missing CSRF is rejected before mutation',$result->status===419&&$guest->profile(1)['followersCount']===0);
    $result=$post('/tape-room/people/1/follow',['following'=>'1','owner_id'=>3,'follower_id'=>3]);
    $check('Authenticated follow ignores forged actor and returns new state',$result->status===200&&$result->data['following']===true&&$two->profile(1)['following']&&!((new Fireball\ReelPlayer\Repositories\Social(3))->profile(1)['following']));
    $result=$post('/tape-room/people/2/follow',['following'=>'1']);$check('Self follow route returns validation error',$result->status===422);
    $result=$post('/tape-room/people/1/follow',['following'=>['1']]);$check('Malformed follow input is rejected',$result->status===422);
    $result=$post('/tape-room/social/settings',['bio'=>'Route bio','profile_public'=>'1','collection_public'=>'1','owner_id'=>1]);
    $check('Settings route changes only current user',$result->status===200&&$two->settings()['bio']==='Route bio'&&$one->settings()['bio']==='Создатель коллекции');
    $result=$post('/tape-room/social/settings',['bio'=>'forbidden','profile_public'=>'1'],false);$check('Settings route requires CSRF',$result->status===419&&$two->settings()['bio']==='Route bio');
    $html=$request('/tape-room/people/1');$xpath=$dom($html);
    $check('Followed button communicates current state',$xpath->query('//button[@aria-pressed="true"]//span[text()="Вы подписаны"]')->length===1);
    $check('Real follow forms include a CSRF field',$xpath->query('//form[@data-tr-follow-user]//input[@name="needCSRFToken"]')->length===1);
    $html=$request('/profile/settings');$xpath=$dom($html);
    $check('Account settings contain independent music visibility form',$xpath->query('//section[@id="social-settings"]//form[@action="'.base_href('/tape-room/social/settings').'"]')->length===1);
    $check('Settings have no duplicate IDs',$xpath->query('//*[@id]')->length===count(array_unique(array_map(fn($n)=>$n->nodeValue,iterator_to_array($xpath->query('//@id'))))));
    $check('Follow mutation also supports ordinary form submission',$throws(fn()=>$request('/tape-room/people/1/follow','POST',['following'=>'0','needCSRFToken'=>session()->get('needCSRFToken')]),SocialRedirect::class)&&!$two->profile(1)['following']);
    $_COOKIE['tr_site_player']='1';$html=$request('/tape-room/people/1');$xpath=$dom($html);
    $check('Profile integrates with persistent mini player without duplicate audio',$xpath->query('//audio')->length===1&&$xpath->query('//*[@data-tr-page]//h1[text()="Создатель"]')->length===1);
    $check('Social client is loaded exactly once',$xpath->query('//script[contains(@src,"/social.js")]')->length===1);
    $check('All dynamic social icons exist',!str_contains($html,'ci-users')&&str_contains($html,'ci-user-plus'));
    session()->set('user',db()->findOne('users',1));$ui=$request('/tape-room/ui-kit');$check('UI library includes social components',str_contains($ui,'id="social-ui"')&&str_contains($ui,'ci-user-check'));
    foreach([false,true]as$global){
        if($global)$_COOKIE['tr_site_player']='1';else unset($_COOKIE['tr_site_player']);
        foreach(['/tape-room/people'=>'/tape-room/people','/tape-room/people?q=Creator'=>'/tape-room/people','/tape-room/people/2?tab=collection'=>'/tape-room/people','/profile/settings'=>'/profile','/tape-room/ui-kit'=>'/tape-room/ui-kit','/tape-room/changelog'=>'/tape-room/changelog']as$route=>$section){
            $navigation=$dom($request($route));$selected=$navigation->query('//*[@id="tr-site-menu"]//a[@data-tr-nav-page and @aria-current="page"]');
            $check(($global?'Persistent':'Standalone').' sidebar selects only the current section: '.$route,$selected->length===1&&$selected->item(0)->getAttribute('href')===base_href($section)&&str_contains(' '.$selected->item(0)->getAttribute('class').' ',' active '));
        }
    }
    file_put_contents(getenv('TAPE_ROOM_TEST_ROOT').'/social-profile.html',$html);
    db()->query('DROP TABLE reel_user_follows');db()->query('DROP TABLE reel_social_profiles');
    $check('Existing catalogue stays playable before social migration',count((new Fireball\ReelPlayer\Repositories\Catalog(0))->state()['albums'])===1);
    $check('Settings show pending update before migration',$two->settings()['available']===false);
    $unavailable=false;try{$guest->directory();}catch(RuntimeException $error){$unavailable=$error->getCode()===503;}
    $check('Social route reports pending migration explicitly',$unavailable);
    $check('Settings POST reports pending migration without a server error',$post('/tape-room/social/settings',['bio'=>'','profile_public'=>'1'])->status===503);
    $check('Unfollow POST reports pending migration without a server error',$post('/tape-room/people/2/follow',['following'=>'0'])->status===503);
    echo json_encode(['passed'=>count($checks),'checks'=>$checks,'database'=>'isolated SQLite','browser_verified'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),"\n";exit;
}
$scratch=__DIR__.'/.tmp/tape-room-social-'.bin2hex(random_bytes(8));mkdir($scratch,0700,true);
try{
    socialRun(['python3',__DIR__.'/tape_room_fixture_setup.py',$scratch]);
    $report=socialRun([PHP_BINARY,'-d','session.save_path='.$scratch.'/sessions',__FILE__,'--worker'],['TAPE_ROOM_TEST_ROOT'=>$scratch,'TAPE_ROOM_TEST_PUBLIC'=>'1','TMPDIR'=>$scratch]);
    json_decode($report,true,512,JSON_THROW_ON_ERROR);$artifacts=__DIR__.'/artifacts/tape-room-social';if(!is_dir($artifacts))mkdir($artifacts,0700,true);
    file_put_contents($artifacts.'/checks.json',$report);echo $report;
}finally{
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($entries as$entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($scratch);
}
