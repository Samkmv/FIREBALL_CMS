<?php

namespace App\Controllers;

use App\Services\PluginUpdateService;
use Throwable;

final class PluginController extends BaseController
{
    public function index(): string
    {
        $plugins = plugin_manager()->all();
        try {
            $plugins = (new PluginUpdateService())->decoratePlugins($plugins);
        } catch (Throwable $exception) {
            log_error_details('Plugin update status loading failed', [], $exception);
        }

        return view('admin/plugins', [
            'title' => \FBL\Language::get('admin_plugins_title'),
            'plugins' => $plugins,
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

    public function checkUpdate(): void
    {
        $this->requirePluginUpdatePermission();
        $slug = (string)request()->post('slug', '');
        try {
            $result = (new PluginUpdateService())->check($slug);
            session()->setFlash(
                !empty($result['update_available']) ? 'info' : 'success',
                (string)($result['message'] ?? \FBL\Language::get('admin_plugin_updates_checked'))
            );
        } catch (Throwable $exception) {
            log_error_details('Plugin update check failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', $exception->getMessage());
        }

        $this->redirectToPlugin($slug);
    }

    public function checkAllUpdates(): void
    {
        $this->requirePluginUpdatePermission();
        try {
            $result = (new PluginUpdateService())->checkAll();
            $configured = (int)($result['configured'] ?? 0);
            $available = (int)($result['available'] ?? 0);
            $sourceOlder = (int)($result['source_older'] ?? 0);
            $failed = (int)($result['failed'] ?? 0);

            if ($configured === 0) {
                $message = \FBL\Language::get('admin_plugin_updates_all_none_configured');
                $flash = 'info';
            } elseif ($failed > 0) {
                $message = str_replace(
                    [':available', ':older', ':failed'],
                    [(string)$available, (string)$sourceOlder, (string)$failed],
                    \FBL\Language::get('admin_plugin_updates_all_partial')
                );
                $flash = $failed === $configured ? 'error' : 'warning';
            } elseif ($available > 0 && $sourceOlder > 0) {
                $message = str_replace(
                    [':available', ':older'],
                    [(string)$available, (string)$sourceOlder],
                    \FBL\Language::get('admin_plugin_updates_all_mixed')
                );
                $flash = 'warning';
            } elseif ($available > 0) {
                $message = str_replace(
                    ':count',
                    (string)$available,
                    \FBL\Language::get('admin_plugin_updates_all_available')
                );
                $flash = 'info';
            } elseif ($sourceOlder > 0) {
                $message = str_replace(
                    ':count',
                    (string)$sourceOlder,
                    \FBL\Language::get('admin_plugin_updates_all_source_older')
                );
                $flash = 'warning';
            } else {
                $message = \FBL\Language::get('admin_plugin_updates_all_current');
                $flash = 'success';
            }

            session()->setFlash($flash, $message);
        } catch (Throwable $exception) {
            log_error_details('Plugin updates check all failed', [], $exception);
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect(base_href('/admin/plugins'));
    }

    public function update(): void
    {
        $this->requirePluginUpdatePermission();
        $slug = (string)request()->post('slug', '');
        try {
            $result = (new PluginUpdateService())->update($slug, (array)get_user());
            session()->setFlash(
                ($result['status'] ?? '') === 'source_older'
                    ? 'warning'
                    : (($result['status'] ?? '') === 'current' ? 'info' : 'success'),
                (string)($result['message'] ?? \FBL\Language::get('admin_plugin_updates_success'))
            );
        } catch (Throwable $exception) {
            log_error_details('Plugin update failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', $exception->getMessage());
        }

        $this->redirectToPlugin($slug);
    }

    private function requirePluginUpdatePermission(): void
    {
        if (!\FBL\Auth::isAdmin()) {
            abort(\FBL\Language::get('error_403_message'), 403);
        }
    }

    private function redirectToPlugin(string $slug): never
    {
        $anchor = preg_match('/^[a-zA-Z0-9_-]+$/', $slug) === 1 ? '#plugin-' . $slug : '';
        response()->redirect(base_href('/admin/plugins') . $anchor);
    }

}
