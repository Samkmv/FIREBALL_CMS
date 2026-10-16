<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use Fireball\ReelPlayer\Repositories\PublicShare;

final class ShareController
{
    public function show(): string
    {
        header('Cache-Control: no-store');
        try { $item=(new PublicShare())->get((string)get_route_param('kind'),(int)get_route_param('id')); }
        catch (\InvalidArgumentException $error) { abort($error->getMessage(),404); }
        $embedded=(string)get_route_param('surface')==='embed';
        if ($embedded) {
            // Only this read-only public surface may be framed by other sites.
            header_remove('X-Frame-Options');
            header("Content-Security-Policy: frame-ancestors *; object-src 'none'; base-uri 'none'");
        }
        $appearance=(string)request()->get('theme','auto');
        if (!in_array($appearance,['light','dark','auto'],true)) $appearance='auto';
        $asset=static fn(string $file): string=>base_url('/themes/tape-room/assets/'.$file).'?v='.substr(hash_file('sha256',ROOT.'/themes/tape-room/assets/'.$file),0,16);
        return plugin_view('reel-player','embed',['item'=>$item,'embedded'=>$embedded,'appearance'=>$appearance,'asset'=>$asset],false);
    }
}
