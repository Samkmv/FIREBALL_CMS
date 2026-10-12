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
    private function catalog(): Catalog { return new Catalog((int)get_user()['id'],check_creator()); }
    private function trackDrive(array $track): GoogleDrive { return new GoogleDrive((int)$track['owner_id']); }

    public function index(): string
    {
        header('Cache-Control: private, no-store');
        $assets = dirname(__DIR__, 2) . '/assets';
        $manifest = json_decode((string)file_get_contents(dirname($assets) . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $defaultCovers = [];
        foreach (['light', 'dark'] as $theme) {
            $file = 'cover-' . $theme . '.png';
            $defaultCovers[$theme] = base_href('/plugins/reel-player/assets/' . $file . '?v=' . substr(hash_file('sha256', $assets . '/' . $file), 0, 16));
        }
        $pwa = function_exists('pwa_head_data') ? pwa_head_data() : [];
        return plugin_view('reel-player', 'player', [
            'config' => [
                'version' => (string)$manifest['version'],
                'api' => base_href('/admin/reel-player/api'),
                'csrf' => (string)session()->get('needCSRFToken', ''),
                'storageKey' => 'fireball-tape-room:' . (int)get_user()['id'],
                'defaultCover' => $defaultCovers['light'],
                'defaultArtwork' => $defaultCovers['light'],
                'defaultCovers' => $defaultCovers,
                'defaultArtworkSize' => '1254x1254',
                'maxUpload' => UploadPolicy::limits()['effective'],
                'state' => $this->library()->state(),
                'drive' => $this->drive()->status(),
                'pwa' => ['enabled' => !empty($pwa['enabled']), 'worker' => $pwa['service_worker_url'] ?? ''],
            ],
            'pwa_head' => function_exists('pwa_head_tags') ? pwa_head_tags() : '',
            'css_url' => base_href('/plugins/reel-player/assets/player.css?v=' . substr(hash_file('sha256',$assets . '/player.css'),0,16)),
            'js_url' => base_href('/plugins/reel-player/assets/player.js?v=' . substr(hash_file('sha256',$assets . '/player.js'),0,16)),
            'meters_url' => base_href('/plugins/reel-player/assets/meters.js?v=' . substr(hash_file('sha256',$assets . '/meters.js'),0,16)),
            'preload_url' => base_href('/plugins/reel-player/assets/preload.js?v=' . substr(hash_file('sha256',$assets . '/preload.js'),0,16)),
            'chapters_url' => base_href('/plugins/reel-player/assets/chapters.js?v=' . substr(hash_file('sha256',$assets . '/chapters.js'),0,16)),
            'transport_url' => base_href('/plugins/reel-player/assets/transport.js?v=' . substr(hash_file('sha256',$assets . '/transport.js'),0,16)),
            'quality_url' => base_href('/plugins/reel-player/assets/quality.js?v=' . substr(hash_file('sha256',$assets . '/quality.js'),0,16)),
            'collections_url' => base_href('/plugins/reel-player/assets/collections.js?v=' . substr(hash_file('sha256',$assets . '/collections.js'),0,16)),
            'catalog_url' => base_href('/plugins/reel-player/assets/catalog.js?v=' . substr(hash_file('sha256',$assets . '/catalog.js'),0,16)),
        ], false);
    }

    public function state(): void
    {
        header('Cache-Control: private, no-store');
        response()->json(['status' => true, ...$this->library()->state()]);
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

    public function driveCallback(): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, no-store');
        try {
            $this->drive()->finish((string)request()->get('state', ''), (string)request()->get('code', ''));
            response()->redirect(base_href('/admin/reel-player') . '?drive=connected');
        } catch (\Throwable) {
            response()->redirect(base_href('/admin/reel-player') . '?drive=error');
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
        (new MediaStorage())->streamCover($cover['path'],$cover['mime']);
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
        if ($cover) $storage->streamCover($path, $track['cover_mime']);
        $storage->stream($path, $track['mime']);
    }
}
