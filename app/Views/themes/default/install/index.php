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
?>
<!doctype html>
<html lang="<?= htmlSC($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlSC($t('title')) ?> — FIREBALL CMS</title>
    <link rel="stylesheet" href="<?= base_url('/assets/default/bootstrap/css/bootstrap.min.css') ?>">
    <style>
        body { min-height: 100vh; background: radial-gradient(circle at top left, #e9f5ef, transparent 34rem), #f6f7f9; }
        .install-shell { max-width: 980px; margin: 0 auto; padding: 48px 16px; }
        .install-card { background: #fff; border: 1px solid rgba(17, 24, 39, .08); border-radius: 28px; box-shadow: 0 24px 80px rgba(17, 24, 39, .08); }
        .install-step { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #e9f5ef; color: #1f5c4f; font-weight: 700; }
        code { color: #b42318; }
    </style>
</head>
<body>
<div class="install-shell">
    <div class="mb-4">
        <div class="text-uppercase text-muted small fw-semibold mb-2">FIREBALL CMS</div>
        <h1 class="display-6 fw-bold mb-2"><?= htmlSC($t('title')) ?></h1>
        <p class="text-muted mb-0"><?= htmlSC($t('subtitle')) ?></p>
    </div>

    <?php if (!empty($result['message'])): ?>
        <div class="alert <?= ($result['status'] ?? '') === 'error' ? 'alert-danger' : 'alert-success' ?> rounded-4">
            <?= htmlSC((string)$result['message']) ?>
            <?php if (!empty($result['tables'])): ?>
                <div class="small mt-2"><?= htmlSC($t('tables')) ?>: <?= htmlSC(implode(', ', array_slice((array)$result['tables'], 0, 12))) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="install-card p-4 p-md-5">
        <?php if ($step === 'language'): ?>
            <div class="d-flex align-items-center gap-3 mb-4"><span class="install-step">1</span><h2 class="h4 mb-0"><?= htmlSC($t('language')) ?></h2></div>
            <form method="post" action="<?= base_url('/install') ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="step" value="language">
                <div class="row g-3 mb-4">
                    <?php foreach ((array)$languages as $code => $language): ?>
                        <div class="col-sm-6 col-lg-3">
                            <label class="border rounded-4 p-3 d-block h-100">
                                <input class="form-check-input me-2" type="radio" name="locale" value="<?= htmlSC($code) ?>" <?= $code === $locale ? 'checked' : '' ?>>
                                <?= htmlSC((string)($language['title'] ?? $code)) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-dark rounded-pill px-4" type="submit"><?= htmlSC($t('continue')) ?></button>
            </form>
        <?php elseif ($step === 'requirements'): ?>
            <div class="d-flex align-items-center gap-3 mb-4"><span class="install-step">2</span><h2 class="h4 mb-0"><?= htmlSC($t('requirements')) ?></h2></div>
            <div class="list-group mb-4">
                <?php foreach ((array)$requirements as $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlSC((string)$item['label']) ?></span>
                        <span class="badge rounded-pill <?= !empty($item['ok']) ? 'text-bg-success' : 'text-bg-danger' ?>"><?= !empty($item['ok']) ? 'OK' : htmlSC($t('error')) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= base_url('/install') ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="step" value="requirements">
                <button class="btn btn-dark rounded-pill px-4" type="submit" <?= empty($requirements_pass) ? 'disabled' : '' ?>><?= htmlSC($t('continue')) ?></button>
            </form>
        <?php elseif ($step === 'database'): ?>
            <div class="d-flex align-items-center gap-3 mb-4"><span class="install-step">3</span><h2 class="h4 mb-0"><?= htmlSC($t('database')) ?></h2></div>
            <form method="post" action="<?= base_url('/install') ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="step" value="database">
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('db_type')) ?></label><input class="form-control" value="MySQL" disabled></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('host')) ?></label><input class="form-control" name="db_host" value="<?= htmlSC((string)($db['host'] ?? 'localhost')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('port')) ?></label><input class="form-control" name="db_port" value="<?= htmlSC((string)($db['port'] ?? '3306')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('db_name')) ?></label><input class="form-control" name="db_name" value="<?= htmlSC((string)($db['database'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('db_user')) ?></label><input class="form-control" name="db_user" value="<?= htmlSC((string)($db['username'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('password')) ?></label><input class="form-control" type="password" name="db_password" value="<?= htmlSC((string)($db['password'] ?? '')) ?>"></div>
                    <div class="col-md-6">
                        <label class="form-label"><?= htmlSC($t('prefix')) ?></label>
                        <input class="form-control" name="db_prefix" value="<?= htmlSC((string)($db['prefix'] ?? '')) ?>" placeholder="<?= htmlSC($t('prefix_placeholder')) ?>">
                        <div class="form-text"><?= htmlSC($t('prefix_help')) ?></div>
                    </div>
                </div>
                <button class="btn btn-dark rounded-pill px-4" type="submit"><?= htmlSC($t('test_connection')) ?></button>
            </form>
        <?php elseif ($step === 'site'): ?>
            <div class="d-flex align-items-center gap-3 mb-4"><span class="install-step">4</span><h2 class="h4 mb-0"><?= htmlSC($t('site')) ?></h2></div>
            <?php if (!empty($dbTables)): ?><div class="alert alert-warning rounded-4"><?= htmlSC($t('existing_tables')) ?></div><?php endif; ?>
            <form method="post" action="<?= base_url('/install') ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="step" value="site">
                <div class="mb-3"><label class="form-label"><?= htmlSC($t('site_name')) ?></label><input class="form-control" name="site_name" value="<?= htmlSC((string)($site['name'] ?? 'FIREBALL CMS')) ?>" required></div>
                <div class="mb-3"><label class="form-label"><?= htmlSC($t('site_url')) ?></label><input class="form-control" name="site_url" value="<?= htmlSC((string)($site['url'] ?? $defaultSiteUrl)) ?>" required></div>
                <div class="mb-4"><label class="form-label"><?= htmlSC($t('timezone')) ?></label><select class="form-select" name="timezone"><?php foreach ((array)$timezones as $tz): ?><option value="<?= htmlSC($tz) ?>" <?= ($site['timezone'] ?? APP_TIMEZONE) === $tz ? 'selected' : '' ?>><?= htmlSC($tz) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-dark rounded-pill px-4" type="submit"><?= htmlSC($t('continue')) ?></button>
            </form>
        <?php elseif ($step === 'admin'): ?>
            <div class="d-flex align-items-center gap-3 mb-4"><span class="install-step">5</span><h2 class="h4 mb-0"><?= htmlSC($t('admin')) ?></h2></div>
            <form method="post" action="<?= base_url('/install') ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="step" value="admin">
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('login')) ?></label><input class="form-control" name="admin_login" value="<?= htmlSC((string)($admin['login'] ?? 'creator')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('email')) ?></label><input class="form-control" type="email" name="admin_email" value="<?= htmlSC((string)($admin['email'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('password')) ?></label><input class="form-control" type="password" name="admin_password" required></div>
                    <div class="col-md-6"><label class="form-label"><?= htmlSC($t('password_confirm')) ?></label><input class="form-control" type="password" name="admin_password_confirmation" required></div>
                </div>
                <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="install_demo" value="1"> <span class="form-check-label"><?= htmlSC($t('install_demo')) ?></span></label>
                <?php if (!empty($dbTables) || !empty($result['requires_confirmation'])): ?>
                    <label class="form-check mb-4"><input class="form-check-input" type="checkbox" name="allow_existing" value="1" required> <span class="form-check-label"><?= htmlSC($t('allow_existing')) ?></span></label>
                <?php endif; ?>
                <button class="btn btn-danger rounded-pill px-4" type="submit"><?= htmlSC($t('install')) ?></button>
            </form>
        <?php elseif ($step === 'finish' && ($result['status'] ?? '') === 'success'): ?>
            <div class="text-center py-4">
                <div class="display-6 fw-bold mb-3"><?= htmlSC($t('success')) ?></div>
                <p class="text-muted mb-4"><?= htmlSC($t('version')) ?>: <?= htmlSC((string)($result['version'] ?? '')) ?> · URL: <?= htmlSC((string)($result['site_url'] ?? '')) ?> · <?= htmlSC($t('login')) ?>: <?= htmlSC((string)($result['login'] ?? '')) ?></p>
                <div class="d-flex justify-content-center flex-wrap gap-2">
                    <a class="btn btn-dark rounded-pill px-4" href="<?= base_url('/admin') ?>"><?= htmlSC($t('open_admin')) ?></a>
                    <a class="btn btn-outline-secondary rounded-pill px-4" href="<?= base_url('/') ?>"><?= htmlSC($t('open_site')) ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info rounded-4"><?= htmlSC($t('start_first')) ?></div>
            <a class="btn btn-dark rounded-pill" href="<?= base_url('/install') ?>"><?= htmlSC($t('start')) ?></a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
