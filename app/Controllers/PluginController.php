<?php

namespace App\Controllers;

use Throwable;

final class PluginController extends BaseController
{
    public function index(): string
    {
        return view('admin/plugins', [
            'title' => \FBL\Language::get('admin_plugins_title'),
            'plugins' => plugin_manager()->all(),
        ]);
    }

    public function install(): void
    {
        $slug = (string)request()->post('slug', '');
        try {
            plugin_manager()->install($slug);
            session()->setFlash('success', \FBL\Language::get('admin_plugins_installed'));
        } catch (Throwable $exception) {
            log_error_details('Plugin admin install failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', str_replace(':error', $exception->getMessage(), \FBL\Language::get('admin_plugins_install_failed')));
        }

        response()->redirect(base_href('/admin/plugins'));
    }

    public function activate(): void
    {
        $slug = (string)request()->post('slug', '');
        try {
            plugin_manager()->activate($slug);
            session()->setFlash('success', \FBL\Language::get('admin_plugins_activated'));
        } catch (Throwable $exception) {
            log_error_details('Plugin admin activation failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', str_replace(':error', $exception->getMessage(), \FBL\Language::get('admin_plugins_activate_failed')));
        }

        response()->redirect(base_href('/admin/plugins'));
    }

    public function deactivate(): void
    {
        $slug = (string)request()->post('slug', '');
        try {
            plugin_manager()->deactivate($slug);
            session()->setFlash('success', \FBL\Language::get('admin_plugins_deactivated'));
        } catch (Throwable $exception) {
            log_error_details('Plugin admin deactivation failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', str_replace(':error', $exception->getMessage(), \FBL\Language::get('admin_plugins_deactivate_failed')));
        }

        response()->redirect(base_href('/admin/plugins'));
    }

}
