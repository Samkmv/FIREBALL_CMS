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
        $this->users->touchPresence((int)$currentUser['id']);
        $contacts = $this->chatMessages->getContactsForUser((int)$currentUser['id'], check_admin());
        $activeContact = $this->resolveActiveContact($contacts);

        return view('chat/index', [
            'title' => return_translation('chat_index_title'),
            'contacts' => $contacts,
            'active_contact' => $activeContact,
            'chat_fetch_url' => base_href('/chat/messages'),
            'chat_send_url' => base_href('/chat/send'),
            'footer_scripts' => [
                base_url('/assets/default/js/chat.js?v=' . filemtime(WWW . '/assets/default/js/chat.js')),
            ],
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

        response()->json([
            'status' => true,
            'current_user_id' => $currentUserId,
            'unread_count' => $this->chatMessages->getUnreadCountForUser($currentUserId),
            'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId),
            'messages' => $this->chatMessages->getConversationMessages($currentUserId, $contactId),
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, check_admin()),
            'contact' => $this->users->getPresenceForChat($contactId),
        ]);
    }

    /**
     * Отправляет сообщение или вложение выбранному собеседнику.
     */
    public function send()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $message = trim((string)request()->post('message'));
        $file = new File('attachment');
        $this->users->touchPresence($currentUserId);

        $fileErrors = $this->validateAttachment($file);
        if (!empty($fileErrors)) {
            response()->json([
                'status' => false,
                'message' => implode(' ', $fileErrors),
            ], 422);
        }

        if ($message === '' && !$file->isFile) {
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

        $attachment = $this->storeAttachment($file);
        if ($file->isFile && !$attachment) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_file_upload_error'),
            ], 422);
        }

        $this->chatMessages->create($currentUserId, $contactId, $message, $attachment);

        response()->json([
            'status' => true,
            'message' => return_translation('chat_message_sent'),
            'current_user_id' => $currentUserId,
            'unread_count' => $this->chatMessages->getUnreadCountForUser($currentUserId),
            'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId),
            'messages' => $this->chatMessages->getConversationMessages($currentUserId, $contactId),
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, check_admin()),
            'contact' => $this->users->getPresenceForChat($contactId),
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
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, check_admin()),
        ]);
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

        if (check_admin()) {
            return true;
        }

        return in_array(($contact['role'] ?? 'user'), ['creator', 'admin'], true);
    }

    /**
     * Проверяет вложение по наличию, размеру, расширению и содержимому файла.
     */
    protected function validateAttachment(File $file): array
    {
        if (!$file->isFile && $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        if (!$file->isFile || $file->getError() !== UPLOAD_ERR_OK) {
            return [return_translation('chat_file_upload_error')];
        }

        if ($file->getSize() > 200 * 1024 * 1024) {
            return [return_translation('chat_file_size_error')];
        }

        $extension = strtolower($file->getExt());
        $allowedExtensions = $this->getAllowedAttachmentExtensions();

        if (!in_array($extension, $allowedExtensions, true)) {
            return [return_translation('chat_file_type_error')];
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true) && !@getimagesize($file->getTmpName())) {
            return [return_translation('chat_file_type_error')];
        }

        return [];
    }

    /**
     * Сохраняет вложение сообщения и возвращает его метаданные.
     */
    protected function storeAttachment(File $file): ?array
    {
        if (!$file->isFile) {
            return null;
        }

        $savedPath = $file->save('chat');
        if (!$savedPath) {
            return null;
        }

        $type = $file->getType();
        $extension = strtolower($file->getExt());
        $type = $this->resolveAttachmentMimeType($extension, $type);

        return [
            'path' => ltrim((string)$savedPath, '/'),
            'name' => $file->getName(),
            'type' => $type,
            'size' => $file->getSize(),
        ];
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
