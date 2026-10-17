<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** One private, revisioned resume point per account; never stores audio or media URLs. */
final class Playback
{
    public function __construct(private int $owner) {}

    public function read(): ?array
    {
        $row = db()->query('SELECT * FROM reel_playback_state WHERE owner_id=?', [$this->owner])->getOne();
        return $row ? $this->present($row) : null;
    }

    public function write(array $input): array
    {
        $device = $input['device'] ?? '';
        $seenDevice = $input['seenDevice'] ?? null;
        $revision = $input['revision'] ?? null;
        if (!is_string($device) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $device)
            || !is_string($seenDevice) || ($seenDevice!=='' && !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$seenDevice))
            || !is_int($revision) || $revision < 0
            || !is_bool($input['claim'] ?? null) || !is_bool($input['playing'] ?? null)
            || !is_array($input['state'] ?? null)) {
            throw new \InvalidArgumentException('Некорректное состояние плеера.');
        }
        $state = $this->normalize($input['state']);
        if ($state === null) throw new \InvalidArgumentException('Песня недоступна для синхронизации.');
        db()->beginTransaction();
        try {
            db()->query('INSERT IGNORE INTO reel_playback_state(owner_id) VALUES(?)', [$this->owner]);
            $row = db()->query('SELECT * FROM reel_playback_state WHERE owner_id=? FOR UPDATE', [$this->owner])->getOne();
            // Only an explicit user action may take over another device. An old
            // background tab can never overwrite a newer revision on its own.
            $accepted = ($input['claim'] && $row['device'] === $seenDevice)
                || ($row['device'] === $device && (int)$row['revision'] === $revision);
            if ($accepted) {
                $row = ['owner_id'=>$this->owner, 'revision'=>(int)$row['revision']+1, 'device'=>$device,
                    'state_json'=>json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'playing'=>$input['playing'] ? 1 : 0, 'updated_ms'=>(int)floor(microtime(true)*1000)];
                db()->query('UPDATE reel_playback_state SET revision=?,device=?,state_json=?,playing=?,updated_ms=? WHERE owner_id=?',
                    [$row['revision'],$row['device'],$row['state_json'],$row['playing'],$row['updated_ms'],$this->owner]);
            }
            db()->commit();
        } catch (\Throwable $error) {
            db()->rollBack();
            throw $error;
        }
        return ['accepted'=>$accepted, 'playback'=>$this->present($row)];
    }

    private function present(array $row): ?array
    {
        if (empty($row['state_json'])) return null;
        $raw = json_decode($row['state_json'], true);
        $state = is_array($raw) ? $this->normalize($raw) : null;
        if ($state === null) return null;
        $now = (int)floor(microtime(true)*1000);
        return ['revision'=>(int)$row['revision'], 'device'=>(string)$row['device'],
            'playing'=>(bool)$row['playing'], 'active'=>(bool)$row['playing'] && $now-(int)$row['updated_ms']<30000,
            'updatedAt'=>(int)$row['updated_ms'], 'serverTime'=>$now, 'state'=>$state];
    }

    private function normalize(array $state): ?array
    {
        $current = $state['current'] ?? null;
        $time = $state['time'] ?? null;
        $queue = $state['queue'] ?? [];
        if (!is_int($current) || $current <= 0 || $current >= 4000000000000000
            || !is_numeric($time) || !is_finite((float)$time) || (float)$time < 0 || (float)$time > 604800
            || !is_array($queue) || !array_is_list($queue) || count($queue)>1000) {
            throw new \InvalidArgumentException('Некорректная очередь или позиция воспроизведения.');
        }
        foreach ($queue as $id) if (!is_int($id) || $id<=0 || $id>=4000000000000000) throw new \InvalidArgumentException('Некорректная очередь воспроизведения.');
        $ids = array_values(array_unique([$current,...$queue]));
        $marks = implode(',', array_fill(0,count($ids),'?'));
        $tracks = db()->query("SELECT t.id,t.duration FROM reel_tracks t WHERE t.id IN ($marks) AND (t.owner_id=? OR EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=t.id AND a.published=1 AND a.moderation_status='approved'))", [...$ids,$this->owner])->get() ?: [];
        $allowed = array_column($tracks, null, 'id');
        if (!isset($allowed[$current])) return null;
        $queue = array_values(array_unique(array_filter($queue, static fn(int $id): bool => isset($allowed[$id]))));
        if (!in_array($current,$queue,true)) $queue[] = $current;
        $source = is_array($state['queueSource'] ?? null) ? $state['queueSource'] : [];
        if ((isset($state['queueName']) && !is_string($state['queueName'])) || (isset($source['artist']) && !is_string($source['artist']))) throw new \InvalidArgumentException('Некорректное название коллекции.');
        $type = in_array($source['type'] ?? '', ['songs','favorites','playlist','album','artist','genre','popular'],true) ? $source['type'] : 'songs';
        $source = ['type'=>$type, 'id'=>max(0,(int)($source['id'] ?? 0)), 'artist'=>mb_substr((string)($source['artist'] ?? ''),0,240)];
        $duration = (float)$allowed[$current]['duration'];
        return ['current'=>$current, 'time'=>$duration>0 ? min($duration,(float)$time) : (float)$time,
            'queue'=>$queue, 'queueSource'=>$source, 'queueName'=>mb_substr((string)($state['queueName'] ?? ''),0,240),
            'queueSort'=>in_array($state['queueSort'] ?? '',['new','old','playlist'],true) ? $state['queueSort'] : 'playlist',
            'queuePlaylist'=>$type==='playlist' ? $source['id'] : ($type==='favorites' ? -1 : 0),
            'shuffle'=>(bool)($state['shuffle'] ?? false), 'repeat'=>in_array($state['repeat'] ?? '',['off','all','one'],true) ? $state['repeat'] : 'off'];
    }
}
