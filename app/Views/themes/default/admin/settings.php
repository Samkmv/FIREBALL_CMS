<?php
$formData = session()->get('form_data') ?: [];
$siteTitle = $formData['site_title'] ?? ($settings['site_title'] ?? SITE_NAME);
$siteDescription = $formData['site_description'] ?? ($settings['site_description'] ?? '');
$siteFavicon = $formData['site_favicon'] ?? ($settings['site_favicon'] ?? '');
$defaultLocale = \FBL\Localization::normalizeLocale((string)($formData['default_locale'] ?? ($settings['default_locale'] ?? DEFAULT_LOCALE))) ?: \FBL\Localization::siteLocale();
$adminSessionLifetimeHours = $formData['admin_session_lifetime_hours'] ?? ($settings['admin_session_lifetime_hours'] ?? '12');
$socialNetworkOptions = site_social_network_options();
$storedSocialLinks = $formData['social_links'] ?? ($settings['social_links'] ?? '');
$socialLinks = [];
if (is_string($storedSocialLinks) && trim($storedSocialLinks) !== '') {
    $decodedSocialLinks = json_decode($storedSocialLinks, true);
    $socialLinks = is_array($decodedSocialLinks) ? $decodedSocialLinks : [];
}
if (empty($socialLinks)) {
    foreach (['telegram', 'instagram', 'facebook', 'youtube'] as $legacyNetwork) {
        $legacyUrl = $formData['social_' . $legacyNetwork] ?? ($settings['social_' . $legacyNetwork] ?? '');
        if (trim((string)$legacyUrl) !== '') {
            $socialLinks[] = ['network' => $legacyNetwork, 'url' => $legacyUrl];
        }
    }
}
if (empty($socialLinks)) {
    $socialLinks[] = ['network' => 'telegram', 'url' => ''];
}
$contactsPageHeading = $formData['contacts_page_heading'] ?? ($settings['contacts_page_heading'] ?? '');
$contactsPageSubheading = $formData['contacts_page_subheading'] ?? ($settings['contacts_page_subheading'] ?? '');
$contactsPageImage = $formData['contacts_page_image'] ?? ($settings['contacts_page_image'] ?? '');
$contactsPhoneCustomers = $formData['contacts_phone_customers'] ?? ($settings['contacts_phone_customers'] ?? '');
$contactsPhoneSupport = $formData['contacts_phone_support'] ?? (trim((string)($settings['contacts_phone_support'] ?? '')) !== '' ? $settings['contacts_phone_support'] : ($settings['contacts_phone_franchise'] ?? ''));
$contactsEmailCustomers = $formData['contacts_email_customers'] ?? ($settings['contacts_email_customers'] ?? '');
$contactsEmailSupport = $formData['contacts_email_support'] ?? (trim((string)($settings['contacts_email_support'] ?? '')) !== '' ? $settings['contacts_email_support'] : ($settings['contacts_email_franchise'] ?? ''));
$contactsLocationCity = $formData['contacts_location_city'] ?? ($settings['contacts_location_city'] ?? '');
$contactsLocationAddress = $formData['contacts_location_address'] ?? ($settings['contacts_location_address'] ?? '');
$contactsHoursWeekdays = $formData['contacts_hours_weekdays'] ?? ($settings['contacts_hours_weekdays'] ?? '');
$contactsHoursWeekends = $formData['contacts_hours_weekends'] ?? ($settings['contacts_hours_weekends'] ?? '');
$contactsSupportTitle = $formData['contacts_support_title'] ?? ($settings['contacts_support_title'] ?? '');
$contactsSupportText = $formData['contacts_support_text'] ?? ($settings['contacts_support_text'] ?? '');
$seoHomeTitle = $formData['seo_home_title'] ?? ($settings['seo_home_title'] ?? '');
$seoDefaultTitleSuffix = $formData['seo_default_title_suffix'] ?? ($settings['seo_default_title_suffix'] ?? '');
$seoMetaDescription = $formData['seo_meta_description'] ?? ($settings['seo_meta_description'] ?? '');
$seoMetaKeywords = $formData['seo_meta_keywords'] ?? ($settings['seo_meta_keywords'] ?? '');
$seoMetaAuthor = $formData['seo_meta_author'] ?? ($settings['seo_meta_author'] ?? '');
$seoRobots = $formData['seo_robots'] ?? ($settings['seo_robots'] ?? 'index,follow');
$seoOgImage = $formData['seo_og_image'] ?? ($settings['seo_og_image'] ?? '');
$seoTwitterCard = $formData['seo_twitter_card'] ?? ($settings['seo_twitter_card'] ?? 'summary_large_image');
$homepageType = $formData['homepage_type'] ?? ($settings['homepage_type'] ?? 'default');
if (!in_array($homepageType, ['default', 'page', 'posts'], true)) {
    $homepageType = 'default';
}
$homepagePageId = (int)($formData['homepage_page_id'] ?? ($settings['homepage_page_id'] ?? 0));
$postsPerPage = (int)($formData['posts_per_page'] ?? ($settings['posts_per_page'] ?? 10));
if ($postsPerPage < 1) {
    $postsPerPage = 10;
}
$publishedPages = (array)($published_pages ?? []);
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_settings_heading'),
    'subtitle' => return_translation('admin_settings_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/settings_tabs', ['active' => 'general']) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_settings_site_title') ?></label>
                <input class="form-control <?= get_validation_class('site_title') ?>" type="text" name="site_title" value="<?= htmlSC($siteTitle) ?>" required>
                <?= get_errors('site_title') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_settings_default_locale') ?></label>
                <select class="form-select <?= get_validation_class('default_locale') ?>" name="default_locale" required>
                    <?php foreach (LANGS as $localeCode => $locale): ?>
                        <option value="<?= htmlSC((string)$localeCode) ?>" <?= $defaultLocale === (string)$localeCode ? 'selected' : '' ?>>
                            <?= htmlSC((string)($locale['title'] ?? $localeCode)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= print_translation('admin_settings_default_locale_hint') ?></div>
                <?= get_errors('default_locale') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_settings_admin_session_lifetime_hours') ?></label>
                <input
                    class="form-control <?= get_validation_class('admin_session_lifetime_hours') ?>"
                    type="number"
                    name="admin_session_lifetime_hours"
                    value="<?= htmlSC((string)$adminSessionLifetimeHours) ?>"
                    min="1"
                    max="720"
                    step="1"
                    required
                >
                <div class="form-text"><?= print_translation('admin_settings_admin_session_lifetime_hours_hint') ?></div>
                <?= get_errors('admin_session_lifetime_hours') ?>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_settings_site_description') ?></label>
                <textarea class="form-control" name="site_description" rows="4"><?= htmlSC($siteDescription) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label"><?= print_translation('admin_settings_site_favicon') ?></label>
                <div class="input-group">
                    <input class="form-control <?= get_validation_class('site_favicon') ?>" type="text" id="settings_site_favicon" name="site_favicon" value="<?= htmlSC($siteFavicon) ?>" placeholder="/uploads/branding/favicon.png" data-file-preview-image="#settings_site_favicon_preview_image" data-file-preview-text="#settings_site_favicon_preview_text">
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        data-file-manager-open
                        data-file-manager-input="settings_site_favicon"
                        data-file-manager-dir="branding"
                        data-file-manager-url="<?= base_href('/admin/files') ?>"
                    ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                </div>
                <div class="form-text"><?= print_translation('admin_settings_site_favicon_hint') ?></div>
                <?= get_errors('site_favicon') ?>
                <div class="d-flex align-items-center gap-3 mt-3 <?= $siteFavicon === '' ? 'd-none' : '' ?>" id="settings_site_favicon_preview_wrap">
                    <span class="border rounded-3 d-inline-flex align-items-center justify-content-center bg-body-tertiary overflow-hidden" style="width: 2.75rem; height: 2.75rem;">
                        <img id="settings_site_favicon_preview_image" src="<?= htmlSC($siteFavicon !== '' ? get_image($siteFavicon) : '') ?>" alt="favicon preview" style="width: 100%; height: 100%; object-fit: contain;">
                    </span>
                    <code class="small text-break" id="settings_site_favicon_preview_text"><?= htmlSC($siteFavicon) ?></code>
                </div>
            </div>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4" data-homepage-settings>
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_settings_homepage_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_settings_homepage_subtitle') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_homepage_type') ?></label>
                            <select class="form-select" name="homepage_type" data-homepage-type>
                                <option value="default" <?= $homepageType === 'default' ? 'selected' : '' ?>><?= print_translation('admin_settings_homepage_type_default') ?></option>
                                <option value="page" <?= $homepageType === 'page' ? 'selected' : '' ?>><?= print_translation('admin_settings_homepage_type_page') ?></option>
                                <option value="posts" <?= $homepageType === 'posts' ? 'selected' : '' ?>><?= print_translation('admin_settings_homepage_type_posts') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6 <?= $homepageType === 'page' ? '' : 'd-none' ?>" data-homepage-page-field>
                            <label class="form-label"><?= print_translation('admin_settings_homepage_page') ?></label>
                            <select class="form-select" name="homepage_page_id">
                                <option value="0"><?= print_translation('admin_settings_homepage_page_placeholder') ?></option>
                                <?php foreach ($publishedPages as $page): ?>
                                    <option value="<?= (int)$page['id'] ?>" <?= $homepagePageId === (int)$page['id'] ? 'selected' : '' ?>>
                                        <?= htmlSC((string)$page['label']) ?> (/<?= htmlSC((string)$page['slug']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($publishedPages)): ?>
                                <div class="form-text"><?= print_translation('admin_settings_homepage_no_pages') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 <?= $homepageType === 'posts' ? '' : 'd-none' ?>" data-homepage-posts-field>
                            <label class="form-label"><?= print_translation('admin_settings_homepage_posts_per_page') ?></label>
                            <input class="form-control" type="number" name="posts_per_page" value="<?= (int)$postsPerPage ?>" min="1" max="100" step="1">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_settings_social_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_settings_social_subtitle') ?></p>
                    </div>
                    <div class="d-grid gap-3" data-social-settings-list>
                        <?php foreach ($socialLinks as $link): ?>
                            <?php
                            $selectedNetwork = (string)($link['network'] ?? 'telegram');
                            if (!isset($socialNetworkOptions[$selectedNetwork])) {
                                $selectedNetwork = 'telegram';
                            }
                            $selectedUrl = (string)($link['url'] ?? '');
                            ?>
                            <div class="row g-2 align-items-end" data-social-settings-row>
                                <div class="col-md-4">
                                    <label class="form-label"><?= print_translation('admin_settings_social_network') ?></label>
                                    <select class="form-select" name="social_networks[]" data-social-settings-network>
                                        <?php foreach ($socialNetworkOptions as $network => $option): ?>
                                            <option value="<?= htmlSC($network) ?>" data-placeholder="<?= htmlSC((string)$option['placeholder']) ?>" <?= $selectedNetwork === $network ? 'selected' : '' ?>>
                                                <?= htmlSC((string)$option['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label"><?= print_translation('admin_settings_social_url') ?></label>
                                    <input class="form-control <?= get_validation_class('social_links') ?>" type="text" name="social_urls[]" value="<?= htmlSC($selectedUrl) ?>" placeholder="<?= htmlSC((string)$socialNetworkOptions[$selectedNetwork]['placeholder']) ?>" data-social-settings-url>
                                </div>
                                <div class="col-md-1 d-grid">
                                    <button class="btn btn-outline-danger btn-icon" type="button" data-social-settings-remove aria-label="<?= htmlSC(return_translation('admin_settings_social_remove')) ?>">
                                        <i class="ci-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?= get_errors('social_links') ?>
                    <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2 mt-3" type="button" data-social-settings-add>
                        <i class="ci-plus"></i><?= print_translation('admin_settings_social_add') ?>
                    </button>
                </div>
            </div>
            <div class="col-12 pt-2">
                <div class="border rounded-4 p-3 p-md-4">
                    <div class="mb-3">
                        <h2 class="h5 mb-1"><?= print_translation('admin_settings_contacts_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_settings_contacts_subtitle') ?></p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_page_heading') ?></label>
                            <input class="form-control" type="text" name="contacts_page_heading" value="<?= htmlSC($contactsPageHeading) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_page_heading_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_page_subheading') ?></label>
                            <input class="form-control" type="text" name="contacts_page_subheading" value="<?= htmlSC($contactsPageSubheading) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_page_subheading_hint') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_page_image') ?></label>
                            <div class="input-group">
                                <input class="form-control <?= get_validation_class('contacts_page_image') ?>" type="text" id="settings_contacts_page_image" name="contacts_page_image" value="<?= htmlSC($contactsPageImage) ?>" placeholder="/uploads/contacts/cover.jpg">
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-file-manager-open
                                    data-file-manager-input="settings_contacts_page_image"
                                    data-file-manager-dir="posts"
                                    data-file-manager-url="<?= base_href('/admin/files') ?>"
                                ><i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?></button>
                            </div>
                            <div class="form-text"><?= print_translation('admin_settings_contacts_page_image_hint') ?></div>
                            <?= get_errors('contacts_page_image') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_phone_customers') ?></label>
                            <input class="form-control" type="text" name="contacts_phone_customers" value="<?= htmlSC($contactsPhoneCustomers) ?>" placeholder="+1 50 537 53 082">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_phone_customers_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_phone_support') ?></label>
                            <input class="form-control" type="text" name="contacts_phone_support" value="<?= htmlSC($contactsPhoneSupport) ?>" placeholder="+1 50 537 53 000">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_phone_support_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_email_customers') ?></label>
                            <input class="form-control <?= get_validation_class('contacts_email_customers') ?>" type="email" name="contacts_email_customers" value="<?= htmlSC($contactsEmailCustomers) ?>" placeholder="info@example.com">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_email_customers_hint') ?></div>
                            <?= get_errors('contacts_email_customers') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_email_support') ?></label>
                            <input class="form-control <?= get_validation_class('contacts_email_support') ?>" type="email" name="contacts_email_support" value="<?= htmlSC($contactsEmailSupport) ?>" placeholder="support@example.com">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_email_support_hint') ?></div>
                            <?= get_errors('contacts_email_support') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_location_city') ?></label>
                            <input class="form-control" type="text" name="contacts_location_city" value="<?= htmlSC($contactsLocationCity) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_location_city_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_location_address') ?></label>
                            <input class="form-control" type="text" name="contacts_location_address" value="<?= htmlSC($contactsLocationAddress) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_location_address_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_hours_weekdays') ?></label>
                            <input class="form-control" type="text" name="contacts_hours_weekdays" value="<?= htmlSC($contactsHoursWeekdays) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_hours_weekdays_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_hours_weekends') ?></label>
                            <input class="form-control" type="text" name="contacts_hours_weekends" value="<?= htmlSC($contactsHoursWeekends) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_hours_weekends_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_support_title') ?></label>
                            <input class="form-control" type="text" name="contacts_support_title" value="<?= htmlSC($contactsSupportTitle) ?>">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_support_title_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_support_text') ?></label>
                            <textarea class="form-control" name="contacts_support_text" rows="3"><?= htmlSC($contactsSupportText) ?></textarea>
                            <div class="form-text"><?= print_translation('admin_settings_contacts_support_text_hint') ?></div>
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
                            <select class="form-select <?= get_validation_class('seo_robots') ?>" name="seo_robots" data-select aria-label="<?= htmlSC(return_translation('admin_settings_seo_robots')) ?>">
                                <option value="index,follow" <?= $seoRobots === 'index,follow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_index_follow') ?></option>
                                <option value="index,nofollow" <?= $seoRobots === 'index,nofollow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_index_nofollow') ?></option>
                                <option value="noindex,follow" <?= $seoRobots === 'noindex,follow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_noindex_follow') ?></option>
                                <option value="noindex,nofollow" <?= $seoRobots === 'noindex,nofollow' ? 'selected' : '' ?>><?= print_translation('admin_settings_seo_robots_noindex_nofollow') ?></option>
                            </select>
                            <?= get_errors('seo_robots') ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= print_translation('admin_settings_seo_twitter_card') ?></label>
                            <select class="form-select <?= get_validation_class('seo_twitter_card') ?>" name="seo_twitter_card" data-select aria-label="<?= htmlSC(return_translation('admin_settings_seo_twitter_card')) ?>">
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
    <template id="socialSettingsRowTemplate">
        <div class="row g-2 align-items-end" data-social-settings-row>
            <div class="col-md-4">
                <label class="form-label"><?= print_translation('admin_settings_social_network') ?></label>
                <select class="form-select" name="social_networks[]" data-social-settings-network>
                    <?php foreach ($socialNetworkOptions as $network => $option): ?>
                        <option value="<?= htmlSC($network) ?>" data-placeholder="<?= htmlSC((string)$option['placeholder']) ?>">
                            <?= htmlSC((string)$option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label"><?= print_translation('admin_settings_social_url') ?></label>
                <input class="form-control" type="text" name="social_urls[]" value="" placeholder="<?= htmlSC((string)$socialNetworkOptions['telegram']['placeholder']) ?>" data-social-settings-url>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-outline-danger btn-icon" type="button" data-social-settings-remove aria-label="<?= htmlSC(return_translation('admin_settings_social_remove')) ?>">
                    <i class="ci-trash"></i>
                </button>
            </div>
        </div>
    </template>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const homepageSettings = document.querySelector('[data-homepage-settings]');
            if (homepageSettings) {
                const homepageType = homepageSettings.querySelector('[data-homepage-type]');
                const homepagePageField = homepageSettings.querySelector('[data-homepage-page-field]');
                const homepagePostsField = homepageSettings.querySelector('[data-homepage-posts-field]');
                const updateHomepageFields = function () {
                    const type = homepageType ? homepageType.value : 'default';
                    if (homepagePageField) {
                        homepagePageField.classList.toggle('d-none', type !== 'page');
                    }
                    if (homepagePostsField) {
                        homepagePostsField.classList.toggle('d-none', type !== 'posts');
                    }
                };

                if (homepageType) {
                    homepageType.addEventListener('change', updateHomepageFields);
                    updateHomepageFields();
                }
            }

            const list = document.querySelector('[data-social-settings-list]');
            const template = document.getElementById('socialSettingsRowTemplate');
            const addButton = document.querySelector('[data-social-settings-add]');

            function updatePlaceholder(row) {
                const select = row.querySelector('[data-social-settings-network]');
                const input = row.querySelector('[data-social-settings-url]');
                const option = select ? select.options[select.selectedIndex] : null;
                if (input && option) {
                    input.placeholder = option.getAttribute('data-placeholder') || '';
                }
            }

            if (list) {
                list.querySelectorAll('[data-social-settings-row]').forEach(updatePlaceholder);
                list.addEventListener('change', function (event) {
                    const row = event.target.closest('[data-social-settings-row]');
                    if (row && event.target.matches('[data-social-settings-network]')) {
                        updatePlaceholder(row);
                    }
                });
                list.addEventListener('click', function (event) {
                    const removeButton = event.target.closest('[data-social-settings-remove]');
                    if (!removeButton) {
                        return;
                    }
                    const rows = list.querySelectorAll('[data-social-settings-row]');
                    const row = removeButton.closest('[data-social-settings-row]');
                    if (rows.length > 1 && row) {
                        row.remove();
                    }
                });
            }

            if (addButton && list && template) {
                addButton.addEventListener('click', function () {
                    const fragment = template.content.cloneNode(true);
                    const row = fragment.querySelector('[data-social-settings-row]');
                    list.appendChild(fragment);
                    if (row) {
                        updatePlaceholder(row);
                    }
                });
            }
        });
    </script>
<?= view()->renderPartial('admin/shell_close') ?>
