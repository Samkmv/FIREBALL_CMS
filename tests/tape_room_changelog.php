<?php
declare(strict_types=1);
// Runs real routing, middleware and theme rendering against isolated SQLite.
// No listening socket, HTTP connection or configured production database.
$project=dirname(__DIR__);
function run(array $args,array $env=[]):string{
    $process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env?array_replace(getenv(),$env):null);
    if(!is_resource($process))throw new RuntimeException('Cannot start test process.');
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException($stderr.$stdout);
    return $stdout;
}
if(($argv[1]??'')==='--worker'){
    $_SERVER['HTTP_HOST']='fixture.test';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/tape-room/changelog';
    $_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['SERVER_PROTOCOL']='HTTP/1.1';
    putenv('TAPE_ROOM_TEST_BOOTSTRAP_ONLY=1');require __DIR__.'/tape_room_fixture.php';
    final class JournalRedirect extends RuntimeException{}
    final class JournalResponse extends FBL\Response{
        public function redirect($url=''){throw new JournalRedirect($this->sanitizeRedirectTarget((string)$url));}
    }
    $app->response=new JournalResponse();
    $checks=[];
    $verify=static function(string $name,bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException($name);$checks[]=$name;};
    $render=static function(string $path)use($app):string{
        $_SERVER['REQUEST_URI']=$path;$app->request->uri=trim($path,'/');$app->request->rowUri=$path;
        return (string)$app->router->dispatch();
    };
    $document=static function(string $html):DOMXPath{$dom=new DOMDocument();$before=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($before);return new DOMXPath($dom);};
    $data=require $project.'/plugins/reel-player/resources/changelog.php';
    $metadata=json_decode(file_get_contents($project.'/plugins/reel-player/plugin.json'),true,512,JSON_THROW_ON_ERROR);
    $verify('Latest journal entry matches the installed plugin',$data['releases'][0]['version']===$metadata['version']);
    $dates=array_column($data['releases'],'date');$sorted=$dates;rsort($sorted);$verify('Release dates are chronological',$dates===$sorted);
    $covered=[];
    foreach($data['releases'] as $entry){
        if(str_contains($entry['version'],'–')){
            [$first,$last]=explode('–',$entry['version']);$parts=explode('.',$first);$prefix=$parts[0].'.'.$parts[1].'.';
            for($patch=(int)$parts[2];$patch<=(int)explode('.',$last)[2];$patch++)$covered[]=$prefix.$patch;
        }else $covered[]=$entry['version'];
    }
    $past=[];foreach(array_filter(explode("\n",trim(run(['git','log','--format=%H','--','plugins/reel-player/plugin.json']))))as$commit){
        $old=json_decode(run(['git','show',$commit.':plugins/reel-player/plugin.json']),true,512,JSON_THROW_ON_ERROR);$past[]=$old['version'];
    }
    $verify('Every saved plugin version is represented',array_diff($past,$covered)===[]);
    $rc=array_values(array_filter($data['releases'],fn($entry)=>$entry['version']==='2.0.0-rc.1'));
    $verify('Removed release candidate is archived',count($rc)===1&&($rc[0]['archived']??false)===true);
    $ids=array_column($data['capabilities'],'id');$verify('Capability identifiers are unique',count(array_unique($ids))===count($ids));
    foreach($data['capabilities']as$item){
        $verify('Valid capability status: '.$item['id'],in_array($item['status'],['done','partial','absent'],true));
        if($item['status']==='partial')$verify('Limits are stated: '.$item['id'],!empty($item['remaining']));
    }
    session()->remove('user');$destination='';try{$render('/tape-room/changelog');}catch(JournalRedirect $error){$destination=$error->getMessage();}
    $verify('Guest is redirected to login',$destination===base_href('/login'));
    session()->set('user',db()->findOne('users',2));$destination='';try{$render('/tape-room/changelog');}catch(JournalRedirect $error){$destination=$error->getMessage();}
    $verify('Ordinary listener is redirected without reading journal',$destination===base_href('/profile'));
    $home=$render('/');$verify('Ordinary listener has no journal menu link',!str_contains($home,'href="'.base_href('/tape-room/changelog').'"'));
    foreach(['admin'=>3,'creator'=>1]as$role=>$id){
        session()->set('user',db()->findOne('users',$id));$html=$render('/tape-room/changelog');$xpath=$document($html);
        $verify($role.': route renders the journal',$xpath->query('//h1[text()="Change Log"]')->length===1);
        $verify($role.': two accessible content tabs',$xpath->query('//button[@role="tab"]')->length===2&&$xpath->query('//section[@role="tabpanel"]')->length===2);
        $targetsValid=true;foreach($xpath->query('//button[@data-bs-target]')as$control){$target=ltrim($control->getAttribute('data-bs-target'),'#');if($xpath->query('//*[@id="'.$target.'"]')->length!==1)$targetsValid=false;}
        $verify($role.': tab and accordion controls have unique existing targets',$targetsValid);
        $verify($role.': every release can be opened',$xpath->query('//h3[@class="accordion-header"]')->length===count($data['releases']));
        $verify($role.': all capability cards rendered',$xpath->query('//article[starts-with(@id,"roadmap-")]')->length===count($data['capabilities']));
        $verify($role.': archived experiment clearly labelled',str_contains($html,'Архив · убрано из main')&&str_contains($html,'не считаются реализованными'));
        $verify($role.': UI library is linked',$xpath->query('//a[@href="'.base_href('/tape-room/ui-kit').'"]')->length>0);
        $verify($role.': active journal navigation',$xpath->query('//nav[@aria-label="Документация Tape Room"]/a[@href="'.base_href('/tape-room/changelog').'" and @aria-current="page"]')->length===1);
        $verify($role.': sections use unique IDs',$xpath->query('//*[@id]')->length===count(array_unique(array_map(static fn($node)=>$node->nodeValue,iterator_to_array($xpath->query('//@id'))))));
        $assetsExist=true;foreach($xpath->query('//link[@rel="stylesheet"]/@href | //script[@src]/@src')as$asset){$path=(string)parse_url($asset->nodeValue,PHP_URL_PATH);$file=str_starts_with($path,'/themes/')?$project.$path:$project.'/public'.$path;if(!is_file($file))$assetsExist=false;}
        $verify($role.': all stylesheet and script references resolve to local files',$assetsExist);
        if($data['roadmap_source']==='awaiting-original')$verify($role.': original roadmap limitation is disclosed',str_contains($html,'Сверка исходного плана ожидается'));
        $ui=$render('/tape-room/ui-kit');$verify($role.': UI library links back to journal',str_contains($ui,'href="'.base_href('/tape-room/changelog').'"'));
        $uiDom=$document($ui);
        $verify($role.': standalone UI loads the real player stylesheet once',$uiDom->query('//head/link[contains(@href,"/css/player.css")]')->length===1);
        $verify($role.': UI demo toggles do not change real playback preferences',$uiDom->query('//*[@id="mini-player-ui"]//*[@data-tr-mini-toggle or @data-tr-site-player-toggle]')->length===0&&$uiDom->query('//*[@id="mini-player-ui"]//*[@data-tr-demo-mode]')->length===2);
        $verify($role.': UI examples have unique IDs',$uiDom->query('//*[@id]')->length===count(array_unique(array_map(static fn($node)=>$node->nodeValue,iterator_to_array($uiDom->query('//@id'))))));
        $iconMap=array_unique(require $project.'/themes/tape-room/includes/icon-map.php');
        $verify($role.': UI icon catalogue follows the actual site map',$uiDom->query('//*[@id="icons"]//code')->length===count($iconMap));
        $verify($role.': UI sidebar example shows the active section',$uiDom->query('//nav[@aria-label="Пример текущего раздела"]/a[@aria-current="page"]')->length===1);

        file_put_contents(getenv('TAPE_ROOM_TEST_ROOT').'/'.$role.'-changelog.html',$html);
    }
    $_COOKIE['tr_site_player']='1';$embedded=$document($render('/tape-room/changelog'));
    $verify('Global mini-player document includes exactly one audio element',$embedded->query('//audio')->length===1);
    $verify('Journal stays in the replaceable page slot',$embedded->query('//*[@data-tr-page]//h1[text()="Change Log"]')->length===1);
    $verify('Global player and journal have no duplicate navigation',$embedded->query('//nav[@aria-label="Навигация Tape Room"]')->length===1);
    $globalUi=$document($render('/tape-room/ui-kit'));
    $verify('Global UI has one player stylesheet and one real audio element',$globalUi->query('//head/link[contains(@href,"/css/player.css")]')->length===1&&$globalUi->query('//audio')->length===1);
    echo json_encode(['passed'=>count($checks),'checks'=>$checks,'browser_verified'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),"\n";exit;
}
$scratch=__DIR__.'/.tmp/tape-room-changelog-offline-'.bin2hex(random_bytes(8));mkdir($scratch,0700,true);
try{
    run(['python3',__DIR__.'/tape_room_fixture_setup.py',$scratch]);
    $report=run([PHP_BINARY,'-d','session.save_path='.$scratch.'/sessions',__FILE__,'--worker'],['TAPE_ROOM_TEST_ROOT'=>$scratch,'TAPE_ROOM_TEST_PUBLIC'=>'1','TMPDIR'=>$scratch]);
    json_decode($report,true,512,JSON_THROW_ON_ERROR);
    $artifacts=__DIR__.'/artifacts/tape-room-changelog';if(!is_dir($artifacts))mkdir($artifacts,0700,true);
    file_put_contents($artifacts.'/checks.json',$report);echo $report;
}finally{
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($entries as$entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($scratch);
}
