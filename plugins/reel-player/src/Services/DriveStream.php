<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Byte accounting is independent of HTTP output and cURL, so it is testable. */
final class DriveStream
{
    public function __construct(private DriveCache $cache, private \Closure $validAccount) {}

    public static function response(int $status, array $headers, int $start, int $end, int $size): int
    {
        if ($status === 416) throw new \RuntimeException('Google Drive изменил размер файла. Повторите воспроизведение.', 416);
        if (!in_array($status, [200,206], true)) throw new \RuntimeException(match ($status) {
            401 => 'Доступ Google Drive истёк. Войдите в Google Drive снова.',
            403 => 'Google Drive запретил доступ к этой песне.',
            404 => 'Песня или её версия больше недоступна в Google Drive.',
            429 => 'Google Drive временно ограничил загрузку. Попробуйте позже.',
            default => 'Google Drive временно недоступен. Повторите воспроизведение.',
        }, in_array($status,[401,403,404,429],true) ? $status : 502);
        if ($status === 206) {
            if (!preg_match('~^bytes (\d+)-(\d+)/(\d+)$~D', $headers['content-range'] ?? '', $m)
                || (int)$m[1] !== $start || (int)$m[2] !== $end || (int)$m[3] !== $size) throw new \RuntimeException('Некорректный диапазон Google Drive.', 502);
            $length = $end - $start + 1;
        } else {
            if (!empty($headers['content-range'])) throw new \RuntimeException('Некорректный ответ Google Drive.', 502);
            // If Range is ignored, discard only a bounded prefix, never an entire
            // FLAC just to seek near the end. The caller clips the returned body.
            if ($start > DriveCache::PREFIX_BYTES) throw new \RuntimeException('Google Drive не поддержал перемотку. Повторите попытку.', 502);
            $length = $size;
        }
        if (isset($headers['content-length']) && (!ctype_digit($headers['content-length']) || (int)$headers['content-length'] !== $length)) throw new \RuntimeException('Неверная длина ответа Google Drive.', 502);
        if (!empty($headers['content-encoding']) && strtolower($headers['content-encoding']) !== 'identity') throw new \RuntimeException('Неподдерживаемое сжатие Google Drive.', 502);
        return $status === 200 ? $start : 0;
    }

    /** Reader calls head(status,headers), then body(bytes); false cancels body. */
    private static function read(array $file, int $start, int $end, callable $reader, callable $consume, bool $warming): void
    {
        $remaining = $end - $start + 1; $skip = 0; $headed = false;
        $reader($file, $start, $end, $warming,
            static function(int $status, array $headers) use ($file,$start,$end,&$skip,&$headed): void {
                $skip = self::response($status,$headers,$start,$end,(int)$file['size']); $headed = true;
            },
            static function(string $bytes) use (&$skip,&$remaining,&$headed,$consume): bool {
                if (!$headed) throw new \RuntimeException('Google Drive не вернул заголовки.', 502);
                $discard = min($skip,strlen($bytes)); $skip -= $discard; $bytes = substr($bytes,$discard);
                $chunk = substr($bytes,0,$remaining);
                if ($chunk !== '') { if ($consume($chunk) === false) return false; $remaining -= strlen($chunk); }
                return $remaining > 0;
            });
        if (!$headed || $remaining !== 0) throw new \RuntimeException('Загрузка Google Drive прервалась. Повторите воспроизведение.', 502);
    }

    public function prepare(array $file, callable $reader): array
    {
        if ($this->cache->prefix($file)) return ['ready'=>true,'bytes'=>min(DriveCache::PREFIX_BYTES,(int)$file['size'])];
        // Splicing requires an immutable revision; do not mix two live versions.
        if (empty($file['headRevisionId'])) return ['ready'=>false,'reason'=>'revision-unavailable'];
        $ownerLock = $this->cache->lock($file['id'], true);
        if (!$ownerLock) return ['ready'=>false,'reason'=>'busy'];
        $lock = $this->cache->lock($file['id']);
        if (!$lock) { DriveCache::unlock($ownerLock); return ['ready'=>false,'reason'=>'busy']; }
        try {
            if (!$this->cache->prefix($file)) {
                $bytes = '';
                self::read($file,0,min(DriveCache::PREFIX_BYTES,(int)$file['size'])-1,$reader,static function(string $chunk) use (&$bytes): void { $bytes .= $chunk; },true);
                if (!($this->validAccount)()) throw new \RuntimeException('Google Drive отключён.', 403);
                $this->cache->savePrefix($file,$bytes);
            }
            return ['ready'=>true,'bytes'=>min(DriveCache::PREFIX_BYTES,(int)$file['size'])];
        } finally { DriveCache::unlock($lock); DriveCache::unlock($ownerLock); }
    }

    public function serve(array $file, string $rangeHeader, callable $reader, callable $headers, callable $output): void
    {
        $size = (int)$file['size']; $range = MediaStorage::range($size,$rangeHeader);
        if ($range === null) { $headers(416,['Content-Range'=>'bytes */'.$size,'Content-Length'=>'0']); return; }
        [$start,$end,$partial] = $range; $cursor = $start; $begun = false;
        $begin = static function() use (&$begun,$headers,$partial,$file,$start,$end,$size): void {
            if ($begun) return;
            $fields = ['Content-Type'=>$file['mimeType'],'Content-Length'=>(string)($end-$start+1),'Accept-Ranges'=>'bytes'];
            if ($partial) $fields['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            $headers($partial ? 206 : 200,$fields); $begun = true;
        };
        $prefix = !empty($file['headRevisionId']) ? $this->cache->prefix($file) : null;
        if ($prefix && $start < ($length = (int)filesize($prefix))) {
            $handle = @fopen($prefix,'rb');
            if ($handle) {
                fseek($handle,$start); $left = min($end+1,$length)-$start;
                while ($left > 0 && ($bytes = fread($handle,min(65536,$left))) !== false && $bytes !== '') {
                    $begin(); if ($output($bytes) === false) { fclose($handle); return; }
                    $left -= strlen($bytes); $cursor += strlen($bytes);
                }
                fclose($handle);
            }
        }
        if ($cursor > $end) return;
        $lock = null;
        if ($start === 0 && !$prefix && !empty($file['headRevisionId'])) {
            try { $lock = $this->cache->lock($file['id']); } catch (\Throwable) { /* A full/unwritable disk must not prevent streaming. */ }
        }
        $cacheBytes = ''; $target = min(DriveCache::PREFIX_BYTES,$size);
        try {
            self::read($file,$cursor,$end,$reader,function(string $bytes) use ($begin,$output,$lock,&$cacheBytes,$target): bool {
                if ($lock && strlen($cacheBytes) < $target) $cacheBytes .= substr($bytes,0,$target-strlen($cacheBytes));
                $begin(); return $output($bytes) !== false;
            },false);
        } finally {
            // A broken/aborted stream can still leave a complete, bounded prefix.
            if ($lock && strlen($cacheBytes) === $target && ($this->validAccount)()) {
                try { $this->cache->savePrefix($file,$cacheBytes); } catch (\Throwable) { /* Cache failure never stops native sound. */ }
            }
            DriveCache::unlock($lock);
        }
    }
}
