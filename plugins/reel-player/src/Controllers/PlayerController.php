<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use App\Services\UploadPolicy;
use Fireball\ReelPlayer\Repositories\Library;
use Fireball\ReelPlayer\Services\MediaStorage;
use Fireball\ReelPlayer\Services\GoogleDrive;
use Fireball\ReelPlayer\Services\DriveCache;

final class PlayerController
{
    private function library(): Library { return new Library((int)get_user()['id']); }
    private function drive(): GoogleDrive { return new GoogleDrive((int)get_user()['id'], null, check_creator()); }

    public function index(): string
    {
        header('Cache-Control: private, no-store');
        $assets = dirname(__DIR__, 2) . '/assets';
        $manifest = json_decode((string)file_get_contents(dirname($assets) . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $pwa = function_exists('pwa_head_data') ? pwa_head_data() : [];
        return plugin_view('reel-player', 'player', [
            'config' => [
                'version' => (string)$manifest['version'],
                'api' => base_href('/admin/reel-player/api'),
                'csrf' => (string)session()->get('needCSRFToken', ''),
                'storageKey' => 'fireball-tape-room:' . (int)get_user()['id'],
                'defaultCover' => base_href('/plugins/reel-player/assets/cover.svg'),
                'defaultArtwork' => base_href('/plugins/reel-player/assets/cover.png'),
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
                        return ['imported' => $count, ...$this->library()->state()];
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

    public function prepare(): void
    {
        // Authentication/CSRF are checked before releasing the session lock.
        try { $track = $this->library()->track((int)request()->post('id',0)); }
        catch (\InvalidArgumentException) { abort('Трек не найден.',404); }
        session()->close();
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: private, no-store'); header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) ob_end_clean();
        echo ' '; flush();
        try {
            $result = ($track['source'] ?? 'local') === 'drive' ? $this->drive()->prepare($track['source_id']) : ['ready'=>true,'bytes'=>min(DriveCache::PREFIX_BYTES,(int)$track['file_size'])];
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
        if (!$cover && ($track['source'] ?? 'local') === 'drive') {
            try { $this->drive()->stream($track['source_id'], (string)request()->get('meter','') === '1', (float)($track['duration'] ?? 0)); }
            catch (\InvalidArgumentException $error) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: private, no-store'); exit($error->getMessage()); }
        }
        $path = $cover ? $track['cover_path'] : $track['file_path'];
        if (!$path) abort('Обложка не найдена.', 404);
        (new MediaStorage())->stream($path, $cover ? $track['cover_mime'] : $track['mime']);
    }
}
