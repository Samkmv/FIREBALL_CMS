<?php

namespace App\Controllers;

use App\Models\FileManager;
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
        $state = $this->getManagerStateFromGet();
        $directoryData = $this->files->getDirectoryData($state['current_dir'], $state['table_params']);

        if (request()->isAjax()) {
            response()->json([
                'status' => 'success',
                'html' => $this->renderBrowser($directoryData, $state['picker_mode'], $state['picker_field']),
                'current_dir' => $directoryData['current_dir'] ?? '',
            ]);
        }

        return view('admin/files', [
            'title' => return_translation('admin_files_title'),
            'manager' => $directoryData,
            'picker_mode' => $state['picker_mode'],
            'picker_field' => $state['picker_field'],
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
        $state = $this->getManagerStateFromPost();
        $files = request()->files['upload_files'] ?? [];

        try {
            $uploadedCount = $this->files->uploadMany($state['current_dir'], $files);
            $message = str_replace(':count', (string)$uploadedCount, return_translation('admin_files_uploaded_count'));
            $this->respondWithManagerState($state, 'success', $message);
        } catch (\RuntimeException $exception) {
            $this->respondWithManagerState($state, 'error', $exception->getMessage());
        }
    }

    /**
     * Создаёт новую папку в выбранной директории.
     */
    public function createDirectory()
    {
        $state = $this->getManagerStateFromPost();
        $directoryName = trim((string)request()->post('directory_name', ''));

        try {
            $this->files->createDirectory($state['current_dir'], $directoryName);
            $this->respondWithManagerState($state, 'success', return_translation('admin_files_folder_created'));
        } catch (\RuntimeException $exception) {
            $this->respondWithManagerState($state, 'error', $exception->getMessage());
        }
    }

    /**
     * Удаляет выбранный файл через файловый менеджер.
     */
    public function delete()
    {
        $state = $this->getManagerStateFromPost();
        $path = trim((string)request()->post('path', ''));

        try {
            $this->files->delete($path);
            $this->respondWithManagerState($state, 'success', return_translation('admin_files_deleted'));
        } catch (\RuntimeException $exception) {
            $this->respondWithManagerState($state, 'error', $exception->getMessage());
        }
    }

    /**
     * Удаляет выбранную папку через файловый менеджер.
     */
    public function deleteDirectory()
    {
        $state = $this->getManagerStateFromPost();
        $path = trim((string)request()->post('path', ''));

        try {
            $this->files->deleteDirectory($path);
            $this->respondWithManagerState($state, 'success', return_translation('admin_files_folder_deleted'));
        } catch (\RuntimeException $exception) {
            $this->respondWithManagerState($state, 'error', $exception->getMessage());
        }
    }

    /**
     * Переименовывает выбранный файл или папку.
     */
    public function rename()
    {
        $state = $this->getManagerStateFromPost();
        $path = trim((string)request()->post('path', ''));
        $newName = trim((string)request()->post('new_name', ''));

        try {
            $this->files->rename($path, $newName);
            $this->respondWithManagerState($state, 'success', return_translation('admin_files_renamed'));
        } catch (\RuntimeException $exception) {
            $this->respondWithManagerState($state, 'error', $exception->getMessage());
        }
    }

    /**
     * Выполняет групповое действие над выбранными файлами и папками.
     */
    public function bulkAction()
    {
        $state = $this->getManagerStateFromPost();
        $action = trim((string)request()->post('action_name', ''));
        $paths = request()->post['selected_paths'] ?? [];
        $types = request()->post['selected_types'] ?? [];
        $items = [];

        if (is_array($paths)) {
            foreach ($paths as $index => $path) {
                $path = trim((string)$path);
                if ($path === '') {
                    continue;
                }

                $items[] = [
                    'path' => $path,
                    'type' => is_array($types) ? trim((string)($types[$index] ?? 'file')) : 'file',
                ];
            }
        }

        try {
            if ($action !== 'delete') {
                throw new \RuntimeException(return_translation('admin_files_action_invalid'));
            }

            if ($items === []) {
                throw new \RuntimeException(return_translation('admin_files_selection_required'));
            }

            $deletedCount = $this->files->deleteMany($items);
            $message = str_replace(':count', (string)$deletedCount, return_translation('admin_files_bulk_deleted'));
            $this->respondWithManagerState($state, 'success', $message);
        } catch (\RuntimeException $exception) {
            $this->respondWithManagerState($state, 'error', $exception->getMessage());
        }
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
            'per_page' => 30,
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

    /**
     * Возвращает полное состояние менеджера для GET-запроса.
     */
    protected function getManagerStateFromGet(): array
    {
        return [
            'current_dir' => trim((string)request()->get('dir', '')),
            'picker_mode' => request()->get('picker', '') === '1',
            'picker_field' => trim((string)request()->get('field', '')),
            'table_params' => $this->getTableParams('modified', 'desc'),
        ];
    }

    /**
     * Возвращает полное состояние менеджера для POST-запроса.
     */
    protected function getManagerStateFromPost(): array
    {
        return [
            'current_dir' => trim((string)request()->post('dir', '')),
            'picker_mode' => request()->post('picker', '') === '1',
            'picker_field' => trim((string)request()->post('field', '')),
            'table_params' => $this->getRedirectTableParams(),
        ];
    }

    /**
     * Рендерит динамическую часть файлового менеджера.
     */
    protected function renderBrowser(array $directoryData, bool $pickerMode, string $pickerField): string
    {
        return view()->renderPartial('admin/_file_manager_browser', [
            'manager' => $directoryData,
            'picker_mode' => $pickerMode,
            'picker_field' => $pickerField,
        ]);
    }

    /**
     * Возвращает обновлённое состояние менеджера в JSON или делает редирект для обычной формы.
     */
    protected function respondWithManagerState(array $state, string $status, string $message): void
    {
        if (request()->isAjax()) {
            $directoryData = $this->files->getDirectoryData($state['current_dir'], $state['table_params']);
            response()->json([
                'status' => $status,
                'message' => $message,
                'html' => $this->renderBrowser($directoryData, $state['picker_mode'], $state['picker_field']),
                'current_dir' => $directoryData['current_dir'] ?? '',
            ], $status === 'success' ? 200 : 422);
        }

        session()->setFlash($status === 'success' ? 'success' : 'error', $message);
        response()->redirect($this->buildUrl(
            $state['current_dir'],
            $state['picker_mode'],
            $state['picker_field'],
            $state['table_params']
        ));
    }

}
