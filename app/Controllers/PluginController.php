<?php

namespace App\Controllers;

use Throwable;

final class PluginController extends BaseController
{
    public function index(): string
    {
        return view('admin/plugins', [
            'title' => 'Плагины',
            'plugins' => plugin_manager()->all(),
        ]);
    }

    public function install(): void
    {
        $slug = (string)request()->post('slug', '');
        try {
            plugin_manager()->install($slug);
            session()->setFlash('success', 'Плагин установлен.');
        } catch (Throwable $exception) {
            log_error_details('Plugin admin install failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', 'Не удалось установить плагин: ' . $exception->getMessage());
        }

        response()->redirect(base_href('/admin/plugins'));
    }

    public function activate(): void
    {
        $slug = (string)request()->post('slug', '');
        try {
            plugin_manager()->activate($slug);
            session()->setFlash('success', 'Плагин активирован.');
        } catch (Throwable $exception) {
            log_error_details('Plugin admin activation failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', 'Не удалось активировать плагин: ' . $exception->getMessage());
        }

        response()->redirect(base_href('/admin/plugins'));
    }

    public function deactivate(): void
    {
        $slug = (string)request()->post('slug', '');
        try {
            plugin_manager()->deactivate($slug);
            session()->setFlash('success', 'Плагин деактивирован.');
        } catch (Throwable $exception) {
            log_error_details('Plugin admin deactivation failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', 'Не удалось деактивировать плагин: ' . $exception->getMessage());
        }

        response()->redirect(base_href('/admin/plugins'));
    }

}
