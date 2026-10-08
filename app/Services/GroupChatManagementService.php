<?php

namespace App\Services;

final class GroupChatManagementService
{
    public function permissions(string $role): array
    {
        $manager = in_array($role, ['owner', 'admin'], true);
        return ['can_moderate' => $manager, 'can_bulk_delete' => $manager, 'can_clear_chat' => $manager,
            'can_view_audit' => false, 'can_delete_audit' => false, 'can_manage_group' => $manager, 'is_owner' => $role === 'owner'];
    }

    /** Caller wraps mutations in a transaction; lock the group to serialize role changes. */
    public function manage(int $id, int $actorId, string $action, array $data): array
    {
        db()->query("SELECT id FROM chat_conversations WHERE id = ? AND type = 'group' FOR UPDATE", [$id]);
        $group = (new GroupChatService())->getForUser($id, $actorId);
        if (!$group) throw new \RuntimeException('Нет доступа к группе.');
        $role = $group['role'];
        $manager = in_array($role, ['owner', 'admin'], true);
        $mediaPaths = [];
        $targetId = (int)($data['member_id'] ?? 0);
        $target = db()->query('SELECT role FROM chat_members WHERE conversation_id = ? AND user_id = ?', [$id, $targetId])->getColumn();
        if ($action === 'leave') {
            if ($role === 'owner') throw new \RuntimeException('Перед выходом передайте владение другому участнику.');
            db()->query('DELETE FROM chat_members WHERE conversation_id = ? AND user_id = ?', [$id, $actorId]);
        } elseif ($action === 'delete') {
            if ($role !== 'owner') throw new \RuntimeException('Удалить группу может только владелец.');
            $mediaPaths = array_column(db()->query('SELECT attachment_path FROM chat_messages WHERE conversation_id = ? AND attachment_path IS NOT NULL', [$id])->get() ?: [], 'attachment_path');
            if (!empty($group['avatar_path'])) $mediaPaths[] = $group['avatar_path'];
            db()->query('UPDATE chat_messages SET deleted_at = COALESCE(deleted_at, ?), deleted_by = COALESCE(deleted_by, ?), attachment_path = NULL, attachment_name = NULL, attachment_type = NULL, attachment_size = NULL WHERE conversation_id = ?', [date('Y-m-d H:i:s'), $actorId, $id]);
            db()->query('DELETE a FROM chat_attachments a JOIN chat_messages m ON m.id = a.message_id WHERE m.conversation_id = ?', [$id]);
            db()->query("UPDATE chat_conversations SET type = 'group_deleted', avatar_path = NULL WHERE id = ?", [$id]);
            db()->query('DELETE FROM chat_members WHERE conversation_id = ?', [$id]);
        } elseif ($action === 'transfer') {
            if ($role !== 'owner' || !$target || $targetId === $actorId) throw new \RuntimeException('Выберите участника для передачи владения.');
            db()->query("UPDATE chat_members SET role = 'admin' WHERE conversation_id = ? AND user_id = ?", [$id, $actorId]);
            db()->query("UPDATE chat_members SET role = 'owner' WHERE conversation_id = ? AND user_id = ?", [$id, $targetId]);
            db()->query('UPDATE chat_conversations SET created_by = ? WHERE id = ?', [$targetId, $id]);
        } elseif (!$manager) {
            throw new \RuntimeException('Недостаточно прав для управления группой.');
        } elseif ($action === 'update') {
            $title = trim((string)($data['title'] ?? ''));
            if (mb_strlen($title) < 2 || mb_strlen($title) > 100) throw new \RuntimeException('Название: от 2 до 100 символов.');
            db()->query('UPDATE chat_conversations SET title = ? WHERE id = ?', [$title, $id]);
            if (array_key_exists('avatar_path', $data)) db()->query('UPDATE chat_conversations SET avatar_path = ? WHERE id = ?', [$data['avatar_path'], $id]);
        } elseif ($action === 'add') {
            if ($targetId <= 0 || !db()->query('SELECT id FROM users WHERE id = ?', [$targetId])->getColumn()) throw new \RuntimeException('Участник не найден.');
            db()->query("INSERT IGNORE INTO chat_members (conversation_id, user_id, role, joined_at) VALUES (?, ?, 'member', ?)", [$id, $targetId, date('Y-m-d H:i:s')]);
        } elseif (in_array($action, ['remove', 'role'], true)) {
            if (!$target || $target === 'owner' || $targetId === $actorId || ($role !== 'owner' && $target === 'admin')) throw new \RuntimeException('Нельзя изменить этого участника.');
            if ($action === 'remove') {
                db()->query('DELETE FROM chat_members WHERE conversation_id = ? AND user_id = ?', [$id, $targetId]);
            } else {
                $newRole = (string)($data['role'] ?? '');
                if ($role !== 'owner' || !in_array($newRole, ['admin', 'member'], true)) throw new \RuntimeException('Изменять роли может только владелец.');
                db()->query('UPDATE chat_members SET role = ? WHERE conversation_id = ? AND user_id = ?', [$newRole, $id, $targetId]);
            }
        } else {
            throw new \RuntimeException('Неизвестное действие.');
        }
        // Revoked members cannot keep publishing typing state.
        db()->query('DELETE t FROM chat_typing_states t LEFT JOIN chat_members cm ON cm.conversation_id = t.conversation_id AND cm.user_id = t.user_id WHERE t.conversation_id = ? AND cm.user_id IS NULL', [$id]);
        (new ChatWorkspaceService())->bump($id);
        // Delete encrypted objects only after the caller commits the database transaction.
        return array_values(array_unique(array_filter($mediaPaths)));
    }

