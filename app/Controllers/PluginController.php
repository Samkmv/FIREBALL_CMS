<?php

namespace App\Controllers;

use App\Services\PluginUpdateService;
use App\Services\PluginCatalogCategories;
use Throwable;

final class PluginController extends BaseController
{
    public function index(): string
    {
        $plugins = array_values(array_filter(
            plugin_manager()->all(),
            static fn(array $plugin): bool => !empty($plugin['installed'])
        ));
        try {
            $plugins = (new PluginUpdateService())->decoratePlugins($plugins);
        } catch (Throwable $exception) {
            log_error_details('Plugin update status loading failed', [], $exception);
        }

        return view('admin/plugins', [
            'title' => \FBL\Language::get('admin_plugins_title'),
            'plugins' => $plugins,
            'footer_scripts' => [asset_versioned_url(base_url('/assets/default/js/plugin-updates.js'), WWW . '/assets/default/js/plugin-updates.js')],
        ]);
    }

    public function install(): void
    {
        $this->installPlugin('/admin/plugins');
    }

    public function marketplace(): string
    {
        $query = request()->get('q', '');
        $catalog = (new PluginUpdateService())->marketplace(
            plugin_manager()->all(), false,
            PluginCatalogCategories::normalize(request()->get('category', '')),
            is_string($query) ? $query : ''
        );
        return view('admin/plugin_catalog', [
            'title' => \FBL\Language::get('admin_plugin_marketplace_title'),
            'catalog' => $catalog,
        ]);
    }

    public function refreshMarketplace(): void
    {
        $this->requirePluginUpdatePermission();
        $catalog = (new PluginUpdateService())->marketplace(plugin_manager()->all(), true);
        session()->setFlash($catalog['error'] === '' ? 'success' : 'error',
            $catalog['error'] ?: \FBL\Language::get('admin_plugin_marketplace_refreshed'));
        response()->redirect(base_href($this->catalogReturnPath()));
    }

    public function installMarketplace(): void
    {
        $this->installPlugin($this->catalogReturnPath());
    }

    private function catalogReturnPath(): string
    {
        $query = request()->post('q', '');
        $parameters = http_build_query(array_filter([
            'category' => PluginCatalogCategories::normalize(request()->post('category', '')),
            'q' => is_string($query) ? mb_substr(trim($query), 0, 200) : '',
        ], static fn(string $value): bool => $value !== ''), '', '&', PHP_QUERY_RFC3986);
        return '/admin/plugins/catalog' . ($parameters !== '' ? '?' . $parameters : '');
    }

    private function installPlugin(string $redirect): void
    {
        $this->requirePluginUpdatePermission();
        $slug = (string)request()->post('slug', '');
        try {
            (new PluginUpdateService())->installFromMarketplace($slug);
            session()->setFlash('success', \FBL\Language::get('admin_plugin_marketplace_installed'));
            $redirect = '/admin/plugins#plugin-' . $slug;
        } catch (Throwable $exception) {
            log_error_details('Plugin admin install failed', ['Plugin' => $slug], $exception);
            session()->setFlash('error', str_replace(':error', $exception->getMessage(), \FBL\Language::get('admin_plugins_install_failed')));
        }

        response()->redirect(base_href($redirect));
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
            if (request()->isAjax()) {
                response()->json(['status' => true, 'result' => $result]);
                return;
            }
            session()->setFlash(
                ($result['status'] ?? '') === 'source_older'
                    ? 'warning'
                    : (($result['status'] ?? '') === 'current' ? 'info' : 'success'),
                (string)($result['message'] ?? \FBL\Language::get('admin_plugin_updates_success'))
            );
        } catch (Throwable $exception) {
            log_error_details('Plugin update failed', ['Plugin' => $slug], $exception);
            if (request()->isAjax()) {
                response()->json(['status' => false, 'message' => $exception->getMessage()], 500);
                return;
            }
            session()->setFlash('error', $exception->getMessage());
        }

        $this->redirectToPlugin($slug);
    }

    public function updateAll(): void
    {
        $this->requirePluginUpdatePermission();
        try {
            $result = (new PluginUpdateService())->updateAll((array)get_user());
            $message = str_replace(
                [':updated', ':skipped', ':failed'],
                [(string)$result['updated'], (string)$result['skipped'], (string)$result['failed']],
                \FBL\Language::get('admin_plugin_updates_bulk_result')
            );
            if ($result['failures'] !== []) {
                $message .= ' ' . implode('; ', array_map(
                    static fn(string $slug, string $error): string => $slug . ': ' . $error,
                    array_keys($result['failures']), array_values($result['failures'])
                ));
            }
            session()->setFlash($result['failed'] > 0 ? 'warning' : ($result['updated'] > 0 ? 'success' : 'info'), $message);
        } catch (Throwable $exception) {
            log_error_details('Plugin bulk update failed', [], $exception);
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect(base_href('/admin/plugins'));
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
