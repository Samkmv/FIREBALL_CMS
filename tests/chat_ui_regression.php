<?php

function chatUiAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$projectRoot = dirname(__DIR__);
$controller = (string)file_get_contents($projectRoot . '/app/Controllers/ChatController.php');
$view = (string)file_get_contents($projectRoot . '/app/Views/themes/default/chat/index.php');
$javascript = (string)file_get_contents($projectRoot . '/public/assets/default/js/chat.js');
$stylesheet = (string)file_get_contents($projectRoot . '/public/assets/default/css/style.css');
$mainJavascript = (string)file_get_contents($projectRoot . '/public/assets/default/js/main.js');
$routes = (string)file_get_contents($projectRoot . '/config/routes.php');
$model = (string)file_get_contents($projectRoot . '/app/Models/ChatMessage.php');

$messagesStart = strpos($controller, 'public function messages()');
$mediaStart = strpos($controller, 'public function media()');
$sendStart = strpos($controller, 'public function send()');
$deleteStart = strpos($controller, 'public function deleteMessages()');

chatUiAssert($messagesStart !== false && $mediaStart !== false, 'Chat messages action could not be inspected.');
chatUiAssert($sendStart !== false && $deleteStart !== false, 'Chat send action could not be inspected.');

$messagesAction = substr($controller, $messagesStart, $mediaStart - $messagesStart);
$sendAction = substr($controller, $sendStart, $deleteStart - $sendStart);

chatUiAssert(!str_contains($messagesAction, 'mb_strlen($message)'), 'Message length validation still breaks chat history loading.');
chatUiAssert(str_contains($sendAction, 'mb_strlen($message)'), 'Outgoing chat messages are missing server-side length validation.');
chatUiAssert(str_contains($view, 'data-load-error-text'), 'Chat load errors are not localized in the view.');
chatUiAssert(str_contains($javascript, 'const showFlashAlert ='), 'Chat notifications do not use the shared flash alert adapter.');
chatUiAssert(!preg_match('/\btoastr\.(?:success|error|info|warning)\s*\(/', $javascript), 'Chat still bypasses the shared flash alert adapter.');
chatUiAssert(str_contains($javascript, "showFlashAlert('success', response.message || '')"), 'Successful message sends do not show a flash alert.');
chatUiAssert(str_contains($javascript, "chatApp[0].style.setProperty('--chat-composer-growth'"), 'Mobile composer growth is not synchronized with the chat shell.');
chatUiAssert(str_contains($stylesheet, 'height: calc(var(--chat-shell-height) + var(--chat-composer-growth));'), 'The mobile chat shell does not grow downward with the composer.');
chatUiAssert(str_contains($stylesheet, '.chat-voice-recorder__actions > .btn'), 'Mobile voice recorder actions have no compact sizing.');
chatUiAssert(str_contains($stylesheet, 'white-space: nowrap;'), 'Mobile voice recorder action labels can wrap to multiple lines.');
chatUiAssert(str_contains($view, "print_translation('chat_voice_stop_short')"), 'The mobile voice stop button still uses its oversized full label.');
chatUiAssert(
    str_contains($routes, "post('/chat/conversation/audit/clear', [ChatController::class, 'clearAudit'])->middleware(['auth', 'creator'])"),
    'Creator-only chat audit cleanup route is missing.'
);
chatUiAssert(str_contains($view, 'data-chat-clear-audit'), 'Creator chat audit cleanup control is missing.');
chatUiAssert(str_contains($javascript, 'const runClearAudit ='), 'Creator chat audit cleanup request is missing.');
chatUiAssert(str_contains($model, "'can_delete_audit' => \$role === 'creator'"), 'Chat audit deletion is not restricted to the creator role.');
chatUiAssert(str_contains($model, 'clearAuditLogForConversation'), 'Conversation-scoped chat audit cleanup is missing.');

$showToastStart = strpos($mainJavascript, 'const showToast =');
$showChatToastStart = strpos($mainJavascript, 'const showChatToast =');
chatUiAssert($showToastStart !== false && $showChatToastStart !== false, 'Dynamic flash alert template could not be inspected.');
$showToastTemplate = substr($mainJavascript, $showToastStart, $showChatToastStart - $showToastStart);
chatUiAssert(str_contains($showToastTemplate, '<div class="d-flex align-items-start">'), 'Dynamic alerts do not use the system flash alert layout.');
chatUiAssert(str_contains($showToastTemplate, '<strong class="d-block mb-1">'), 'Dynamic alerts are missing the system flash alert title layout.');
chatUiAssert(!str_contains($showToastTemplate, 'toast-header'), 'Dynamic alerts still use the non-system split header layout.');
chatUiAssert(!str_contains($showToastTemplate, "setAttribute('data-bs-theme'"), 'Dynamic alerts still override the active system theme.');

echo "Chat UI regression checks passed.\n";
