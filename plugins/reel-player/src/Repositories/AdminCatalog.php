<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** The moderation surface lists submitted albums; private drafts stay with their owner. */
final class AdminCatalog
{
    public function overview(int $owner = 0, int $genre = 0, int $page = 1): array
    {
        $where = 'a.published=1'; $params=[];
        if ($owner>0) { $where.=' AND a.owner_id=?'; $params[]=$owner; }
        if ($genre>0) { $where.=' AND a.genre_id=?'; $params[]=$genre; }
        $page=max(1,min(100000,$page));$offset=($page-1)*50;
        return [
            'page'=>$page,'total'=>(int)db()->query("SELECT COUNT(*) FROM reel_albums a WHERE {$where}",$params)->getColumn(),
            'stats'=>db()->query("SELECT COUNT(*) AS albums,SUM(CASE WHEN published=1 THEN 1 ELSE 0 END) AS submitted,SUM(CASE WHEN published=1 AND moderation_status='pending' THEN 1 ELSE 0 END) AS pending,SUM(CASE WHEN published=1 AND moderation_status='approved' THEN 1 ELSE 0 END) AS approved FROM reel_albums".($owner>0?' WHERE owner_id=?':''),$owner>0?[$owner]:[])->getOne(),
            'users'=>db()->query("SELECT u.id,u.name,u.login,COUNT(a.id) AS albums,SUM(CASE WHEN a.published=1 THEN 1 ELSE 0 END) AS submitted,SUM(CASE WHEN a.published=1 AND a.moderation_status='approved' THEN 1 ELSE 0 END) AS approved FROM users u JOIN reel_albums a ON a.owner_id=u.id GROUP BY u.id,u.name,u.login ORDER BY u.name,u.id")->get() ?: [],
            'albums'=>db()->query("SELECT a.*,u.name AS owner_name,u.login AS owner_login,g.name AS genre_name,(SELECT COUNT(*) FROM reel_album_tracks at WHERE at.album_id=a.id) AS tracks FROM reel_albums a JOIN users u ON u.id=a.owner_id LEFT JOIN reel_genres g ON g.id=a.genre_id WHERE {$where} ORDER BY CASE WHEN a.moderation_status='pending' THEN 0 ELSE 1 END,a.updated_at DESC,a.id DESC LIMIT 50 OFFSET {$offset}",$params)->get() ?: [],
        ];
    }

    public function album(int $id): array
    {
        $album=db()->query('SELECT a.*,u.name AS owner_name,g.name AS genre_name FROM reel_albums a JOIN users u ON u.id=a.owner_id LEFT JOIN reel_genres g ON g.id=a.genre_id WHERE a.id=? AND a.published=1',[$id])->getOne();
        if (!$album) throw new \InvalidArgumentException('Альбом не отправлен на модерацию или больше недоступен.');
        $album['tracks']=db()->query('SELECT t.id,t.title,t.artist,t.duration,t.source,g.name AS genre FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id LEFT JOIN reel_genres g ON g.id=t.genre_id WHERE at.album_id=? ORDER BY at.position,at.track_id',[$id])->get() ?: [];
        $album['cover_available']=(bool)$album['cover_path'] || (bool)db()->query('SELECT t.cover_path FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND t.cover_path IS NOT NULL LIMIT 1',[$id])->getColumn();
        return $album;
    }

    public function review(int $id, string $decision, string $note, int $reviewer, string $revision): void
    {
        if (!in_array($decision,['approved','rejected'],true) || mb_strlen($note)>1000) throw new \InvalidArgumentException('Выберите решение и укажите комментарий до 1000 символов.');
        db()->beginTransaction();
        try {
            $album=db()->query('SELECT * FROM reel_albums WHERE id=? AND published=1 FOR UPDATE',[$id])->getOne();
            if (!$album || $album['moderation_status']!=='pending' || !hash_equals(self::revision($album),$revision)) throw new \InvalidArgumentException('Альбом изменился или уже рассмотрен. Обновите страницу и проверьте его заново.');
            if ($decision==='approved' && !(int)db()->query('SELECT COUNT(*) FROM reel_album_tracks WHERE album_id=?',[$id])->getColumn()) throw new \InvalidArgumentException('Нельзя одобрить пустой альбом.');
            db()->query('UPDATE reel_albums SET moderation_status=?,moderation_note=?,reviewed_by=?,reviewed_at=? WHERE id=?',[$decision,trim($note),$reviewer,date('Y-m-d H:i:s'),$id]);
            db()->commit();
        } catch (\Throwable $error) { db()->rollBack(); throw $error; }
    }

    public static function revision(array $album): string { return hash('sha256',json_encode([$album['id'],$album['title'],$album['artist'],$album['genre_id'],$album['updated_at'],$album['cover_path'],$album['moderation_status'],$album['moderation_version']],JSON_THROW_ON_ERROR)); }
}
