<?php
declare(strict_types=1);
$project=dirname(__DIR__);require $project.'/vendor/autoload.php';
$scratch=__DIR__.'/.tmp/update-runtime-'.bin2hex(random_bytes(8));mkdir($scratch,0700,true);
define('ROOT',$scratch);define('WWW',ROOT.'/public');define('STORAGE',ROOT.'/storage');
function return_translation(string $key): string{return $key;}
function base_url(string $path='/'): string{return 'https://fixture.test'.$path;}
final class RuntimeSettings extends App\Models\SiteSetting {
    public array $values=[];
    public function all(): array{return $this->values;}
    public function get(string $key,string $default=''): string{return $this->values[$key]??$default;}
    public function setMany(array $values): void{$this->values=array_replace($this->values,$values);}
}
final class RuntimePwa extends App\Services\PwaService {
    public function generate(string $from,string $to): bool{return $this->generateRasterIcons($from,$to);}
}
final class RuntimeSvgPwa extends App\Services\PwaService {
    protected function resolveIconSource(): array{return ['kind'=>'favicon','type'=>'svg','path'=>WWW.'/assets/default/icons/fireball-cms.svg'];}
}
final class RuntimeUpdater extends App\Services\UpdateCenter {
    public function __construct(App\Models\SiteSetting $settings){$this->siteSettings=$settings;}
    public function inspect(): array{return $this->getLocalGitState();}
    public function fresh(): void{$this->componentPolicyFrozen=false;$this->componentPolicy=null;}
    public function manifest(array $manifest): void{$this->engineRelease=['version'=>'1.0.0'];$this->validateUpdateManifest($manifest);}
    public function validate(string $commit,array $manifest): void{$this->validateGitDiffAgainstRef($commit,$manifest);}
    public function snapshot(): string{
        $file=$this->createFileBackup();file_put_contents(STORAGE.'/update-recovery.json',json_encode(['files'=>$file,'files_sha256'=>hash_file('sha256',$file)]));return $file;
    }
    public function merge(string $from,string $to,array $manifest): void{$this->fastForwardToCommit($to,$manifest);$this->restorePreservedGitPaths($from,$to,$manifest);}
    public function archive(string $package,array $manifest): void{$this->migrateArchivePwa($package);$this->copyPackageToRoot($package,ROOT,$manifest);}
}
$checks=[];
function verify(string $name,bool $ok): void{global $checks;if(!$ok)throw new RuntimeException($name);$checks[]=$name;}
function rejects(callable $operation): bool{try{$operation();return false;}catch(Throwable){return true;}}
function put(string $name,string $content): void{$file=ROOT.'/'.$name;if(!is_dir(dirname($file)))mkdir(dirname($file),0700,true);file_put_contents($file,$content);}
function git(array $args): string{$p=proc_open(['git','-C',ROOT,...$args],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException($err);return trim($out);}
function png(string $file,int $size,int $color): void{$image=imagecreatetruecolor($size,$size);imagefill($image,0,0,$color);if(!is_dir(dirname(ROOT.'/'.$file)))mkdir(dirname(ROOT.'/'.$file),0700,true);imagepng($image,ROOT.'/'.$file);imagedestroy($image);}
function metadata(string $slug,string $version): string{return json_encode(['slug'=>$slug,'version'=>$version]);}
function hashes(string $directory): array{$result=[];foreach(glob($directory.'/*')?:[] as $file)if(is_file($file))$result[basename($file)]=hash_file('sha256',$file);return $result;}
try {
    mkdir(STORAGE,0700,true);mkdir(WWW.'/assets/default/pwa',0700,true);
    foreach(App\Services\PwaIconStorage::names() as $file)copy($project.'/public/assets/default/pwa/'.$file,WWW.'/assets/default/pwa/'.$file);
    put('public/assets/default/icons/fireball-cms.svg','<svg xmlns="http://www.w3.org/2000/svg"/>');
    png('public/assets/img/fbl_logo.png',256,0x123456);
    put('app/Services/UpdateCenter.php','<?php return "old updater fixture";');
    put('app/main.php','<?php return "base";');put('plugins/reel-player/plugin.json',metadata('reel-player','1.0.0'));
    put('plugins/reel-player/Plugin.php','<?php return "plugin base";');
    git(['init','-q','-b','main']);git(['config','user.name','Runtime fixture']);git(['config','user.email','fixture@example.test']);git(['add','.']);git(['commit','-qm','base']);$base=git(['rev-parse','HEAD']);
    put('app/Services/UpdateCenter.php','<?php return "fixed updater fixture";');
    put('app/Services/PwaIconStorage.php','<?php return "new helper fixture";');
    put('app/main.php','<?php return "new core";');put('plugins/reel-player/Plugin.php','<?php return "bundled plugin new";');
    put('plugins/reel-player/plugin.json',metadata('reel-player','1.1.0'));put('plugins/reel-player/introduced.php','<?php return "bundled file";');
    git(['add','.']);git(['commit','-qm','new main commit']);$release=git(['rev-parse','HEAD']);
    git(['update-ref','refs/remotes/origin/main',$release]);git(['checkout','-q','-B','installed',$base]);
    $settings=new RuntimeSettings();$pwa=new RuntimePwa($settings);$updater=new RuntimeUpdater($settings);
    $manifest=['update'=>['app','public','plugins','config','vendor','composer.lock'],'protected'=>['public/uploads','storage','config/config.local.php']];
    $before=hashes(WWW.'/assets/default/pwa');$pwa->syncIcons();
    verify('Default PWA generation never changes shipped icons',$before===hashes(WWW.'/assets/default/pwa'));
    verify('Default icon settings do not dirty system code',$updater->inspect()['is_update_clean']);
    $svg=new RuntimeSvgPwa($settings);verify('SVG favicon keeps valid standard raster app icons',$svg->icons()['favicon']['type']==='image/svg+xml' && count($svg->icons()['sizes'])===9 && str_contains($svg->icons()['apple']['src'],'/assets/default/pwa/')); 
    png('public/uploads/favicon.png',256,0x663399);$settings->values['site_favicon']='/uploads/favicon.png';$pwa->syncIcons();$icons=$pwa->icons();
    verify('Custom favicon produces an immutable runtime set',str_contains($icons['favicon']['src'],'/uploads/pwa/generated-'));
    verify('Favicon changes keep system assets intact',$before===hashes(WWW.'/assets/default/pwa') && $updater->inspect()['is_update_clean']);
    $first=$icons['sizes'][192];$settings->values['site_favicon']='';png('public/uploads/app.png',256,0x228833);$settings->values['pwa_app_icon']='/uploads/app.png';$pwa->syncIcons();
    verify('PWA icon has its own complete generated set',$pwa->icons()['sizes'][192]!==$first && count($pwa->manifestIcons())===10);
    foreach($pwa->manifestIcons() as $icon){$image=getimagesize(WWW.parse_url($icon['src'],PHP_URL_PATH));verify('Manifest size and MIME match runtime asset '.$icon['sizes'].' '.$icon['purpose'],$image[0].'x'.$image[1]===$icon['sizes'] && $image['mime']===$icon['type']);}
    verify('Apple touch icon uses the runtime PNG',str_contains($pwa->icons()['apple']['src'],'/uploads/pwa/generated-'));
    verify('Direct attempt to generate into system assets is rejected',rejects(fn()=>$pwa->generate(WWW.'/uploads/app.png',WWW.'/assets/default/pwa')) && $before===hashes(WWW.'/assets/default/pwa'));
    $settings->values['site_title']='Custom user settings';put('config/config.local.php','<?php return ["custom"=>true];');put('public/uploads/music/user.flac','user audio fixture');
    verify('Settings, local configuration and music are runtime data',$updater->inspect()['is_update_clean']);
    $settings->values['pwa_app_icon']='';
    put('public/assets/default/pwa/icon-48.png','<?php return "not an image";');$updater->fresh();
    verify('A non-image disguised as a PWA icon remains a blocking conflict',!$updater->inspect()['is_update_clean']);
    png('public/assets/default/pwa/icon-48.png',48,0xdd2233);$legacyHash=hash_file('sha256',WWW.'/assets/default/pwa/icon-48.png');
    verify('Legacy changed PWA icon is managed, not a source conflict',in_array('public/assets/default/pwa/icon-48.png',$updater->inspect()['managed_dirty_files'],true));
    put('plugins/reel-player/Plugin.php','<?php return "independent plugin 9";');put('plugins/reel-player/plugin.json',metadata('reel-player','9.0.0'));
    verify('Unverified plugin source changes block the core updater',!$updater->inspect()['is_update_clean']);
    $installations=new App\Services\ManagedPluginInstallations(ROOT);
    $installations->record('reel-player',['kind'=>'test-admin-package','version'=>'9.0.0']);
    put('plugins/personal/plugin.json',metadata('personal','1.0.0'));put('plugins/personal/Plugin.php','<?php return "personal";');
    verify('Independent plugin package and a new local plugin are preserved',$updater->inspect()['is_update_clean']);
    put('app/Services/UpdateCenter.php','<?php return "fixed updater fixture";');
    put('app/Services/PwaIconStorage.php','<?php return "new helper fixture";');$updater->fresh();
    verify('One-time updater bootstrap is accepted only when exact fetched main bytes match',$updater->inspect()['is_update_clean']);
    put('app/Services/UpdateCenter.php','<?php return "unknown customization";');$updater->fresh();
    verify('A custom updater edit cannot masquerade as the official bootstrap',!$updater->inspect()['is_update_clean']);
    put('app/Services/UpdateCenter.php','<?php return "fixed updater fixture";');$updater->fresh();
    put('plugins/personal/plugin.json',json_encode(['slug'=>'personal','version'=>'1.0.0','requires'=>['cms'=>'>=2.0.0']]));$updater->fresh();
    verify('Incompatible independent plugin requirements block before installation',rejects(fn()=>$updater->manifest($manifest+['version'=>'1.1.0','minVersion'=>'1.0.0','type'=>'patch'])));
    put('plugins/personal/plugin.json',metadata('personal','1.0.0'));$updater->fresh();
    $snapshotAbsent=$settings->values;
    verify('Legacy icon is never reset without a verified snapshot',rejects(fn()=>$updater->merge($base,$release,$manifest)) && hash_file('sha256',WWW.'/assets/default/pwa/icon-48.png')===$legacyHash && $settings->values===$snapshotAbsent);
    $updater->fresh();$updater->snapshot();png('public/assets/default/pwa/icon-48.png',48,0x999999);
    verify('An icon changed after backup aborts before touching any code',rejects(fn()=>$updater->merge($base,$release,$manifest)) && git(['rev-parse','HEAD'])===$base);
    png('public/assets/default/pwa/icon-48.png',48,0xdd2233);$updater->fresh();$snapshot=$updater->snapshot();$updater->validate($release,$manifest);$updater->merge($base,$release,$manifest);
    verify('New main commit installs through a normal fast-forward',git(['rev-parse','HEAD'])===$release && str_contains(file_get_contents(ROOT.'/app/main.php'),'new core'));
    verify('Legacy standard icon is restored to the Git baseline',hashes(WWW.'/assets/default/pwa')===$before);
    $legacyUrl=$pwa->icons()['sizes'][48];$legacyPath=WWW.parse_url($legacyUrl,PHP_URL_PATH);
    verify('Previously modified PWA image remains selected from runtime storage',str_contains($legacyUrl,'/uploads/pwa/legacy-') && hash_file('sha256',$legacyPath)===$legacyHash);
    verify('SVG favicon keeps migrated raster icons for Apple and Android',$svg->icons()['favicon']['type']==='image/svg+xml' && str_contains($svg->icons()['apple']['src'],'/uploads/pwa/legacy-') && str_contains($svg->icons()['sizes'][192],'/uploads/pwa/legacy-'));
    verify('Legacy favicon and Apple touch icon resolve to the migrated set',str_contains($pwa->icons()['favicon']['src'],'/uploads/pwa/legacy-') && str_contains($pwa->icons()['apple']['src'],'/uploads/pwa/legacy-'));
    verify('Independently updated Tape Room is not downgraded by the engine',str_contains(file_get_contents(ROOT.'/plugins/reel-player/Plugin.php'),'independent plugin 9') && json_decode(file_get_contents(ROOT.'/plugins/reel-player/plugin.json'),true)['version']==='9.0.0');
    verify('Core files absent from the installed independent package stay absent',!file_exists(ROOT.'/plugins/reel-player/introduced.php'));
    verify('User music, settings and personal plugins survive',file_get_contents(WWW.'/uploads/music/user.flac')==='user audio fixture' && $settings->values['site_title']==='Custom user settings' && is_file(ROOT.'/plugins/personal/Plugin.php'));
    $zip=new ZipArchive();$zip->open($snapshot);verify('Recovery backup contains the old icon and independent plugin',hash('sha256',$zip->getFromName('public/assets/default/pwa/icon-48.png'))===$legacyHash && str_contains($zip->getFromName('plugins/reel-player/Plugin.php'),'independent plugin 9'));$zip->close();
    $updater->fresh();verify('Post-update managed changes remain eligible for the next engine update',$updater->inspect()['is_update_clean']);
    put('plugins/reel-player/Plugin.php','<?php return "manual edit";');$updater->fresh();
    verify('Manual edit after a plugin update blocks the next engine update',!$updater->inspect()['is_update_clean']);
    verify('Manual plugin edit also blocks plugin replacement',rejects(fn()=>$installations->assertReplaceable('reel-player')));
    put('plugins/reel-player/Plugin.php','<?php return "independent plugin 9";');
    verify('Verified plugin is replaceable by its independent updater',count($installations->assertReplaceable('reel-player'))===2);
    symlink(ROOT.'/app/main.php',ROOT.'/plugins/reel-player/link.php');verify('Plugin symlink cannot become an installation receipt',rejects(fn()=>$installations->record('reel-player',[])));unlink(ROOT.'/plugins/reel-player/link.php');
    $updater->fresh();$package=ROOT.'/storage/archive-package';
    foreach(App\Services\PwaIconStorage::names() as $file)put('storage/archive-package/public/assets/default/pwa/'.$file,(string)file_get_contents($project.'/public/assets/default/pwa/'.$file));
    png('storage/archive-package/public/assets/default/pwa/icon-48.png',48,0x556677);
    put('storage/archive-package/plugins/reel-player/Plugin.php','<?php return "archive bundled downgrade";');
    $updater->snapshot();$updater->archive($package,$manifest);
    verify('ZIP update keeps the existing migrated icon selection',$pwa->icons()['sizes'][48]===$legacyUrl && hash_file('sha256',$legacyPath)===$legacyHash);
    verify('ZIP update preserves independently installed plugin code',str_contains(file_get_contents(ROOT.'/plugins/reel-player/Plugin.php'),'independent plugin 9'));
    verify('ZIP update preserves user music',file_get_contents(WWW.'/uploads/music/user.flac')==='user audio fixture');
    // Existing admin update without a receipt: adopt only an exact fetched package.
    git(['restore','--source=HEAD','--staged','--worktree','public/assets/default/pwa']);
    unlink(STORAGE.'/component-installations/plugins/reel-player.json');
    put('plugins/reel-player/Plugin.php','<?php return "bundled plugin new";');put('plugins/reel-player/plugin.json',metadata('reel-player','1.1.0'));put('plugins/reel-player/introduced.php','<?php return "bundled file";');
    git(['checkout','-q','-B','legacy-installed',$base]);
    put('plugins/reel-player/Plugin.php','<?php return "bundled plugin new";');put('plugins/reel-player/plugin.json',metadata('reel-player','1.1.0'));put('plugins/reel-player/introduced.php','<?php return "bundled file";');$updater->fresh();
    verify('Legacy admin package is recognized only by exact fetched Git contents',$updater->inspect()['is_update_clean']);
    put('plugins/reel-player/Plugin.php','<?php return "untrusted";');$updater->fresh();verify('Same plugin version with altered code is a real conflict',!$updater->inspect()['is_update_clean']);
    git(['restore','--source=HEAD','--staged','--worktree','plugins/reel-player']);unlink(ROOT.'/plugins/reel-player/introduced.php');
    put('app/main.php','<?php this is invalid PHP;');git(['add','app/main.php']);git(['commit','-qm','bad release']);$bad=git(['rev-parse','HEAD']);git(['checkout','-q','-B','syntax-installed',$base]);$updater->fresh();
    verify('Invalid incoming PHP is rejected before source replacement',rejects(fn()=>$updater->validate($bad,$manifest)) && git(['rev-parse','HEAD'])===$base);
    symlink(ROOT.'/app/main.php',ROOT.'/app/linked.php');git(['add','app/linked.php']);git(['commit','-qm','unsafe symlink release']);$linked=git(['rev-parse','HEAD']);git(['checkout','-q','-B','link-check',$base]);$updater->fresh();
    verify('Incoming Git symlinks are rejected before source replacement',rejects(fn()=>$updater->validate($linked,$manifest)) && !file_exists(ROOT.'/app/linked.php'));
    put('app/main.php','<?php return "manual core";');$updater->fresh();verify('Real core source changes still block updates',!$updater->inspect()['is_update_clean']);
    $report=['passed'=>count($checks),'checks'=>$checks];$output=__DIR__.'/artifacts/update-center';if(!is_dir($output))mkdir($output,0700,true);file_put_contents($output.'/runtime-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($scratch);
}
