<?php
$history=$changelog['releases'];
$capabilities=$changelog['capabilities'];
$counts=array_count_values(array_column($capabilities,'status'));
$statusLabels=['done'=>'Готово','partial'=>'С ограничениями','absent'=>'Пока нет'];
$statusIcons=['done'=>'check','partial'=>'clock','absent'=>'roadmap'];
$formatDate=static fn(string $value): string=>(new DateTimeImmutable($value))->format('d.m.Y');
$sourcePending=$changelog['roadmap_source']==='awaiting-original';
?>
<?= $this->partial('development-navigation',['section'=>'changelog']) ?>
<div class="tr-changelog">
    <header class="tr-history-hero">
        <span class="tr-eyebrow">TAPE ROOM · ИСТОРИЯ ПРОЕКТА</span>
        <h1>Change Log</h1>
        <p>Как менялся плеер: новые возможности, исправления и то, что ещё предстоит сделать.</p>
        <div class="tr-history-versions" aria-label="Установленные версии">
            <?php foreach($release_versions as $label=>$value): ?><span><b><?= htmlSC($label) ?></b> <?= htmlSC($value) ?></span><?php endforeach; ?>
        </div>
    </header>
    <div class="row g-3 tr-history-summary" aria-label="Сводка истории">
        <div class="col-12 col-sm-4"><div class="card h-100"><div class="card-body"><strong><?= count($history) ?></strong><span>записей в истории</span><small>С <?= $formatDate($history[array_key_last($history)]['date']) ?></small></div></div></div>
        <div class="col-6 col-sm-4"><div class="card h-100"><div class="card-body"><strong><?= $counts['done'] ?? 0 ?></strong><span>возможностей готово</span><small>Проверено по текущему коду</small></div></div></div>
        <div class="col-6 col-sm-4"><div class="card h-100"><div class="card-body"><strong><?= ($counts['partial'] ?? 0)+($counts['absent'] ?? 0) ?></strong><span>требуют продолжения</span><small>Ограничения и отсутствующие функции</small></div></div></div>
    </div>
    <ul class="nav nav-pills tr-history-tabs" role="tablist" aria-label="История и дорожная карта">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="tr-history-tab" type="button" data-bs-toggle="pill" data-bs-target="#tr-history-panel" role="tab" aria-controls="tr-history-panel" aria-selected="true"><?= $this->partial('site-icon',['name'=>'changelog']) ?>История изменений</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="tr-roadmap-tab" type="button" data-bs-toggle="pill" data-bs-target="#tr-roadmap-panel" role="tab" aria-controls="tr-roadmap-panel" aria-selected="false"><?= $this->partial('site-icon',['name'=>'roadmap']) ?>Дорожная карта</button></li>
    </ul>
    <div class="tab-content">
        <section class="tab-pane fade show active" id="tr-history-panel" role="tabpanel" aria-labelledby="tr-history-tab" tabindex="0">
            <div class="tr-history-section-heading"><div><h2>От первого запуска до сегодня</h2><p>История восстановлена по сохранённым выпускам и изменениям проекта. Промежуточные версии объединены там, где они сохранились одним обновлением.</p></div><span class="tr-history-reviewed">Сверено <?= $formatDate($changelog['reviewed_at']) ?></span></div>
            <div class="accordion tr-release-list" id="tr-release-list">
                <?php foreach($history as $index=>$release): $archived=!empty($release['archived']); $releaseId='tr-release-'.$index; ?>
                <article class="accordion-item tr-release <?= $archived?'tr-release--archived':'' ?>">
                    <h3 class="accordion-header" id="<?= $releaseId ?>-heading"><button class="accordion-button <?= $index===0?'':'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $releaseId ?>" aria-expanded="<?= $index===0?'true':'false' ?>" aria-controls="<?= $releaseId ?>">
                        <span class="tr-release-number"><b><?= htmlSC($release['version']) ?></b><time datetime="<?= htmlSC($release['date']) ?>"><?= $formatDate($release['date']) ?></time></span>
                        <span class="tr-release-name"><?= htmlSC($release['title']) ?><?php if($archived): ?><small>Архив · убрано из main</small><?php elseif($index===0): ?><small>Новый выпуск</small><?php endif; ?></span>
                    </button></h3>
                    <div class="accordion-collapse collapse <?= $index===0?'show':'' ?>" id="<?= $releaseId ?>" aria-labelledby="<?= $releaseId ?>-heading"><div class="accordion-body">
                        <?php foreach(['added'=>'Добавлено','fixed'=>'Исправлено'] as $key=>$label): if(empty($release[$key]))continue; ?><div class="tr-release-changes"><h4><?= $label ?></h4><ul><?php foreach($release[$key] as $item): ?><li><?= htmlSC($item) ?></li><?php endforeach; ?></ul></div><?php endforeach; ?>
                        <?php foreach($release['notes'] ?? [] as $note): ?><p class="tr-release-note"><?= htmlSC($note) ?></p><?php endforeach; ?>
                    </div></div>
                </article>
                <?php endforeach; ?>
            </div>
            <aside class="tr-history-engine card"><div class="card-body"><span class="tr-eyebrow">ВМЕСТЕ С FIREBALL CMS</span><h3>Иконки приложения и безопасные обновления</h3><p>Пользовательские PWA-иконки отделены от системных, старые наборы сохраняются. Штатные изменения Composer и независимые обновления плагинов учитываются отдельно от правок исходников. Перед заменой кода создаются копии файлов и базы, предусмотрено восстановление. Локальная музыка, настройки и загрузки сохраняются.</p></div></aside>
        </section>
        <section class="tab-pane fade" id="tr-roadmap-panel" role="tabpanel" aria-labelledby="tr-roadmap-tab" tabindex="0">
            <div class="tr-history-section-heading"><div><h2>Что уже работает и что осталось</h2><p>Статусы относятся к текущей реализации плеера. Ограничения источников и проверка на реальных устройствах отмечены отдельно.</p></div></div>
            <?php if($sourcePending): ?><div class="tr-roadmap-source" role="note"><strong>Сверка исходного плана ожидается</strong><p>Полный текст связанной дорожной карты пока недоступен. Ниже — проверка возможностей по текущему коду; это не перечень согласованных будущих задач.</p><a href="<?= htmlSC($changelog['roadmap_url']) ?>" target="_blank" rel="noopener noreferrer">Открыть исходную дорожную карту <?= $this->partial('site-icon',['name'=>'arrow']) ?></a></div><?php endif; ?>
            <div class="tr-roadmap-legend" aria-label="Обозначения статусов"><?php foreach($statusLabels as $status=>$label): ?><span class="tr-roadmap-status tr-roadmap-status--<?= $status ?>"><?= $this->partial('site-icon',['name'=>$statusIcons[$status]]) ?><?= $label ?> · <?= $counts[$status] ?? 0 ?></span><?php endforeach; ?></div>
            <?php foreach($statusLabels as $status=>$label): $items=array_filter($capabilities,static fn(array $item): bool=>$item['status']===$status); if(!$items)continue; ?>
            <section class="tr-roadmap-group" aria-labelledby="tr-roadmap-<?= $status ?>"><h3 id="tr-roadmap-<?= $status ?>"><?= $status==='done'?'Готово в текущей версии':($status==='partial'?'Работает с ограничениями':'Возможности, которых пока нет') ?></h3><div class="row g-3">
                <?php foreach($items as $item): ?><div class="col-12 col-lg-6"><article class="card tr-roadmap-card h-100" id="roadmap-<?= htmlSC($item['id']) ?>"><div class="card-body"><div class="tr-roadmap-card-heading"><span class="tr-eyebrow"><?= htmlSC($item['group']) ?></span><span class="tr-roadmap-status tr-roadmap-status--<?= $status ?>"><?= $this->partial('site-icon',['name'=>$statusIcons[$status]]) ?><?= $label ?></span></div><h4><?= htmlSC($item['title']) ?></h4><p><?= htmlSC($item['description']) ?></p><?php if(!empty($item['remaining'])): ?><div class="tr-roadmap-remaining"><b>Что осталось</b><p><?= htmlSC($item['remaining']) ?></p></div><?php endif; ?></div></article></div><?php endforeach; ?>
            </div></section>
            <?php endforeach; ?>
            <p class="tr-history-footnote">Отсутствующая возможность не означает обещанный срок. Приоритеты и даты появятся после сверки согласованной дорожной карты.</p>
        </section>
    </div>
</div>
