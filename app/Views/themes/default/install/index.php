<?php
$step = (string)($step ?? 'language');
$data = (array)($data ?? []);
$result = (array)($result ?? []);
$locale = (string)($data['locale'] ?? DEFAULT_LOCALE);
$db = (array)($data['db'] ?? []);
$site = (array)($data['site'] ?? []);
$admin = (array)($data['admin'] ?? []);
$dbTables = (array)($data['db_tables'] ?? []);
$defaultSiteUrl = (string)($default_site_url ?? '');
$translations = (array)($translations ?? []);
$t = static fn(string $key): string => (string)($translations[$key] ?? $key);
$asset = static fn(string $path): string => asset_versioned_url(base_url($path), WWW . $path);
$stages = [
    'language' => ['label' => 'step_language', 'icon' => 'ci-globe'],
    'requirements' => ['label' => 'step_requirements', 'icon' => 'ci-server'],
    'database' => ['label' => 'step_database', 'icon' => 'ci-database'],
    'site' => ['label' => 'step_site', 'icon' => 'ci-settings'],
    'admin' => ['label' => 'step_admin', 'icon' => 'ci-user'],
];
$stageKeys = array_keys($stages);
$finished = $step === 'finish' && ($result['status'] ?? '') === 'success';
$stageIndex = array_search($step, $stageKeys, true);
$stageIndex = $finished ? count($stages) : ($stageIndex === false ? 0 : $stageIndex);
$stepNumber = min($stageIndex + 1, count($stages));
$stepLabel = str_replace([':current', ':total'], [(string)$stepNumber, (string)count($stages)], $t('step_of'));
$version = (string)((require CONFIG . '/version.php')['version'] ?? '');
$titles = ['language' => 'language', 'requirements' => 'requirements', 'database' => 'database', 'site' => 'site', 'admin' => 'admin'];
?>
<!doctype html>
<html lang="<?= htmlSC($locale) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#f5f6f8">
    <title><?= htmlSC($t('title')) ?> — FIREBALL CMS</title>
    <script>
        (function () {
            var theme = 'auto';
            try { theme = localStorage.getItem('theme') || theme; } catch (error) {}
            if (theme === 'auto') theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            theme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.querySelector('meta[name="theme-color"]').content = theme === 'dark' ? '#101720' : '#f5f6f8';
        })();
    </script>
    <link rel="icon" type="image/svg+xml" href="<?= htmlSC($asset('/assets/default/icons/fireball-admin.svg')) ?>">
    <link rel="stylesheet" href="<?= htmlSC($asset('/assets/default/icons/cartzilla-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= htmlSC($asset('/assets/default/css/theme.min.css')) ?>">
    <link rel="stylesheet" href="<?= htmlSC($asset('/assets/default/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlSC($asset('/assets/default/css/install.css')) ?>">
