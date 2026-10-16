<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use App\Services\UploadPolicy;
use Fireball\ReelPlayer\Repositories\Library;
use Fireball\ReelPlayer\Repositories\Catalog;
use Fireball\ReelPlayer\Services\MediaStorage;
use Fireball\ReelPlayer\Services\GoogleDrive;
use Fireball\ReelPlayer\Services\DriveCache;
use Fireball\ReelPlayer\Repositories\Chapters;
use Fireball\ReelPlayer\Services\CueParser;
use Fireball\ReelPlayer\Services\ChapterAnalysis;
use Fireball\ReelPlayer\Services\AudioQuality;

final class PlayerController
{
    private function library(): Library { return new Library((int)get_user()['id']); }
    private function drive(): GoogleDrive { return new GoogleDrive((int)get_user()['id'], null, check_creator()); }
    private function catalog(): Catalog { return new Catalog((int)(get_user()['id'] ?? 0),check_creator(),in_array(get_user()['role'] ?? '',['admin','creator'],true)); }
    private function playerState(): array
    {
        return check_auth() ? $this->library()->state() : ['tracks'=>[],'playlists'=>[],'catalog'=>$this->catalog()->state()];
    }
    private function trackDrive(array $track): GoogleDrive { return new GoogleDrive((int)$track['owner_id']); }

    /** The plugin entry in the CMS is an overview; the theme owns the player UI. */
    public function index(): string
    {
        return (new AdminCatalogController())->index();
    }

    /** Supplies account data to the standalone Tape Room theme. */
    public function pageData(): array
    {
        header('Cache-Control: private, no-store');
        $assets = theme()->getActiveTheme()['path'] . '/assets';
        $manifest = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $defaultCovers = []; $defaultCoverVariants = []; $defaultArtists = []; $defaultArtistVariants = [];
        foreach (['light', 'dark'] as $theme) {
            $file = 'images/cover-' . $theme . '.png';
            $defaultCovers[$theme] = theme_asset_versioned($file);
            foreach (MediaStorage::COVER_SIZES as $size) {
                $variant = 'images/cover-' . $theme . '-' . $size . '.webp';
                if (is_file($assets . '/' . $variant)) $defaultCoverVariants[$theme][$size] = theme_asset_versioned($variant);
            }
            // Owner-supplied microphone artwork is optional; do not substitute
            // album covers or request missing files while it is unavailable.
            $artist = 'images/artist-' . $theme . '.png';
            if (is_file($assets . '/' . $artist)) {
                $defaultArtists[$theme] = theme_asset_versioned($artist);
                foreach (MediaStorage::COVER_SIZES as $size) {
                    $variant = 'images/artist-' . $theme . '-' . $size . '.webp';
                    if (is_file($assets . '/' . $variant)) $defaultArtistVariants[$theme][$size] = theme_asset_versioned($variant);
                }
            }
        }
        $pwa = function_exists('pwa_head_data') ? pwa_head_data() : [];
        return [
            'config' => [
                'version' => (string)$manifest['version'],
                'api' => base_href('/tape-room/api'),
                'csrf' => (string)session()->get('needCSRFToken', ''),
                'storageKey' => 'fireball-tape-room:' . (check_auth() ? (int)get_user()['id'] : 'guest'),
                'authenticated' => check_auth(),
                'login' => base_href('/login'),
                'register' => base_href('/register'),
                'defaultCover' => $defaultCovers['light'],
                'defaultArtwork' => $defaultCovers['light'],
                'defaultCovers' => $defaultCovers,
                'defaultCoverVariants' => $defaultCoverVariants,
                'defaultArtists' => $defaultArtists,
                'defaultArtistVariants' => $defaultArtistVariants,
                'defaultArtworkSize' => '1254x1254',
                'maxUpload' => UploadPolicy::limits()['effective'],
                'state' => $this->playerState(),
                'drive' => check_auth() ? $this->drive()->status() : ['connected'=>false,'canManage'=>false],
                'pwa' => ['enabled' => !empty($pwa['enabled']), 'worker' => $pwa['service_worker_url'] ?? ''],
                'torrent' => ['worker' => base_url('/tape-room/torrent-worker.js'), 'scope' => base_url('/')],
            ],
            'back_url' => base_href(check_admin() ? '/admin' : '/profile'),
            'pwa_head' => theme()->partial('pwa-head'),
            'css_url' => theme_asset_versioned('css/player.css'),
            'js_url' => theme_asset_versioned('js/player.js'),
            'meters_url' => theme_asset_versioned('js/meters.js'),
            'preload_url' => theme_asset_versioned('js/preload.js'),
            'chapters_url' => theme_asset_versioned('js/chapters.js'),
            'transport_url' => theme_asset_versioned('js/transport.js'),
            'quality_url' => theme_asset_versioned('js/quality.js'),
            'collections_url' => theme_asset_versioned('js/collections.js'),
            'catalog_url' => theme_asset_versioned('js/catalog.js'),
            'genres_url' => theme_asset_versioned('js/genres.js'),
            'images_url' => theme_asset_versioned('js/images.js'),
        ];
    }

