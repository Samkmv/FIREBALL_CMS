<?php
declare(strict_types=1);
$project=dirname(__DIR__);
require $project.'/vendor/autoload.php';
$parent=__DIR__.'/.tmp';if(!is_dir($parent))mkdir($parent,0700,true);
$scratch=$parent.'/update-git-'.bin2hex(random_bytes(8));mkdir($scratch,0700);
define('ROOT',$scratch);
function return_translation(string $key): string {return $key;}
final class GitStatusCheck extends App\Services\UpdateCenter {
    public function __construct() {}
    public function parse(string $status): array {return $this->normalizeDirtyFiles($status);}
    public function blocking(array $paths): array {return $this->filterBlockingDirtyFiles($paths);}
    public function inspect(): array {return $this->getLocalGitState();}
    public function command(string $command): array {return $this->runCommand($command,ROOT);}
}
final class BrokenGitStatusCheck extends App\Services\UpdateCenter {
    public function __construct() {}
    public function inspect(): array {return $this->getLocalGitState();}
    protected function runCommand(string $command,string $cwd): array {
        if(str_starts_with($command,'git status '))return ['exit_code'=>128,'stdout'=>'','stdout_raw'=>'','stderr'=>'fixture failure'];
        return parent::runCommand($command,$cwd);
    }
}
$checks=[];
function verify(string $label,bool $value): void {global $checks;if(!$value)throw new RuntimeException($label);$checks[]=$label;}
function rejects(callable $operation): bool {try{$operation();return false;}catch(RuntimeException){return true;}}
function git(array $arguments): void {
    $process=proc_open(['git','-C',ROOT,...$arguments],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException($output.$error);
}
function removeOwnedScratch(string $path): void {
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($entries as $entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($path);
}
try {
    $subject=new GitStatusCheck();
    verify('First plugin path keeps initial p',$subject->parse(" M plugins/reel-player/Plugin.php\0")==['plugins/reel-player/Plugin.php']);
    verify('First dependency path keeps initial v',$subject->parse(" M vendor/autoload.php\0")==['vendor/autoload.php']);
    $special=[".github/workflows/check.yml","plugins/reel-player/Моя песня \"mix\".php","app/name -> other.php","app/tab\tline\nname.php"];
    verify('NUL paths retain dots, Unicode, quotes, arrows and whitespace',$subject->parse(implode('',array_map(fn($name)=>" M ".$name."\0",$special)))===$special);
    verify('Rename includes both destination and source',$subject->parse("R  storage/moved.php\0app/original.php\0")==['storage/moved.php','app/original.php']);
    verify('Rename of core into protected folder still blocks',$subject->blocking($subject->parse("R  storage/moved.php\0app/original.php\0"))===['app/original.php']);
    verify('Incomplete status cannot be considered clean',rejects(fn()=>$subject->parse(' M vendor/autoload.php')));
    verify('Missing rename source cannot be considered clean',rejects(fn()=>$subject->parse("R  app/new.php\0")));
    verify('Unexpected status cannot be considered clean',rejects(fn()=>$subject->parse("broken\0")));
    verify('Filename whitespace cannot masquerade as preserved config',$subject->blocking([' config/config.local.php','storage\\code.php'])===[' config/config.local.php','storage\\code.php']);
    git(['init','-q']);git(['config','user.email','fixture@example.test']);git(['config','user.name','Fixture']);
    foreach(['plugins/reel-player/Plugin.php','vendor/autoload.php','storage/note.txt','app/original.php',...$special] as $name){$path=ROOT.'/'.$name;if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,"initial\n");}
    git(['add','--all']);git(['commit','-qm','Fixture']);
    verify('Real clean repository is detected',$subject->inspect()['is_clean']);
    foreach(['plugins/reel-player/Plugin.php','vendor/autoload.php','storage/note.txt',...$special] as $name)file_put_contents(ROOT.'/'.$name,"changed\n");
    git(['mv','app/original.php','storage/moved.php']);
    $state=$subject->inspect();
    verify('Real Git blocks plugin source and manages Composer files',!$state['is_update_clean']&&in_array('plugins/reel-player/Plugin.php',$state['blocking_dirty_files'],true)&&in_array('vendor/autoload.php',$state['managed_dirty_files'],true)&&!in_array('vendor/autoload.php',$state['blocking_dirty_files'],true));
    verify('Real Git preserves unusual filenames',array_diff($special,$state['blocking_dirty_files'])===[]);
    verify('Real Git protects only runtime paths',in_array('storage/note.txt',$state['ignored_dirty_files'],true)&&in_array('app/original.php',$state['blocking_dirty_files'],true));
    $raw=$subject->command("printf ' M vendor/autoload.php\\000'");
    verify('Command reader retains raw status bytes',$raw['stdout_raw']===" M vendor/autoload.php\0");
    verify('Git status failure blocks inspection',rejects(fn()=>(new BrokenGitStatusCheck())->inspect()));
    $report=['passed'=>count($checks),'checks'=>$checks];$artifact=__DIR__.'/artifacts/update-center';if(!is_dir($artifact))mkdir($artifact,0700,true);
    file_put_contents($artifact.'/git-status-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} finally {removeOwnedScratch($scratch);}
