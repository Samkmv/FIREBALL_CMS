<?php
$formData = session()->get('form_data') ?: [];
$siteTitle = $formData['site_title'] ?? ($settings['site_title'] ?? SITE_NAME);
$siteDescription = $formData['site_description'] ?? ($settings['site_description'] ?? '');
$socialTelegram = $formData['social_telegram'] ?? ($settings['social_telegram'] ?? '');
$socialInstagram = $formData['social_instagram'] ?? ($settings['social_instagram'] ?? '');
$socialFacebook = $formData['social_facebook'] ?? ($settings['social_facebook'] ?? '');
$socialYoutube = $formData['social_youtube'] ?? ($settings['social_youtube'] ?? '');
$seoHomeTitle = $formData['seo_home_title'] ?? ($settings['seo_home_title'] ?? '');
$seoDefaultTitleSuffix = $formData['seo_default_title_suffix'] ?? ($settings['seo_default_title_suffix'] ?? '');
$seoMetaDescription = $formData['seo_meta_description'] ?? ($settings['seo_meta_description'] ?? '');
$seoMetaKeywords = $formData['seo_meta_keywords'] ?? ($settings['seo_meta_keywords'] ?? '');
$seoMetaAuthor = $formData['seo_meta_author'] ?? ($settings['seo_meta_author'] ?? '');
$seoRobots = $formData['seo_robots'] ?? ($settings['seo_robots'] ?? 'index,follow');
$seoOgImage = $formData['seo_og_image'] ?? ($settings['seo_og_image'] ?? '');
$seoTwitterCard = $formData['seo_twitter_card'] ?? ($settings['seo_twitter_card'] ?? 'summary_large_image');
?>

<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= print_translation('admin_settings_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_settings_subtitle') ?></p>
        </div>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_settings_site_title') ?></label>
                <input class="form-control <?= get_validation_class('site_title') ?>" type="text" name="site_title" value="<?= htmlSC($siteTitle) ?>" required>
                <?= get_errors('site_title') ?>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_settings_site_description') ?></label>
                <textarea class="form-control" name="site_description" rows="4"><?= htmlSC($siteDescription) ?></textarea>
            </div>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_settings_social_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_settings_social_subtitle') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_social_telegram') ?></label>
                            <input class="form-control <?= get_validation_class('social_telegram') ?>" type="url" name="social_telegram" value="<?= htmlSC($socialTelegram) ?>" placeholder="https://t.me/your_channel">
                            <?= get_errors('social_telegram') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_social_instagram') ?></label>
                            <input class="form-control <?= get_validation_class('social_instagram') ?>" type="url" name="social_instagram" value="<?= htmlSC($socialInstagram) ?>" placeholder="https://instagram.com/your_profile">
                            <?= get_errors('social_instagram') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_social_facebook') ?></label>
                            <input class="form-control <?= get_validation_class('social_facebook') ?>" type="url" name="social_facebook" value="<?= htmlSC($socialFacebook) ?>" placeholder="https://facebook.com/your_page">
                            <?= get_errors('social_facebook') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_social_youtube') ?></label>
                            <input class="form-control <?= get_validation_class('social_youtube') ?>" type="url" name="social_youtube" value="<?= htmlSC($socialYoutube) ?>" placeholder="https://youtube.com/@your_channel">
                            <?= get_errors('social_youtube') ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_settings_seo_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_settings_seo_subtitle') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_seo_home_title') ?></label>
                            <input class="form-control" type="text" name="seo_home_title" value="<?= htmlSC($seoHomeTitle) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_seo_home_title_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_seo_default_title_suffix') ?></label>
                            <input class="form-control" type="text" name="seo_default_title_suffix" value="<?= htmlSC($seoDefaultTitleSuffix) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_seo_default_title_suffix_hint') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_settings_seo_meta_description') ?></label>
                            <textarea class="form-control" name="seo_meta_description" rows="3"><?= htmlSC($seoMetaDescription) ?></textarea>
                            <div class="form-text"><?= print_translation('admin_settings_seo_meta_description_hint') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_settings_seo_meta_keywords') ?></label>
                            <textarea class="form-control" name="seo_meta_keywords" rows="2"><?= htmlSC($seoMetaKeywords) ?></textarea>
                            <div class="form-text"><?= print_translation('admin_settings_seo_meta_keywords_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_seo_meta_author') ?></label>
                            <input class="form-control" type="text" name="seo_meta_author" value="<?= htmlSC($seoMetaAuthor) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_seo_meta_author_hint') ?></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= print_translation('admin_settings_seo_robots') ?></label>
                            <select class="form-select <?= get_validation_class('seo_robots') ?>" name="seo_robots">
                                <option value="index,follow" <?= $seoRobots === 'index,follow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_index_follow') ?></option>
                                <option value="index,nofollow" <?= $seoRobots === 'index,nofollow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_index_nofollow') ?></option>
                                <option value="noindex,follow" <?= $seoRobots === 'noindex,follow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_noindex_follow') ?></option>
                                <option value="noindex,nofollow" <?= $seoRobots === 'noindex,nofollow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_noindex_nofollow') ?></option>
                            </select>
                            <?= get_errors('seo_robots') ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= print_translation('admin_settings_seo_twitter_card') ?></label>
                            <select class="form-select <?= get_validation_class('seo_twitter_card') ?>" name="seo_twitter_card">
                                <option value="summary" <?= $seoTwitterCard === 'summary' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_twitter_card_summary') ?></option>
                                <option value="summary_large_image" <?= $seoTwitterCard === 'summary_large_image' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_twitter_card_summary_large_image') ?></option>
                            </select>
                            <?= get_errors('seo_twitter_card') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_settings_seo_og_image') ?></label>
                            <div class="input-group">
                                <input class="form-control <?= get_validation_class('seo_og_image') ?>" type="text" id="settings_seo_og_image" name="seo_og_image" value="<?= htmlSC($seoOgImage) ?>" placeholder="/uploads/seo/cover.jpg">
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-file-manager-open
                                    data-file-manager-input="settings_seo_og_image"
                                    data-file-manager-dir="seo"
                                    data-file-manager-url="<?= base_href('/admin/files') ?>"
                                ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                            </div>
                            <div class="form-text"><?= print_translation('admin_settings_seo_og_image_hint') ?></div>
                            <?= get_errors('seo_og_image') ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
            </div>
        </div>
    </form>
</section>
