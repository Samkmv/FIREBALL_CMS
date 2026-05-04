<?php
$formData = session()->get('form_data') ?: [];
$siteTitle = $formData['site_title'] ?? ($settings['site_title'] ?? SITE_NAME);
$siteDescription = $formData['site_description'] ?? ($settings['site_description'] ?? '');
$adminSessionLifetimeHours = $formData['admin_session_lifetime_hours'] ?? ($settings['admin_session_lifetime_hours'] ?? '12');
$socialTelegram = $formData['social_telegram'] ?? ($settings['social_telegram'] ?? '');
$socialInstagram = $formData['social_instagram'] ?? ($settings['social_instagram'] ?? '');
$socialFacebook = $formData['social_facebook'] ?? ($settings['social_facebook'] ?? '');
$socialYoutube = $formData['social_youtube'] ?? ($settings['social_youtube'] ?? '');
$contactsPageHeading = $formData['contacts_page_heading'] ?? ($settings['contacts_page_heading'] ?? '');
$contactsPageSubheading = $formData['contacts_page_subheading'] ?? ($settings['contacts_page_subheading'] ?? '');
$contactsPageImage = $formData['contacts_page_image'] ?? ($settings['contacts_page_image'] ?? '');
$contactsPhoneCustomers = $formData['contacts_phone_customers'] ?? ($settings['contacts_phone_customers'] ?? '');
$contactsPhoneFranchise = $formData['contacts_phone_franchise'] ?? ($settings['contacts_phone_franchise'] ?? '');
$contactsEmailCustomers = $formData['contacts_email_customers'] ?? ($settings['contacts_email_customers'] ?? '');
$contactsEmailFranchise = $formData['contacts_email_franchise'] ?? ($settings['contacts_email_franchise'] ?? '');
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
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_settings_heading'),
    'subtitle' => return_translation('admin_settings_subtitle'),
    'actions' => '',
]) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= print_translation('admin_settings_site_title') ?></label>
                <input class="form-control <?= get_validation_class('site_title') ?>" type="text" name="site_title" value="<?= htmlSC($siteTitle) ?>" required>
                <?= get_errors('site_title') ?>
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
                            <label class="form-label"><?= print_translation('admin_settings_contacts_phone_franchise') ?></label>
                            <input class="form-control" type="text" name="contacts_phone_franchise" value="<?= htmlSC($contactsPhoneFranchise) ?>" placeholder="+1 50 537 53 000">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_phone_franchise_hint') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_email_customers') ?></label>
                            <input class="form-control <?= get_validation_class('contacts_email_customers') ?>" type="email" name="contacts_email_customers" value="<?= htmlSC($contactsEmailCustomers) ?>" placeholder="info@example.com">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_email_customers_hint') ?></div>
                            <?= get_errors('contacts_email_customers') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= print_translation('admin_settings_contacts_email_franchise') ?></label>
                            <input class="form-control <?= get_validation_class('contacts_email_franchise') ?>" type="email" name="contacts_email_franchise" value="<?= htmlSC($contactsEmailFranchise) ?>" placeholder="franchise@example.com">
                            <div class="form-text"><?= print_translation('admin_settings_contacts_email_franchise_hint') ?></div>
                            <?= get_errors('contacts_email_franchise') ?>
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
<?= view()->renderPartial('admin/shell_close') ?>
