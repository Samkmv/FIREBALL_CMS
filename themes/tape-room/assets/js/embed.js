(() => {
    'use strict';
    const root = document.querySelector('[data-embed-player]');
    if (!root) return;
    const config = JSON.parse(root.querySelector('[data-embed-config]').textContent);
    const audio = root.querySelector('audio');
    const find = name => root.querySelector(`[data-embed-${name}]`);
    const media = matchMedia('(prefers-color-scheme:dark)');
    let current = 0;
    const theme = () => {
        const value = root.dataset.appearance === 'auto' ? (media.matches ? 'dark' : 'light') : root.dataset.appearance;
        document.documentElement.dataset.theme = value;
        root.dataset.bsTheme = value;
    };
    theme();
    media.addEventListener('change', theme);
    const time = seconds => `${Math.floor((Number(seconds) || 0) / 60)}:${String(Math.floor((Number(seconds) || 0) % 60)).padStart(2, '0')}`;
    const update = () => {
        const playing = !audio.paused && !audio.ended;
        const duration = Number.isFinite(audio.duration) ? audio.duration : config.tracks[current].duration;
        const progress = duration ? audio.currentTime / duration * 100 : 0;
        let buffered = 0;
        for (let index = 0; index < audio.buffered.length; index++) buffered = Math.max(buffered, audio.buffered.end(index));
        find('play').setAttribute('aria-label', playing ? 'Пауза' : 'Воспроизвести');
        find('play').setAttribute('aria-pressed', String(playing));
        find('play').querySelector('i').className = `ci-${playing ? 'pause' : 'play-filled'} tr-icon`;
        find('time').textContent = time(audio.currentTime);
        find('duration').textContent = time(duration);
        const seek = find('seek');
        seek.value = progress * 10;
        seek.style.setProperty('--fill', `${progress}%`);
        seek.style.setProperty('--buffered', `${duration ? Math.min(100, buffered / duration * 100) : 0}%`);
        seek.setAttribute('aria-valuetext', `${time(audio.currentTime)} из ${time(duration)}`);
        root.querySelectorAll('[data-embed-index]').forEach(button => {
            const index = Number(button.dataset.embedIndex);
            const active = index === current;
            button.setAttribute('aria-current', String(active));
            button.setAttribute('aria-label', `${active && playing ? 'Пауза' : 'Воспроизвести'}: ${config.tracks[index].title}`);
            button.querySelector('i').className = `ci-${active && playing ? 'pause' : 'play-filled'}`;
        });
    };
    const play = async () => {
        try { find('error').hidden = true; await audio.play(); }
        catch (_) {
            if (!audio.paused) return;
            find('error').textContent = 'Не удалось начать воспроизведение. Нажмите ещё раз или откройте музыку на сайте.';
            find('error').hidden = false;
        }
    };
    const load = (index, autoplay = false) => {
        current = (index + config.tracks.length) % config.tracks.length;
        audio.src = config.tracks[current].url;
        find('error').hidden = true;
        find('song').textContent = config.kind === 'album' ? config.tracks[current].title : '';
        find('previous').disabled = find('next').disabled = config.tracks.length < 2;
        update();
        if (autoplay) play();
    };
    find('play').addEventListener('click', () => audio.paused ? play() : audio.pause());
    find('previous').addEventListener('click', () => load(current - 1, true));
    find('next').addEventListener('click', () => load(current + 1, true));
    find('seek').addEventListener('input', () => {
        if (Number.isFinite(audio.duration)) audio.currentTime = Number(find('seek').value) / 1000 * audio.duration;
    });
    root.querySelectorAll('[data-embed-index]').forEach(button => button.addEventListener('click', () => {
        const index = Number(button.dataset.embedIndex);
        if (index === current && !audio.paused) audio.pause();
        else if (index === current) play();
        else load(index, true);
    }));
    for (const event of ['play', 'pause', 'loadedmetadata', 'timeupdate', 'durationchange', 'progress']) audio.addEventListener(event, update);
    audio.addEventListener('ended', () => {
        if (current + 1 < config.tracks.length) load(current + 1, true);
        else update();
    });
    audio.addEventListener('error', () => {
        find('error').textContent = 'Песня недоступна. Возможно, публикация снята или источник временно не отвечает.';
        find('error').hidden = false;
    });
    load(0);
})();
