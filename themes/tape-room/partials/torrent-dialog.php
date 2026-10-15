<div class="tr-bootstrap" data-tr-shell data-bs-theme="light">
<div class="modal fade" id="tr-torrent-modal" tabindex="-1" aria-labelledby="tr-torrent-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="tr-torrent-title">Музыка из torrent</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть torrent"><?= $this->partial('site-icon',['name'=>'close']) ?></button></div>
    <div class="modal-body">
        <p class="text-body-secondary small">Музыка поступает напрямую в браузер. На сервер она не передаётся. Список песен и теги сохраняются только в этом браузере для вашего аккаунта.</p>
        <div class="alert alert-warning small">Нужны раздающие с поддержкой WebTorrent / WebRTC или доступный web seed. Обычная раздача без них не подключится. Закрытие страницы останавливает torrent.</div>
        <div class="alert alert-danger" role="alert" data-torrent-error hidden></div>
        <label class="form-label" for="tr-torrent-file">Открыть .torrent · до 2 МБ</label><input class="form-control" type="file" id="tr-torrent-file" accept=".torrent,application/x-bittorrent" data-torrent-file>
        <form class="mt-4" data-torrent-selection hidden>
            <h3 class="tr-torrent-name" data-torrent-name></h3><p class="text-body-secondary small" data-torrent-summary></p>
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" data-torrent-select-all checked><span class="form-check-label small">Выбрать все песни</span></label>
            <div class="tr-torrent-files" data-torrent-files></div>
            <div class="row g-3 mt-2"><div class="col-sm-6"><label class="form-label" for="tr-torrent-album">Название альбома</label><input class="form-control" name="title" id="tr-torrent-album" maxlength="240" required></div><div class="col-sm-6"><label class="form-label" for="tr-torrent-artist">Исполнитель</label><input class="form-control" name="artist" id="tr-torrent-artist" maxlength="240" placeholder="Из тегов песни при прослушивании"></div><div class="col-12"><label class="form-label" for="tr-torrent-genre">Жанр</label><select class="form-select" name="genre_id" id="tr-torrent-genre"><option value="">Из тегов песни</option><?php foreach(($config['state']['catalog']['genres']??[]) as $genre): ?><option value="<?= (int)$genre['id'] ?>"><?= htmlSC($genre['name']) ?></option><?php endforeach; ?></select></div></div>
            <p class="small text-body-secondary mt-3">Названия, длительность и обложка читаются из аудио при прослушивании, если теги есть. Альбом виден в «Моя музыка» на этом устройстве. До 128 МБ на песню.</p>
            <button class="btn btn-primary" type="submit" data-torrent-start>Добавить в мою музыку</button>
        </form>
        <section class="mt-4" data-torrent-progress hidden aria-live="polite"><h3 class="tr-torrent-name" data-torrent-progress-name></h3><p class="small text-body-secondary mb-1" data-torrent-status></p><p class="small text-body-secondary" data-torrent-speed></p><button class="btn btn-outline-secondary" type="button" data-torrent-stop>Остановить torrent</button></section>
        <section class="mt-4" data-torrent-recent hidden><h3 class="tr-torrent-name">В этом браузере</h3><div class="vstack gap-2" data-torrent-jobs></div></section>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Закрыть</button></div>
</div></div></div>
</div>
