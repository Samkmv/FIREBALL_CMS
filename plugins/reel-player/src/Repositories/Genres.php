<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

final class Genres
{
    public function fromTag(string $value): array
    {
        $aliases=['rock'=>'Рок','pop'=>'Поп','jazz'=>'Джаз','blues'=>'Блюз','classical'=>'Классическая музыка','electronic'=>'Электронная музыка','ambient'=>'Эмбиент','metal'=>'Метал','folk'=>'Фолк','country'=>'Кантри','hip-hop'=>'Хип-хоп','soul'=>'Соул','funk'=>'Фанк'];
        $known=[];foreach ($this->all() as $genre) $known[mb_strtolower($genre['name'])]=(int)$genre['id'];
        $ids=[];
        foreach (preg_split('~[;,/]~u',$value) ?: [] as $name) {
            $key=mb_strtolower(trim($name));$key=mb_strtolower($aliases[$key] ?? $key);
            if (isset($known[$key]) && !in_array($known[$key],$ids,true)) $ids[]=$known[$key];
            if (count($ids)>=50) break;
        }
        return $ids;
    }

    public function selection(array $data, array $fallback = []): array
    {
        if (array_key_exists('genre_ids',$data)) {
            $ids=$data['genre_ids'];
            if (is_string($ids)) {
                try { $ids=json_decode($ids,true,512,JSON_THROW_ON_ERROR); }
                catch (\JsonException) { throw new \InvalidArgumentException('Некорректный список жанров.'); }
            }
        } elseif (array_key_exists('genre_id',$data)) $ids=[$data['genre_id']];
        else return $fallback;
        if (!is_array($ids) || !array_is_list($ids) || count($ids)>50) throw new \InvalidArgumentException('Выберите до 50 жанров из библиотеки.');
        $result=[];
        foreach ($ids as $value) { $id=$this->validateId($value); if ($id && !in_array($id,$result,true)) $result[]=$id; }
        return $result;
    }

    private static function table(string $kind): string
    {
        if (!in_array($kind,['album','track'],true)) throw new \LogicException('Unknown genre owner.');
        return 'reel_'.$kind.'_genres';
    }

    private static function missingTable(\PDOException $error): bool
    {
        return (string)$error->getCode()==='42S02' || str_contains($error->getMessage(),'no such table:');
    }

    public function sync(string $kind,int $id,array $genres): void
    {
        $table=self::table($kind);$column=$kind.'_id';
        try { db()->query("DELETE FROM {$table} WHERE {$column}=?",[$id]); }
        catch (\PDOException $error) {
            if (self::missingTable($error)) throw new \InvalidArgumentException('Завершите обновление Tape Room в админ-панели, чтобы сохранять несколько жанров.');
            throw $error;
        }
        foreach ($genres as $position=>$genre) db()->query("INSERT INTO {$table} ({$column},genre_id) VALUES(?,?)",[$id,$genre]);
    }

    public function decorate(array $rows,string $kind): array
    {
        if (!$rows) return [];
        $table=self::table($kind);$column=$kind.'_id';$members=[];
        foreach (array_chunk(array_column($rows,'id'),500) as $ids) {
            try { $links=db()->query("SELECT {$column} AS item_id,g.id,g.name FROM {$table} m JOIN reel_genres g ON g.id=m.genre_id WHERE {$column} IN (".implode(',',array_fill(0,count($ids),'?')).") ORDER BY g.id",$ids)->get() ?: []; }
            catch (\PDOException $error) { if (self::missingTable($error)) break;throw $error; }
            foreach ($links as $link) $members[(int)$link['item_id']][]=['id'=>(int)$link['id'],'name'=>$link['name']];
        }
        $names=array_column($this->all(),'name','id');
        foreach ($rows as &$row) {
            $legacy=(int)($row['genreId'] ?? $row['genre_id'] ?? 0);
            $row['genres']=$members[(int)$row['id']] ?? ($legacy && isset($names[$legacy]) ? [['id'=>$legacy,'name'=>$names[$legacy]]] : []);
            usort($row['genres'],static fn(array $a,array $b): int=>(int)($b['id']===$legacy)-(int)($a['id']===$legacy) ?: $a['id']<=>$b['id']);
            $row['genreIds']=array_column($row['genres'],'id');$row['genreId']=$row['genreIds'][0] ?? 0;
            $row['genre']=implode(', ',array_column($row['genres'],'name'));
        }
        unset($row);return $rows;
    }

