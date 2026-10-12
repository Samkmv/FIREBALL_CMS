<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

use Fireball\ReelPlayer\Services\MediaStorage;

/** Explicitly published albums are the sole boundary for cross-user audio access. */
final class Catalog
{
    public function __construct(private int $owner, private bool $canPublish = false) {}

    public function state(): array
    {
        $albums = db()->query('SELECT a.*, s.album_id AS saved_id FROM reel_albums a LEFT JOIN reel_album_saves s ON s.album_id=a.id AND s.owner_id=? WHERE a.owner_id=? OR a.published=1 ORDER BY a.created_at DESC,a.id DESC', [$this->owner,$this->owner])->get() ?: [];
        $memberships = db()->query('SELECT at.album_id,at.track_id FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE a.owner_id=? OR a.published=1 ORDER BY at.album_id,at.position,at.track_id', [$this->owner])->get() ?: [];
        $albumTracks = [];
        foreach ($memberships as $member) $albumTracks[(int)$member['album_id']][] = (int)$member['track_id'];
        $tracks = db()->query('SELECT t.*, CASE WHEN t.owner_id=? THEN t.favorite ELSE COALESCE(f.favorite,0) END AS viewer_favorite, COALESCE(stats.listens,0) AS listen_count,
            EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_album_saves s ON s.album_id=at.album_id WHERE at.track_id=t.id AND s.owner_id=?) AS saved_album,
            EXISTS(SELECT 1 FROM reel_playlist_tracks pt JOIN reel_playlists p ON p.id=pt.playlist_id WHERE pt.track_id=t.id AND p.owner_id=?) AS playlist_member
            FROM reel_tracks t LEFT JOIN reel_catalog_favorites f ON f.track_id=t.id AND f.owner_id=? LEFT JOIN reel_track_stats stats ON stats.track_id=t.id
            WHERE EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=t.id AND (a.owner_id=? OR a.published=1)) ORDER BY t.id DESC',
            [$this->owner,$this->owner,$this->owner,$this->owner,$this->owner])->get() ?: [];
        $chapters = db()->query('SELECT c.id,c.track_id,c.title,c.artist,c.start_time FROM reel_chapters c JOIN reel_tracks t ON t.id=c.track_id WHERE EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=t.id AND (a.owner_id=? OR a.published=1)) ORDER BY c.track_id,c.start_time,c.id', [$this->owner])->get() ?: [];
        $trackChapters = [];
        foreach ($chapters as $chapter) $trackChapters[(int)$chapter['track_id']][] = ['id'=>(int)$chapter['id'],'track_id'=>(int)$chapter['track_id'],'title'=>$chapter['title'],'artist'=>$chapter['artist'],'start'=>(float)$chapter['start_time']];
        $publicTracks = []; $coverByTrack = [];
        foreach ($tracks as $track) {
            $id = (int)$track['id']; $shared = (int)$track['owner_id'] !== $this->owner;
            $coverByTrack[$id] = $track['cover_path'];
            $prefix = $shared ? '/admin/reel-player/catalog/' : '/admin/reel-player/';
            $publicTracks[] = [
                'id'=>$id,'title'=>$track['title'],'artist'=>$track['artist'],'filename'=>$track['filename'],
                'mime'=>$track['mime'],'duration'=>(float)$track['duration'],'file_size'=>(int)$track['file_size'],
                'source'=>$track['source'],'cover_mime'=>$track['cover_mime'],'favorite'=>(bool)$track['viewer_favorite'],
                'created_at'=>$track['created_at'],'url'=>base_href($prefix.'media/'.$id),
                'cover'=>$track['cover_path'] ? base_href($prefix.'cover/'.$id).'?v='.basename($track['cover_path']) : '',
                'chapters'=>$trackChapters[$id] ?? [],'shared'=>$shared,'editable'=>!$shared,
                'saved'=>(bool)$track['saved_album'],
                'collected'=>!$shared || (bool)$track['saved_album'] || (bool)$track['viewer_favorite'] || (bool)$track['playlist_member'],
                'listen_count'=>(int)$track['listen_count'],
            ];
        }
        $publicAlbums = [];
        foreach ($albums as $album) {
            $id = (int)$album['id']; $ids = $albumTracks[$id] ?? [];
            $coverPath = $album['cover_path'];
            if (!$coverPath) foreach ($ids as $trackId) if (!empty($coverByTrack[$trackId])) { $coverPath = $coverByTrack[$trackId]; break; }
            $editable = (int)$album['owner_id'] === $this->owner;
            $publicAlbums[] = [
                'id'=>$id,'title'=>$album['title'],'artist'=>$album['artist'],'release_date'=>$album['release_date'],
                'description'=>$album['description'],'cover'=>$coverPath ? base_href('/admin/reel-player/catalog/album-cover/'.$id).'?v='.basename($coverPath) : '',
                'tracks'=>$ids,'published'=>(bool)$album['published'],'saved'=>$album['saved_id'] !== null,
                'editable'=>$editable,'canPublish'=>$editable && $this->canPublish,'created_at'=>$album['created_at'],
            ];
        }
        $publishedArtistKeys = []; $publishedTrackIds = [];
        foreach ($publicAlbums as $album) if ($album['published']) {
            $publishedArtistKeys[self::artistKey($album['artist'])] = true;
            foreach ($album['tracks'] as $trackId) $publishedTrackIds[$trackId] = true;
        }
        foreach ($publicTracks as $track) if (isset($publishedTrackIds[$track['id']])) $publishedArtistKeys[self::artistKey($track['artist'])] = true;
        return ['albums'=>$publicAlbums,'tracks'=>$publicTracks,'artistProfiles'=>$this->artistProfiles([...$albums,...$tracks],$publishedArtistKeys),'canPublish'=>$this->canPublish];
    }

    /** Artist identity remains its existing name; a profile never renames music. */
    private static function artistKey(string $name): string
    {
        return mb_strtolower(trim($name),'UTF-8');
    }

    private static function missingArtistTable(\PDOException $error): bool
    {
        return (string)$error->getCode()==='42S02' || ((string)$error->getCode()==='HY000' && str_contains($error->getMessage(),'no such table: reel_artist_profiles'));
    }

    /** Only matching accessible artist names are serialized, never private paths. */
    private function artistProfiles(array $items, array $publishedKeys): array
    {
        $keys = [];
        foreach ($items as $item) {
            $key = self::artistKey((string)($item['artist'] ?? ''));
            if ($key!=='') $keys[hash('sha256',$key)] = true;
        }
        if (!$keys) return [];
        $profiles = [];
        // Bound placeholders independently of the size of a user's collection.
        foreach (array_chunk(array_keys($keys),500) as $chunk) {
            try {
                $rows = db()->query('SELECT id,name,owner_id,image_path FROM reel_artist_profiles WHERE name_key IN ('.implode(',',array_fill(0,count($chunk),'?')).')', $chunk)->get() ?: [];
            } catch (\PDOException $error) {
                // A Git checkout can precede the plugin migration. Continue
                // ordinary playback until the CMS applies migration 007.
                if (self::missingArtistTable($error)) return [];
                throw $error;
            }
            foreach ($rows as $row) {
                $key = self::artistKey($row['name']);
                // A user-created private album with a guessed matching name
                // must not make another owner's unpublished photo visible.
                if ((int)$row['owner_id']!==$this->owner && !isset($publishedKeys[$key])) continue;
                $profiles[] = [
                    'id'=>(int)$row['id'],'name'=>$row['name'],'key'=>$key,
                    'image'=>$row['image_path'] ? base_href('/admin/reel-player/catalog/artist-image/'.(int)$row['id']).'?v='.basename($row['image_path']) : '',
                    'editable'=>$this->canPublish,
                ];
            }
        }
        return $profiles;
    }

    private function hasArtist(string $key, bool $publishedOnly = false): bool
    {
        $albumAccess = $publishedOnly ? 'published=1' : 'owner_id=? OR published=1';
        $trackAccess = ($publishedOnly ? '' : 't.owner_id=? OR ').'EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=t.id AND a.published=1)';
        $names = db()->query('SELECT artist FROM reel_albums WHERE '.$albumAccess.' UNION SELECT t.artist FROM reel_tracks t WHERE '.$trackAccess, $publishedOnly ? [] : [$this->owner,$this->owner])->get() ?: [];
        foreach ($names as $row) if (self::artistKey((string)$row['artist'])===$key) return true;
        return false;
    }

    /** Authorization must precede conditional image-cache responses. */
    public function artistCover(int $id): array
    {
        try { $profile = db()->query('SELECT name,owner_id,image_path,image_mime FROM reel_artist_profiles WHERE id=?', [$id])->getOne(); }
        catch (\PDOException $error) { if (self::missingArtistTable($error)) throw new \InvalidArgumentException('Изображение артиста пока не доступно.'); throw $error; }
        if (!$profile || !$profile['image_path'] || !$this->hasArtist(self::artistKey($profile['name']),(int)$profile['owner_id']!==$this->owner)) throw new \InvalidArgumentException('Изображение артиста не найдено или больше не доступно.');
        return ['path'=>$profile['image_path'],'mime'=>$profile['image_mime']];
    }

    private function updateArtist(string $action, array $data, ?array $cover): ?int
    {
        if (!$this->canPublish) throw new \InvalidArgumentException('Изображения артистов может изменять только создатель сайта.');
        $name = self::text($data['name'] ?? '',240,'Имя артиста',true);
        $key = self::artistKey($name);
        if ($action!=='artist.delete' && !$this->hasArtist($key)) throw new \InvalidArgumentException('Сначала добавьте музыку этого артиста в библиотеку или альбом.');
        $storage = new MediaStorage(); $saved = null; $oldPath = null; $id = null;
        db()->beginTransaction();
        try {
            try { $profile = db()->query('SELECT * FROM reel_artist_profiles WHERE name_key=? FOR UPDATE', [hash('sha256',$key)])->getOne(); }
            catch (\PDOException $error) { if (self::missingArtistTable($error)) throw new \InvalidArgumentException('Сначала завершите обновление плагина в настройках CMS.'); throw $error; }
            if ($action==='artist.delete') {
                if ($profile) {
                    $id = (int)$profile['id']; $oldPath = $profile['image_path'];
                    db()->query('UPDATE reel_artist_profiles SET image_path=NULL,image_mime=NULL,updated_at=? WHERE id=?', [date('Y-m-d H:i:s'),$id]);
                }
            } else {
                if (!$cover || (int)($cover['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) throw new \InvalidArgumentException('Выберите изображение артиста.');
                $saved = $storage->upload($cover,$this->owner,true);
                $now = date('Y-m-d H:i:s');
                if ($profile) {
                    $id = (int)$profile['id']; $oldPath = $profile['image_path'];
                    db()->query('UPDATE reel_artist_profiles SET owner_id=?,image_path=?,image_mime=?,updated_at=? WHERE id=?', [$this->owner,$saved['path'],$saved['mime'],$now,$id]);
                } else {
                    db()->query('INSERT INTO reel_artist_profiles(name_key,name,owner_id,image_path,image_mime,created_at,updated_at) VALUES(?,?,?,?,?,?,?)', [hash('sha256',$key),$name,$this->owner,$saved['path'],$saved['mime'],$now,$now]);
                    $id = (int)db()->getInsertId();
                }
            }
            db()->commit();
        } catch (\Throwable $error) { db()->rollBack(); if ($saved) $storage->delete($saved['path']); throw $error; }
        if ($oldPath) $storage->delete($oldPath);
        return $id;
    }

    /** Returns a server-only row: source paths are never serialized by state(). */
    public function accessibleTrack(int $id): array
    {
        $track = db()->query('SELECT t.* FROM reel_tracks t WHERE t.id=? AND (t.owner_id=? OR EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=t.id AND a.published=1))', [$id,$this->owner])->getOne();
        if (!$track) throw new \InvalidArgumentException('Трек не найден или больше не доступен.');
        return $track;
    }

    public function album(int $id, bool $edit = false): array
    {
        return $this->albumRow($id,$edit,false);
    }

    private function albumRow(int $id, bool $edit, bool $lock): array
    {
        $album = db()->query('SELECT * FROM reel_albums WHERE id=? AND '.($edit ? 'owner_id=?' : '(owner_id=? OR published=1)').($lock ? ' FOR UPDATE' : ''), [$id,$this->owner])->getOne();
        if (!$album) throw new \InvalidArgumentException($edit ? 'Этот альбом нельзя изменять.' : 'Альбом не найден или больше не доступен.');
        return $album;
    }

    public function albumCover(int $id): array
    {
        $album = $this->album($id);
        if ($album['cover_path']) return ['path'=>$album['cover_path'],'mime'=>$album['cover_mime']];
        $cover = db()->query('SELECT t.cover_path,t.cover_mime FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND t.cover_path IS NOT NULL ORDER BY at.position,at.track_id LIMIT 1', [$id])->getOne();
        if (!$cover) throw new \InvalidArgumentException('У альбома пока нет обложки.');
        return ['path'=>$cover['cover_path'],'mime'=>$cover['cover_mime']];
    }

    private static function text(mixed $value, int $max, string $label, bool $required = false): string
    {
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8')) throw new \InvalidArgumentException($label.': некорректное значение.');
        $value = trim($value);
        if (($required && $value==='') || mb_strlen($value)>$max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u',$value)) throw new \InvalidArgumentException($label.': до '.$max.' символов.');
        return $value;
    }

    private static function releaseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$value,$parts) || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]) || (int)$parts[1]<1000) throw new \InvalidArgumentException('Дата релиза должна быть в формате ГГГГ-ММ-ДД.');
        return $value;
    }

    private static function flag(mixed $value): int
    {
        if (!in_array($value,[0,1,'0','1',false,true],true)) throw new \InvalidArgumentException('Некорректное значение переключателя.');
        return (int)$value;
    }

    private static function trackIds(mixed $value): array
    {
        if (is_string($value)) {
            try { $value = json_decode($value,true,512,JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new \InvalidArgumentException('Некорректный список треков альбома.'); }
        }
        if (!is_array($value) || !array_is_list($value) || count($value)>1000) throw new \InvalidArgumentException('Альбом может содержать до 1000 треков.');
        $ids = [];
        foreach ($value as $id) {
            if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int)$id<=0) throw new \InvalidArgumentException('Некорректный ID трека альбома.');
            $ids[] = (int)$id;
        }
        if (count(array_unique($ids)) !== count($ids)) throw new \InvalidArgumentException('Трек повторяется в альбоме.');
        return $ids;
    }

    /** Called inside an album transaction; every member must belong to its editor. */
    private function setTracks(int $id, array $ids, bool $published): void
    {
        if ($published && !$ids) throw new \InvalidArgumentException('В опубликованном альбоме должен быть хотя бы один трек.');
        if ($ids) {
            $placeholders = implode(',',array_fill(0,count($ids),'?'));
            $owned = db()->query('SELECT id FROM reel_tracks WHERE owner_id=? AND id IN ('.$placeholders.') ORDER BY id FOR UPDATE', [$this->owner,...$ids])->get() ?: [];
            if (count($owned)!==count($ids)) throw new \InvalidArgumentException('В альбом можно добавить только собственные треки.');
        }
        db()->query('DELETE FROM reel_album_tracks WHERE album_id=?', [$id]);
        foreach ($ids as $position=>$track) db()->query('INSERT INTO reel_album_tracks(album_id,track_id,position) VALUES(?,?,?)', [$id,$track,$position]);
    }

    public function action(string $name, array $data, ?array $cover = null): ?int
    {
        if (in_array($name,['artist.update','artist.delete'],true)) return $this->updateArtist($name,$data,$cover);
        $id = (int)($data['id'] ?? 0);
        if ($name==='track.favorite') {
            $track = $this->accessibleTrack($id); $favorite = self::flag($data['favorite'] ?? null);
            if ((int)$track['owner_id']===$this->owner) { (new Library($this->owner))->action('track.favorite',['id'=>$id,'favorite'=>$favorite]); return null; }
            if ($favorite) db()->query('INSERT INTO reel_catalog_favorites(owner_id,track_id,favorite) VALUES(?,?,1) ON DUPLICATE KEY UPDATE favorite=1', [$this->owner,$id]);
            else db()->query('DELETE FROM reel_catalog_favorites WHERE owner_id=? AND track_id=?', [$this->owner,$id]);
            return null;
        }
        if ($name==='album.save') {
            $saved = self::flag($data['saved'] ?? 1);
            db()->beginTransaction();
            try {
                $this->albumRow($id,false,true);
                if ($saved) db()->query('INSERT IGNORE INTO reel_album_saves(owner_id,album_id,created_at) VALUES(?,?,?)', [$this->owner,$id,date('Y-m-d H:i:s')]);
                else db()->query('DELETE FROM reel_album_saves WHERE owner_id=? AND album_id=?', [$this->owner,$id]);
                db()->commit();
            } catch (\Throwable $error) { db()->rollBack(); throw $error; }
            return null;
        }
        if (!in_array($name,['album.create','album.update','album.delete','album.publish','album.tracks'],true)) throw new \InvalidArgumentException('Неизвестное действие каталога.');
        $storage = new MediaStorage(); $savedCover = null; $oldCover = null;
        db()->beginTransaction();
        try {
            if ($name==='album.create') {
                $title = self::text($data['title'] ?? '',240,'Название альбома',true);
                $artist = self::text($data['artist'] ?? '',240,'Исполнитель');
                $description = self::text($data['description'] ?? '',4000,'Описание');
                $releaseDate = self::releaseDate($data['release_date'] ?? '');
                if ($cover && (int)($cover['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $savedCover = $storage->upload($cover,$this->owner,true);
                $now = date('Y-m-d H:i:s');
                db()->query('INSERT INTO reel_albums(owner_id,title,artist,description,release_date,cover_path,cover_mime,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [$this->owner,$title,$artist,$description,$releaseDate,$savedCover['path'] ?? null,$savedCover['mime'] ?? null,$now,$now]);
                $id = (int)db()->getInsertId();
                $this->setTracks($id,self::trackIds($data['tracks'] ?? []),false);
                db()->query('INSERT INTO reel_album_saves(owner_id,album_id,created_at) VALUES(?,?,?)', [$this->owner,$id,$now]);
            } else {
                $album = $this->albumRow($id,true,true);
                switch ($name) {
                    case 'album.update':
                        $title = self::text($data['title'] ?? $album['title'],240,'Название альбома',true);
                        $artist = self::text($data['artist'] ?? $album['artist'],240,'Исполнитель');
                        $description = self::text($data['description'] ?? $album['description'],4000,'Описание');
                        $releaseDate = self::releaseDate($data['release_date'] ?? $album['release_date']);
                        if ($cover && (int)($cover['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) { $savedCover = $storage->upload($cover,$this->owner,true); $oldCover = $album['cover_path']; }
                        db()->query('UPDATE reel_albums SET title=?,artist=?,description=?,release_date=?,cover_path=?,cover_mime=?,updated_at=? WHERE id=? AND owner_id=?', [$title,$artist,$description,$releaseDate,$savedCover['path'] ?? $album['cover_path'],$savedCover['mime'] ?? $album['cover_mime'],date('Y-m-d H:i:s'),$id,$this->owner]);
                        if (array_key_exists('tracks',$data)) $this->setTracks($id,self::trackIds($data['tracks']),(bool)$album['published']);
                        break;
                    case 'album.tracks':
                        $this->setTracks($id,self::trackIds($data['tracks'] ?? null),(bool)$album['published']);
                        db()->query('UPDATE reel_albums SET updated_at=? WHERE id=?', [date('Y-m-d H:i:s'),$id]);
                        break;
                    case 'album.delete':
                        $oldCover = $album['cover_path'];
                        db()->query('DELETE FROM reel_albums WHERE id=? AND owner_id=?', [$id,$this->owner]);
                        break;
                    case 'album.publish':
                        $published = self::flag($data['published'] ?? 1);
                        if (!$this->canPublish) throw new \InvalidArgumentException('Публиковать альбомы может только создатель сайта.');
                        if ($published && !(int)db()->query('SELECT COUNT(*) FROM reel_album_tracks WHERE album_id=?', [$id])->getColumn()) throw new \InvalidArgumentException('Сначала добавьте треки в альбом.');
                        db()->query('UPDATE reel_albums SET published=?,updated_at=? WHERE id=? AND owner_id=?', [$published,date('Y-m-d H:i:s'),$id,$this->owner]);
                        break;
                }
            }
            db()->commit();
        } catch (\Throwable $error) { db()->rollBack(); if ($savedCover) $storage->delete($savedCover['path']); throw $error; }
        if ($oldCover) $storage->delete($oldCover);
        return $name==='album.create' ? $id : null;
    }

    public function beginListening(int $trackId): array
    {
        $track = $this->accessibleTrack($trackId);
        // Expired tokens contain no durable listening history. Any active user
        // can clear expired sessions, including those of users who do not return.
        db()->query('DELETE FROM reel_listening_sessions WHERE expires_at<NOW(6)');
        $rate = (int)db()->query('SELECT COUNT(*) FROM reel_listening_sessions WHERE owner_id=? AND started_at>=DATE_SUB(NOW(6),INTERVAL 1 MINUTE)', [$this->owner])->getColumn();
        if ($rate>=30) throw new \InvalidArgumentException('Слишком много переключений. Статистика будет доступна через минуту.');
        $duration = (float)$track['duration']; $threshold = $duration>0 ? min(30,max(.001,$duration/2)) : 30;
        $token = bin2hex(random_bytes(32));
        db()->query('INSERT INTO reel_listening_sessions(token_hash,owner_id,track_id,threshold,started_at,expires_at) VALUES(?,?,?,?,NOW(6),DATE_ADD(NOW(6),INTERVAL 2 HOUR))', [hash('sha256',$token),$this->owner,$trackId,$threshold]);
        return ['token'=>$token,'threshold'=>$threshold];
    }

    /** Counts elapsed listening, never navigation/clicks; tokens are user-bound and idempotent. */
    public function finishListening(string $token, mixed $seconds): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token) || !is_numeric($seconds) || !is_finite((float)$seconds) || (float)$seconds<0 || (float)$seconds>86400) throw new \InvalidArgumentException('Некорректная запись прослушивания.');
        $candidate = db()->query('SELECT track_id FROM reel_listening_sessions WHERE token_hash=? AND owner_id=? AND expires_at>=NOW(6)', [hash('sha256',$token),$this->owner])->getOne();
        if (!$candidate) throw new \InvalidArgumentException('Сеанс прослушивания устарел.');
        db()->beginTransaction();
        try {
            // Always lock the track before a session token. Parallel tabs then
            // cannot hold opposite tokens while waiting for the same track.
            $existing = db()->query('SELECT id FROM reel_tracks WHERE id=? FOR UPDATE', [(int)$candidate['track_id']])->getOne();
            if (!$existing) throw new \InvalidArgumentException('Трек больше не доступен.');
            $listen = db()->query('SELECT *,TIMESTAMPDIFF(MICROSECOND,started_at,NOW(6))/1000000 AS elapsed FROM reel_listening_sessions WHERE token_hash=? AND owner_id=? AND expires_at>=NOW(6) FOR UPDATE', [hash('sha256',$token),$this->owner])->getOne();
            if (!$listen) throw new \InvalidArgumentException('Сеанс прослушивания устарел.');
            $this->accessibleTrack((int)$listen['track_id']);
            $counted = (bool)$listen['counted']; $credited = (bool)$listen['credited'];
            $elapsed = max(0,(float)$listen['elapsed']); $threshold = (float)$listen['threshold'];
            if (!$counted && (float)$seconds >= $threshold && $elapsed+.2 >= $threshold && (float)$seconds <= $elapsed*2+1) {
                // A locking/current read sees the count committed by an earlier
                // tab even when the transaction previously read catalog access.
                // A processed duplicate consumes its token, but does not move
                // the credit window. Only an actual credited play may suppress
                // an overlapping tab or a very rapid repeat of this song.
                $duplicate = db()->query('SELECT token_hash FROM reel_listening_sessions WHERE owner_id=? AND track_id=? AND credited=1 AND started_at>=DATE_SUB(?,INTERVAL 30 SECOND) LIMIT 1 FOR UPDATE', [$this->owner,(int)$listen['track_id'],$listen['started_at']])->getOne();
                $credited = !$duplicate;
                if ($credited) db()->query('INSERT INTO reel_track_stats(track_id,listens) VALUES(?,1) ON DUPLICATE KEY UPDATE listens=listens+1', [(int)$listen['track_id']]);
                db()->query('UPDATE reel_listening_sessions SET counted=1,credited=? WHERE token_hash=?', [(int)$credited,hash('sha256',$token)]);
                $counted = true;
            }
            $listens = (int)db()->query('SELECT COALESCE(listens,0) FROM reel_track_stats WHERE track_id=?', [(int)$listen['track_id']])->getColumn();
            db()->commit();
            return ['counted'=>$counted,'credited'=>$credited,'listens'=>$listens,'track_id'=>(int)$listen['track_id']];
        } catch (\Throwable $error) { db()->rollBack(); throw $error; }
    }
}