    public function messageAction(int $id, int $actorId, string $action, array $data): array
    {
        db()->query("SELECT id FROM chat_conversations WHERE id = ? AND type = 'group' FOR UPDATE", [$id]);
        $group = (new GroupChatService())->getForUser($id, $actorId);
        if (!$group) throw new \RuntimeException('Нет доступа к группе.');
        $messageIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['message_ids'] ?? [$data['message_id'] ?? 0])))));
        $manager = in_array($group['role'], ['owner', 'admin'], true);
        $mediaPaths = [];
        if ($action === 'clear') {
            if (!$manager) throw new \RuntimeException('Недостаточно прав.');
            $mediaPaths = array_column(db()->query('SELECT attachment_path FROM chat_messages WHERE conversation_id = ? AND deleted_at IS NULL AND attachment_path IS NOT NULL', [$id])->get() ?: [], 'attachment_path');
            db()->query('UPDATE chat_messages SET deleted_at = ?, deleted_by = ?, attachment_path = NULL, attachment_name = NULL, attachment_type = NULL, attachment_size = NULL WHERE conversation_id = ? AND deleted_at IS NULL', [date('Y-m-d H:i:s'), $actorId, $id]);
        } else {
            if (!$messageIds || count($messageIds) > 300) throw new \RuntimeException('Сообщение не найдено.');
            $marks = implode(',', array_fill(0, count($messageIds), '?'));
            $rows = db()->query("SELECT id, sender_id, attachment_path FROM chat_messages WHERE conversation_id = ? AND deleted_at IS NULL AND id IN ({$marks}) FOR UPDATE", array_merge([$id], $messageIds))->get() ?: [];
            if (count($rows) !== count($messageIds)) throw new \RuntimeException('Сообщение недоступно.');
            if ($action === 'edit') {
                $text = trim((string)($data['message'] ?? ''));
                if (count($rows) !== 1 || (int)$rows[0]['sender_id'] !== $actorId || mb_strlen($text) > 2000 || ($text === '' && !$rows[0]['attachment_path'])) throw new \RuntimeException('Можно редактировать только своё сообщение.');
                db()->query('UPDATE chat_messages SET message_ciphertext = ?, encryption_key_id = ?, edited_at = ? WHERE id = ?', [ChatCipher::encrypt($text), $text === '' ? null : ChatCipher::currentKeyId(), date('Y-m-d H:i:s'), $messageIds[0]]);
            } elseif ($action === 'react') {
                $reaction = (string)($data['reaction'] ?? '');
                if (count($rows) !== 1 || !in_array($reaction, ['👍', '❤️', '😂', '😮', '😢'], true)) throw new \RuntimeException('Некорректная реакция.');
                $current = db()->query('SELECT reaction FROM chat_reactions WHERE message_id = ? AND user_id = ?', [$messageIds[0], $actorId])->getColumn();
                db()->query('DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ?', [$messageIds[0], $actorId]);
                if ($current !== $reaction) db()->query('INSERT INTO chat_reactions (message_id, user_id, reaction, created_at) VALUES (?, ?, ?, ?)', [$messageIds[0], $actorId, $reaction, date('Y-m-d H:i:s')]);
            } elseif ($action === 'delete') {
                foreach ($rows as $row) {
                    if (!$manager && (count($rows) > 1 || (int)$row['sender_id'] !== $actorId)) throw new \RuntimeException('Можно удалить только своё сообщение.');
                }
                $mediaPaths = array_values(array_filter(array_column($rows, 'attachment_path')));
                db()->query("UPDATE chat_messages SET deleted_at = ?, deleted_by = ?, attachment_path = NULL, attachment_name = NULL, attachment_type = NULL, attachment_size = NULL WHERE id IN ({$marks})", array_merge([date('Y-m-d H:i:s'), $actorId], $messageIds));
            } else {
                throw new \RuntimeException('Неизвестное действие.');
            }
        }
        db()->query('DELETE a FROM chat_attachments a JOIN chat_messages m ON m.id = a.message_id WHERE m.conversation_id = ? AND m.deleted_at IS NOT NULL', [$id]);
        (new ChatWorkspaceService())->bump($id);
        return $mediaPaths;
    }
}
