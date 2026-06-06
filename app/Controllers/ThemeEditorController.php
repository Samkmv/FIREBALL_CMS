<?php

namespace App\Controllers;

use App\Services\ThemeEditorService;
use FBL\Theme;

/**
 * Administrative controller for the sandboxed theme source editor.
 */
class ThemeEditorController extends BaseController
{
    protected ThemeEditorService $editor;

    public function __construct()
    {
        parent::__construct();
        $this->editor = new ThemeEditorService();
    }

    public function index()
    {
        $slug = trim((string)get_route_param('slug'));
        $theme = Theme::getTheme($slug);
        if ($theme === null) {
            abort();
        }

        $selectedDirectory = trim((string)request()->get('directory', ''));
        $selectedPath = $selectedDirectory !== ''
            ? $selectedDirectory
            : trim((string)request()->get('file', 'theme.json'));
        $selectedFile = null;
        $history = [];
        $editorError = '';

        try {
            if ($selectedDirectory !== '') {
                $selectedFile = $this->editor->openDirectory($slug, $selectedPath);
            } else {
                $selectedFile = $this->editor->open($slug, $selectedPath);
                $history = $this->editor->history($slug, $selectedPath);
            }
        } catch (\Throwable $exception) {
            $editorError = $exception->getMessage();
        }

        return view('admin/theme_files', [
            'title' => return_translation('admin_theme_editor_title'),
            'theme_item' => $theme,
            'themes' => Theme::getThemes(),
            'tree' => $this->editor->tree($slug),
            'selected_file' => $selectedFile,
            'selected_path' => $selectedPath,
            'history' => $history,
            'editor_error' => $editorError,
            'footer_scripts' => [
                base_url('/assets/default/js/admin-theme-editor.js?v=' . filemtime(WWW . '/assets/default/js/admin-theme-editor.js')),
            ],
        ]);
    }

    public function save(): void
    {
        $slug = $this->postedSlug();
        $path = trim((string)request()->post('path', ''));
        try {
            $this->editor->save($slug, $path, (string)request()->post('content', ''));
            $this->redirect($slug, $path, 'success', return_translation('admin_theme_editor_saved'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, $path, 'error', $exception->getMessage());
        }
    }

    public function createFile(): void
    {
        $slug = $this->postedSlug();
        try {
            $path = $this->editor->createFile(
                $slug,
                trim((string)request()->post('directory', '')),
                trim((string)request()->post('name', ''))
            );
            $this->redirect($slug, $path, 'success', return_translation('admin_theme_editor_file_created'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, null, 'error', $exception->getMessage());
        }
    }

    public function createDirectory(): void
    {
        $slug = $this->postedSlug();
        try {
            $this->editor->createDirectory(
                $slug,
                trim((string)request()->post('directory', '')),
                trim((string)request()->post('name', ''))
            );
            $this->redirect($slug, null, 'success', return_translation('admin_theme_editor_folder_created'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, null, 'error', $exception->getMessage());
        }
    }

    public function rename(): void
    {
        $slug = $this->postedSlug();
        $path = trim((string)request()->post('path', ''));
        try {
            $renamed = $this->editor->rename($slug, $path, trim((string)request()->post('name', '')));
            $this->redirect($slug, $renamed, 'success', return_translation('admin_theme_editor_renamed'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, $path, 'error', $exception->getMessage());
        }
    }

    public function delete(): void
    {
        $slug = $this->postedSlug();
        $path = trim((string)request()->post('path', ''));
        try {
            $this->editor->delete($slug, $path);
            $this->redirect($slug, null, 'success', return_translation('admin_theme_editor_deleted'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, $path, 'error', $exception->getMessage());
        }
    }

    public function replaceImage(): void
    {
        $slug = $this->postedSlug();
        $path = trim((string)request()->post('path', ''));
        try {
            $this->editor->replaceImage($slug, $path, request()->files['image'] ?? []);
            $this->redirect($slug, $path, 'success', return_translation('admin_theme_editor_image_replaced'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, $path, 'error', $exception->getMessage());
        }
    }

    public function restore(): void
    {
        $slug = $this->postedSlug();
        $path = trim((string)request()->post('path', ''));
        try {
            $this->editor->restore($slug, $path, trim((string)request()->post('backup_id', '')));
            $this->redirect($slug, $path, 'success', return_translation('admin_theme_editor_restored'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, $path, 'error', $exception->getMessage());
        }
    }

    public function copyTheme(): void
    {
        $slug = $this->postedSlug();
        try {
            $copy = $this->editor->copyTheme(
                $slug,
                trim((string)request()->post('new_slug', '')),
                trim((string)request()->post('new_name', ''))
            );
            $this->redirect((string)$copy['slug'], 'theme.json', 'success', return_translation('admin_theme_editor_copied'));
        } catch (\Throwable $exception) {
            $this->redirect($slug, null, 'error', $exception->getMessage());
        }
    }

    protected function postedSlug(): string
    {
        return trim((string)request()->post('slug', ''));
    }

    protected function redirect(string $slug, ?string $path, string $type, string $message): never
    {
        session()->setFlash($type, $message);
        $url = base_href('/admin/themes/files/' . rawurlencode($slug));
        if ($path !== null && $path !== '') {
            $url .= '?' . http_build_query(['file' => $path]);
        }
        response()->redirect($url);
    }
}
