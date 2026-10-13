<?php
declare(strict_types=1);
use Fireball\ReelPlayer\Services\PlatformAssets;
$config = [...$config,'tracks'=>[$track],'state'=>['tracks'=>[$track]],'theme'=>$theme];
?>
<!doctype html>
<html lang="ru" data-theme="<?= htmlSC($theme==='system'?'light':$theme) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title><?= htmlSC($title) ?></title><link rel="stylesheet" href="<?= htmlSC(PlatformAssets::url('public.css')) ?>"></head>
<body class="tr-embed">
    <?php require dirname(__DIR__) . '/components/symbols.php'; ?>
    <section data-tape-room-embed aria-label="Tape Room"><audio data-audio preload="none" playsinline></audio><script type="application/json" data-embed-config><?= json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) ?></script></section>
    <a class="tr-embed-link" href="<?= htmlSC(base_href('/music/track/'.(int)$track['id'])) ?>" target="_blank" rel="noopener">Слушать в Tape Room</a>
    <?php foreach (['chapters.js','images.js','playback-core.js','compact.js','embed.js'] as $file): ?><script src="<?= htmlSC(PlatformAssets::url($file)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
