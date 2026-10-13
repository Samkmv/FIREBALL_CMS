<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use Fireball\ReelPlayer\Repositories\Catalog;
use Fireball\ReelPlayer\Repositories\PublicCatalog;
use Fireball\ReelPlayer\Services\AudioQuality;
use Fireball\ReelPlayer\Services\EmbedAuthorization;
use Fireball\ReelPlayer\Services\MediaStorage;
use Fireball\ReelPlayer\Services\PlatformAssets;
use Fireball\ReelPlayer\Services\PlatformFeatures;

final class PublicController
{
    private function user(): ?array { return check_auth() ? get_user() : null; }
    private function repository(): PublicCatalog { return new PublicCatalog($this->user()); }
    private function guard(string $bucket = 'catalog', int $limit = 180): void
    {
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if (!PlatformFeatures::enabled('public_catalog') || !PlatformFeatures::schemaReady()) abort('Раздел музыки недоступен.', 404);
        // Use the existing CMS limiter and its managed runtime cache directory.
        $key = 'tape-room:' . $bucket . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!\FBL\RateLimiter::attempt($key, $limit, 60)) { header('Retry-After: 60'); abort('Слишком много запросов. Попробуйте немного позже.', 429); }
    }
    private function filters(): array
    {
        $filters = [];
        foreach (['q','genre','tag','artist','sort','from','to','kind'] as $key) { $value = request()->get($key, ''); if ($value !== '') $filters[$key] = $value; }
        return $filters;
    }
    private function config(array $extra = []): array
    {
        $user = !empty($extra['embed']) ? null : $this->user();
        $config = ['api' => base_href('/music/api'), 'media' => base_href('/music/media/'), 'base' => base_href('/music'), 'csrf' => $user ? (string)session()->get('needCSRFToken', '') : '', 'viewer' => $user !== null, 'flags' => PlatformFeatures::all(), ...$extra];
        return class_exists(PlatformAssets::class) ? PlatformAssets::publicConfig($config) : $config;
    }
    private static function title(string $section): string
    {
        return ['overview'=>'Музыка','albums'=>'Альбомы','artists'=>'Артисты','tracks'=>'Композиции','new'=>'Новые релизы','popular'=>'Популярное','genres'=>'Жанры','collection'=>'Моя коллекция'][$section] ?? 'Музыка';
    }
    private function pageView(string $view, array $data): string
    {
        if ((string)request()->get('fragment', '') === '1') {
            $data['fragment'] = true;
            response()->json(['status' => true, 'html' => plugin_view('reel-player', $view, $data, false), 'title' => $data['seo_title'] ?? $data['title'], 'description' => $data['seo_description'] ?? '', 'canonical' => $data['seo_canonical'] ?? '', 'image' => $data['seo_image'] ?? '']);
        }
        if (class_exists(PlatformAssets::class)) {
            $data['styles'] = array_values(array_filter(array_map([PlatformAssets::class, 'url'], PlatformAssets::STYLES)));
            $data['footer_scripts'] = array_values(array_filter(array_map([PlatformAssets::class, 'url'], PlatformAssets::SCRIPTS)));
        }
        return plugin_view('reel-player', $view, $data, true);
    }
    public function index(): string
    {
        $this->guard(); $section = (string)get_route_param('section', 'overview'); $filters = $this->filters();
        try { $catalog = $this->repository()->page($section, $filters, (int)request()->get('page', 1), (int)request()->get('per_page', 24)); }
        catch (\InvalidArgumentException $error) { abort($error->getMessage(), 422); }
        return $this->pageView('public/catalog', ['title' => self::title($section) . ' · Tape Room', 'section' => $section, 'filters' => $filters, 'catalog' => $catalog, 'config' => $this->config(), 'seo_title' => self::title($section) . ' · Tape Room', 'seo_description' => 'Музыкальные альбомы, артисты и композиции в Tape Room.', 'seo_canonical' => base_href('/music' . ($section === 'overview' ? '' : '/' . $section))]);
    }
    public function detail(): string
    {
        $this->guard(); $type = (string)get_route_param('type'); $id = (int)get_route_param('id');
        try { $entity = $type === 'album' ? $this->repository()->album($id, (int)request()->get('page', 1), (int)request()->get('per_page', 48)) : $this->repository()->track($id); }
        catch (\InvalidArgumentException) { abort('Музыка не найдена.', 404); }
        $title = implode(' — ', array_filter([$entity['artist'], $entity['title']]));
        $slug = function_exists('make_slug') ? make_slug($entity['title'], '') : '';
        return $this->pageView('public/detail', ['title' => $title . ' · Tape Room', 'entityType' => $type, 'entity' => $entity, 'catalog' => ['items' => $type === 'album' ? $entity['tracks'] : [$entity]], 'config' => $this->config(), 'seo_title' => $title . ' · Tape Room', 'seo_description' => mb_substr(trim(strip_tags((string)($entity['description'] ?? $title))), 0, 240), 'seo_image' => $entity['cover'], 'seo_canonical' => base_href('/music/' . $type . '/' . $id . ($slug ? '-' . $slug : ''))]);
    }
    private function json(callable $callback): never
    {
        $this->guard();
        try { response()->json(['status' => true, ...$callback()]); }
        catch (\InvalidArgumentException $error) { response()->json(['status' => false, 'message' => $error->getMessage()], 404); }
        catch (\Throwable $error) { log_error_details('Tape Room public request failed', [], $error); response()->json(['status' => false, 'message' => 'Не удалось получить музыку. Попробуйте ещё раз.'], 500); }
        exit;
    }
    public function catalog(): never { $this->json(fn(): array => $this->repository()->page((string)request()->get('section', 'tracks'), $this->filters(), (int)request()->get('page', 1), (int)request()->get('per_page', 24))); }
    public function album(): never { $this->json(fn(): array => ['album' => $this->repository()->album((int)get_route_param('id'), (int)request()->get('page', 1), (int)request()->get('per_page', 48), (string)request()->get('streamable','') === '1')]); }
    public function track(): never { $this->json(fn(): array => ['track' => $this->repository()->track((int)get_route_param('id'))]); }
    public function artists(): never { $this->json(fn(): array => $this->repository()->artists((int)request()->get('page', 1), (int)request()->get('per_page', 24), $this->filters())); }

    public function media(): never
    {
        $this->guard('media', 360);
        try { $track = $this->repository()->mediaTrack((int)get_route_param('id'), 'stream', (int)request()->get('album_id', 0)); }
        catch (\InvalidArgumentException) { abort('Музыка недоступна.', 404); }
        (new MediaStorage())->stream($track['file_path'], $track['mime']);
    }
    public function cover(): never { $this->image('track'); }
    public function albumCover(): never { $this->image('album'); }
    public function artistImage(): never { $this->image('artist'); }
    private function image(string $type): never
    {
        $this->guard('image', 360); $id = (int)get_route_param('id');
        try { $cover = match ($type) { 'album' => $this->repository()->albumCover($id), 'artist' => $this->repository()->artistCover($id), default => $this->repository()->trackCover($id) }; }
        catch (\InvalidArgumentException) { abort('Изображение недоступно.', 404); }
        // Access precedes generation and ETag/304, including revoked publication.
        (new MediaStorage())->streamCover($cover['path'], $cover['mime'], (int)request()->get('size', 0));
    }
    public function quality(): never
    {
        $this->json(function (): array {
            $row = $this->repository()->mediaTrack((int)get_route_param('id'));
            session()->close();
            return ['quality' => AudioQuality::local((new MediaStorage())->path($row['file_path']), $row['filename'], $row['mime'], (int)$row['file_size'], (float)$row['duration'])];
        });
    }
    public function prepare(): never
    {
        $this->json(function (): array { $row = $this->repository()->mediaTrack((int)request()->post('id', request()->post('track_id', 0))); return ['ready' => true, 'bytes' => min(1048576, (int)$row['file_size'])]; });
    }
    public function download(): never
    {
        $this->guard('download', 20);
        try { $row = $this->repository()->mediaTrack((int)get_route_param('id'), 'download', (int)request()->get('album_id', 0)); }
        catch (\InvalidArgumentException) { abort('Скачивание недоступно.', 404); }
        $ext = pathinfo($row['file_path'], PATHINFO_EXTENSION);
        $name = implode(' — ', array_filter([$row['artist'], $row['title']]));
        $name = trim((string)preg_replace('/[\x00-\x1f\x7f\/:\\\\"*?<>|]+/u', ' ', $name), " .\t");
        $name = mb_substr($name ?: 'Композиция', 0, 160) . '.' . $ext;
        header('Content-Disposition: attachment; filename="track-' . (int)$row['id'] . '.' . $ext . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        (new MediaStorage())->stream($row['file_path'], $row['mime']);
    }
    public function collection(): never
    {
        $this->json(function (): array {
            $user = $this->user(); if (!$user) throw new \InvalidArgumentException('Войдите в аккаунт.');
            $owner = (int)$user['id']; $data = request()->getData(); $id = (int)($data['id'] ?? 0); $action = (string)($data['action'] ?? '');
            $value = $data['value'] ?? $data['saved'] ?? $data['favorite'] ?? '1';
            if (!in_array($value, [true,false,1,0,'1','0'], true)) throw new \InvalidArgumentException('Некорректное значение.');
            $enabled = in_array($value, [true,1,'1'], true);
            if ($action === 'album.save') {
                $this->repository()->album($id);
                if ($enabled) db()->query('INSERT IGNORE INTO reel_album_saves(owner_id,album_id,created_at) VALUES(?,?,?)', [$owner,$id,date('Y-m-d H:i:s')]);
                else db()->query('DELETE FROM reel_album_saves WHERE owner_id=? AND album_id=?', [$owner,$id]);
            } elseif ($action === 'track.favorite') {
                $this->repository()->track($id);
                $owns = (bool)db()->query('SELECT id FROM reel_tracks WHERE id=? AND owner_id=?', [$id,$owner])->getColumn();
                if ($owns) db()->query('UPDATE reel_tracks SET favorite=? WHERE id=? AND owner_id=?', [(int)$enabled,$id,$owner]);
                elseif ($enabled) db()->query('INSERT INTO reel_catalog_favorites(owner_id,track_id,favorite) VALUES(?,?,1) ON DUPLICATE KEY UPDATE favorite=1', [$owner,$id]);
                else db()->query('DELETE FROM reel_catalog_favorites WHERE owner_id=? AND track_id=?', [$owner,$id]);
            } else throw new \InvalidArgumentException('Неизвестное действие коллекции.');
            return ['id' => $id, 'value' => $enabled];
        });
    }
    public function listen(): never
    {
        $this->json(function (): array {
            $user = $this->user(); if (!$user) throw new \InvalidArgumentException('Войдите в аккаунт.');
            $data = request()->getData(); $catalog = new Catalog((int)$user['id']);
            if (($data['action'] ?? '') === 'start') {
                $id = (int)($data['track_id'] ?? $data['id'] ?? 0); $this->repository()->mediaTrack($id); session()->close(); return $catalog->beginListening($id);
            }
            if (($data['action'] ?? '') !== 'finish') throw new \InvalidArgumentException('Некорректный сеанс прослушивания.');
            $token = (string)($data['token'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new \InvalidArgumentException('Некорректный сеанс прослушивания.');
            $row = db()->query('SELECT track_id FROM reel_listening_sessions WHERE token_hash=? AND owner_id=?', [hash('sha256',$token),(int)$user['id']])->getOne();
            if (!$row) throw new \InvalidArgumentException('Сеанс прослушивания недоступен.');
            $this->repository()->mediaTrack((int)$row['track_id']); session()->close(); return $catalog->finishListening($token, $data['listened'] ?? $data['seconds'] ?? null);
        });
    }
    public function embed(): string
    {
        $this->guard('embed', 90); $id = (int)get_route_param('id');
        try {
            if (!PlatformFeatures::schemaReady(true)) throw new \InvalidArgumentException('Встраивание недоступно.');
            $guest = new PublicCatalog(); $guest->mediaTrack($id, 'embed'); $track = $guest->track($id);
            $settings = EmbedAuthorization::frame($id, (string)request()->get('ticket', ''), (float)$track['duration']);
        } catch (\InvalidArgumentException) { header("Content-Security-Policy: frame-ancestors 'none'"); abort('Встраивание недоступно.', 404); }
        header_remove('X-Frame-Options');
        header('Content-Security-Policy: frame-ancestors ' . $settings['ancestors']);
        header('Referrer-Policy: no-referrer');
        $track['url'] = base_href('/music/embed/media/' . $id) . ($settings['mediaToken'] ? '?token=' . rawurlencode($settings['mediaToken']) : '');
        $theme = (string)request()->get('theme', $settings['theme']); if (!in_array($theme, ['light','dark','system'], true)) $theme = 'system';
        return plugin_view('reel-player', 'embed/track', ['track' => $track, 'theme' => $theme, 'config' => $this->config(['embed' => true]), 'title' => $track['title'] . ' · Tape Room'], false);
    }
    public function embedMedia(): never
    {
        $this->guard('media', 360); $id = (int)get_route_param('id');
        try { if (!PlatformFeatures::schemaReady(true)) throw new \InvalidArgumentException('Встраивание недоступно.'); $row = (new PublicCatalog())->mediaTrack($id, 'embed'); EmbedAuthorization::media($id, (string)request()->get('token', '')); }
        catch (\InvalidArgumentException) { abort('Музыка недоступна.', 404); }
        (new MediaStorage())->stream($row['file_path'], $row['mime']);
    }
}
