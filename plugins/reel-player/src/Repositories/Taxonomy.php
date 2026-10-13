<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** Additional metadata never changes audio files, IDs or album membership. */
final class Taxonomy
{
    public const MAX_FILTERS = 10;
    public const MAX_ASSIGNMENTS = 20;

    public function __construct(private int $owner, private bool $canManage = false) {}

    private static function kind(string $kind): string
    {
        if (!in_array($kind, ['genre','tag'], true)) throw new \InvalidArgumentException('Неизвестный справочник музыки.');
        return $kind;
    }

    private static function type(string $type): string
    {
        if (!in_array($type, ['track','album'], true)) throw new \InvalidArgumentException('Неизвестный тип музыки.');
        return $type;
    }

    public static function normalizedName(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 4096 || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) throw new \InvalidArgumentException('Некорректное название жанра или тега.');
        $name = trim((string)preg_replace('/\s+/u', ' ', $value));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) throw new \InvalidArgumentException('Название жанра или тега: от 1 до 120 символов.');
        return $name;
    }

    public static function nameKey(mixed $value): string
    {
        return hash('sha256', mb_convert_case(self::normalizedName($value), MB_CASE_FOLD, 'UTF-8'));
    }

    private static function slug(mixed $value, string $name): string
    {
        if (!is_string($value)) throw new \InvalidArgumentException('Некорректный адрес жанра или тега.');
        $slug = trim($value);
        if ($slug === '') {
            $slug = function_exists('make_slug') ? make_slug($name, '') : '';
            if ($slug === '') $slug = 'music-' . substr(self::nameKey($name), 0, 16);
            if (strlen($slug) > 120) $slug = rtrim(substr($slug, 0, 103), '-') . '-' . substr(self::nameKey($name), 0, 16);
        }
        $slug = strtolower($slug);
        if (strlen($slug) > 120 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new \InvalidArgumentException('Адрес: до 120 латинских букв, цифр и дефисов.');
        return $slug;
    }

    private static function description(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > 1000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) throw new \InvalidArgumentException('Описание: до 1000 символов.');
        return trim($value);
    }

    private static function id(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value) || filter_var($value, FILTER_VALIDATE_INT) === false) throw new \InvalidArgumentException('Некорректный идентификатор.');
        return (int)$value;
    }

    private static function ids(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > self::MAX_ASSIGNMENTS) throw new \InvalidArgumentException('Выберите не более 20 жанров или тегов.');
        $ids = array_values(array_unique(array_map(self::id(...), $values)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    private static function row(array $row): array
    {
        return ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'slug'=>(string)$row['slug'], 'description'=>(string)($row['description'] ?? '')];
    }

    public function dictionary(string $kind): array
    {
        self::kind($kind);
        if (!$this->canManage) throw new \InvalidArgumentException('Справочник доступен только администратору.');
        return array_map(self::row(...), db()->query("SELECT id,name,slug,description FROM reel_{$kind}s ORDER BY name,id LIMIT 1000")->get() ?: []);
    }

    /** Duplicate normalized names and slugs fail before any metadata is changed. */
    public function save(string $kind, array $data, int $id = 0): int
    {
        self::kind($kind);
        if (!$this->canManage) throw new \InvalidArgumentException('Изменять справочник может только администратор.');
        $name = self::normalizedName($data['name'] ?? '');
        $slug = self::slug($data['slug'] ?? '', $name);
        $description = self::description($data['description'] ?? '');
        $key = self::nameKey($name);
        if ($id < 0) throw new \InvalidArgumentException('Некорректный идентификатор.');
        if ($id && !db()->query("SELECT id FROM reel_{$kind}s WHERE id=?", [$id])->getOne()) throw new \InvalidArgumentException('Жанр или тег не найден.');
        if (db()->query("SELECT id FROM reel_{$kind}s WHERE (name_key=? OR slug=?) AND id<>?", [$key,$slug,$id])->getOne()) throw new \InvalidArgumentException('Такое название или адрес уже используется.');
        $now = date('Y-m-d H:i:s');
        try {
            if ($id) db()->query("UPDATE reel_{$kind}s SET name=?,name_key=?,slug=?,description=?,updated_at=? WHERE id=?", [$name,$key,$slug,$description,$now,$id]);
            else {
                db()->query("INSERT INTO reel_{$kind}s(name,name_key,slug,description,created_at,updated_at) VALUES(?,?,?,?,?,?)", [$name,$key,$slug,$description,$now,$now]);
                $id = (int)db()->getInsertId();
            }
        } catch (\PDOException $error) {
            // A concurrent editor can reach the unique index after our check.
            if ((string)$error->getCode() === '23000') throw new \InvalidArgumentException('Такое название или адрес уже используется.', 0, $error);
            throw $error;
        }
        return $id;
    }

    public function delete(string $kind, int $id): void
    {
        self::kind($kind);
        if (!$this->canManage || $id < 1) throw new \InvalidArgumentException('Изменять справочник может только администратор.');
        // Foreign keys remove metadata links; source music is never deleted.
        db()->query("DELETE FROM reel_{$kind}s WHERE id=?", [$id]);
    }

    private function owned(string $type, int $id, bool $lock = false): array
    {
        self::type($type);
        $row = $id > 0 && $this->owner > 0 ? db()->query("SELECT id,title,artist FROM reel_{$type}s WHERE id=? AND owner_id=?" . ($lock ? ' FOR UPDATE' : ''), [$id,$this->owner])->getOne() : false;
        if (!$row) throw new \InvalidArgumentException('Запись не найдена или её нельзя изменить.');
        return $row;
    }

    public function entity(string $type, int $id): array
    {
        $entity = $this->owned($type, $id);
        return self::decorate($type, [['id'=>$id,'title'=>(string)$entity['title'],'artist'=>(string)$entity['artist']]])[0];
    }

    public function replace(string $type, int $id, mixed $genres, mixed $tags): array
    {
        self::type($type);
        $selected = ['genre'=>self::ids($genres), 'tag'=>self::ids($tags)];
        db()->beginTransaction();
        try {
            $this->owned($type, $id, true);
            foreach ($selected as $kind=>$ids) {
                if ($ids) {
                    $found = db()->query("SELECT id FROM reel_{$kind}s WHERE id IN (" . implode(',',array_fill(0,count($ids),'?')) . ')', $ids)->get() ?: [];
                    if (count($found) !== count($ids)) throw new \InvalidArgumentException('Один из выбранных жанров или тегов больше не существует.');
                }
            }
            foreach ($selected as $kind=>$ids) {
                db()->query("DELETE FROM reel_{$type}_{$kind}s WHERE {$type}_id=?", [$id]);
                foreach ($ids as $taxonomyId) db()->query("INSERT INTO reel_{$type}_{$kind}s({$type}_id,{$kind}_id) VALUES(?,?)", [$id,$taxonomyId]);
            }
            db()->commit();
        } catch (\Throwable $error) {
            if (db()->inTransaction()) db()->rollBack();
            throw $error;
        }
        return $this->entity($type, $id);
    }

    private static function missingTable(\PDOException $error): bool
    {
        return (string)$error->getCode() === '42S02' || ((string)$error->getCode() === 'HY000' && str_contains($error->getMessage(),'no such table: reel_'));
    }

    /** Safe entities arrive already access-filtered; metadata is read in batches. */
    public static function decorate(string $type, array $items): array
    {
        self::type($type);
        $ids = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int)($item['id'] ?? 0), $items), static fn(int $id): bool => $id > 0)));
        $metadata = [];
        foreach (['genre','tag'] as $kind) foreach (array_chunk($ids, 200) as $chunk) {
            try {
                $rows = db()->query("SELECT links.{$type}_id AS entity_id,tax.id,tax.name,tax.slug FROM reel_{$type}_{$kind}s links JOIN reel_{$kind}s tax ON tax.id=links.{$kind}_id WHERE links.{$type}_id IN (" . implode(',',array_fill(0,count($chunk),'?')) . ') ORDER BY tax.name,tax.id', $chunk)->get() ?: [];
            } catch (\PDOException $error) { if (self::missingTable($error)) continue; throw $error; }
            foreach ($rows as $row) $metadata[(int)$row['entity_id']][$kind.'s'][] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'slug'=>(string)$row['slug']];
        }
        return array_map(static fn(array $item): array => [...$item,'genres'=>$metadata[(int)($item['id'] ?? 0)]['genres'] ?? [],'tags'=>$metadata[(int)($item['id'] ?? 0)]['tags'] ?? []], $items);
    }

    /** AND within each selected facet; invalid SQL identifiers are never accepted. */
    public static function filterSql(string $type, string $alias, array $filters): array
    {
        self::type($type);
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $alias)) throw new \InvalidArgumentException('Некорректное поле фильтра.');
        $parts = []; $params = []; $count = 0;
        foreach (['genre','tag'] as $kind) {
            $values = $filters[$kind] ?? [];
            if (is_string($values)) $values = $values === '' ? [] : [$values];
            if (!is_array($values) || !array_is_list($values)) throw new \InvalidArgumentException('Некорректный фильтр жанра или тега.');
            $count += count($values);
            if ($count > self::MAX_FILTERS) throw new \InvalidArgumentException('Можно выбрать не более 10 фильтров.');
            foreach (array_unique($values, SORT_REGULAR) as $slug) {
                if (!is_string($slug) || strlen($slug) > 120 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new \InvalidArgumentException('Некорректный адрес жанра или тега.');
                $parts[] = "EXISTS(SELECT 1 FROM reel_{$type}_{$kind}s links JOIN reel_{$kind}s tax ON tax.id=links.{$kind}_id WHERE links.{$type}_id={$alias}.id AND tax.slug=?)";
                $params[] = $slug;
            }
        }
        return ['sql'=>$parts ? ' AND ' . implode(' AND ', $parts) : '', 'params'=>$params];
    }
}