    public function all(): array
    {
        return array_map(static fn(array $row): array => ['id'=>(int)$row['id'],'name'=>$row['name'],'description'=>$row['description']], db()->query('SELECT id,name,description FROM reel_genres ORDER BY name,id')->get() ?: []);
    }

    public function find(int $id): array
    {
        $row = db()->query('SELECT * FROM reel_genres WHERE id=?', [$id])->getOne();
        if (!$row) throw new \InvalidArgumentException('Жанр не найден.');
        return $row;
    }

    public function validateId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) return null;
        if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || (int)$value < 1) throw new \InvalidArgumentException('Выберите жанр из библиотеки.');
        $this->find((int)$value);
        return (int)$value;
    }

    public function save(int $id, mixed $name, mixed $description): int
    {
        if (!is_string($name) || !is_string($description)) throw new \InvalidArgumentException('Некорректный жанр.');
        $name = trim($name); $description = trim($description);
        if ($name === '' || mb_strlen($name)>100 || mb_strlen($description)>1000 || preg_match('/[\x00-\x1f]/u',$name)) throw new \InvalidArgumentException('Название жанра: от 1 до 100 символов; описание: до 1000 символов.');
        $key=hash('sha256',mb_strtolower($name,'UTF-8')); $now=date('Y-m-d H:i:s');
        if (db()->query('SELECT id FROM reel_genres WHERE name_key=? AND id<>?', [$key,$id])->getOne()) throw new \InvalidArgumentException('Такой жанр уже есть в библиотеке.');
        if ($id) { $this->find($id); db()->query('UPDATE reel_genres SET name=?,name_key=?,description=?,updated_at=? WHERE id=?', [$name,$key,$description,$now,$id]); }
        else { db()->query('INSERT INTO reel_genres(name,name_key,slug,description,created_at,updated_at) VALUES(?,?,?,?,?,?)', [$name,$key,'genre-'.substr($key,0,12),$description,$now,$now]); $id=(int)db()->getInsertId(); }
        return $id;
    }

    public function counts(string $query = ''): array
    {
        return db()->query('SELECT g.*,(SELECT COUNT(*) FROM reel_albums a WHERE (a.genre_id=g.id OR EXISTS(SELECT 1 FROM reel_album_genres ag WHERE ag.album_id=a.id AND ag.genre_id=g.id)) AND a.published=1) AS albums,(SELECT COUNT(DISTINCT t.id) FROM reel_tracks t JOIN reel_album_tracks at ON at.track_id=t.id JOIN reel_albums a ON a.id=at.album_id WHERE a.published=1 AND (t.genre_id=g.id OR EXISTS(SELECT 1 FROM reel_track_genres tg WHERE tg.track_id=t.id AND tg.genre_id=g.id) OR (t.genre_id IS NULL AND NOT EXISTS(SELECT 1 FROM reel_track_genres tg WHERE tg.track_id=t.id) AND (a.genre_id=g.id OR EXISTS(SELECT 1 FROM reel_album_genres ag WHERE ag.album_id=a.id AND ag.genre_id=g.id))))) AS tracks FROM reel_genres g WHERE g.name LIKE ? ORDER BY g.name,g.id', ['%'.mb_substr(trim($query),0,100).'%'])->get() ?: [];
    }

    public function tracks(int $id): array
    {
        return db()->query('SELECT t.id,t.title,t.artist,a.id AS album_id,a.title AS album_title FROM reel_tracks t JOIN reel_album_tracks at ON at.track_id=t.id JOIN reel_albums a ON a.id=at.album_id WHERE a.published=1 AND (t.genre_id=? OR EXISTS(SELECT 1 FROM reel_track_genres tg WHERE tg.track_id=t.id AND tg.genre_id=?) OR (t.genre_id IS NULL AND NOT EXISTS(SELECT 1 FROM reel_track_genres tg WHERE tg.track_id=t.id) AND (a.genre_id=? OR EXISTS(SELECT 1 FROM reel_album_genres ag WHERE ag.album_id=a.id AND ag.genre_id=?)))) ORDER BY t.title,a.id LIMIT 200',[$id,$id,$id,$id])->get() ?: [];
    }
}
