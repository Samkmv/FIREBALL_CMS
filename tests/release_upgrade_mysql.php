<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$repository = dirname(__DIR__);
$artifact = realpath(getenv('FIREBALL_RELEASE_ZIP') ?: $repository . '/dist/fireball-cms-1.8.2.zip');
if ($artifact === false || dirname($artifact) !== $repository . '/dist') throw new RuntimeException('Build the local release archive before upgrade integration tests.');
require $repository . '/vendor/autoload.php';
if (getenv('FIREBALL_TEST_MYSQL_HOST') !== false) {
    $settings = ['host' => getenv('FIREBALL_TEST_MYSQL_HOST'), 'port' => (int)(getenv('FIREBALL_TEST_MYSQL_PORT') ?: 3306), 'username' => getenv('FIREBALL_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('FIREBALL_TEST_MYSQL_PASSWORD') ?: '', 'charset' => 'utf8mb4', 'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]];
} else { $local = require $repository . '/config/config.local.php'; $settings = $local['DB_SETTINGS']; }
if (!in_array($settings['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Only local disposable MySQL is allowed.');
$database = 'fbl_release_test_' . bin2hex(random_bytes(6));
$settings['database'] = $database;
$temporary = sys_get_temp_dir() . '/fireball-upgrade-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700); $legacy = $temporary . '/legacy'; $package = $temporary . '/package';
mkdir($legacy,0700); mkdir($package,0700);
$dsn = 'mysql:host=' . $settings['host'] . ';port=' . (int)($settings['port'] ?? 3306) . ';charset=utf8mb4';
$server = new PDO($dsn, $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false; $checks = 0;
function upgradeCheck(bool $value,string $message): void { $GLOBALS['checks']++; if (!$value) throw new RuntimeException($message); }
function fixtureCommand(array $arguments,string $cwd): string {
    $process=proc_open($arguments,[1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd);
    if (!is_resource($process)) throw new RuntimeException('Cannot start fixture command.');
    $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($process)!==0) {
        $log = $GLOBALS['legacy'] . '/tmp/error.log';
        if (is_file($log)) { preg_match_all('/^(?:Message|File|Line): (.+)$/m', file_get_contents($log), $messages); $error .= implode("\n", array_slice($messages[1], -4)); }
        throw new RuntimeException('Fixture command failed: '.substr($out.$error,-1500));
    }
    return trim($out);
}
function removeUpgradeFixture(string $path): void { if(is_file($path)||is_link($path)){unlink($path);return;}foreach(scandir($path)?:[]as$name)if(!in_array($name,['.','..'],true))removeUpgradeFixture($path.'/'.$name);rmdir($path); }
try {
    fixtureCommand(['git','archive','--format=zip','--output='.$temporary.'/legacy.zip','1.8.0'],$repository);
    $zip=new ZipArchive();if($zip->open($temporary.'/legacy.zip')!==true)throw new RuntimeException('Cannot open legacy fixture.');$zip->extractTo($legacy);$zip->close();
    $zip->open($artifact);$zip->extractTo($package);$zip->close();
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $pdo=new PDO($dsn.';dbname='.$database,$settings['username'],$settings['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $runner=new App\Services\SqlFileRunner();
    foreach(['schema.sql','seed.sql']as$file)$runner->executePdo($pdo,file_get_contents($legacy.'/database/'.$file),['now'=>date('Y-m-d H:i:s')]);
    $pdo->exec('CREATE TABLE fixture_legacy_data (id INT PRIMARY KEY, label VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO fixture_legacy_data VALUES (42,'keep legacy data')");
    $config=['DB_SETTINGS'=>$settings,'PATH'=>'https://fixture.example.test','CHAT_ENCRYPTION_KEY'=>bin2hex(random_bytes(32))];
    file_put_contents($legacy.'/config/config.local.php','<?php return '.var_export($config,true).';');chmod($legacy.'/config/config.local.php',0600);
    $configHash=hash_file('sha256',$legacy.'/config/config.local.php');
    file_put_contents($legacy.'/storage/installed.lock','{}');
    file_put_contents($legacy.'/storage/local-upgrade-fixture.txt','keep local state');
    if(!is_dir($legacy.'/public/uploads'))mkdir($legacy.'/public/uploads',0700,true);
    file_put_contents($legacy.'/public/uploads/fixture.txt','keep upload');
    if(!is_dir($legacy.'/themes/custom'))mkdir($legacy.'/themes/custom',0700,true);
    file_put_contents($legacy.'/themes/custom/fixture.txt','keep custom theme');
    fixtureCommand(['git','init','-b','main'],$legacy);
    fixtureCommand(['git','add','-A'],$legacy);
    fixtureCommand(['git','add','-f','storage/local-upgrade-fixture.txt'],$legacy);
    upgradeCheck(fixtureCommand(['git','ls-files','config/config.local.php'],$legacy)==='', 'Disposable credentials are never tracked');
    $commit=['git','-c','user.name=Fixture','-c','user.email=fixture@example.test','-c','core.hooksPath=/dev/null','-c','commit.gpgsign=false','commit','-qm'];
    fixtureCommand([...$commit,'Legacy 1.8.0 fixture'],$legacy);
    $oldCommit=fixtureCommand(['git','rev-parse','HEAD'],$legacy);
    $zip->open($artifact);$zip->extractTo($legacy);$zip->close();
    $removals=json_decode(file_get_contents($legacy.'/update.json'),true)['remove']??[];
    foreach($removals as $path)if(is_file($legacy.'/'.$path))unlink($legacy.'/'.$path);
    file_put_contents($legacy.'/storage/local-upgrade-fixture.txt','bad value from release Git tree');
    fixtureCommand(['git','add','-A'],$legacy);
    fixtureCommand(['git','add','-f','storage/local-upgrade-fixture.txt'],$legacy);
    fixtureCommand([...$commit,'New local release fixture'],$legacy);
    $newCommit=fixtureCommand(['git','rev-parse','HEAD'],$legacy);
    fixtureCommand(['git','reset','--hard',$oldCommit],$legacy);
    if (!is_dir($legacy . '/tmp')) mkdir($legacy . '/tmp',0700);
    file_put_contents($legacy . '/tmp/error.log',''); chmod($legacy . '/tmp/error.log',0600);
    $output=fixtureCommand([PHP_BINARY,'-d','session.save_path='.sys_get_temp_dir(),$package.'/bin/upgrade-local.php','--root='.$legacy,'--ref='.$newCommit,'--confirm-offline'],$repository);
    upgradeCheck(str_contains($output,'Upgraded to 1.8.2'),'Offline bridge completes the actual Git update from legacy code');
    upgradeCheck(fixtureCommand(['git','rev-parse','HEAD'],$legacy)===$newCommit,'Git HEAD is aligned with the applied release');
    upgradeCheck((require $legacy.'/config/version.php')['version']==='1.8.2','Version metadata upgraded');
    upgradeCheck(hash_file('sha256',$legacy.'/config/config.local.php')===$configHash,'Private local configuration preserved byte for byte');
    upgradeCheck(file_get_contents($legacy.'/storage/local-upgrade-fixture.txt')==='keep local state','Git changes cannot replace local runtime data');
    upgradeCheck(file_get_contents($legacy.'/public/uploads/fixture.txt')==='keep upload' && file_get_contents($legacy.'/themes/custom/fixture.txt')==='keep custom theme','Uploads and custom theme preserved');
    upgradeCheck($pdo->query('SELECT label FROM fixture_legacy_data WHERE id=42')->fetchColumn()==='keep legacy data','Legacy database data preserved');
    upgradeCheck(!is_file($legacy.'/storage/update.maintenance'),'Successful upgrade leaves maintenance');
    $descriptor=json_decode(file_get_contents($legacy.'/storage/update-recovery.json'),true,512,JSON_THROW_ON_ERROR);
    upgradeCheck(is_file($descriptor['database'])&&is_file($descriptor['files']),'Complete recovery snapshots retained');
    // Repeat against a fresh legacy schema without Git to exercise ZIP delivery.
    $zipLegacy=$temporary.'/zip-legacy';mkdir($zipLegacy,0700);
    $zip->open($temporary.'/legacy.zip');$zip->extractTo($zipLegacy);$zip->close();
    file_put_contents($zipLegacy.'/config/config.local.php','<?php return '.var_export($config,true).';');chmod($zipLegacy.'/config/config.local.php',0600);
    file_put_contents($zipLegacy.'/storage/installed.lock','{}');
    file_put_contents($zipLegacy.'/storage/local-upgrade-fixture.txt','keep ZIP state');
    if(!is_dir($zipLegacy.'/tmp'))mkdir($zipLegacy.'/tmp',0700);
    file_put_contents($zipLegacy.'/tmp/error.log','');chmod($zipLegacy.'/tmp/error.log',0600);
    $server->exec('DROP DATABASE `'.$database.'`');
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo=new PDO($dsn.';dbname='.$database,$settings['username'],$settings['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach(['schema.sql','seed.sql']as$file)$runner->executePdo($pdo,file_get_contents($zipLegacy.'/database/'.$file),['now'=>date('Y-m-d H:i:s')]);
    $pdo->exec('CREATE TABLE fixture_legacy_data (id INT PRIMARY KEY, label VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO fixture_legacy_data VALUES (42,'keep ZIP database')");
    $GLOBALS['legacy']=$zipLegacy;
    $output=fixtureCommand([PHP_BINARY,'-d','session.save_path='.sys_get_temp_dir(),$package.'/bin/upgrade-local.php','--root='.$zipLegacy,'--confirm-offline'],$repository);
    upgradeCheck(str_contains($output,'Upgraded to 1.8.2'),'Offline bridge completes the legacy ZIP update');
    upgradeCheck(hash_file('sha256',$zipLegacy.'/config/config.local.php')===$configHash,'ZIP preserves private configuration');
    upgradeCheck(file_get_contents($zipLegacy.'/storage/local-upgrade-fixture.txt')==='keep ZIP state','ZIP preserves local data');
    upgradeCheck($pdo->query('SELECT label FROM fixture_legacy_data WHERE id=42')->fetchColumn()==='keep ZIP database','ZIP preserves the legacy database');
    upgradeCheck(!is_file($zipLegacy.'/storage/update.maintenance'),'ZIP update releases maintenance after success');
    echo "Legacy Git/ZIP upgrade regressions passed: $checks checks.\n";
} finally {
    if($created&&preg_match('/^fbl_release_test_[a-f0-9]{12}$/D',$database))$server->exec('DROP DATABASE `'.$database.'`');
    removeUpgradeFixture($temporary);
}
