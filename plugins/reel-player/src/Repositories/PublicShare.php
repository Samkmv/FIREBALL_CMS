<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** Public shares always use approval checks, including for a signed-in owner. */
final class PublicShare
{
    public function get(string $kind,int $id): array
    {
        if ($kind==='album') {
            $item=db()->query("SELECT id,title,artist,cover_path FROM reel_albums WHERE id=? AND published=1 AND moderation_status='approved'",[$id])->getOne();
            if (!$item) throw new \InvalidArgumentException('Альбом не опубликован или больше недоступен.');
            $tracks=db()->query('SELECT t.id,t.title,t.artist,t.duration,t.cover_path FROM reel_tracks t JOIN reel_album_tracks at ON at.track_id=t.id WHERE at.album_id=? ORDER BY at.position,at.track_id',[$id])->get() ?: [];
        } elseif ($kind==='track') {
            $item=db()->query("SELECT t.id,t.title,t.artist,t.duration,t.cover_path FROM reel_tracks t WHERE t.id=? AND EXISTS(SELECT 1 FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=t.id AND a.published=1 AND a.moderation_status='approved')",[$id])->getOne();
            if (!$item) throw new \InvalidArgumentException('Песня не опубликована или больше недоступна.');
            $tracks=[$item];
        } else throw new \InvalidArgumentException('Запись не найдена.');
        $albumId=$kind==='album' ? $id : (int)db()->query("SELECT at.album_id FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id WHERE at.track_id=? AND a.published=1 AND a.moderation_status='approved' ORDER BY a.id LIMIT 1",[$id])->getColumn();
        if (!$tracks) throw new \InvalidArgumentException('В альбоме нет доступных песен.');
        $cover=$item['cover_path'] ? base_href('/tape-room/catalog/'.($kind==='album'?'album-cover/':'cover/').$id).'?v='.basename($item['cover_path']) : '';
        foreach ($tracks as &$track) {
            $track['id']=(int)$track['id'];$track['duration']=(float)$track['duration'];
            $track['url']=base_href('/tape-room/catalog/media/'.$track['id']);
            $track['cover']=$track['cover_path'] ? base_href('/tape-room/catalog/cover/'.$track['id']).'?v='.basename($track['cover_path']) : '';
            unset($track['cover_path']);
        }
        unset($track,$item['cover_path']);
        return ['kind'=>$kind,'id'=>$id,'albumId'=>$albumId,'title'=>$item['title'],'artist'=>$item['artist'],'cover'=>$cover ?: ($tracks[0]['cover'] ?? ''),'tracks'=>$tracks];
    }
}
