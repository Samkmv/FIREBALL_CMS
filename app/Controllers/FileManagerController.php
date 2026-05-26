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
        $state['table_params']['page'] = max(1, (int)request()->get('page', 1));

        if (request()->isAjax()) {
            response()->json([
                'status' => 'success',
                'html' => $this->renderBrowser($directoryData, $state['picker_mode'], $state['picker_field']),
                'current_dir' => $directoryData['current_dir'] ?? '',
                'url' => $this->buildUrl(
                    $directoryData['current_dir'] ?? $state['current_dir'],
                    $state['picker_mode'],
                    $state['picker_field'],
                    $state['table_params']
                ),
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
        $destinationDir = trim((string)request()->post('destination_dir', ''));
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
            if ($items === []) {
                throw new \RuntimeException(return_translation('admin_files_selection_required'));
            }

            if ($action === 'delete') {
                $deletedCount = $this->files->deleteMany($items);
                $message = str_replace(':count', (string)$deletedCount, return_translation('admin_files_bulk_deleted'));
                $this->respondWithManagerState($state, 'success', $message);
            }

            if ($action === 'copy') {
                $copiedCount = $this->files->transferMany($items, $destinationDir, 'copy');
                $message = str_replace(':count', (string)$copiedCount, return_translation('admin_files_bulk_copied'));
                $this->respondWithManagerState($state, 'success', $message);
            }

            if ($action === 'move') {
                $movedCount = $this->files->transferMany($items, $destinationDir, 'move');
                $message = str_replace(':count', (string)$movedCount, return_translation('admin_files_bulk_moved'));
                $this->respondWithManagerState($state, 'success', $message);
            }

            throw new \RuntimeException(return_translation('admin_files_action_invalid'));
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
        $page = max(1, (int)($tableParams['page'] ?? 1));

        if ($search !== '') {
            $params['q'] = $search;
        }

        if ($sort !== '') {
            $params['sort'] = $sort;
        }

        if ($direction !== '') {
            $params['direction'] = $direction;
        }

        if ($page > 1) {
            $params['page'] = (string)$page;
        }

        $query = $params ? ('?' . http_build_query($params)) : '';

        return base_href('/admin/files' . $query);
    }

    /**
     * Возвращает параметры таблицы файлов для поиска, сортировки и пагинации.
     */
    protected function getTableParams(string $defaultSort, string $defaultDirection = 'desc'): array
    {
        $sort = $this->normalizeTableSort((string)request()->get('sort', $defaultSort), $defaultSort);
        $direction = $this->normalizeTableDirection((string)request()->get('direction', $defaultDirection), $defaultDirection);

        return [
            'per_page' => 5,
            'search' => request()->get('q', ''),
            'sort' => $sort,
            'direction' => $direction,
            'page' => max(1, (int)request()->get('page', 1)),
        ];
    }

    /**
     * Возвращает параметры списка для обратного перехода после POST-операций.
     */
    protected function getRedirectTableParams(): array
    {
        $sort = $this->normalizeTableSort((string)request()->post('sort', 'modified'), 'modified');
        $direction = $this->normalizeTableDirection((string)request()->post('direction', 'desc'), 'desc');

        return [
            'search' => request()->post('q', ''),
            'sort' => $sort,
            'direction' => $direction,
            'page' => max(1, (int)request()->post('page', 1)),
        ];
    }

    /**
     * Ограничивает сортировку файлового менеджера поддерживаемыми колонками.
     */
    protected function normalizeTableSort(string $sort, string $fallback): string
    {
        return in_array($sort, ['name', 'type', 'size', 'modified'], true) ? $sort : $fallback;
    }

    /**
     * Ограничивает направление сортировки предсказуемыми значениями.
     */
    protected function normalizeTableDirection(string $direction, string $fallback): string
    {
        $direction = strtolower($direction);
        if (in_array($direction, ['asc', 'desc'], true)) {
            return $direction;
        }

        return strtolower($fallback) === 'asc' ? 'asc' : 'desc';
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
        return view()->renderPartial('admin/file_manager_browser', [
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
            $this->syncRequestWithManagerState($state);
            $directoryData = $this->files->getDirectoryData($state['current_dir'], $state['table_params']);
            $state['table_params']['page'] = max(1, (int)request()->get('page', 1));

            response()->json([
                'status' => $status,
                'message' => $message,
                'html' => $this->renderBrowser($directoryData, $state['picker_mode'], $state['picker_field']),
                'current_dir' => $directoryData['current_dir'] ?? '',
                'url' => $this->buildUrl(
                    $directoryData['current_dir'] ?? $state['current_dir'],
                    $state['picker_mode'],
                    $state['picker_field'],
                    $state['table_params']
                ),
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

    /**
     * Подменяет GET-контекст для AJAX POST, чтобы пагинация строила ссылки на /admin/files.
     */
    protected function syncRequestWithManagerState(array $state): void
    {
        $url = $this->buildUrl(
            $state['current_dir'],
            $state['picker_mode'],
            $state['picker_field'],
            $state['table_params']
        );
        $parsedUrl = parse_url($url);
        $query = [];

        if (!empty($parsedUrl['query'])) {
            parse_str((string)$parsedUrl['query'], $query);
        }

        request()->get = $query;
        request()->uri = trim('/admin/files' . (!empty($parsedUrl['query']) ? '?' . $parsedUrl['query'] : ''), '/');
    }

}