    public function state(): void
    {
        header('Cache-Control: private, no-store');
        response()->json(['status' => true, ...$this->playerState()]);
    }

    public function action(): void
    {
        $this->respond(function (): array {
            $data = request()->getData();
            $action = (string)($data['action'] ?? '');
            if (str_starts_with($action, 'catalog.')) {
                $id = $this->catalog()->action(substr($action,8),$data,$_FILES['cover'] ?? null);
                return ['id'=>$id,...$this->library()->state()];
            }
            if (str_starts_with($action, 'drive.')) {
                $drive = $this->drive();
                switch ($action) {
                    case 'drive.settings':
                        if (!check_creator()) response()->json(['status' => false, 'message' => 'Настройки доступны только создателю сайта.'], 403);
                        $drive->settings((string)($data['client_id'] ?? ''), (string)($data['client_secret'] ?? ''));
                        return ['drive' => $drive->status()];
                    case 'drive.connect': return ['authUrl' => $drive->authorizationUrl()];
                    case 'drive.disconnect': $drive->disconnect(); return ['drive' => $drive->status()];
                    case 'drive.import':
                        $playlist = (int)($data['playlist_id'] ?? 0);
                        if ($playlist) $this->library()->playlist($playlist);
                        $ids = $data['ids'] ?? [];
                        if (!is_array($ids)) throw new \InvalidArgumentException('Выберите файлы Google Drive.');
                        $files = $drive->importFiles($ids, (string)($data['link'] ?? ''));
                        $count = $this->library()->importDrive($files, $playlist);
                        $sourceIds = array_column($files,'id');
                        $imported = db()->query('SELECT id,source_id FROM reel_tracks WHERE owner_id=? AND source=? AND source_id IN ('.implode(',',array_fill(0,count($sourceIds),'?')).')',[(int)get_user()['id'],'drive',...$sourceIds])->get() ?: [];
                        $bySource = array_column($imported,'id','source_id');
                        return ['imported' => $count,'imported_ids'=>array_values(array_filter(array_map(static fn(string $source): int => (int)($bySource[$source] ?? 0),$sourceIds))), ...$this->library()->state()];
                    default: throw new \InvalidArgumentException('Неизвестное действие Google Drive.');
                }
            }
            $id = $this->library()->action($action, $data, $_FILES['cover'] ?? null);
            return ['id' => $id, ...$this->library()->state()];
        });
    }

    public function upload(): void
    {
        $this->respond(function (): array {
            $id = $this->library()->upload($_FILES['audio'] ?? [], (int)request()->post('playlist_id', 0), request()->post('duration', 0), request()->post('favorite', '0') === '1');
            return ['id' => $id, ...$this->library()->state()];
        });
    }

    public function cuePreview(): void
    {
        $this->respond(function (): array {
            $id = (int)request()->post('track_id',0);
            $track = $this->library()->track($id);
            $parsed = CueParser::upload($_FILES['cue'] ?? [],$track['filename'],(float)$track['duration']);
            $preview = (new Chapters((int)get_user()['id']))->preview($id,$parsed['entries']);
            return [...$parsed,...$preview];
        });
    }

    public function chapterAnalysis(): void
    {
        $this->respond(function (): array {
            $id = (int)request()->post('track_id',0);
            $track = $this->library()->track($id);
            $chapters = new Chapters((int)get_user()['id']);
            $revision = $chapters->revision($id);
            // Ownership and CSRF have already been checked by the CMS. Analysis
            // releases the session so playback and other tabs remain responsive.
            $drive = $this->drive(); session()->close();
            if (function_exists('set_time_limit')) @set_time_limit(65);
            try {
                $analysis = ($track['source'] ?? 'local') === 'drive'
                    ? $drive->analyzeChapters($track['source_id'],(float)$track['duration'])
                    : (new ChapterAnalysis())->analyze((new MediaStorage())->path($track['file_path']),(float)$track['duration']);
            } catch (\RuntimeException $error) { throw new \InvalidArgumentException($error->getMessage(),0,$error); }
            return [...$analysis,'entries'=>$analysis['chapters'],'revision'=>$revision];
        });
    }

