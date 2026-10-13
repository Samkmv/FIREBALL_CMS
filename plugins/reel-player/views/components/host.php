<?php
declare(strict_types=1);
require __DIR__ . '/icons.php';
$readOnlyReel = true;
$reelHeading = 'h2';
?>
<section class="tr-playback" data-tape-room-host data-theme="light" aria-label="Tape Room">
    <?php require __DIR__ . '/symbols.php'; ?>
    <div class="rp-app">
        <header class="tr-playback-heading">
            <a href="<?= htmlSC(base_href('/music')) ?>" data-music-link>TAPE ROOM</a>
            <div class="rp-header-actions">
                <?= $button('appearance', 'Внешний вид плеера', 'settings') ?>
                <?= $button('theme', 'Изменить тему плеера', 'moon') ?>
            </div>
        </header>
        <?php require __DIR__ . '/reel.php'; ?>
        <?php require __DIR__ . '/chapters.php'; ?>
        <p class="tr-message tr-error" role="alert" data-playback-error hidden></p>
    </div>
    <audio data-audio data-player-native preload="metadata" playsinline></audio>
    <script type="application/json" data-player-config><?= json_encode($config, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) ?></script>
</section>
