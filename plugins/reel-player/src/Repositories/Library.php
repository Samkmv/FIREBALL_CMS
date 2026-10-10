<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

use Fireball\ReelPlayer\Services\MediaStorage;

final class Library
{
    public function __construct(private int $owner) {}

    public function state(): array
    {
        $tracks = db()->query('SELECT id, title, artist, filename, duration, file_size, cover_path, cover_mime, source, favorite, created_at FROM reel_tracks WHERE owner_id = ? ORDER BY id DESC', [$this->owner])->get() ?: [];
        foreach ($tracks as &$track) {
            $track['id'] = (int)$track['id'];
            $track['duration'] = (float)$track['duration'];
            $track['favorite'] = (bool)$track['favorite'];
            $track['url'] = base_href('/admin/reel-player/media/' . $track['id']);
            $track['cover'] = $track['cover_path'] ? base_href('/admin/reel-player/cover/' . $track['id']) . '?v=' . basename($track['cover_path']) : '';
            unset($track['cover_path']);
        }
        unset($track);
        $playlists = db()->query('SELECT id, name FROM reel_playlists WHERE owner_id = ? ORDER BY id', [$this->owner])->get() ?: [];
        $memberships = db()->query('SELECT pt.playlist_id, pt.track_id FROM reel_playlist_tracks pt JOIN reel_playlists p ON p.id = pt.playlist_id WHERE p.owner_id = ? ORDER BY pt.position, pt.track_id', [$this->owner])->get() ?: [];
        foreach ($playlists as &$playlist) {
            $playlist['id'] = (int)$playlist['id'];
            $playlist['tracks'] = array_map(static fn(array $row): int => (int)$row['track_id'], array_values(array_filter($memberships, static fn(array $row): bool => (int)$row['playlist_id'] === $playlist['id'])));
        }
        return ['tracks' => $tracks, 'playlists' => $playlists];
    }

    public function track(int $id): array
    {
        $row = db()->query('SELECT * FROM reel_tracks WHERE id = ? AND owner_id = ?', [$id, $this->owner])->getOne();
        if (!$row) throw new \InvalidArgumentException('Трек не найден.');
        return $row;
    }

    public function playlist(int $id, bool $lock = false): array
    {
        $row = db()->query('SELECT * FROM reel_playlists WHERE id = ? AND owner_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id, $this->owner])->getOne();
        if (!$row) throw new \InvalidArgumentException('Плейлист не найден.');
        return $row;
    }

    private static function text(mixed $value, int $max, string $label, bool $required = true): string
    {
        if (!is_string($value)) throw new \InvalidArgumentException($label . ': недопустимое значение.');
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $max) throw new \InvalidArgumentException($label . ': от 1 до ' . $max . ' символов.');
        return $value;
    }

    private static function duration(mixed $value): float
    {
        if (!is_numeric($value)) throw new \InvalidArgumentException('Некорректная длительность.');
        $duration = (float)$value;
        if (!is_finite($duration) || $duration < 0 || $duration > 604800) throw new \InvalidArgumentException('Некорректная длительность.');
        return $duration;
    }

