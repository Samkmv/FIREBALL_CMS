<?php
declare(strict_types=1);
$project=dirname(__DIR__);$scratch=realpath((string)getenv('FBL_PWA_TEST_ROOT'));
if (!$scratch || !str_starts_with($scratch,$project.'/tests/.tmp/')) {http_response_code(404);exit;}
define('ROOT',$scratch.'/installation');define('WWW',ROOT.'/public');define('STORAGE',ROOT.'/storage');define('CACHE',ROOT.'/cache');
define('APP',$project.'/app');define('CORE',$project.'/core');define('HELPERS',$project.'/helpers');define('CONFIG',$project.'/config');
define('ERROR_LOGS',$scratch.'/errors.log');define('PATH','http://'.$_SERVER['HTTP_HOST']);define('FIREBALL_CLI',true);
foreach([ROOT,WWW,STORAGE,CACHE,$scratch.'/sessions'] as $directory)if(!is_dir($directory))mkdir($directory,0700,true);
ini_set('session.save_path',$scratch.'/sessions');ini_set('error_log',ERROR_LOGS);
require $project.'/config/config.php';require $project.'/vendor/autoload.php';require $project.'/helpers/helpers.php';
final class PwaFixtureDatabase extends FBL\Database {
    public function __construct(PDO $pdo){$this->connection=$pdo;}
    public function query(string $query,array $params=[]): static {
        $query=str_replace('ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
            'ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value, updated_at=excluded.updated_at',$query);
        return parent::query($query,$params);
    }
}
$pdo=new PDO('sqlite:'.$scratch.'/settings.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (setting_key TEXT PRIMARY KEY,setting_value TEXT,updated_at TEXT)');
$app=new FBL\Application(false);$app->db=new PwaFixtureDatabase($pdo);$app->theme=new FBL\ThemeManager();
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/test/select'){
    $mode=$_GET['mode']??'default';$source=match($mode){'a'=>'/uploads/source-a.png','b'=>'/uploads/source-b.png','svg'=>'/uploads/source.svg',default=>''};
    (new App\Models\SiteSetting())->setMany(['site_title'=>'PWA fixture','site_favicon'=>$source,'active_theme'=>'tape-room','pwa_enabled'=>'1']);
    (new App\Services\PwaService())->syncIcons();
    if($mode==='unavailable'){
        unlink(WWW.'/assets/default/pwa/apple-touch-icon.png');unlink(ROOT.'/themes/tape-room/assets/images/apple-touch-icon.png');
    }
    header('Content-Type: application/json');echo json_encode(['mode'=>$mode]);exit;
}
if($path==='/'){
    header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html><head><meta charset="utf-8">'.pwa_head_tags().'</head><body>PWA icon fixture</body></html>';exit;
}
if(str_starts_with($path,'/uploads/pwa/') || str_starts_with($path,'/assets/default/pwa/')){
    $file=realpath(WWW.$path);
    if($file && str_starts_with($file,realpath(WWW).'/') && str_ends_with($file,'.png')){header('Content-Type: image/png');readfile($file);exit;}
}
require $project.'/config/routes.php';
try {echo $app->router->dispatch();}
catch(Throwable $error){http_response_code(500);error_log((string)$error);echo $error::class.': '.$error->getMessage();}
