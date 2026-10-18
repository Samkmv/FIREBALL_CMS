<?php
declare(strict_types=1);
// Uses only a newly created disposable database; never changes the configured CMS database.
$worker=($argv[1]??'')==='--worker';
$scratch=$worker?realpath($argv[2]):__DIR__.'/.tmp/tape-room-social-mysql-'.bin2hex(random_bytes(8));
if(!$scratch||!str_starts_with($scratch,__DIR__.'/.tmp/tape-room-social-mysql-'))throw new RuntimeException('Unsafe scratch path.');
if(!$worker)mkdir($scratch,0700,true);
define('STORAGE',$scratch.'/storage');define('CACHE',$scratch.'/cache');define('ERROR_LOGS',$scratch.'/errors.log');
require dirname(__DIR__).'/config/config.php';require ROOT.'/vendor/autoload.php';
require ROOT.'/plugins/reel-player/src/Repositories/Social.php';
function db():FBL\Database{return $GLOBALS['social_mysql_db'];}
function get_user_avatar($avatar,$size='lg'):string{return '/test/avatar.png';}
function base_href($path=''):string{return 'http://fixture.test'.$path;}
function log_error_details($message,$context=[],$error=null):void{file_put_contents(ERROR_LOGS,$message."\n",FILE_APPEND);}
$dsn='mysql:host='.DB_SETTINGS['host'].';charset=utf8mb4'.(!empty(DB_SETTINGS['port'])?';port='.(int)DB_SETTINGS['port']:'');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$connect=static fn():PDO=>new PDO($dsn,DB_SETTINGS['username'],DB_SETTINGS['password'],$options);
$database=$worker?$argv[3]:'fbtest_social_'.bin2hex(random_bytes(8));
if(!preg_match('/^fbtest_social_[a-f0-9]{16}$/D',$database))throw new RuntimeException('Unsafe test database.');
if($worker){
    $pdo=$connect();$pdo->exec('USE `'.$database.'`');$GLOBALS['social_mysql_db']=new FBL\Database($pdo);
    $actor=(int)$argv[4];file_put_contents($scratch.'/ready-'.$actor,'1');$deadline=microtime(true)+8;
    while(!is_file($scratch.'/go')){if(microtime(true)>$deadline)throw new RuntimeException('Worker barrier timed out.');usleep(10000);}
    $repo=new Fireball\ReelPlayer\Repositories\Social($actor);
    for($i=0;$i<8;$i++){$repo->follow($actor===1?2:1,true);$repo->follow($actor===1?2:1,false);}
    $repo->follow($actor===1?2:1,true);echo 'OK';exit;
}
$created=false;$workers=[];$checks=[];
$check=static function(string $label,bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
try{
    $pdo=$connect();$pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$pdo->exec('USE `'.$database.'`');$GLOBALS['social_mysql_db']=new FBL\Database($pdo);
    $pdo->exec('CREATE TABLE users(id INT(10) UNSIGNED PRIMARY KEY,name VARCHAR(100),avatar VARCHAR(255)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO users VALUES(1,'Первый слушатель',NULL),(2,'Второй слушатель',NULL),(3,'Удаляемый тестовый слушатель',NULL)");
    $pdo->exec("CREATE TABLE reel_albums(id INT UNSIGNED PRIMARY KEY,owner_id INT UNSIGNED,published TINYINT,moderation_status VARCHAR(16)) ENGINE=InnoDB");
    $runner=new App\Services\SqlFileRunner();$sql=file_get_contents(ROOT.'/plugins/reel-player/migrations/013_social_profiles.sql');$runner->executeDatabase($sql);$runner->executeDatabase($sql);
    $check('Real migration is repeatable on MySQL',count($pdo->query("SHOW TABLES LIKE 'reel_%'")->fetchAll())===3);
    $first=new Fireball\ReelPlayer\Repositories\Social(1);$second=new Fireball\ReelPlayer\Repositories\Social(2);
    $first->saveSettings(['bio'=>'Музыка 🎵','profile_public'=>'1','collection_public'=>'1']);$first->saveSettings(['bio'=>'Обновлено 🎵','profile_public'=>'1','collection_public'=>'1']);
    $check('Settings upsert retains one row and Unicode',(int)$pdo->query('SELECT COUNT(*) FROM reel_social_profiles WHERE owner_id=1')->fetchColumn()===1&&$first->settings()['bio']==='Обновлено 🎵');
    $first->follow(2,true);$first->follow(2,true);$check('Composite key prevents duplicate follows',(int)$pdo->query('SELECT COUNT(*) FROM reel_user_follows')->fetchColumn()===1);
    $indices=$pdo->query('SHOW INDEX FROM reel_user_follows')->fetchAll();$check('Reverse lookup uses the followed-user index',in_array('idx_reel_followed',array_column($indices,'Key_name'),true));
    $rejected=false;try{$pdo->exec('INSERT INTO reel_user_follows VALUES(999999,2,NOW())');}catch(PDOException $error){$rejected=$error->getCode()==='23000';}$check('Foreign key rejects nonexistent account',$rejected);
    foreach([1,2]as$actor){
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$scratch,$database,(string)$actor],[1=>['pipe','w'],2=>['file',$scratch.'/worker-'.$actor.'.log','a']],$pipes,ROOT,array_replace(getenv(),['TMPDIR'=>$scratch]));
        if(!is_resource($process))throw new RuntimeException('Cannot start MySQL test worker.');$workers[]=[$process,$pipes[1]];
    }
    $deadline=microtime(true)+8;while(!is_file($scratch.'/ready-1')||!is_file($scratch.'/ready-2')){if(microtime(true)>$deadline)throw new RuntimeException('Concurrent workers did not start.');usleep(10000);}file_put_contents($scratch.'/go','1');
    $ok=true;foreach($workers as[$process,$pipe]){$out=stream_get_contents($pipe);fclose($pipe);$ok=proc_close($process)===0&&$out==='OK'&&$ok;}$workers=[];
    $check('Reciprocal follows serialize without deadlock or lost updates',$ok&&(int)$pdo->query('SELECT COUNT(*) FROM reel_user_follows')->fetchColumn()===2);
    (new Fireball\ReelPlayer\Repositories\Social(3))->saveSettings(['profile_public'=>'1']);
    $first->follow(3,true);(new Fireball\ReelPlayer\Repositories\Social(3))->follow(2,true);
    $pdo->exec('DELETE FROM users WHERE id=3');
    $check('Deleting an account cascades its settings and both follow directions',(int)$pdo->query('SELECT COUNT(*) FROM reel_social_profiles WHERE owner_id=3')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM reel_user_follows WHERE follower_id=3 OR followed_id=3')->fetchColumn()===0);
    $check('Unrelated subscriptions and account data survive deletion',(int)$pdo->query('SELECT COUNT(*) FROM reel_user_follows')->fetchColumn()===2&&$first->settings()['bio']==='Обновлено 🎵');
    $report=json_encode(['passed'=>count($checks),'checks'=>$checks,'database'=>'disposable MySQL'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
    $artifacts=__DIR__.'/artifacts/tape-room-social';if(!is_dir($artifacts))mkdir($artifacts,0700,true);file_put_contents($artifacts.'/mysql.json',$report);echo $report;
}finally{
    foreach($workers as[$process,$pipe]){proc_terminate($process);fclose($pipe);proc_close($process);}
    if($created)$pdo->exec('DROP DATABASE `'.$database.'`');
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($entries as$entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($scratch);
}
