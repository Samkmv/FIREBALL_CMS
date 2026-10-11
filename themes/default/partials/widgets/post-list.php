<?php if ($posts): ?>
<h4 class="h6 pt-5 mb-0"><?= htmlSC($settings['title'] ?: ($legacy ? $context->translate('index_sidebar_trending', 'show_recent') : return_translation('widgets_latest'))) ?></h4>
<?php foreach ($posts as $index => $item): ?>
    <article class="hover-effect-scale position-relative d-flex align-items-center <?= $index < count($posts) - 1 ? 'border-bottom ' : '' ?>py-4">
        <div class="w-100 pe-3">
            <h3 class="h6 lh-base fs-sm mb-1">
                <a class="hover-effect-underline stretched-link" href="<?= htmlSC(base_href('/posts/' . $item['slug'])) ?>">
                    <?php if ($legacy && isset($item['subscription_access']) && empty($item['subscription_access']['allowed'])): ?><i class="ci-lock me-1" aria-hidden="true"></i><?php endif; ?>
                    <?= htmlSC($item['title']) ?>
                </a>
            </h3>
            <?php if ($legacy || $settings['date']): ?>
            <div class="text-body-tertiary fs-xs">
                <?= htmlSC(date('d.m.Y', strtotime($item['published_at']))) ?>
                <?php if ($legacy): ?><span class="mx-1">•</span> <?= htmlSC($context->translate('show_views', 'show_views')) ?> <?= (int)$item['views_count'] ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($legacy || $settings['thumbnails']): ?>
        <div class="ratio w-100" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)">
            <img src="<?= htmlSC($item['image_mobile'] ?? get_image($item['image'])) ?>" srcset="<?= htmlSC($item['image_srcset'] ?? '') ?>" sizes="86px" data-image-fallback="<?= htmlSC(base_url('/assets/img/no-image.png')) ?>" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=this.dataset.imageFallback;" class="rounded-2" width="86" height="64" alt="<?= htmlSC($item['title']) ?>" loading="lazy" decoding="async" style="object-fit: cover;">
        </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
<?php endif; ?>
