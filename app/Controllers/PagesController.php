<?php

namespace App\Controllers;

use App\Models\Page;
use App\Models\SiteSetting;
use FBL\Theme;

/**
 * Serves public CMS pages by slug.
 */
class PagesController extends BaseController
{

    protected Page $pages;
    protected SiteSetting $siteSettings;

    public function __construct()
    {
        parent::__construct();
        $this->pages = new Page();
        $this->siteSettings = new SiteSetting();
    }

    /**
     * Shows a published page, or returns 404 for drafts and missing pages.
     */
    public function show()
    {
        $slug = trim((string)get_route_param('slug', ''));
        $page = $this->pages->findPublishedBySlug($slug);

        if (!$page) {
            abort();
        }

        if ($this->isAssignedHomepage($page)) {
            response()->setResponseCode(301);
            response()->redirect(base_href('/'));
        }

        return Theme::render('page', [
            'title' => $page['title'],
            'page' => $page,
            'seo_title' => $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
            'seo_description' => $page['meta_description'],
            'seo_canonical' => base_href('/' . $page['slug']),
        ]);
    }

    protected function isAssignedHomepage(array $page): bool
    {
        return $this->siteSettings->get('homepage_type', 'default') === 'page'
            && (int)$this->siteSettings->get('homepage_page_id', '0') === (int)($page['id'] ?? 0);
    }

}
