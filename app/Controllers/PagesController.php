<?php

namespace App\Controllers;

use App\Models\Page;

/**
 * Serves public CMS pages by slug.
 */
class PagesController extends BaseController
{

    protected Page $pages;

    public function __construct()
    {
        parent::__construct();
        $this->pages = new Page();
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

        return view('pages/show', [
            'title' => $page['title'],
            'page' => $page,
            'seo_title' => $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
            'seo_description' => $page['meta_description'],
            'seo_canonical' => base_href('/' . $page['slug']),
        ]);
    }

}
