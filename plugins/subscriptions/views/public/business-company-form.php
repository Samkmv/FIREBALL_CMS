    <form id="details" method="post" enctype="multipart/form-data" class="profile-panel business-panel">
        <?= get_csrf_field() ?><input type="hidden" name="action" value="page">
        <h2 class="h4 mb-4"><?= $t('details') ?></h2>
        <div class="row g-3">
        <?php foreach (['name'=>190, 'address'=>255, 'phone'=>50, 'email'=>190, 'website'=>500, 'hours'=>255] as $key=>$length): ?>
            <div class="<?= in_array($key,['name','address']) ? 'col-12' : 'col-md-6' ?>"><label class="form-label" for="business-<?= $key ?>"><?= $t($key) ?></label><input id="business-<?= $key ?>" class="form-control" name="<?= $key ?>" maxlength="<?= $length ?>" type="<?= $key === 'email' ? 'email' : ($key === 'website' ? 'url' : 'text') ?>" value="<?= $v($key) ?>" <?= $key === 'name' ? 'required' : '' ?>></div>
        <?php if ($key === 'name'):
            $originalName = (string)($page['name'] ?? '');
            $originalSlug = (string)($page['slug'] ?? '');
            $previewName = (string)($pageValues['name'] ?? '');
            $previewSlug = $previewName === $originalName && $originalSlug !== '' ? $originalSlug : \Fireball\Subscriptions\Repositories\BusinessRepository::slugFromName($previewName);
        ?>
            <div class="col-12"><label class="form-label" for="business-slug"><?= $t('slug') ?></label>
                <input id="business-slug" class="form-control" readonly value="<?= htmlSC($previewSlug) ?>" data-original-name="<?= htmlSC($originalName) ?>" data-original-slug="<?= htmlSC($originalSlug) ?>">
                <div class="form-text"><?= $t('slug_hint') ?><br><span data-business-public-address data-base="<?= htmlSC(base_href('/business/')) ?>"><?= htmlSC(base_href('/business/' . $previewSlug)) ?></span></div>
            </div>
        <?php endif; endforeach; ?>
        <div class="col-12"><label class="form-label" for="business-description"><?= $t('description') ?></label><textarea id="business-description" class="form-control" name="description" rows="5" maxlength="10000"><?= $v('description') ?></textarea></div>
        <?php foreach (['avatar','cover'] as $key): ?><div class="col-md-6"><label class="form-label" for="business-<?= $key ?>"><?= $t($key) ?></label>
            <?php if (!empty($page[$key])): ?><img class="business-image-preview d-block mb-2" src="<?= htmlSC(base_href('/' . ltrim($page[$key], '/'))) ?>" alt="<?= $t($key) ?>"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="remove_<?= $key ?>" value="1"><span><?= $t('remove_image') ?></span></label><?php endif; ?>
            <input id="business-<?= $key ?>" class="form-control" type="file" name="<?= $key ?>" accept="image/jpeg,image/png,image/webp"><div class="form-text"><?= $t('image_hint') ?></div></div><?php endforeach; ?>
        <div class="col-12"><h3 class="h5 mt-3"><?= $t('socials') ?></h3></div>
        <?php foreach (\Fireball\Subscriptions\Repositories\BusinessRepository::SOCIALS as $key): ?><div class="col-md-6"><label class="form-label" for="social-<?= $key ?>"><?= htmlSC(ucfirst($key)) ?></label><input id="social-<?= $key ?>" class="form-control" type="url" name="socials[<?= $key ?>]" maxlength="500" value="<?= htmlSC((string)($socials[$key] ?? '')) ?>" placeholder="https://"></div><?php endforeach; ?>
        <input type="hidden" name="camera_title" value="<?= $v('camera_title') ?>"><input type="hidden" name="show_camera" value="<?= !isset($pageValues['show_camera']) || !empty($pageValues['show_camera']) ? 1 : 0 ?>">
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" <?= !empty($pageValues['is_published']) ? 'checked' : '' ?>><span><?= $t('publish') ?></span></label></div>
        </div><button class="btn btn-outline-secondary rounded-pill mt-4"><?= $t('save') ?></button>
    </form>
