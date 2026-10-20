<?php
declare(strict_types=1);
// Uses a newly created database, never the configured site's database.
$project=dirname(__DIR__);require $project.'/vendor/autoload.php';
$scratch=__DIR__.'/.tmp/update-recovery-'.bin2hex(random_bytes(8));mkdir($scratch,0700,true);
define('ROOT',$scratch.'/installation');define('CONFIG',ROOT.'/config');define('WWW',ROOT.'/public');define('STORAGE',ROOT.'/storage');define('PLUGINS',ROOT.'/plugins');define('CACHE',ROOT.'/tmp/cache');
$testPort=getenv('FIREBALL_TEST_DB_PORT');
if($testPort!==false){
    if(!ctype_digit($testPort)||(int)$testPort<1024||(int)$testPort>65535)throw new RuntimeException('Invalid isolated MySQL test port.');
    $connection=['host'=>'127.0.0.1','port'=>(int)$testPort,'username'=>'root','password'=>''];
}else{
    $local=require $project.'/config/config.local.php';$connection=$local['DB_SETTINGS']??[];
}
$databaseName='fbl_update_test_'.bin2hex(random_bytes(8));$created=false;
$dsn='mysql:host='.($connection['host']??'127.0.0.1').';port='.(int)($connection['port']??3306).';charset=utf8mb4';
define('DB_SETTINGS',array_replace($connection,['database'=>$databaseName,'charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]]));
function return_translation(string $key): string{return $key;}
function base_url(string $path='/'): string{return 'https://fixture.test'.$path;}
function cache(): FBL\Cache {static $cache;return $cache??=new FBL\Cache();}
function db(): FBL\Database{return $GLOBALS['testDatabase'];}
function log_error_details(string $message,array $context=[],?Throwable $exception=null): void{file_put_contents(STORAGE.'/test-errors.log',$message.' '.($exception?->getMessage()??'')."\n",FILE_APPEND);}
final class RecoverySettings extends App\Models\SiteSetting {
    public function __construct(private PDO $pdo){}
    public function all(): array{$result=[];foreach($this->pdo->query('SELECT setting_key,setting_value FROM site_settings') as $row)$result[$row['setting_key']]=$row['setting_value'];return $result;}
    public function get(string $key,string $default=''): string{return $this->all()[$key]??$default;}
    public function setMany(array $values): void{$query=$this->pdo->prepare('INSERT INTO site_settings VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($values as $key=>$value)$query->execute([$key,$value]);}
}
final class RecoveryUpdater extends App\Services\UpdateCenter {
    public bool $failMigration=false;
    public function __construct(App\Models\SiteSetting $settings,private PDO $pdo){parent::__construct($settings);}
    protected function refreshLastCheckAfterUpdate(): void{}
    protected function tryFetchLatestRelease(string $repository,string $token,string $branch): ?array{return null;}
    protected function clearRuntimeCache(): void{}
    protected function runPendingMigrations(): void{
        $this->pdo->exec('CREATE TABLE migrated_data (id INT PRIMARY KEY) ENGINE=InnoDB');
        $this->pdo->exec('UPDATE music SET title="migrated" WHERE id=1');
        if($this->failMigration){
            if(!is_dir(STORAGE.'/component-installations/plugins'))mkdir(STORAGE.'/component-installations/plugins',0700,true);
            file_put_contents(STORAGE.'/component-installations/plugins/introduced.json','{"schema":1}');
            throw new RuntimeException('Simulated migration failure after committed DDL');
        }
    }
}
final class PackageUpdater extends App\Services\PluginUpdateService {
    public bool $race=false;
    public bool $publicQuotaBlocked=false;
    public array $requests=[];
    public array $downloads=[];
    public function __construct(FBL\Plugins\PluginManager $manager,App\Models\SiteSetting $settings,private array $metadata,private string $archive,private ?array $baselineMetadata=null,private string $baselineArchive=''){parent::__construct($manager);$this->siteSettings=$settings;}
    protected function httpGet(string $url,array $headers=[]): array{
        $this->requests[]=['url'=>$url,'headers'=>$headers];
        if($this->publicQuotaBlocked){
            if(str_contains($url,'.git/info/refs?'))return ['status_code'=>200,'body'=>str_repeat('a',40)." refs/heads/main\0multi_ack\n"];
            if(str_starts_with($url,'https://raw.githubusercontent.com/fixture/cms/'.str_repeat('a',40).'/plugins/reel-player/'))return ['status_code'=>200,'body'=>json_encode($this->metadata)];
            return ['status_code'=>403,'body'=>'{"message":"API rate limit exceeded"}','headers'=>['x-ratelimit-remaining'=>'0']];
        }
        if(str_starts_with($url,'https://raw.githubusercontent.com/')||str_contains($url,'.git/info/refs?'))return ['status_code'=>404,'body'=>'Not public'];
        if(str_contains($url,'/commits?'))$body=[['sha'=>str_repeat('b',40)]];
        elseif(str_contains($url,'/commits/'))$body=['sha'=>str_repeat('a',40)];
        else $body=['content'=>base64_encode(json_encode(str_contains($url,'ref='.str_repeat('b',40))?$this->baselineMetadata:$this->metadata))];
        return ['status_code'=>200,'body'=>json_encode($body)];
    }
    public function baseline(string $slug,array $metadata,string $workspace): array{return $this->assertInstalledPluginPackage($slug,$metadata,$metadata['update'],$workspace);}
    protected function downloadFile(string $url,string $destination,array $headers=[]): void{
        $this->downloads[]=['url'=>$url,'headers'=>$headers];
        if($this->publicQuotaBlocked && $url!=='https://codeload.github.com/fixture/cms/zip/'.str_repeat('a',40))throw new RuntimeException('Rate-limited public updates may download only the pinned public package');
        copy(str_ends_with($url,str_repeat('b',40))?$this->baselineArchive:$this->archive,$destination);
    }
    protected function clearRuntimeCache(): void{}
    protected function createPreUpdateBackups(): void{parent::createPreUpdateBackups();if($this->race)file_put_contents(PLUGINS.'/reel-player/Plugin.php',"<?php return 'concurrent edit';");}
}
$checks=[];
function verify(string $label,bool $ok): void{global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function rejects(callable $op): bool{try{$op();return false;}catch(Throwable){return true;}}
function put(string $path,string $data): void{$target=ROOT.'/'.$path;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);file_put_contents($target,$data);}
function command(array $args,string $cwd=ROOT,array $environment=[]): string{
    $p=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd,$environment ? array_replace(getenv(),$environment):null);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Fixture command failed: '.$err);return trim($out);
}
function git(array $args): string{return command(['git','-C',ROOT,...$args]);}
function version(string $version): void{
    put('config/version.php',"<?php return ['version'=>'".$version."'];");
    put('update.json',json_encode(['version'=>$version,'minVersion'=>'1.0.0','type'=>'patch','protected'=>['storage','public/uploads','config/config.local.php'],'update'=>['app','config','public','plugins','vendor','composer.json','composer.lock','update.json']]));
}
try {
    $server=new PDO($dsn,$connection['username']??'',$connection['password']??'',DB_SETTINGS['options']);
    $server->exec('CREATE DATABASE `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $pdo=new PDO($dsn.';dbname='.$databaseName,$connection['username']??'',$connection['password']??'',DB_SETTINGS['options']);$GLOBALS['testDatabase']=new FBL\Database($pdo);
    $pdo->exec('CREATE TABLE site_settings (setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE music (id INT PRIMARY KEY,title TEXT,payload BLOB) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE audit (id INT PRIMARY KEY,changes INT) ENGINE=InnoDB');$pdo->exec('INSERT INTO audit VALUES (1,0)');
    $pdo->exec('CREATE TRIGGER music_update AFTER UPDATE ON music FOR EACH ROW UPDATE audit SET changes=changes+1 WHERE id=1');
    $insert=$pdo->prepare('INSERT INTO music VALUES (1,?,?)');$insert->execute(['Музыка 🎵',"\0\xff\n'\\"]);
    mkdir(STORAGE,0700,true);$settings=new RecoverySettings($pdo);$settings->setMany(['updater_github_repository'=>'fixture/cms','updater_github_branch'=>'main','update_channel'=>'dev','site_title'=>'User site']);
    put('.gitignore',"/storage/\n/public/uploads/\n/config/config.local.php\n/composer-home/\n/composer-cache/\n/tmp/\n");
    foreach(App\Services\PwaIconStorage::names() as $name)put('public/assets/default/pwa/'.$name,(string)file_get_contents($project.'/public/assets/default/pwa/'.$name));
    put('public/assets/img/fbl_logo.png',(string)file_get_contents($project.'/public/assets/default/pwa/icon-512.png'));
    put('public/index.php','<?php echo "fixture";');put('app/main.php','<?php return "base";');version('1.0.0');
    $pluginBase=['slug'=>'reel-player','name'=>'Tape Room fixture','version'=>'1.0.0','update'=>['enabled'=>true,'repository'=>'fixture/cms','path'=>'plugins/reel-player']];
    put('plugins/reel-player/plugin.json',json_encode($pluginBase));put('plugins/reel-player/Plugin.php','<?php return "plugin base";');
    put('storage/composer-package/composer.json','{"name":"fixture/audio","version":"1.0.0","autoload":{"psr-4":{"Fixture\\\\":"src/"}}}');put('storage/composer-package/src/Audio.php','<?php namespace Fixture; class Audio {}');
    put('composer.json',json_encode(['name'=>'fixture/cms','repositories'=>[['type'=>'path','url'=>'storage/composer-package','options'=>['symlink'=>false]]],'require'=>['fixture/audio'=>'1.0.0']]));
    $composer=[PHP_BINARY,getenv('FIREBALL_TEST_COMPOSER') ?: '/usr/local/bin/composer'];$env=['COMPOSER_HOME'=>ROOT.'/composer-home','COMPOSER_CACHE_DIR'=>ROOT.'/composer-cache','COMPOSER_DISABLE_NETWORK'=>'1','TMPDIR'=>$scratch];
    command([...$composer,'update','--no-interaction','--no-plugins','--no-scripts','--no-audit'],ROOT,$env);
    git(['init','-q','-b','main']);git(['config','user.name','Recovery fixture']);git(['config','user.email','fixture@example.test']);git(['add','.']);git(['commit','-qm','base']);$base=git(['rev-parse','HEAD']);
    put('app/main.php','<?php return "release 1";');version('1.1.0');put('plugins/reel-player/Plugin.php','<?php return "bundled 1.1";');git(['add','app','config/version.php','update.json','plugins']);git(['commit','-qm','release 1']);$release=git(['rev-parse','HEAD']);
    command(['git','clone','--bare','--quiet',ROOT,$scratch.'/remote.git']);git(['remote','add','origin',$scratch.'/remote.git']);git(['checkout','-q','-B','main',$base]);
    command([...$composer,'install','--no-interaction','--no-plugins','--no-scripts','--optimize-autoloader'],ROOT,$env);
    verify('Actual composer install changes tracked generated autoload files',str_contains(git(['status','--porcelain']),'vendor/'));
    put('config/config.local.php','<?php return ["user-setting"=>true];');put('public/uploads/music/keep.flac','user music');
    $manager=new FBL\Plugins\PluginManager();
    App\Services\SchemaMigration::run(fn()=>(new ReflectionMethod($manager,'ensureSchema'))->invoke($manager));
    $pdo->exec("INSERT INTO plugins (slug,name,version,status) VALUES ('reel-player','Tape Room fixture','1.0.0','inactive')");
    $pluginNew=array_replace($pluginBase,['version'=>'9.0.0']);
    $pluginCode='<?php class FireballPluginReelPlayer implements \\FBL\\Plugins\\PluginInterface {public function install(): void {} public function uninstall(): void {} public function activate(): void {} public function deactivate(): void {} public function boot(): void {}} return "independent package";';
    $archive=$scratch.'/plugin-package.zip';$package=new ZipArchive();$package->open($archive,ZipArchive::CREATE);
    $package->addFromString('repository/plugins/reel-player/plugin.json',json_encode($pluginNew));$package->addFromString('repository/plugins/reel-player/Plugin.php',$pluginCode);$package->close();
    $packageUpdater=new PackageUpdater($manager,$settings,$pluginNew,$archive);$packageResult=$packageUpdater->update('reel-player');
    verify('Admin plugin update installs a validated independent package',$packageResult['status']==='success' && json_decode(file_get_contents(PLUGINS.'/reel-player/plugin.json'),true)['version']==='9.0.0');
    verify('Admin plugin update records the exact installed package',(new App\Services\ManagedPluginInstallations(ROOT))->isVerified('reel-player'));
    $nextPlugin=array_replace($pluginNew,['version'=>'10.0.0']);$racingUpdater=new PackageUpdater($manager,$settings,$nextPlugin,$archive);$racingUpdater->race=true;
    put('plugins/reel-player/Plugin.php','<?php return "manual plugin edit";');
    verify('Admin plugin update refuses manual code edits',rejects(fn()=>$racingUpdater->update('reel-player')) && str_contains(file_get_contents(PLUGINS.'/reel-player/Plugin.php'),'manual plugin edit'));
    put('plugins/reel-player/Plugin.php',$pluginCode);
    $nextArchive=$scratch.'/next-plugin.zip';$package=new ZipArchive();$package->open($nextArchive,ZipArchive::CREATE);
    $package->addFromString('repository/plugins/reel-player/plugin.json',json_encode($nextPlugin));$package->addFromString('repository/plugins/reel-player/Plugin.php',$pluginCode);$package->close();
    $racingUpdater=new PackageUpdater($manager,$settings,$nextPlugin,$nextArchive);$racingUpdater->race=true;
    verify('Plugin code changed after backup is not overwritten',rejects(fn()=>$racingUpdater->update('reel-player')) && str_contains(file_get_contents(PLUGINS.'/reel-player/Plugin.php'),'concurrent edit') && !is_file(STORAGE.'/update.maintenance'));
    put('plugins/reel-player/Plugin.php',$pluginCode);

    $receiptFile=STORAGE.'/component-installations/plugins/reel-player.json';$receipt=file_get_contents($receiptFile);unlink($receiptFile);
    $savedPluginState=$manager->setting('reel-player','__plugin_update_state',[]);
    rename(ROOT.'/.git',$scratch.'/held-git');
    try {
        $legacyUpdater=new PackageUpdater($manager,$settings,$nextPlugin,$nextArchive,$pluginNew,$archive);
        $probe=STORAGE.'/legacy-probe';mkdir($probe,0700);
        verify('Old ZIP plugin is verified against an official historical package',count($legacyUpdater->baseline('reel-player',$pluginNew,$probe))===2 && !file_exists($receiptFile));
        put('plugins/reel-player/Plugin.php','<?php return "unknown ZIP customization";');
        verify('Old ZIP plugin with manual changes cannot be approved by its version alone',rejects(fn()=>$legacyUpdater->baseline('reel-player',$pluginNew,$probe)));
        put('plugins/reel-player/Plugin.php',$pluginCode);
        $legacyResult=$legacyUpdater->update('reel-player');
        verify('Old ZIP installation completes a real plugin upgrade without a pre-existing receipt',$legacyResult['status']==='success' && json_decode(file_get_contents(PLUGINS.'/reel-player/plugin.json'),true)['version']==='10.0.0');
        verify('Legacy upgrade records the new package and leaves maintenance mode',(new App\Services\ManagedPluginInstallations(ROOT))->isVerified('reel-player') && !is_file(STORAGE.'/update.maintenance'));

        // Reproduce Maxipapa: old official ZIP, no receipt/Git checkout, a
        // nonempty stale token, and every REST endpoint over its shared quota.
        // Public HEAD/raw/archive plus the core catalog must suffice end to end.
        put('plugins/reel-player/Plugin.php',$pluginCode);put('plugins/reel-player/plugin.json',json_encode($pluginNew));unlink($receiptFile);
        $pdo->exec("UPDATE plugins SET version='9.0.0' WHERE slug='reel-player'");
        put('config/plugin-baselines.json',json_encode(['schema'=>1,'repository'=>'fixture/cms','plugins'=>['reel-player'=>[[
            'version'=>'9.0.0','commit'=>str_repeat('b',40),
            'required_files'=>['Plugin.php'=>hash('sha256',$pluginCode),'plugin.json'=>hash('sha256',json_encode($pluginNew))],
            'allowed_extras'=>[],
        ]]]]));
        $settings->setMany(['updater_github_token'=>'fixture-invalid-token-not-a-real-secret']);
        $publicManager=new FBL\Plugins\PluginManager();
        $publicUpdater=new PackageUpdater($publicManager,$settings,$nextPlugin,$nextArchive);$publicUpdater->publicQuotaBlocked=true;
        $publicResult=$publicUpdater->update('reel-player');
        verify('Legacy ZIP upgrades successfully while all GitHub REST endpoints are rate-limited',$publicResult['status']==='success' && json_decode(file_get_contents(PLUGINS.'/reel-player/plugin.json'),true)['version']==='10.0.0');
        verify('Public legacy upgrade makes no REST or historical source request',count($publicUpdater->requests)===2 && !array_filter($publicUpdater->requests,fn($request)=>str_starts_with($request['url'],'https://api.github.com/')));
        verify('Public legacy upgrade downloads only the commit-pinned new archive',count($publicUpdater->downloads)===1 && $publicUpdater->downloads[0]['url']==='https://codeload.github.com/fixture/cms/zip/'.str_repeat('a',40));
        verify('A stale configured token is never sent to public update endpoints',!array_filter(array_merge($publicUpdater->requests,$publicUpdater->downloads),fn($request)=>(bool)array_filter($request['headers'],fn($header)=>(bool)preg_match('/^(?:authorization|cookie):/i',$header))));
        verify('Rate-limited legacy upgrade records a verified new receipt and leaves maintenance mode',(new App\Services\ManagedPluginInstallations(ROOT))->isVerified('reel-player') && !is_file(STORAGE.'/update.maintenance'));
    } finally {
        if(is_file(CONFIG.'/plugin-baselines.json'))unlink(CONFIG.'/plugin-baselines.json');
        $settings->setMany(['updater_github_token'=>'']);
        put('plugins/reel-player/Plugin.php',$pluginCode);put('plugins/reel-player/plugin.json',json_encode($pluginNew));
        file_put_contents($receiptFile,$receipt);chmod($receiptFile,0600);rename($scratch.'/held-git',ROOT.'/.git');
        $pdo->exec("UPDATE plugins SET version='9.0.0' WHERE slug='reel-player'");$manager->setSetting('reel-player','__plugin_update_state',$savedPluginState);
    }
    $icon=imagecreatetruecolor(48,48);imagefill($icon,0,0,0x334455);imagepng($icon,WWW.'/assets/default/pwa/icon-48.png');imagedestroy($icon);$oldIconHash=hash_file('sha256',WWW.'/assets/default/pwa/icon-48.png');
    $updater=new RecoveryUpdater($settings,$pdo);$result=$updater->runUpdate();
    $pwa=new App\Services\PwaService($settings);$activeIcon=WWW.parse_url($pwa->icons()['sizes'][48],PHP_URL_PATH);
    verify('Full update migrates legacy PWA icons after code and DB backup',str_contains($activeIcon,'/uploads/pwa/legacy-') && hash_file('sha256',$activeIcon)===$oldIconHash && hash_file('sha256',WWW.'/assets/default/pwa/icon-48.png')===hash_file('sha256',$project.'/public/assets/default/pwa/icon-48.png'));
    verify('Full Git update fetches and installs the new main commit',$result['status']==='success' && git(['rev-parse','HEAD'])===$release);
    verify('Successful update leaves maintenance mode',!is_file(STORAGE.'/update.maintenance'));
    verify('Migration and health checks complete before success',$pdo->query('SELECT title FROM music WHERE id=1')->fetchColumn()==='migrated' && is_file(ROOT.'/vendor/autoload.php'));
    verify('Independent plugin, local configuration, user files and settings survive',str_contains(file_get_contents(PLUGINS.'/reel-player/Plugin.php'),'independent package') && is_file(CONFIG.'/config.local.php') && file_get_contents(WWW.'/uploads/music/keep.flac')==='user music' && $settings->get('site_title')==='User site');
    $descriptor=json_decode(file_get_contents(STORAGE.'/update-recovery.json'),true);
    verify('Code and database snapshots have verified hashes',hash_equals($descriptor['files_sha256'],hash_file('sha256',$descriptor['files'])) && hash_equals($descriptor['database_sha256'],hash_file('sha256',$descriptor['database'])));
    verify('Recovery snapshots are private',(fileperms($descriptor['files'])&0777)===0600 && (fileperms($descriptor['database'])&0777)===0600 && (fileperms(STORAGE.'/update-recovery.json')&0777)===0600);
    // Publish a second release and fail only after its DDL has committed.
    $pdo->exec('DROP TABLE migrated_data');$pdo->exec('UPDATE music SET title="Before failed update" WHERE id=1');$pdo->exec('UPDATE audit SET changes=7 WHERE id=1');
    version('1.2.0');put('app/introduced.php','<?php return "introduced by failed version";');git(['add','app/introduced.php','config/version.php','update.json']);git(['commit','-qm','release 2']);$second=git(['rev-parse','HEAD']);git(['push','--quiet','origin','main']);git(['checkout','-q','-B','main',$release]);
    $updater=new RecoveryUpdater($settings,$pdo);$updater->failMigration=true;verify('Failure after committed migration DDL is reported',rejects(fn()=>$updater->runUpdate()));
    verify('Failed update keeps maintenance and recovery available',is_file(STORAGE.'/update.maintenance') && is_file(STORAGE.'/update-recovery.json') && git(['rev-parse','HEAD'])===$second);
    verify('A second update cannot run during pending recovery',rejects(fn()=>(new RecoveryUpdater($settings,$pdo))->runUpdate()));
    $original=file_get_contents(STORAGE.'/update-recovery.json');$descriptor=json_decode($original,true);$descriptor['database_sha256']=str_repeat('0',64);file_put_contents(STORAGE.'/update-recovery.json',json_encode($descriptor));
    $recovery=new App\Services\UpdateRecoveryService(ROOT);verify('Corrupt database snapshot blocks recovery before data changes',rejects(fn()=>$recovery->restore($pdo)) && $pdo->query('SELECT title FROM music WHERE id=1')->fetchColumn()==='migrated');file_put_contents(STORAGE.'/update-recovery.json',$original);
    $held=fopen(STORAGE.'/update.lock','c+');flock($held,LOCK_EX);verify('Recovery cannot race another installation process',rejects(fn()=>$recovery->restore($pdo)));flock($held,LOCK_UN);fclose($held);
    $recovery->restore($pdo);
    verify('Recovery restores the original Git commit and removes introduced code',git(['rev-parse','HEAD'])===$release && !file_exists(ROOT.'/app/introduced.php'));
    verify('Recovery removes tables introduced by failed migrations',(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='migrated_data'")->fetchColumn()===0);
    $row=$pdo->query('SELECT * FROM music WHERE id=1')->fetch();verify('Recovery restores rows and binary data exactly',$row['title']==='Before failed update' && $row['payload']==="\0\xff\n'\\");
    verify('Triggers are restored after rows without firing during import',(int)$pdo->query('SELECT changes FROM audit WHERE id=1')->fetchColumn()===7 && (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn()===1);
    verify('Recovery rolls back package receipts introduced by failed installation',!file_exists(STORAGE.'/component-installations/plugins/introduced.json') && (new App\Services\ManagedPluginInstallations(ROOT))->isVerified('reel-player'));
    verify('Recovery restores migrated PWA selection from the database',str_contains($pwa->icons()['sizes'][48],'/uploads/pwa/legacy-') && hash_file('sha256',WWW.parse_url($pwa->icons()['sizes'][48],PHP_URL_PATH))===$oldIconHash);
    verify('Recovery retains user music and independent plugin',file_get_contents(WWW.'/uploads/music/keep.flac')==='user music' && str_contains(file_get_contents(PLUGINS.'/reel-player/Plugin.php'),'independent package'));
    verify('Successful recovery leaves maintenance mode',!is_file(STORAGE.'/update.maintenance'));
    verify('Normal admin update succeeds after recovery',(new RecoveryUpdater($settings,$pdo))->runUpdate()['status']==='success' && git(['rev-parse','HEAD'])===$second);
    put('app/main.php','<?php return "manual customization";');verify('Real source conflict blocks admin update without deleting edits',rejects(fn()=>(new RecoveryUpdater($settings,$pdo))->runUpdate()) && str_contains(file_get_contents(ROOT.'/app/main.php'),'manual customization') && !is_file(STORAGE.'/update.maintenance'));
    $report=['passed'=>count($checks),'checks'=>$checks];$output=__DIR__.'/artifacts/update-center';if(!is_dir($output))mkdir($output,0700,true);file_put_contents($output.'/recovery-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} finally {
    if($created)$server->exec('DROP DATABASE `'.$databaseName.'`');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($scratch);
}