    public function upload(array $file, int $playlist = 0, mixed $duration = 0, bool $favorite = false): int
    {
        if ($playlist) $this->playlist($playlist);
        $duration = self::duration($duration);
        $storage = new MediaStorage();
        $saved = $storage->upload($file, $this->owner);
        try {
            $name = mb_substr(basename(str_replace('\\', '/', (string)$file['name'])), 0, 240);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $parts = explode(' - ', $base, 2);
            $artist = count($parts) === 2 ? trim($parts[0]) : '';
            $title = trim(count($parts) === 2 ? $parts[1] : $base) ?: 'Без названия';
            db()->query('INSERT INTO reel_tracks (owner_id, title, artist, filename, file_path, mime, file_size, duration, favorite, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$this->owner, mb_substr($title, 0, 240), mb_substr($artist, 0, 240), $name, $saved['path'], $saved['mime'], $saved['size'], $duration, (int)$favorite, date('Y-m-d H:i:s')]);
            $id = (int)db()->getInsertId();
        } catch (\Throwable $error) {
            $storage->delete($saved['path']);
            throw $error;
        }
        if ($playlist) $this->add($playlist, $id);
        return $id;
    }

    public function add(int $playlist, int $track): void
    {
        $this->track($track);
        db()->beginTransaction();
        try {
            $this->playlist($playlist, true);
            $position = (int)db()->query('SELECT COALESCE(MAX(position), -1) + 1 FROM reel_playlist_tracks WHERE playlist_id = ?', [$playlist])->getColumn();
            db()->query('INSERT IGNORE INTO reel_playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)', [$playlist, $track, $position]);
            db()->commit();
        } catch (\Throwable $error) {
            db()->rollBack();
            throw $error;
        }
    }

    public function importDrive(array $files, int $playlist = 0): int
    {
        if ($playlist) $this->playlist($playlist);
        foreach ($files as $file) {
            $id = (int)db()->query('SELECT id FROM reel_tracks WHERE owner_id = ? AND source = ? AND source_id = ?', [$this->owner, 'drive', $file['id']])->getColumn();
            if (!$id) {
                $parts = explode(' - ', pathinfo($file['name'], PATHINFO_FILENAME), 2);
                db()->query('INSERT INTO reel_tracks (owner_id, title, artist, filename, mime, file_size, source, source_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$this->owner, mb_substr(trim(count($parts) > 1 ? $parts[1] : $parts[0]) ?: 'Без названия', 0, 240), mb_substr(count($parts) > 1 ? trim($parts[0]) : '', 0, 240), mb_substr($file['name'], 0, 240), $file['mimeType'], (int)($file['size'] ?? 0), 'drive', $file['id'], date('Y-m-d H:i:s')]);
                $id = (int)db()->getInsertId();
            }
            if ($playlist) $this->add($playlist, $id);
        }
        return count($files);
    }

    public function action(string $action, array $data, ?array $cover = null): ?int
    {
        $id = (int)($data['id'] ?? 0);
        switch ($action) {
            case 'playlist.create':
                $name = self::text($data['name'] ?? '', 160, 'Название плейлиста');
                db()->query('INSERT INTO reel_playlists (owner_id, name, created_at) VALUES (?, ?, ?)', [$this->owner, $name, date('Y-m-d H:i:s')]);
                return (int)db()->getInsertId();
            case 'playlist.rename':
                $this->playlist($id);
                db()->query('UPDATE reel_playlists SET name = ? WHERE id = ? AND owner_id = ?', [self::text($data['name'] ?? '', 160, 'Название плейлиста'), $id, $this->owner]);
                break;
            case 'playlist.delete':
                $this->playlist($id);
                db()->query('DELETE FROM reel_playlists WHERE id = ? AND owner_id = ?', [$id, $this->owner]);
                break;
            case 'playlist.add':
                $this->add($id, (int)($data['track_id'] ?? 0));
                break;
            case 'playlist.remove':
                $this->playlist($id);
                db()->query('DELETE FROM reel_playlist_tracks WHERE playlist_id = ? AND track_id = ?', [$id, (int)($data['track_id'] ?? 0)]);
                break;
            case 'playlist.reorder':
                $ids = $data['tracks'] ?? [];
                if (!is_array($ids)) throw new \InvalidArgumentException('Некорректный порядок треков.');
                $ids = array_map('intval', $ids);
                db()->beginTransaction();
                try {
                    $this->playlist($id, true);
                    $current = array_map('intval', array_column(db()->query('SELECT track_id FROM reel_playlist_tracks WHERE playlist_id = ?', [$id])->get() ?: [], 'track_id'));
                    $sorted = $ids;
                    sort($sorted); sort($current);
                    if ($sorted !== $current) throw new \InvalidArgumentException('Состав плейлиста изменился. Обновите страницу.');
                    foreach ($ids as $position => $track) db()->query('UPDATE reel_playlist_tracks SET position = ? WHERE playlist_id = ? AND track_id = ?', [$position, $id, $track]);
                    db()->commit();
                } catch (\Throwable $error) { db()->rollBack(); throw $error; }
                break;
            case 'track.update':
                $track = $this->track($id);
                $title = self::text($data['title'] ?? $track['title'], 240, 'Название');
                $artist = self::text($data['artist'] ?? $track['artist'], 240, 'Исполнитель', false);
                $duration = self::duration($data['duration'] ?? $track['duration']);
                $storage = new MediaStorage();
                $saved = $cover && (int)$cover['error'] !== UPLOAD_ERR_NO_FILE ? $storage->upload($cover, $this->owner, true) : null;
                try {
                    db()->query('UPDATE reel_tracks SET title = ?, artist = ?, duration = ?, cover_path = ?, cover_mime = ? WHERE id = ? AND owner_id = ?',
                        [$title, $artist, $duration, $saved['path'] ?? $track['cover_path'], $saved['mime'] ?? $track['cover_mime'], $id, $this->owner]);
                } catch (\Throwable $error) { if ($saved) $storage->delete($saved['path']); throw $error; }
                if ($saved) $storage->delete($track['cover_path']);
                break;
            case 'track.duration':
                $this->track($id);
                db()->query('UPDATE reel_tracks SET duration = ? WHERE id = ? AND owner_id = ?', [self::duration($data['duration'] ?? null), $id, $this->owner]);
                break;
            case 'track.favorite':
                $this->track($id);
                $favorite = $data['favorite'] ?? null;
                if (!in_array($favorite, [0, 1, '0', '1', false, true], true)) throw new \InvalidArgumentException('Некорректное значение избранного.');
                db()->query('UPDATE reel_tracks SET favorite = ? WHERE id = ? AND owner_id = ?', [(int)$favorite, $id, $this->owner]);
                break;
            case 'track.delete':
                $track = $this->track($id);
                db()->query('DELETE FROM reel_tracks WHERE id = ? AND owner_id = ?', [$id, $this->owner]);
                $storage = new MediaStorage();
                $storage->delete($track['file_path']);
                $storage->delete($track['cover_path']);
                break;
            default: throw new \InvalidArgumentException('Неизвестное действие.');
        }
        return null;
    }
}
