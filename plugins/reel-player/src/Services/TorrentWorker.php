<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** The HTTP-looking music URLs are answered by the browser, never by PHP. */
final class TorrentWorker
{
    public static function script(): never
    {
        session()->close();
        header_remove('Set-Cookie');
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        // WebTorrent handles its own requests first; the ordinary CMS worker
        // continues handling offline pages, notifications and static resources.
        readfile(ROOT . '/themes/tape-room/assets/js/torrent-worker.js');
        echo "\n";
        $pwa = new \App\Services\PwaService();
        if ($pwa->isEnabled()) echo $pwa->serviceWorkerScript();
        exit;
    }
}
