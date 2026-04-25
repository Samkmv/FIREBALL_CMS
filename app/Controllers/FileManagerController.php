<?php

namespace App\Controllers;

use App\Models\FileManager;
use FBL\File;

/**
 * Управляет файловым менеджером административной панели.
 */
class FileManagerController extends BaseController
{

    protected FileManager $files;

    /**
     * Инициализирует модель файлового менеджера.
     */
    public function __construct()
    {
        $this->files = new FileManager();
    }

    /**
     * Показывает содержимое текущей директории файлового менеджера.
     */
    public function index()
    {
        $currentDir = trim((string)request()->get('dir', ''));
        $pickerMode = request()->get('picker', '') === '1';
        $field = trim((string)request()->get('field', ''));
        $tableParams = $this->getTableParams('modified', 'desc');
        $directoryData = $this->files->getDirectoryData($currentDir, $tableParams);

        return view('admin/files', [
            'title' => return_translation('admin_files_title'),
            'manager' => $directoryData,
            'picker_mode' => $pickerMode,
            'picker_field' => $field,
            'footer_scripts' => [
                base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js')),
            ],
        ]);
    }

    /**
     * Загружает файл в выбранную директорию и возвращает пользователя в менеджер.
     */
    public function upload()
    {
        $currentDir = trim((string)request()->post('dir', ''));
        $pickerMode = request()->post('picker', '') === '1';
        $field = trim((string)request()->post('field', ''));
        $tableParams = $this->getRedirectTableParams();
        $file = new File('upload_file');

        try {
            $this->files->upload($currentDir, $file);
            session()->setFlash('success', return_translation('admin_files_uploaded'));
        } catch (\RuntimeException $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect($this->buildUrl($currentDir, $pickerMode, $field, $tableParams));
    }

    /**
     * Создаёт новую папку в выбранной директории.
     */
    public function createDirectory()
    {
        $currentDir = trim((string)request()->post('dir', ''));
        $pickerMode = request()->post('picker', '') === '1';
        $field = trim((string)request()->post('field', ''));
        $tableParams = $this->getRedirectTableParams();
        $directoryName = trim((string)request()->post('directory_name', ''));

        try {
            $this->files->createDirectory($currentDir, $directoryName);
            session()->setFlash('success', return_translation('admin_files_folder_created'));
        } catch (\RuntimeException $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect($this->buildUrl($currentDir, $pickerMode, $field, $tableParams));
    }

    /**
     * Удаляет выбранный файл через файловый менеджер.
     */
    public function delete()
    {
        $currentDir = trim((string)request()->post('dir', ''));
        $pickerMode = request()->post('picker', '') === '1';
        $field = trim((string)request()->post('field', ''));
        $tableParams = $this->getRedirectTableParams();
        $path = trim((string)request()->post('path', ''));

        try {
            $this->files->delete($path);
            session()->setFlash('success', return_translation('admin_files_deleted'));
        } catch (\RuntimeException $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect($this->buildUrl($currentDir, $pickerMode, $field, $tableParams));
    }

    /**
     * Удаляет выбранную папку через файловый менеджер.
     */
    public function deleteDirectory()
    {
        $currentDir = trim((string)request()->post('dir', ''));
        $pickerMode = request()->post('picker', '') === '1';
        $field = trim((string)request()->post('field', ''));
        $tableParams = $this->getRedirectTableParams();
        $path = trim((string)request()->post('path', ''));

        try {
            $this->files->deleteDirectory($path);
            session()->setFlash('success', return_translation('admin_files_folder_deleted'));
        } catch (\RuntimeException $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect($this->buildUrl($currentDir, $pickerMode, $field, $tableParams));
    }

    /**
     * Переименовывает выбранный файл.
     */
    public function rename()
    {
        $currentDir = trim((string)request()->post('dir', ''));
        $pickerMode = request()->post('picker', '') === '1';
        $field = trim((string)request()->post('field', ''));
        $tableParams = $this->getRedirectTableParams();
        $path = trim((string)request()->post('path', ''));
        $newName = trim((string)request()->post('new_name', ''));

        try {
            $this->files->rename($path, $newName);
            session()->setFlash('success', return_translation('admin_files_renamed'));
        } catch (\RuntimeException $exception) {
            session()->setFlash('error', $exception->getMessage());
        }

        response()->redirect($this->buildUrl($currentDir, $pickerMode, $field, $tableParams));
    }

    /**
     * Собирает URL возврата в файловый менеджер с сохранением текущих параметров.
     */
    protected function buildUrl(string $dir = '', bool $pickerMode = false, string $field = '', array $tableParams = []): string
    {
        $params = [];

        if ($dir !== '') {
            $params['dir'] = $dir;
        }

        if ($pickerMode) {
            $params['picker'] = '1';
        }

        if ($field !== '') {
            $params['field'] = $field;
        }

        $search = trim((string)($tableParams['search'] ?? ''));
        $sort = trim((string)($tableParams['sort'] ?? ''));
        $direction = trim((string)($tableParams['direction'] ?? ''));

        if ($search !== '') {
            $params['q'] = $search;
        }

        if ($sort !== '') {
            $params['sort'] = $sort;
        }

        if ($direction !== '') {
            $params['direction'] = $direction;
        }

        $query = $params ? ('?' . http_build_query($params)) : '';

        return base_href('/admin/files' . $query);
    }

    /**
     * Возвращает параметры таблицы файлов для поиска, сортировки и пагинации.
     */
    protected function getTableParams(string $defaultSort, string $defaultDirection = 'desc'): array
    {
        return [
            'per_page' => 15,
            'search' => request()->get('q', ''),
            'sort' => request()->get('sort', $defaultSort),
            'direction' => request()->get('direction', $defaultDirection),
        ];
    }

    /**
     * Возвращает параметры списка для обратного перехода после POST-операций.
     */
    protected function getRedirectTableParams(): array
    {
        return [
            'search' => request()->post('q', ''),
            'sort' => request()->post('sort', 'modified'),
            'direction' => request()->post('direction', 'desc'),
        ];
    }

}
