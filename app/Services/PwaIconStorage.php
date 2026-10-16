<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use RuntimeException;

/** Published assets are immutable; only uploads belongs to the application. */
final class PwaIconStorage
{
    public const PREFIX = 'public/assets/default/pwa/';

    public static function names(): array
    {
        return [...array_map(fn($size) => 'icon-'.$size.'.png', [48,72,96,128,144,152,192,384,512]),
            'apple-touch-icon.png','favicon-16x16.png','favicon-32x32.png','icon-maskable-512.png','favicon.ico'];
    }

    public static function valid(string $name, string $data): bool
    {
        if (!in_array($name, self::names(), true) || strlen($data)>4*1048576) return false;
        if ($name==='favicon.ico') {
            // The previous generator embeds two PNGs in an ICO container.
            if (substr($data,0,4)!=="\0\0\1\0" || strlen($data)<22) return false;
            $count=unpack('v',substr($data,4,2))[1];
            if ($count<1 || $count>16 || strlen($data)<6+16*$count) return false;
            for ($i=0;$i<$count;$i++) {
                $entry=unpack('Vlength/Voffset',substr($data,6+16*$i+8,8));
                if ($entry['offset']<6+16*$count || $entry['length']<8 || $entry['offset']+$entry['length']>strlen($data)) return false;
                if (!@getimagesizefromstring(substr($data,$entry['offset'],$entry['length']))) return false;
            }
            return true;
        }
        $size=$name==='apple-touch-icon.png'?180:(int)(preg_match('/(?:icon-(?:maskable-)?|favicon-)(\d+)/',$name,$match)?$match[1]:0);
        $image=@getimagesizefromstring($data);
        return $image && $image[2]===IMAGETYPE_PNG && $image[0]===$size && $image[1]===$size;
    }

    public static function managed(string $path): bool
    {
        if (!str_starts_with($path,self::PREFIX)) return false;
        $name=substr($path,strlen(self::PREFIX));$file=ROOT.'/'.$path;
        foreach (['public','public/assets','public/assets/default','public/assets/default/pwa',$path] as $parent) if (is_link(ROOT.'/'.$parent)) return false;
        return in_array($name,self::names(),true) && is_file($file) && filesize($file)<=4*1048576 && self::valid($name,(string)file_get_contents($file));
    }

    public static function fingerprint(SiteSetting $settings): string
    {
        return hash('sha256',json_encode(array_map(fn($key)=>$settings->get($key,''),['site_favicon','pwa_app_icon','pwa_logo']),JSON_THROW_ON_ERROR));
    }

    public static function directory(string $name): string
    {
        if (!preg_match('/^(?:legacy|generated)-[a-f0-9]{64}$/D',$name)) throw new RuntimeException('Unsafe PWA icon set.');
        $path=ROOT;
        foreach (['public','uploads','pwa'] as $part) {
            $path.='/'.$part;
            if (is_link($path)) throw new RuntimeException('Unsafe PWA runtime directory.');
            if (!is_dir($path) && !mkdir($path,0755)) throw new RuntimeException('Unable to create PWA runtime directory.');
        }
        if (is_link($path.'/'.$name)) throw new RuntimeException('Unsafe PWA icon set.');
        return $path.'/'.$name;
    }

    /** Called only after the updater has verified its complete recovery snapshot. */
    public static function migrate(\ZipArchive $snapshot, SiteSetting $settings): void
    {
        $files=[];
        foreach (self::names() as $name) {
            $data=$snapshot->getFromName(self::PREFIX.$name);
            if (!is_string($data) || !self::valid($name,$data)) throw new RuntimeException('Не удалось безопасно сохранить старые PWA-иконки: '.$name);
            $files[$name]=$data;
        }
        $name='legacy-'.hash('sha256',implode('',array_map(fn($data)=>hash('sha256',$data),$files)));
        $destination=self::directory($name);
        if (!is_dir($destination)) {
            $stage=dirname($destination).'/.migration-'.bin2hex(random_bytes(8));
            if (!mkdir($stage,0755)) throw new RuntimeException('Unable to stage PWA migration.');
            try {
                foreach ($files as $file=>$data) if (file_put_contents($stage.'/'.$file,$data,LOCK_EX)!==strlen($data)) throw new RuntimeException('Unable to preserve PWA icon.');
                if (!rename($stage,$destination)) throw new RuntimeException('Unable to publish PWA migration.');
            } finally {
                if (is_dir($stage)) {foreach (self::names() as $file) if (is_file($stage.'/'.$file)) unlink($stage.'/'.$file);rmdir($stage);}
            }
        }
        foreach ($files as $file=>$data) if (is_link($destination.'/'.$file) || !is_file($destination.'/'.$file) || !hash_equals(hash('sha256',$data),(string)hash_file('sha256',$destination.'/'.$file))) throw new RuntimeException('PWA migration verification failed.');
        // A previous migration or a custom generated set already owns the active
        // raster icons. A later engine update must not replace that selection.
        $current=(new PwaService($settings))->icons();
        if (!str_contains((string)parse_url($current['apple']['src'], PHP_URL_PATH), '/uploads/pwa/')) {
            $changes=[];
            foreach (['site_favicon','pwa_app_icon','pwa_logo'] as $key) {
                $value=$settings->get($key,'');
                if (preg_match('~/assets/default/pwa/([^/]+)$~D', (string)parse_url($value,PHP_URL_PATH), $match) && isset($files[$match[1]])) {
                    $changes[$key]='/uploads/pwa/'.$name.'/'.$match[1];
                }
            }
            if ($changes!==[]) $settings->setMany($changes);
            $settings->setMany(['pwa_legacy_icon_set'=>$name,'pwa_legacy_icon_fingerprint'=>self::fingerprint($settings),'pwa_cache_version'=>bin2hex(random_bytes(8))]);
        }
    }

    public static function legacyDirectory(SiteSetting $settings): ?string
    {
        $name=$settings->get('pwa_legacy_icon_set','');
        if (!preg_match('/^legacy-[a-f0-9]{64}$/D',$name) || !hash_equals(self::fingerprint($settings),$settings->get('pwa_legacy_icon_fingerprint',''))) return null;
        $path=ROOT.'/public/uploads/pwa/'.$name;
        foreach (['public','public/uploads','public/uploads/pwa','public/uploads/pwa/'.$name] as $part) if (is_link(ROOT.'/'.$part)) return null;
        foreach (self::names() as $file) if (is_link($path.'/'.$file) || !is_file($path.'/'.$file)) return null;
        return $path;
    }
}
