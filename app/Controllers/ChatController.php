<?php

namespace App\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use FBL\File;

/**
 * Обрабатывает интерфейс личных сообщений, загрузку вложений и счётчики непрочитанных сообщений.
 */
class ChatController extends BaseController
{

    protected ChatMessage $chatMessages;
    protected User $users;

    /**
     * Инициализирует модели пользователей и сообщений чата.
     */
    public function __construct()
    {
        parent::__construct();
        $this->chatMessages = new ChatMessage();
        $this->users = new User();
    }

    /**
     * Показывает страницу чата со списком доступных контактов и активным диалогом.
     */
    public function index()
    {
        $currentUser = get_user();
        $currentUserId = (int)$currentUser['id'];
        $this->users->touchPresence($currentUserId);
        $contacts = $this->chatMessages->getContactsForUser($currentUserId, $this->isPrivilegedChatUser());
        $activeContact = $this->resolveActiveContact($contacts);
        $footerScripts = [
            base_url('/assets/default/js/chat.js?v=' . filemtime(WWW . '/assets/default/js/chat.js')),
        ];

        if (check_admin()) {
            $footerScripts[] = base_url('/assets/default/js/admin-file-manager.js?v=' . filemtime(WWW . '/assets/default/js/admin-file-manager.js'));
        }

        return view('chat/index', [
            'title' => return_translation('chat_index_title'),
            'contacts' => $contacts,
            'active_contact' => $activeContact,
            'chat_fetch_url' => base_href('/chat/messages'),
            'chat_send_url' => base_href('/chat/send'),
            'chat_delete_url' => base_href('/chat/messages/delete'),
            'chat_clear_url' => base_href('/chat/conversation/clear'),
            'chat_audit_url' => base_href('/chat/conversation/audit'),
            'chat_file_manager_enabled' => check_admin(),
            'chat_file_manager_url' => base_href('/admin/files'),
            'chat_permissions' => $this->chatMessages->getPermissionsForRole((string)($currentUser['role'] ?? 'user')),
            'footer_scripts' => $footerScripts,
        ]);
    }

