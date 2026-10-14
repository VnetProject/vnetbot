<?php

// Button position = row * 10 + column (see schema.php), so any row can hold
// up to 10 buttons and the layout is edited from the mini app (webapp/).
const MAIN_MENU_ROW_STEP = 10;

function build_main_menu_keyboard(): array
{
    // Each button's `position` is row*10+column. The customer keyboard groups
    // buttons by row, orders them by column, and skips empty/hidden rows.
    $bySlot = [];
    foreach (buttons_in('main_menu') as $b) {
        $bySlot[(int) $b['position']] = $b;
    }
    ksort($bySlot);

    $rowsBySlotRow = [];
    foreach ($bySlot as $slot => $b) {
        $rowIndex = intdiv($slot, MAIN_MENU_ROW_STEP);
        $rowsBySlotRow[$rowIndex][] = tg_button($b['label'], null, null, $b['style'], $b['emoji_custom_id']);
    }
    ksort($rowsBySlotRow);

    return tg_reply_markup(array_values($rowsBySlotRow));
}

function inline_btn(string $key, string $callbackData): array
{
    $b = get_button($key);
    return tg_button($b['label'], $callbackData, null, $b['style'], $b['emoji_custom_id']);
}

function back_btn(string $callbackData = 'back:main'): array
{
    return inline_btn('back', $callbackData);
}

function dynamic_btn(string $label, string $callbackData, ?string $style = null, ?string $emojiId = null): array
{
    return tg_button($label, $callbackData, null, $style, $emojiId);
}

/** Inline "copy to clipboard" button (Telegram copy_text), styled like any other configured button. */
function inline_copy_btn(string $key, string $textToCopy): array
{
    $b = get_button($key);
    $btn = ['text' => $b['label'], 'copy_text' => ['text' => $textToCopy]];
    if (!empty($b['style'])) {
        $btn['style'] = $b['style'];
    }
    if (!empty($b['emoji_custom_id'])) {
        $btn['icon_custom_emoji_id'] = $b['emoji_custom_id'];
    }
    return $btn;
}
