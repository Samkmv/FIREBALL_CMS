<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Private content receipts distinguish an installed package from later code edits. */
final class ManagedPluginInstallations
{
    private string $storage;

    public function __construct(private string $root)
    {
        $this->storage=$root.'/storage/component-installations/plugins';
    }

    private static function slug(string $slug): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D',$slug)) throw new RuntimeException('Invalid plugin identity.');
    }

    public function tree(string $directory): array
    {
        if (is_link($this->root.'/plugins') || is_link($directory) || !is_dir($directory)) throw new RuntimeException('Unsafe plugin directory.');
        $files=[];$count=0;
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (++$count>20000 || $item->isLink()) throw new RuntimeException('Unsafe or oversized plugin package.');
            if (!$item->isFile() || $item->getFilename()==='.DS_Store') continue;
            $path=substr($item->getPathname(),strlen($directory)+1);
            if (preg_match('/[\x00-\x1f\x7f\\\\]/',$path) || array_intersect(explode('/',$path),['','.','..','.git'])) throw new RuntimeException('Unsafe plugin filename.');
            $hash=hash_file('sha256',$item->getPathname());
            if ($hash===false) throw new RuntimeException('Unable to fingerprint plugin file.');
            $files[$path]=$hash;
        }
        ksort($files,SORT_STRING);return $files;
    }

    public function record(string $slug,array $origin,?array $expected=null): void
    {
        self::slug($slug);$files=$this->tree($this->root.'/plugins/'.$slug);
        if ($expected!==null && $files!==$expected) throw new RuntimeException('Plugin files changed during installation: '.$slug);
        $current=$this->root;
        foreach (['storage','component-installations','plugins'] as $part) {
            $current.='/'.$part;
            if (is_link($current)) throw new RuntimeException('Unsafe plugin receipt directory.');
            if (!is_dir($current) && !mkdir($current,0700)) throw new RuntimeException('Unable to create plugin receipt directory.');
        }
        $path=$this->storage.'/'.$slug.'.json';
        if (is_link($path)) throw new RuntimeException('Unsafe plugin receipt.');
        $payload=json_encode(['schema'=>1,'slug'=>$slug,'origin'=>$origin,'files'=>$files],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $temporary=tempnam($this->storage,'.receipt-');
        try {
            if ($temporary===false || !chmod($temporary,0600) || file_put_contents($temporary,$payload,LOCK_EX)!==strlen($payload) || !rename($temporary,$path)) throw new RuntimeException('Unable to save plugin receipt.');
        } finally {if ($temporary && is_file($temporary)) unlink($temporary);}
    }

    public function receipt(string $slug): ?array
    {
        self::slug($slug);$path=$this->storage.'/'.$slug.'.json';
        foreach ([$this->root.'/storage',$this->root.'/storage/component-installations',$this->storage] as $parent) if (is_link($parent)) throw new RuntimeException('Unsafe plugin receipt directory.');
        if (!is_file($path)) return null;
        if (is_link($path) || filesize($path)>8*1048576) throw new RuntimeException('Unsafe plugin receipt.');
        $data=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (($data['schema']??0)!==1 || ($data['slug']??'')!==$slug || !is_array($data['files']??null) || count($data['files'])>20000) throw new RuntimeException('Invalid plugin receipt.');
        return $data;
    }

    public function isVerified(string $slug): bool
    {
        $receipt=$this->receipt($slug);
        return $receipt!==null && $receipt['files']===$this->tree($this->root.'/plugins/'.$slug);
    }

    private function git(array $args): ?string
    {
        if (!function_exists('proc_open')) return null;
        $process=proc_open(['git','-C',$this->root,...$args],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) return null;
        $output=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        return proc_close($process)===0 ? $output : null;
    }

    public function matchesGit(string $slug,string $ref): bool
    {
        self::slug($slug);
        if (!preg_match('~^(?:HEAD|refs/remotes/origin/[a-zA-Z0-9_./-]+|[a-f0-9]{40})$~D',$ref)) throw new RuntimeException('Invalid plugin baseline.');
        $output=$this->git(['ls-tree','-rz','--full-tree',$ref,'--','plugins/'.$slug.'/']);
        if ($output===null || $output==='') return false;
        $directory=$this->root.'/plugins/'.$slug;$expected=[];
        foreach (array_filter(explode("\0",$output),'strlen') as $entry) {
            if (!preg_match('/^100(?:644|755) blob ([a-f0-9]{40})\t(.+)$/sD',$entry,$match)) return false;
            $path=substr($match[2],strlen('plugins/'.$slug.'/'));$absolute=$directory.'/'.$path;
            if (is_link($absolute) || !is_file($absolute)) return false;
            $hash=hash_init('sha1');hash_update($hash,'blob '.filesize($absolute)."\0");
            if (!hash_update_file($hash,$absolute) || !hash_equals($match[1],hash_final($hash))) return false;
            $expected[]=$path;
        }
        sort($expected,SORT_STRING);return $expected===array_keys($this->tree($directory));
    }

    /** Legacy admin updates are adopted only when every file matches a fetched trusted tree. */
    public function preserved(string $branch='main'): array
    {
        $result=[];
        foreach (glob($this->root.'/plugins/*/plugin.json') ?: [] as $metadataPath) {
            $slug=basename(dirname($metadataPath));if (!preg_match('/^[a-zA-Z0-9_-]+$/D',$slug) || is_link(dirname($metadataPath))) continue;
            if ($this->receipt($slug)!==null) {
                if ($this->isVerified($slug)) $result[$slug]=['kind'=>'receipt'];
                continue;
            }
            $base=$this->git(['show','HEAD:plugins/'.$slug.'/plugin.json']);
            if ($base===null) {
                $current=json_decode((string)file_get_contents($metadataPath),true);
                $tracked=$this->git(['ls-tree','-rz','HEAD','--','plugins/'.$slug.'/']);
                if (($tracked==='' || ($tracked===null && !is_dir($this->root.'/.git'))) && ($current['slug']??'')===$slug && is_string($current['version']??null) && preg_match('/^\d+\.\d+\.\d+(?:[-+][a-zA-Z0-9.-]+)?$/D',$current['version'])) {
                    $this->tree(dirname($metadataPath));$result[$slug]=['kind'=>'local-component'];
                }
                continue;
            }
            $remote=$this->git(['show','refs/remotes/origin/'.$branch.':plugins/'.$slug.'/plugin.json']);
            if ($remote===null) continue;
            $current=json_decode((string)file_get_contents($metadataPath),true);$old=json_decode($base,true);$new=json_decode($remote,true);
            if (($current['slug']??'')!==$slug || !is_string($current['version']??null) || !is_string($old['version']??null) || ($current['version']??'')!==($new['version']??'') || !version_compare($current['version'],$old['version'],'>')) continue;
            if ($this->matchesGit($slug,'refs/remotes/origin/'.$branch)) {
                $commit=trim((string)$this->git(['rev-parse','refs/remotes/origin/'.$branch]));
                $result[$slug]=['kind'=>'verified-git-package','commit'=>$commit,'version'=>$current['version']];
            }
        }
        return $result;
    }

    public function assertReplaceable(string $slug): array
    {
        self::slug($slug);$files=$this->tree($this->root.'/plugins/'.$slug);
        if (($this->receipt($slug)!==null && $this->isVerified($slug)) || ($this->receipt($slug)===null && $this->matchesGit($slug,'HEAD'))) return $files;
        if ($this->receipt($slug)===null && ($this->preserved()[$slug]['kind']??'')==='verified-git-package') return $files;
        throw new RuntimeException('Локальный код плагина '.$slug.' отличается от установленного пакета. Обновление остановлено; изменения сохранены.');
    }
}