    /**
     * Возвращает сообщения выбранного диалога и помечает их как прочитанные.
     */
    public function messages()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->get('user_id');
        $this->users->touchPresence($currentUserId);

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        $this->chatMessages->markConversationAsRead($currentUserId, $contactId);
        response()->json($this->buildConversationPayload($currentUserId, $contactId));
    }

    /**
     * Отправляет сообщение или вложение выбранному собеседнику.
     */
    public function send()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $message = trim((string)request()->post('message'));
        $files = $this->getAttachmentFiles();
        $siteAttachmentPaths = $this->getSiteAttachmentPaths();
        $this->users->touchPresence($currentUserId);

        $errors = array_merge(
            $this->validateAttachments($files),
            $this->validateSiteAttachmentPaths($siteAttachmentPaths)
        );
        if (!empty($errors)) {
            response()->json([
                'status' => false,
                'message' => implode(' ', array_values(array_unique(array_filter($errors)))),
            ], 422);
        }

        if ($message === '' && empty($files) && empty($siteAttachmentPaths)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_message_required'),
            ], 422);
        }

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        $attachments = $this->storeAttachments($files);
        if (count($attachments) !== count($files)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_file_upload_error'),
            ], 422);
        }
        $attachments = array_merge($attachments, $this->buildSiteAttachments($siteAttachmentPaths));

        $requestContext = $this->getRequestContext();

        if (empty($attachments)) {
            $this->chatMessages->create($currentUserId, $contactId, $message, null, $requestContext);
        } else {
            foreach ($attachments as $index => $attachment) {
                $text = $index === 0 ? $message : '';
                $this->chatMessages->create($currentUserId, $contactId, $text, $attachment, $requestContext);
            }
        }

        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['message'] = return_translation('chat_message_sent');

        response()->json($payload);
    }

    /**
     * Мягко удаляет одно или несколько сообщений.
     */
    public function deleteMessages()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $messageIds = $this->normalizeMessageIds($_POST['message_ids'] ?? request()->post('message_id'));

        if (!$this->isAllowedContact($currentUserId, $contactId) || empty($messageIds)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        try {
            $this->chatMessages->softDeleteMessages($messageIds, $currentUserId, [
                'reason' => trim((string)request()->post('reason')),
                'remove_media' => true,
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (\Throwable $exception) {
            response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['message'] = count($messageIds) > 1
            ? return_translation('chat_messages_deleted')
            : return_translation('chat_message_deleted');

        response()->json($payload);
    }

    /**
     * Очищает весь диалог между текущим пользователем и выбранным контактом.
     */
    public function clearConversation()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        try {
            $this->chatMessages->clearConversation($currentUserId, $contactId, [
                'reason' => trim((string)request()->post('reason')),
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (\Throwable $exception) {
            response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['message'] = return_translation('chat_conversation_cleared');

        response()->json($payload);
    }

    /**
     * Возвращает аудит действий по выбранному диалогу.
     */
    public function audit()
    {
        $currentUser = get_user();
        $currentUserId = (int)$currentUser['id'];
        $contactId = (int)request()->get('user_id');

        if (!can_view_chat_audit()) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_permission_denied'),
            ], 403);
        }

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        response()->json([
            'status' => true,
            'items' => $this->chatMessages->getAuditLogForConversation($currentUserId, $contactId),
        ]);
    }

    /**
     * Возвращает счётчики непрочитанных сообщений и обновлённый список контактов.
     */
    public function unreadCount()
    {
        $currentUserId = (int)get_user()['id'];
        $this->users->touchPresence($currentUserId);

        response()->json([
            'status' => true,
            'unread_count' => $this->chatMessages->getUnreadCountForUser($currentUserId),
            'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId),
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, $this->isPrivilegedChatUser()),
        ]);
    }

    /**
     * Собирает стандартный JSON-ответ для выбранного диалога.
     */
    protected function buildConversationPayload(int $currentUserId, int $contactId): array
    {
        $currentUser = get_user();

        return [
            'status' => true,
            'current_user_id' => $currentUserId,
            'permissions' => $this->chatMessages->getPermissionsForRole((string)($currentUser['role'] ?? 'user')),
            'unread_count' => $this->chatMessages->getUnreadCountForUser($currentUserId),
            'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId),
            'messages' => $this->chatMessages->getConversationMessages($currentUserId, $contactId),
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, $this->isPrivilegedChatUser()),
            'contact' => $this->users->getPresenceForChat($contactId),
        ];
    }

    /**
     * Определяет активный контакт по параметру запроса или берёт первый доступный диалог.
     */
    protected function resolveActiveContact(array $contacts): ?array
    {
        $requestedContactId = (int)request()->get('user_id');

        foreach ($contacts as $contact) {
            if ((int)$contact['id'] === $requestedContactId) {
                return $contact;
            }
        }

        return $contacts[0] ?? null;
    }

    /**
     * Проверяет, может ли текущий пользователь открыть диалог с указанным контактом.
     */
    protected function isAllowedContact(int $currentUserId, int $contactId): bool
    {
        if ($contactId <= 0 || $contactId === $currentUserId) {
            return false;
        }

        $contact = $this->users->findById($contactId);
        if (!$contact) {
            return false;
        }

        if ($this->isPrivilegedChatUser()) {
            return true;
        }

        return in_array(($contact['role'] ?? 'user'), ['creator', 'admin', 'moderator'], true);
    }

    /**
     * Определяет, должен ли пользователь видеть полный список чатов.
     */
    protected function isPrivilegedChatUser(): bool
    {
        return can_moderate_chat() || check_admin();
    }

    /**
     * Проверяет вложение по наличию, размеру, расширению и содержимому файла.
     */
    protected function validateAttachments(array $files): array
    {
        $errors = [];
        foreach ($files as $file) {
            if (!$file instanceof File) {
                continue;
            }

            if (!$file->isFile || $file->getError() !== UPLOAD_ERR_OK) {
                $errors[] = return_translation('chat_file_upload_error');
                continue;
            }

            if ($file->getSize() > 200 * 1024 * 1024) {
                $errors[] = return_translation('chat_file_size_error');
                continue;
            }

            $extension = strtolower($file->getExt());
            $allowedExtensions = $this->getAllowedAttachmentExtensions();
            $blockedExtensions = $this->getBlockedAttachmentExtensions();

            if (in_array($extension, $blockedExtensions, true) || !in_array($extension, $allowedExtensions, true)) {
                $errors[] = return_translation('chat_file_type_error');
                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true) && !@getimagesize($file->getTmpName())) {
                $errors[] = return_translation('chat_file_type_error');
            }
        }

        return array_values(array_unique(array_filter($errors)));
    }

    /**
     * Сохраняет вложение сообщения и возвращает его метаданные.
     */
    protected function storeAttachments(array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            if (!$file instanceof File || !$file->isFile) {
                continue;
            }

            $savedPath = $file->save('chat');
            if (!$savedPath) {
                continue;
            }

            $type = $file->getType();
            $extension = strtolower($file->getExt());
            $type = $this->resolveAttachmentMimeType($extension, $type);

            $attachments[] = [
                'path' => ltrim((string)$savedPath, '/'),
                'name' => $file->getName(),
                'type' => $type,
                'size' => $file->getSize(),
            ];
        }

        return $attachments;
    }

    /**
     * Возвращает список разрешённых расширений вложений.
     */
    protected function getAllowedAttachmentExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp',
            'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac',
            'mp4', 'webm', 'mov', 'avi', 'mkv', 'mpeg', 'mpg',
            'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'odt', 'ods', 'odp', 'md', 'json', 'xml',
            'zip', 'rar', '7z',
        ];
    }

    /**
     * Возвращает список явно запрещённых расширений.
     */
    protected function getBlockedAttachmentExtensions(): array
    {
        return ['exe', 'bat', 'cmd', 'sh', 'apk', 'js'];
    }

    /**
     * Собирает вложения из input[type=file] с поддержкой multiple.
     */
    protected function getAttachmentFiles(): array
    {
        $requestFiles = request()->files['attachment'] ?? null;
        if (!is_array($requestFiles)) {
            return [];
        }

        $names = $requestFiles['name'] ?? null;
        if (is_array($names)) {
            $files = [];
            foreach (array_keys($names) as $index) {
                $file = new File('attachment.' . $index);
                if ($file->isFile || $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    $files[] = $file;
                }
            }
            return $files;
        }

        $file = new File('attachment');
        return $file->isFile ? [$file] : [];
    }

    /**
     * Возвращает выбранные через файловый менеджер пути внутри uploads.
     */
    protected function getSiteAttachmentPaths(): array
    {
        $value = $_POST['site_attachment_paths'] ?? request()->post('site_attachment_paths');
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : [$value];
        $paths = [];

        foreach ($items as $item) {
            $path = $this->normalizeSiteAttachmentPath((string)$item);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Проверяет вложения, выбранные через файловый менеджер сайта.
     */
    protected function validateSiteAttachmentPaths(array $paths): array
    {
        $errors = [];

        foreach ($paths as $path) {
            $absolutePath = $this->getSiteAttachmentAbsolutePath($path);
            if ($absolutePath === '' || !is_file($absolutePath)) {
                $errors[] = return_translation('chat_file_upload_error');
                continue;
            }

            $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
            $allowedExtensions = $this->getAllowedAttachmentExtensions();
            $blockedExtensions = $this->getBlockedAttachmentExtensions();

            if (in_array($extension, $blockedExtensions, true) || !in_array($extension, $allowedExtensions, true)) {
                $errors[] = return_translation('chat_file_type_error');
                continue;
            }

            $size = (int)@filesize($absolutePath);
            if ($size > 200 * 1024 * 1024) {
                $errors[] = return_translation('chat_file_size_error');
                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true) && !@getimagesize($absolutePath)) {
                $errors[] = return_translation('chat_file_type_error');
            }
        }

        return array_values(array_unique(array_filter($errors)));
    }

    /**
     * Формирует метаданные для вложений, выбранных в файловом менеджере.
     */
    protected function buildSiteAttachments(array $paths): array
    {
        $attachments = [];

        foreach ($paths as $path) {
            $absolutePath = $this->getSiteAttachmentAbsolutePath($path);
            if ($absolutePath === '' || !is_file($absolutePath)) {
                continue;
            }

            $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
            $attachments[] = [
                'path' => $path,
                'name' => basename($path),
                'type' => $this->resolveAttachmentMimeType($extension, (string)(@mime_content_type($absolutePath) ?: 'application/octet-stream')),
                'size' => (int)@filesize($absolutePath),
            ];
        }

        return $attachments;
    }

    /**
     * Нормализует публичный путь до файла внутри каталога uploads.
     */
    protected function normalizeSiteAttachmentPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $parsedPath = (string)(parse_url($path, PHP_URL_PATH) ?: $path);
        $parsedPath = str_replace('\\', '/', $parsedPath);
        $parsedPath = preg_replace('#/+#', '/', $parsedPath) ?: '';

        if (str_starts_with($parsedPath, '/uploads/')) {
            $parsedPath = ltrim($parsedPath, '/');
        }

        if (!str_starts_with($parsedPath, 'uploads/') || str_contains($parsedPath, '..')) {
            return '';
        }

        return $parsedPath;
    }

    /**
     * Возвращает абсолютный путь до файла из uploads.
     */
    protected function getSiteAttachmentAbsolutePath(string $path): string
    {
        if (!str_starts_with($path, 'uploads/')) {
            return '';
        }

        $relativePath = ltrim(substr($path, strlen('uploads/')), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return '';
        }

        return rtrim(UPLOADS, '/') . '/' . $relativePath;
    }

    /**
     * Возвращает контекст запроса для аудита и IP/device.
     */
    protected function getRequestContext(): array
    {
        return [
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];
    }

    /**
     * Нормализует идентификаторы сообщений из POST.
     */
    protected function normalizeMessageIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('intval', $value))));
        }

        $singleId = (int)$value;
        return $singleId > 0 ? [$singleId] : [];
    }

    /**
     * Нормализует MIME-тип вложения по расширению файла.
     */
    protected function resolveAttachmentMimeType(string $extension, string $fallbackType): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'md' => 'text/markdown',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'rtf' => 'application/rtf',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
        ];

        return $mimeTypes[$extension] ?? $fallbackType;
    }

}
