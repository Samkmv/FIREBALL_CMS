<?php

namespace App\Services;

/** Shared, membership-scoped conversation preferences and history. */
final class ChatWorkspaceService
{
    public function preferences(int $conversationId, int $userId): array
    {
        $row = db()->query('SELECT pinned, archived, muted_until, draft_ciphertext FROM chat_members WHERE conversation_id = ? AND user_id = ?', [$conversationId, $userId])->getOne() ?: [];
        $draft = ChatCipher::decrypt((string)($row['draft_ciphertext'] ?? ''));
        if (str_starts_with($draft, 'fb-draft-v1:')) { $decoded = json_decode(substr($draft, 12), true); if (is_string($decoded)) $draft = $decoded; }
        return [
            'pinned' => !empty($row['pinned']),
            'archived' => !empty($row['archived']),
            'muted' => !empty($row['muted_until']) && strtotime($row['muted_until']) > time(),
            'draft' => $draft,
        ];
    }

    public function update(int $conversationId, int $userId, array $changes): array
    {
        if (!(new ConversationService())->isMember($conversationId, $userId)) {
            throw new \RuntimeException('Нет доступа к диалогу.');
        }
        foreach (['pinned', 'archived'] as $field) {
            if (array_key_exists($field, $changes)) {
                db()->query("UPDATE chat_members SET {$field} = ? WHERE conversation_id = ? AND user_id = ?", [(int)(bool)$changes[$field], $conversationId, $userId]);
            }
        }
        if (array_key_exists('muted', $changes)) {
            db()->query('UPDATE chat_members SET muted_until = ? WHERE conversation_id = ? AND user_id = ?', [$changes['muted'] ? '2099-12-31 23:59:59' : null, $conversationId, $userId]);
        }
        if (array_key_exists('draft', $changes)) {
            $draft = mb_substr((string)$changes['draft'], 0, 2000);
            db()->query('UPDATE chat_members SET draft_ciphertext = ? WHERE conversation_id = ? AND user_id = ?', [$draft === '' ? null : ChatCipher::encrypt('fb-draft-v1:' . json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $conversationId, $userId]);
        }
        return $this->preferences($conversationId, $userId);
    }

    public function bump(int $conversationId): void
    {
        db()->query('UPDATE chat_conversations SET revision = revision + 1, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $conversationId]);
    }

    /** Search ciphertext in bounded batches, without persisting a plaintext index. */
    public function historyIds(int $conversationId, int $userId, string $query = '', int $before = 0, bool $media = false): array
    {
        if (!(new ConversationService())->isMember($conversationId, $userId)) {
            throw new \RuntimeException('Нет доступа к диалогу.');
        }
        $ids = [];
        $cursor = $before > 0 ? $before : PHP_INT_MAX;
        $needle = mb_strtolower(trim($query));
        do {
            $rows = db()->query('SELECT id, message_ciphertext, attachment_name FROM chat_messages WHERE conversation_id = ? AND deleted_at IS NULL AND id < ?' . ($media ? ' AND attachment_path IS NOT NULL' : '') . ' ORDER BY id DESC LIMIT 500', [$conversationId, $cursor])->get() ?: [];
            foreach ($rows as $row) {
                $cursor = (int)$row['id'];
                if ($needle === '' || mb_strpos(mb_strtolower(ChatCipher::decrypt((string)$row['message_ciphertext']) . ' ' . ($row['attachment_name'] ?? '')), $needle) !== false) {
                    $ids[] = $cursor;
                    if (count($ids) >= 100) break;
                }
            }
        } while (count($rows) === 500 && count($ids) < 100);
        return $ids;
    }
}
