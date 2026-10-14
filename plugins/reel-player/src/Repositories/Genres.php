<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

final class Genres
{
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
        return db()->query('SELECT g.*,COUNT(DISTINCT a.id) AS albums,COUNT(DISTINCT at.track_id) AS tracks FROM reel_genres g LEFT JOIN reel_albums a ON a.genre_id=g.id AND a.published=1 LEFT JOIN reel_album_tracks at ON at.album_id=a.id WHERE g.name LIKE ? GROUP BY g.id,g.name,g.description ORDER BY g.name,g.id', ['%'.mb_substr(trim($query),0,100).'%'])->get() ?: [];
    }
}
