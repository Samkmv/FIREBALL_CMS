<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** Public identities never include login, email, credentials or private media. */
final class Social
{
    public const PAGE_SIZE=24;
    private const PUBLISHED="a.published=1 AND a.moderation_status='approved'";
    public function __construct(private int $viewer=0){}

    private static function missing(\PDOException $error):bool
    {
        return (string)$error->getCode()==='42S02' || str_contains($error->getMessage(),'no such table: reel_social_profiles') || str_contains($error->getMessage(),'no such table: reel_user_follows');
    }
    private static function unavailable(\PDOException $error):never
    {
        if(self::missing($error))throw new \RuntimeException('Раздел пользователей станет доступен после завершения обновления Tape Room.',503,$error);
        throw $error;
    }
    private static function identity(array $row):array
    {
        $id=(int)$row['id'];
        return ['id'=>$id,'name'=>trim((string)$row['name']) ?: 'Слушатель '.$id,
            'avatar'=>get_user_avatar($row['avatar'] ?? null,'lg'),'url'=>base_href('/tape-room/people/'.$id)];
    }
    private function stats():string
    {
        return '(SELECT COUNT(*) FROM reel_albums a WHERE a.owner_id=u.id AND '.self::PUBLISHED.') AS albums_count,
            (SELECT COUNT(*) FROM reel_user_follows f JOIN users other ON other.id=f.follower_id LEFT JOIN reel_social_profiles osp ON osp.owner_id=other.id WHERE f.followed_id=u.id AND COALESCE(osp.profile_public,1)=1) AS followers_count,
            (SELECT COUNT(*) FROM reel_user_follows f JOIN users other ON other.id=f.followed_id LEFT JOIN reel_social_profiles osp ON osp.owner_id=other.id WHERE f.follower_id=u.id AND COALESCE(osp.profile_public,1)=1) AS following_count,
            EXISTS(SELECT 1 FROM reel_user_follows mine WHERE mine.followed_id=u.id AND mine.follower_id=?) AS is_following';
    }
    private function person(array $row):array
    {
        return [...self::identity($row),'bio'=>(string)($row['bio'] ?? ''),'profilePublic'=>(bool)($row['profile_public'] ?? true),
            'collectionPublic'=>(bool)($row['collection_public'] ?? false),'mine'=>(int)$row['id']===$this->viewer,
            'albumsCount'=>(int)$row['albums_count'],'followersCount'=>(int)$row['followers_count'],
            'followingCount'=>(int)$row['following_count'],'following'=>(bool)$row['is_following']];
    }
    public function profile(int $id):array
    {
        try{$row=db()->query('SELECT u.id,u.name,u.avatar,COALESCE(sp.bio,\'\') AS bio,COALESCE(sp.profile_public,1) AS profile_public,
            COALESCE(sp.collection_public,0) AS collection_public,'.$this->stats().'
            FROM users u LEFT JOIN reel_social_profiles sp ON sp.owner_id=u.id WHERE u.id=? AND (COALESCE(sp.profile_public,1)=1 OR u.id=?)',[$this->viewer,$id,$this->viewer])->getOne();}
        catch(\PDOException $error){self::unavailable($error);}
        if(!$row)throw new \OutOfBoundsException('Профиль не найден или скрыт.');
        return $this->person($row);
    }
    public function directory(string $query='',int $page=1,string $list='all',int $person=0):array
    {
        if(!mb_check_encoding($query,'UTF-8') || mb_strlen($query)>80)throw new \InvalidArgumentException('Поиск: не более 80 символов.');
        if(!in_array($list,['all','following','followers'],true))throw new \InvalidArgumentException('Неизвестный список пользователей.');
        $where='COALESCE(sp.profile_public,1)=1';$params=[];
        if($list!=='all'){
            $subject=$person ?: $this->viewer;
            if(!$subject)throw new \InvalidArgumentException('Войдите, чтобы просмотреть свои подписки.');
            if($person)$this->profile($person);
            $where.=$list==='following'?' AND EXISTS(SELECT 1 FROM reel_user_follows f WHERE f.followed_id=u.id AND f.follower_id=?)':' AND EXISTS(SELECT 1 FROM reel_user_follows f WHERE f.follower_id=u.id AND f.followed_id=?)';$params[]=$subject;
        }
        if(trim($query)!==''){$where.=" AND u.name LIKE ? ESCAPE '!'";$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($query)).'%';}
        try{
            $total=(int)db()->query('SELECT COUNT(*) FROM users u LEFT JOIN reel_social_profiles sp ON sp.owner_id=u.id WHERE '.$where,$params)->getColumn();
            $pages=max(1,(int)ceil($total/self::PAGE_SIZE));$page=max(1,min($page,$pages));$offset=($page-1)*self::PAGE_SIZE;
            $rows=db()->query('SELECT u.id,u.name,u.avatar,COALESCE(sp.bio,\'\') AS bio,COALESCE(sp.collection_public,0) AS collection_public,'.$this->stats().'
                FROM users u LEFT JOIN reel_social_profiles sp ON sp.owner_id=u.id WHERE '.$where.' ORDER BY albums_count DESC,u.id DESC LIMIT '.self::PAGE_SIZE.' OFFSET '.$offset,[$this->viewer,...$params])->get() ?: [];
        }catch(\PDOException $error){self::unavailable($error);}
        return ['people'=>array_map($this->person(...),$rows),'total'=>$total,'page'=>$page,'pages'=>$pages,'query'=>$query,'list'=>$list];
    }
    /** One bounded query decorates catalogue authors; missing migrations keep playback available. */
    public function summaries(array $ids):array
    {
        $result=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        foreach(array_chunk($ids,500)as$batch){
            try{$rows=db()->query('SELECT u.id,u.name,u.avatar FROM users u LEFT JOIN reel_social_profiles sp ON sp.owner_id=u.id WHERE COALESCE(sp.profile_public,1)=1 AND u.id IN ('.implode(',',array_fill(0,count($batch),'?')).')',$batch)->get() ?: [];}
            catch(\PDOException $error){if(self::missing($error))return [];throw $error;}
            foreach($rows as$row)$result[(int)$row['id']]=self::identity($row);
        }
        return $result;
    }
    public function albums(int $owner,string $tab='published',int $page=1):array
    {
        $profile=$this->profile($owner);
        if(!in_array($tab,['published','collection'],true))throw new \InvalidArgumentException('Неизвестная коллекция.');
        // An owner's preview still contains only published, approved albums.
        if($tab==='collection'&&!$profile['collectionPublic']&&!$profile['mine'])return ['albums'=>[],'total'=>0,'page'=>1,'pages'=>1,'private'=>true];
        $where=self::PUBLISHED.($tab==='published'?' AND a.owner_id=?':' AND EXISTS(SELECT 1 FROM reel_album_saves s WHERE s.album_id=a.id AND s.owner_id=?)');
        $total=(int)db()->query('SELECT COUNT(*) FROM reel_albums a WHERE '.$where,[$owner])->getColumn();$pages=max(1,(int)ceil($total/self::PAGE_SIZE));$page=max(1,min($page,$pages));
        $rows=db()->query('SELECT a.id,a.title,a.artist,a.cover_path,a.release_date,
            (SELECT COUNT(*) FROM reel_album_tracks at WHERE at.album_id=a.id) AS track_count,
            (SELECT COALESCE(SUM(t.duration),0) FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=a.id) AS duration,
            (SELECT t.cover_path FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=a.id AND t.cover_path IS NOT NULL ORDER BY at.position,at.track_id LIMIT 1) AS track_cover
            FROM reel_albums a WHERE '.$where.' ORDER BY a.created_at DESC,a.id DESC LIMIT '.self::PAGE_SIZE.' OFFSET '.(($page-1)*self::PAGE_SIZE),[$owner])->get() ?: [];
        $albums=[];foreach($rows as$row){$cover=$row['cover_path'] ?: $row['track_cover'];$albums[]=['id'=>(int)$row['id'],'title'=>$row['title'],'artist'=>$row['artist'],
            'url'=>base_href('/').'?album='.(int)$row['id'],'cover'=>$cover?base_href('/tape-room/catalog/album-cover/'.(int)$row['id']).'?v='.basename($cover):'',
            'tracks'=>(int)$row['track_count'],'duration'=>(float)$row['duration'],'releaseDate'=>$row['release_date']];}
        return ['albums'=>$albums,'total'=>$total,'page'=>$page,'pages'=>$pages,'private'=>false];
    }
    public function settings():array
    {
        $defaults=['available'=>true,'bio'=>'','profilePublic'=>true,'collectionPublic'=>false];
        if(!$this->viewer)return $defaults;
        try{$row=db()->query('SELECT bio,profile_public,collection_public FROM reel_social_profiles WHERE owner_id=?',[$this->viewer])->getOne();}
        catch(\PDOException $error){if(self::missing($error))return [...$defaults,'available'=>false];throw $error;}
        return $row?['available'=>true,'bio'=>$row['bio'],'profilePublic'=>(bool)$row['profile_public'],'collectionPublic'=>(bool)$row['collection_public']]:$defaults;
    }
    private function actor():void{if($this->viewer<=0)throw new \InvalidArgumentException('Сначала войдите в аккаунт.');}
    public function follow(int $target,bool $following):array
    {
        $this->actor();if($target===$this->viewer)throw new \InvalidArgumentException('На себя подписаться нельзя.');
        db()->beginTransaction();
        try{
            $actors=db()->query('SELECT id FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE',[$this->viewer,$target])->get() ?: [];
            if(count($actors)!==2)throw new \OutOfBoundsException('Пользователь не найден.');
            if($following){$this->profile($target);db()->query('INSERT IGNORE INTO reel_user_follows(follower_id,followed_id,created_at) VALUES(?,?,?)',[$this->viewer,$target,date('Y-m-d H:i:s')]);}
            else db()->query('DELETE FROM reel_user_follows WHERE follower_id=? AND followed_id=?',[$this->viewer,$target]);
            db()->commit();
        }catch(\Throwable $error){db()->rollBack();if($error instanceof \PDOException)self::unavailable($error);throw $error;}
        try{$profile=$this->profile($target);}catch(\OutOfBoundsException){$profile=null;}
        return ['following'=>$following,'target'=>$target,'person'=>$profile];
    }
    public function saveSettings(array $data):array
    {
        $this->actor();$bio=$data['bio'] ?? '';
        if(!is_string($bio)||!mb_check_encoding($bio,'UTF-8')||mb_strlen($bio)>500||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u',$bio))throw new \InvalidArgumentException('О себе: до 500 символов.');
        $flags=[];foreach(['profile_public','collection_public']as$key){if(!in_array($data[$key]??'0',['0','1',0,1,false,true],true))throw new \InvalidArgumentException('Некорректная настройка профиля.');$flags[$key]=(int)($data[$key]??0);}
        db()->beginTransaction();
        try{
            if(!db()->query('SELECT id FROM users WHERE id=? FOR UPDATE',[$this->viewer])->getOne())throw new \OutOfBoundsException('Аккаунт не найден.');
            $existing=db()->query('SELECT owner_id FROM reel_social_profiles WHERE owner_id=?',[$this->viewer])->getOne();$now=date('Y-m-d H:i:s');
            if($existing)db()->query('UPDATE reel_social_profiles SET bio=?,profile_public=?,collection_public=?,updated_at=? WHERE owner_id=?',[trim($bio),$flags['profile_public'],$flags['collection_public'],$now,$this->viewer]);
            else db()->query('INSERT INTO reel_social_profiles(owner_id,bio,profile_public,collection_public,created_at,updated_at) VALUES(?,?,?,?,?,?)',[$this->viewer,trim($bio),$flags['profile_public'],$flags['collection_public'],$now,$now]);
            db()->commit();
        }catch(\Throwable $error){db()->rollBack();if($error instanceof \PDOException)self::unavailable($error);throw $error;}
        return $this->settings();
    }
}
