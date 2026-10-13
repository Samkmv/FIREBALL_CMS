<?php $reelHeading = in_array($reelHeading ?? '', ['h1','h2'], true) ? $reelHeading : 'h1'; ?>
    <section class="rp-machine" data-tape-room-reel aria-label="Катушечный аудиоплеер">
        <div class="rp-machine-caption"><span class="rp-status-dot"></span><span data-play-status>ГОТОВ К ПРОСЛУШИВАНИЮ</span><span class="rp-model" data-audio-quality>АУДИО</span></div>
        <div class="rp-deck">
            <svg class="rp-deck-svg" viewBox="0 0 1200 460" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <linearGradient id="rp-metal" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#a8a5a0"/><stop offset=".18" stop-color="#f5f3ee"/><stop offset=".34" stop-color="#c2beb7"/><stop offset=".51" stop-color="#faf8f3"/><stop offset=".72" stop-color="#b8b4ad"/><stop offset=".89" stop-color="#eeeae3"/><stop offset="1" stop-color="#a8a39b"/></linearGradient>
                    <linearGradient id="rp-clear-face" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#e7e3da" stop-opacity=".10"/><stop offset=".18" stop-color="#e7e3da" stop-opacity=".16"/><stop offset=".4" stop-color="#dedbd1" stop-opacity=".11"/><stop offset=".7" stop-color="#e2ded4" stop-opacity=".12"/><stop offset="1" stop-color="#e7e3da" stop-opacity=".14"/></linearGradient>
                    <linearGradient id="rp-clear-edge" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#fffef9" stop-opacity=".85"/><stop offset=".35" stop-color="#dedad0" stop-opacity=".35"/><stop offset=".7" stop-color="#88877e" stop-opacity=".48"/><stop offset="1" stop-color="#fffef9" stop-opacity=".75"/></linearGradient>
                    <linearGradient id="rp-hub" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#3f4140"/><stop offset="1" stop-color="#161918"/></linearGradient>
                    <radialGradient id="rp-tape"><stop stop-color="#211810"/><stop offset=".46" stop-color="#302419"/><stop offset=".75" stop-color="#3b2e21"/><stop offset="1" stop-color="#1f1812"/></radialGradient>
                    <radialGradient id="rp-glass"><stop offset=".65" stop-color="#e7e3da" stop-opacity=".04"/><stop offset=".9" stop-color="#d2cdc3" stop-opacity=".12"/><stop offset="1" stop-color="#e5e0d7" stop-opacity=".22"/></radialGradient>
                    <filter id="rp-shadow" x="-30%" y="-20%" width="170%" height="165%"><feDropShadow dx="6" dy="17" stdDeviation="12" flood-color="#6a5b42" flood-opacity=".21"/></filter>
                    <linearGradient id="rp-tape-feed-left" data-tape-fade="left" gradientUnits="userSpaceOnUse" x1="163.88" y1="372.85" x2="180.63" y2="379.43"><stop class="rp-tape-ink" stop-opacity="0"/><stop class="rp-tape-ink" offset=".55" stop-opacity=".75"/><stop class="rp-tape-ink" offset="1" stop-opacity="1"/></linearGradient>
                    <linearGradient id="rp-tape-feed-right" data-tape-fade="right" gradientUnits="userSpaceOnUse" x1="1027.24" y1="265.57" x2="1014.38" y2="278.16"><stop class="rp-tape-ink" stop-opacity="0"/><stop class="rp-tape-ink" offset=".55" stop-opacity=".75"/><stop class="rp-tape-ink" offset="1" stop-opacity="1"/></linearGradient>
                    <mask id="rp-tape-visible" maskUnits="userSpaceOnUse" x="0" y="0" width="1200" height="460">
                        <rect width="1200" height="460" fill="white"/>
                        <circle cx="230" cy="210" r="174" fill="black" data-tape-mask="left"/>
                        <circle cx="970" cy="210" r="78" fill="black" data-tape-mask="right"/>
                    </mask>
                </defs>
                <g mask="url(#rp-tape-visible)">
                    <path data-tape-left d="M160.49 365.07 C164.21 366.53 162.02 372.12 165.74 373.58 C199.30 386.77 232.87 405.95 266.43 419.14 C300.00 432.32 333.56 439.51 367.12 452.69 A29.75 29.75 0 0 0 407.75 425 L378 425" class="rp-tape-line" style="--rp-tape-stroke:url(#rp-tape-feed-left)"/>
                    <path data-tape-right d="M1027.33 257.08 C1024.47 259.88 1028.67 264.17 1025.81 266.97 C995.31 296.85 964.81 332.73 934.32 362.61 C903.82 392.49 873.32 416.37 842.82 446.25 A29.75 29.75 0 0 1 792.25 425 L822 425" class="rp-tape-line" style="--rp-tape-stroke:url(#rp-tape-feed-right)"/>
                </g>
                <?php foreach (['left' => 20, 'right' => 760] as $side => $x): ?>
                <svg x="<?= $x ?>" y="0" width="420" height="420" viewBox="0 0 420 420" class="rp-reel" data-reel="<?= $side ?>">
                    <defs>
                        <clipPath id="rp-pack-<?= $side ?>"><circle cx="210" cy="210" r="<?= $side === 'left' ? 174 : 78 ?>" data-pack-clip="<?= $side ?>"/></clipPath>
                        <mask id="rp-pack-annulus-<?= $side ?>" maskUnits="userSpaceOnUse" x="0" y="0" width="420" height="420"><circle cx="210" cy="210" r="180" fill="white"/><circle cx="210" cy="210" r="78" fill="black"/></mask>
                    </defs>
                    <circle cx="210" cy="210" r="197" fill="url(#rp-glass)" stroke="#b4afa6" stroke-width="1.5" filter="url(#rp-shadow)"/>
                    <circle cx="210" cy="210" r="78" fill="url(#rp-metal)" stroke="#817c70" stroke-width="1"/>
                    <g mask="url(#rp-pack-annulus-<?= $side ?>)">
                        <circle cx="210" cy="210" r="<?= $side === 'left' ? 174 : 78 ?>" fill="url(#rp-tape)" data-pack="<?= $side ?>"/>
                        <g clip-path="url(#rp-pack-<?= $side ?>)" opacity=".5"><?php for ($n = 0; $n < 88; $n++): ?><circle cx="210" cy="210" r="<?= 79 + $n * 1.08 ?>" fill="none" stroke="<?= $n % 3 ? '#6a5036' : '#15120e' ?>" stroke-width=".35"/><?php endfor; ?></g>
                    </g>
                </svg>
                <?php endforeach; ?>
                <?php foreach ([378, 822] as $x): ?><g class="rp-roller"><circle cx="<?= $x ?>" cy="425" r="28" fill="#252623" stroke="#9b978d" stroke-width="2"/><circle cx="<?= $x ?>" cy="425" r="22" fill="url(#rp-metal)" stroke="#d7d1c7" stroke-width="2"/><circle cx="<?= $x ?>" cy="425" r="3" fill="#8e897e"/></g><?php endforeach; ?>
            </svg>
            <?php foreach (['left','right'] as $side): ?>
                <!-- Clear reinforced arms rotate above the real tape annulus.
                     Face reflections and the single acrylic rim stay in the light. -->
                <div class="rp-rotor-mount rp-rotor-mount--<?= $side ?>" aria-hidden="true"><div class="rp-rotor" data-rotor="<?= $side ?>">
                    <svg viewBox="0 0 420 420" xmlns="http://www.w3.org/2000/svg">
                        <?php foreach ([22.5, 142.5, 262.5] as $angle): ?>
                        <path class="rp-reel-spoke" transform="rotate(<?= $angle ?> 210 210)" d="M187.79 143.62 L149.71 29.81 A190 190 0 0 1 270.29 29.81 L232.21 143.62 A70 70 0 0 0 187.79 143.62 Z" fill="#e8e3d7" fill-opacity=".09"/>
                        <?php endforeach; ?>
                        <circle cx="210" cy="210" r="74" fill="url(#rp-metal)" stroke="#77756e" stroke-width="1.5"/>
                        <circle cx="210" cy="210" r="65" fill="url(#rp-hub)" stroke="#0e100e" stroke-width="2"/>
                        <circle cx="210" cy="210" r="68" fill="none" stroke="#f3f0e9" stroke-width="1.2"/>
                        <?php foreach ([0, 120, 240] as $angle): ?><g transform="rotate(<?= $angle ?> 210 210)"><rect x="204" y="134" width="12" height="6" rx="2" fill="#8c8880" stroke="#282825" stroke-width="1"/><circle cx="210" cy="281" r="1.8" fill="#161916"/></g><?php endforeach; ?>
                    </svg>
                </div>
                    <svg class="rp-reel-rim" viewBox="0 0 420 420" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="210" cy="210" r="193" fill="url(#rp-clear-face)" stroke="url(#rp-clear-edge)" stroke-width="5"/>
                        <circle cx="210" cy="210" r="188" fill="none" stroke="#e6e3d8" stroke-opacity=".28" stroke-width=".8"/>
                        <circle cx="210" cy="210" r="195" fill="none" stroke="#fffefa" stroke-opacity=".65" stroke-width="1.2"/>
                    </svg>
                </div>
            <?php endforeach; ?>
            <div class="rp-now-playing">
                <button type="button" class="rp-cover-button" <?= !empty($readOnlyReel) ? 'disabled' : 'data-action="edit-current"' ?> title="<?= !empty($readOnlyReel) ? 'Обложка текущей композиции' : 'Изменить название и обложку' ?>" aria-label="<?= !empty($readOnlyReel) ? 'Обложка текущей композиции' : 'Изменить название и обложку' ?>">
                    <img class="rp-custom-cover" data-cover alt="Обложка текущей композиции" width="280" height="280">
                    <?php foreach (['light','dark'] as $coverTheme): $variants = $config['defaultCoverVariants'][$coverTheme] ?? []; ?>
                    <img class="rp-theme-cover rp-theme-cover--<?= $coverTheme ?>" data-theme-cover src="<?= htmlSC($variants[320] ?? $config['defaultCovers'][$coverTheme]) ?>" <?php if (!empty($variants[320]) && !empty($variants[640])): ?>srcset="<?= htmlSC($variants[320]) ?> 1x, <?= htmlSC($variants[640]) ?> 2x"<?php endif; ?> alt="Стандартная обложка" width="280" height="280" decoding="async" fetchpriority="<?= $coverTheme === 'light' ? 'high' : 'auto' ?>">
                    <?php endforeach; ?>
                    <span class="rp-cover-edit"><?= $icon('edit') ?></span>
                </button>
                <<?= $reelHeading ?> data-title>Ваша первая лента</<?= $reelHeading ?>>
                <p data-artist>ДОБАВЬТЕ ЛЮБИМУЮ МУЗЫКУ</p>
            </div>
        </div>
        <div class="rp-transport">
            <?php foreach (['left' => 'L', 'right' => 'R'] as $side => $label): ?>
            <div class="rp-meter rp-meter--<?= $side ?>" aria-label="Уровень <?= $label === 'L' ? 'левого' : 'правого' ?> канала">
                <span class="rp-channel"><?= $label ?></span>
                <div class="rp-meter-body"><div class="rp-meter-bars" data-meter="<?= $side ?>"><?php for ($i=0; $i<15; $i++): ?><i style="--bar-height:<?= min(100, 44 + $i * 6) ?>%"></i><?php endfor; ?></div><div class="rp-meter-labels"><span>−20</span><span>−10</span><span>−5</span><span>0</span><span>+3</span></div></div>
            </div>
            <?php endforeach; ?>
            <div class="rp-main-controls">
                <div class="rp-timeline">
                    <label class="rp-seek-wrap"><span class="rp-sr-only">Позиция воспроизведения</span><input class="rp-range rp-seek" data-seek type="range" min="0" max="1000" value="0" disabled></label>
                    <div class="rp-chapter-markers" data-chapter-markers data-action="chapter-marker-seek" role="group" aria-label="Таймкоды треков в аудиофайле" hidden></div>
                </div>
                <div class="rp-times"><span data-elapsed>0:00</span><span data-duration>0:00</span></div>
                <p class="rp-chapter-current" data-chapter-current aria-live="polite" aria-atomic="true" hidden></p>
                <div class="rp-play-controls"><?= $button('prev', 'Предыдущий трек', 'prev') ?><?= $button('play', 'Воспроизвести', 'play', 'rp-play') ?><?= $button('next', 'Следующий трек', 'next') ?></div>
            </div>
        </div>
        <div class="rp-secondary-controls">
            <div class="rp-playback-options"><?= $button('shuffle', 'Случайный порядок: выключен', 'shuffle') ?><span class="rp-repeat-wrap"><?= $button('repeat', 'Повтор: выключен', 'repeat') ?><span data-repeat-one hidden>1</span></span></div>
            <span class="rp-source" data-source>ВАША МУЗЫКА. ВАШ РИТМ.</span>
            <div class="rp-volume"><?= $button('mute', 'Выключить звук', 'volume') ?><label><span class="rp-sr-only">Громкость</span><input type="range" class="rp-range" data-volume min="0" max="1" step="0.01" value="0.8"></label></div>
        </div>
        <div class="rp-meter-recovery" data-meter-recovery hidden><button type="button" class="rp-button" data-action="meters">Включить индикаторы</button></div>
    </section>
