<?php
// Thin wrapper around the Telegram Bot API. No SDK, just cURL + JSON —
// works on any shared host with the cURL extension enabled (virtually all do).

function tg_api(string $method, array $params = [])
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("Telegram API cURL error on $method: $error");
        return null;
    }
    $decoded = json_decode($response, true);
    if (!($decoded['ok'] ?? false)) {
        error_log("Telegram API error on $method: " . $response);
    }
    return $decoded;
}

function tg_send_message(int $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML')
{
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => $parseMode];
    if ($replyMarkup !== null) {
        $params['reply_markup'] = $replyMarkup;
    }
    return tg_api('sendMessage', $params);
}

function tg_edit_message(int $chatId, int $messageId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML')
{
    $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => $parseMode];
    if ($replyMarkup !== null) {
        $params['reply_markup'] = $replyMarkup;
    }
    return tg_api('editMessageText', $params);
}

function tg_edit_caption(int $chatId, int $messageId, string $caption, ?array $replyMarkup = null)
{
    $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($replyMarkup !== null) {
        $params['reply_markup'] = $replyMarkup;
    }
    return tg_api('editMessageCaption', $params);
}

function tg_send_photo(int $chatId, string $fileId, string $caption = '', ?array $replyMarkup = null)
{
    $params = ['chat_id' => $chatId, 'photo' => $fileId, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($replyMarkup !== null) {
        $params['reply_markup'] = $replyMarkup;
    }
    return tg_api('sendPhoto', $params);
}

function tg_answer_callback(string $callbackId, string $text = '', bool $alert = false)
{
    return tg_api('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text, 'show_alert' => $alert]);
}

function tg_copy_message(int $toChatId, int $fromChatId, int $messageId)
{
    return tg_api('copyMessage', ['chat_id' => $toChatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId]);
}

function tg_forward_message(int $toChatId, int $fromChatId, int $messageId)
{
    return tg_api('forwardMessage', ['chat_id' => $toChatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId]);
}

function tg_get_chat_member(string $chatId, int $userId)
{
    return tg_api('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}

function tg_set_webhook(string $url)
{
    return tg_api('setWebhook', ['url' => $url, 'allowed_updates' => ['message', 'callback_query']]);
}

function tg_delete_webhook()
{
    return tg_api('deleteWebhook');
}

/**
 * Build a single inline/reply button array with optional color style and
 * premium custom-emoji icon (Bot API's `style` / `icon_custom_emoji_id`
 * fields — supported on both InlineKeyboardButton and KeyboardButton).
 */
function tg_button(string $text, ?string $callbackData = null, ?string $url = null, ?string $style = null, ?string $emojiId = null): array
{
    $btn = ['text' => $text];
    if ($url !== null) {
        $btn['url'] = $url;
    } elseif ($callbackData !== null) {
        $btn['callback_data'] = $callbackData;
    }
    if ($style) {
        $btn['style'] = $style;
    }
    if ($emojiId) {
        $btn['icon_custom_emoji_id'] = $emojiId;
    }
    return $btn;
}

function tg_inline_markup(array $rows): array
{
    return ['inline_keyboard' => $rows];
}

function tg_reply_markup(array $rows): array
{
    return ['keyboard' => $rows, 'resize_keyboard' => true];
}

// ---------------------------------------------------------------------
// Error logging: file log + instant Telegram alert to admins
// ---------------------------------------------------------------------

function bot_log_path(): string
{
    return dirname(__DIR__) . '/bot_errors.log';
}

function bot_log(string $context, string $detail): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] $context | " . str_replace(["\r", "\n"], ' ', $detail) . "\n";
    @file_put_contents(bot_log_path(), $line, FILE_APPEND | LOCK_EX);
    error_log("$context | $detail");
}

/** Logs the error AND sends the real reason to every bot admin in Telegram. */
function notify_admins_error(string $context, string $detail): void
{
    bot_log($context, $detail);
    $cut = function_exists('mb_substr') ? mb_substr($detail, 0, 900) : substr($detail, 0, 900);
    $msg = "⚠️ خطا: $context\n\n<code>" . htmlspecialchars($cut) . '</code>';
    foreach (ADMIN_IDS as $adminId) {
        tg_send_message((int) $adminId, $msg);
    }
}

/** Last N lines of the log file (newest last). */
function bot_log_tail(int $lines = 25): string
{
    $path = bot_log_path();
    if (!is_file($path)) {
        return 'هنوز هیچ خطایی ثبت نشده.';
    }
    $all = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return implode("\n", array_slice($all, -$lines)) ?: 'هنوز هیچ خطایی ثبت نشده.';
}
