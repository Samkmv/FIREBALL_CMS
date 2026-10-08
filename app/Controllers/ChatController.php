<?php

namespace App\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatCipher;
use App\Services\ChatMediaStorage;
use App\Services\ChatRealtimeService;
use App\Services\ChatTypingService;
use App\Services\ConversationService;
use App\Services\GroupChatService;
use App\Services\GroupChatRealtimeService;
use App\Services\GroupChatManagementService;
use App\Services\ChatWorkspaceService;
use App\Services\NotificationService;
use App\Services\SafeUploadService;
use App\Services\UploadSettings;
use FBL\File;
use FBL\Language;
use FBL\Theme;

/**
 * Обрабатывает интерфейс личных сообщений, загрузку вложений и счётчики непрочитанных сообщений.
 */
class ChatController extends BaseController
{
    // FIREBALL_CHAT30_GROUPS
    // FIREBALL_CHAT24_TYPING
    // FIREBALL_CHAT21_REACTIONS
    // FIREBALL_CHAT21_EDIT
    // FIREBALL_CHAT21_REPLY
    // FIREBALL_CHAT2_CONTROLLER

    protected ChatMessage $chatMessages;
    protected User $users;

    /**
     * Инициализирует модели пользователей и сообщений чата.
     */
    public function __construct()
    {
        parent::__construct();
        Language::load([self::class, 'index']);
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
        $conversationId = max(0, (int)request()->get('conversation_id'));
        $group = null;
        if ($conversationId) {
            $group = (new GroupChatService())->getForUser($conversationId, $currentUserId);
            if (!$group) response()->text('', 404);
            $activeContact = ['id' => $conversationId, 'name' => $group['title'], 'avatar' => null, 'role' => 'group', 'is_online' => false];
        }
        $footerScripts = [
            theme_asset_versioned('js/chat.js'),
            theme_asset_versioned('js/chat-workspace.js'),
        ];

        if (check_admin()) {
            $footerScripts[] = theme_asset_versioned('js/admin-file-manager.js');
        }

        return Theme::render('chat/index', [
            'title' => return_translation('chat_index_title'),
            'contacts' => $contacts,
            'header_scripts' => [
                theme_asset_versioned('js/app-viewport.js'),
                theme_asset_versioned('js/chat-viewport.js'),
            ],
            'active_contact' => $activeContact,
            'chat_active_group' => $group,
            'chat_members' => $group ? (new GroupChatService())->getMembers($conversationId) : [],
            'chat_workspace_url' => base_href('/chat/workspace'),
            'chat_group_manage_url' => base_href('/chat/group/manage'),
            'chat_history_url' => base_href('/chat/history'),
            'chat_forward_url' => base_href('/chat/forward'),
            'chat_fetch_url' => base_href($group ? '/chat/group/messages' : '/chat/messages'),
            'chat_stream_url' => base_href($group ? '/chat/group/stream' : '/chat/stream'),
            'chat_typing_url' => base_href('/chat/typing'),
            'chat_groups' => (new GroupChatService())->listForUser($currentUserId),
            'chat_group_candidates' => $contacts,
            'chat_group_create_url' => base_href('/chat/groups/create'),
            'chat_group_url' => base_href('/chat/group'),
            'chat_send_url' => base_href('/chat/send'),
            'chat_edit_url' => base_href('/chat/messages/edit'),
            'chat_react_url' => base_href('/chat/messages/react'),
            'chat_delete_url' => base_href('/chat/messages/delete'),
            'chat_clear_url' => base_href('/chat/conversation/clear'),
            'chat_audit_url' => base_href('/chat/conversation/audit'),
            'chat_audit_clear_url' => base_href('/chat/conversation/audit/clear'),
            'chat_file_manager_enabled' => check_admin(),
            'chat_file_manager_url' => base_href('/admin/files'),
            'chat_max_file_size' => \App\Services\UploadPolicy::limits()['effective'],
            'chat_permissions' => $group ? (new GroupChatManagementService())->permissions($group['role']) : $this->chatMessages->getPermissionsForRole((string)($currentUser['role'] ?? 'user')),
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
        session()->close();
        response()->json($this->buildConversationPayload($currentUserId, $contactId));
    }

    /**
     * Выдаёт зашифрованное вложение только участнику соответствующего диалога.
     */
    public function media()
    {
        $messageId = (int)get_route_param('id', 0);
        $currentUserId = (int)get_user()['id'];
        $attachment = $this->chatMessages->getAttachmentForUser($messageId, $currentUserId);

        if (!$attachment || !ChatMediaStorage::isProtectedPath((string)($attachment['path'] ?? ''))) {
            response()->text('', 404);
        }

        session()->close();
        try {
            (new ChatMediaStorage())->stream(
                (string)$attachment['path'],
                (string)$attachment['name'],
                (string)$attachment['type'],
                (int)$attachment['size']
            );
        } catch (\Throwable $exception) {
            log_error_details('Protected chat media delivery failed', [
                'message_id' => $messageId,
                'user_id' => $currentUserId,
            ], $exception);
            response()->text('', 404);
        }
    }

    /**
     * Отправляет сообщение или вложение выбранному собеседнику.
     */
    public function send()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $conversationId = max(0, (int)request()->post('conversation_id'));
        $isGroup = $conversationId > 0;
        $message = trim((string)request()->post('message'));
        $replyToId = max(0, (int)request()->post('reply_to_id'));
        $files = $this->getAttachmentFiles();
        $siteAttachmentPaths = $this->getSiteAttachmentPaths();
        $this->users->touchPresence($currentUserId);

        if (mb_strlen($message) > 2000) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_message_too_long'),
            ], 422);
        }

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

        if (!($isGroup ? (new GroupChatService())->isMember($conversationId, $currentUserId) : $this->isAllowedContact($currentUserId, $contactId))) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        if ($replyToId > 0
            && !($isGroup ? db()->query('SELECT id FROM chat_messages WHERE id = ? AND conversation_id = ? AND deleted_at IS NULL', [$replyToId, $conversationId])->getColumn() : $this->chatMessages->getReplyTargetForConversation($replyToId, $currentUserId, $contactId))) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_reply_invalid'),
            ], 422);
        }

        $attachments = [];
        $database = db();
        $ownsTransaction = !$database->inTransaction();

        try {
            $attachments = $this->storeAttachments($files);
            $attachments = array_merge($attachments, $this->buildSiteAttachments($siteAttachmentPaths));

            if (count($attachments) !== count($files) + count($siteAttachmentPaths)) {
                throw new \RuntimeException('Not all chat attachments were stored.');
            }

            $requestContext = $this->getRequestContext();
            $this->chatMessages->ensureTableExists();
            if ($ownsTransaction) {
                $database->beginTransaction();
            }

            foreach ($attachments ?: [null] as $index => $attachment) {
                $text = $index === 0 ? $message : '';
                $reply = $index === 0 ? ($replyToId ?: null) : null;
                if ($isGroup) {
                    (new GroupChatService())->createMessage($conversationId, $currentUserId, $text, $requestContext, $attachment, $reply);
                } else {
                    $this->chatMessages->create($currentUserId, $contactId, $text, $attachment, $requestContext, $reply);
                }
            }
            if ($isGroup) (new GroupChatService())->markRead($conversationId, $currentUserId);
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            $this->cleanupStoredAttachments($attachments);
            log_error_details('Protected chat media storage failed', [
                'sender_id' => $currentUserId,
                'receiver_id' => $contactId,
                'attachment_count' => count($files) + count($siteAttachmentPaths),
            ], $exception);
            response()->json([
                'status' => false,
                'message' => count($files) + count($siteAttachmentPaths) > 0
                    ? return_translation('chat_file_upload_error')
                    : return_translation('chat_message_send_error'),
            ], 422);
        }

        if ($isGroup) $this->notifyGroupRecipients($conversationId, $currentUserId, $message);
        else $this->notifyChatRecipient($currentUserId, $contactId, $message, $attachments);

        if (!(int)request()->post('conversation_id')) (new ChatWorkspaceService())->bump((new ConversationService())->ensureDirectConversation($currentUserId, $contactId));
        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['message'] = return_translation('chat_message_sent');

        response()->json($payload);
    }

    /**
     * Переключает реакцию текущего пользователя на сообщении.
     */
    public function reactMessage()
    {
        if ((int)request()->post('conversation_id') > 0) return $this->groupMessageAction('react');
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $messageId = max(0, (int)request()->post('message_id'));
        $reaction = trim((string)request()->post('reaction'));
        $this->users->touchPresence($currentUserId);

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        try {
            $result = $this->chatMessages->toggleReaction(
                $messageId,
                $currentUserId,
                $contactId,
                $reaction
            );
        } catch (\Throwable $exception) {
            log_error_details('Chat reaction toggle failed', [
                'message_id' => $messageId,
                'user_id' => $currentUserId,
                'contact_id' => $contactId,
                'reaction' => $reaction,
            ], $exception);

            response()->json([
                'status' => false,
                'message' => return_translation('chat_reaction_error'),
            ], 422);
        }

        if (empty($result['status'])) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_reaction_invalid'),
            ], 422);
        }

        if (!(int)request()->post('conversation_id')) (new ChatWorkspaceService())->bump((new ConversationService())->ensureDirectConversation($currentUserId, $contactId));
        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['reaction_active'] = !empty($result['active']);
        $payload['reaction'] = (string)($result['reaction'] ?? '');
        $payload['reaction_message_id'] = $messageId;

        response()->json($payload);
    }

    /**
     * Редактирует текст собственного сообщения.
     */
    public function editMessage()
    {
        if ((int)request()->post('conversation_id') > 0) return $this->groupMessageAction('edit');
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $messageId = max(0, (int)request()->post('message_id'));
        $message = trim((string)request()->post('message'));
        $this->users->touchPresence($currentUserId);

        if ($messageId <= 0) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_edit_invalid'),
            ], 422);
        }

        if (mb_strlen($message) > 2000) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_message_too_long'),
            ], 422);
        }

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        try {
            $result = $this->chatMessages->editOwnMessage(
                $messageId,
                $currentUserId,
                $contactId,
                $message
            );
        } catch (\Throwable $exception) {
            log_error_details('Chat message edit failed', [
                'message_id' => $messageId,
                'sender_id' => $currentUserId,
                'receiver_id' => $contactId,
            ], $exception);

            response()->json([
                'status' => false,
                'message' => return_translation('chat_edit_error'),
            ], 422);
        }

        if (empty($result['status'])) {
            $reason = (string)($result['reason'] ?? '');
            response()->json([
                'status' => false,
                'message' => $reason === 'message_required'
                    ? return_translation('chat_edit_message_required')
                    : return_translation('chat_edit_invalid'),
            ], $reason === 'message_required' ? 422 : 403);
        }

        if (!(int)request()->post('conversation_id')) (new ChatWorkspaceService())->bump((new ConversationService())->ensureDirectConversation($currentUserId, $contactId));
        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['edited_message_id'] = $messageId;
        $payload['message'] = return_translation('chat_message_edited');

        response()->json($payload);
    }

    /**
     * Мягко удаляет одно или несколько сообщений.
     */
    public function deleteMessages()
    {
        if ((int)request()->post('conversation_id') > 0) return $this->groupMessageAction('delete');
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
            $result = $this->chatMessages->softDeleteMessages($messageIds, $currentUserId, [
                'reason' => trim((string)request()->post('reason')),
                'remove_media' => true,
                'conversation_user_ids' => [$currentUserId, $contactId],
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (\Throwable $exception) {
            response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        if (!(int)request()->post('conversation_id')) (new ChatWorkspaceService())->bump((new ConversationService())->ensureDirectConversation($currentUserId, $contactId));
        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['deleted_count'] = (int)($result['deleted_count'] ?? 0);
        $payload['message'] = (int)($result['deleted_count'] ?? 0) > 1
            ? return_translation('chat_messages_deleted')
            : return_translation('chat_message_deleted');

        response()->json($payload);
    }

    /**
     * Очищает весь диалог между текущим пользователем и выбранным контактом.
     */
    public function clearConversation()
    {
        if ((int)request()->post('conversation_id') > 0) return $this->groupMessageAction('clear');
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');

        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        try {
            $result = $this->chatMessages->clearConversation($currentUserId, $contactId, [
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

        if (!(int)request()->post('conversation_id')) (new ChatWorkspaceService())->bump((new ConversationService())->ensureDirectConversation($currentUserId, $contactId));
        $payload = $this->buildConversationPayload($currentUserId, $contactId);
        $payload['cleared_count'] = (int)($result['deleted_count'] ?? 0);
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

        $permissions = $this->chatMessages->getPermissionsForRole((string)($currentUser['role'] ?? 'user'));
        if (empty($permissions['can_view_audit'])) {
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
     * Удаляет аудит выбранного диалога. Действие доступно только создателю сайта.
     */
    public function clearAudit()
    {
        $currentUser = get_user();
        $currentUserId = (int)$currentUser['id'];
        $contactId = (int)request()->post('user_id');

        $permissions = $this->chatMessages->getPermissionsForRole((string)($currentUser['role'] ?? 'user'));
        if (empty($permissions['can_delete_audit'])) {
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

        try {
            $deletedCount = $this->chatMessages->clearAuditLogForConversation($currentUserId, $contactId);
        } catch (\Throwable $exception) {
            response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        response()->json([
            'status' => true,
            'deleted_count' => $deletedCount,
            'message' => return_translation('chat_audit_cleared'),
        ]);
    }


    /**
     * Создаёт новый групповой диалог из доступных текущему пользователю контактов.
     */
    public function createGroup()
    {
        $currentUser = get_user();
        $currentUserId = (int)$currentUser['id'];
        $title = trim((string)request()->post('title'));
        $rawMemberIds = $_POST['member_ids'] ?? [];
        $memberIds = is_array($rawMemberIds) ? $rawMemberIds : [$rawMemberIds];

        if (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_group_name_invalid'),
            ], 422);
        }

        $allowedContacts = $this->chatMessages->getContactsForUser(
            $currentUserId,
            $this->isPrivilegedChatUser()
        );
        $allowedIds = array_map(
            static fn (array $contact): int => (int)$contact['id'],
            $allowedContacts
        );

        $memberIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $memberIds
        ))));
        $memberIds = array_values(array_intersect($memberIds, $allowedIds));

        if (count($memberIds) < 2) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_group_min_members'),
            ], 422);
        }

        try {
            $conversationId = (new GroupChatService())->createGroup(
                $currentUserId,
                $title,
                $memberIds
            );
        } catch (\Throwable $exception) {
            log_error_details('Group chat create failed', [
                'creator_id' => $currentUserId,
                'member_ids' => $memberIds,
            ], $exception);

            response()->json([
                'status' => false,
                'message' => return_translation('chat_group_create_error'),
            ], 422);
        }

        response()->json([
            'status' => true,
            'conversation_id' => $conversationId,
            'url' => base_href('/chat/group?conversation_id=' . $conversationId),
        ]);
    }

    /**
     * Показывает отдельный интерфейс группового диалога.
     */
    public function group()
    {
        return $this->index();
    }

    /**
     * Возвращает сообщения группового диалога и отмечает их прочитанными.
     */
    public function groupMessages()
    {
        $id = (int)request()->get('conversation_id');
        $userId = (int)get_user()['id'];
        $groups = new GroupChatService();
        if (!$groups->isMember($id, $userId)) response()->json(['status' => false, 'message' => return_translation('chat_group_access_denied')], 403);
        $groups->markRead($id, $userId);
        session()->close();
        response()->json($this->buildConversationPayload($userId, $id));
    }

    /**
     * Отправляет текстовое сообщение в групповой диалог.
     */
    public function groupSend()
    {
        return $this->send();
    }

    /**
     * SSE для активного группового диалога.
     */
    public function groupStream()
    {
        $currentUserId = (int)get_user()['id'];
        $conversationId = (int)request()->get('conversation_id');
        $groups = new GroupChatService();

        if (!$groups->isMember($conversationId, $currentUserId)) {
            response()->text('', 403);
        }

        $this->users->touchPresence($currentUserId);
        session()->close();

        (new GroupChatRealtimeService())->stream(
            $currentUserId,
            $conversationId
        );
    }

    /**
     * Обновляет краткоживущее состояние "печатает" для direct-диалога.
     */
    public function typing()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->post('user_id');
        $groupId = max(0, (int)request()->post('conversation_id'));
        $isTyping = (int)request()->post('typing') === 1;
        $this->users->touchPresence($currentUserId);

        if (!($groupId ? (new GroupChatService())->isMember($groupId, $currentUserId) : $this->isAllowedContact($currentUserId, $contactId))) {
            response()->json([
                'status' => false,
                'message' => return_translation('chat_access_denied'),
            ], 403);
        }

        $conversationId = $groupId ?: (new ConversationService())->ensureDirectConversation(
            $currentUserId,
            $contactId
        );

        $updated = (new ChatTypingService())->setTyping(
            $conversationId,
            $currentUserId,
            $isTyping
        );

        response()->json([
            'status' => $updated,
            'typing' => $isTyping && $updated,
        ], $updated ? 200 : 503);
    }

    /** SSE realtime for the active direct conversation. */
    public function stream()
    {
        $currentUserId = (int)get_user()['id'];
        $contactId = (int)request()->get('user_id');
        $this->users->touchPresence($currentUserId);
        if (!$this->isAllowedContact($currentUserId, $contactId)) {
            response()->text('', 403);
        }
        session()->close();
        (new ChatRealtimeService())->streamDirectConversation($currentUserId, $contactId);
    }

    /**
     * Возвращает счётчики непрочитанных сообщений и обновлённый список контактов.
     */
    public function unreadCount()
    {
        $currentUserId = (int)get_user()['id'];
        $this->users->touchPresence($currentUserId);
        session()->close();

        response()->json([
            'status' => true,
            'unread_count' => $this->totalChatUnread($currentUserId),
            'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId),
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, $this->isPrivilegedChatUser()),
            'groups' => (new GroupChatService())->listForUser($currentUserId),
        ]);
    }

    /**
     * Собирает стандартный JSON-ответ для выбранного диалога.
     */
    protected function buildConversationPayload(int $currentUserId, int $contactId): array
    {
        $currentUser = get_user();
        $groupId = max(0, (int)(request()->post('conversation_id') ?: request()->get('conversation_id')));
        $workspace = new ChatWorkspaceService();
        if ($groupId) {
            $groups = new GroupChatService();
            $group = $groups->getForUser($groupId, $currentUserId);
            if (!$group) response()->json(['status' => false, 'message' => 'Нет доступа к группе.'], 403);
            $members = $groups->getMembers($groupId);
            $typing = db()->query('SELECT t.typing_until, u.name FROM chat_typing_states t JOIN users u ON u.id = t.user_id JOIN chat_members cm ON cm.user_id = t.user_id AND cm.conversation_id = t.conversation_id WHERE t.conversation_id = ? AND t.user_id <> ? AND t.typing_until > NOW()', [$groupId, $currentUserId])->get() ?: [];
            return ['status' => true, 'current_user_id' => $currentUserId,
                'permissions' => (new GroupChatManagementService())->permissions($group['role']),
                'revision' => (int)$group['revision'], 'messages' => $groups->getMessages($groupId, $currentUserId), 'group' => $group, 'members' => $members,
                'typing' => ['is_typing' => !empty($typing), 'names' => array_column($typing, 'name')],
                'preferences' => $workspace->preferences($groupId, $currentUserId),
                'unread_count' => $this->totalChatUnread($currentUserId),
                'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId)];
        }
        $conversationId = (new ConversationService())->ensureDirectConversation($currentUserId, $contactId);

        return [
            'status' => true,
            'current_user_id' => $currentUserId,
            'permissions' => $this->chatMessages->getPermissionsForRole((string)($currentUser['role'] ?? 'user')),
            'unread_count' => $this->totalChatUnread($currentUserId),
            'contact_unread_counts' => $this->chatMessages->getUnreadCountsByContactForUser($currentUserId),
            'messages' => $this->chatMessages->getConversationMessages($currentUserId, $contactId),
            'contacts' => $this->chatMessages->getContactsForUser($currentUserId, $this->isPrivilegedChatUser()),
            'contact' => $this->users->getPresenceForChat($contactId),
            'preferences' => $workspace->preferences($conversationId, $currentUserId),
            'revision' => (int)db()->query('SELECT revision FROM chat_conversations WHERE id = ?', [$conversationId])->getColumn(),
        ];
    }

    protected function groupMessageAction(string $action): void
    {
        $id = (int)request()->post('conversation_id');
        $userId = (int)get_user()['id'];
        $database = db();
        try {
            $database->beginTransaction();
            $mediaPaths = (new GroupChatManagementService())->messageAction($id, $userId, $action, $_POST);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) $database->rollBack();
            response()->json(['status' => false, 'message' => $error->getMessage()], 403);
        }
        foreach ($mediaPaths as $path) { try { (new ChatMediaStorage())->delete($path); } catch (\Throwable $error) { log_error_details('Deleted group media cleanup failed', ['conversation_id' => $id], $error); } }
        response()->json($this->buildConversationPayload($userId, $id));
    }

    public function groupManage(): void
    {
        $id = (int)request()->post('conversation_id');
        $userId = (int)get_user()['id'];
        $data = $_POST;
        $newPath = null;
        $oldPath = null;
        $mediaPaths = [];
        $database = db();
        try {
            $database->beginTransaction();
            db()->query("SELECT id FROM chat_conversations WHERE id = ? AND type = 'group' FOR UPDATE", [$id]);
            $group = (new GroupChatService())->getForUser($id, $userId);
            if (!$group) throw new \RuntimeException('Нет доступа к группе.');
            $action = (string)request()->post('action');
            if ($action === 'add' && !$this->isAllowedContact($userId, (int)request()->post('member_id'))) throw new \RuntimeException('Нельзя добавить этого пользователя.');
            $file = $action === 'update' ? ($_FILES['avatar'] ?? null) : null;
            if ($file && (int)$file['error'] !== UPLOAD_ERR_NO_FILE) {
                if (!in_array($group['role'], ['owner', 'admin'], true) || (int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Не удалось загрузить аватар (максимум 5 МБ).');
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || !getimagesize($file['tmp_name'])) throw new \RuntimeException('Выберите изображение JPEG, PNG, WebP или GIF.');
                $newPath = (new ChatMediaStorage())->store($file['tmp_name']);
                $data['avatar_path'] = $newPath;
                $oldPath = $group['avatar_path'];
            } elseif ($action === 'update' && !empty($data['remove_avatar'])) {
                $data['avatar_path'] = null;
                $oldPath = $group['avatar_path'];
            }
            $mediaPaths = (new GroupChatManagementService())->manage($id, $userId, (string)request()->post('action'), $data);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) $database->rollBack();
            if ($newPath) (new ChatMediaStorage())->delete($newPath);
            response()->json(['status' => false, 'message' => $error->getMessage()], 403);
        }
        if ($oldPath) { try { (new ChatMediaStorage())->delete($oldPath); } catch (\Throwable $error) { log_error_details('Old group avatar cleanup failed', ['conversation_id' => $id], $error); } }
        foreach ($mediaPaths as $path) { try { (new ChatMediaStorage())->delete($path); } catch (\Throwable $error) { log_error_details('Deleted group media cleanup failed', ['conversation_id' => $id], $error); } }
        if (!(new GroupChatService())->isMember($id, $userId)) response()->json(['status' => true, 'redirect' => base_href('/chat')]);
        response()->json($this->buildConversationPayload($userId, $id));
    }

    public function groupAvatar(): void
    {
        $id = (int)request()->get('conversation_id');
        $group = (new GroupChatService())->getForUser($id, (int)get_user()['id']);
        if (!$group || empty($group['avatar_path'])) response()->text('', 404);
        session()->close();
        try {
            $storage = new ChatMediaStorage();
            // Avatar uploads are bounded at 5 MB and validated as raster images.
            $bytes = $storage->readRange($group['avatar_path']);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            header('Content-Type: ' . $mime);
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            echo $bytes;
            exit;
        } catch (\Throwable $error) {
            response()->text('', 404);
        }
    }

    protected function workspaceConversation(): int
    {
        $userId = (int)get_user()['id'];
        $groupId = (int)(request()->post('conversation_id') ?: request()->get('conversation_id'));
        if ($groupId) {
            if (!(new GroupChatService())->isMember($groupId, $userId)) response()->json(['status' => false, 'message' => 'Нет доступа к группе.'], 403);
            return $groupId;
        }
        $contactId = (int)(request()->post('user_id') ?: request()->get('user_id'));
        if (!$this->isAllowedContact($userId, $contactId)) response()->json(['status' => false, 'message' => return_translation('chat_access_denied')], 403);
        return (new ConversationService())->ensureDirectConversation($userId, $contactId);
    }

    public function workspace(): void
    {
        $id = $this->workspaceConversation();
        $changes = array_intersect_key($_POST, array_flip(['pinned', 'muted', 'archived', 'draft']));
        response()->json(['status' => true, 'preferences' => (new ChatWorkspaceService())->update($id, (int)get_user()['id'], $changes)]);
    }

    public function history(): void
    {
        $id = $this->workspaceConversation();
        $userId = (int)get_user()['id'];
        $selected = request()->get('message_ids');
        $ids = $selected !== null
            ? array_values(array_unique(array_filter(array_map('intval', array_slice((array)$selected, 0, 300)), static fn(int $value): bool => $value > 0)))
            : (new ChatWorkspaceService())->historyIds($id, $userId, mb_substr((string)request()->get('q'), 0, 200), max(0, (int)request()->get('before')), (int)request()->get('media') === 1);
        $limit = $selected !== null ? 300 : 100;
        $group = (new GroupChatService())->getForUser($id, $userId);
        $messages = $group ? (new GroupChatService())->getMessages($id, $userId, $limit, $ids)
            : $this->chatMessages->getConversationMessages($userId, (int)request()->get('user_id'), $limit, $ids);
        session()->close();
        response()->json(['status' => true, 'messages' => $messages, 'next_before' => $selected === null && count($ids) === 100 ? min($ids) : null]);
    }

    public function forward(): void
    {
        $sourceId = $this->workspaceConversation();
        $userId = (int)get_user()['id'];
        $messageId = (int)request()->post('message_id');
        $message = db()->query('SELECT m.*, u.name AS sender_name FROM chat_messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.id = ? AND m.conversation_id = ? AND m.deleted_at IS NULL', [$messageId, $sourceId])->getOne();
        if (!$message) response()->json(['status' => false, 'message' => 'Сообщение недоступно.'], 403);
        $groupId = (int)request()->post('target_group_id');
        $contactId = (int)request()->post('target_user_id');
        if ($groupId ? !(new GroupChatService())->isMember($groupId, $userId) : !$this->isAllowedContact($userId, $contactId)) response()->json(['status' => false, 'message' => 'Нет доступа к получателю.'], 403);
        $attachment = null;
        $path = null;
        $database = db();
        try {
            if (!empty($message['attachment_path'])) {
                // Copy encrypted bytes to a new opaque object; deleting the source cannot break the forward.
                $path = (new ChatMediaStorage())->copy($message['attachment_path']);
                $attachment = ['path' => $path, 'name' => $message['attachment_name'], 'type' => $message['attachment_type'], 'size' => (int)$message['attachment_size']];
            }
            $text = ChatCipher::decrypt((string)$message['message_ciphertext']);
            $database->beginTransaction();
            $id = $groupId ? (new GroupChatService())->createMessage($groupId, $userId, $text, $this->getRequestContext(), $attachment)
                : $this->chatMessages->create($userId, $contactId, $text, $attachment, $this->getRequestContext());
            db()->query('UPDATE chat_messages SET forwarded_label = ? WHERE id = ?', [mb_substr((string)($message['forwarded_label'] ?: $message['sender_name']), 0, 190), $id]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) $database->rollBack();
            if ($path) (new ChatMediaStorage())->delete($path);
            log_error_details('Chat forwarding failed', ['message_id' => $messageId, 'conversation_id' => $sourceId], $error);
            response()->json(['status' => false, 'message' => 'Не удалось переслать сообщение.'], 422);
        }
        if ($groupId) $this->notifyGroupRecipients($groupId, $userId, $text);
        else $this->notifyChatRecipient($userId, $contactId, $text, $attachment ? [$attachment] : []);
        response()->json(['status' => true]);
    }

    protected function totalChatUnread(int $userId): int
    {
        return $this->chatMessages->getUnreadCountForUser($userId);
    }

    protected function notifyGroupRecipients(int $id, int $senderId, string $text): void
    {
        $group = (new GroupChatService())->getForUser($id, $senderId);
        $recipients = db()->query('SELECT user_id FROM chat_members WHERE conversation_id = ? AND user_id <> ? AND (muted_until IS NULL OR muted_until <= NOW())', [$id, $senderId])->get() ?: [];
        foreach ($recipients as $recipient) {
            try {
                NotificationService::create(['user_id' => (int)$recipient['user_id'], 'title' => (string)$group['title'], 'message' => get_user()['name'] . ': ' . mb_substr($text ?: return_translation('chat_attachment_label'), 0, 200), 'type' => 'chat', 'source' => 'chat', 'action_url' => '/chat/group?conversation_id=' . $id, 'store_unread' => false]);
            } catch (\Throwable $error) {
                log_error_details('Group notification dispatch failed', ['conversation_id' => $id], $error);
            }
        }
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

            try {
                $file->validate(null, array_values(array_diff($this->getAllowedAttachmentExtensions(), $this->getBlockedAttachmentExtensions())));
            } catch (\App\Services\UploadException $exception) {
                $errors[] = $exception->getMessage();
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
        $storage = new ChatMediaStorage();

        try {
            foreach ($files as $file) {
                if (!$file instanceof File || !$file->isFile) {
                    continue;
                }

                $extension = strtolower($file->getExt());
                $attachments[] = [
                    'path' => $storage->store($file->getTmpName()),
                    'name' => $this->normalizeAttachmentName($file->getName()),
                    'type' => $this->resolveAttachmentMimeType($extension, $file->getType()),
                    'size' => $file->getSize(),
                ];
            }
        } catch (\Throwable $exception) {
            $this->cleanupStoredAttachments($attachments);
            throw $exception;
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
            if ($size > UploadSettings::maxFileSizeBytes()) {
                $errors[] = return_translation('chat_file_size_error');
                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true) && !@getimagesize($absolutePath)) {
                $errors[] = return_translation('chat_file_type_error');
                continue;
            }

            try {
                (new SafeUploadService())->validate(
                    $absolutePath,
                    basename($path),
                    $size,
                    UploadSettings::maxFileSizeBytes(),
                    $allowedExtensions
                );
            } catch (\RuntimeException) {
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
        $storage = new ChatMediaStorage();

        try {
            foreach ($paths as $path) {
                $absolutePath = $this->getSiteAttachmentAbsolutePath($path);
                if ($absolutePath === '' || !is_file($absolutePath)) {
                    throw new \RuntimeException('Selected site attachment is unavailable.');
                }

                $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
                $attachments[] = [
                    'path' => $storage->store($absolutePath),
                    'name' => $this->normalizeAttachmentName(basename($path)),
                    'type' => $this->resolveAttachmentMimeType($extension, (string)(@mime_content_type($absolutePath) ?: 'application/octet-stream')),
                    'size' => (int)@filesize($absolutePath),
                ];
            }
        } catch (\Throwable $exception) {
            $this->cleanupStoredAttachments($attachments);
            throw $exception;
        }

        return $attachments;
    }

    /**
     * Удаляет уже зашифрованные файлы, если отправка сообщения не завершилась.
     */
    protected function cleanupStoredAttachments(array $attachments): void
    {
        $storage = new ChatMediaStorage();
        foreach ($attachments as $attachment) {
            $storage->delete((string)($attachment['path'] ?? ''));
        }
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

        $uploadsRoot = realpath(UPLOADS);
        $absolutePath = realpath(rtrim(UPLOADS, '/') . '/' . $relativePath);
        if ($uploadsRoot === false || $absolutePath === false) {
            return '';
        }

        $uploadsPrefix = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $absolutePath);
        if (!str_starts_with($normalizedPath, $uploadsPrefix)) {
            return '';
        }

        return $absolutePath;
    }

    /**
     * Нормализует имя вложения для заголовков скачивания и поля VARCHAR(255).
     */
    protected function normalizeAttachmentName(string $name): string
    {
        $name = basename(str_replace('\\', '/', str_replace(["\0", "\r", "\n"], '', trim($name))));
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }

        return mb_substr($name, 0, 255);
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

        if ($extension === 'webm' && str_starts_with(strtolower($fallbackType), 'audio/')) {
            return 'audio/webm';
        }

        return $mimeTypes[$extension] ?? $fallbackType;
    }

    protected function notifyChatRecipient(int $senderId, int $receiverId, string $message, array $attachments): void
    {
        if ($senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId) {
            return;
        }

        $conversationId = (new ConversationService())->findDirectConversationId($senderId, $receiverId);
        if ($conversationId && (new ChatWorkspaceService())->preferences($conversationId, $receiverId)['muted']) return;
        try {
            $sender = get_user();
            $preview = trim($message);
            if ($preview === '' && !empty($attachments[0]['name'])) {
                $preview = return_translation('chat_attachment_label') . ': ' . (string)$attachments[0]['name'];
            }
            if ($preview === '') {
                $preview = return_translation('chat_message_sent');
            }

            NotificationService::create([
                'user_id' => $receiverId,
                'title' => (string)($sender['name'] ?? return_translation('notification_chat_fallback_title')),
                'message' => mb_substr($preview, 0, 240),
                'type' => 'chat',
                'action_url' => '/chat?user_id=' . $senderId,
                'source' => 'chat',
                'priority' => 'normal',
                'metadata' => [
                    'sender_id' => $senderId,
                ],
                'store_unread' => false,
            ]);
        } catch (\Throwable $exception) {
            log_error_details('Chat notification dispatch failed', [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
            ], $exception);
        }
    }

}
