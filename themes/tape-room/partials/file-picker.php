<div class="tr-file-picker d-flex flex-wrap align-items-center gap-3" data-tr-file-picker>
    <input class="visually-hidden" type="file" id="<?= htmlSC($id) ?>" accept="<?= htmlSC($accept ?? '*') ?>" tabindex="-1" <?= !empty($torrent_file)?'data-torrent-file':'' ?> <?= !empty($name)?'name="'.htmlSC($name).'"':'' ?>>
    <button class="btn btn-outline-secondary" type="button" data-tr-file-pick aria-controls="<?= htmlSC($id) ?>"><?= $this->partial('site-icon',['name'=>'upload']) ?><span><?= htmlSC($button_label ?? 'Выбрать файл') ?></span></button>
    <span class="small text-body-secondary tr-file-name" data-tr-file-name aria-live="polite">Файл не выбран</span>
</div>
