    <section class="rp-chapter-panel" data-chapter-panel aria-labelledby="rp-chapter-heading" hidden>
        <div class="rp-chapter-toolbar">
            <div class="rp-chapter-heading">
                <h2 id="rp-chapter-heading">Список треков</h2>
                <p class="rp-chapter-source"><span class="rp-sr-only">Запись: </span><span data-chapter-source></span></p>
            </div>
            <div class="rp-chapter-tools" <?= !empty($readOnlyReel) ? 'hidden' : '' ?>>
                <button type="button" class="rp-upload-button" data-action="chapter-add"><?= $icon('plus') ?><span>Добавить таймкод</span></button>
                <button type="button" class="rp-button" data-action="chapter-tools">CUE и автопоиск</button>
            </div>
        </div>
        <label class="rp-search rp-chapter-search"><?= $icon('search') ?><input type="search" data-chapter-search placeholder="Найти трек или исполнителя" aria-label="Найти трек внутри аудиофайла"></label>
        <div class="rp-chapter-list" data-chapter-list></div>
        <p class="rp-chapter-empty" data-chapter-empty hidden>Ничего не найдено. Попробуйте другое название или имя исполнителя.</p>
    </section>