</head>
<body class="fb-install-body">
<div class="install-shell">
    <header class="install-header">
        <div class="install-brand">
            <img src="<?= htmlSC($asset('/assets/default/icons/fireball-admin.svg')) ?>" width="44" height="44" alt="">
            <span>FIREBALL <span class="install-brand__cms">CMS</span></span>
        </div>
        <div class="install-header__tools">
            <?php if ($version !== ''): ?><span class="install-version">v<?= htmlSC($version) ?></span><?php endif; ?>
            <button class="install-theme" type="button" data-install-theme aria-label="<?= htmlSC($t('theme_toggle')) ?>" title="<?= htmlSC($t('theme_toggle')) ?>">
                <i class="ci-moon install-theme__moon" aria-hidden="true"></i><i class="ci-sun install-theme__sun" aria-hidden="true"></i>
            </button>
        </div>
    </header>
    <div class="install-intro">
        <div class="install-eyebrow"><span aria-hidden="true"></span><?= htmlSC($t('welcome')) ?></div>
        <h1><?= htmlSC($t('title')) ?></h1>
        <p><?= htmlSC($t('subtitle')) ?></p>
    </div>
    <main class="install-workspace">
        <aside class="install-sidebar" aria-label="<?= htmlSC($t('stages')) ?>">
            <div class="install-sidebar__heading"><?= htmlSC($t('stages')) ?><span><?= $finished ? htmlSC($t('done')) : $stepNumber . ' / ' . count($stages) ?></span></div>
            <ol class="install-stages">
                <?php foreach ($stages as $key => $stage): $index = array_search($key, $stageKeys, true); $complete = $index < $stageIndex; $active = !$finished && $key === $step; ?>
                    <li class="install-stage <?= $complete ? 'is-complete' : '' ?> <?= $active ? 'is-active' : '' ?>" <?= $active ? 'aria-current="step"' : '' ?>>
                        <span class="install-stage__icon" aria-hidden="true"><i class="<?= $complete ? 'ci-check' : $stage['icon'] ?>"></i></span>
                        <span class="install-stage__text"><small><?= htmlSC(str_replace(':number', (string)($index + 1), $t('step_number'))) ?></small><span><?= htmlSC($t($stage['label'])) ?></span></span>
                        <?php if ($active): ?><span class="install-stage__dot" aria-hidden="true"></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="install-sidebar__note"><i class="ci-shield" aria-hidden="true"></i><p><?= htmlSC($t('safe_setup')) ?></p></div>
        </aside>
        <section class="install-card" aria-labelledby="install-step-title">
            <?php if (!$finished): ?>
                <div class="install-card__header">
                    <div class="install-card__meta"><span><?= htmlSC($stepLabel) ?></span><span><?= htmlSC($t('setup')) ?></span></div>
                    <div class="install-progress" role="progressbar" aria-label="<?= htmlSC($t('stages')) ?>" aria-valuenow="<?= $stepNumber ?>" aria-valuemin="0" aria-valuemax="<?= count($stages) ?>"><span style="width:<?= $stepNumber * 100 / count($stages) ?>%"></span></div>
                    <h2 id="install-step-title"><?= htmlSC($t($titles[$step] ?? 'start')) ?></h2>
                    <p><?= htmlSC((string)($translations[$step . '_hint'] ?? $t('language_hint'))) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($result['message']) && !$finished): ?>
                <div class="install-feedback <?= ($result['status'] ?? '') === 'error' ? 'is-error' : 'is-success' ?>" role="<?= ($result['status'] ?? '') === 'error' ? 'alert' : 'status' ?>">
                    <i class="<?= ($result['status'] ?? '') === 'error' ? 'ci-alert-circle' : 'ci-check-circle' ?>" aria-hidden="true"></i>
                    <div><?= htmlSC((string)$result['message']) ?><?php if (!empty($result['tables'])): ?><small><?= htmlSC($t('tables')) ?>: <?= htmlSC(implode(', ', array_slice((array)$result['tables'], 0, 12))) ?></small><?php endif; ?></div>
                </div>
            <?php endif; ?>
            <?php if ($step === 'language'): ?>
                <form method="post" action="<?= base_url('/install') ?>" data-install-form data-busy-label="<?= htmlSC($t('continuing')) ?>">
                    <?= get_csrf_field() ?><input type="hidden" name="step" value="language">
                    <fieldset class="install-languages">
                        <legend class="visually-hidden"><?= htmlSC($t('language')) ?></legend>
                        <?php foreach ((array)$languages as $code => $language): ?>
                            <label class="install-language">
                                <span class="install-language__symbol" aria-hidden="true"><?= htmlSC($code === 'zh-cn' ? '简' : strtoupper($code)) ?></span>
                                <span class="install-language__name"><?= htmlSC((string)($language['title'] ?? $code)) ?><small><?= htmlSC($t('language_' . $code)) ?></small></span>
                                <input class="form-check-input" type="radio" name="locale" value="<?= htmlSC($code) ?>" <?= $code === $locale ? 'checked' : '' ?> required>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="install-help"><i class="ci-info" aria-hidden="true"></i><?= htmlSC($t('language_note')) ?></p>
                    <div class="install-actions"><span class="install-actions__note"><?= htmlSC($t('next_requirements')) ?></span><button class="btn install-primary" type="submit"><span data-submit-label><?= htmlSC($t('continue')) ?></span><i class="ci-arrow-right" data-submit-icon aria-hidden="true"></i></button></div>
                </form>
            <?php elseif ($step === 'requirements'): ?>
                <div class="install-check-summary <?= empty($requirements_pass) ? 'is-warning' : 'is-success' ?>"><i class="<?= empty($requirements_pass) ? 'ci-alert-triangle' : 'ci-check-circle' ?>" aria-hidden="true"></i><div><strong><?= htmlSC($t(empty($requirements_pass) ? 'requirements_failed' : 'requirements_ready')) ?></strong><span><?= htmlSC($t(empty($requirements_pass) ? 'requirements_fix' : 'requirements_ready_hint')) ?></span></div></div>
                <ul class="install-requirements">
                    <?php foreach ((array)$requirements as $item): ?>
                        <li><span class="install-requirement__name"><?= htmlSC((string)$item['label']) ?></span><span class="install-requirement__status <?= !empty($item['ok']) ? 'is-ok' : 'is-failed' ?>"><i class="<?= !empty($item['ok']) ? 'ci-check' : 'ci-close' ?>" aria-hidden="true"></i><span><?= htmlSC($t(!empty($item['ok']) ? 'ready' : 'error')) ?></span></span></li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" action="<?= base_url('/install') ?>" data-install-form data-busy-label="<?= htmlSC($t('continuing')) ?>">
                    <?= get_csrf_field() ?><input type="hidden" name="step" value="requirements">
                    <div class="install-actions"><a class="install-back" href="<?= base_url('/install?step=language') ?>"><i class="ci-arrow-left" aria-hidden="true"></i><?= htmlSC($t('back')) ?></a><div class="install-actions__buttons"><?php if (empty($requirements_pass)): ?><a class="btn btn-outline-secondary rounded-pill" href="<?= base_url('/install?step=requirements') ?>"><i class="ci-refresh-cw me-2" aria-hidden="true"></i><?= htmlSC($t('check_again')) ?></a><?php endif; ?><button class="btn install-primary" type="submit" <?= empty($requirements_pass) ? 'disabled' : '' ?>><span data-submit-label><?= htmlSC($t('continue')) ?></span><i class="ci-arrow-right" data-submit-icon aria-hidden="true"></i></button></div></div>
                </form>
            <?php elseif ($step === 'database'): ?>
                <form method="post" action="<?= base_url('/install') ?>" data-install-form data-busy-label="<?= htmlSC($t('testing_connection')) ?>">
                    <?= get_csrf_field() ?><input type="hidden" name="step" value="database">
                    <div class="row g-3 g-md-4">
                        <div class="col-md-6"><label class="form-label" for="install-db-type"><?= htmlSC($t('db_type')) ?></label><input id="install-db-type" class="form-control" value="MySQL" disabled></div>
                        <div class="col-md-6"><label class="form-label" for="install-db-host"><?= htmlSC($t('host')) ?></label><input id="install-db-host" class="form-control" name="db_host" value="<?= htmlSC((string)($db['host'] ?? 'localhost')) ?>" autocomplete="off" required></div>
                        <div class="col-md-6"><label class="form-label" for="install-db-port"><?= htmlSC($t('port')) ?></label><input id="install-db-port" class="form-control" type="number" min="1" max="65535" name="db_port" value="<?= htmlSC((string)($db['port'] ?? '3306')) ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="install-db-name"><?= htmlSC($t('db_name')) ?></label><input id="install-db-name" class="form-control" name="db_name" value="<?= htmlSC((string)($db['database'] ?? '')) ?>" placeholder="fireball_cms" autocomplete="off" required></div>
                        <div class="col-md-6"><label class="form-label" for="install-db-user"><?= htmlSC($t('db_user')) ?></label><input id="install-db-user" class="form-control" name="db_user" value="<?= htmlSC((string)($db['username'] ?? '')) ?>" autocomplete="off" required></div>
                        <div class="col-md-6"><?= view()->renderPartial('incs/password_field', ['id' => 'install-db-password', 'name' => 'db_password', 'label' => $t('password'), 'value' => (string)($db['password'] ?? ''), 'autocomplete' => 'off']) ?></div>
                        <div class="col-12"><label class="form-label" for="install-db-prefix"><?= htmlSC($t('prefix')) ?></label><input id="install-db-prefix" class="form-control" name="db_prefix" value="<?= htmlSC((string)($db['prefix'] ?? '')) ?>" placeholder="<?= htmlSC($t('prefix_placeholder')) ?>" aria-describedby="install-prefix-help"><div class="form-text" id="install-prefix-help"><?= htmlSC($t('prefix_help')) ?></div></div>
                    </div>
                    <div class="install-actions"><a class="install-back" href="<?= base_url('/install?step=requirements') ?>"><i class="ci-arrow-left" aria-hidden="true"></i><?= htmlSC($t('back')) ?></a><button class="btn install-primary" type="submit"><span data-submit-label><?= htmlSC($t('test_connection')) ?></span><i class="ci-arrow-right" data-submit-icon aria-hidden="true"></i></button></div>
                </form>
            <?php elseif ($step === 'site'): ?>
                <?php if (!empty($dbTables)): ?><div class="install-feedback is-warning" role="alert"><i class="ci-alert-triangle" aria-hidden="true"></i><div><?= htmlSC($t('existing_tables')) ?></div></div><?php endif; ?>
                <form method="post" action="<?= base_url('/install') ?>" data-install-form data-busy-label="<?= htmlSC($t('continuing')) ?>">
                    <?= get_csrf_field() ?><input type="hidden" name="step" value="site">
                    <div class="mb-4"><label class="form-label" for="install-site-name"><?= htmlSC($t('site_name')) ?></label><input id="install-site-name" class="form-control" name="site_name" value="<?= htmlSC((string)($site['name'] ?? 'FIREBALL CMS')) ?>" required></div>
                    <div class="mb-4"><label class="form-label" for="install-site-url"><?= htmlSC($t('site_url')) ?></label><input id="install-site-url" class="form-control" type="url" name="site_url" value="<?= htmlSC((string)($site['url'] ?? $defaultSiteUrl)) ?>" placeholder="https://example.com" required><div class="form-text"><?= htmlSC($t('site_url_hint')) ?></div></div>
                    <div><label class="form-label" for="install-timezone"><?= htmlSC($t('timezone')) ?></label><select id="install-timezone" class="form-select" name="timezone"><?php foreach ((array)$timezones as $tz): ?><option value="<?= htmlSC($tz) ?>" <?= ($site['timezone'] ?? APP_TIMEZONE) === $tz ? 'selected' : '' ?>><?= htmlSC($tz) ?></option><?php endforeach; ?></select></div>
                    <div class="install-actions"><a class="install-back" href="<?= base_url('/install?step=database') ?>"><i class="ci-arrow-left" aria-hidden="true"></i><?= htmlSC($t('back')) ?></a><button class="btn install-primary" type="submit"><span data-submit-label><?= htmlSC($t('continue')) ?></span><i class="ci-arrow-right" data-submit-icon aria-hidden="true"></i></button></div>
                </form>
            <?php elseif ($step === 'admin'): ?>
                <form method="post" action="<?= base_url('/install') ?>" data-install-form data-busy-label="<?= htmlSC($t('installing')) ?>">
                    <?= get_csrf_field() ?><input type="hidden" name="step" value="admin">
                    <div class="row g-3 g-md-4">
                        <div class="col-md-6"><label class="form-label" for="install-admin-login"><?= htmlSC($t('login')) ?></label><input id="install-admin-login" class="form-control" name="admin_login" value="<?= htmlSC((string)($admin['login'] ?? 'creator')) ?>" autocomplete="username" required></div>
                        <div class="col-md-6"><label class="form-label" for="install-admin-email"><?= htmlSC($t('email')) ?></label><input id="install-admin-email" class="form-control" type="email" name="admin_email" value="<?= htmlSC((string)($admin['email'] ?? '')) ?>" placeholder="you@example.com" autocomplete="email" required></div>
                        <div class="col-md-6"><?= view()->renderPartial('incs/password_field', ['id' => 'install-admin-password', 'name' => 'admin_password', 'label' => $t('password'), 'autocomplete' => 'new-password', 'required' => true, 'minlength' => 12]) ?></div>
                        <div class="col-md-6"><?= view()->renderPartial('incs/password_field', ['id' => 'install-admin-password-confirmation', 'name' => 'admin_password_confirmation', 'label' => $t('password_confirm'), 'autocomplete' => 'new-password', 'required' => true, 'minlength' => 12]) ?></div>
                    </div>
                    <p class="install-help"><i class="ci-lock" aria-hidden="true"></i><?= htmlSC($t('password_hint')) ?></p>
                    <label class="install-demo"><input class="form-check-input" type="checkbox" name="install_demo" value="1" <?= !empty($data['demo']) ? 'checked' : '' ?>><span><strong><?= htmlSC($t('install_demo')) ?></strong><small><?= htmlSC($t('demo_hint')) ?></small></span><i class="ci-layout" aria-hidden="true"></i></label>
                    <?php if (!empty($dbTables) || !empty($result['requires_confirmation'])): ?><label class="install-existing"><input class="form-check-input" type="checkbox" name="allow_existing" value="1" required><span><?= htmlSC($t('allow_existing')) ?></span></label><?php endif; ?>
                    <div class="install-actions"><a class="install-back" href="<?= base_url('/install?step=site') ?>"><i class="ci-arrow-left" aria-hidden="true"></i><?= htmlSC($t('back')) ?></a><button class="btn install-primary" type="submit"><span data-submit-label><?= htmlSC($t('install')) ?></span><i class="ci-arrow-right" data-submit-icon aria-hidden="true"></i></button></div>
                    <p class="install-submit-hint" data-install-status role="status" aria-live="polite"></p>
                </form>
            <?php elseif ($finished): ?>
                <div class="install-finish">
                    <span class="install-finish__icon" aria-hidden="true"><i class="ci-check"></i></span>
                    <div class="install-eyebrow"><?= htmlSC($t('done')) ?></div>
                    <h2 id="install-step-title"><?= htmlSC($t('success')) ?></h2>
                    <p><?= htmlSC($t('success_hint')) ?></p>
                    <dl class="install-finish__details"><div><dt><?= htmlSC($t('version')) ?></dt><dd><?= htmlSC((string)($result['version'] ?? '')) ?></dd></div><div><dt><?= htmlSC($t('site_url')) ?></dt><dd><?= htmlSC((string)($result['site_url'] ?? '')) ?></dd></div><div><dt><?= htmlSC($t('login')) ?></dt><dd><?= htmlSC((string)($result['login'] ?? '')) ?></dd></div></dl>
                    <div class="install-finish__actions"><a class="btn install-primary" href="<?= base_url('/admin') ?>"><?= htmlSC($t('open_admin')) ?><i class="ci-arrow-right" aria-hidden="true"></i></a><a class="btn btn-outline-secondary rounded-pill" href="<?= base_url('/') ?>"><?= htmlSC($t('open_site')) ?><i class="ci-external-link ms-2" aria-hidden="true"></i></a></div>
                </div>
            <?php else: ?>
                <div class="install-feedback"><i class="ci-info" aria-hidden="true"></i><div><?= htmlSC($t('start_first')) ?></div></div><a class="btn install-primary" href="<?= base_url('/install') ?>"><?= htmlSC($t('start')) ?></a>
            <?php endif; ?>
        </section>
    </main>
    <footer class="install-footer"><span>FIREBALL CMS</span><span><?= htmlSC($t('footer')) ?></span></footer>
</div>
<script src="<?= htmlSC($asset('/assets/default/js/password-field.js')) ?>"></script>
<script src="<?= htmlSC($asset('/assets/default/js/install.js')) ?>" data-install-script data-installing-hint="<?= htmlSC($t('installing_hint')) ?>"></script>
</body>
</html>
