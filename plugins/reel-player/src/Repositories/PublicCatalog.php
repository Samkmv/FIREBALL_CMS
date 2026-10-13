<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

use Fireball\ReelPlayer\Services\MusicAccessPolicy;
use Fireball\ReelPlayer\Services\PlatformFeatures;

/** Public DTOs and SQL scopes are independent of the private Library::state(). */
final class PublicCatalog
{
    private MusicAccessPolicy $policy;
    public function __construct(private ?array $user = null, ?MusicAccessPolicy $policy = null) { $this->policy = $policy ?? new MusicAccessPolicy($user); }
    public function viewerId(): int { return $this->policy->viewerId(); }
    public function metadataScope(string $type, string $alias): array { return $this->policy->metadataScope($type, $alias); }
    private static function pagination(int $page, int $limit, int $total): array { return ['page' => $page, 'per_page' => $limit, 'total' => $total, 'total_pages' => (int)ceil($total / $limit)]; }
    private static function bounds(int $page, int $limit): array { return [max(1, min(1000000, $page)), max(1, min(48, $limit))]; }
    private static function missing(): never { throw new \InvalidArgumentException('Музыка не найдена или недоступна.'); }
    private static function rule(array $row, string $type): array
    {
        $rule = ['access_mode' => $row['dist_access_mode'] ?? 'internal', 'show_metadata' => (bool)($row['dist_show_metadata'] ?? false), 'plan_ids' => []];
        if ($type === 'track') foreach (['rights_confirmed', 'license_type', 'license_url', 'allow_stream', 'allow_embed', 'allow_download', 'purchase_url'] as $key) $rule[$key] = $row['dist_' . $key] ?? (str_ends_with($key, 'url') || $key === 'license_type' ? '' : false);
        return $rule;
    }
    private static function distributionSelect(string $alias, string $type): string
    {
        $keys = ['access_mode', 'show_metadata'];
        if ($type === 'track') $keys = [...$keys, 'rights_confirmed', 'license_type', 'license_url', 'allow_stream', 'allow_embed', 'allow_download', 'purchase_url'];
        return implode(',', array_map(static fn(string $key): string => $alias . '.' . $key . ' AS dist_' . $key, $keys));
    }
    private function filter(string $type, string $alias, array $filters): array
    {
        $sql = ''; $params = [];
        $q = $filters['q'] ?? '';
        if (!is_string($q) || !mb_check_encoding($q, 'UTF-8') || mb_strlen($q) > 240) throw new \InvalidArgumentException('Поисковый запрос слишком длинный.');
        $q = trim($q);
        if ($q !== '') {
            // Parameterized LIKE uses an explicit escape character, not SQL mode.
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $sql .= " AND ({$alias}.title LIKE ? ESCAPE '!' OR {$alias}.artist LIKE ? ESCAPE '!'";
            $params = [$like, $like];
            if ($type === 'track') {
                $albumScope = $this->metadataScope('album', 'qs_a');
                $sql .= " OR EXISTS(SELECT 1 FROM reel_album_tracks qs_at JOIN reel_albums qs_a ON qs_a.id=qs_at.album_id WHERE qs_at.track_id={$alias}.id AND {$albumScope['sql']} AND qs_a.title LIKE ? ESCAPE '!')";
                $params = [...$params, ...$albumScope['params'], $like];
            }
            if (PlatformFeatures::enabled('discovery')) foreach (['genre', 'tag'] as $kind) {
                $table = 'reel_' . $type . '_' . $kind . 's';
                $sql .= " OR EXISTS(SELECT 1 FROM {$table} qs_link JOIN reel_{$kind}s qs_tax ON qs_tax.id=qs_link.{$kind}_id WHERE qs_link.{$type}_id={$alias}.id AND qs_tax.name LIKE ? ESCAPE '!')";
                $params[] = $like;
            }
            $sql .= ')';
        }
        if (isset($filters['artist']) && $filters['artist'] !== '') {
            if (!is_string($filters['artist']) || mb_strlen($filters['artist']) > 240) throw new \InvalidArgumentException('Некорректный исполнитель.');
            $sql .= " AND LOWER(TRIM({$alias}.artist))=LOWER(TRIM(?))"; $params[] = $filters['artist'];
        }
        if (PlatformFeatures::enabled('discovery')) {
            $taxonomy = Taxonomy::filterSql($type, $alias, $filters);
            $sql .= $taxonomy['sql']; $params = [...$params, ...$taxonomy['params']];
        }
        foreach (['from', 'to'] as $key) if (!empty($filters[$key])) {
            $date = $filters[$key];
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || date('Y-m-d', strtotime($date) ?: 0) !== $date) throw new \InvalidArgumentException('Некорректная дата релиза.');
            $op = $key === 'from' ? '>=' : '<=';
            if ($type === 'album') $sql .= " AND {$alias}.release_date {$op} ?";
            else {
                $dateScope = $this->metadataScope('album', 'qd_a');
                $sql .= " AND EXISTS(SELECT 1 FROM reel_album_tracks qd_at JOIN reel_albums qd_a ON qd_a.id=qd_at.album_id WHERE qd_at.track_id={$alias}.id AND {$dateScope['sql']} AND qd_a.release_date {$op} ?)";
                $params = [...$params, ...$dateScope['params']];
            }
            $params[] = $date;
        }
        return ['sql' => $sql, 'params' => $params];
    }

    public function page(string $section = 'overview', array $filters = [], int $page = 1, int $perPage = 24): array
    {
        [$page, $perPage] = self::bounds($page, $perPage);
        if ($section === 'artists') return ['section' => $section, ...$this->artists($page, $perPage, $filters)];
        if ($section === 'genres') {
            $items = PlatformFeatures::enabled('discovery') ? $this->taxonomy('genre') : [];
            return ['section' => $section, 'items' => array_slice($items, ($page - 1) * $perPage, $perPage), 'pagination' => self::pagination($page, $perPage, count($items))];
        }
        if (!in_array($section, ['overview', 'albums', 'tracks', 'new', 'popular', 'collection'], true)) throw new \InvalidArgumentException('Неизвестный раздел музыки.');
        if ($section === 'collection') {
            if ($this->viewerId() === 0) throw new \InvalidArgumentException('Войдите в аккаунт, чтобы открыть свою коллекцию.');
            $kind = $filters['kind'] ?? 'albums';
            if (!in_array($kind, ['albums','tracks'], true)) throw new \InvalidArgumentException('Неизвестный раздел коллекции.');
            $type = $kind === 'albums' ? 'album' : 'track';
        } else $type = in_array($section, ['overview', 'albums'], true) ? 'album' : 'track';
        $alias = $type === 'album' ? 'a' : 't';
        $scope = $this->metadataScope($type, $alias); $filter = $this->filter($type, $alias, $filters);
        $params = [...$scope['params'], ...$filter['params']];
        $where = $scope['sql'] . $filter['sql'];
        if ($section === 'collection') {
            if ($type === 'album') $where .= ' AND EXISTS(SELECT 1 FROM reel_album_saves collection_s WHERE collection_s.album_id=a.id AND collection_s.owner_id=?)';
            else $where .= ' AND ((t.owner_id=? AND t.favorite=1) OR EXISTS(SELECT 1 FROM reel_catalog_favorites collection_f WHERE collection_f.track_id=t.id AND collection_f.owner_id=? AND collection_f.favorite=1))';
            $params = [...$params, ...array_fill(0, $type === 'album' ? 1 : 2, $this->viewerId())];
        }
        $total = (int)db()->query("SELECT COUNT(*) FROM reel_{$type}s {$alias} WHERE {$where}", $params)->getColumn();
        $sort = (string)($filters['sort'] ?? ($section === 'popular' ? 'popular' : 'new'));
        $order = $sort === 'old' ? "{$alias}.created_at ASC,{$alias}.id ASC" : "{$alias}.created_at DESC,{$alias}.id DESC";
        if ($type === 'album') {
            if ($sort === 'popular') {
                $popularScope = $this->metadataScope('track', 'qt');
                $order = "COALESCE((SELECT SUM(qs.listens) FROM reel_album_tracks qat JOIN reel_tracks qt ON qt.id=qat.track_id JOIN reel_track_stats qs ON qs.track_id=qat.track_id WHERE qat.album_id=a.id AND {$popularScope['sql']}),0) DESC,a.id DESC";
                $params = [...$params, ...$popularScope['params']];
            }
            elseif (!isset($filters['sort'])) $order = 'COALESCE(a.release_date,DATE(a.created_at)) DESC,a.id DESC';
            $rows = db()->query('SELECT a.*,' . self::distributionSelect('d', 'album') . " FROM reel_albums a LEFT JOIN reel_album_distribution d ON d.album_id=a.id WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), $params)->get() ?: [];
            $items = $this->albumDtos($rows);
        } else {
            if ($sort === 'popular') $order = 'COALESCE(s.listens,0) DESC,t.id DESC';
            $rows = db()->query('SELECT t.*,COALESCE(s.listens,0) AS listen_count,' . self::distributionSelect('d', 'track') . " FROM reel_tracks t LEFT JOIN reel_track_stats s ON s.track_id=t.id LEFT JOIN reel_track_distribution d ON d.track_id=t.id WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), $params)->get() ?: [];
            $items = $this->trackDtos($this->hydrateTracks($rows));
        }
        $result = ['section' => $section, 'items' => $items, 'pagination' => self::pagination($page, $perPage, $total)];
        if ($section === 'collection') $result['kind'] = $type === 'album' ? 'albums' : 'tracks';
        if ($section === 'overview') $result['tracks'] = $this->recommendationCandidates(6);
        return $result;
    }

    private function hydrateTracks(array $rows): array
    {
        if (!$rows) return [];
        $ids = array_map('intval', array_column($rows, 'id')); $marks = implode(',', array_fill(0, count($ids), '?'));
        $plans = db()->query("SELECT track_id,plan_id FROM reel_track_distribution_plans WHERE track_id IN ({$marks})", $ids)->get() ?: [];
        $albums = db()->query('SELECT at.track_id,a.*,' . self::distributionSelect('d', 'album') . " FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id LEFT JOIN reel_album_distribution d ON d.album_id=a.id WHERE at.track_id IN ({$marks}) AND a.published=1", $ids)->get() ?: [];
        $albumIds = array_values(array_unique(array_map('intval', array_column($albums, 'id'))));
        $albumPlans = $albumIds ? db()->query('SELECT album_id,plan_id FROM reel_album_distribution_plans WHERE album_id IN (' . implode(',', array_fill(0, count($albumIds), '?')) . ')', $albumIds)->get() ?: [] : [];
        $byAlbum = [];
        foreach ($albumPlans as $plan) $byAlbum[(int)$plan['album_id']][] = (int)$plan['plan_id'];
        $byTrack = [];
        foreach ($albums as $album) { $album['distribution'] = self::rule($album, 'album'); $album['distribution']['plan_ids'] = $byAlbum[(int)$album['id']] ?? []; $byTrack[(int)$album['track_id']][] = $album; }
        $byPlan = [];
        foreach ($plans as $plan) $byPlan[(int)$plan['track_id']][] = (int)$plan['plan_id'];
        $chapters = db()->query("SELECT id,track_id,title,artist,start_time FROM reel_chapters WHERE track_id IN ({$marks}) ORDER BY track_id,start_time,id", $ids)->get() ?: [];
        $byChapter = [];
        foreach ($chapters as $chapter) $byChapter[(int)$chapter['track_id']][] = ['id' => (int)$chapter['id'], 'track_id' => (int)$chapter['track_id'], 'title' => $chapter['title'], 'artist' => $chapter['artist'], 'start' => (float)$chapter['start_time']];
        $favorites = $this->viewerId() ? db()->query("SELECT track_id FROM reel_catalog_favorites WHERE owner_id=? AND favorite=1 AND track_id IN ({$marks})", [$this->viewerId(), ...$ids])->get() ?: [] : [];
        $favoriteIds = array_flip(array_map('intval', array_column($favorites, 'track_id')));
        foreach ($rows as &$row) {
            $id = (int)$row['id']; $row['distribution'] = self::rule($row, 'track'); $row['distribution']['plan_ids'] = $byPlan[$id] ?? [];
            $row['albums'] = $byTrack[$id] ?? []; $row['chapters'] = $byChapter[$id] ?? [];
            $row['viewer_favorite'] = ((int)$row['owner_id'] === $this->viewerId() ? (bool)($row['favorite'] ?? false) : isset($favoriteIds[$id]));
        }
        unset($row); return $rows;
    }

    private function trackDtos(array $rows, ?array $context = null): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int)$row['id']; $stream = $this->policy->decision($row, $context, 'stream');
            $download = $this->policy->decision($row, $context, 'download'); $embed = $this->policy->decision($row, $context, 'embed');
            $items[] = [
                'id' => $id, 'title' => $row['title'], 'artist' => $row['artist'], 'duration' => (float)$row['duration'], 'mime' => $row['mime'],
                'url' => $stream['allowed'] ? base_href('/music/media/' . $id) . ($context ? '?album_id=' . (int)$context['id'] : '') : '',
                'cover' => $row['cover_path'] ? base_href('/music/cover/' . $id) . '?v=' . substr(hash('sha256', $row['cover_path']), 0, 16) : '',
                'chapters' => $row['chapters'], 'favorite' => (bool)$row['viewer_favorite'], 'listen_count' => (int)($row['listen_count'] ?? 0), 'created_at' => $row['created_at'],
                'access' => ['stream' => $stream['allowed'], 'download' => $download['allowed'], 'embed' => $embed['allowed'], 'reason' => $stream['reason']],
                'download_url' => $download['allowed'] ? base_href('/music/download/' . $id) . ($context ? '?album_id=' . (int)$context['id'] : '') : '',
                'purchase_url' => (string)($row['distribution']['purchase_url'] ?? ''),
                ...(($row['source'] ?? 'local') === 'local' ? ['source' => 'local'] : []),
            ];
        }
        return PlatformFeatures::enabled('discovery') ? Taxonomy::decorate('track', $items) : $items;
    }

    private function albumDtos(array $rows): array
    {
        $items = []; $firstByAlbum = []; $firstRows = [];
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'id')); $streamScope = $this->policy->streamScope('ft');
            // One bounded ID lookup per displayed album, followed by one batch
            // hydration. The catalogue never loads an album's entire queue.
            $firstIds = db()->query("SELECT fa.id,(SELECT ft.id FROM reel_album_tracks fat JOIN reel_tracks ft ON ft.id=fat.track_id WHERE fat.album_id=fa.id AND {$streamScope['sql']} ORDER BY fat.position,fat.track_id LIMIT 1) AS first_track_id FROM reel_albums fa WHERE fa.id IN (" . implode(',',array_fill(0,count($ids),'?')) . ')', [...$streamScope['params'], ...$ids])->get() ?: [];
            foreach ($firstIds as $first) if ($first['first_track_id']) $firstByAlbum[(int)$first['id']] = (int)$first['first_track_id'];
            $trackIds = array_values(array_unique($firstByAlbum));
            if ($trackIds) {
                $first = db()->query('SELECT t.*,COALESCE(s.listens,0) AS listen_count,' . self::distributionSelect('d','track') . ' FROM reel_tracks t LEFT JOIN reel_track_stats s ON s.track_id=t.id LEFT JOIN reel_track_distribution d ON d.track_id=t.id WHERE t.id IN (' . implode(',',array_fill(0,count($trackIds),'?')) . ')', $trackIds)->get() ?: [];
                foreach ($this->hydrateTracks($first) as $track) $firstRows[(int)$track['id']] = $track;
            }
        }
        foreach ($rows as $row) {
            $id = (int)$row['id']; $scope = $this->metadataScope('track', 't');
            $count = (int)db()->query("SELECT COUNT(*) FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND {$scope['sql']}", [$id, ...$scope['params']])->getColumn();
            $coverPath = $row['cover_path'];
            if (!$coverPath) $coverPath = db()->query("SELECT t.cover_path FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND t.cover_path IS NOT NULL AND {$scope['sql']} ORDER BY at.position,at.track_id LIMIT 1", [$id, ...$scope['params']])->getColumn();
            $saved = $this->viewerId() && (bool)db()->query('SELECT album_id FROM reel_album_saves WHERE owner_id=? AND album_id=?', [$this->viewerId(), $id])->getColumn();
            $row['distribution'] = self::rule($row, 'album');
            $row['distribution']['plan_ids'] = array_map('intval', array_column(db()->query('SELECT plan_id FROM reel_album_distribution_plans WHERE album_id=?', [$id])->get() ?: [], 'plan_id'));
            $decision = $this->policy->albumDecision($row, 'stream');
            $first = $firstRows[$firstByAlbum[$id] ?? 0] ?? null;
            $firstDto = $decision['allowed'] && $first ? $this->trackDtos([$first], $row)[0] : null;
            if ($firstDto && !$firstDto['access']['stream']) $firstDto = null;
            $items[] = ['id' => $id, 'title' => $row['title'], 'artist' => $row['artist'], 'description' => $row['description'], 'release_date' => $row['release_date'], 'cover' => $coverPath ? base_href('/music/album-cover/' . $id) . '?v=' . substr(hash('sha256', $coverPath), 0, 16) : '', 'tracks' => [], 'first_track' => $firstDto, 'track_count' => $count, 'saved' => $saved, 'access' => $decision, 'created_at' => $row['created_at']];
        }
        return PlatformFeatures::enabled('discovery') ? Taxonomy::decorate('album', $items) : $items;
    }

    private function albumRow(int $id): array
    {
        $scope = $this->metadataScope('album', 'a');
        $row = db()->query('SELECT a.*,' . self::distributionSelect('d', 'album') . ' FROM reel_albums a LEFT JOIN reel_album_distribution d ON d.album_id=a.id WHERE a.id=? AND ' . $scope['sql'], [$id, ...$scope['params']])->getOne();
        if (!$row) self::missing();
        $row['distribution'] = self::rule($row, 'album');
        $row['distribution']['plan_ids'] = array_map('intval', array_column(db()->query('SELECT plan_id FROM reel_album_distribution_plans WHERE album_id=?', [$id])->get() ?: [], 'plan_id'));
        return $row;
    }

    public function album(int $id, int $page = 1, int $perPage = 48, bool $streamable = false): array
    {
        [$page, $perPage] = self::bounds($page, $perPage); $row = $this->albumRow($id); $dto = $this->albumDtos([$row])[0];
        $scope = $streamable ? $this->policy->streamScope('t') : $this->metadataScope('track', 't');
        if ($streamable && !$this->policy->albumDecision($row, 'stream')['allowed']) $scope = ['sql' => '1=0', 'params' => []];
        $total = $streamable ? (int)db()->query("SELECT COUNT(*) FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND {$scope['sql']}", [$id,...$scope['params']])->getColumn() : $dto['track_count'];
        $rows = db()->query('SELECT t.*,COALESCE(s.listens,0) AS listen_count,' . self::distributionSelect('d', 'track') . " FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id LEFT JOIN reel_track_stats s ON s.track_id=t.id LEFT JOIN reel_track_distribution d ON d.track_id=t.id WHERE at.album_id=? AND {$scope['sql']} ORDER BY at.position,at.track_id LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), [$id, ...$scope['params']])->get() ?: [];
        $dto['tracks'] = $this->trackDtos($this->hydrateTracks($rows), $row);
        $dto['tracks_pagination'] = self::pagination($page, $perPage, $total);
        $dto['pagination'] = $dto['tracks_pagination'];
        if ($streamable) $dto['streamable_track_count'] = $total;
        return $dto;
    }

    private function trackRow(int $id): array
    {
        $scope = $this->metadataScope('track', 't');
        $row = db()->query('SELECT t.*,COALESCE(s.listens,0) AS listen_count,' . self::distributionSelect('d', 'track') . ' FROM reel_tracks t LEFT JOIN reel_track_stats s ON s.track_id=t.id LEFT JOIN reel_track_distribution d ON d.track_id=t.id WHERE t.id=? AND ' . $scope['sql'], [$id, ...$scope['params']])->getOne();
        if (!$row) self::missing(); return $this->hydrateTracks([$row])[0];
    }
    public function track(int $id): array { return $this->trackDtos([$this->trackRow($id)])[0]; }
    public function mediaTrack(int $id, string $action = 'stream', int $albumId = 0): array
    {
        $row = $this->trackRow($id);
        $context = $albumId ? $this->albumRow($albumId) : null;
        if ($context && !db()->query('SELECT track_id FROM reel_album_tracks WHERE album_id=? AND track_id=?', [$albumId,$id])->getColumn()) self::missing();
        if (!$this->policy->decision($row, $context, $action)['allowed']) self::missing();
        return $row;
    }
    public function trackCover(int $id): array
    {
        $row = $this->trackRow($id);
        if (!$row['cover_path']) self::missing(); return ['path' => $row['cover_path'], 'mime' => $row['cover_mime']];
    }
    public function albumCover(int $id): array
    {
        $row = $this->albumRow($id);
        if ($row['cover_path']) return ['path' => $row['cover_path'], 'mime' => $row['cover_mime']];
        $scope = $this->metadataScope('track', 't');
        $cover = db()->query("SELECT t.cover_path,t.cover_mime FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND t.cover_path IS NOT NULL AND {$scope['sql']} ORDER BY at.position,at.track_id LIMIT 1", [$id, ...$scope['params']])->getOne();
        if (!$cover) self::missing(); return ['path' => $cover['cover_path'], 'mime' => $cover['cover_mime']];
    }

    public function recommendationCandidates(int $limit = 200, array $filters = []): array
    {
        $limit = max(1, min(200, $limit)); $scope = $this->policy->streamScope('t'); $filter = $this->filter('track', 't', $filters);
        $rows = db()->query('SELECT t.*,COALESCE(s.listens,0) AS listen_count,' . self::distributionSelect('d', 'track') . " FROM reel_tracks t LEFT JOIN reel_track_stats s ON s.track_id=t.id LEFT JOIN reel_track_distribution d ON d.track_id=t.id WHERE {$scope['sql']}{$filter['sql']} ORDER BY COALESCE(s.listens,0) DESC,t.id DESC LIMIT {$limit}", [...$scope['params'], ...$filter['params']])->get() ?: [];
        return $this->trackDtos($this->hydrateTracks($rows));
    }

    private function artistSources(): array
    {
        $track = $this->metadataScope('track', 't'); $album = $this->metadataScope('album', 'a');
        return ['sql' => "SELECT t.artist,t.owner_id,'t' AS kind,t.id FROM reel_tracks t WHERE {$track['sql']} AND TRIM(t.artist)<>'' UNION ALL SELECT a.artist,a.owner_id,'a' AS kind,a.id FROM reel_albums a WHERE {$album['sql']} AND TRIM(a.artist)<>''", 'params' => [...$track['params'], ...$album['params']]];
    }
    public function artists(int $page = 1, int $perPage = 24, array $filters = []): array
    {
        [$page, $perPage] = self::bounds($page, $perPage); $source = $this->artistSources(); $where = ''; $params = $source['params'];
        if (!empty($filters['q'])) { if (!is_string($filters['q']) || mb_strlen($filters['q']) > 240) throw new \InvalidArgumentException('Некорректный поиск.'); $where = " WHERE names.artist LIKE ? ESCAPE '!'"; $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%'; }
        $group = "SELECT LOWER(TRIM(names.artist)) AS artist_key,MIN(TRIM(names.artist)) AS name,COUNT(*) AS music_count FROM ({$source['sql']}) names{$where} GROUP BY LOWER(TRIM(names.artist))";
        $total = (int)db()->query("SELECT COUNT(*) FROM ({$group}) artist_count", $params)->getColumn();
        $rows = db()->query("{$group} ORDER BY name LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), $params)->get() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $profile = db()->query("SELECT p.id,p.image_path FROM reel_artist_profiles p WHERE LOWER(TRIM(p.name))=? AND EXISTS(SELECT 1 FROM ({$source['sql']}) source_names WHERE source_names.owner_id=p.owner_id AND LOWER(TRIM(source_names.artist))=LOWER(TRIM(p.name)))", [$row['artist_key'], ...$source['params']])->getOne();
            $items[] = ['id' => $profile ? (int)$profile['id'] : substr(hash('sha256', $row['artist_key']), 0, 16), 'name' => $row['name'], 'key' => $row['artist_key'], 'music_count' => (int)$row['music_count'], 'image' => $profile && $profile['image_path'] ? base_href('/music/artist-image/' . (int)$profile['id']) . '?v=' . substr(hash('sha256', $profile['image_path']), 0, 16) : ''];
        }
        return ['items' => $items, 'pagination' => self::pagination($page, $perPage, $total)];
    }
    public function artistCover(int $id): array
    {
        $source = $this->artistSources();
        $row = db()->query("SELECT p.image_path,p.image_mime FROM reel_artist_profiles p WHERE p.id=? AND p.image_path IS NOT NULL AND EXISTS(SELECT 1 FROM ({$source['sql']}) source_names WHERE source_names.owner_id=p.owner_id AND LOWER(TRIM(source_names.artist))=LOWER(TRIM(p.name)))", [$id, ...$source['params']])->getOne();
        if (!$row) self::missing(); return ['path' => $row['image_path'], 'mime' => $row['image_mime']];
    }
    public function taxonomy(string $kind): array
    {
        if (!PlatformFeatures::enabled('discovery')) return [];
        if (!in_array($kind, ['genre', 'tag'], true)) throw new \InvalidArgumentException('Некорректный справочник.');
        $track = $this->metadataScope('track', 't'); $album = $this->metadataScope('album', 'a');
        $sql = "SELECT tax.id,tax.name,tax.slug,tax.description,COUNT(*) AS music_count FROM reel_{$kind}s tax JOIN (SELECT link.{$kind}_id AS taxonomy_id,t.id,'track' AS kind FROM reel_track_{$kind}s link JOIN reel_tracks t ON t.id=link.track_id WHERE {$track['sql']} UNION ALL SELECT link.{$kind}_id AS taxonomy_id,a.id,'album' AS kind FROM reel_album_{$kind}s link JOIN reel_albums a ON a.id=link.album_id WHERE {$album['sql']}) visible ON visible.taxonomy_id=tax.id GROUP BY tax.id,tax.name,tax.slug,tax.description ORDER BY tax.name LIMIT 200";
        return array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'name' => $row['name'], 'slug' => $row['slug'], 'description' => $row['description'], 'count' => (int)$row['music_count']], db()->query($sql, [...$track['params'], ...$album['params']])->get() ?: []);
    }
}
