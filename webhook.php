<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/format.php';
require __DIR__ . '/includes/keyboards.php';
require __DIR__ . '/includes/state.php';
require __DIR__ . '/includes/panel_api.php';
require __DIR__ . '/includes/crud.php';
require __DIR__ . '/includes/agency.php';
require __DIR__ . '/includes/crypto_checker.php';
require __DIR__ . '/includes/ton_checker.php';
require __DIR__ . '/includes/fulfillment.php';
require __DIR__ . '/handlers/user.php';
require __DIR__ . '/handlers/admin.php';

// Optional but recommended: Telegram sends this header when a secret token
// was set on setWebhook (installer.php does this automatically).
if (defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '') {
    $incoming = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals(WEBHOOK_SECRET, $incoming)) {
        http_response_code(403);
        exit;
    }
}

maybe_upgrade_schema();

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!$update) {
    http_response_code(200);
    exit;
}

function is_admin(int $userId): bool
{
    return in_array($userId, ADMIN_IDS, true);
}

try {
    if (isset($update['message'])) {
        handle_message($update['message']);
    } elseif (isset($update['callback_query'])) {
        handle_callback_query($update['callback_query']);
    }
} catch (Throwable $e) {
    bot_log('Webhook', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}

http_response_code(200);

// ---------------------------------------------------------------------

function handle_message(array $message): void
{
    $from = $message['from'];
    $userId = (int) $from['id'];
    $chatId = (int) $message['chat']['id'];
    $text = $message['text'] ?? null;
    $fullName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    $username = $from['username'] ?? null;

    if (!is_admin($userId) && force_join_block($userId, $chatId)) {
        return;
    }

    get_or_create_customer($userId, $username, $fullName);

    if ($text === '/start') {
        clear_state($userId);
        tg_send_message($chatId, get_text('welcome', ['full_name' => $fullName ?: 'دوست']), build_main_menu_keyboard());
        return;
    }

    if ($text === '/log' && is_admin($userId)) {
        tg_send_message($chatId, '<code>' . htmlspecialchars(bot_log_tail(25)) . '</code>');
        return;
    }

    if ($text === '/admin') {
        if (!is_admin($userId)) {
            return;
        }
        clear_state($userId);
        admin_send_main_menu($chatId);
        return;
    }

    $state = get_state($userId);

    if ($state['state'] !== null && str_starts_with($state['state'], 'admin_')) {
        if (is_admin($userId)) {
            admin_handle_state_message($state['state'], $state['data'], $message, $userId, $chatId);
        }
        return;
    }

    // Tapping a main-menu button while a question is pending cancels that question.
    if ($state['state'] !== null && str_starts_with($state['state'], 'user_') && $text !== null && user_menu_key_for($text) !== null) {
        clear_state($userId);
        user_dispatch_main_menu($text, $userId, $chatId, $fullName);
        return;
    }

    if ($state['state'] !== null && str_starts_with($state['state'], 'user_')) {
        user_handle_state_message($state['state'], $state['data'], $message, $userId, $chatId, $fullName, $username);
        return;
    }

    // Photo receipt (card payment proof), no active state required
    if (isset($message['photo'])) {
        user_handle_receipt($message, $userId, $chatId);
        return;
    }

    // No active state: try to match one of the (fully customizable) main-menu buttons
    if ($text !== null) {
        user_dispatch_main_menu($text, $userId, $chatId, $fullName);
    }
}

function handle_callback_query(array $callback): void
{
    $from = $callback['from'];
    $userId = (int) $from['id'];
    $data = $callback['data'] ?? '';
    $callbackId = $callback['id'];
    $message = $callback['message'] ?? null;
    $chatId = $message ? (int) $message['chat']['id'] : $userId;
    $messageId = $message ? (int) $message['message_id'] : 0;
    $fullName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    $username = $from['username'] ?? null;

    if ($data === 'force_join:check') {
        force_join_recheck($userId, $chatId, $messageId, $callbackId, $fullName);
        return;
    }

    if (!is_admin($userId) && force_join_block($userId, $chatId)) {
        tg_answer_callback($callbackId);
        return;
    }

    get_or_create_customer($userId, $username, $fullName);

    if (str_starts_with($data, 'adm:')) {
        if (!is_admin($userId)) {
            tg_answer_callback($callbackId, 'دسترسی نداری.', true);
            return;
        }
        admin_handle_callback($data, $userId, $chatId, $messageId, $callbackId, $message);
        return;
    }

    user_handle_callback($data, $userId, $chatId, $messageId, $callbackId, $fullName, $username);
}

// ---------------------------------------------------------------------
// Mandatory channel join gate
// ---------------------------------------------------------------------

function force_join_block(int $userId, int $chatId): bool
{
    $channels = list_active_channels();
    if (!$channels) {
        return false;
    }
    $notJoined = [];
    foreach ($channels as $channel) {
        $result = tg_get_chat_member($channel['chat_id'], $userId);
        if (!($result['ok'] ?? false)) {
            continue; // bot probably isn't admin there yet — don't block real users over a misconfiguration
        }
        $status = $result['result']['status'] ?? '';
        if (in_array($status, ['left', 'kicked'], true)) {
            $notJoined[] = $channel;
        }
    }
    if (!$notJoined) {
        return false;
    }
    $rows = [];
    foreach ($notJoined as $channel) {
        $rows[] = [tg_button($channel['title'], null, $channel['invite_link'])];
    }
    $rows[] = [tg_button(get_text('channels.check_button'), 'force_join:check')];
    tg_send_message($chatId, get_text('channels.join_required'), tg_inline_markup($rows));
    return true;
}

function force_join_recheck(int $userId, int $chatId, int $messageId, string $callbackId, string $fullName): void
{
    $channels = list_active_channels();
    $stillMissing = false;
    foreach ($channels as $channel) {
        $result = tg_get_chat_member($channel['chat_id'], $userId);
        if (!($result['ok'] ?? false)) {
            continue;
        }
        $status = $result['result']['status'] ?? '';
        if (in_array($status, ['left', 'kicked'], true)) {
            $stillMissing = true;
            break;
        }
    }
    if ($stillMissing) {
        tg_answer_callback($callbackId, get_text('channels.not_joined_yet'), true);
        return;
    }
    tg_answer_callback($callbackId);
    tg_send_message($chatId, get_text('welcome', ['full_name' => $fullName ?: 'دوست']), build_main_menu_keyboard());
}
