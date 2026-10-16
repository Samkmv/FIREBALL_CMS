<?= view()->renderPartial('admin/shell_open',['title'=>'Tape Room · Настройки','subtitle'=>'Подключение Google Drive для сайта']) ?>
<?php require __DIR__.'/admin-tabs.php'; ?>
<section class="card rounded-4"><div class="card-body p-3 p-md-4">
    <h2 class="h5">Google Drive</h2>
    <p class="text-body-secondary">Общие параметры приложения Google. Каждый пользователь подключает собственный Google-аккаунт в плеере; его песни и альбомы сохраняются в личной библиотеке.</p>
    <?php if ($drive['canManage']): ?>
    <form method="post" action="<?= htmlSC(base_href('/admin/reel-player/settings')) ?>" class="row g-3" autocomplete="off">
        <?= get_csrf_field() ?>
        <div class="col-12"><label class="form-label" for="drive-client-id">OAuth Client ID</label><input class="form-control" id="drive-client-id" name="client_id" value="<?= htmlSC($drive['clientId']) ?>" maxlength="500" spellcheck="false" required></div>
        <div class="col-12"><label class="form-label" for="drive-client-secret">OAuth Client Secret</label><input class="form-control" type="password" id="drive-client-secret" name="client_secret" maxlength="500" autocomplete="new-password" <?= $drive['configured']?'':'required' ?>><div class="form-text"><?= $drive['configured']?'Оставьте пустым, чтобы сохранить текущий секрет.':'Введите секрет клиента Google.' ?></div></div>
        <div class="col-12"><label class="form-label" for="drive-callback">Адрес возврата Google</label><input class="form-control" id="drive-callback" value="<?= htmlSC($drive['callback']) ?>" readonly><div class="form-text">Добавьте этот адрес без изменений в Authorized redirect URIs.</div></div>
        <div class="col-12"><button type="submit" class="btn btn-dark">Сохранить настройки</button></div>
    </form>
    <details class="mt-4"><summary>Как настроить подключение</summary><ol class="mt-3">
        <li>Создайте проект в <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a> и включите Google Drive API.</li>
        <li>Настройте Google Auth Platform, доступ External и разрешение <code>https://www.googleapis.com/auth/drive.readonly</code>. В режиме Testing добавьте пользователей в Test users.</li>
        <li>Создайте OAuth-клиент типа Web application. В Authorized redirect URIs укажите адрес возврата выше.</li>
        <li>Сохраните Client ID и Client Secret в этой форме. Затем пользователи могут выбрать «Google Drive» в боковом меню плеера и подключить свой аккаунт.</li>
    </ol><p class="small text-body-secondary">В режиме Testing доступ может потребовать повторного подключения через семь дней.</p></details>
    <?php else: ?><p class="mb-0">Подключение <?= $drive['configured']?'настроено':'ещё не настроено' ?>. Изменять параметры OAuth может только создатель сайта.</p><?php endif; ?>
</div></section>
<?= view()->renderPartial('admin/shell_close') ?>
