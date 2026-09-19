"""
Builds inline ("glass" / شیشه‌ای) keyboards for the whole bot.

Uses the real Telegram Bot API 9.4 button styling (`style`: primary/success/
danger, and `icon_custom_emoji_id` for a Premium custom-emoji icon before the
label) - configurable per button from the admin panel (adm_buttons section)
and stored in Settings.button_colors as:
    { "<button_key>": {"label": str|None, "style": str|None, "emoji_id": str|None} }
"""
from __future__ import annotations

from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup

STYLE_CHOICES = {
    "default": None,
    "blue": "primary",
    "green": "success",
    "red": "danger",
}

BUTTON_KEYS = {
    "buy_panel": "خرید نمایندگی",
    "wallet": "کیف پول",
    "my_panels": "لیست نمایندگی هام",
    "get_test": "دریافت تست",
    "faq": "سوالات متداول",
    "rules": "قوانین",
    "support": "پشتیبانی",
    "back": "بازگشت (همه‌جا)",
}


def styled_button(default_text: str, callback_data: str, key: str, settings) -> InlineKeyboardButton:
    cfg = (settings.button_colors or {}).get(key, {}) or {}
    kwargs = {}
    if cfg.get("style"):
        kwargs["style"] = cfg["style"]
    if cfg.get("emoji_id"):
        kwargs["icon_custom_emoji_id"] = cfg["emoji_id"]
    label = cfg.get("label") or default_text
    return InlineKeyboardButton(text=label, callback_data=callback_data, **kwargs)


def main_menu(settings) -> InlineKeyboardMarkup:
    rows = [
        [styled_button("خرید نمایندگی", "buy_panel", "buy_panel", settings)],
        [
            styled_button("کیف پول", "wallet", "wallet", settings),
            styled_button("لیست نمایندگی هام", "my_panels", "my_panels", settings),
        ],
        [styled_button("دریافت تست", "get_test", "get_test", settings)],
        [
            styled_button("سوالات متداول", "faq", "faq", settings),
            styled_button("قوانین", "rules", "rules", settings),
        ],
        [styled_button("پشتیبانی", "support", "support", settings)],
    ]
    return InlineKeyboardMarkup(inline_keyboard=rows)


def admin_main_menu(settings) -> InlineKeyboardMarkup:
    rows = [
        [InlineKeyboardButton(text="📦 پلن‌ها", callback_data="adm_plans")],
        [InlineKeyboardButton(text="🖥 پنل‌ها", callback_data="adm_panels")],
        [InlineKeyboardButton(text="⚙️ تنظیمات", callback_data="adm_settings")],
        [InlineKeyboardButton(text="👥 کاربران", callback_data="adm_users")],
        [InlineKeyboardButton(text="📢 پیام همگانی", callback_data="adm_broadcast")],
        [InlineKeyboardButton(text="📣 کانال‌ها", callback_data="adm_channels")],
        [InlineKeyboardButton(text="📝 متن‌های ربات", callback_data="adm_texts")],
        [InlineKeyboardButton(text="🎨 دکمه‌ها (رنگ/متن/ایموجی)", callback_data="adm_buttons")],
    ]
    return InlineKeyboardMarkup(inline_keyboard=rows)


def back_button(callback_data: str, settings) -> InlineKeyboardButton:
    return styled_button("بازگشت", callback_data, "back", settings)


def with_back(rows: list[list[InlineKeyboardButton]], back_cb: str, settings) -> InlineKeyboardMarkup:
    rows = list(rows)
    rows.append([back_button(back_cb, settings)])
    return InlineKeyboardMarkup(inline_keyboard=rows)


def cancel_kb(settings, target: str = "cancel_fsm") -> InlineKeyboardMarkup:
    """A lone cancel/back button to attach to every FSM text-prompt message,
    so the user is never stuck mid-flow with no way back."""
    return InlineKeyboardMarkup(inline_keyboard=[[back_button(target, settings)]])
