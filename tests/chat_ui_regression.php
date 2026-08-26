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
chatUiAssert(str_contains($javascript, "if (type !== 'error')"), 'Chat flash alerts are not restricted to critical errors.');
chatUiAssert(!str_contains($javascript, "showFlashAlert('success'"), 'Successful chat actions still obstruct the conversation with flash alerts.');
chatUiAssert(!str_contains($javascript, "showFlashAlert('info'"), 'Informational chat actions still obstruct the conversation with flash alerts.');
chatUiAssert(str_contains($javascript, "showFlashAlert('error', response.message || 'Message could not be sent.')"), 'Message send failures no longer show a critical alert.');
chatUiAssert(str_contains($javascript, "chatApp[0].style.setProperty('--chat-composer-growth'"), 'Mobile composer growth is not synchronized with the chat shell.');
chatUiAssert(str_contains($stylesheet, 'height: calc(var(--chat-shell-height) + var(--chat-composer-growth));'), 'The mobile chat shell does not grow downward with the composer.');
chatUiAssert(str_contains($javascript, "classList.add('chat-viewport-fullscreen')"), 'The chat page does not enter viewport fullscreen mode.');
chatUiAssert(str_contains($javascript, "classList.toggle('chat-mobile-fullscreen', isMobile)"), 'The chat page does not retain its mobile-only compact state.');
chatUiAssert(str_contains($javascript, 'window.visualViewport.addEventListener'), 'The mobile chat does not adapt to the on-screen keyboard.');
chatUiAssert(str_contains($stylesheet, 'html.chat-viewport-fullscreen .chat-page {'), 'The chat has no fixed viewport shell.');
chatUiAssert(str_contains($stylesheet, 'overflow: hidden !important;'), 'The outer mobile chat page can still scroll.');
chatUiAssert(str_contains($javascript, "chatApp.toggleClass('is-selection-mode'"), 'Mobile selection mode has no compact layout state.');
chatUiAssert(str_contains($javascript, "document.body.classList.add('chat-sidebar-open')"), 'The chat sidebar cannot rise above the site navbar.');
chatUiAssert(str_contains($view, 'data-chat-selection-count'), 'Mobile selection mode has no compact counter.');
chatUiAssert(str_contains($view, 'class="chat-sidebar__close btn btn-outline-secondary rounded-circle'), 'The mobile chat sidebar has no visible close button.');
chatUiAssert(strpos($view, '</main>') < strpos($view, 'id="accountSidebar"'), 'Chat overlays remain trapped inside the fixed fullscreen stacking context.');
chatUiAssert(str_contains($stylesheet, '[data-chat-app].is-selection-mode .chat-thread__searchbar'), 'Mobile selection actions are not kept in one row.');
chatUiAssert(str_contains($stylesheet, '[data-chat-app].is-selection-mode .chat-thread__toolbar'), 'Normal chat tools remain visible during mobile selection.');
chatUiAssert(str_contains($stylesheet, '[data-chat-app].is-selection-mode .chat-thread__composer'), 'The message composer still occupies space during mobile selection.');
chatUiAssert(str_contains($stylesheet, 'body.chat-sidebar-open .offcanvas-backdrop'), 'The chat sidebar backdrop remains below the site navbar.');
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
