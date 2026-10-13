<?php
declare(strict_types=1);
use Fireball\ReelPlayer\Services\PlatformAssets;
require dirname(__DIR__) . '/components/icons.php';
?>
<link rel="stylesheet" href="<?= htmlSC(PlatformAssets::url('public.css')) ?>">
<section class="tr-admin" data-platform-admin>
    <?php require dirname(__DIR__) . '/components/symbols.php'; ?>
    <header class="tr-heading"><div><h1>Tape Room · Платформа</h1><p>Каталог, распространение музыки и музыкальные жанры.</p></div><a class="tr-button" href="<?= htmlSC(base_href('/admin/reel-player')) ?>">Вернуться к магнитофону</a></header>
    <?php if (!empty($migrationPending)): ?><div class="tr-message"><h2>Требуется обновление базы данных</h2><p>Запустите обновление Tape Room в разделе плагинов CMS. Перед изменениями будет создана резервная копия. Личный плеер продолжает работать.</p></div>
    <?php else: ?>
    <div class="tr-admin-grid">
        <?php if (!empty($canManageFeatures)): ?><section class="tr-admin-card"><h2>Возможности платформы</h2><form class="tr-form" data-platform-form="settings">
            <?php foreach (['public_catalog'=>'Публичный музыкальный каталог','discovery'=>'Жанры, теги и рекомендации','compact_player'=>'Компактный плеер на сайте','embed'=>'Встраивание на других сайтах','monetization'=>'Доступ по подпискам'] as $key=>$label): ?><label class="tr-check"><input type="checkbox" name="<?= $key ?>" value="1"<?= !empty($features[$key])?' checked':'' ?>><?= $label ?></label><?php endforeach; ?>
            <p>Старые публикации остаются внутренними. Для появления музыки на сайте отдельно настройте альбом и права каждой композиции.</p>
            <button class="tr-button tr-button--primary" type="submit">Сохранить возможности</button><p class="tr-error" data-form-status role="status" hidden></p>
        </form></section><?php endif; ?>
        <section class="tr-admin-card"><h2>Доступ к музыке</h2><form class="tr-form" data-platform-form="distribution">
            <label class="tr-field">Запись<select name="entity" data-platform-entity><option value="">Выберите альбом или композицию</option><?php foreach (['album'=>$albums ?? [],'track'=>$tracks ?? []] as $type=>$entities): ?><optgroup label="<?= $type==='album'?'Альбомы':'Композиции' ?>"><?php foreach ($entities as $entity): ?><option value="<?= $type ?>:<?= (int)$entity['id'] ?>"><?= htmlSC(implode(' — ',array_filter([$entity['artist'],$entity['title']]))) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <label class="tr-field">Кому доступна музыка<select name="access_mode"><?php foreach (['private'=>'Только мне','internal'=>'Внутренний каталог администраторов','public'=>'Всем посетителям','authenticated'=>'После входа в аккаунт','subscription'=>'По подписке'] as $value=>$label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select></label>
            <label class="tr-check"><input type="checkbox" name="show_metadata" value="1">Показывать карточку посетителям без доступа к прослушиванию</label>
            <label class="tr-field">Разрешённые тарифы<select name="plan_ids[]" multiple><?php foreach ($plans ?? [] as $plan): ?><option value="<?= (int)$plan['id'] ?>"><?= htmlSC($plan['name']) ?></option><?php endforeach; ?></select><small>Учитываются только активные подписки и музыкальные разрешения тарифа. Пустой список допускает любой подходящий активный тариф.</small></label>
            <fieldset data-track-rights hidden><legend>Распространение композиции</legend><div class="tr-form">
                <label class="tr-check"><input type="checkbox" name="rights_confirmed" value="1">Подтверждаю необходимые права на распространение</label>
                <label class="tr-field">Основание<select name="license_type"><option value="">Выберите основание</option><option value="own">Собственные права</option><option value="permission">Разрешение правообладателя</option><option value="creative_commons">Creative Commons</option><option value="other">Другое</option></select></label>
                <label class="tr-field">Ссылка на условия лицензии<input type="url" name="license_url" maxlength="2048" placeholder="https://…"></label>
                <label class="tr-check"><input type="checkbox" name="allow_stream" value="1">Разрешить прослушивание на сайте</label><label class="tr-check"><input type="checkbox" name="allow_embed" value="1">Разрешить встраивание открытой музыки</label><label class="tr-check"><input type="checkbox" name="allow_download" value="1">Разрешить скачивание оригинала</label>
                <label class="tr-field">Ссылка на покупку<input type="url" name="purchase_url" maxlength="2048" placeholder="https://…"></label>
            </div></fieldset>
            <p data-distribution-note>Альбом должен также быть опубликован в существующем редакторе. Разрешения самой композиции применяются во всех альбомах.</p>
            <button class="tr-button tr-button--primary" type="submit">Сохранить доступ</button><p class="tr-error" data-form-status role="status" hidden></p>
        </form></section>
        <section class="tr-admin-card"><h2>Встраивание музыки</h2><form class="tr-form" data-platform-form="embed.settings">
            <label class="tr-field">Композиция<select name="id" data-embed-track><option value="">Выберите композицию</option><?php foreach ($tracks ?? [] as $entity): ?><option value="<?= (int)$entity['id'] ?>"><?= htmlSC($entity['title']) ?></option><?php endforeach; ?></select></label>
            <label class="tr-field">Тема виджета<select name="theme"><option value="system">Системная</option><option value="light">Светлая</option><option value="dark">Тёмная</option></select></label>
            <label class="tr-field">Разрешённые сайты<textarea name="origins" rows="3" placeholder="https://example.com"></textarea><small>По одному адресу без пути на строке. Пустой список разрешает встраивание на любых сайтах. Требуются открытая публикация и разрешение на встраивание.</small></label>
            <button class="tr-button tr-button--primary" type="submit">Сохранить виджет</button><p class="tr-error" data-form-status role="status" hidden></p>
            <div data-embed-codes></div>
        </form></section>
        <section class="tr-admin-card"<?= empty($features['discovery'])?' hidden':'' ?>><h2>Жанры и теги</h2>
            <form class="tr-form" data-platform-form="taxonomy"><div class="tr-inline"><label class="tr-field">Справочник<select name="kind"><option value="genre">Жанры</option><option value="tag">Теги</option></select></label><label class="tr-field">Запись<select name="id" data-taxonomy-record><option value="">Создать новую</option></select></label></div><label class="tr-field">Название<input name="name" maxlength="120" required></label><label class="tr-field">Адресное имя<input name="slug" maxlength="120" placeholder="Создаётся из названия"></label><label class="tr-field">Описание<textarea name="description" maxlength="1000"></textarea></label><div class="tr-inline"><button class="tr-button tr-button--primary" type="submit">Сохранить</button><button class="tr-button tr-button--quiet" type="button" data-taxonomy-delete>Удалить запись справочника</button></div><p class="tr-error" data-form-status role="status" hidden></p></form>
            <h3 style="margin-top:28px">Жанры и теги музыки</h3><form class="tr-form" data-platform-form="entity.set"><label class="tr-field">Запись<select name="entity" data-taxonomy-entity><option value="">Выберите запись</option><?php foreach (['album'=>$albums ?? [],'track'=>$tracks ?? []] as $type=>$entities) foreach ($entities as $entity): ?><option value="<?= $type ?>:<?= (int)$entity['id'] ?>"><?= htmlSC(($type==='album'?'Альбом: ':'Композиция: ').$entity['title']) ?></option><?php endforeach; ?></select></label><label class="tr-field">Жанры<select name="genres[]" multiple data-taxonomy-genres></select></label><label class="tr-field">Теги<select name="tags[]" multiple data-taxonomy-tags></select></label><button class="tr-button tr-button--primary" type="submit">Сохранить разметку</button><p class="tr-error" data-form-status role="status" hidden></p></form>
        </section>
    </div>
    <script type="application/json" data-platform-data><?= json_encode(['albums'=>$albums ?? [],'tracks'=>$tracks ?? [],'features'=>$features,'csrf'=>$csrf,'base'=>base_href('/admin/reel-player'),'musicBase'=>base_href('/music')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) ?></script>
    <script src="<?= htmlSC(PlatformAssets::url('platform-admin.js')) ?>" defer></script>
    <?php endif; ?>
</section>
