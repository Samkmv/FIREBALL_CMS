<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Документация',
    'subtitle' => 'Выберите раздел документации FIREBALL CMS.',
]) ?>

    <div class="row g-4">
        <div class="col-md-6">
            <a class="card h-100 rounded-5 text-decoration-none border hover-shadow" href="<?= base_href('/admin/docs/themes') ?>">
                <div class="card-body p-4 p-md-5">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-4" style="width: 3rem; height: 3rem;">
                        <i class="ci-monitor fs-4"></i>
                    </span>
                    <h2 class="h4 text-body mb-2">Темы</h2>
                    <p class="text-body-secondary mb-0">Структура темы, шаблоны, Theme API, assets, SEO и редактор тем.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a class="card h-100 rounded-5 text-decoration-none border hover-shadow" href="<?= base_href('/admin/docs/plugins') ?>">
                <div class="card-body p-4 p-md-5">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success mb-4" style="width: 3rem; height: 3rem;">
                        <i class="ci-box fs-4"></i>
                    </span>
                    <h2 class="h4 text-body mb-2">Плагины</h2>
                    <p class="text-body-secondary mb-0">plugin.json, жизненный цикл, хуки, события, маршруты, миграции и настройки.</p>
                </div>
            </a>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