    private function respond(callable $callback): never
    {
        try {
            $result = $callback();
            response()->json(['status' => true, ...$result]);
        } catch (\InvalidArgumentException | \App\Services\UploadException $error) {
            response()->json(['status' => false, 'message' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            log_error_details('Tape Room request failed', [], $error);
            response()->json(['status' => false, 'message' => 'Не удалось сохранить изменения. Попробуйте ещё раз.'], 500);
        }
        exit;
    }

    public function media(): void { $this->stream(false); }
    public function cover(): void { $this->stream(true); }

    public function reviewMedia(): void
    {
        try { $album=(new \Fireball\ReelPlayer\Repositories\AdminCatalog())->album((int)get_route_param('album')); }
        catch (\InvalidArgumentException $error) { abort($error->getMessage(),404); }
        $id=(int)get_route_param('id');
        if (!in_array($id,array_map('intval',array_column($album['tracks'],'id')),true)) abort('Трек не входит в этот альбом.',404);
        $track=db()->query('SELECT * FROM reel_tracks WHERE id=?',[$id])->getOne();
        if (!$track) abort('Трек не найден.',404);
        $this->deliverTrack($track,false);
    }

    public function reviewCover(): void
    {
        try { $album=(new \Fireball\ReelPlayer\Repositories\AdminCatalog())->album((int)get_route_param('id')); }
        catch (\InvalidArgumentException $error) { abort($error->getMessage(),404); }
        $cover=$album['cover_path'] ? $album : db()->query('SELECT t.cover_path,t.cover_mime FROM reel_album_tracks at JOIN reel_tracks t ON t.id=at.track_id WHERE at.album_id=? AND t.cover_path IS NOT NULL ORDER BY at.position,at.track_id LIMIT 1',[(int)$album['id']])->getOne();
        if (!$cover || !$cover['cover_path']) abort('Обложка не найдена.',404);
        session()->close();
        (new MediaStorage())->streamCover($cover['cover_path'],$cover['cover_mime'],320);
    }

    public function quality(): void
    {
        header('Cache-Control: private, no-store');
        try { $track = $this->library()->track((int)get_route_param('id')); }
        catch (\InvalidArgumentException) { abort('Трек не найден.', 404); }
        $this->trackQuality($track);
    }

    public function catalogQuality(): void
    {
        header('Cache-Control: private, no-store');
        try { $track = $this->catalog()->accessibleTrack((int)get_route_param('id')); }
        catch (\InvalidArgumentException) { abort('Трек не найден.',404); }
        $this->trackQuality($track);
    }

    private function trackQuality(array $track): void
    {
        // Optional metadata must not hold the session lock or delay native audio.
        $drive = $this->trackDrive($track); session()->close();
        $fallback = AudioQuality::describe('', (string)$track['filename'], (string)$track['mime'], (int)$track['file_size'], (float)$track['duration']);
        try {
            $info = ($track['source'] ?? 'local') === 'drive'
                ? $drive->quality((string)$track['source_id'], (float)$track['duration'])
                : AudioQuality::local((new MediaStorage())->path((string)$track['file_path']), (string)$track['filename'], (string)$track['mime'], (int)$track['file_size'], (float)$track['duration']);
        } catch (\Throwable) {
            // Format/known average remains useful when an optional header is unavailable.
            $info = $fallback;
        }
        response()->json(['status' => true, 'quality' => $info]);
    }

    public function prepare(): void
    {
        // Authentication/CSRF are checked before releasing the session lock.
        try { $track = $this->library()->track((int)request()->post('id',0)); }
        catch (\InvalidArgumentException) { abort('Трек не найден.',404); }
        $this->prepareTrack($track);
    }

    public function catalogPrepare(): void
    {
        try { $track = $this->catalog()->accessibleTrack((int)request()->post('id',0)); }
        catch (\InvalidArgumentException) { abort('Трек не найден.',404); }
        $this->prepareTrack($track);
    }

    private function prepareTrack(array $track): void
    {
        $drive = $this->trackDrive($track);
        session()->close();
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: private, no-store'); header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) ob_end_clean();
        echo ' '; flush();
        try {
            $result = ($track['source'] ?? 'local') === 'drive' ? $drive->prepare($track['source_id']) : ['ready'=>true,'bytes'=>min(DriveCache::PREFIX_BYTES,(int)$track['file_size'])];
            echo json_encode(['status'=>true,...$result],JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) { echo json_encode(['status'=>false,'ready'=>false,'message'=>$error->getMessage()],JSON_THROW_ON_ERROR); }
        exit;
    }

    public function driveStatus(): void
    {
        header('Cache-Control: private, no-store');
        response()->json(['status' => true, 'drive' => $this->drive()->status()]);
    }

    public function driveFiles(): void
    {
        $this->respond(function (): array {
            $page = (string)request()->get('page', '');
            $folder = (string)request()->get('folder', '');
            $drive = $this->drive();
            session()->close();
            return $folder !== '' ? $drive->browse($page, $folder) : $drive->files($page);
        });
    }

    public function driveMetadata(): void
    {
        $this->respond(function (): array {
            $id=(int)request()->post('id',0); $library=$this->library();$track=$library->track($id);
            if($track['source']!=='drive')throw new \InvalidArgumentException('Выберите песню Google Drive.');
            $artwork=request()->post('artwork','0')==='1';
            if($track['metadata_loaded'] && (!$artwork || $track['cover_path'])) return ['track'=>$library->metadataTrack($id)];
            $drive=$this->drive(); session()->close();
            return ['track'=>$library->applyMetadata($id,$drive->metadata($track['source_id'],$artwork))];
        });
    }

    public function driveCallback(): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, no-store');
        try {
            $this->drive()->finish((string)request()->get('state', ''), (string)request()->get('code', ''));
            response()->redirect(base_href('/') . '?drive=connected');
        } catch (\Throwable) {
            response()->redirect(base_href('/') . '?drive=error');
        }
    }

    private function stream(bool $cover): never
    {
        try { $track = $this->library()->track((int)get_route_param('id')); }
        catch (\InvalidArgumentException) { abort('Трек не найден.', 404); }
        $this->deliverTrack($track,$cover);
    }

    public function catalogMedia(): never { $this->streamCatalog(false); }
    public function catalogCover(): never { $this->streamCatalog(true); }

    private function streamCatalog(bool $cover): never
    {
        try { $track = $this->catalog()->accessibleTrack((int)get_route_param('id')); }
        catch (\InvalidArgumentException) { abort('Трек не найден.',404); }
        $this->deliverTrack($track,$cover);
    }

    public function albumCover(): never
    {
        try { $cover = $this->catalog()->albumCover((int)get_route_param('id')); }
        catch (\InvalidArgumentException) { abort('Обложка не найдена.',404); }
        (new MediaStorage())->streamCover($cover['path'],$cover['mime'],(int)request()->get('size',0));
    }

    public function artistImage(): never
    {
        try { $cover = $this->catalog()->artistCover((int)get_route_param('id')); }
        catch (\InvalidArgumentException) { abort('Изображение артиста не найдено.',404); }
        (new MediaStorage())->streamCover($cover['path'],$cover['mime'],(int)request()->get('size',0));
    }

    public function catalogListen(): void
    {
        header('Cache-Control: private, no-store');
        $this->respond(function(): array {
            $catalog = $this->catalog();
            $data = request()->getData();
            session()->close();
            return match ((string)($data['action'] ?? '')) {
                'start' => $catalog->beginListening((int)($data['id'] ?? 0)),
                'finish' => $catalog->finishListening((string)($data['token'] ?? ''),$data['seconds'] ?? null),
                default => throw new \InvalidArgumentException('Неизвестное действие статистики.'),
            };
        });
    }

    private function deliverTrack(array $track,bool $cover): never
    {
        if (!$cover && ($track['source'] ?? 'local') === 'drive') {
            try { $this->trackDrive($track)->stream($track['source_id'], (string)request()->get('meter','') === '1', (float)($track['duration'] ?? 0)); }
            catch (\InvalidArgumentException $error) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: private, no-store'); exit($error->getMessage()); }
        }
        $path = $cover ? $track['cover_path'] : $track['file_path'];
        if (!$path) abort('Обложка не найдена.', 404);
        $storage = new MediaStorage();
        if ($cover) $storage->streamCover($path, $track['cover_mime'],(int)request()->get('size',0));
        $storage->stream($path, $track['mime']);
    }
}
