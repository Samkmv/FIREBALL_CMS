<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** Chapters only seek within an existing track; they never own audio files. */
final class Chapters
{
    public const MAX_ENTRIES = 1000;
    public const MAX_SECONDS = 604800;

    public function __construct(private int $owner) {}

    /** One owner-filtered query supplies the whole library without N+1 reads. */
    public function all(): array
    {
        $rows = db()->query('SELECT c.id,c.track_id,c.title,c.artist,c.start_time FROM reel_chapters c JOIN reel_tracks t ON t.id=c.track_id WHERE t.owner_id=? ORDER BY c.track_id,c.start_time,c.id',[$this->owner])->get() ?: [];
        $grouped = [];
        foreach ($rows as $row) $grouped[(int)$row['track_id']][] = self::publicRow($row);
        return $grouped;
    }

    public function list(int $track): array
    {
        (new Library($this->owner))->track($track);
        return $this->rows($track);
    }

    private function rows(int $track): array
    {
        return array_map(self::publicRow(...),db()->query('SELECT id,track_id,title,artist,start_time FROM reel_chapters WHERE track_id=? ORDER BY start_time,id',[$track])->get() ?: []);
    }

    private static function publicRow(array $row): array
    {
        return ['id'=>(int)$row['id'],'track_id'=>(int)$row['track_id'],'title'=>(string)$row['title'],'artist'=>(string)$row['artist'],'start'=>(float)$row['start_time']];
    }

    private static function text(mixed $text, bool $required): string
    {
        if (!is_string($text) || !mb_check_encoding($text,'UTF-8')) throw new \InvalidArgumentException('Некорректное название главы или исполнитель.');
        $text = trim($text);
        if (($required && $text==='') || mb_strlen($text)>240 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u',$text)) throw new \InvalidArgumentException('Название главы: до 240 символов; исполнитель необязателен.');
        return $text;
    }

    /** Normalize once, before any delete/insert, and again under the track lock. */
    public static function validateEntries(array $entries, float $duration = 0): array
    {
        if (!array_is_list($entries) || count($entries)>self::MAX_ENTRIES) throw new \InvalidArgumentException('Можно добавить до 1000 глав в один трек.');
        $result = []; $seen = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_numeric($entry['start'] ?? null)) throw new \InvalidArgumentException('Укажите начало главы в секундах.');
            $start = (float)$entry['start'];
            if (!is_finite($start) || $start<0 || $start>self::MAX_SECONDS) throw new \InvalidArgumentException('Начало главы должно быть от 0 до 604800 секунд.');
            $start = round($start,6);
            if ($duration>0 && $start >= $duration) throw new \InvalidArgumentException('Начало главы выходит за длительность выбранного трека.');
            $key = number_format($start,6,'.','');
            if (isset($seen[$key])) throw new \InvalidArgumentException('У двух глав одинаковое время начала.');
            $seen[$key] = true;
            $result[] = ['title'=>self::text($entry['title'] ?? '',true),'artist'=>self::text($entry['artist'] ?? '',false),'start'=>$start];
        }
        usort($result,static fn(array $a,array $b): int => $a['start'] <=> $b['start']);
        return $result;
    }

    private static function hash(array $chapters): string
    {
        return hash('sha256',json_encode($chapters,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR));
    }

    public function revision(int $track): string
    {
        (new Library($this->owner))->track($track);
        return self::hash($this->rows($track));
    }

    private static function mode(string $mode): void
    {
        if (!in_array($mode,['replace','merge'],true)) throw new \InvalidArgumentException('Выберите добавление или замену глав.');
    }

    public function preview(int $track, array $entries, string $mode = 'replace'): array
    {
        self::mode($mode);
        $row = (new Library($this->owner))->track($track);
        $existing = $this->rows($track);
        $entries = self::validateEntries($entries,(float)$row['duration']);
        $result = self::validateEntries($mode==='merge' ? [...$existing,...$entries] : $entries,(float)$row['duration']);
        return ['entries'=>$entries,'mode'=>$mode,'existing'=>count($existing),'total'=>count($result),'revision'=>self::hash($existing)];
    }

    private function locked(int $track, callable $operation): mixed
    {
        db()->beginTransaction();
        try {
            $row = (new Library($this->owner))->track($track,true);
            $result = $operation($row,$this->rows($track));
            db()->commit();
            return $result;
        } catch (\Throwable $error) { db()->rollBack(); throw $error; }
    }

    private function insert(int $track, array $entry): int
    {
        db()->query('INSERT INTO reel_chapters (track_id,title,artist,start_time) VALUES (?,?,?,?)',[$track,$entry['title'],$entry['artist'],number_format($entry['start'],6,'.','')]);
        return (int)db()->getInsertId();
    }

    public function create(int $track, array $entry): int
    {
        return $this->locked($track,function(array $row,array $existing) use ($track,$entry): int {
            $normalized = self::validateEntries([$entry],(float)$row['duration'])[0];
            self::validateEntries([...$existing,$normalized],(float)$row['duration']);
            return $this->insert($track,$normalized);
        });
    }

    public function update(int $track, int $id, array $entry): void
    {
        $this->locked($track,function(array $row,array $existing) use ($track,$id,$entry): void {
            $current = null; $remaining = [];
            foreach ($existing as $chapter) { if ($chapter['id']===$id) $current=$chapter; else $remaining[]=$chapter; }
            if (!$current) throw new \InvalidArgumentException('Глава не найдена.');
            $normalized = self::validateEntries([[...$current,...$entry]],(float)$row['duration'])[0];
            self::validateEntries([...$remaining,$normalized],(float)$row['duration']);
            db()->query('UPDATE reel_chapters SET title=?,artist=?,start_time=? WHERE id=? AND track_id=?',[$normalized['title'],$normalized['artist'],number_format($normalized['start'],6,'.',''),$id,$track]);
        });
    }

    public function delete(int $track, int $id): void
    {
        $this->locked($track,function(array $row,array $existing) use ($track,$id): void {
            if (!array_filter($existing,static fn(array $chapter): bool => $chapter['id']===$id)) throw new \InvalidArgumentException('Глава не найдена.');
            db()->query('DELETE FROM reel_chapters WHERE id=? AND track_id=?',[$id,$track]);
        });
    }

    public function import(int $track, array $entries, string $mode, bool $confirmed = false, ?string $revision = null): void
    {
        self::mode($mode);
        if ($mode==='replace' && !$confirmed) throw new \InvalidArgumentException('Подтвердите замену существующих глав.');
        $this->locked($track,function(array $row,array $existing) use ($track,$entries,$mode,$revision): void {
            if ($revision!==null && !hash_equals(self::hash($existing),$revision)) throw new \InvalidArgumentException('Главы изменились после предпросмотра. Проверьте импорт ещё раз.');
            $normalized = self::validateEntries($entries,(float)$row['duration']);
            self::validateEntries($mode==='merge' ? [...$existing,...$normalized] : $normalized,(float)$row['duration']);
            if ($mode==='replace') db()->query('DELETE FROM reel_chapters WHERE track_id=?',[$track]);
            foreach ($normalized as $entry) $this->insert($track,$entry);
        });
    }
}
