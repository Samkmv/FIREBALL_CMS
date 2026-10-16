<?php
declare(strict_types=1);
$project=dirname(__DIR__);require $project.'/vendor/autoload.php';
$parent=__DIR__.'/.tmp';if(!is_dir($parent))mkdir($parent,0700,true);
$scratch=$parent.'/update-dependencies-'.bin2hex(random_bytes(8));mkdir($scratch,0700);
define('ROOT',$scratch);define('STORAGE',$scratch.'/storage');
function return_translation(string $key): string{return $key;}
final class DependencyUpdaterCheck extends App\Services\UpdateCenter {
    public function __construct(){}
    public function state(): array{return $this->getLocalGitState();}
    public function managed(string $path): bool{return $this->isManagedDirtyFile($path);}
    public function prepare(array $manifest): void{$this->prepareManagedGitFiles($manifest);}
    public function matches(string $root): bool{return $this->composerVendorMatchesLock($root);}
    public function snapshot(): string {
        $path=$this->createFileBackup();
        file_put_contents(STORAGE.'/update-recovery.json',json_encode(['files'=>$path,'files_sha256'=>hash_file('sha256',$path)]));return $path;
    }
    public function dependencies(): void{$this->runComposerIfDependencyFilesChanged([]);}
    public function merge(string $from,string $to,array $manifest): void{$this->fastForwardToCommit($to,$manifest);$this->restorePreservedGitPaths($from,$to,$manifest);}
    public function zip(string $package,string $target,array $manifest): void{$this->assertPackageComposerReady($package,[]);$this->copyPackageToRoot($package,$target,$manifest);}
    protected function detectComposerBinary(): string{throw new RuntimeException('Composer must not be called when release dependencies match.');}
}
$checks=[];
function verify(string $label,bool $condition): void{global $checks;if(!$condition)throw new RuntimeException($label);$checks[]=$label;}
function rejects(callable $op): bool{try{$op();return false;}catch(Throwable){return true;}}
function writeFixture(string $path,string $value): void{$path=ROOT.'/'.$path;if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,$value);}
function git(array $arguments,bool $allowFailure=false): string {
    $p=proc_open(['git','-C',ROOT,...$arguments],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    if(!$allowFailure&&$code!==0)throw new RuntimeException($out.$err);return trim($out);
}
function packages(string $version): void {
    $package=['name'=>'example/audio','version'=>$version,'source'=>['reference'=>'ref-'.$version]];
    writeFixture('composer.lock',json_encode(['packages'=>[$package]]));
    writeFixture('vendor/composer/installed.json',json_encode(['packages'=>[$package+['install-path'=>'../example/audio']]]));
    writeFixture('vendor/example/audio/player.php','package '.$version);
    writeFixture('vendor/autoload.php','autoload '.$version);
}
try{
    git(['init','-q']);git(['config','user.email','fixture@example.test']);git(['config','user.name','Fixture']);
    writeFixture('composer.json','{"require":{"example/audio":"^1.0"}}');packages('1.0.0');
    writeFixture('vendor/example/audio/deleted.php','old dependency');writeFixture('app/main.php','base core');
    writeFixture('plugins/reel-player/docs/catalog.md','base documentation');writeFixture('plugins/reel-player/Plugin.php','base plugin');
    writeFixture('storage/tracked-runtime.txt','base runtime');
    git(['add','--all']);git(['commit','-qm','base']);$base=git(['rev-parse','HEAD']);
    packages('1.1.0');writeFixture('app/main.php','new core');writeFixture('plugins/reel-player/docs/catalog.md','new documentation');
    writeFixture('storage/tracked-runtime.txt','release runtime');
    writeFixture('vendor/new-package/generated.php','release file');git(['add','--all']);git(['commit','-qm','release']);$release=git(['rev-parse','HEAD']);
    writeFixture('app/not-validated.php','future commit');git(['add','app/not-validated.php']);git(['commit','-qm','unvalidated future']);$future=git(['rev-parse','HEAD']);
    git(['checkout','-q','-B','installed',$base]);
    $subject=new DependencyUpdaterCheck();
    verify('Shipped vendor matches lock',$subject->matches(ROOT));
    packages('2.9.0');writeFixture('plugins/reel-player/docs/catalog.md','locally installed plugin docs');
    writeFixture('plugins/reel-player/docs/Новая песня.md','new plugin docs');
    writeFixture('vendor/new-package/generated.php','newer local generated file');git(['add','vendor/new-package/generated.php']);
    git(['rm','-q','vendor/example/audio/deleted.php']);
    writeFixture('vendor/untracked/added.php','untracked dependency');
    writeFixture('storage/user-note.txt','preserve user runtime');writeFixture('config/config.local.php','preserve config');
    writeFixture('storage/tracked-runtime.txt','local runtime data');
    writeFixture('composer.phar','local Composer binary');writeFixture('auth.json','local credentials fixture');
    $state=$subject->state();
    verify('Newer Composer vendor and lock do not block updates',$state['is_update_clean']);
    verify('Plugin documentation changes are managed',in_array('plugins/reel-player/docs/catalog.md',$state['managed_dirty_files'],true));
    verify('Composer binary and credentials are local files',in_array('composer.phar',$state['ignored_dirty_files'],true)&&in_array('auth.json',$state['ignored_dirty_files'],true));
    verify('Vendor PHP is managed but plugin executable code is not',$subject->managed('vendor/example/audio/player.php')&&!$subject->managed('plugins/reel-player/docs/run.php')&&!$subject->managed('plugins/reel-player/Plugin.php'));
    verify('Traversal, control characters and leading whitespace cannot become managed',$subject->managed('vendor/../app/main.php')===false && !$subject->managed(' vendor/autoload.php') && !$subject->managed("vendor/a\nb.php"));
    writeFixture('app/main.php','custom code');verify('Real source edits still block updates',!$subject->state()['is_update_clean']);
    verify('Source edits cannot be reset by preparation',rejects(fn()=>$subject->prepare(['update'=>['app','vendor','composer.lock','plugins']])) && file_get_contents(ROOT.'/app/main.php')==='custom code');
    writeFixture('app/main.php','base core');$manifest=['update'=>['app','vendor','composer.lock','plugins']];
    verify('No file can be reset without a snapshot',rejects(fn()=>$subject->prepare($manifest))&&file_get_contents(ROOT.'/vendor/autoload.php')==='autoload 2.9.0');
    $snapshot=$subject->snapshot();$subject->prepare($manifest);
    verify('Generated files are reconciled before merging',file_get_contents(ROOT.'/vendor/autoload.php')==='autoload 1.0.0' && file_get_contents(ROOT.'/composer.lock')===json_encode(['packages'=>[['name'=>'example/audio','version'=>'1.0.0','source'=>['reference'=>'ref-1.0.0']]]]) );
    verify('New staged and untracked dependencies are cleared from the working tree',!file_exists(ROOT.'/vendor/new-package/generated.php')&&!file_exists(ROOT.'/vendor/untracked/added.php'));
    verify('Staged deletion is restored before the merge',is_file(ROOT.'/vendor/example/audio/deleted.php'));
    $zip=new ZipArchive();$zip->open($snapshot);
    verify('Snapshot retains newer vendor and plugin documentation',$zip->getFromName('vendor/autoload.php')==='autoload 2.9.0' && $zip->getFromName('plugins/reel-player/docs/catalog.md')==='locally installed plugin docs');
    verify('Snapshot retains new dependency files before removing them',$zip->getFromName('vendor/new-package/generated.php')==='newer local generated file'&&$zip->getFromName('vendor/untracked/added.php')==='untracked dependency');$zip->close();
    git(['update-ref','refs/remotes/origin/main',$future]);$subject->merge($base,$release,$manifest);$subject->dependencies();
    verify('Remote changes after validation cannot change the installed commit',git(['rev-parse','HEAD'])===$release&&!file_exists(ROOT.'/app/not-validated.php'));
    verify('Tracked protected data is restored after its incoming Git change',file_get_contents(ROOT.'/storage/tracked-runtime.txt')==='local runtime data');
    verify('Release applies with its tested dependencies without Composer',$subject->matches(ROOT)&&file_get_contents(ROOT.'/vendor/autoload.php')==='autoload 1.1.0'&&file_get_contents(ROOT.'/app/main.php')==='new core');
    verify('Release documentation is installed',file_get_contents(ROOT.'/plugins/reel-player/docs/catalog.md')==='new documentation');
    verify('Settings, runtime data and local Composer are retained',file_get_contents(ROOT.'/config/config.local.php')==='preserve config'&&file_get_contents(ROOT.'/storage/user-note.txt')==='preserve user runtime'&&file_get_contents(ROOT.'/composer.phar')==='local Composer binary');
    packages('3.0.0');$subject->snapshot();writeFixture('vendor/autoload.php','changed after snapshot');
    verify('Changes after snapshot abort before reset',rejects(fn()=>$subject->prepare($manifest))&&file_get_contents(ROOT.'/vendor/autoload.php')==='changed after snapshot'&&str_contains(file_get_contents(ROOT.'/composer.lock'),'3.0.0'));
    $subject->snapshot();$descriptor=json_decode(file_get_contents(STORAGE.'/update-recovery.json'),true);$descriptor['files_sha256']=str_repeat('0',64);file_put_contents(STORAGE.'/update-recovery.json',json_encode($descriptor));
    verify('Corrupted snapshot cannot authorize file replacement',rejects(fn()=>$subject->prepare($manifest))&&file_get_contents(ROOT.'/vendor/autoload.php')==='changed after snapshot');
    writeFixture('composer.lock','{}');verify('Malformed lock is rejected',!$subject->matches(ROOT));
    packages('1.1.0');writeFixture('vendor/composer/installed.json','{"packages":{}}');verify('Malformed installed metadata is rejected',!$subject->matches(ROOT));
    packages('1.1.0');$lock=json_decode(file_get_contents(ROOT.'/composer.lock'),true);$lock['packages'][0]['source']['reference']='different-ref';writeFixture('composer.lock',json_encode($lock));verify('Same version with another source reference is rejected',!$subject->matches(ROOT));
    packages('1.1.0');rename(ROOT.'/vendor/example/audio',ROOT.'/vendor/example/missing');verify('Missing package directory is detected',!$subject->matches(ROOT));rename(ROOT.'/vendor/example/missing',ROOT.'/vendor/example/audio');
    symlink(ROOT.'/app/main.php',ROOT.'/vendor/linked.php');verify('Symlinks cannot be silently reconciled',!$subject->managed('vendor/linked.php')&&!$subject->state()['is_update_clean']);unlink(ROOT.'/vendor/linked.php');
    git(['restore','--source=HEAD','--staged','--worktree','.']);git(['checkout','-qb','conflict-side']);writeFixture('vendor/autoload.php','side');git(['add','vendor/autoload.php']);git(['commit','-qm','side']);git(['checkout','-q','installed']);writeFixture('vendor/autoload.php','other');git(['add','vendor/autoload.php']);git(['commit','-qm','other']);git(['merge','conflict-side'],true);
    verify('Unresolved vendor conflict remains a blocker',!$subject->state()['is_update_clean']);
    foreach(['zip-install','zip-package'] as $directory){
        $prefix='storage/'.$directory.'/';
        writeFixture($prefix.'composer.json','{"require":{"example/audio":"^1.0"}}');
        $version=$directory==='zip-install'?'9.0.0':'1.1.0';$package=['name'=>'example/audio','version'=>$version];
        writeFixture($prefix.'composer.lock',json_encode(['packages'=>[$package]]));writeFixture($prefix.'vendor/composer/installed.json',json_encode(['packages'=>[$package+['install-path'=>'../example/audio']]]));
        writeFixture($prefix.'vendor/autoload.php','autoload '.$version);writeFixture($prefix.'vendor/example/audio/player.php','package '.$version);
        writeFixture($prefix.'config/config.local.php',$directory==='zip-install'?'local config':'release config');
    }
    $subject->zip(ROOT.'/storage/zip-package',ROOT.'/storage/zip-install',['update'=>['composer.json','composer.lock','vendor','config']]);
    verify('ZIP installations with newer dependencies receive coherent release files',$subject->matches(ROOT.'/storage/zip-install')&&file_get_contents(ROOT.'/storage/zip-install/vendor/autoload.php')==='autoload 1.1.0');
    verify('ZIP replacement retains local configuration',file_get_contents(ROOT.'/storage/zip-install/config/config.local.php')==='local config');
    $report=['passed'=>count($checks),'checks'=>$checks];$artifact=__DIR__.'/artifacts/update-center';if(!is_dir($artifact))mkdir($artifact,0700,true);file_put_contents($artifact.'/dependency-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
}finally{
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($items as $item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($scratch);
}
