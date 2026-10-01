<?php
$t = static fn(string $key): string => htmlSC(FireballPluginSubscriptions::t('business_' . $key));
$businessUrl = static fn(array $business): string => htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($business)));
$imageUrl = static function (array $business): string {
    $image = $business['cover'] ?: $business['avatar'];
    return htmlSC($image ? base_href('/' . ltrim($image, '/')) : base_url('/assets/img/no-image.png'));
};
$fallbackImage = htmlSC(base_url('/assets/img/no-image.png'));
$excerpt = static function (string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    return htmlSC(mb_strlen($text) > 150 ? rtrim(mb_substr($text, 0, 147)) . '...' : $text);
};
$paginationMarkup = str_replace('class="pagination"', 'class="pagination justify-content-center"', (string)$pagination);
$top_businesses = array_filter($top_businesses, static fn(array $business): bool => (int)$business['review_count'] > 0);
?>
<nav class="container pt-3 my-3 my-md-4" aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= htmlSC(base_href('/')) ?>" class="d-flex align-items-center"><i class="ci-home fs-base me-2" aria-hidden="true"></i><?= $t('home') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $t('directory') ?></li>
    </ol>
</nav>
<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 pb-4 mb-2">
                <div><span class="badge text-bg-dark rounded-pill mb-3"><?= $t('directory_total') ?>: <?= (int)$total_businesses ?></span><h1 class="h3 mb-2"><?= $t('directory') ?></h1><p class="text-body-secondary mb-0"><?= $search !== '' ? $t('search_results') . ': ' . htmlSC($search) : $t('directory_subtitle') ?></p></div>
                <?php if ($search !== '' || $sort !== 'newest'): ?><a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/business')) ?>"><?= $t('clear_filters') ?></a><?php endif; ?>
            </div>
            <?php if ($businesses): ?>
                <div class="row row-cols-1 row-cols-sm-2 gy-5 pb-2 pb-sm-0">
                    <?php foreach ($businesses as $business): ?><article class="col">
                        <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="<?= $businessUrl($business) ?>" style="--cz-aspect-ratio: calc(305 / 416 * 100%)"><img src="<?= $imageUrl($business) ?>" data-image-fallback="<?= $fallbackImage ?>" onerror="this.onerror=null;this.src=this.dataset.imageFallback;" class="hover-effect-target w-100 h-100 object-fit-cover" width="416" height="305" alt="<?= htmlSC($business['name']) ?>" loading="lazy" decoding="async"></a>
                        <div class="pt-4">
                            <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1 fs-xs text-body-secondary">
                                <?php if ($business['address']): ?><span class="text-break"><i class="ci-map-pin me-1" aria-hidden="true"></i><?= htmlSC($business['address']) ?></span><?php endif; ?>
                                <?php if ((int)$business['review_count'] > 0): ?><span><i class="ci-star text-warning me-1" aria-hidden="true"></i><?= number_format((float)$business['average_rating'], 1) ?> / 5 · <?= $t('reviews') ?>: <?= (int)$business['review_count'] ?></span><?php else: ?><span><?= $t('no_ratings') ?></span><?php endif; ?>
                            </div>
                            <h3 class="h5 mb-2 text-break"><a class="hover-effect-underline" href="<?= $businessUrl($business) ?>"><?= htmlSC($business['name']) ?></a></h3>
                            <?php if ($business['description']): ?><p class="text-body-secondary fs-sm mb-0"><?= $excerpt($business['description']) ?></p><?php endif; ?>
                        </div>
                    </article><?php endforeach; ?>
                </div>
                <?php if ($total_businesses > PAGINATION_SETTINGS['perPage'] && $paginationMarkup !== ''): ?><hr class="mt-4 mt-sm-5"><?= $paginationMarkup ?><?php endif; ?>
            <?php else: ?><div class="border rounded-5 p-5 text-center"><h2 class="h5 mb-2"><?= $t($search !== '' ? 'directory_not_found' : 'directory_empty') ?></h2><p class="text-body-secondary mb-0"><?= $t($search !== '' ? 'directory_not_found_hint' : 'directory_empty_hint') ?></p></div><?php endif; ?>
        </div>
        <aside class="col-lg-4 col-xl-3 offset-xl-1" style="margin-top: -115px">
            <div class="offcanvas-lg offcanvas-end sticky-lg-top ps-lg-4 ps-xl-0" id="blogSidebar">
                <div class="d-none d-lg-block" style="height: 115px"></div>
                <div class="offcanvas-header py-3"><h5 class="offcanvas-title"><?= $t('directory_filters') ?></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#blogSidebar" aria-label="<?= $t('close') ?>"></button></div>
                <div class="offcanvas-body d-block pt-2 py-lg-0">
                    <h2 class="h6 mb-4"><?= $t('find_business') ?></h2>
                    <form method="get" action="<?= htmlSC(base_href('/business')) ?>">
                        <label class="form-label" for="business-directory-search"><?= $t('search') ?></label>
                        <input class="form-control mb-3" id="business-directory-search" type="search" name="q" value="<?= htmlSC($search) ?>" maxlength="190" placeholder="<?= $t('search_hint') ?>">
                        <label class="form-label" for="business-directory-sort"><?= $t('sort') ?></label>
                        <select class="form-select mb-3" id="business-directory-sort" name="sort"><?php foreach (['newest','rating','name'] as $option): ?><option value="<?= $option ?>" <?= $sort === $option ? 'selected' : '' ?>><?= $t('sort_' . $option) ?></option><?php endforeach; ?></select>
                        <button type="submit" class="btn btn-outline-secondary w-100"><?= $t('search') ?></button>
                    </form>
                    <?php if ($top_businesses): ?><h2 class="h6 pt-5 mb-0"><?= $t('top_businesses') ?></h2>
                        <?php foreach ($top_businesses as $index=>$business): ?><article class="hover-effect-scale position-relative d-flex align-items-center border-bottom py-4">
                            <div class="w-100 pe-3"><h3 class="h6 lh-base fs-sm mb-1 text-break"><a class="hover-effect-underline stretched-link" href="<?= $businessUrl($business) ?>"><?= htmlSC($business['name']) ?></a></h3><div class="text-body-tertiary fs-xs"><i class="ci-star text-warning me-1" aria-hidden="true"></i><?= number_format((float)$business['average_rating'],1) ?> / 5 · <?= $t('reviews') ?>: <?= (int)$business['review_count'] ?></div></div>
                            <div class="ratio w-100" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)"><img src="<?= $imageUrl($business) ?>" data-image-fallback="<?= $fallbackImage ?>" onerror="this.onerror=null;this.src=this.dataset.imageFallback;" class="rounded-2 object-fit-cover" width="86" height="64" alt="" loading="lazy" decoding="async"></div>
                        </article><?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</section>
<button type="button" class="fixed-bottom z-sticky w-100 btn btn-lg btn-dark border-0 border-top border-light border-opacity-10 rounded-0 pb-4 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#blogSidebar" aria-controls="blogSidebar" data-bs-theme="light"><i class="ci-sidebar fs-base me-2" aria-hidden="true"></i><?= $t('directory_filters') ?></button>
