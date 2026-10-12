<?php

namespace App\Controllers;

/** Read-only catalogue of the shared admin components. */
class AdminUiController extends BaseController
{
    public function index()
    {
        return view('admin/ui_kit', [
            'title' => return_translation('admin_ui_kit_title'),
            'styles' => [
                asset_versioned_url(base_url('/assets/default/css/admin-ui-kit.css'), WWW . '/assets/default/css/admin-ui-kit.css'),
            ],
            'footer_scripts' => [
                asset_versioned_url(base_url('/assets/default/js/admin-ui-kit.js'), WWW . '/assets/default/js/admin-ui-kit.js'),
            ],
        ]);
    }
}
